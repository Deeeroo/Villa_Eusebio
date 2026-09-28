<?php
require_once "../includes/admin_auth.php";
admin_start_session();
admin_security_headers();

function admin_login_redirect(string $error, int $wait = 0): void {
    $params = ['error' => $error];
    if ($wait > 0) {
        $params['wait'] = $wait;
    }
    header('Location: ../pages/owner.php?' . http_build_query($params));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    admin_login_redirect('auth');
}

include "../includes/db.php";
require_once "../includes/capstone2_features.php";
ve_ensure_capstone2_schema($conn);

$username = strtolower(trim($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');

if ($username === '' || $password === '' || strlen($username) > 120 || strlen($password) > 255) {
    admin_login_redirect('1');
}

$lockRemaining = admin_login_lock_remaining($conn, $username);
if ($lockRemaining > 0) {
    admin_login_redirect('locked', $lockRemaining);
}

$sql = "SELECT id, full_name, username, password_hash, role FROM admins WHERE username = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    admin_login_redirect('1');
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if ($admin && password_verify($password, $admin['password_hash'])) {
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = mysqli_prepare($conn, "UPDATE admins SET password_hash = ? WHERE id = ?");
        if ($rehashStmt) {
            mysqli_stmt_bind_param($rehashStmt, 'si', $newHash, $admin['id']);
            mysqli_stmt_execute($rehashStmt);
            mysqli_stmt_close($rehashStmt);
        }
    }

    admin_record_login_attempt($conn, $username, true);
    admin_login_success($admin);

    $updateSql = "UPDATE admins SET last_login = NOW() WHERE id = ?";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'i', $admin['id']);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }

    header('Location: ../pages/admin-panel.php');
    exit;
}

admin_record_login_attempt($conn, $username, false);
$lockRemaining = admin_login_lock_remaining($conn, $username);
if ($lockRemaining > 0) {
    admin_login_redirect('locked', $lockRemaining);
}

admin_login_redirect('1');
