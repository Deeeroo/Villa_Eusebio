<?php
session_start();
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

const SUBSCRIBER_LIMIT_BEFORE_COOLDOWN = 2;
const SUBSCRIBER_COOLDOWN_SECONDS = 300;

$email = strtolower(trim($_POST['email'] ?? ''));
$wantsJson = (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch'
);

function subscriber_response(bool $ok, string $message, bool $json, array $extra = []): void {
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra));
    } else {
        header('Location: ../index.php?subscribe=' . urlencode($ok ? 'success' : 'error'));
    }
    exit;
}

function subscriber_email_error(string $email): string {
    if ($email === '' || strlen($email) > 190 || preg_match('/\s/', $email)) {
        return 'Please enter a valid email address.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    if (!preg_match('/^[^\s@]+@([A-Za-z0-9-]+\.)+[A-Za-z]{2,}$/', $email)) {
        return 'Please enter a valid email address.';
    }

    $domain = substr(strrchr($email, '@') ?: '', 1);
    $localPart = substr($email, 0, strpos($email, '@'));
    if (!preg_match('/[a-z]/i', $localPart)) {
        return 'Please enter a real email address, not only numbers or symbols.';
    }
    foreach (explode('.', $domain) as $label) {
        if ($label === '' || $label[0] === '-' || substr($label, -1) === '-') {
            return 'Please enter a valid email domain.';
        }
    }

    if (is_likely_misspelled_common_domain($domain)) {
        return 'Please enter a working email address.';
    }

    if (!checkdnsrr($domain, 'MX')) {
        return 'Please enter a working email address.';
    }

    return '';
}

function is_likely_misspelled_common_domain(string $domain): bool {
    $commonDomains = [
        'gmail.com',
        'yahoo.com',
        'outlook.com',
        'hotmail.com',
        'icloud.com',
        'proton.me',
        'protonmail.com',
        'live.com',
        'aol.com',
    ];

    if (in_array($domain, $commonDomains, true)) {
        return false;
    }

    foreach ($commonDomains as $commonDomain) {
        $distance = levenshtein($domain, $commonDomain);
        if ($distance > 0 && $distance <= 2) {
            return true;
        }

        [$domainName] = explode('.', $domain, 2);
        [$commonName] = explode('.', $commonDomain, 2);
        if ($domainName !== $commonName && levenshtein($domainName, $commonName) === 1) {
            return true;
        }
    }

    return false;
}

$emailError = subscriber_email_error($email);
if ($emailError !== '') {
    subscriber_response(false, $emailError, $wantsJson);
}

$cooldownUntil = (int)($_SESSION['subscriber_cooldown_until'] ?? 0);
$cooldownRemaining = max(0, $cooldownUntil - time());
if ($cooldownRemaining > 0) {
    subscriber_response(
        false,
        'Please wait before subscribing another email.',
        $wantsJson,
        ['cooldown_seconds' => $cooldownRemaining]
    );
}

if ($cooldownUntil > 0) {
    $_SESSION['subscriber_cooldown_until'] = 0;
    $_SESSION['subscriber_success_count'] = 0;
}

$checkStmt = mysqli_prepare($conn, "SELECT subscriber_id FROM email_subscribers WHERE email = ? LIMIT 1");
if (!$checkStmt) {
    subscriber_response(false, 'Unable to save your email right now.', $wantsJson);
}
mysqli_stmt_bind_param($checkStmt, 's', $email);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$alreadySubscribed = $checkResult && mysqli_num_rows($checkResult) > 0;
mysqli_stmt_close($checkStmt);

if ($alreadySubscribed) {
    subscriber_response(
        false,
        'This email is already subscribed.',
        $wantsJson
    );
}

$stmt = mysqli_prepare($conn, "INSERT INTO email_subscribers (email) VALUES (?)");
if (!$stmt) {
    subscriber_response(false, 'Unable to save your email right now.', $wantsJson);
}

mysqli_stmt_bind_param($stmt, 's', $email);
$saved = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$cooldownSeconds = 0;
if ($saved) {
    $successCount = (int)($_SESSION['subscriber_success_count'] ?? 0) + 1;
    if ($successCount >= SUBSCRIBER_LIMIT_BEFORE_COOLDOWN) {
        $_SESSION['subscriber_success_count'] = 0;
        $_SESSION['subscriber_cooldown_until'] = time() + SUBSCRIBER_COOLDOWN_SECONDS;
        $cooldownSeconds = SUBSCRIBER_COOLDOWN_SECONDS;
    } else {
        $_SESSION['subscriber_success_count'] = $successCount;
    }
}

subscriber_response(
    $saved,
    $saved
        ? ($cooldownSeconds > 0 ? 'Subscribed successfully. You can add another email in 5 minutes.' : 'Subscribed successfully.')
        : 'Unable to save your email right now.',
    $wantsJson,
    $cooldownSeconds > 0 ? ['cooldown_seconds' => $cooldownSeconds] : []
);
?>
