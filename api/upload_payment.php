<?php
include "../includes/db.php";
require_once "../includes/capstone2_features.php";
require_once "../includes/ocr_helper.php";
ve_ensure_capstone2_schema($conn);

$bookingId = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;
if ($bookingId <= 0) die('Invalid booking.');
if (!isset($_FILES['proof']) || $_FILES['proof']['name'] === '') die('Please upload proof of payment.');
if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
if ($_FILES['proof']['error'] !== UPLOAD_ERR_OK) die('File upload failed.');

$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
$originalName = $_FILES['proof']['name'];
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) die('Invalid file type. Only JPG, JPEG, PNG, WEBP, and PDF are allowed.');
if ($_FILES['proof']['size'] > 5 * 1024 * 1024) die('File is too large. Maximum size is 5MB.');

$safeBaseName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
$fileName = time() . '_' . $safeBaseName . '.' . $extension;
$targetFile = '../uploads/' . $fileName;
if (!move_uploaded_file($_FILES['proof']['tmp_name'], $targetFile)) die('File upload failed.');

$paymentMethod = '';
$methodStmt = mysqli_prepare($conn, "SELECT payment_method FROM payments WHERE booking_id = ? LIMIT 1");
if ($methodStmt) {
    mysqli_stmt_bind_param($methodStmt, 'i', $bookingId);
    mysqli_stmt_execute($methodStmt);
    $methodResult = mysqli_stmt_get_result($methodStmt);
    $methodRow = $methodResult ? mysqli_fetch_assoc($methodResult) : null;
    $paymentMethod = $methodRow['payment_method'] ?? '';
    mysqli_stmt_close($methodStmt);
}

$ocrResult = ve_scan_payment_proof_ocr(realpath($targetFile) ?: (__DIR__ . '/../uploads/' . $fileName), $paymentMethod, 2000.00);

$stmt = mysqli_prepare($conn, "UPDATE payments SET proof_of_payment = ?, ocr_status = ?, ocr_text = ?, ocr_reference = ?, ocr_amount = ?, ocr_notes = ?, ocr_scanned_at = ? WHERE booking_id = ?");
if (!$stmt) die('Database update failed.');
$ocrStatus = $ocrResult['status'];
$ocrText = $ocrResult['text'];
$ocrReference = $ocrResult['reference'];
$ocrAmount = $ocrResult['amount'];
$ocrNotes = $ocrResult['notes'];
$ocrScannedAt = $ocrResult['scanned_at'];
mysqli_stmt_bind_param($stmt, 'ssssdssi', $fileName, $ocrStatus, $ocrText, $ocrReference, $ocrAmount, $ocrNotes, $ocrScannedAt, $bookingId);
if (mysqli_stmt_execute($stmt)) {
    echo 'Payment submitted successfully.';
} else {
    echo 'Database update failed.';
}
mysqli_stmt_close($stmt);
