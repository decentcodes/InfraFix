
<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/priority.php';

/*
 * Only authenticated citizens may request issue details.
 */
if (!isCitizenLoggedIn()) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Please log in to view issue details.'
    ]);

    exit;
}

/*
 * Read the public Issue ID.
 */
$issueId = trim($_GET['issue_id'] ?? '');

if ($issueId === '') {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Issue ID is required.'
    ]);

    exit;
}

/*
 * Fetch the issue, its authority, priority,
 * report count, first report and latest report.
 *
 * Reporter identities and reporter GPS coordinates
 * are deliberately excluded.
 */
$sql = "
    SELECT
        i.issue_id,
        i.status,
        i.address,
        i.latitude,
        i.longitude,
        i.created_at AS issue_created_at,
        i.updated_at AS issue_updated_at,

        c.name AS category_name,
        a.public_name AS authority_name,

        ips.priority_score,

        (
            SELECT COUNT(*)
            FROM reports r
            WHERE r.issue_id = i.id
        ) AS report_count,

        (
            SELECT r.description
            FROM reports r
            WHERE r.issue_id = i.id
            ORDER BY r.reported_at ASC, r.id ASC
            LIMIT 1
        ) AS first_description,

        (
            SELECT r.reported_at
            FROM reports r
            WHERE r.issue_id = i.id
            ORDER BY r.reported_at ASC, r.id ASC
            LIMIT 1
        ) AS first_reported_at,

        (
            SELECT r.reported_at
            FROM reports r
            WHERE r.issue_id = i.id
            ORDER BY r.reported_at DESC, r.id DESC
            LIMIT 1
        ) AS last_reported_at,

        (
            SELECT rp.file_path
            FROM reports r
            INNER JOIN report_photos rp
                ON rp.report_id = r.id
            WHERE r.issue_id = i.id
            ORDER BY r.reported_at ASC, r.id ASC,
                     rp.uploaded_at ASC, rp.id ASC
            LIMIT 1
        ) AS first_evidence_photo

    FROM issues i

    INNER JOIN categories c
        ON c.id = i.category_id

    LEFT JOIN authorities a
        ON a.id = i.authority_id

    LEFT JOIN issue_priority_scores ips
        ON ips.issue_id = i.id

    WHERE i.issue_id = :issue_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':issue_id' => $issueId
]);

$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Issue not found.'
    ]);

    exit;
}

/*
 * Convert the stored score to a citizen-facing
 * priority band. Do not return the numeric score.
 */
if ($issue['priority_score'] !== null) {
    $issue['priority_band'] = getPriorityBand(
        (float) $issue['priority_score']
    );
} else {
    $issue['priority_band'] = 'Unscored';
}

unset($issue['priority_score']);

$issue['report_count'] = (int) $issue['report_count'];

echo json_encode([
    'success' => true,
    'issue' => $issue
]);
