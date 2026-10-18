<?php

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/settings.php';

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
if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your session token is invalid or expired. Refresh the page and try again.'
    ]);
    exit;
}

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
 * Limit OTP issuance per phone number.
 *
 * Allow at most one request per 60 seconds and five requests per hour.
 * This uses the existing OTP request history, so no schema migration is
 * required. A separate IP-level limit should be added before public launch.
 */
$stmt = $pdo->prepare("
    SELECT GREATEST(1, 60 - TIMESTAMPDIFF(SECOND, created_at, NOW())) AS retry_after
    FROM citizen_otp_requests
    WHERE phone = :phone
      AND created_at >= DATE_SUB(NOW(), INTERVAL 60 SECOND)
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([':phone' => $phone]);
$recentRequestAt = $stmt->fetchColumn();

if ($recentRequestAt !== false) {
    $retryAfter = (int) $recentRequestAt;
    header('Retry-After: ' . $retryAfter);
    sendError('Please wait before requesting another OTP.', 429);
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM citizen_otp_requests
    WHERE phone = :phone
      AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$stmt->execute([':phone' => $phone]);
$requestsLastHour = (int) $stmt->fetchColumn();

if ($requestsLastHour >= 5) {
    sendError('OTP request limit reached. Please try again later.', 429);
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
 * OTP delivery mode (app_settings.otp_mode):
 *   demo - the OTP is returned in the response and shown on screen.
 *          For the college demo only: no SMS provider is configured.
 *   off  - OTP sign-in is disabled.
 * Replace the 'demo' branch with a real SMS/e-mail provider call
 * before using InfraFix with real users.
 */
$otpMode = getAppSetting($pdo, 'otp_mode', 'off');

if ($otpMode !== 'demo') {
    http_response_code(503);

    echo json_encode([
        'success' => false,
        'message' => 'OTP delivery is not configured. Please try again later.'
    ]);

    exit;
}

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
 * Older requests are kept (not deleted) so the per-phone rate limit above
 * can count them. verify-otp.php always checks only the newest request.
 */


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
 * Never log or return the OTP unless demo mode is on (checked above).
 */
error_log('[InfraFix DEMO OTP issued for ' . substr($phone, 0, 2) . '********]');

echo json_encode([
    'success' => true,
    'message' => 'Demo mode: your verification code is shown on screen.',
    'demo_otp' => $otp,
    'is_existing_citizen' => $citizen !== false
]);
