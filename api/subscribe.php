<?php
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$email = trim($_POST['email'] ?? '');
$wantsJson = (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
) || (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch'
);

function subscriber_response(bool $ok, string $message, bool $json): void {
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message]);
    } else {
        header('Location: ../index.php?subscribe=' . urlencode($ok ? 'success' : 'error'));
    }
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    subscriber_response(false, 'Please enter a valid email address.', $wantsJson);
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
    subscriber_response(false, 'This email is already subscribed.', $wantsJson);
}

$stmt = mysqli_prepare($conn, "INSERT INTO email_subscribers (email) VALUES (?)");
if (!$stmt) {
    subscriber_response(false, 'Unable to save your email right now.', $wantsJson);
}

mysqli_stmt_bind_param($stmt, 's', $email);
$saved = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

subscriber_response($saved, $saved ? 'Subscribed successfully.' : 'Unable to save your email right now.', $wantsJson);
?>
