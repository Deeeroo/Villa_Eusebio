<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
header('Content-Type: application/json');

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$reservationPending = 0;

$reservationResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM bookings WHERE status = 'pending' AND COALESCE(archived, 0) = 0");
if ($reservationResult) {
    $reservationPending = (int)(mysqli_fetch_assoc($reservationResult)['total'] ?? 0);
}

echo json_encode([
    'reservation_pending' => $reservationPending
]);
?>
