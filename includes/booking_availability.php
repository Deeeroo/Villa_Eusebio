<?php
require_once __DIR__ . '/capstone2_features.php';
require_once __DIR__ . '/booking_repository.php';

function ve_required_slots(string $timeType): array {
    if ($timeType === 'day') return ['day'];
    if ($timeType === 'overnight') return ['overnight'];
    if ($timeType === '22hour') return ['day', 'overnight'];
    return [];
}

function ve_allowed_stay_types(): array {
    return ['day', 'overnight', '22hour'];
}

function ve_allowed_block_types(): array {
    return ['day', 'overnight', '22hour', 'whole'];
}

function ve_expand_block_slots(string $stayType): array {
    if ($stayType === '22hour' || $stayType === 'whole') {
        return ['day', 'overnight'];
    }
    if (in_array($stayType, ['day', 'overnight'], true)) {
        return [$stayType];
    }
    return [];
}

function ve_checkout_date(string $checkIn, string $timeType): string {
    $date = DateTime::createFromFormat('Y-m-d', $checkIn);
    if (!$date) {
        return '';
    }
    if ($timeType === 'overnight' || $timeType === '22hour') {
        $date->modify('+1 day');
    }
    return $date->format('Y-m-d');
}

function ve_stay_start_passed_today(string $checkIn, string $timeType): bool {
    $startTimes = [
        'day' => '09:00:00',
        'overnight' => '19:00:00',
        '22hour' => '09:00:00',
    ];
    if (!isset($startTimes[$timeType]) || $checkIn !== date('Y-m-d')) {
        return false;
    }
    return time() >= strtotime($checkIn . ' ' . $startTimes[$timeType]);
}

function ve_stay_start_passed_message(string $timeType): string {
    if ($timeType === 'day') return 'Day Tour can no longer be booked today because its 9:00 AM start time has passed.';
    if ($timeType === 'overnight') return 'Overnight Stay can no longer be booked today because its 7:00 PM start time has passed.';
    if ($timeType === '22hour') return '22-Hour Stay can no longer be booked today because its 9:00 AM start time has passed.';
    return 'Selected stay type can no longer be booked today.';
}

function ve_slot_label(string $slot): string {
    if ($slot === 'day') return 'Day Tour';
    if ($slot === 'overnight') return 'Overnight Stay';
    if ($slot === '22hour') return '22-Hour Stay';
    if ($slot === 'whole' || $slot === 'blocked') return 'Whole Day';
    return ucwords(str_replace(['_', '-'], ' ', $slot));
}

function ve_date_label(string $dateValue): string {
    $time = strtotime($dateValue);
    return $time ? date('l, F j, Y', $time) : $dateValue;
}

function ve_conflict_message(string $dateValue, string $slot, bool $blocked = false): string {
    $status = $blocked ? 'Blocked' : 'Already Booked';
    return 'Conflict Detected on ' . ve_date_label($dateValue) . ' (' . ve_slot_label($slot) . ' ' . $status . ').';
}

function ve_previous_date(string $dateValue): string {
    $date = DateTime::createFromFormat('Y-m-d', $dateValue);
    if (!$date) {
        return '';
    }
    $date->modify('-1 day');
    return $date->format('Y-m-d');
}

function ve_checkout_day_block_exists(mysqli $conn, string $dateValue, int $excludeBlockId = 0): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
        return false;
    }

    $stmt = mysqli_prepare($conn, "SELECT block_id FROM admin_blocks WHERE blocked_date = ? AND stay_type IN ('22hour', 'whole') AND (? = 0 OR block_id != ?) LIMIT 1");
    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'sii', $dateValue, $excludeBlockId, $excludeBlockId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (bool)$exists;
}

