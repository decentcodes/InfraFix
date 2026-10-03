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
