<?php
require_once '../includes/admin_auth.php';
require_once '../includes/url_helper.php';
admin_start_session();
admin_security_headers();

include '../includes/db.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$token = trim($_GET['token'] ?? '');
$errorCode = $_GET['error'] ?? '';
$isValidToken = false;

if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $tokenHash = hash('sha256', $token);
    $stmt = mysqli_prepare($conn, "SELECT reset_id FROM admin_password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $tokenHash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $isValidToken = (bool)($result && mysqli_fetch_assoc($result));
        mysqli_stmt_close($stmt);
    }
}

$errorMessage = '';
if ($errorCode === 'short') {
    $errorMessage = 'Password must be at least 8 characters.';
} elseif ($errorCode === 'mismatch') {
    $errorMessage = 'The new password and confirmation do not match.';
} elseif ($errorCode === 'invalid') {
    $errorMessage = 'This reset link is invalid, expired, or already used.';
} elseif ($errorCode === 'failed') {
    $errorMessage = 'Unable to reset the password. Please request a new reset link.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password | Villa Eusebio</title>
<link rel="icon" type="image/jpeg" href="<?php echo htmlspecialchars(ve_url('assets/icon.jpg')); ?>">
<style>
:root {
    --portal-green: #174b33;
    --portal-green-dark: #0f3d29;
    --portal-text: #17211c;
    --portal-muted: #69736d;
    --portal-line: #dde3df;
    --portal-bg: #fbfaf6;
}

* { box-sizing: border-box; }
html, body { min-height: 100%; }
body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--portal-text);
    background:
        radial-gradient(circle at top center, rgba(44, 91, 63, .08), transparent 34%),
        linear-gradient(135deg, #fffefa 0%, #f7f5ee 48%, #fbfaf6 100%);
}
.reset-shell {
    min-height: 100vh;
    min-height: 100svh;
    display: grid;
    place-items: center;
    padding: 28px 18px;
}
.reset-card {
    width: min(440px, 100%);
    padding: 28px;
    border: 1px solid rgba(210, 218, 213, .95);
    border-radius: 14px;
    background: rgba(255, 255, 255, .9);
    box-shadow: 0 24px 70px rgba(37, 51, 43, .13);
}
.reset-brand {
    display: grid;
    justify-items: center;
    gap: 6px;
    margin-bottom: 20px;
    text-align: center;
}
.reset-brand img {
    width: 58px;
    height: 58px;
    object-fit: contain;
}
.reset-brand h1 {
    margin: 0;
    font-family: Georgia, 'Times New Roman', serif;
    color: var(--portal-green);
    font-size: 2.2rem;
    font-weight: 500;
    line-height: 1;
}
.reset-card h2 {
    margin: 0 0 8px;
    font-size: 1.65rem;
}
.reset-card p {
    margin: 0 0 18px;
    color: var(--portal-muted);
    line-height: 1.45;
}
.reset-form-group {
    margin-bottom: 14px;
}
.reset-form-group label {
    display: block;
    margin-bottom: 6px;
    font-size: .9rem;
    font-weight: 800;
}
.reset-form-group input {
    width: 100%;
    min-height: 46px;
    padding: 0 14px;
    border: 1px solid var(--portal-line);
    border-radius: 8px;
    background: rgba(255, 255, 255, .9);
    color: #1e2822;
    font: inherit;
    outline: none;
}
.reset-form-group input:focus {
    border-color: #6d967c;
    box-shadow: 0 0 0 4px rgba(23, 75, 51, .1);
}
.reset-alert {
    margin-bottom: 14px;
    padding: 11px 12px;
    border-radius: 10px;
    border: 1px solid #efc7c7;
    background: #fff4f4;
    color: #a81515;
    font-size: .9rem;
    font-weight: 700;
}
.reset-btn,
.reset-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    min-height: 46px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, #23643f 0%, #12472e 100%);
    color: #fff;
    font: inherit;
    font-weight: 900;
    text-decoration: none;
    cursor: pointer;
}
.reset-link.secondary {
    margin-top: 10px;
    background: #eef4f0;
    color: var(--portal-green);
}
</style>
</head>
<body>
<main class="reset-shell">
    <section class="reset-card">
        <div class="reset-brand">
            <img src="<?php echo htmlspecialchars(ve_url('assets/admin-login-leaf.png')); ?>" alt="Villa Eusebio leaf mark">
            <h1>Villa Eusebio</h1>
        </div>

        <?php if ($isValidToken): ?>
            <h2>Choose a new password</h2>
            <p>Enter and confirm your new owner/admin password.</p>
            <?php if ($errorMessage !== ''): ?><div class="reset-alert"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
            <form method="POST" action="../api/reset_admin_password.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="reset-form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="new_password" minlength="8" autocomplete="new-password" required>
                </div>
                <div class="reset-form-group">
                    <label for="confirmPassword">Confirm Password</label>
                    <input type="password" id="confirmPassword" name="confirm_password" minlength="8" autocomplete="new-password" required>
                </div>
                <button type="submit" class="reset-btn">Save New Password</button>
            </form>
        <?php else: ?>
            <h2>Reset link unavailable</h2>
            <p>This password reset link is invalid, expired, or already used. Request a new link from the owner login page.</p>
            <?php if ($errorMessage !== ''): ?><div class="reset-alert"><?php echo htmlspecialchars($errorMessage); ?></div><?php endif; ?>
            <a class="reset-link" href="<?php echo htmlspecialchars(ve_url('pages/owner.php')); ?>">Back to Sign In</a>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
