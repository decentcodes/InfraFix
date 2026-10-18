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

function sendError($message, $statusCode = 400)
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your session token is invalid or expired. Refresh the page and try again.'
    ]);
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';

/*
|--------------------------------------------------------------------------
| Validate input
|--------------------------------------------------------------------------
*/

if ($phone === '') {
    sendError('Phone number is required.');
}

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    sendError('Enter a valid 10-digit mobile number.');
}

if ($password === '') {
    sendError('Password is required.');
}


/*
|--------------------------------------------------------------------------
| Find citizen
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        citizen_id,
        phone,
        password_hash,
        is_active
    FROM citizens
    WHERE phone = :phone
    LIMIT 1
");

$stmt->execute([
    ':phone' => $phone
]);

$citizen = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$citizen || !$citizen['is_active']) {
    sendError('Invalid phone number or password.', 401);
}


/*
|--------------------------------------------------------------------------
| Verify password
|--------------------------------------------------------------------------
*/

if (!password_verify($password, $citizen['password_hash'])) {
    sendError('Invalid phone number or password.', 401);
}


/*
|--------------------------------------------------------------------------
| Create authenticated session
|--------------------------------------------------------------------------
*/

loginCitizen($citizen);


/*
|--------------------------------------------------------------------------
| Successful login
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'citizen_id' => $citizen['citizen_id']
]);
