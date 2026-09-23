<?php
require_once __DIR__ . '/capstone2_features.php';

function ve_get_base_price($type) {
    if ($type === 'day') return 7000.0;
    if ($type === 'overnight') return 10000.0;
    if ($type === '22hour') return 13000.0;
    return 0.0;
}

function ve_fetch_all_bookings(mysqli $conn, string $orderBy = 'bs.booking_id DESC'): array {
    ve_ensure_capstone2_schema($conn);
    $sql = "
        SELECT
            bs.booking_id AS id,
            bs.guest_id,
            gi.guest_name,
            gi.email,
            gi.mobile,
            gi.address,
            bs.check_in_date,
            bs.check_out_date,
            bs.guests,
            bs.time_type,
            bs.special_requests,
            bs.status,
            COALESCE(bs.archived, 0) AS archived,
            COALESCE(bs.rejection_reason, '') AS rejection_reason,
            bs.created_at,
            COALESCE(pi.payment_id, 0) AS payment_id,
            COALESCE(pi.payment_method, '') AS payment_method,
            COALESCE(pi.proof_of_payment, '') AS proof_of_payment,
            COALESCE(pi.reservation_fee_amount, 2000.00) AS reservation_fee_amount,
            COALESCE(pi.reservation_fee_status, 'unpaid') AS reservation_fee_status,
            COALESCE(pi.remaining_balance, 0.00) AS remaining_balance,
            COALESCE(pi.payment_status, 'unpaid') AS payment_status,
            COALESCE(bc.base_stay_value, 0.00) AS base_stay_value,
            COALESCE(bc.extra_guest_count, 0) AS extra_guest_count,
            COALESCE(bc.extra_guest_fee, 0.00) AS extra_guest_fee,
            COALESCE(bc.total_stay_value, 0.00) AS total_stay_value
        FROM bookings bs
        INNER JOIN guests gi ON gi.guest_id = bs.guest_id
        LEFT JOIN payments pi ON pi.booking_id = bs.booking_id
        LEFT JOIN charges bc ON bc.booking_id = bs.booking_id
        WHERE COALESCE(bs.archived, 0) = 0
        ORDER BY {$orderBy}
    ";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function ve_fetch_booking_by_id(mysqli $conn, int $bookingId): ?array {
    ve_ensure_capstone2_schema($conn);
    $sql = "
        SELECT
            bs.booking_id AS id,
            bs.guest_id,
            gi.guest_name,
            gi.email,
            gi.mobile,
            gi.address,
            bs.check_in_date,
            bs.check_out_date,
            bs.guests,
            bs.time_type,
            bs.special_requests,
            bs.status,
            COALESCE(bs.archived, 0) AS archived,
            COALESCE(bs.rejection_reason, '') AS rejection_reason,
            bs.created_at,
            COALESCE(pi.payment_id, 0) AS payment_id,
            COALESCE(pi.payment_method, '') AS payment_method,
            COALESCE(pi.proof_of_payment, '') AS proof_of_payment,
            COALESCE(pi.reservation_fee_amount, 2000.00) AS reservation_fee_amount,
            COALESCE(pi.reservation_fee_status, 'unpaid') AS reservation_fee_status,
            COALESCE(pi.remaining_balance, 0.00) AS remaining_balance,
            COALESCE(pi.payment_status, 'unpaid') AS payment_status,
            COALESCE(bc.base_stay_value, 0.00) AS base_stay_value,
            COALESCE(bc.extra_guest_count, 0) AS extra_guest_count,
            COALESCE(bc.extra_guest_fee, 0.00) AS extra_guest_fee,
            COALESCE(bc.total_stay_value, 0.00) AS total_stay_value
        FROM bookings bs
        INNER JOIN guests gi ON gi.guest_id = bs.guest_id
        LEFT JOIN payments pi ON pi.booking_id = bs.booking_id
        LEFT JOIN charges bc ON bc.booking_id = bs.booking_id
        WHERE bs.booking_id = ? AND COALESCE(bs.archived, 0) = 0
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $bookingId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}
