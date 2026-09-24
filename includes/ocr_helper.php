<?php

function ve_ocr_default_result(string $status, string $notes = ''): array {
    return [
        'status' => $status,
        'text' => '',
        'reference' => '',
        'amount' => null,
        'notes' => $notes,
        'scanned_at' => date('Y-m-d H:i:s'),
    ];
}

function ve_ocr_command_exists(string $command): ?string {
    if (!function_exists('exec')) {
        return null;
    }

    $lookup = stripos(PHP_OS, 'WIN') === 0
        ? 'where ' . escapeshellarg($command)
        : 'command -v ' . escapeshellarg($command);

    $output = [];
    $code = 1;
    @exec($lookup, $output, $code);

    return ($code === 0 && !empty($output[0])) ? trim($output[0]) : null;
}

function ve_ocr_tesseract_path(): ?string {
    $candidates = [];
    $envPath = getenv('TESSERACT_PATH');
    if ($envPath) {
        $candidates[] = $envPath;
    }

    $candidates[] = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
    $candidates[] = 'C:\\Program Files (x86)\\Tesseract-OCR\\tesseract.exe';

    foreach ($candidates as $candidate) {
        if ($candidate && is_file($candidate)) {
            return $candidate;
        }
    }

    return ve_ocr_command_exists('tesseract');
}

function ve_ocr_normalize_text(string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = preg_replace("/[ \t]+/", ' ', $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);
    return trim(substr($text, 0, 12000));
}

function ve_ocr_run_tesseract(string $tesseractPath, string $absoluteFilePath): array {
    if (function_exists('proc_open')) {
        $command = [$tesseractPath, $absoluteFilePath, 'stdout', '-l', 'eng', '--psm', '6'];
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            return ['ok' => false, 'output' => 'Unable to start the OCR process.'];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return ['ok' => false, 'output' => trim($stderr ?: $stdout)];
        }

        return ['ok' => true, 'output' => (string)$stdout];
    }

    if (!function_exists('shell_exec')) {
        return ['ok' => false, 'output' => 'PHP command execution is disabled.'];
    }

    $command = escapeshellarg($tesseractPath) . ' ' . escapeshellarg($absoluteFilePath) . ' stdout -l eng --psm 6 2>&1';
    $output = @shell_exec($command);
    return ['ok' => is_string($output), 'output' => (string)$output];
}

function ve_ocr_extract_reference(string $text): string {
    $patterns = [
        '/(?:reference|ref(?:erence)?\s*(?:no|number)?|transaction|txn|trace|receipt)\D{0,30}([A-Z0-9][A-Z0-9-]{5,})/i',
        '/\b([0-9]{10,18})\b/',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            return strtoupper(trim($matches[1]));
        }
    }

    return '';
}

function ve_ocr_extract_amount(string $text): ?float {
    $patterns = [
        '/(?:PHP|PESO|PESOS|AMOUNT|TOTAL|PAID|SENT|TRANSFERRED)\s*(?:[:\-]?\s*)(?:PHP|P|₱)?\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{1,2})?|[0-9]+(?:\.[0-9]{1,2})?)/i',
        '/(?:PHP|P|₱)\s*([0-9]{1,3}(?:,[0-9]{3})*(?:\.[0-9]{1,2})?|[0-9]+(?:\.[0-9]{1,2})?)/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $matches)) {
            $amount = (float)str_replace(',', '', $matches[1]);
            if ($amount > 0 && $amount < 1000000) {
                return $amount;
            }
        }
    }

    return null;
}

function ve_scan_payment_proof_ocr(string $absoluteFilePath, string $paymentMethod, float $expectedAmount = 2000.0): array {
    if ($paymentMethod === 'cash') {
        return ve_ocr_default_result('not_applicable', 'Cash payment uses a valid ID upload. OCR is skipped to avoid reading sensitive ID details.');
    }

    if (!is_file($absoluteFilePath)) {
        return ve_ocr_default_result('failed', 'Uploaded file could not be found for OCR scanning.');
    }

    $extension = strtolower(pathinfo($absoluteFilePath, PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        return ve_ocr_default_result('unsupported_file', 'OCR currently supports image proofs only. PDF files are saved for manual admin review.');
    }

    $tesseractPath = ve_ocr_tesseract_path();
    if (!$tesseractPath) {
        return ve_ocr_default_result('unavailable', 'Tesseract OCR is not installed or not found in PATH. Install Tesseract to enable automatic text scanning.');
    }

    $result = ve_ocr_run_tesseract($tesseractPath, $absoluteFilePath);
    if (!$result['ok']) {
        return ve_ocr_default_result('failed', 'OCR scan failed: ' . substr($result['output'] ?: 'No error details returned.', 0, 500));
    }

    $text = ve_ocr_normalize_text($result['output']);
    if ($text === '') {
        return ve_ocr_default_result('no_text', 'OCR ran successfully but no readable text was detected.');
    }

    $amount = ve_ocr_extract_amount($text);
    $reference = ve_ocr_extract_reference($text);
    $notes = [];

    if ($amount !== null) {
        $notes[] = abs($amount - $expectedAmount) < 0.01
            ? 'Detected amount matches the PHP ' . number_format($expectedAmount, 2) . ' reservation fee.'
            : 'Detected amount is PHP ' . number_format($amount, 2) . '; admin should compare it with the expected reservation fee.';
    } else {
        $notes[] = 'No payment amount was detected.';
    }

    $notes[] = $reference !== ''
        ? 'Possible reference number detected.'
        : 'No reference number was detected.';

    return [
        'status' => 'scanned',
        'text' => $text,
        'reference' => $reference,
        'amount' => $amount,
        'notes' => implode(' ', $notes),
        'scanned_at' => date('Y-m-d H:i:s'),
    ];
}

?>
