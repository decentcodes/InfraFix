<?php

/*
 * A citizen who reported an Issue can leave one rating (1-5) and an
 * optional comment on the Issue's latest resolution. Feedback is extra
 * evidence for the authority and the public page; it does not reopen
 * or close anything.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

function feedbackResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!isCitizenLoggedIn()) {
    feedbackResponse(401, ['success' => false, 'message' => 'Please log in first.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    feedbackResponse(405, ['success' => false, 'message' => 'POST is required.']);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    feedbackResponse(403, [
        'success' => false,
        'message' => 'Your session token is missing or expired. Refresh the page and try again.'
    ]);
}

$citizenId = (int) getLoggedInCitizenId();
$reportId = trim((string) ($_POST['report_id'] ?? ''));
$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$comment = trim((string) ($_POST['comment'] ?? ''));

if ($reportId === '' || strlen($reportId) > 40) {
    feedbackResponse(400, ['success' => false, 'message' => 'Invalid report.']);
}

if ($rating === false || $rating < 1 || $rating > 5) {
    feedbackResponse(422, ['success' => false, 'message' => 'Choose a rating from 1 to 5.']);
}

if (mb_strlen($comment) > 500) {
    feedbackResponse(422, ['success' => false, 'message' => 'Comment must be 500 characters or fewer.']);
}

try {
    // The citizen must own a report on this Issue.
    $stmt = $pdo->prepare("
        SELECT i.id AS issue_db_id, i.status
        FROM reports r
        INNER JOIN issues i ON i.id = r.issue_id
        WHERE r.report_id = ? AND r.citizen_id = ?
        LIMIT 1
    ");
    $stmt->execute([$reportId, $citizenId]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issue) {
        feedbackResponse(404, ['success' => false, 'message' => 'Report not found.']);
    }

    if (!in_array($issue['status'], ['Resolved', 'Closed'], true)) {
        feedbackResponse(409, [
            'success' => false,
            'message' => 'Feedback is possible only after the authority marks the issue resolved.'
        ]);
    }

    $stmt = $pdo->prepare("
        SELECT id FROM issue_resolutions
        WHERE issue_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([(int) $issue['issue_db_id']]);
    $resolutionDbId = $stmt->fetchColumn();

    if (!$resolutionDbId) {
        feedbackResponse(409, ['success' => false, 'message' => 'No resolution record exists yet.']);
    }

    $stmt = $pdo->prepare("
        SELECT 1 FROM resolution_feedback
        WHERE resolution_id = ? AND citizen_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int) $resolutionDbId, $citizenId]);

    if ($stmt->fetchColumn()) {
        feedbackResponse(409, ['success' => false, 'message' => 'You have already submitted feedback.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO resolution_feedback
            (feedback_id, resolution_id, citizen_id, rating, comment)
        VALUES (?, ?, ?, ?, ?)
    ");

    try {
        $stmt->execute([
            'FBK-' . strtoupper(bin2hex(random_bytes(8))),
            (int) $resolutionDbId,
            $citizenId,
            $rating,
            $comment === '' ? null : $comment
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // duplicate (unique key)
            feedbackResponse(409, ['success' => false, 'message' => 'You have already submitted feedback.']);
        }
        throw $e;
    }

    feedbackResponse(200, ['success' => true, 'message' => 'Thank you for your feedback.']);
} catch (Throwable $e) {
    error_log('InfraFix feedback failed: ' . $e->getMessage());
    feedbackResponse(500, ['success' => false, 'message' => 'Could not save feedback.']);
}
