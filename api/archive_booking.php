<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = $_POST['redirect'] ?? 'reservation';
$allowed = ['reservation', 'sales', 'archive'];
if (!in_array($redirect, $allowed, true)) $redirect = 'reservation';

if ($id <= 0) {
    header("Location: ../pages/{$redirect}.php?error=" . urlencode('Invalid booking selected.'));
    exit;
}

$stmt = mysqli_prepare($conn, "UPDATE bookings SET archived = 1, archived_at = NOW() WHERE booking_id = ?");
if (!$stmt) {
    header("Location: ../pages/{$redirect}.php?error=" . urlencode('Database error.'));
    exit;
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

$delete = mysqli_prepare($conn, "DELETE FROM booked_dates WHERE booking_id = ?");
if ($delete) {
    mysqli_stmt_bind_param($delete, 'i', $id);
    mysqli_stmt_execute($delete);
    mysqli_stmt_close($delete);
}

header("Location: ../pages/{$redirect}.php?success=" . urlencode('Booking moved to archive.'));
exit;
?>
