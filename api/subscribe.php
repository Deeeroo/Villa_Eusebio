<?php
session_start();
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

const SUBSCRIBER_COOLDOWN_SECONDS = 120;

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

$lastSubscriberAttempt = (int)($_SESSION['subscriber_last_attempt_at'] ?? 0);
$cooldownRemaining = SUBSCRIBER_COOLDOWN_SECONDS - (time() - $lastSubscriberAttempt);
if ($cooldownRemaining > 0) {
    subscriber_response(
        false,
        'Please wait ' . $cooldownRemaining . ' seconds before subscribing another email.',
        $wantsJson,
        ['cooldown_seconds' => $cooldownRemaining]
    );
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
    $_SESSION['subscriber_last_attempt_at'] = time();
    subscriber_response(
        false,
        'This email is already subscribed. Please wait 2 minutes before trying another email.',
        $wantsJson,
        ['cooldown_seconds' => SUBSCRIBER_COOLDOWN_SECONDS]
    );
}

$stmt = mysqli_prepare($conn, "INSERT INTO email_subscribers (email) VALUES (?)");
if (!$stmt) {
    subscriber_response(false, 'Unable to save your email right now.', $wantsJson);
}

mysqli_stmt_bind_param($stmt, 's', $email);
$saved = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if ($saved) {
    $_SESSION['subscriber_last_attempt_at'] = time();
}

subscriber_response(
    $saved,
    $saved ? 'Subscribed successfully. Please wait 2 minutes before adding another email.' : 'Unable to save your email right now.',
    $wantsJson,
    $saved ? ['cooldown_seconds' => SUBSCRIBER_COOLDOWN_SECONDS] : []
);
?>
