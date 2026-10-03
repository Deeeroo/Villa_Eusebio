<?php
require_once '../includes/admin_auth.php';
admin_start_session();
admin_security_headers();

function reset_request_redirect(string $status = 'sent'): void {
    header('Location: ../pages/owner.php?reset=' . urlencode($status));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !admin_verify_csrf_token($_POST['csrf_token'] ?? null)) {
    header('Location: ../pages/owner.php?error=auth');
    exit;
}

include '../includes/db.php';
require_once '../includes/mailer.php';
require_once '../includes/capstone2_features.php';
ve_ensure_capstone2_schema($conn);

$sourceIp = admin_client_ip();
$userAgent = admin_user_agent();

$recentCount = 0;
$rateStmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM admin_password_resets WHERE source_ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)");
if ($rateStmt) {
    mysqli_stmt_bind_param($rateStmt, 's', $sourceIp);
    mysqli_stmt_execute($rateStmt);
    $rateResult = mysqli_stmt_get_result($rateStmt);
    $rateRow = $rateResult ? mysqli_fetch_assoc($rateResult) : null;
    mysqli_stmt_close($rateStmt);
    $recentCount = (int)($rateRow['total'] ?? 0);
}

if ($recentCount >= 1) {
    reset_request_redirect('limited');
}

$admin = null;
$contactEmail = function_exists('ve_setting') ? trim(ve_setting($conn, 'contact_email', '')) : '';
$adminResult = mysqli_query($conn, "SELECT id, full_name, username, email FROM admins ORDER BY id ASC LIMIT 1");
$admin = $adminResult ? mysqli_fetch_assoc($adminResult) : null;

if (!$admin) {
    reset_request_redirect('missing_email');
}

$recipientEmail = trim($admin['email'] ?? '');
if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    $recipientEmail = $contactEmail;
}
if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
    reset_request_redirect('missing_email');
}

$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$adminId = (int)$admin['id'];

$insertStmt = mysqli_prepare($conn, "INSERT INTO admin_password_resets (admin_id, token_hash, expires_at, source_ip, user_agent) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), ?, ?)");
if (!$insertStmt) {
    reset_request_redirect('mail_failed');
}
mysqli_stmt_bind_param($insertStmt, 'isss', $adminId, $tokenHash, $sourceIp, $userAgent);
if (!mysqli_stmt_execute($insertStmt)) {
    mysqli_stmt_close($insertStmt);
    reset_request_redirect('mail_failed');
}
$resetId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($insertStmt);

$config = ve_mail_config();
if ($config['app_url'] !== '') {
    $resetUrl = rtrim($config['app_url'], '/') . '/pages/reset-password.php?token=' . urlencode($token);
} else {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $origin = ($secure ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $resetUrl = rtrim($origin, '/') . ve_url('pages/reset-password.php?token=' . urlencode($token));
}

$recipientName = trim($admin['full_name'] ?? 'Villa Eusebio Owner') ?: 'Villa Eusebio Owner';
$subject = 'Reset your Villa Eusebio admin password';
$text = "Hi {$recipientName},\n\n"
    . "We received a request to reset the Villa Eusebio admin password.\n\n"
    . "Open this secure link to choose a new password:\n{$resetUrl}\n\n"
    . "This link expires in 1 hour and can only be used once. Your current password stays active unless this link is opened and a new password is saved.\n\n"
    . "If you did not request this change, ignore this email and keep your current password.\n";
$html = '<div style="margin:0;padding:28px;background:#f5f1e8;font-family:Arial,sans-serif;color:#1f2b24;">'
    . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e3dccf;">'
    . '<div style="background:#173f2f;color:#fff;padding:24px 28px;">'
    . '<h1 style="margin:0;font-size:24px;">Villa Eusebio</h1>'
    . '<p style="margin:6px 0 0;letter-spacing:3px;font-size:12px;">ADMIN PASSWORD RESET</p>'
    . '</div><div style="padding:28px;">'
    . '<p style="margin:0 0 12px;color:#647067;">Hi ' . ve_mail_escape($recipientName) . ',</p>'
    . '<h2 style="margin:0 0 14px;color:#173f2f;font-size:26px;">Reset your admin password</h2>'
    . '<p style="font-size:16px;line-height:1.6;">We received a request to reset the Villa Eusebio admin password.</p>'
    . '<p style="margin-top:24px;"><a href="' . ve_mail_escape($resetUrl) . '" style="background:#1f5f3d;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">Choose New Password</a></p>'
    . '<p style="font-size:14px;line-height:1.6;color:#5d665f;">This link expires in 1 hour and can only be used once. Your current password stays active unless this link is opened and a new password is saved.</p>'
    . '<p style="font-size:14px;line-height:1.6;color:#5d665f;">If you did not request this change, ignore this email and keep your current password.</p>'
    . '</div></div></div>';

$outboxText = "Admin password reset email sent to {$recipientEmail}. The one-time reset link is omitted from the database log for security.";
$outboxHtml = '<p>Admin password reset email sent to ' . ve_mail_escape($recipientEmail) . '.</p><p>The one-time reset link is omitted from the database log for security.</p>';

$emailId = ve_outbox_insert($conn, [
    'recipient_email' => $recipientEmail,
    'recipient_name' => $recipientName,
    'subject' => $subject,
    'body_text' => $outboxText,
    'body_html' => $outboxHtml,
    'trigger_key' => 'admin_password_reset',
    'related_type' => 'admin',
    'related_id' => $adminId,
]);
$sendResult = ve_send_smtp_mail($recipientEmail, $recipientName, $subject, $text, $html);
ve_outbox_mark($conn, $emailId, $sendResult['ok'] ? 'sent' : 'failed', $sendResult['message']);

if ($sendResult['ok']) {
    $cleanupStmt = mysqli_prepare($conn, "UPDATE admin_password_resets SET used_at = NOW() WHERE admin_id = ? AND used_at IS NULL AND reset_id <> ?");
    if ($cleanupStmt) {
        mysqli_stmt_bind_param($cleanupStmt, 'ii', $adminId, $resetId);
        mysqli_stmt_execute($cleanupStmt);
        mysqli_stmt_close($cleanupStmt);
    }
} else {
    $disableStmt = mysqli_prepare($conn, "UPDATE admin_password_resets SET used_at = NOW() WHERE reset_id = ?");
    if ($disableStmt) {
        mysqli_stmt_bind_param($disableStmt, 'i', $resetId);
        mysqli_stmt_execute($disableStmt);
        mysqli_stmt_close($disableStmt);
    }
}

reset_request_redirect($sendResult['ok'] ? 'sent' : 'mail_failed');
?>
