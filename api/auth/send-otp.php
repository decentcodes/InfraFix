<?php

header('Content-Type: application/json');

require_once '../../includes/db.php';

function sendError($message, $statusCode = 400)
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}


/*
 * Read phone number.
 */
$phone = trim($_POST['phone'] ?? '');

if ($phone === '') {
    sendError('Phone number is required.');
}


/*
 * Basic phone validation.
 *
 * For the current India-focused version, accept:
 * 10-digit Indian mobile numbers.
 */
if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    sendError('Enter a valid 10-digit mobile number.');
}


/*
 * Check whether the phone belongs to an active citizen.
 *
 * We do not create the citizen yet.
 * Account creation happens only after successful OTP verification
 * and password creation.
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
 * Generate a six-digit OTP.
 */
$otp = (string) random_int(100000, 999999);


/*
 * Hash the OTP before storing it.
 */
$otpHash = password_hash($otp, PASSWORD_DEFAULT);


/*
 * Generate an identifier for this OTP request.
 */
$otpRequestId = strtoupper(bin2hex(random_bytes(16)));


/*
 * OTP validity: 5 minutes.
 */
$expiresAt = date('Y-m-d H:i:s', time() + 300);


/*
 * Remove older unverified OTP requests for this phone.
 *
 * This ensures that only the latest OTP remains valid.
 */
$stmt = $pdo->prepare("
    DELETE FROM citizen_otp_requests
    WHERE phone = :phone
      AND verified_at IS NULL
");
$stmt->execute([':phone' => $phone]);


/*
 * Store the new OTP request.
 */
$stmt = $pdo->prepare("
    INSERT INTO citizen_otp_requests
        (
            otp_request_id,
            phone,
            otp_hash,
            expires_at
        )
    VALUES
        (
            :otp_request_id,
            :phone,
            :otp_hash,
            :expires_at
        )
");

$stmt->execute([
    ':otp_request_id' => $otpRequestId,
    ':phone' => $phone,
    ':otp_hash' => $otpHash,
    ':expires_at' => $expiresAt
]);


/*
 * Development mode:
 *
 * No SMS provider has been integrated yet.
 * Return the OTP so that we can test the complete
 * authentication flow locally.
 *
 * IMPORTANT:
 * This must be removed when real SMS delivery is implemented.
 */
echo json_encode([
    'success' => true,
    'message' => 'OTP generated successfully.',
    'is_existing_citizen' => $citizen !== false,
    'development_otp' => $otp
]);
