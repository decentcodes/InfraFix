<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');


function sendError($message)
{
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}


/*
 * ------------------------------------------------------------
 * Validate request
 * ------------------------------------------------------------
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    sendError('Method not allowed. Use POST.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your session token is invalid or expired. Refresh the page and try again.'
    ]);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';


if ($email === '' || $password === '') {
    sendError('Email and password are required.');
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('Please enter a valid email address.');
}


/*
 * ------------------------------------------------------------
 * Find active authority
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        id,
        authority_id,
        public_name,
        email,
        password_hash,
        is_active
    FROM authorities
    WHERE email = ?
      AND is_active = TRUE
    LIMIT 1
");

$stmt->execute([$email]);

$authority = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$authority) {
    sendError('Invalid email or password.');
}


/*
 * ------------------------------------------------------------
 * Verify password
 * ------------------------------------------------------------
 */

if (!password_verify($password, $authority['password_hash'])) {
    sendError('Invalid email or password.');
}


/*
 * ------------------------------------------------------------
 * Log authority in
 * ------------------------------------------------------------
 */

loginAuthority($authority);


echo json_encode([
    'success' => true,
    'message' => 'Login successful.'
]);