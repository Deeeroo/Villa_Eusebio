<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$id = (int)($_POST['image_id'] ?? 0);
$redirect = $_POST['redirect'] ?? 'archive_settings';
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE gallery_images SET archived_at = NULL WHERE image_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

$target = $redirect === 'settings' ? 'settings.php' : 'archive_settings.php';
header('Location: ../pages/' . $target . '?success=' . urlencode('Gallery image restored.'));
exit;
?>
