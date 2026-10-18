<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/priority.php';

header('Content-Type: application/json');

function priorityRetryResponse(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (!isAuthorityLoggedIn()) {
    priorityRetryResponse(401, [
        'success' => false,
        'message' => 'Authentication required.'
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    priorityRetryResponse(405, [
        'success' => false,
        'message' => 'POST is required.'
    ]);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    priorityRetryResponse(403, [
        'success' => false,
        'message' => 'Your session token is missing or expired. Refresh the page and try again.'
    ]);
}

$issueId = filter_input(INPUT_POST, 'issue_id', FILTER_VALIDATE_INT);
$authorityId = (int) getLoggedInAuthorityId();

if (!$issueId || $issueId < 1) {
    priorityRetryResponse(400, [
        'success' => false,
        'message' => 'Invalid Issue.'
    ]);
}

try {
    $scopeStmt = $pdo->prepare("
        SELECT i.id
        FROM issues i
        WHERE i.id = ?
          AND i.authority_id = ?
          AND EXISTS (
              SELECT 1
              FROM authority_categories ac
              WHERE ac.authority_id = i.authority_id
                AND ac.category_id = i.category_id
                AND ac.is_active = TRUE
          )
          AND EXISTS (
              SELECT 1
              FROM authority_regions ar
              WHERE ar.authority_id = i.authority_id
                AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
                AND ar.is_active = TRUE
          )
        LIMIT 1
    ");
    $scopeStmt->execute([$issueId, $authorityId]);

    if (!$scopeStmt->fetchColumn()) {
        priorityRetryResponse(404, [
            'success' => false,
            'message' => 'Issue not found or it is outside your active authority assignments.'
        ]);
    }

    $priority = updateIssuePriority((int) $issueId);

    priorityRetryResponse(200, [
        'success' => true,
        'message' => 'Priority recalculated successfully.',
        'priority_score' => $priority['priority_score']
    ]);
} catch (Throwable $e) {
    error_log(
        'InfraFix manual priority recalculation failed for Issue ' .
        $issueId . ': ' . $e->getMessage()
    );

    priorityRetryResponse(500, [
        'success' => false,
        'message' => 'Priority could not be recalculated. Check server logs or contact the system administrator.'
    ]);
}
