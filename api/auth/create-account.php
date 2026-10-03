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


/*
|--------------------------------------------------------------------------
| Check OTP verification state
|--------------------------------------------------------------------------
*/

$phone = getOtpVerifiedPhone();

if ($phone === null) {
    sendError('Phone verification is required before creating an account.');
}


/*
|--------------------------------------------------------------------------
| Get password
|--------------------------------------------------------------------------
*/

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if ($password === '') {
    sendError('Password is required.');
}

if ($confirmPassword === '') {
    sendError('Please confirm your password.');
}

if ($password !== $confirmPassword) {
    sendError('Passwords do not match.');
}

if (strlen($password) < 8) {
    sendError('Password must be at least 8 characters long.');
}


/*
|--------------------------------------------------------------------------
| Make sure the phone number is not already registered
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM citizens
    WHERE phone = :phone
    LIMIT 1
");

$stmt->execute([
    ':phone' => $phone
]);

if ($stmt->fetch()) {
    clearOtpVerifiedPhone();

    sendError('An account with this phone number already exists.');
}


/*
|--------------------------------------------------------------------------
| Generate Citizen ID
|--------------------------------------------------------------------------
*/

do {

    $citizenId = 'CIT-' . strtoupper(
        substr(bin2hex(random_bytes(4)), 0, 8)
    );

    $stmt = $pdo->prepare("
        SELECT id
        FROM citizens
        WHERE citizen_id = :citizen_id
        LIMIT 1
    ");

    $stmt->execute([
        ':citizen_id' => $citizenId
    ]);
} while ($stmt->fetch());


/*
|--------------------------------------------------------------------------
| Hash password
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash($password, PASSWORD_DEFAULT);


/*
|--------------------------------------------------------------------------
| Create citizen account
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    INSERT INTO citizens
        (citizen_id, phone, password_hash)
    VALUES
        (:citizen_id, :phone, :password_hash)
");

$stmt->execute([
    ':citizen_id' => $citizenId,
    ':phone' => $phone,
    ':password_hash' => $passwordHash
]);

$newCitizenId = $pdo->lastInsertId();

$citizen = [
    'id' => $newCitizenId,
    'citizen_id' => $citizenId
];

loginCitizen($citizen);

/*
|--------------------------------------------------------------------------
| Clear OTP verification state
|--------------------------------------------------------------------------
*/

clearOtpVerifiedPhone();


/*
|--------------------------------------------------------------------------
| Return successful response
|--------------------------------------------------------------------------
*/

echo json_encode([
    'success' => true,
    'message' => 'Account created successfully.',
    'citizen_id' => $citizenId
]);
