<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/appointment.php');
    exit;
}

include "../includes/db.php";
include "../includes/booking_availability.php";
require_once "../includes/ocr_helper.php";
ve_ensure_capstone2_schema($conn);
date_default_timezone_set('Asia/Manila');

function respondError($message) {
    http_response_code(400);
    echo $message;
    exit;
}

function uploadProofFile($fieldName, $paymentMethod) {
    $label = $paymentMethod === 'cash' ? 'valid ID' : 'payment proof';

    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['name'] === '') {
        respondError('Please upload your ' . $label . '.');
    }

    if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        respondError(ucfirst($label) . ' upload failed.');
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
    $originalName = $_FILES[$fieldName]['name'];
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        respondError('Invalid uploaded file. Only JPG, JPEG, PNG, WEBP, and PDF are allowed.');
    }

    if ($_FILES[$fieldName]['size'] > 5 * 1024 * 1024) {
        respondError('Uploaded file is too large. Maximum size is 5MB.');
    }

    $uploadDir = '../uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $fileName = time() . '_' . $safeBaseName . '.' . $extension;
    $targetFile = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetFile)) {
        respondError('Unable to save the uploaded file.');
    }

    return $fileName;
}

if (ve_bookings_paused($conn)) {
    respondError(ve_booking_pause_message($conn));
}

$name = trim($_POST['guest_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$address = trim($_POST['address'] ?? '');
$guests = trim($_POST['guests'] ?? '');
$paymentMethod = trim($_POST['payment_method'] ?? '');
$specialRequests = trim($_POST['special_requests'] ?? '');
$checkin = trim($_POST['check_in_date'] ?? '');
$checkout = trim($_POST['check_out_date'] ?? '');
$timeType = trim($_POST['time_type'] ?? '');

if ($name === '' || $email === '' || $mobile === '' || $address === '' || $guests === '' || $paymentMethod === '' || $checkin === '' || $checkout === '' || $timeType === '') {
    respondError('Please complete all required fields.');
}
if (!preg_match('/^[A-Za-z]+(?: [A-Za-z]+)*$/', $name)) respondError('Full name must contain letters and spaces only.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respondError('Invalid email address.');
if (!preg_match('/^(09\d{9}|\+639\d{9})$/', $mobile)) respondError('Invalid Philippine mobile number.');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkin) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkout)) respondError('Invalid date format.');

$allowedPayments = ['gcash', 'bdo', 'unionbank', 'cash'];
if (!in_array($paymentMethod, $allowedPayments, true)) respondError('Invalid payment method.');

$requiredSlots = ve_required_slots($timeType);
if (empty($requiredSlots)) respondError('Invalid stay type.');

$today = date('Y-m-d');
if ($checkin < $today) respondError('Past dates are not allowed.');
if (ve_stay_start_passed_today($checkin, $timeType)) respondError(ve_stay_start_passed_message($timeType));

$expectedCheckout = ve_checkout_date($checkin, $timeType);
if ($checkout !== $expectedCheckout) respondError('Selected check-out date does not match the chosen stay type.');

$guestsInt = (int) $guests;
if ($guestsInt <= 0) respondError('Number of guests must be at least 1.');
if ($guestsInt > 50) respondError('50 above is impossible for the resort capacity.');

$availability = ve_check_date_availability($conn, $checkin, $timeType);
if (!$availability['available']) {
    respondError($availability['message'] ?: 'Selected date is not available for the chosen stay type.');
}

$reservationFeeAmount = 2000.00;
$proofOfPayment = uploadProofFile('proof_of_payment', $paymentMethod);
$proofPath = realpath(__DIR__ . '/../uploads/' . $proofOfPayment) ?: (__DIR__ . '/../uploads/' . $proofOfPayment);
$ocrResult = ve_scan_payment_proof_ocr($proofPath, $paymentMethod, $reservationFeeAmount);
$reservationFeeStatus = 'unpaid';
$paymentStatus = 'unpaid';
$status = 'pending';
$baseStayValue = ve_get_base_price($timeType);
$extraGuestCount = max($guestsInt - 30, 0);
$extraGuestFee = $extraGuestCount * 150.0;
$totalStayValue = $baseStayValue + $extraGuestFee;
$remainingBalance = max($totalStayValue - $reservationFeeAmount, 0);

