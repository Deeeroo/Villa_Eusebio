<?php
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/booking_repository.php';
require_once __DIR__ . '/capstone2_features.php';

function ve_mail_config(): array {
    $username = trim(ve_env('SMTP_USERNAME'));
    return [
        'enabled' => ve_env_bool('MAIL_ENABLED', false),
        'host' => trim(ve_env('SMTP_HOST', 'smtp.gmail.com')),
        'port' => (int)ve_env('SMTP_PORT', '587'),
        'username' => $username,
        'password' => preg_replace('/\s+/', '', ve_env('SMTP_PASSWORD')),
        'from_email' => trim(ve_env('SMTP_FROM_EMAIL', $username)),
        'from_name' => trim(ve_env('SMTP_FROM_NAME', 'Villa Eusebio')),
        'timeout' => max(5, (int)ve_env('SMTP_TIMEOUT', '20')),
        'app_url' => rtrim(trim(ve_env('APP_URL')), '/'),
    ];
}

function ve_mail_ready(array $config): ?string {
    if (!$config['enabled']) {
        return 'Email sending is disabled in .env.';
    }
    foreach (['host', 'port', 'username', 'password', 'from_email'] as $key) {
        if (empty($config[$key])) {
            return 'Email setting ' . strtoupper($key) . ' is missing.';
        }
    }
    if (str_contains($config['password'], 'paste_your_')) {
        return 'SMTP_PASSWORD still has the placeholder value in .env.';
    }
    if (!filter_var($config['username'], FILTER_VALIDATE_EMAIL) || !filter_var($config['from_email'], FILTER_VALIDATE_EMAIL)) {
        return 'The sender email settings are invalid.';
    }
    if (!extension_loaded('openssl')) {
        return 'PHP OpenSSL extension is required for Gmail SMTP.';
    }
    return null;
}

function ve_mail_header_text(string $value): string {
    return trim(str_replace(["\r", "\n"], '', $value));
}

function ve_mailbox(string $name, string $email): string {
    $email = ve_mail_header_text($email);
    $name = ve_mail_header_text($name);
    if ($name === '') {
        return '<' . $email . '>';
    }
    $name = str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
    return '"' . $name . '" <' . $email . '>';
}

function ve_smtp_read($socket): array {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return [(int)substr($response, 0, 3), trim($response)];
}

function ve_smtp_expect($socket, array $codes, string $step): string {
    [$code, $response] = ve_smtp_read($socket);
    if (!in_array($code, $codes, true)) {
        throw new RuntimeException($step . ' failed: ' . $response);
    }
    return $response;
}

function ve_smtp_command($socket, string $command, array $codes, string $step): string {
    fwrite($socket, $command . "\r\n");
    return ve_smtp_expect($socket, $codes, $step);
}

function ve_send_smtp_mail(string $recipientEmail, string $recipientName, string $subject, string $textBody, string $htmlBody): array {
    $config = ve_mail_config();
    $notReady = ve_mail_ready($config);
    if ($notReady !== null) {
        return ['ok' => false, 'message' => $notReady];
    }
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Customer email address is invalid.'];
    }

    $host = $config['host'];
    $port = $config['port'];
    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $config['timeout']);
    if (!$socket) {
        return ['ok' => false, 'message' => 'Could not connect to SMTP server: ' . $errstr];
    }

    try {
        stream_set_timeout($socket, $config['timeout']);
        ve_smtp_expect($socket, [220], 'SMTP greeting');
        ve_smtp_command($socket, 'EHLO villaeusebio.local', [250], 'SMTP hello');
        ve_smtp_command($socket, 'STARTTLS', [220], 'SMTP TLS');
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('Could not start encrypted SMTP connection.');
        }
        ve_smtp_command($socket, 'EHLO villaeusebio.local', [250], 'SMTP hello after TLS');
        ve_smtp_command($socket, 'AUTH LOGIN', [334], 'SMTP auth');
        ve_smtp_command($socket, base64_encode($config['username']), [334], 'SMTP username');
        ve_smtp_command($socket, base64_encode($config['password']), [235], 'SMTP password');
        ve_smtp_command($socket, 'MAIL FROM:<' . $config['from_email'] . '>', [250], 'SMTP sender');
        ve_smtp_command($socket, 'RCPT TO:<' . $recipientEmail . '>', [250, 251], 'SMTP recipient');
        ve_smtp_command($socket, 'DATA', [354], 'SMTP data');

        $boundary = 'villa_' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ve_mailbox($config['from_name'], $config['from_email']),
            'To: ' . ve_mailbox($recipientName, $recipientEmail),
            'Subject: ' . ve_mail_header_text($subject),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($textBody) . "\r\n\r\n";
        $message .= '--' . $boundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $message .= quoted_printable_encode($htmlBody) . "\r\n\r\n";
        $message .= '--' . $boundary . "--\r\n";
        $message = preg_replace("/\r\n\./", "\r\n..", $message);

        fwrite($socket, $message . "\r\n.\r\n");
        ve_smtp_expect($socket, [250], 'SMTP send');
        ve_smtp_command($socket, 'QUIT', [221], 'SMTP quit');
        fclose($socket);
        return ['ok' => true, 'message' => 'Email sent.'];
    } catch (Throwable $e) {
        fclose($socket);
        return ['ok' => false, 'message' => $e->getMessage()];
    }
}

