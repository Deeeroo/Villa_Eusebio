<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$redirect = $_POST['redirect'] ?? 'archive_reservation';
$allowed = ['archive', 'archive_reservation', 'archive_sales_record'];
if (!in_array($redirect, $allowed, true)) $redirect = 'archive_reservation';
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE bookings SET archived = 0, archived_at = NULL WHERE booking_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
header('Location: ../pages/' . $redirect . '.php?success=' . urlencode('Booking restored.'));
exit;
?>
