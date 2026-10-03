<?php
require_once "../includes/admin_auth.php";
require_once "../includes/url_helper.php";
admin_start_session();
admin_security_headers();
if (admin_is_logged_in()) {
    header('Location: admin-panel.php');
    exit;
}
$errorCode = $_GET['error'] ?? '';
$waitSeconds = max(0, (int)($_GET['wait'] ?? 0));
$resetStatus = $_GET['reset'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Owner Login | Villa Eusebio</title>
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

* {
    box-sizing: border-box;
}

html,
body {
    height: 100%;
    min-height: 100%;
}

body {
    margin: 0;
    overflow: hidden;
    font-family: Arial, Helvetica, sans-serif;
    color: var(--portal-text);
    background:
        radial-gradient(circle at top center, rgba(44, 91, 63, .08), transparent 34%),
        linear-gradient(135deg, #fffefa 0%, #f7f5ee 48%, #fbfaf6 100%);
}

.owner-portal-shell {
    position: relative;
    height: 100vh;
    height: 100svh;
    min-height: 0;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr);
    padding: clamp(12px, 2.1vh, 22px) clamp(18px, 4vw, 42px);
    overflow: hidden;
}

.owner-login-toplink {
    justify-self: start;
}

.owner-login-toplink a {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--portal-green);
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
}

.owner-login-toplink a:hover {
    color: var(--portal-green-dark);
}

.owner-login-page {
    width: min(420px, 100%);
    align-self: center;
    justify-self: center;
    padding: 0;
}

.owner-brand-block {
    display: grid;
    justify-items: center;
    gap: 5px;
    margin-bottom: clamp(10px, 2vh, 18px);
    text-align: center;
}

.owner-brand-icon {
    width: clamp(46px, 7vh, 64px);
    height: clamp(46px, 7vh, 64px);
    object-fit: contain;
}

.owner-brand-block h1 {
    margin: 0;
    font-family: Georgia, 'Times New Roman', serif;
    color: var(--portal-green);
    font-size: clamp(1.9rem, 4.4vw, 2.55rem);
    font-weight: 500;
    line-height: 1;
}

.owner-brand-block p {
    margin: 0;
    color: #8f978f;
    font-size: .68rem;
    font-weight: 900;
    letter-spacing: .28em;
    text-transform: uppercase;
}

.owner-login-card {
    padding: clamp(18px, 2.8vh, 26px);
    border: 1px solid rgba(210, 218, 213, .95);
    border-radius: 14px;
    background: rgba(255, 255, 255, .88);
    box-shadow: 0 24px 70px rgba(37, 51, 43, .13);
    backdrop-filter: blur(10px);
}

.owner-login-card-header {
    margin-bottom: clamp(12px, 2vh, 16px);
}

.owner-login-card-header h2 {
    margin: 0 0 6px;
    color: #18231e;
    font-size: clamp(1.6rem, 3.6vw, 1.95rem);
    line-height: 1.08;
}

.owner-login-card-header p {
    margin: 0;
    color: var(--portal-muted);
    font-size: .92rem;
    line-height: 1.35;
}

.login-error {
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #efc7c7;
    border-radius: 10px;
    background: #fff4f4;
    color: #a81515;
    font-size: .86rem;
    font-weight: 700;
}

.login-success {
    margin-bottom: 12px;
    padding: 10px 12px;
    border: 1px solid #cbe2d0;
    border-radius: 10px;
    background: #f1faf3;
    color: #1f6a3d;
    font-size: .86rem;
    font-weight: 700;
    line-height: 1.45;
}

.owner-form-group {
    margin-bottom: 13px;
}

.owner-form-group label {
    display: block;
    margin-bottom: 6px;
    color: #202923;
    font-size: .88rem;
    font-weight: 700;
}

.owner-password-wrap {
    position: relative;
}

.owner-input-icon,
.owner-password-toggle {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    color: #6a7f72;
}

.owner-input-icon {
    left: 16px;
    width: 20px;
    height: 20px;
    pointer-events: none;
}

.owner-password-wrap input {
    width: 100%;
    min-height: 44px;
    padding: 0 48px 0 46px;
    border: 1px solid var(--portal-line);
    border-radius: 8px;
    background: rgba(255, 255, 255, .86);
    color: #1e2822;
    font: inherit;
    font-size: .95rem;
    outline: none;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}

.owner-password-wrap input:focus {
    border-color: #6d967c;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(23, 75, 51, .1);
}

.owner-password-wrap input::placeholder {
    color: #8c9690;
}

.owner-password-toggle {
    right: 11px;
    display: inline-grid;
    place-items: center;
    width: 32px;
    height: 32px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    cursor: pointer;
}

.owner-password-toggle:hover {
    background: #eef4f0;
}

.owner-password-toggle svg,
.owner-input-icon svg {
    width: 20px;
    height: 20px;
    display: block;
}

.forgot-link {
    margin: -2px 0 13px;
    text-align: right;
}

