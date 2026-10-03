<?php

header('Content-Type: application/json');

require_once '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit;
}

$phone = trim($_POST['phone'] ?? '');

if (!preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid phone number.'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id
    FROM citizens
    WHERE phone = :phone
      AND is_active = TRUE
    LIMIT 1
");

$stmt->execute([
    ':phone' => $phone
]);

$isExistingCitizen = $stmt->fetch() !== false;

echo json_encode([
    'success' => true,
    'is_existing_citizen' => $isExistingCitizen
]);
