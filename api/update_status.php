<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    die('Unauthorized access.');
}
include '../includes/db.php';
include '../includes/booking_repository.php';
ve_ensure_capstone2_schema($conn);

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$status = $_REQUEST['status'] ?? '';
$rejectReasonChoice = trim($_REQUEST['reject_reason'] ?? '');
$rejectReasonCustom = trim($_REQUEST['reject_note'] ?? '');
$rejectReason = $rejectReasonCustom !== '' ? $rejectReasonCustom : $rejectReasonChoice;
if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
    die('Invalid request.');
}

function getRequiredSlots($timeType) {
    if ($timeType === 'day') return ['day'];
    if ($timeType === 'overnight') return ['overnight'];
    if ($timeType === '22hour') return ['day', 'overnight'];
    return [];
}

function approvalConflictDateLabel($dateValue) {
    $time = strtotime($dateValue);
    return $time ? date('l, F j, Y', $time) : $dateValue;
}

function approvalConflictSlotLabel($slot) {
    if ($slot === 'day') return 'Day Tour';
    if ($slot === 'overnight') return 'Overnight Stay';
    if ($slot === '22hour') return '22-Hour Stay';
    if ($slot === 'whole' || $slot === 'blocked') return 'Whole Day';
    return ucwords(str_replace(['_', '-'], ' ', (string)$slot));
}

function approvalConflictMessage($dateValue, $slot) {
    return 'Conflict Detected on ' . approvalConflictDateLabel($dateValue) . ' (' . approvalConflictSlotLabel($slot) . ' Already Booked).';
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
$requiredSlots = getRequiredSlots($booking['time_type']);
if (empty($requiredSlots)) {
    redirectWithMessage('error', 'Invalid stay type for this booking.');
}
$slotDate = $booking['check_in_date'];

mysqli_begin_transaction($conn);
try {
    if ($status === 'approved') {
        $conflictStmt = mysqli_prepare($conn, "SELECT time_type FROM booked_dates WHERE booking_id != ? AND booked_date = ?");
        mysqli_stmt_bind_param($conflictStmt, 'is', $id, $slotDate);
        mysqli_stmt_execute($conflictStmt);
        $conflictResult = mysqli_stmt_get_result($conflictStmt);
        $existingSlots = [];
        while ($row = mysqli_fetch_assoc($conflictResult)) {
            $existingSlots[] = $row['time_type'];
        }
        mysqli_stmt_close($conflictStmt);

        $blockStmt = mysqli_prepare($conn, "SELECT stay_type FROM admin_blocks WHERE blocked_date = ?");
        if ($blockStmt) {
            mysqli_stmt_bind_param($blockStmt, 's', $slotDate);
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

        if ($booking['time_type'] === '22hour' && !empty($existingSlots)) {
            throw new Exception(approvalConflictMessage($slotDate, $existingSlots[0] ?? 'day'));
        }
        if (in_array('day', $existingSlots, true) && in_array('overnight', $existingSlots, true)) {
            throw new Exception(approvalConflictMessage($slotDate, 'whole'));
        }
        foreach ($requiredSlots as $slot) {
            if (in_array($slot, $existingSlots, true)) {
                throw new Exception(approvalConflictMessage($slotDate, $slot));
            }
        }
    }

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM booked_dates WHERE booking_id = ?');
    mysqli_stmt_bind_param($deleteStmt, 'i', $id);
    mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    if ($status === 'approved') {
        $insertStmt = mysqli_prepare($conn, 'INSERT INTO booked_dates (booking_id, booked_date, time_type) VALUES (?, ?, ?)');
        foreach ($requiredSlots as $slot) {
            mysqli_stmt_bind_param($insertStmt, 'iss', $id, $slotDate, $slot);
            if (!mysqli_stmt_execute($insertStmt)) {
                throw new Exception('Failed to save approved booking slots.');
            }
        }
        mysqli_stmt_close($insertStmt);

        $paidReservationFee = 'paid';
        $paymentUpdateStmt = mysqli_prepare($conn, 'UPDATE payments SET reservation_fee_status = ? WHERE booking_id = ?');
        if ($paymentUpdateStmt) {
            mysqli_stmt_bind_param($paymentUpdateStmt, 'si', $paidReservationFee, $id);
            mysqli_stmt_execute($paymentUpdateStmt);
            mysqli_stmt_close($paymentUpdateStmt);
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