.forgot-link button {
    padding: 0;
    border: 0;
    background: none;
    color: var(--portal-green);
    font-size: .88rem;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.forgot-link button:hover {
    text-decoration: underline;
}

.owner-login-btn {
    width: 100%;
    min-height: 46px;
    border: 0;
    border-radius: 8px;
    background: linear-gradient(135deg, #23643f 0%, #12472e 100%);
    color: #ffffff;
    font: inherit;
    font-size: 1rem;
    font-weight: 800;
    cursor: pointer;
    box-shadow: 0 16px 34px rgba(18, 71, 46, .24);
    transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
}

.owner-login-btn:hover {
    filter: brightness(1.04);
    transform: translateY(-1px);
    box-shadow: 0 18px 40px rgba(18, 71, 46, .28);
}

.owner-login-btn:active {
    transform: translateY(0);
}

.owner-footer-note {
    position: absolute;
    left: 16px;
    right: 16px;
    bottom: 8px;
    color: #8a918a;
    font-size: .72rem;
    text-align: center;
}

@media (max-height: 760px) {
    .owner-brand-icon {
        width: 44px;
        height: 44px;
    }

    .owner-brand-block h1 {
        font-size: 2rem;
    }

    .owner-brand-block p {
        font-size: .62rem;
    }

    .owner-login-card {
        padding: 18px 22px;
    }

    .owner-login-card-header h2 {
        font-size: 1.62rem;
    }

    .owner-login-card-header p,
    .forgot-link button {
        font-size: .84rem;
    }
}
@media (max-width: 560px) {
    .owner-portal-shell {
        padding: 22px 16px;
    }

    .owner-login-page {
        padding-top: 18px;
    }

    .owner-brand-icon {
        width: 72px;
        height: 72px;
    }

    .owner-login-card {
        padding: 24px 20px;
    }
}
</style>
</head>
<body>
<div class="owner-portal-shell">
    <div class="owner-login-toplink">
        <a href="<?php echo htmlspecialchars(ve_url('index.php')); ?>" aria-label="Back to website">
            <span aria-hidden="true">&larr;</span>
            <span>Back to website</span>
        </a>
    </div>

    <main class="owner-login-page" aria-labelledby="ownerLoginTitle">
        <div class="owner-brand-block">
            <img class="owner-brand-icon" src="<?php echo htmlspecialchars(ve_url('assets/admin-login-leaf.png')); ?>" alt="Villa Eusebio leaf mark">
            <h1>Villa Eusebio</h1>
            <p>Management Portal</p>
        </div>

        <section class="owner-login-card">
            <div class="owner-login-card-header">
                <h2 id="ownerLoginTitle">Sign in</h2>
                <p>Access your resort management dashboard.</p>
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

            <?php if ($resetStatus !== ''): ?>
                <div class="<?php echo in_array($resetStatus, ['mail_failed', 'missing_email', 'limited'], true) ? 'login-error' : 'login-success'; ?>">
                    <?php if ($resetStatus === 'updated'): ?>
                        Your password has been updated. Please sign in with the new password.
                    <?php elseif ($resetStatus === 'mail_failed'): ?>
                        The reset link could not be emailed. Please check the Gmail SMTP settings and app password.
                    <?php elseif ($resetStatus === 'missing_email'): ?>
                        No owner reset email is configured. Set the admin reset email in Settings or `ADMIN_EMAIL` in `.env`.
                    <?php elseif ($resetStatus === 'limited'): ?>
                        Too many reset requests were sent recently. Please wait 15 seconds before trying again.
                    <?php else: ?>
                        If the owner reset email is configured, a password reset confirmation link has been sent there.
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="../api/admin_login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

                <div class="owner-form-group">
                    <label for="ownerUsername">Username</label>
                    <div class="owner-password-wrap">
                        <span class="owner-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
                        </span>
                        <input type="text" id="ownerUsername" name="username" placeholder="Enter your username" autocomplete="username" required>
                    </div>
                </div>

                <div class="owner-form-group">
                    <label for="ownerPassword">Password</label>
                    <div class="owner-password-wrap">
                        <span class="owner-input-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </span>
                        <input type="password" id="ownerPassword" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="owner-password-toggle" id="ownerPasswordToggle" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>

                <div class="forgot-link">
                    <button type="submit" id="forgotPasswordLink" form="ownerResetForm">Forgot password?</button>
                </div>

                <button type="submit" class="owner-login-btn">Sign in</button>
            </form>
            <form id="ownerResetForm" action="../api/request_admin_password_reset.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(admin_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            </form>
        </section>
    </main>

    <div class="owner-footer-note">
        &copy; 2026 Villa Eusebio. All rights reserved.
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('ownerPassword');
    const passwordToggle = document.getElementById('ownerPasswordToggle');

    if (passwordInput && passwordToggle) {
        passwordToggle.addEventListener('click', function() {
            const showing = passwordInput.type === 'text';
            passwordInput.type = showing ? 'password' : 'text';
            passwordToggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    }

    const error = document.getElementById('loginError');
    if (!error) return;

    document.querySelectorAll('.owner-password-wrap input').forEach(function(input) {
        input.style.borderColor = '#d24b4b';
        input.animate([
            { transform: 'translateX(0)' },
            { transform: 'translateX(-4px)' },
            { transform: 'translateX(4px)' },
            { transform: 'translateX(0)' }
        ], { duration: 300 });
    });
});
</script>
</body>
</html>
