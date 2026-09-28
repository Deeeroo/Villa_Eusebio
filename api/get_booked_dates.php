<?php
require_once "../includes/admin_auth.php";
include "../includes/db.php";
require_once "../includes/capstone2_features.php";
ve_ensure_capstone2_schema($conn);

$mode = $_GET['mode'] ?? 'dates';
$excludeBookingId = isset($_GET['exclude_booking_id']) ? (int)$_GET['exclude_booking_id'] : 0;
$showPrivateDetails = isset($_COOKIE[session_name()]) && admin_is_logged_in();
$bookingsPaused = ve_bookings_paused($conn);
$bookingPauseMessage = ve_booking_pause_message($conn);
$systemMeta = [
    'bookings_paused' => $bookingsPaused,
    'booking_pause_message' => $bookingPauseMessage
];

$sql = "
    SELECT
        bd.booked_date,
        bd.time_type,
        gi.guest_name,
        bs.check_in_date,
        bs.check_out_date,
        bs.time_type AS booking_time_type,
        bs.booking_id,
        COALESCE(pi.reservation_fee_status, 'unpaid') AS reservation_fee_status,
        COALESCE(pi.payment_status, 'unpaid') AS payment_status
    FROM booked_dates bd
    INNER JOIN bookings bs ON bd.booking_id = bs.booking_id
    INNER JOIN guests gi ON bs.guest_id = gi.guest_id
    LEFT JOIN payments pi ON pi.booking_id = bs.booking_id
    WHERE bs.status = 'approved' AND COALESCE(bs.archived, 0) = 0
        " . ($excludeBookingId > 0 ? "AND bs.booking_id != " . $excludeBookingId : "") . "
    ORDER BY bd.booked_date ASC, bd.booking_id ASC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    header('Content-Type: application/json');
    echo json_encode($mode === 'events' ? [] : [
        'dates' => new stdClass(),
        'meta' => new stdClass(),
        'system' => $systemMeta
    ]);
    exit;
}

$dates = [];
$dateMeta = [];
while ($row = mysqli_fetch_assoc($result)) {
    $date = $row['booked_date'];
    $slot = $row['time_type'];

    if (!isset($dates[$date])) $dates[$date] = [];
    if (!in_array($slot, $dates[$date], true)) $dates[$date][] = $slot;

    if (!isset($dateMeta[$date])) $dateMeta[$date] = [];
    $alreadyAdded = false;
    foreach ($dateMeta[$date] as $existingDetail) {
        if (empty($existingDetail['is_blocked']) && (int)($existingDetail['booking_id'] ?? 0) === (int)$row['booking_id']) {
            $alreadyAdded = true;
            break;
        }
    }
    if ($alreadyAdded) {
        continue;
    }
    $dateMeta[$date][] = [
        'booking_id' => $showPrivateDetails ? (int)$row['booking_id'] : 0,
        'block_id' => 0,
        'guest_name' => $showPrivateDetails ? $row['guest_name'] : 'Booked',
        'check_in_date' => $row['check_in_date'],
        'check_out_date' => $row['check_out_date'],
        'booking_time_type' => $row['booking_time_type'],
        'slot' => $row['time_type'],
        'reservation_fee_status' => $showPrivateDetails ? $row['reservation_fee_status'] : '',
        'payment_status' => $showPrivateDetails ? $row['payment_status'] : '',
        'is_blocked' => false,
        'block_reason' => ''
    ];
}


$blockSql = "SELECT block_id, blocked_date, stay_type, reason FROM admin_blocks ORDER BY blocked_date ASC";
$blockResult = mysqli_query($conn, $blockSql);
if ($blockResult) {
    while ($block = mysqli_fetch_assoc($blockResult)) {
        $date = $block['blocked_date'];
        $blockSlots = in_array($block['stay_type'], ['22hour', 'whole'], true) ? ['day', 'overnight'] : [$block['stay_type']];
        if (!isset($dates[$date])) $dates[$date] = [];
        if (!isset($dateMeta[$date])) $dateMeta[$date] = [];
        foreach ($blockSlots as $slot) {
            if (!in_array($slot, $dates[$date], true)) $dates[$date][] = $slot;
        }
        $dateMeta[$date][] = [
            'booking_id' => 0,
            'block_id' => (int)$block['block_id'],
            'guest_name' => 'Admin Blocked',
            'check_in_date' => $date,
            'check_out_date' => $block['stay_type'] === 'day' ? $date : date('Y-m-d', strtotime($date . ' +1 day')),
            'booking_time_type' => $block['stay_type'],
            'slot' => $block['stay_type'],
            'is_blocked' => true,
            'block_reason' => $showPrivateDetails ? $block['reason'] : 'Unavailable',
            'reservation_fee_status' => '',
            'payment_status' => ''
        ];
    }
}

$events = [];
foreach ($dates as $date => $slots) {
    sort($slots);
    $details = $dateMeta[$date] ?? [];
    $firstBlocked = !empty($details[0]['is_blocked']);
    $guestTitle = $firstBlocked ? 'Blocked' : (!empty($details[0]['guest_name']) ? $details[0]['guest_name'] : 'Booked');

    $isReal22HourBooking = false;
    $hasAdminBlock = false;
    $bookingIds = [];
    foreach ($details as $detail) {
        $bookingIds[] = (int)($detail['booking_id'] ?? 0);
        if (($detail['booking_time_type'] ?? '') === '22hour' && empty($detail['is_blocked'])) {
            $isReal22HourBooking = true;
        }
        if (!empty($detail['is_blocked'])) {
            $hasAdminBlock = true;
        }
    }
    $bookingIds = array_values(array_unique(array_filter($bookingIds)));

    $displayType = 'partial';
    if ($hasAdminBlock) {
        $displayType = 'blocked';
    } elseif ($isReal22HourBooking) {
        $displayType = '22hour';
    } elseif (in_array('day', $slots, true) && in_array('overnight', $slots, true)) {
        $displayType = 'full';
    } elseif (in_array('day', $slots, true)) {
        $displayType = 'day';
    } elseif (in_array('overnight', $slots, true)) {
        $displayType = 'overnight';
    }

    $events[] = [
        'title' => $guestTitle,
        'start' => $date,
        'end' => date('Y-m-d', strtotime($date . ' +1 day')),
        'display' => 'background',
        'backgroundColor' => 'transparent',
        'borderColor' => 'transparent',
        'extendedProps' => [
            'guest_name' => $guestTitle,
            'type' => $displayType,
            'slots' => $slots,
            'check_in_date' => $details[0]['check_in_date'] ?? $date,
            'check_out_date' => $details[0]['check_out_date'] ?? $date,
            'reservation_fee_status' => $details[0]['reservation_fee_status'] ?? 'unpaid',
            'payment_status' => $details[0]['payment_status'] ?? 'unpaid',
            'details' => $details,
            'booking_ids' => $bookingIds,
        ]
    ];
}

header('Content-Type: application/json');
if ($mode === 'events') {
    echo json_encode($events);
} else {
    echo json_encode([
        'dates' => $dates,
        'meta' => $dateMeta,
        'system' => $systemMeta
    ]);
}
