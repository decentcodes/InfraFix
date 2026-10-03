<?php

header('Content-Type: application/json');

require_once '../../includes/db.php';
require_once '../../includes/auth.php';

function sendError($message, $statusCode = 400)
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}

$phone = trim($_POST['phone'] ?? '');
$otp = trim($_POST['otp'] ?? '');

if ($phone === '') {
    sendError('Phone number is required.');
}

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    sendError('Enter a valid 10-digit mobile number.');
}

if ($otp === '') {
    sendError('OTP is required.');
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    sendError('Enter a valid 6-digit OTP.');
}


/*
|--------------------------------------------------------------------------
| Find the latest pending OTP request
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        otp_request_id,
        otp_hash,
        expires_at,
        attempts
    FROM citizen_otp_requests
    WHERE phone = :phone
      AND verified_at IS NULL
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([
    ':phone' => $phone
]);

$otpRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$otpRequest) {
    sendError('No active OTP request found.');
}


/*
|--------------------------------------------------------------------------
| Check expiry
|--------------------------------------------------------------------------
*/

if (strtotime($otpRequest['expires_at']) < time()) {
    sendError('OTP has expired. Please request a new OTP.');
}


/*
|--------------------------------------------------------------------------
| Check attempt limit
|--------------------------------------------------------------------------
*/

if ((int) $otpRequest['attempts'] >= 5) {
    sendError('Too many incorrect attempts. Please request a new OTP.');
}


/*
|--------------------------------------------------------------------------
| Verify OTP
|--------------------------------------------------------------------------
*/

if (!password_verify($otp, $otpRequest['otp_hash'])) {

    $stmt = $pdo->prepare("
        UPDATE citizen_otp_requests
        SET attempts = attempts + 1
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $otpRequest['id']
    ]);

    sendError('Incorrect OTP.');
}


/*
|--------------------------------------------------------------------------
| Mark OTP as verified
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    UPDATE citizen_otp_requests
    SET verified_at = CURRENT_TIMESTAMP
    WHERE id = :id
");

$stmt->execute([
    ':id' => $otpRequest['id']
]);

setOtpVerifiedPhone($phone);


/*
|--------------------------------------------------------------------------
| Check whether the citizen already exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, citizen_id
    FROM citizens
    WHERE phone = :phone
      AND is_active = TRUE
    LIMIT 1
");

$stmt->execute([
    ':phone' => $phone
]);

$citizen = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Successful response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'OTP verified successfully.',
    'is_existing_citizen' => $citizen !== false
]);