function ve_date_reserved_slots(mysqli $conn, string $dateValue, int $excludeBookingId = 0, bool $includeBlocks = true): array {
    ve_ensure_capstone2_schema($conn);
    $slots = [];
    $details = [];

    $bookingSql = "
        SELECT bd.time_type, bs.booking_id, gi.guest_name
        FROM booked_dates bd
        INNER JOIN bookings bs ON bs.booking_id = bd.booking_id
        INNER JOIN guests gi ON gi.guest_id = bs.guest_id
        WHERE bd.booked_date = ?
            AND bs.status = 'approved'
            AND COALESCE(bs.archived, 0) = 0
            AND (? = 0 OR bs.booking_id != ?)
    ";
    $bookingStmt = mysqli_prepare($conn, $bookingSql);
    if ($bookingStmt) {
        mysqli_stmt_bind_param($bookingStmt, 'sii', $dateValue, $excludeBookingId, $excludeBookingId);
        mysqli_stmt_execute($bookingStmt);
        $bookingResult = mysqli_stmt_get_result($bookingStmt);
        while ($row = $bookingResult ? mysqli_fetch_assoc($bookingResult) : null) {
            $slot = (string)$row['time_type'];
            $slots[] = $slot;
            $details[] = [
                'slot' => $slot,
                'booking_id' => (int)$row['booking_id'],
                'guest_name' => (string)$row['guest_name'],
                'is_blocked' => false,
                'reason' => '',
            ];
        }
        mysqli_stmt_close($bookingStmt);
    }

    if ($includeBlocks) {
        $blockStmt = mysqli_prepare($conn, "SELECT block_id, stay_type, reason FROM admin_blocks WHERE blocked_date = ?");
        if ($blockStmt) {
            mysqli_stmt_bind_param($blockStmt, 's', $dateValue);
            mysqli_stmt_execute($blockStmt);
            $blockResult = mysqli_stmt_get_result($blockStmt);
            while ($block = $blockResult ? mysqli_fetch_assoc($blockResult) : null) {
                foreach (ve_expand_block_slots((string)$block['stay_type']) as $slot) {
                    $slots[] = $slot;
                    $details[] = [
                        'slot' => $slot,
                        'booking_id' => 0,
                        'block_id' => (int)$block['block_id'],
                        'guest_name' => 'Admin Blocked',
                        'is_blocked' => true,
                        'reason' => (string)$block['reason'],
                    ];
                }
            }
            mysqli_stmt_close($blockStmt);
        }
    }

    return [
        'slots' => array_values(array_unique($slots)),
        'details' => $details,
    ];
}

function ve_availability_conflict(array $existingSlots, string $timeType): string {
    $requiredSlots = ve_required_slots($timeType);
    if (empty($requiredSlots)) {
        return 'invalid';
    }
    if ($timeType === '22hour' && !empty($existingSlots)) {
        return (string)($existingSlots[0] ?? 'day');
    }
    foreach ($requiredSlots as $slot) {
        if (in_array($slot, $existingSlots, true)) {
            return $slot;
        }
    }
    return '';
}

function ve_check_date_availability(mysqli $conn, string $dateValue, string $timeType, int $excludeBookingId = 0, bool $includeBlocks = true): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) || !in_array($timeType, ve_allowed_stay_types(), true)) {
        return [
            'available' => false,
            'message' => 'Invalid stay date or type.',
            'conflict_slot' => 'invalid',
            'slots' => [],
            'details' => [],
        ];
    }

    $reserved = ve_date_reserved_slots($conn, $dateValue, $excludeBookingId, $includeBlocks);
    $conflictSlot = ve_availability_conflict($reserved['slots'], $timeType);
    if ($conflictSlot === '' && $includeBlocks && in_array($timeType, ['overnight', '22hour'], true)) {
        $checkoutDate = ve_checkout_date($dateValue, $timeType);
        if ($checkoutDate !== '' && ve_checkout_day_block_exists($conn, $checkoutDate)) {
            return [
                'available' => false,
                'message' => 'Conflict Detected on ' . ve_date_label($checkoutDate) . ' (Check-out date is blocked).',
                'conflict_slot' => 'checkout_blocked',
                'slots' => $reserved['slots'],
                'details' => $reserved['details'],
            ];
        }
    }

    return [
        'available' => $conflictSlot === '',
        'message' => $conflictSlot === '' ? '' : ve_conflict_message($dateValue, $conflictSlot),
        'conflict_slot' => $conflictSlot,
        'slots' => $reserved['slots'],
        'details' => $reserved['details'],
    ];
}

