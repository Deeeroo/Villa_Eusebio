<?php
require_once '../includes/admin_auth.php';
admin_require_login(false);
include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$id = (int)($_POST['announcement_id'] ?? 0);
if ($id > 0) {
    $title = '';
    $titleStmt = mysqli_prepare($conn, "SELECT title FROM announcements WHERE announcement_id = ? LIMIT 1");
    if ($titleStmt) {
        mysqli_stmt_bind_param($titleStmt, 'i', $id);
        mysqli_stmt_execute($titleStmt);
        $titleResult = mysqli_stmt_get_result($titleStmt);
        $titleRow = $titleResult ? mysqli_fetch_assoc($titleResult) : null;
        mysqli_stmt_close($titleStmt);
        $title = ve_audit_text((string)($titleRow['title'] ?? ''), 80);
    }

    $stmt = mysqli_prepare($conn, "UPDATE announcements SET archived_at = NULL WHERE announcement_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    ve_audit_log($conn, 'Announcement', 'Restored announcement ' . ($title !== '' ? '"' . $title . '"' : 'from archive') . '.');
}

header('Location: ../pages/archive_announcement.php?success=' . urlencode('Announcement restored.'));
exit;
?>
