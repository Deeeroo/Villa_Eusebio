<?php
require_once '../includes/admin_auth.php';
admin_start_session();
admin_security_headers();

function reset_password_redirect(string $token, string $error): void {
    $query = http_build_query(['token' => $token, 'error' => $error]);
    header('Location: ../pages/reset-password.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pages/owner.php?error=auth');
    exit;
}

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$token = trim($_POST['token'] ?? '');
$password = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    header('Location: ../pages/owner.php?error=auth');
    exit;
}

if (strlen($password) < 8) {
    reset_password_redirect($token, 'short');
}
if ($password !== $confirm) {
    reset_password_redirect($token, 'mismatch');
}

$tokenHash = hash('sha256', $token);

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "SELECT reset_id, admin_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Unable to verify reset link.');
    }
    mysqli_stmt_bind_param($stmt, 's', $tokenHash);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $reset = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (!$reset) {
        mysqli_rollback($conn);
        reset_password_redirect($token, 'invalid');
    }

    $adminId = (int)$reset['admin_id'];
    $resetId = (int)$reset['reset_id'];
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $updateAdmin = mysqli_prepare($conn, "UPDATE admins SET password_hash = ?, updated_at = NOW() WHERE id = ?");
    if (!$updateAdmin) {
        throw new RuntimeException('Unable to update password.');
    }
    mysqli_stmt_bind_param($updateAdmin, 'si', $hash, $adminId);
    mysqli_stmt_execute($updateAdmin);
    mysqli_stmt_close($updateAdmin);

    $markUsed = mysqli_prepare($conn, "UPDATE admin_password_resets SET used_at = NOW() WHERE reset_id = ?");
    if ($markUsed) {
        mysqli_stmt_bind_param($markUsed, 'i', $resetId);
        mysqli_stmt_execute($markUsed);
        mysqli_stmt_close($markUsed);
    }

    $expireOthers = mysqli_prepare($conn, "UPDATE admin_password_resets SET used_at = COALESCE(used_at, NOW()) WHERE admin_id = ? AND used_at IS NULL");
    if ($expireOthers) {
        mysqli_stmt_bind_param($expireOthers, 'i', $adminId);
        mysqli_stmt_execute($expireOthers);
        mysqli_stmt_close($expireOthers);
    }

    mysqli_commit($conn);
    $_SESSION['admin_id'] = $adminId;
    ve_audit_log($conn, 'Admin Account', 'Changed owner/admin password using the email reset confirmation link.');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    reset_password_redirect($token, 'failed');
}

admin_end_session();
header('Location: ../pages/owner.php?reset=updated');
exit;
?>
