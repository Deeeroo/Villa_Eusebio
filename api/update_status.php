<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();
include '../includes/db.php';
include '../includes/booking_availability.php';
require_once '../includes/mailer.php';
ve_ensure_capstone2_schema($conn);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? '';
$rejectReasonChoice = trim($_POST['reject_reason'] ?? '');
$rejectReasonCustom = trim($_POST['reject_note'] ?? '');
$rejectReason = $rejectReasonCustom !== '' ? $rejectReasonCustom : $rejectReasonChoice;
if ($id <= 0 || !in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
    die('Invalid request.');
}

function redirectWithMessage($type, $message) {
    $redirect = $_POST['redirect'] ?? 'admin-panel';
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
$currentStatus = strtolower($booking['status'] ?? '');
$today = date('Y-m-d');
if ($status === 'cancelled') {
    if ($currentStatus !== 'approved') {
        redirectWithMessage('error', 'Only approved bookings can be cancelled.');
    }
    if (!empty($booking['check_out_date']) && $booking['check_out_date'] < $today) {
        redirectWithMessage('error', 'Completed bookings cannot be cancelled.');
    }
}

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

    if ($status === 'cancelled') {
        $paidReservationFee = 'paid';
        $unpaidBalance = 'unpaid';
        $zeroBalance = 0.0;
        $paymentUpdateStmt = mysqli_prepare($conn, 'UPDATE payments SET reservation_fee_status = ?, remaining_balance = ?, payment_status = ? WHERE booking_id = ?');
        if ($paymentUpdateStmt) {
            mysqli_stmt_bind_param($paymentUpdateStmt, 'sdsi', $paidReservationFee, $zeroBalance, $unpaidBalance, $id);
            mysqli_stmt_execute($paymentUpdateStmt);
            mysqli_stmt_close($paymentUpdateStmt);
        }
        $chargeZeroStmt = mysqli_prepare($conn, 'UPDATE charges SET base_stay_value = 0, extra_guest_count = 0, extra_guest_fee = 0, total_stay_value = 0 WHERE booking_id = ?');
        if ($chargeZeroStmt) {
            mysqli_stmt_bind_param($chargeZeroStmt, 'i', $id);
            mysqli_stmt_execute($chargeZeroStmt);
            mysqli_stmt_close($chargeZeroStmt);
        }

        $updateStmt = mysqli_prepare($conn, 'UPDATE bookings SET status = ?, rejection_reason = ? WHERE booking_id = ?');
        mysqli_stmt_bind_param($updateStmt, 'ssi', $status, $rejectReason, $id);
    } elseif ($status === 'rejected') {
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

$message = $status === 'cancelled' ? 'Booking cancelled successfully.' : 'Booking status updated successfully.';
$messageType = 'success';

if (in_array($status, ['approved', 'rejected', 'cancelled'], true) && $currentStatus !== $status) {
    $emailResult = ve_send_booking_status_email($conn, $id, $status, $rejectReason);
    if ($emailResult['ok']) {
        $message .= ' Customer email sent.';
    } else {
        $messageType = 'error';
        $message .= ' However, the customer email was not sent: ' . $emailResult['message'];
    }
}

redirectWithMessage($messageType, $message);
