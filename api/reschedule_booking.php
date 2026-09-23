<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    die('Unauthorized access.');
}

include '../includes/db.php';
include '../includes/booking_repository.php';
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

function reschedule_required_slots($timeType) {
    if ($timeType === 'day') return ['day'];
    if ($timeType === 'overnight') return ['overnight'];
    if ($timeType === '22hour') return ['day', 'overnight'];
    return [];
}

function reschedule_conflict_date_label($dateValue) {
    $time = strtotime($dateValue);
    return $time ? date('l, F j, Y', $time) : $dateValue;
}

function reschedule_conflict_slot_label($slot) {
    if ($slot === 'day') return 'Day Tour';
    if ($slot === 'overnight') return 'Overnight Stay';
    if ($slot === '22hour') return '22-Hour Stay';
    if ($slot === 'whole' || $slot === 'blocked') return 'Whole Day';
    return ucwords(str_replace(['_', '-'], ' ', (string)$slot));
}

function reschedule_conflict_message($dateValue, $slot) {
    return 'Conflict Detected on ' . reschedule_conflict_date_label($dateValue) . ' (' . reschedule_conflict_slot_label($slot) . ' Already Booked).';
}

function reschedule_checkout_date($checkIn, $timeType) {
    $date = new DateTime($checkIn);
    if ($timeType === 'overnight' || $timeType === '22hour') {
        $date->modify('+1 day');
    }
    return $date->format('Y-m-d');
}

if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkIn) || !in_array($timeType, ['day', 'overnight', '22hour'], true)) {
    redirect_reschedule('error', 'Invalid reschedule request.', $redirect);
}

$booking = ve_fetch_booking_by_id($conn, $id);
if (!$booking) {
    redirect_reschedule('error', 'Booking not found.', $redirect);
}

$requiredSlots = reschedule_required_slots($timeType);
if (empty($requiredSlots)) {
    redirect_reschedule('error', 'Invalid stay type for this booking.', $redirect);
}

$today = date('Y-m-d');
if ($checkIn < $today) {
    redirect_reschedule('error', 'Past dates are not allowed for rescheduling.', $redirect);
}

$checkOut = reschedule_checkout_date($checkIn, $timeType);
$status = strtolower($booking['status'] ?? '');

mysqli_begin_transaction($conn);
try {
    $conflictStmt = mysqli_prepare($conn, "SELECT time_type FROM booked_dates WHERE booking_id != ? AND booked_date = ?");
    if (!$conflictStmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($conflictStmt, 'is', $id, $checkIn);
    mysqli_stmt_execute($conflictStmt);
    $conflictResult = mysqli_stmt_get_result($conflictStmt);
    $existingSlots = [];
    while ($row = mysqli_fetch_assoc($conflictResult)) {
        $existingSlots[] = $row['time_type'];
    }
    mysqli_stmt_close($conflictStmt);

    $blockStmt = mysqli_prepare($conn, "SELECT stay_type FROM admin_blocks WHERE blocked_date = ?");
    if ($blockStmt) {
        mysqli_stmt_bind_param($blockStmt, 's', $checkIn);
        mysqli_stmt_execute($blockStmt);
        $blockResult = mysqli_stmt_get_result($blockStmt);
        while ($block = mysqli_fetch_assoc($blockResult)) {
            if ($block['stay_type'] === '22hour' || $block['stay_type'] === 'whole') {
                $existingSlots[] = 'day';
                $existingSlots[] = 'overnight';
            } else {
                $existingSlots[] = $block['stay_type'];
            }
        }
        mysqli_stmt_close($blockStmt);
    }

    $existingSlots = array_values(array_unique($existingSlots));
    if ($timeType === '22hour' && !empty($existingSlots)) {
        throw new Exception(reschedule_conflict_message($checkIn, $existingSlots[0] ?? 'day'));
    }
    if (in_array('day', $existingSlots, true) && in_array('overnight', $existingSlots, true)) {
        throw new Exception(reschedule_conflict_message($checkIn, 'whole'));
    }
    foreach ($requiredSlots as $slot) {
        if (in_array($slot, $existingSlots, true)) {
            throw new Exception(reschedule_conflict_message($checkIn, $slot));
        }
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

    $deleteStmt = mysqli_prepare($conn, "DELETE FROM booked_dates WHERE booking_id = ?");
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'i', $id);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }

    if ($status === 'approved') {
        $insertStmt = mysqli_prepare($conn, "INSERT INTO booked_dates (booking_id, booked_date, time_type) VALUES (?, ?, ?)");
        if (!$insertStmt) {
            throw new Exception(mysqli_error($conn));
        }
        foreach ($requiredSlots as $slot) {
            mysqli_stmt_bind_param($insertStmt, 'iss', $id, $checkIn, $slot);
            mysqli_stmt_execute($insertStmt);
        }
        mysqli_stmt_close($insertStmt);
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    redirect_reschedule('error', $e->getMessage(), $redirect);
}

redirect_reschedule('success', 'Reservation rescheduled successfully.', $redirect);
?>