function ve_check_block_availability(mysqli $conn, string $dateValue, string $stayType, int $excludeBlockId = 0): array {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue) || !in_array($stayType, ve_allowed_block_types(), true)) {
        return ['available' => false, 'message' => 'Invalid block date details.', 'conflict_slot' => 'invalid'];
    }

    $targetSlots = ve_expand_block_slots($stayType);
    $reserved = ve_date_reserved_slots($conn, $dateValue, 0, false);
    foreach ($targetSlots as $slot) {
        if (in_array($slot, $reserved['slots'], true)) {
            return [
                'available' => false,
                'message' => 'Cannot block ' . ve_date_label($dateValue) . ' because the ' . ve_slot_label($slot) . ' slot already has an approved reservation.',
                'conflict_slot' => $slot,
            ];
        }
    }

    if ($stayType === 'whole') {
        $previousDate = ve_previous_date($dateValue);
        $previousReserved = $previousDate !== '' ? ve_date_reserved_slots($conn, $previousDate, 0, false) : ['slots' => []];
        if (in_array('overnight', $previousReserved['slots'] ?? [], true)) {
            return [
                'available' => false,
                'message' => 'Cannot block ' . ve_date_label($dateValue) . ' as a whole day because an approved overnight or 22-hour reservation from ' . ve_date_label($previousDate) . ' checks out that morning.',
                'conflict_slot' => 'overnight',
            ];
        }
    }

    $blockStmt = mysqli_prepare($conn, "SELECT stay_type FROM admin_blocks WHERE blocked_date = ? AND (? = 0 OR block_id != ?)");
    if ($blockStmt) {
        mysqli_stmt_bind_param($blockStmt, 'sii', $dateValue, $excludeBlockId, $excludeBlockId);
        mysqli_stmt_execute($blockStmt);
        $blockResult = mysqli_stmt_get_result($blockStmt);
        $existingBlockSlots = [];
        while ($block = $blockResult ? mysqli_fetch_assoc($blockResult) : null) {
            $existingBlockSlots = array_merge($existingBlockSlots, ve_expand_block_slots((string)$block['stay_type']));
        }
        mysqli_stmt_close($blockStmt);

        foreach ($targetSlots as $slot) {
            if (in_array($slot, $existingBlockSlots, true)) {
                return [
                    'available' => false,
                    'message' => ve_conflict_message($dateValue, $slot, true),
                    'conflict_slot' => $slot,
                ];
            }
        }
    }

    return ['available' => true, 'message' => '', 'conflict_slot' => ''];
}

function ve_replace_booking_slots(mysqli $conn, int $bookingId, string $dateValue, string $timeType): void {
    $deleteStmt = mysqli_prepare($conn, "DELETE FROM booked_dates WHERE booking_id = ?");
    if ($deleteStmt) {
        mysqli_stmt_bind_param($deleteStmt, 'i', $bookingId);
        mysqli_stmt_execute($deleteStmt);
        mysqli_stmt_close($deleteStmt);
    }

    $requiredSlots = ve_required_slots($timeType);
    if (empty($requiredSlots)) {
        throw new Exception('Invalid stay type for booking slots.');
    }

    $insertStmt = mysqli_prepare($conn, "INSERT INTO booked_dates (booking_id, booked_date, time_type) VALUES (?, ?, ?)");
    if (!$insertStmt) {
        throw new Exception(mysqli_error($conn));
    }
    foreach ($requiredSlots as $slot) {
        mysqli_stmt_bind_param($insertStmt, 'iss', $bookingId, $dateValue, $slot);
        if (!mysqli_stmt_execute($insertStmt)) {
            mysqli_stmt_close($insertStmt);
            throw new Exception('Failed to save booking slots.');
        }
    }
    mysqli_stmt_close($insertStmt);
}
