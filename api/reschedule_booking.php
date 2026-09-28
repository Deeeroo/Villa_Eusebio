<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();

include '../includes/db.php';
include '../includes/booking_availability.php';
ve_ensure_capstone2_schema($conn);

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$checkIn = trim($_POST['check_in_date'] ?? '');
$timeType = trim($_POST['time_type'] ?? '');
$redirect = $_POST['redirect'] ?? 'reservation';
$allowedRedirects = ['reservation', 'sales', 'admin-panel'];
if (!in_array($redirect, $allowedRedirects, true)) {
    $redirect = 'reservation';
}

function redirect_reschedule($type, $message, $redirect) {
    header("Location: ../pages/{$redirect}.php?{$type}=" . urlencode($message));
    exit;
}

function days_until_date(string $dateValue): int {
    $today = new DateTimeImmutable(date('Y-m-d'));
    $target = DateTimeImmutable::createFromFormat('Y-m-d', $dateValue);
    if (!$target) {
        return -1;
    }
    return (int)$today->diff($target)->format('%r%a');
}

if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !in_array($timeType, ['day', 'overnight', '22hour'], true)) {
    redirect_reschedule('error', 'Invalid reschedule request.', $redirect);
}

$booking = ve_fetch_booking_by_id($conn, $id);
if (!$booking) {
    redirect_reschedule('error', 'Booking not found.', $redirect);
}

$requiredSlots = ve_required_slots($timeType);
if (empty($requiredSlots)) {
    redirect_reschedule('error', 'Invalid stay type for this booking.', $redirect);
}

$today = date('Y-m-d');
if ($checkIn < $today) {
    redirect_reschedule('error', 'Past dates are not allowed for rescheduling.', $redirect);
}

$checkOut = ve_checkout_date($checkIn, $timeType);
$status = strtolower($booking['status'] ?? '');
$currentCheckIn = (string)($booking['check_in_date'] ?? '');
$currentCheckout = (string)($booking['check_out_date'] ?? '');
if (!in_array($status, ['pending', 'approved'], true)) {
    redirect_reschedule('error', 'Only pending or approved reservations can be rescheduled.', $redirect);
}
if (days_until_date($currentCheckIn) < 5) {
    redirect_reschedule('error', 'Rescheduling is only allowed at least 5 days before the current check-in date.', $redirect);
}
if ($currentCheckout !== '' && $currentCheckout < $today) {
    redirect_reschedule('error', 'Completed reservations cannot be rescheduled.', $redirect);
}

mysqli_begin_transaction($conn);
try {
    $availability = ve_check_date_availability($conn, $checkIn, $timeType, $id);
    if (!$availability['available']) {
        throw new Exception($availability['message']);
    }

    $updateStmt = mysqli_prepare($conn, "UPDATE bookings SET check_in_date = ?, check_out_date = ?, time_type = ? WHERE booking_id = ?");
    if (!$updateStmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($updateStmt, 'sssi', $checkIn, $checkOut, $timeType, $id);
    mysqli_stmt_execute($updateStmt);
    mysqli_stmt_close($updateStmt);

    $baseStayValue = ve_get_base_price($timeType);
    $guestCount = isset($booking['guests']) ? (int)$booking['guests'] : 0;
    $extraGuestCount = max($guestCount - 30, 0);
    $extraGuestFee = $extraGuestCount * 150.0;
    $totalStayValue = $baseStayValue + $extraGuestFee;
    $reservationFeeAmount = isset($booking['reservation_fee_amount']) ? (float)$booking['reservation_fee_amount'] : 2000.0;
    $paymentStatus = strtolower($booking['payment_status'] ?? 'unpaid');
    $remainingBalance = $paymentStatus === 'paid' ? 0.0 : max($totalStayValue - $reservationFeeAmount, 0);

    $chargeUpdateStmt = mysqli_prepare($conn, "UPDATE charges SET base_stay_value = ?, extra_guest_count = ?, extra_guest_fee = ?, total_stay_value = ? WHERE booking_id = ?");
    if ($chargeUpdateStmt) {
        mysqli_stmt_bind_param($chargeUpdateStmt, 'diddi', $baseStayValue, $extraGuestCount, $extraGuestFee, $totalStayValue, $id);
        mysqli_stmt_execute($chargeUpdateStmt);
        $chargeRows = mysqli_stmt_affected_rows($chargeUpdateStmt);
        mysqli_stmt_close($chargeUpdateStmt);
        if ($chargeRows === 0) {
            $chargeExistsStmt = mysqli_prepare($conn, "SELECT booking_id FROM charges WHERE booking_id = ? LIMIT 1");
            $chargeExists = false;
            if ($chargeExistsStmt) {
                mysqli_stmt_bind_param($chargeExistsStmt, 'i', $id);
                mysqli_stmt_execute($chargeExistsStmt);
                $chargeExistsResult = mysqli_stmt_get_result($chargeExistsStmt);
                $chargeExists = $chargeExistsResult && mysqli_num_rows($chargeExistsResult) > 0;
                mysqli_stmt_close($chargeExistsStmt);
            }
            if (!$chargeExists) {
                $chargeInsertStmt = mysqli_prepare($conn, "INSERT INTO charges (booking_id, base_stay_value, extra_guest_count, extra_guest_fee, total_stay_value) VALUES (?, ?, ?, ?, ?)");
                if ($chargeInsertStmt) {
                    mysqli_stmt_bind_param($chargeInsertStmt, 'ididd', $id, $baseStayValue, $extraGuestCount, $extraGuestFee, $totalStayValue);
                    mysqli_stmt_execute($chargeInsertStmt);
                    mysqli_stmt_close($chargeInsertStmt);
                }
            }
        }
    }

    $paymentUpdateStmt = mysqli_prepare($conn, "UPDATE payments SET remaining_balance = ? WHERE booking_id = ?");
    if ($paymentUpdateStmt) {
        mysqli_stmt_bind_param($paymentUpdateStmt, 'di', $remainingBalance, $id);
        mysqli_stmt_execute($paymentUpdateStmt);
        mysqli_stmt_close($paymentUpdateStmt);
    }

    if ($status === 'approved') {
        ve_replace_booking_slots($conn, $id, $checkIn, $timeType);
    } else {
        $deleteStmt = mysqli_prepare($conn, "DELETE FROM booked_dates WHERE booking_id = ?");
        if ($deleteStmt) {
            mysqli_stmt_bind_param($deleteStmt, 'i', $id);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    redirect_reschedule('error', $e->getMessage(), $redirect);
}

redirect_reschedule('success', 'Reservation rescheduled successfully.', $redirect);
?>
