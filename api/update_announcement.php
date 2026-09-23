<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: ../pages/owner.php'); exit; }

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$action = $_POST['action'] ?? 'save';
$id = (int)($_POST['announcement_id'] ?? 0);

if ($action === 'show' && $id > 0) {
    mysqli_query($conn, "UPDATE announcements SET is_active = 0 WHERE archived_at IS NULL");
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 1, archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement is now visible to customers.'));
    exit;
}

if ($action === 'hide' && $id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0 WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement hidden from customers.'));
    exit;
}

if ($action === 'delete' && $id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0, archived_at = NOW() WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement moved to archive.'));
    exit;
}

if ($action === 'archive' && $id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET is_active = 0, archived_at = NOW() WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    header('Location: ../pages/announcements.php?success=' . urlencode('Announcement archived.'));
    exit;
}

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$isActive = isset($_POST['show_to_customers']) ? 1 : 0;
$imagePath = null;

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT image_path FROM announcements WHERE announcement_id = ? LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
        $imagePath = $existing['image_path'] ?? null;
    }
}

if ($title === '' || $message === '') {
    header('Location: ../pages/announcements.php?success=' . urlencode('Please add both a title and message.'));
    exit;
}

if ($isActive) {
    mysqli_query($conn, "UPDATE announcements SET is_active = 0 WHERE archived_at IS NULL");
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
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET title = ?, message = ?, image_path = ?, is_active = ?, archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'sssii', $title, $message, $imagePath, $isActive, $id);
} else {
    $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, message, image_path, is_active) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'sssi', $title, $message, $imagePath, $isActive);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: ../pages/announcements.php?success=' . urlencode('Announcement saved.'));
exit;
?>
