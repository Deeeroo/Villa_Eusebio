<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) die('Unauthorized access.');
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$id = (int)($_POST['announcement_id'] ?? 0);
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE announcements SET archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header('Location: ../pages/archive_announcement.php?success=' . urlencode('Announcement restored.'));
exit;
?>
