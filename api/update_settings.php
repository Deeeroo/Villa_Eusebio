<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

function save_setting(mysqli $conn, string $key, string $value): void {
    $stmt = mysqli_prepare($conn, "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function save_settings_log(mysqli $conn, string $area, string $note): void {
    ve_audit_log($conn, $area, $note);
}

function save_settings_snapshot(mysqli $conn, string $note): void {
    $settings = [];
    $settingsResult = mysqli_query($conn, "SELECT setting_key, setting_value FROM system_settings");
    if ($settingsResult) {
        while ($row = mysqli_fetch_assoc($settingsResult)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $gallery = [];
    $galleryResult = mysqli_query($conn, "SELECT image_id, image_path, caption, description, show_on_home, archived_at, created_at FROM gallery_images ORDER BY image_id ASC");
    if ($galleryResult) {
        while ($row = mysqli_fetch_assoc($galleryResult)) {
            $gallery[] = $row;
        }
    }

    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    $snapshot = json_encode(['settings' => $settings, 'gallery' => $gallery], JSON_UNESCAPED_SLASHES);
    $stmt = mysqli_prepare($conn, "INSERT INTO settings_snapshots (admin_id, snapshot_data, action_note) VALUES (?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'iss', $adminId, $snapshot, $note);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function redirect_settings(string $type, string $message): void {
    header('Location: ../pages/settings.php?' . $type . '=' . urlencode($message));
    exit;
}

$action = $_POST['action'] ?? 'site';
if ($action === 'booking_pause') {
    save_settings_snapshot($conn, 'Before emergency booking control update');
    $paused = (string)($_POST['bookings_paused'] ?? '0') === '1' ? '1' : '0';
    $note = trim($_POST['booking_pause_note'] ?? '');
    if ($note === '') {
        $note = 'Bookings are temporarily closed. Please check again later or contact Villa Eusebio for assistance.';
    }

    save_setting($conn, 'bookings_paused', $paused);
    save_setting($conn, 'bookings_pause_note', $note);
    save_settings_log($conn, 'Booking Control', $paused === '1' ? 'Paused all new customer bookings.' : 'Resumed new customer bookings.');
    redirect_settings('success', $paused === '1' ? 'Bookings are now paused for customers.' : 'Bookings are now open for customers.');
}
if ($action === 'site') {
    save_settings_snapshot($conn, 'Before site settings update');
    foreach (['contact_phone','contact_email','contact_address','facebook_link','instagram_link','bio_text'] as $key) {
        save_setting($conn, $key, trim($_POST[$key] ?? ''));
    }
    if (isset($_FILES['site_icon']) && $_FILES['site_icon']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['site_icon']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            $dir = '../assets/settings_uploads';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $name = 'icon_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['site_icon']['tmp_name'], $dir . '/' . $name)) {
                save_setting($conn, 'site_icon', 'assets/settings_uploads/' . $name);
            }
        }
    }
    save_settings_log($conn, 'Site Info', 'Updated contact, social links, bio, or icon image.');
}
if ($action === 'admin') {
    save_settings_snapshot($conn, 'Before admin account update');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? 'Villa Eusebio Owner');
    if ($username !== '') {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "UPDATE admins SET username = ?, full_name = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'sssi', $username, $fullName, $hash, $_SESSION['admin_id']);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE admins SET username = ?, full_name = ?, updated_at = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssi', $username, $fullName, $_SESSION['admin_id']);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        save_settings_log($conn, 'Admin Account', 'Updated admin profile or login credentials.');
    }
}
if ($action === 'gallery_upload' && isset($_FILES['gallery_image']) && $_FILES['gallery_image']['error'] === UPLOAD_ERR_OK) {
    save_settings_snapshot($conn, 'Before gallery upload');
    $ext = strtolower(pathinfo($_FILES['gallery_image']['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        $dir = '../assets/gallery_uploads';
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $name = time() . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['gallery_image']['name']);
        if (move_uploaded_file($_FILES['gallery_image']['tmp_name'], $dir . '/' . $name)) {
            $path = 'assets/gallery_uploads/' . $name;
            $caption = trim($_POST['caption'] ?? 'Villa Eusebio');
            $description = trim($_POST['description'] ?? '');
            $showOnHome = 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO gallery_images (image_path, caption, description, show_on_home) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sssi', $path, $caption, $description, $showOnHome);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            save_settings_log($conn, 'Gallery', 'Added a new gallery image.');
        }
    }
}
if ($action === 'gallery_delete') {
    $id = (int)($_POST['image_id'] ?? 0);
    if ($id > 0) {
        $checkStmt = mysqli_prepare($conn, "SELECT show_on_home FROM gallery_images WHERE image_id = ? AND archived_at IS NULL LIMIT 1");
        if ($checkStmt) {
            mysqli_stmt_bind_param($checkStmt, 'i', $id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            $image = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
            mysqli_stmt_close($checkStmt);

            if (!$image) {
                redirect_settings('error', 'Gallery image not found.');
            }
            if ((int)($image['show_on_home'] ?? 0) === 1) {
                redirect_settings('error', 'This image cannot be archived because it is currently shown on the homepage. Remove it from Homepage first.');
            }
        }

        save_settings_snapshot($conn, 'Before gallery archive');
        $stmt = mysqli_prepare($conn, "UPDATE gallery_images SET show_on_home = 0, archived_at = NOW() WHERE image_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        save_settings_log($conn, 'Gallery', 'Archived a gallery image.');
    }
}
if ($action === 'gallery_home') {
    $id = (int)($_POST['image_id'] ?? 0);
    $showOnHome = (int)($_POST['show_on_home'] ?? 0) === 1 ? 1 : 0;
    if ($id > 0) {
        $currentStmt = mysqli_prepare($conn, "SELECT show_on_home FROM gallery_images WHERE image_id = ? AND archived_at IS NULL LIMIT 1");
        if ($currentStmt) {
            mysqli_stmt_bind_param($currentStmt, 'i', $id);
            mysqli_stmt_execute($currentStmt);
            $currentResult = mysqli_stmt_get_result($currentStmt);
            $currentImage = $currentResult ? mysqli_fetch_assoc($currentResult) : null;
            mysqli_stmt_close($currentStmt);

            if (!$currentImage) {
                redirect_settings('error', 'Gallery image not found.');
            }

            if ($showOnHome === 1 && (int)($currentImage['show_on_home'] ?? 0) !== 1) {
                $countResult = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_images WHERE show_on_home = 1 AND archived_at IS NULL");
                $countRow = $countResult ? mysqli_fetch_assoc($countResult) : ['total' => 0];
                if ((int)($countRow['total'] ?? 0) >= 3) {
                    redirect_settings('error', 'Only 3 images can be shown on the homepage. Remove one homepage image before adding another.');
                }
            }
        }

        save_settings_snapshot($conn, 'Before homepage gallery update');
        $stmt = mysqli_prepare($conn, "UPDATE gallery_images SET show_on_home = ? WHERE image_id = ? AND archived_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'ii', $showOnHome, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        save_settings_log($conn, 'Gallery', $showOnHome ? 'Set a gallery image as homepage image.' : 'Removed a gallery image from homepage.');
    }
}
if ($action === 'gallery_update') {
    $id = (int)($_POST['image_id'] ?? 0);
    $caption = trim($_POST['caption'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($id > 0 && $caption !== '') {
        save_settings_snapshot($conn, 'Before gallery image edit');
        $stmt = mysqli_prepare($conn, "UPDATE gallery_images SET caption = ?, description = ? WHERE image_id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $caption, $description, $id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        save_settings_log($conn, 'Gallery', 'Edited a gallery image title or description.');
    }
}
redirect_settings('success', 'Settings updated.');
?>
