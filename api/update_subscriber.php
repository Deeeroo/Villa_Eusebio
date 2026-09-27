<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

function redirect_subscribers(string $message, bool $isError = false): void {
    $key = $isError ? 'error' : 'success';
    header('Location: ../pages/subscribers.php?' . $key . '=' . urlencode($message));
    exit;
}

function save_subscriber_log(mysqli $conn, string $note): void {
    ve_audit_log($conn, 'Subscribers', $note);
}

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $subscriberId = (int)($_POST['subscriber_id'] ?? 0);
    if ($subscriberId <= 0) {
        redirect_subscribers('Subscriber was not found.', true);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM email_subscribers WHERE subscriber_id = ?");
    if (!$stmt) {
        redirect_subscribers('Unable to delete subscriber right now.', true);
    }
    mysqli_stmt_bind_param($stmt, 'i', $subscriberId);
    $saved = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($saved) {
        save_subscriber_log($conn, 'Deleted subscriber email.');
        redirect_subscribers('Subscriber deleted.');
    }
    redirect_subscribers('Unable to delete subscriber right now.', true);
}

redirect_subscribers('Subscribers can only be deleted from this page.', true);
?>
