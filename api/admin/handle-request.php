<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';

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
    sendError('Your session token is missing or expired. Refresh the page and try again.');
}

$requestDatabaseId = (int) ($_POST['request_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$rejectionReason = trim($_POST['rejection_reason'] ?? '');


if ($requestDatabaseId <= 0) {
    sendError('Invalid authority access request.');
}


if (!in_array($action, ['approve', 'reject'], true)) {
    sendError('Invalid administrative action.');
}


if ($action === 'reject' && $rejectionReason === '') {
    sendError('A rejection reason is required.');
}


/*
 * ------------------------------------------------------------
 * Begin transaction
 * ------------------------------------------------------------
 */

try {

    $pdo->beginTransaction();


    /*
     * --------------------------------------------------------
     * Fetch request
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        SELECT
            id,
            request_id,
            public_name,
            phone,
            email,
            requested_region_id,
            requested_parent_authority_id,
            password_hash,
            status
        FROM authority_access_requests
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([$requestDatabaseId]);

    $request = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$request) {
        throw new Exception(
            'Authority access request was not found.'
        );
    }


    /*
     * --------------------------------------------------------
     * Only Pending requests can be processed
     * --------------------------------------------------------
     */

    if ($request['status'] !== 'Pending') {
        throw new Exception(
            'Only pending authority access requests can be processed.'
        );
    }


    /*
     * --------------------------------------------------------
     * REJECT
     * --------------------------------------------------------
     */

    if ($action === 'reject') {

        $stmt = $pdo->prepare("
            UPDATE authority_access_requests
            SET
                status = 'Rejected',
                rejection_reason = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $rejectionReason,
            $requestDatabaseId
        ]);


        $pdo->commit();


        echo json_encode([
            'success' => true,
            'message' => 'Authority access request rejected.'
        ]);

        exit;
    }


    /*
     * --------------------------------------------------------
     * APPROVE
     * --------------------------------------------------------
     */


    /*
     * Check that the requested region still exists.
     */

    $stmt = $pdo->prepare("
        SELECT id
        FROM regions
        WHERE id = ?
          AND is_active = TRUE
        LIMIT 1
    ");

    $stmt->execute([
        $request['requested_region_id']
    ]);

    if (!$stmt->fetch()) {
        throw new Exception(
            'The requested administrative region is no longer active.'
        );
    }


    /*
     * Check the proposed parent authority.
     */

    if (!empty($request['requested_parent_authority_id'])) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM authorities
            WHERE id = ?
              AND is_active = TRUE
            LIMIT 1
        ");

        $stmt->execute([
            $request['requested_parent_authority_id']
        ]);

        if (!$stmt->fetch()) {
            throw new Exception(
                'The proposed parent authority is no longer active.'
            );
        }
    }


    /*
     * Fetch requested categories.
     */

    $stmt = $pdo->prepare("
        SELECT
            category_id
        FROM authority_access_request_categories
        WHERE request_id = ?
    ");

    $stmt->execute([
        $requestDatabaseId
    ]);

    $categoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);


    if (count($categoryIds) === 0) {
        throw new Exception(
            'The request has no service categories.'
        );
    }


    /*
     * Check that all requested categories are still active.
     */

    $placeholders = implode(
        ',',
        array_fill(0, count($categoryIds), '?')
    );

    $stmt = $pdo->prepare("
        SELECT id
        FROM categories
        WHERE id IN ($placeholders)
          AND is_active = TRUE
    ");

    $stmt->execute($categoryIds);

    $validCategoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);


    if (count($validCategoryIds) !== count($categoryIds)) {
        throw new Exception(
            'One or more requested service categories are no longer active.'
        );
    }


    /*
     * --------------------------------------------------------
     * Generate Authority ID
     * --------------------------------------------------------
     */

    do {

        $authorityCode =
            'AUTH-' .
            strtoupper(bin2hex(random_bytes(4)));

        $stmt = $pdo->prepare("
            SELECT id
            FROM authorities
            WHERE authority_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $authorityCode
        ]);

    } while ($stmt->fetch());


    /*
     * --------------------------------------------------------
     * Create authority
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        INSERT INTO authorities
        (
            authority_id,
            public_name,
            phone,
            email,
            password_hash,
            parent_authority_id,
            is_active
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, TRUE
        )
    ");

    $stmt->execute([
        $authorityCode,
        $request['public_name'],
        $request['phone'],
        $request['email'],
        $request['password_hash'],
        !empty($request['requested_parent_authority_id'])
            ? $request['requested_parent_authority_id']
            : null
    ]);

    $authorityDatabaseId = $pdo->lastInsertId();


    /*
     * --------------------------------------------------------
     * Create region mapping
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        INSERT INTO authority_regions
        (
            authority_id,
            region_id,
            is_active
        )
        VALUES
        (
            ?, ?, TRUE
        )
    ");

    $stmt->execute([
        $authorityDatabaseId,
        $request['requested_region_id']
    ]);


    /*
     * --------------------------------------------------------
     * Create category mappings
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        INSERT INTO authority_categories
        (
            authority_id,
            category_id,
            is_active
        )
        VALUES
        (
            ?, ?, TRUE
        )
    ");

    foreach ($validCategoryIds as $categoryId) {

        $stmt->execute([
            $authorityDatabaseId,
            $categoryId
        ]);
    }


    /*
     * --------------------------------------------------------
     * Mark request as approved
     * --------------------------------------------------------
     */

    $stmt = $pdo->prepare("
        UPDATE authority_access_requests
        SET
            status = 'Approved',
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([
        $requestDatabaseId
    ]);


    /*
     * --------------------------------------------------------
     * Commit everything
     * --------------------------------------------------------
     */

    $pdo->commit();


    echo json_encode([
        'success' => true,
        'message' => 'Authority access request approved successfully.',
        'authority_id' => $authorityCode
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'InfraFix admin request handling failed: ' .
        $e->getMessage()
    );

    sendError($e->getMessage());
}