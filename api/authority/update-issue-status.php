<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/priority.php';

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
 * Authentication
 */

if (!isAuthorityLoggedIn()) {
    sendError('Authentication required.');
}


/*
 * Request method
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


/*
 * Input
 */

$issueId = filter_input(
    INPUT_POST,
    'issue_id',
    FILTER_VALIDATE_INT
);

$newStatus = trim($_POST['new_status'] ?? '');
$remark = trim($_POST['remark'] ?? '');

$authorityDatabaseId = getLoggedInAuthorityId();


if (!$issueId) {
    sendError('Invalid Issue.');
}


$allowedStatuses = [
    'In Progress',
    'Rejected',
    'Resolved',
    'Closed'
];

if (!in_array($newStatus, $allowedStatuses, true)) {
    sendError('Invalid target status.');
}


/*
 * Validate remarks based on transition.
 */

if ($newStatus === 'Rejected' && $remark === '') {
    sendError('A rejection reason is required.');
}

if ($newStatus === 'Resolved' && $remark === '') {
    sendError('A resolution remark is required.');
}

if (strlen($remark) > 500) {
    sendError('Remark must not exceed 500 characters.');
}


try {

    $pdo->beginTransaction();


    /*
     * Fetch the Issue while locking the row.
     *
     * The authority ownership check is performed here,
     * inside the transaction.
     */

    $stmt = $pdo->prepare("
    SELECT
        i.id,
        i.issue_id,
        i.category_id,
        i.authority_id,
        i.status
    FROM issues i
    INNER JOIN authority_categories ac
        ON ac.authority_id = i.authority_id
       AND ac.category_id = i.category_id
       AND ac.is_active = TRUE
    INNER JOIN authority_regions ar
    ON ar.authority_id = i.authority_id
   AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
   AND ar.is_active = TRUE
    WHERE i.id = ?
      AND i.authority_id = ?
    LIMIT 1
    FOR UPDATE
");

    $stmt->execute([
        $issueId,
        $authorityDatabaseId
    ]);

    $issue = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$issue) {

        $pdo->rollBack();

        sendError(
            'Issue not found or it is not assigned to your authority.'
        );
    }


    /*
     * Validate the current → new status transition.
     */

    $validTransitions = [
        'Open' => [
            'In Progress',
            'Rejected'
        ],

        'In Progress' => [
            'Resolved'
        ],

        'Resolved' => [
            'Closed'
        ],

        'Closed' => [],

        'Rejected' => []
    ];


    $currentStatus = $issue['status'];


    if (
        !isset($validTransitions[$currentStatus]) ||
        !in_array(
            $newStatus,
            $validTransitions[$currentStatus],
            true
        )
    ) {

        $pdo->rollBack();

        sendError(
            "Invalid status transition: {$currentStatus} → {$newStatus}."
        );
    }


    /*
     * Update Issue status.
     */

    $stmt = $pdo->prepare("
        UPDATE issues
        SET
            status = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $stmt->execute([
        $newStatus,
        $issueId
    ]);


    /*
     * Record status history.
     */

    $stmt = $pdo->prepare("
        INSERT INTO issue_status_history (
            issue_id,
            issue_status_history_id,
            previous_status,
            new_status,
            changed_by_authority_id,
            remark,
            changed_at
        )
        VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            CURRENT_TIMESTAMP
        )
    ");


    /*
     * Generate a unique status-history ID.
     */

    $statusHistoryId = 'ISH-' . strtoupper(
        bin2hex(random_bytes(8))
    );


    $stmt->execute([
        $issueId,
        $statusHistoryId,
        $currentStatus,
        $newStatus,
        $authorityDatabaseId,
        $remark !== '' ? $remark : null
    ]);


    /*
     * If the Issue has been resolved,
     * create its resolution record.
     */

    if ($newStatus === 'Resolved') {

        $resolutionId = 'RES-' . strtoupper(
            bin2hex(random_bytes(8))
        );

        $stmt = $pdo->prepare("
            INSERT INTO issue_resolutions (
                resolution_id,
                issue_id,
                authority_id,
                remark,
                resolved_at,
                created_at
            )
            VALUES (
                ?,
                ?,
                ?,
                ?,
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            $resolutionId,
            $issueId,
            $authorityDatabaseId,
            $remark
        ]);
    }


    /*
     * Commit the status change before recalculating priority.
     */

    $pdo->commit();


    /*
     * Recalculate priority after the successful transaction.
     *
     * A failure here should not undo the status change.
     */

    $priorityUpdated = true;

    try {

        updateIssuePriority($issueId);
    } catch (Throwable $priorityException) {

        $priorityUpdated = false;

        error_log(
            'InfraFix priority update failed after status change for Issue ' .
                $issueId .
                ': ' .
                $priorityException->getMessage()
        );
    }


    echo json_encode([
        'success' => true,
        'priority_updated' => $priorityUpdated,
        'message' => $priorityUpdated
            ? 'Issue status updated successfully.'
            : 'Issue status was updated, but priority recalculation failed. Open this Issue and use Recalculate Priority to retry.',
        'new_status' => $newStatus
    ]);
} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'InfraFix status update failed: ' .
            $e->getMessage()
    );

    sendError(
        'Unable to update Issue status.'
    );
}
