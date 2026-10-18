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
    sendError('Invalid request method.');
}


/*
 * ------------------------------------------------------------
 * Read submitted values
 * ------------------------------------------------------------
 */

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your session token is invalid or expired. Refresh the page and try again.'
    ]);
    exit;
}

$publicName = trim($_POST['public_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');

$requestedRegionId = (int) ($_POST['requested_region_id'] ?? 0);
$requestedParentAuthorityId = (int) ($_POST['requested_parent_authority_id'] ?? 0);

$categoryIds = $_POST['category_ids'] ?? [];

$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';


/*
 * ------------------------------------------------------------
 * Basic validation
 * ------------------------------------------------------------
 */

if ($publicName === '') {
    sendError('Authority name is required.');
}

if ($phone === '') {
    sendError('Official phone number is required.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendError('A valid official email address is required.');
}

if ($requestedRegionId <= 0) {
    sendError('Please select an administrative region.');
}

if (!is_array($categoryIds) || count($categoryIds) === 0) {
    sendError('Please select at least one service category.');
}

if ($password === '') {
    sendError('Password is required.');
}

if ($password !== $confirmPassword) {
    sendError('Passwords do not match.');
}

if (strlen($password) < 8) {
    sendError('Password must be at least 8 characters long.');
}


/*
 * ------------------------------------------------------------
 * Normalise category IDs
 * ------------------------------------------------------------
 */

$categoryIds = array_map('intval', $categoryIds);
$categoryIds = array_values(array_unique($categoryIds));

if (count($categoryIds) === 0) {
    sendError('Please select at least one service category.');
}


/*
 * ------------------------------------------------------------
 * Database transaction
 * ------------------------------------------------------------
 */

try {

    $pdo->beginTransaction();


    /*
     * --------------------------------------------------------
     * Validate requested region
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        SELECT id
        FROM regions
        WHERE id = ?
          AND is_active = TRUE
        LIMIT 1
    ");

    $stmt->execute([$requestedRegionId]);

    if (!$stmt->fetch()) {
        throw new Exception('Selected administrative region is invalid.');
    }


    /*
     * --------------------------------------------------------
     * Validate requested categories
     * --------------------------------------------------------
     */

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));

    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id IN ($placeholders)
          AND is_active = TRUE
    ");

    $stmt->execute($categoryIds);

    $validCategoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $validCategoryIds = array_map('intval', $validCategoryIds);

    if (count($validCategoryIds) !== count($categoryIds)) {
        throw new Exception('One or more selected service categories are invalid.');
    }


    /*
     * --------------------------------------------------------
     * Validate proposed parent authority
     * --------------------------------------------------------
     */

    if ($requestedParentAuthorityId > 0) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM authorities
            WHERE id = ?
              AND is_active = TRUE
            LIMIT 1
        ");

        $stmt->execute([$requestedParentAuthorityId]);

        if (!$stmt->fetch()) {
            throw new Exception('Selected parent authority is invalid.');
        }
    }


    /*
     * --------------------------------------------------------
     * Check for existing pending request
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        SELECT id
        FROM authority_access_requests
        WHERE email = ?
          AND status = 'Pending'
        LIMIT 1
    ");

    $stmt->execute([$email]);

    if ($stmt->fetch()) {
        throw new Exception(
            'A pending authority access request already exists for this email.'
        );
    }


    /*
     * --------------------------------------------------------
     * Generate request ID
     * --------------------------------------------------------
     */

    $requestId = 'REQ-' . strtoupper(bin2hex(random_bytes(8)));


    /*
     * --------------------------------------------------------
     * Hash password
     * --------------------------------------------------------
     */

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);


    /*
     * --------------------------------------------------------
     * Insert access request
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        INSERT INTO authority_access_requests
        (
            request_id,
            public_name,
            phone,
            email,
            requested_region_id,
            requested_parent_authority_id,
            status,
            password_hash
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, 'Pending', ?
        )
    ");

    $stmt->execute([
        $requestId,
        $publicName,
        $phone,
        $email,
        $requestedRegionId,
        $requestedParentAuthorityId > 0
            ? $requestedParentAuthorityId
            : null,
        $passwordHash
    ]);

    $requestDatabaseId = $pdo->lastInsertId();


    /*
     * --------------------------------------------------------
     * Insert requested service categories
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        INSERT INTO authority_access_request_categories
        (
            request_id,
            category_id
        )
        VALUES
        (
            ?, ?
        )
    ");

    foreach ($validCategoryIds as $categoryId) {
        $stmt->execute([
            $requestDatabaseId,
            $categoryId
        ]);
    }


    /*
     * --------------------------------------------------------
     * Commit
     * --------------------------------------------------------
     */

    $pdo->commit();


    echo json_encode([
        'success' => true,
        'message' => 'Authority access request submitted successfully.',
        'request_id' => $requestId
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'InfraFix authority access request failed: ' .
        $e->getMessage()
    );

    sendError($e->getMessage());
}