mysqli_begin_transaction($conn);
try {
    $guestStmt = mysqli_prepare($conn, "INSERT INTO guests (guest_name, email, mobile, address) VALUES (?, ?, ?, ?)");
    if (!$guestStmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($guestStmt, 'ssss', $name, $email, $mobile, $address);
    if (!mysqli_stmt_execute($guestStmt)) {
        throw new Exception(mysqli_stmt_error($guestStmt));
    }
    $guestId = mysqli_insert_id($conn);
    mysqli_stmt_close($guestStmt);

    $bookingStmt = mysqli_prepare($conn, "INSERT INTO bookings (guest_id, check_in_date, check_out_date, guests, time_type, special_requests, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if (!$bookingStmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($bookingStmt, 'ississs', $guestId, $checkin, $checkout, $guestsInt, $timeType, $specialRequests, $status);
    if (!mysqli_stmt_execute($bookingStmt)) {
        throw new Exception(mysqli_stmt_error($bookingStmt));
    }
    $bookingId = mysqli_insert_id($conn);
    mysqli_stmt_close($bookingStmt);

    $paymentStmt = mysqli_prepare($conn, "INSERT INTO payments (booking_id, payment_method, proof_of_payment, reservation_fee_amount, reservation_fee_status, remaining_balance, payment_status, ocr_status, ocr_text, ocr_reference, ocr_amount, ocr_notes, ocr_scanned_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$paymentStmt) {
        throw new Exception(mysqli_error($conn));
    }
    $ocrStatus = $ocrResult['status'];
    $ocrText = $ocrResult['text'];
    $ocrReference = $ocrResult['reference'];
    $ocrAmount = $ocrResult['amount'];
    $ocrNotes = $ocrResult['notes'];
    $ocrScannedAt = $ocrResult['scanned_at'];
    mysqli_stmt_bind_param($paymentStmt, 'issdsdssssdss', $bookingId, $paymentMethod, $proofOfPayment, $reservationFeeAmount, $reservationFeeStatus, $remainingBalance, $paymentStatus, $ocrStatus, $ocrText, $ocrReference, $ocrAmount, $ocrNotes, $ocrScannedAt);
    if (!mysqli_stmt_execute($paymentStmt)) {
        throw new Exception(mysqli_stmt_error($paymentStmt));
    }
    mysqli_stmt_close($paymentStmt);

    $chargesStmt = mysqli_prepare($conn, "INSERT INTO charges (booking_id, base_stay_value, extra_guest_count, extra_guest_fee, total_stay_value) VALUES (?, ?, ?, ?, ?)");
    if (!$chargesStmt) {
        throw new Exception(mysqli_error($conn));
    }
    mysqli_stmt_bind_param($chargesStmt, 'ididd', $bookingId, $baseStayValue, $extraGuestCount, $extraGuestFee, $totalStayValue);
    if (!mysqli_stmt_execute($chargesStmt)) {
        throw new Exception(mysqli_stmt_error($chargesStmt));
    }
    mysqli_stmt_close($chargesStmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    respondError('Error saving booking: ' . $e->getMessage());
}

$proofLabel = $proofOfPayment !== '' ? htmlspecialchars($proofOfPayment) : 'No payment proof uploaded';
$proofUrl = $proofOfPayment !== '' ? '../uploads/' . rawurlencode($proofOfPayment) : '';
$proofExt = strtolower(pathinfo($proofOfPayment, PATHINFO_EXTENSION));
$isImageProof = in_array($proofExt, ['jpg', 'jpeg', 'png', 'webp'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Submitted</title>
    <link rel="stylesheet" href="../responsive-fixes.css">
    <style>
        body {margin:0;font-family:Arial,sans-serif;background:#F5F3EF;min-height:100vh;display:flex;justify-content:center;align-items:center;color:#2E2E2E;padding:20px;box-sizing:border-box;}
        .success-box {width:100%;max-width:820px;background:#fff;border:1px solid #ddd;border-radius:14px;padding:36px 28px;text-align:center;box-shadow:0 8px 24px rgba(0,0,0,0.08);}
        .success-box h1 {margin-top:0;color:#2f6f4f;}
        .success-lead {max-width:620px;margin:0 auto 18px;line-height:1.6;}
        .booking-reference {display:inline-flex;align-items:center;gap:8px;margin:4px 0 16px;padding:8px 14px;border-radius:999px;background:#edf5ee;color:#255c42;font-weight:800;}
        .fee-summary {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;max-width:560px;margin:0 auto 18px;}
        .fee-summary p {margin:0;padding:13px 14px;border:1px solid #e6dccf;border-radius:12px;background:#fffaf4;}
        .next-steps {margin:20px auto 0;max-width:680px;text-align:left;padding:18px 20px;border:1px solid #d9e5d8;border-radius:14px;background:#f5faf4;}
        .next-steps h2 {margin:0 0 8px;color:#1f5d40;font-size:20px;}
        .next-steps p {margin:0 0 12px;line-height:1.6;color:#4f574f;}
        .next-steps ul {margin:0;padding-left:20px;color:#4d4d46;line-height:1.7;}
        .next-steps li + li {margin-top:4px;}
        .proof-preview{margin-top:18px;padding:14px;border:1px solid #e6e0d8;border-radius:12px;background:#faf7f2;}
        .proof-preview img{max-width:100%;height:auto;border-radius:10px;border:1px solid #ddd;}
        .back-link{display:inline-block;margin-top:18px;padding:12px 18px;border-radius:10px;background:#2f6f4f;color:#fff;text-decoration:none;font-weight:800;}
        @media (max-width: 620px) {.fee-summary{grid-template-columns:1fr}.success-box{padding:28px 18px}.next-steps{padding:16px}}
    </style>
</head>
<body>
<div class="success-box">
    <h1>Booking submitted successfully</h1>
    <p class="success-lead">Your reservation request is now pending admin review. Please wait for a while as we check your selected date, guest details, and uploaded proof.</p>
    <div class="booking-reference">Booking Reference #<?php echo (int)$bookingId; ?></div>
    <div class="fee-summary">
        <p><strong>Reservation Fee:</strong><br>₱<?php echo number_format($reservationFeeAmount, 2); ?></p>
        <p><strong>Remaining Balance:</strong><br>₱<?php echo number_format($remainingBalance, 2); ?></p>
    </div>
    <div class="next-steps">
        <h2>What happens next?</h2>
        <p>Villa Eusebio admin will review your request and contact you through the mobile number or email address you submitted.</p>
        <ul>
            <li>Please keep your phone and email available for confirmation or follow-up questions.</li>
            <li>Your booking is not final until the admin approves it.</li>
            <li>Once approved, you will receive confirmation and instructions for the remaining balance.</li>
        </ul>
    </div>
    <div class="proof-preview">
        <p><strong>Uploaded File:</strong> <?php echo $proofLabel; ?></p>
        <?php if ($proofUrl && $isImageProof): ?>
            <img src="<?php echo $proofUrl; ?>" alt="Uploaded proof">
        <?php elseif ($proofUrl): ?>
            <p><a href="<?php echo $proofUrl; ?>" target="_blank" rel="noopener">Open uploaded file</a></p>
        <?php endif; ?>
    </div>
    <a class="back-link" href="../pages/appointment.php">Back to Calendar</a>
</div>
</body>
</html>
