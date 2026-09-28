<?php
require_once '../includes/admin_auth.php';
admin_require_post_csrf();

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$result = mysqli_query($conn, "SELECT snapshot_id, snapshot_data FROM settings_snapshots ORDER BY snapshot_id DESC LIMIT 1");
$snapshot = $result ? mysqli_fetch_assoc($result) : null;
if (!$snapshot) {
    header('Location: ../pages/settings.php?success=' . urlencode('No settings change to undo.'));
    exit;
}

$data = json_decode($snapshot['snapshot_data'], true);
if (!is_array($data)) {
    header('Location: ../pages/settings.php?success=' . urlencode('Unable to restore the last settings change.'));
    exit;
}

mysqli_begin_transaction($conn);
try {
    mysqli_query($conn, "DELETE FROM system_settings");
    $settings = $data['settings'] ?? [];
    $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $key => $value) {
        mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
        mysqli_stmt_execute($stmt);
    }
    if ($stmt) mysqli_stmt_close($stmt);

    mysqli_query($conn, "DELETE FROM gallery_images");
    $gallery = $data['gallery'] ?? [];
    $stmt = mysqli_prepare($conn, "INSERT INTO gallery_images (image_id, image_path, caption, description, show_on_home, archived_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($gallery as $image) {
        $id = (int)($image['image_id'] ?? 0);
        $path = (string)($image['image_path'] ?? '');
        $caption = (string)($image['caption'] ?? '');
        $description = (string)($image['description'] ?? '');
        $showOnHome = (int)($image['show_on_home'] ?? 0);
        $archivedAt = $image['archived_at'] ?? null;
        $createdAt = (string)($image['created_at'] ?? date('Y-m-d H:i:s'));
        if ($id <= 0 || $path === '') continue;
        mysqli_stmt_bind_param($stmt, 'isssiss', $id, $path, $caption, $description, $showOnHome, $archivedAt, $createdAt);
        mysqli_stmt_execute($stmt);
    }
    if ($stmt) mysqli_stmt_close($stmt);

    $snapshotId = (int)$snapshot['snapshot_id'];
    $stmt = mysqli_prepare($conn, "DELETE FROM settings_snapshots WHERE snapshot_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $snapshotId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    header('Location: ../pages/settings.php?success=' . urlencode('Last settings change undone.'));
} catch (Throwable $e) {
    mysqli_rollback($conn);
    header('Location: ../pages/settings.php?success=' . urlencode('Undo failed. Please try again.'));
}
exit;
?>
