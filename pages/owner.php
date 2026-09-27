<?php
require_once "../includes/admin_auth.php";
admin_start_session();
admin_security_headers();
if (admin_is_logged_in()) {
    header('Location: admin-panel.php');
    exit;
}
$errorCode = $_GET['error'] ?? '';
$waitSeconds = max(0, (int)($_GET['wait'] ?? 0));
?>

<link rel="icon" type="image/jpeg" href="/capstone_system/assets/icon.jpg">
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Login | Villa Eusebio</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    background: #F5F3EF;
    color: #2E2E2E;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    background-image: url('https://www.transparenttextures.com/patterns/natural-paper.png');
}

.owner-login-page {
    width: 100%;
    max-width: 440px;
}

.owner-login-toplink {
    margin-bottom: 20px;
}

.owner-login-toplink a {
    text-decoration: none;
    font-size: 13px;
    color: #6B8E6B;
}

.owner-brand-block {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 25px;
}

.owner-brand-icon {
    font-size: 22px;
}

.owner-brand-block h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.owner-brand-block p {
    margin: 0;
    font-size: 11px;
    color: #888;
}

.owner-login-card {
    background: #ffffff;
    padding: 45px 40px;
    border-radius: 12px;
    max-width: 520px;
    margin: 0 auto;
    border: 1px solid #EAEAEA;
    box-shadow: 0 15px 40px rgba(0,0,0,0.08);
}

.owner-login-card-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
}

.owner-login-card-header p {
    margin: 6px 0 20px;
    font-size: 13px;
    color: #666;
}

.login-error {
    background: #fff2f2;
    color: #b30000;
    border: 1px solid #e6bcbc;
    border-radius: 8px;
    padding: 10px 12px;
    margin-bottom: 14px;
    font-size: 13px;
}

.owner-form-group {
    margin-bottom: 15px;
}

.owner-form-group label {
    font-size: 12px;
    color: #555;
}

.owner-password-wrap {
    position: relative;
    margin-top: 6px;
}

.owner-password-wrap input {
    width: 100%;
    padding: 12px 44px 12px 36px;
    border-radius: 6px;
    border: 1px solid #DDD;
    outline: none;
    font-size: 14px;
}

.owner-password-wrap input:focus {
    border-color: #6B8E6B;
}

.owner-input-icon {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    opacity: 0.6;
}

.owner-password-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    width: 30px;
    height: 30px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #6B8E6B;
    cursor: pointer;
    font-size: 15px;
}

.owner-password-toggle:hover {
    background: #eef3ec;
}

.owner-login-btn {
    width: 100%;
    margin-top: 10px;
    padding: 12px;
    border-radius: 6px;
    border: none;
    background: #6B8E6B;
    color: white;
    font-size: 14px;
    cursor: pointer;
    transition: 0.2s;
}

.owner-login-btn:hover {
    background: #556f55;
}

.owner-footer-note {
    margin-top: 18px;
    font-size: 11px;
    color: #999;
    text-align: center;
}

.forgot-link {
    text-align: right;
    margin-bottom: 10px;
}

.forgot-link a {
    font-size: 12px;
    color: #6B8E6B;
    text-decoration: none;
}

.forgot-box {
    display: none;
    margin-top: 10px;
    background: #fff;
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 8px;
    font-size: 12px;
    color: #555;
}
</style>
<link rel="stylesheet" href="/capstone_system/responsive-fixes.css">
</head>

<body>

<div class="owner-login-page">

    <div class="owner-login-toplink">
        <a href="/capstone_system/index.php">← Back to Villa Eusebio</a>
    </div>

    <div class="owner-brand-block">
        <div class="owner-brand-icon">🍃</div>
        <div>
            <h1>Villa Eusebio</h1>
            <p>Admin Portal</p>
        </div>
    </div>

    <div class="owner-login-card">

        <div class="owner-login-card-header">
            <h2>Owner Access</h2>
            <p>Enter your credentials to continue</p>
        </div>

        <?php if ($errorCode !== ''): ?>
            <div class="login-error" id="loginError">
                <?php if ($errorCode === 'locked'): ?>
                    Too many failed login attempts. Please wait <?php echo max(1, (int)ceil($waitSeconds / 60)); ?> minute(s), then try again.
                <?php elseif ($errorCode === 'timeout'): ?>
                    Your admin session expired. Please log in again.
                <?php else: ?>
                    Invalid username or password.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form action="../api/admin_login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

            <!-- USERNAME -->
            <div class="owner-form-group">
                <label>Username</label>
                <div class="owner-password-wrap">
                    <span class="owner-input-icon">👤</span>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>
            </div>

            <!-- PASSWORD -->
            <div class="owner-form-group">
                <label>Password</label>
                <div class="owner-password-wrap">
                    <span class="owner-input-icon">🔒</span>
                    <input type="password" name="password" id="ownerPassword" placeholder="Enter password" required>
                    <button type="button" class="owner-password-toggle" id="ownerPasswordToggle" aria-label="Show password">&#128065;</button>
                </div>
            </div>

            <!-- FORGOT -->
            <div class="forgot-link">
                <a href="#" onclick="showForgot()">Forgot password?</a>
            </div>

            <div id="forgotBox" class="forgot-box">
                Please contact the system administrator to reset your password.
            </div>

            <button type="submit" class="owner-login-btn">Continue</button>

        </form>

    </div>

    <div class="owner-footer-note">
        © 2026 Villa Eusebio. All rights reserved.
    </div>

</div>

<script>
function showForgot() {
    document.getElementById("forgotBox").style.display = "block";
}

document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('ownerPassword');
    const passwordToggle = document.getElementById('ownerPasswordToggle');
    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const showing = passwordInput.type === 'text';
            passwordInput.type = showing ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            passwordToggle.innerHTML = showing ? '&#128065;' : '&#128584;';
        });
    }

    const error = document.getElementById('loginError');
    if (!error) return;

    const inputs = document.querySelectorAll('input');

    inputs.forEach(input => {
        input.style.borderColor = '#b30000';
        input.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-4px)' },
            { transform: 'translateX(4px)' },
            { transform: 'translateX(0)' }
        ], {
            duration: 300
        });
    });
});
</script>

</body>
</html>



