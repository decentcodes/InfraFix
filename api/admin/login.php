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

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    sendError('Username and password are required.');
}

$stmt = $pdo->prepare("
    SELECT
        id,
        admin_id,
        username,
        password_hash,
        is_active
    FROM admins
    WHERE username = ?
    LIMIT 1
");

$stmt->execute([$username]);

$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !$admin['is_active']) {
    sendError('Invalid username or password.');
}

if (!password_verify($password, $admin['password_hash'])) {
    sendError('Invalid username or password.');
}

loginAdmin($admin);

echo json_encode([
    'success' => true,
    'message' => 'Login successful.'
]);