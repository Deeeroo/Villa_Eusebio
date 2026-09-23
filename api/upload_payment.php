<?php
include "../includes/db.php";

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

$stmt = mysqli_prepare($conn, "UPDATE payments SET proof_of_payment = ? WHERE booking_id = ?");
if (!$stmt) die('Database update failed.');
mysqli_stmt_bind_param($stmt, 'si', $fileName, $bookingId);
if (mysqli_stmt_execute($stmt)) {
    echo 'Payment submitted successfully.';
} else {
    echo 'Database update failed.';
}
mysqli_stmt_close($stmt);