function ve_outbox_insert(mysqli $conn, array $email): int {
    ve_ensure_capstone2_schema($conn);
    $status = 'sending';
    $deliveryMode = 'smtp';
    $relatedId = (int)($email['related_id'] ?? 0);
    $recipientEmail = (string)$email['recipient_email'];
    $recipientName = (string)$email['recipient_name'];
    $subject = (string)$email['subject'];
    $bodyText = (string)$email['body_text'];
    $bodyHtml = (string)$email['body_html'];
    $triggerKey = (string)$email['trigger_key'];
    $relatedType = (string)$email['related_type'];
    $stmt = mysqli_prepare($conn, "INSERT INTO email_outbox (recipient_email, recipient_name, subject, body_text, body_html, trigger_key, related_type, related_id, delivery_mode, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param(
        $stmt,
        'sssssssiss',
        $recipientEmail,
        $recipientName,
        $subject,
        $bodyText,
        $bodyHtml,
        $triggerKey,
        $relatedType,
        $relatedId,
        $deliveryMode,
        $status
    );
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function ve_outbox_mark(mysqli $conn, int $emailId, string $status, string $errorMessage = ''): void {
    if ($emailId <= 0) {
        return;
    }
    $errorMessage = substr($errorMessage, 0, 1000);
    if ($status === 'sent') {
        $stmt = mysqli_prepare($conn, "UPDATE email_outbox SET status = 'sent', error_message = NULL, sent_at = NOW() WHERE email_id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $emailId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        return;
    }

    $stmt = mysqli_prepare($conn, "UPDATE email_outbox SET status = 'failed', error_message = ? WHERE email_id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $emailId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function ve_booking_email_already_sent(mysqli $conn, int $bookingId, string $triggerKey): bool {
    $relatedType = 'booking';
    $status = 'sent';
    $stmt = mysqli_prepare($conn, "SELECT email_id FROM email_outbox WHERE related_type = ? AND related_id = ? AND trigger_key = ? AND status = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'siss', $relatedType, $bookingId, $triggerKey, $status);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return (bool)$exists;
}

function ve_mail_date(string $date): string {
    $time = strtotime($date);
    return $time ? date('F j, Y', $time) : $date;
}

function ve_mail_stay_label(string $type): string {
    if ($type === 'day') return 'Day Tour';
    if ($type === 'overnight') return 'Overnight';
    if ($type === '22hour') return '22-Hour Stay';
    return ucfirst($type);
}

function ve_mail_money($value): string {
    return 'PHP ' . number_format((float)$value, 2);
}

function ve_mail_escape($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function ve_booking_status_email_content(mysqli $conn, array $booking, string $status, string $reason): array {
    $guestName = trim($booking['guest_name'] ?? 'Guest') ?: 'Guest';
    $stayType = ve_mail_stay_label($booking['time_type'] ?? '');
    $checkIn = ve_mail_date($booking['check_in_date'] ?? '');
    $checkOut = ve_mail_date($booking['check_out_date'] ?? '');
    $guests = (int)($booking['guests'] ?? 0);
    $extraGuestCount = (int)($booking['extra_guest_count'] ?? max($guests - 30, 0));
    $extraGuestFeeValue = (float)($booking['extra_guest_fee'] ?? ($extraGuestCount * 150));
    $extraGuestFee = ve_mail_money($extraGuestFeeValue);
    $extraGuestNote = $extraGuestCount > 0
        ? "{$extraGuestFee} ({$extraGuestCount} guests over the 30-person inclusion x PHP 150.00)"
        : '';
    $reservationFee = ve_mail_money($booking['reservation_fee_amount'] ?? 2000);
    $remainingBalance = ve_mail_money($booking['remaining_balance'] ?? 0);
    $contactPhone = function_exists('ve_setting') ? ve_setting($conn, 'contact_phone', '') : '';
    $contactEmail = function_exists('ve_setting') ? ve_setting($conn, 'contact_email', '') : '';
    $config = ve_mail_config();
    $siteUrl = $config['app_url'];

    if ($status === 'approved') {
        $subject = 'Booking Approved | Villa Eusebio';
        $headline = 'Your booking has been approved';
        $intro = 'Your Villa Eusebio booking has been approved. See you there!';
        $text = "Hi {$guestName},\n\n{$intro}\n\n";
        $text .= "Booking details:\n";
        $text .= "- Stay type: {$stayType}\n";
        $text .= "- Check-in: {$checkIn}\n";
        $text .= "- Check-out: {$checkOut}\n";
        $text .= "- Guests: {$guests}\n";
        if ($extraGuestNote !== '') {
            $text .= "- Additional guest fee: {$extraGuestNote}\n";
        }
        $text .= "- Reservation fee: {$reservationFee}\n";
        $text .= "- Remaining balance: {$remainingBalance}\n\n";
        $text .= "Please keep your payment proof and prepare any remaining balance for your stay.\n";
    } else {
        $subject = 'Booking Update | Villa Eusebio';
        $headline = 'Your booking needs clarification';
        $intro = 'We could not approve your booking request because the submitted payment proof could not be verified.';
        $text = "Hi {$guestName},\n\n{$intro}\n";
        if ($reason !== '') {
            $text .= "Reason: {$reason}\n";
        }
        $text .= "\nPlease contact Villa Eusebio admin for clarification before making another booking request.\n\n";
        $text .= "Requested schedule:\n";
        $text .= "- Stay type: {$stayType}\n";
        $text .= "- Check-in: {$checkIn}\n";
        $text .= "- Check-out: {$checkOut}\n";
    }

    if ($contactPhone !== '' || $contactEmail !== '') {
        $text .= "\nContact us:\n";
        if ($contactPhone !== '') $text .= "- Phone: {$contactPhone}\n";
        if ($contactEmail !== '') $text .= "- Email: {$contactEmail}\n";
    }

    $detailsHtml = '<ul>'
        . '<li><strong>Stay type:</strong> ' . ve_mail_escape($stayType) . '</li>'
        . '<li><strong>Check-in:</strong> ' . ve_mail_escape($checkIn) . '</li>'
        . '<li><strong>Check-out:</strong> ' . ve_mail_escape($checkOut) . '</li>'
        . '<li><strong>Guests:</strong> ' . ve_mail_escape((string)$guests) . '</li>';
    if ($status === 'approved') {
        if ($extraGuestNote !== '') {
            $detailsHtml .= '<li><strong>Additional guest fee:</strong> ' . ve_mail_escape($extraGuestNote) . '</li>';
        }
        $detailsHtml .= '<li><strong>Reservation fee:</strong> ' . ve_mail_escape($reservationFee) . '</li>'
            . '<li><strong>Remaining balance:</strong> ' . ve_mail_escape($remainingBalance) . '</li>';
    }
    $detailsHtml .= '</ul>';

    $reasonHtml = ($status === 'rejected' && $reason !== '')
        ? '<p><strong>Reason:</strong> ' . ve_mail_escape($reason) . '</p>'
        : '';
    $buttonHtml = $siteUrl !== ''
        ? '<p style="margin-top:24px;"><a href="' . ve_mail_escape($siteUrl) . '" style="background:#1f5f3d;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">Visit Villa Eusebio</a></p>'
        : '';
    $contactHtml = '';
    if ($contactPhone !== '' || $contactEmail !== '') {
        $contactHtml = '<p style="margin-top:20px;color:#5d665f;">';
        if ($contactPhone !== '') $contactHtml .= 'Phone: ' . ve_mail_escape($contactPhone) . '<br>';
        if ($contactEmail !== '') $contactHtml .= 'Email: ' . ve_mail_escape($contactEmail);
        $contactHtml .= '</p>';
    }

    $html = '<div style="margin:0;padding:28px;background:#f5f1e8;font-family:Arial,sans-serif;color:#1f2b24;">'
        . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e3dccf;">'
        . '<div style="background:#173f2f;color:#fff;padding:24px 28px;">'
        . '<h1 style="margin:0;font-size:24px;">Villa Eusebio</h1>'
        . '<p style="margin:6px 0 0;letter-spacing:3px;font-size:12px;">PRIVATE RESORT</p>'
        . '</div>'
        . '<div style="padding:28px;">'
        . '<p style="margin:0 0 12px;color:#647067;">Hi ' . ve_mail_escape($guestName) . ',</p>'
        . '<h2 style="margin:0 0 14px;color:#173f2f;font-size:26px;">' . ve_mail_escape($headline) . '</h2>'
        . '<p style="font-size:16px;line-height:1.6;">' . ve_mail_escape($intro) . '</p>'
        . $reasonHtml
        . $detailsHtml
        . $buttonHtml
        . $contactHtml
        . '</div>'
        . '<div style="padding:18px 28px;background:#f8f6f0;color:#7b827b;font-size:13px;">'
        . 'This email was sent because a booking request was updated by Villa Eusebio admin.'
        . '</div></div></div>';

    return ['subject' => $subject, 'text' => $text, 'html' => $html];
}

function ve_send_booking_status_email(mysqli $conn, int $bookingId, string $status, string $reason = ''): array {
    if (!in_array($status, ['approved', 'rejected'], true)) {
        return ['ok' => true, 'message' => 'No customer email needed for this status.'];
    }

    $booking = ve_fetch_booking_by_id($conn, $bookingId);
    if (!$booking) {
        return ['ok' => false, 'message' => 'Booking not found for email.'];
    }

    $triggerKey = 'booking_' . $status;
    if (ve_booking_email_already_sent($conn, $bookingId, $triggerKey)) {
        return ['ok' => true, 'message' => 'Customer email was already sent.'];
    }

    $recipientEmail = trim($booking['email'] ?? '');
    $recipientName = trim($booking['guest_name'] ?? 'Guest');
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Customer email address is invalid.'];
    }

    $content = ve_booking_status_email_content($conn, $booking, $status, $reason);
    $emailId = ve_outbox_insert($conn, [
        'recipient_email' => $recipientEmail,
        'recipient_name' => $recipientName,
        'subject' => $content['subject'],
        'body_text' => $content['text'],
        'body_html' => $content['html'],
        'trigger_key' => $triggerKey,
        'related_type' => 'booking',
        'related_id' => $bookingId,
    ]);

    $result = ve_send_smtp_mail($recipientEmail, $recipientName, $content['subject'], $content['text'], $content['html']);
    ve_outbox_mark($conn, $emailId, $result['ok'] ? 'sent' : 'failed', $result['message']);
    return $result;
}

function ve_subscriber_broadcast_content(mysqli $conn, string $subject, string $message): array {
    $config = ve_mail_config();
    $siteUrl = $config['app_url'];
    $contactPhone = function_exists('ve_setting') ? ve_setting($conn, 'contact_phone', '') : '';
    $contactEmail = function_exists('ve_setting') ? ve_setting($conn, 'contact_email', '') : '';

    $text = "Hello,\n\n" . $message . "\n\n";
    if ($siteUrl !== '') {
        $text .= "Visit Villa Eusebio: {$siteUrl}\n";
    }
    if ($contactPhone !== '' || $contactEmail !== '') {
        $text .= "\nContact us:\n";
        if ($contactPhone !== '') $text .= "- Phone: {$contactPhone}\n";
        if ($contactEmail !== '') $text .= "- Email: {$contactEmail}\n";
    }
    $text .= "\nYou are receiving this because you subscribed to Villa Eusebio updates.";

    $messageHtml = nl2br(ve_mail_escape($message));
    $buttonHtml = $siteUrl !== ''
        ? '<p style="margin-top:24px;"><a href="' . ve_mail_escape($siteUrl) . '" style="background:#1f5f3d;color:#fff;text-decoration:none;padding:12px 18px;border-radius:6px;font-weight:700;">Visit Villa Eusebio</a></p>'
        : '';
    $contactHtml = '';
    if ($contactPhone !== '' || $contactEmail !== '') {
        $contactHtml = '<p style="margin-top:20px;color:#5d665f;">';
        if ($contactPhone !== '') $contactHtml .= 'Phone: ' . ve_mail_escape($contactPhone) . '<br>';
        if ($contactEmail !== '') $contactHtml .= 'Email: ' . ve_mail_escape($contactEmail);
        $contactHtml .= '</p>';
    }

    $html = '<div style="margin:0;padding:28px;background:#f5f1e8;font-family:Arial,sans-serif;color:#1f2b24;">'
        . '<div style="max-width:640px;margin:0 auto;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #e3dccf;">'
        . '<div style="background:#173f2f;color:#fff;padding:24px 28px;">'
        . '<h1 style="margin:0;font-size:24px;">Villa Eusebio</h1>'
        . '<p style="margin:6px 0 0;letter-spacing:3px;font-size:12px;">PRIVATE RESORT</p>'
        . '</div>'
        . '<div style="padding:28px;">'
        . '<p style="margin:0 0 12px;color:#647067;">Hello,</p>'
        . '<h2 style="margin:0 0 14px;color:#173f2f;font-size:26px;">' . ve_mail_escape($subject) . '</h2>'
        . '<p style="font-size:16px;line-height:1.65;">' . $messageHtml . '</p>'
        . $buttonHtml
        . $contactHtml
        . '</div>'
        . '<div style="padding:18px 28px;background:#f8f6f0;color:#7b827b;font-size:13px;">'
        . 'You are receiving this because you subscribed to Villa Eusebio updates.'
        . '</div></div></div>';

    return ['text' => $text, 'html' => $html];
}

function ve_send_subscriber_broadcast(mysqli $conn, string $subject, string $message): array {
    ve_ensure_capstone2_schema($conn);
    $subject = trim($subject);
    $message = trim($message);

    if ($subject === '' || $message === '') {
        return ['ok' => false, 'total' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'Please add both a subject and message.'];
    }

    $result = mysqli_query($conn, "SELECT subscriber_id, email FROM email_subscribers ORDER BY subscribed_at DESC, subscriber_id DESC");
    if (!$result) {
        return ['ok' => false, 'total' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'Unable to load subscribers.'];
    }

    $subscribers = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $subscribers[] = $row;
    }

    $total = count($subscribers);
    if ($total === 0) {
        return ['ok' => false, 'total' => 0, 'sent' => 0, 'failed' => 0, 'message' => 'There are no subscribers to email yet.'];
    }

    $content = ve_subscriber_broadcast_content($conn, $subject, $message);
    $sent = 0;
    $failed = 0;
    $firstError = '';

    foreach ($subscribers as $subscriber) {
        $recipientEmail = trim((string)($subscriber['email'] ?? ''));
        $subscriberId = (int)($subscriber['subscriber_id'] ?? 0);
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            $failed++;
            if ($firstError === '') $firstError = 'One subscriber has an invalid email address.';
            continue;
        }

        $emailId = ve_outbox_insert($conn, [
            'recipient_email' => $recipientEmail,
            'recipient_name' => 'Subscriber',
            'subject' => $subject,
            'body_text' => $content['text'],
            'body_html' => $content['html'],
            'trigger_key' => 'subscriber_broadcast',
            'related_type' => 'subscriber',
            'related_id' => $subscriberId,
        ]);

        $sendResult = ve_send_smtp_mail($recipientEmail, 'Subscriber', $subject, $content['text'], $content['html']);
        ve_outbox_mark($conn, $emailId, $sendResult['ok'] ? 'sent' : 'failed', $sendResult['message']);
        if ($sendResult['ok']) {
            $sent++;
        } else {
            $failed++;
            if ($firstError === '') $firstError = $sendResult['message'];
        }
    }

    if ($sent > 0 && $failed === 0) {
        return ['ok' => true, 'total' => $total, 'sent' => $sent, 'failed' => $failed, 'message' => "Email sent to {$sent} subscriber" . ($sent === 1 ? '.' : 's.')];
    }
    if ($sent > 0) {
        return ['ok' => true, 'total' => $total, 'sent' => $sent, 'failed' => $failed, 'message' => "Email sent to {$sent} subscriber" . ($sent === 1 ? '' : 's') . ", but {$failed} failed."];
    }

    return ['ok' => false, 'total' => $total, 'sent' => $sent, 'failed' => $failed, 'message' => 'Subscriber email was not sent: ' . ($firstError ?: 'Unknown email error.')];
}
