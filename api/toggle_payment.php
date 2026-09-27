<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
include '../includes/db.php';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT payment_status, reservation_fee_status FROM payments WHERE booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        if (($row['reservation_fee_status'] ?? '') !== 'paid') {
            mysqli_stmt_close($stmt);
            header('Location: ../pages/sales.php?error=' . urlencode('Mark the reservation fee as paid first.'));
            exit;
        }
        if (($row['payment_status'] ?? '') !== 'paid') {
            $newStatus = 'paid';
            $update = mysqli_prepare($conn, "UPDATE payments SET payment_status = ?, remaining_balance = 0 WHERE booking_id = ?");
            mysqli_stmt_bind_param($update, 'si', $newStatus, $id);
            mysqli_stmt_execute($update);
            mysqli_stmt_close($update);
        }
    }
    mysqli_stmt_close($stmt);
}
header('Location: ../pages/sales.php');
exit;
