<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);
date_default_timezone_set('Asia/Manila');

$action = $_POST['action'] ?? 'save';
$id = (int)($_POST['announcement_id'] ?? 0);

function normalize_announcement_duration(string $duration): string {
    return in_array($duration, ['day', 'week', 'month', 'never'], true) ? $duration : 'never';
}

function announcement_expires_at(string $duration, string $monthValue = ''): ?string {
    $duration = normalize_announcement_duration($duration);
    if ($duration === 'day') {
        return date('Y-m-d H:i:s', strtotime('+1 day'));
    }
    if ($duration === 'week') {
        return date('Y-m-d H:i:s', strtotime('+1 week'));
    }
    if ($duration === 'month') {
        $month = preg_match('/^\d{4}-\d{2}$/', $monthValue) ? $monthValue : date('Y-m');
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $month . '-01 23:59:59');
        if (!$date) {
            $date = new DateTime('last day of this month 23:59:59');
        } else {
            $date->modify('last day of this month');
            $date->setTime(23, 59, 59);
        }
        return $date->format('Y-m-d H:i:s');
    }
    return null;
}

function announcement_log_label(string $title): string {
    $title = ve_audit_text($title, 80);
    return $title !== '' ? 'announcement "' . $title . '"' : 'announcement';
}

function announcement_title_by_id(mysqli $conn, int $id): string {
    if ($id <= 0) return '';

    $stmt = mysqli_prepare($conn, "SELECT title FROM announcements WHERE announcement_id = ? LIMIT 1");
    if (!$stmt) return '';

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return (string)($row['title'] ?? '');
}

function save_announcement_log(mysqli $conn, string $note): void {
    ve_audit_log($conn, 'Announcement', $note);
}

if ($action === 'show' && $id > 0) {
    $durationType = 'never';
    $expiresAt = null;
    $title = announcement_title_by_id($conn, $id);
    $currentStmt = mysqli_prepare($conn, "SELECT duration_type, expires_at FROM announcements WHERE announcement_id = ? LIMIT 1");
    if ($currentStmt) {
        mysqli_stmt_bind_param($currentStmt, 'i', $id);
        mysqli_stmt_execute($currentStmt);
        $currentResult = mysqli_stmt_get_result($currentStmt);
        $current = $currentResult ? mysqli_fetch_assoc($currentResult) : null;
        mysqli_stmt_close($currentStmt);
        if ($current) {
            $durationType = normalize_announcement_duration((string)($current['duration_type'] ?? 'never'));
            $expiresAt = $current['expires_at'] ?? null;
            if ($expiresAt !== null && strtotime($expiresAt) <= time()) {
                $expiresAt = announcement_expires_at($durationType);
            }
        }
    }

    mysqli_query($conn, "UPDATE announcements SET is_active = 0 WHERE archived_at IS NULL");
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 1, duration_type = ?, expires_at = ?, archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $durationType, $expiresAt, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    save_announcement_log($conn, 'Showed ' . announcement_log_label($title) . ' to customers.');
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement is now visible to customers.'));
    exit;
}

if ($action === 'hide' && $id > 0) {
    $title = announcement_title_by_id($conn, $id);
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0 WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    save_announcement_log($conn, 'Hid ' . announcement_log_label($title) . ' from customers.');
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement hidden from customers.'));
    exit;
}

if ($action === 'delete' && $id > 0) {
    $title = announcement_title_by_id($conn, $id);
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0, archived_at = NOW() WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    save_announcement_log($conn, 'Moved ' . announcement_log_label($title) . ' to archive.');
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement moved to archive.'));
    exit;
}

if ($action === 'archive' && $id > 0) {
    $title = announcement_title_by_id($conn, $id);
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0, archived_at = NOW() WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    save_announcement_log($conn, 'Archived ' . announcement_log_label($title) . '.');
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement archived.'));
    exit;
}

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$durationType = normalize_announcement_duration(trim($_POST['duration_type'] ?? 'never'));
$durationMonth = trim($_POST['duration_month'] ?? '');
$expiresAt = announcement_expires_at($durationType, $durationMonth);
$isActive = 0;
$imagePath = null;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image_path, is_active FROM announcements WHERE announcement_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        $imagePath = $existing['image_path'] ?? null;
        $isActive = !empty($existing['is_active']) ? 1 : 0;
    }
}

if ($title === '' || $message === '') {
    header('Location: ../pages/announcements.php?success=' . urlencode('Please add both a title and message.'));
    exit;
}

if (isset($_FILES['announcement_image']) && $_FILES['announcement_image']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $dir = '../assets/announcement_uploads';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['announcement_image']['name']);
        if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $dir . '/' . $name)) {
            $imagePath = 'assets/announcement_uploads/' . $name;
        }
    }
}

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, message = ?, image_path = ?, is_active = ?, duration_type = ?, expires_at = ?, archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'sssissi', $title, $message, $imagePath, $isActive, $durationType, $expiresAt, $id);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, message, image_path, is_active, duration_type, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sssiss', $title, $message, $imagePath, $isActive, $durationType, $expiresAt);
}
mysqli_stmt_execute($stmt);
save_announcement_log($conn, ($id > 0 ? 'Edited ' : 'Created ') . announcement_log_label($title) . '.');
mysqli_stmt_close($stmt);

header('Location: ../pages/announcements.php?success=' . urlencode('Announcement saved.'));
exit;
?>
