<?php
session_start();
include "../includes/db.php";

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    header('Location: ../pages/owner.php?error=1');
    exit;
}

$sql = "SELECT id, full_name, username, password_hash, role FROM admins WHERE username = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    header('Location: ../pages/owner.php?error=1');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$admin = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if ($admin && password_verify($password, $admin['password_hash'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = (int)$admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_full_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];

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

header('Location: ../pages/owner.php?error=1');
exit;
?>
