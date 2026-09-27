<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
include '../includes/db.php';
include '../includes/booking_availability.php';
ve_ensure_capstone2_schema($conn);

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$status = $_REQUEST['status'] ?? '';
$rejectReasonChoice = trim($_REQUEST['reject_reason'] ?? '');
$rejectReasonCustom = trim($_REQUEST['reject_note'] ?? '');
$rejectReason = $rejectReasonCustom !== '' ? $rejectReasonCustom : $rejectReasonChoice;
if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    die('Invalid request.');
}

function redirectWithMessage($type, $message) {
    $redirect = $_GET['redirect'] ?? 'admin-panel';
    $allowed = ['admin-panel', 'reservation'];
    if (!in_array($redirect, $allowed, true)) $redirect = 'admin-panel';
    header("Location: ../pages/{$redirect}.php?{$type}=" . urlencode($message));
    exit;
}

$booking = ve_fetch_booking_by_id($conn, $id);
if (!$booking) {
    redirectWithMessage('error', 'Booking not found.');
}
$requiredSlots = ve_required_slots($booking['time_type']);
if (empty($requiredSlots)) {
    redirectWithMessage('error', 'Invalid stay type for this booking.');
}
$slotDate = $booking['check_in_date'];

mysqli_begin_transaction($conn);
try {
    if ($status === 'approved') {
        $availability = ve_check_date_availability($conn, $slotDate, $booking['time_type'], $id);
        if (!$availability['available']) {
            throw new Exception($availability['message']);
        }
        ve_replace_booking_slots($conn, $id, $slotDate, $booking['time_type']);

        $paidReservationFee = 'paid';
        $paymentUpdateStmt = mysqli_prepare($conn, 'UPDATE payments SET reservation_fee_status = ? WHERE booking_id = ?');
        if ($paymentUpdateStmt) {
            mysqli_stmt_bind_param($paymentUpdateStmt, 'si', $paidReservationFee, $id);
            mysqli_stmt_execute($paymentUpdateStmt);
            mysqli_stmt_close($paymentUpdateStmt);
        }
    } else {
        $deleteStmt = mysqli_prepare($conn, 'DELETE FROM booked_dates WHERE booking_id = ?');
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'i', $id);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }
    }

    if ($status === 'rejected') {
        $updateStmt = mysqli_prepare($conn, 'UPDATE bookings SET status = ?, rejection_reason = ? WHERE booking_id = ?');
        mysqli_stmt_bind_param($updateStmt, 'ssi', $status, $rejectReason, $id);
    } else {
        $emptyReason = '';
        $updateStmt = mysqli_prepare($conn, 'UPDATE bookings SET status = ?, rejection_reason = ? WHERE booking_id = ?');
        mysqli_stmt_bind_param($updateStmt, 'ssi', $status, $emptyReason, $id);
    }
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    redirectWithMessage('error', $e->getMessage());
}

redirectWithMessage('success', 'Booking status updated successfully.');
