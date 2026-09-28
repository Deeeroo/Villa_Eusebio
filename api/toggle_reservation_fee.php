<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();
include '../includes/db.php';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE payments SET reservation_fee_status = 'paid' WHERE booking_id = ? AND reservation_fee_status <> 'paid'");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
header('Location: ../pages/sales.php?success=' . urlencode('Reservation fee marked as paid.'));
exit;
