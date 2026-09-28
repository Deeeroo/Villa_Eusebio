<?php
require_once '../includes/admin_auth.php';
admin_start_session();
$isAsyncRequest = (
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch'
) || (
    isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
);

function block_date_response(bool $ok, string $message, int $statusCode = 200): void {
    global $isAsyncRequest;
    if ($isAsyncRequest) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'message' => $message]);
        exit;
    }

    $key = $ok ? 'success' : 'error';
    header('Location: ../pages/admin-panel.php?' . $key . '=' . urlencode($message));
    exit;
}

function block_date_label(string $dateValue): string {
    $time = strtotime($dateValue);
    return $time ? date('l, F j, Y', $time) : $dateValue;
}

admin_require_post_csrf();
include '../includes/db.php';
require_once '../includes/booking_availability.php';
ve_ensure_capstone2_schema($conn);

$action = trim($_POST['action'] ?? 'create');
$blockId = isset($_POST['block_id']) ? (int)$_POST['block_id'] : 0;
$blockedDate = trim($_POST['blocked_date'] ?? '');
$stayType = trim($_POST['stay_type'] ?? 'day');
$reason = trim($_POST['reason'] ?? '');

$allowedTypes = ve_allowed_block_types();

if ($action === 'delete') {
    if ($blockId <= 0) {
        block_date_response(false, 'Invalid blocked date record.', 422);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM admin_blocks WHERE block_id = ?");
    if (!$stmt) {
        block_date_response(false, 'Unable to remove blocked date right now.', 500);
    }

    mysqli_stmt_bind_param($stmt, 'i', $blockId);
    $deleted = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$deleted) {
        block_date_response(false, 'Unable to remove blocked date right now.', 500);
    }

    block_date_response(true, 'Blocked date removed successfully.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockedDate) || !in_array($stayType, $allowedTypes, true)) {
    block_date_response(false, 'Invalid block date details.', 422);
}

if ($reason === '') {
    block_date_response(false, 'Please enter a reason before blocking this date.', 422);
}

$blockAvailability = ve_check_block_availability($conn, $blockedDate, $stayType, $action === 'update' ? $blockId : 0);
if (!$blockAvailability['available']) {
    block_date_response(false, $blockAvailability['message'], 409);
}

if ($action === 'update' && $blockId > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE admin_blocks SET blocked_date = ?, stay_type = ?, reason = ? WHERE block_id = ?");
    if (!$stmt) {
        block_date_response(false, 'Unable to update blocked date right now.', 500);
    }
    mysqli_stmt_bind_param($stmt, 'sssi', $blockedDate, $stayType, $reason, $blockId);
    $updated = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$updated) {
        block_date_response(false, 'Unable to update blocked date right now.', 500);
    }
    block_date_response(true, 'Blocked date updated successfully.');
}

$stmt = mysqli_prepare($conn, "INSERT INTO admin_blocks (blocked_date, stay_type, reason) VALUES (?, ?, ?)");
if (!$stmt) {
    block_date_response(false, 'Unable to save blocked date right now.', 500);
}

mysqli_stmt_bind_param($stmt, 'sss', $blockedDate, $stayType, $reason);
$saved = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$saved) {
    block_date_response(false, 'Unable to save blocked date right now.', 500);
}

block_date_response(true, 'Date blocked successfully.');
?>
