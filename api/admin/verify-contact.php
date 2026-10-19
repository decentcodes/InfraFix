<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');


function sendResult($success, $message, $status = 200)
{
    http_response_code($status);

    echo json_encode([
        'success' => $success,
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
    sendResult(false, 'Method not allowed. Use POST.', 405);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    sendResult(
        false,
        'Your session token is missing or expired. Refresh the page and try again.',
        403
    );
}

$requestDatabaseId = (int) ($_POST['request_id'] ?? 0);
$contact = trim($_POST['contact'] ?? '');
$verified = trim($_POST['verified'] ?? '');

if ($requestDatabaseId <= 0) {
    sendResult(false, 'Invalid authority access request.', 400);
}

if (!in_array($contact, ['phone', 'email'], true)) {
    sendResult(false, 'Invalid contact type.', 400);
}

if (!in_array($verified, ['0', '1'], true)) {
    sendResult(false, 'Invalid verification value.', 400);
}

// The column name comes from this fixed map, never from user input.
$column = $contact === 'phone' ? 'phone_verified_at' : 'email_verified_at';


try {

    $stmt = $pdo->prepare("
        SELECT id
        FROM authority_access_requests
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([$requestDatabaseId]);

    if (!$stmt->fetchColumn()) {
        sendResult(false, 'Authority access request not found.', 404);
    }

    $value = $verified === '1' ? 'CURRENT_TIMESTAMP' : 'NULL';

    $stmt = $pdo->prepare("
        UPDATE authority_access_requests
        SET {$column} = {$value}
        WHERE id = ?
    ");

    $stmt->execute([$requestDatabaseId]);

    sendResult(
        true,
        $verified === '1'
            ? 'Marked as verified.'
            : 'Verification removed.'
    );

} catch (Throwable $e) {

    error_log('InfraFix contact verification failed: ' . $e->getMessage());

    sendResult(false, 'Could not update the verification status.', 500);
}
