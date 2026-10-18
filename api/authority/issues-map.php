<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/priority.php';

header('Content-Type: application/json');

if (!isAuthorityLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized.'
    ]);
    exit;
}

$authorityId = getLoggedInAuthorityId();

$status = $_GET['status'] ?? 'active';

$allowedStatuses = [
    'active',
    'all',
    'Open',
    'In Progress',
    'Resolved',
    'Closed',
    'Rejected'
];

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status filter.'
    ]);
    exit;
}

$sql = "
    SELECT
        i.id,
        i.issue_id,
        i.latitude,
        i.longitude,
        i.status,
        c.name AS category_name,
        ips.priority_score AS priority_score,
        COUNT(DISTINCT r.id) AS report_count,
        reg.name AS region_name
    FROM issues i

    INNER JOIN categories c
        ON c.id = i.category_id

    INNER JOIN authority_categories ac
        ON ac.authority_id = i.authority_id
        AND ac.category_id = i.category_id
        AND ac.is_active = TRUE

    INNER JOIN authority_regions ar
        ON ar.authority_id = i.authority_id
        AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
        AND ar.is_active = TRUE

    INNER JOIN regions reg
        ON reg.id = i.region_id

    LEFT JOIN issue_priority_scores ips
        ON ips.issue_id = i.id

    LEFT JOIN reports r
        ON r.issue_id = i.id

    WHERE i.authority_id = ?
";

$params = [$authorityId];

if ($status === 'active') {
    $sql .= " AND i.status IN ('Open', 'In Progress')";
} elseif ($status !== 'all') {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= "
    GROUP BY
        i.id,
        i.issue_id,
        i.latitude,
        i.longitude,
        i.status,
        c.name,
        ips.priority_score,
        reg.name

    ORDER BY
    ips.priority_score DESC,
    i.created_at ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($issues as &$issue) {
    $issue['id'] = (int) $issue['id'];
    $issue['latitude'] = (float) $issue['latitude'];
    $issue['longitude'] = (float) $issue['longitude'];
    $issue['priority_score'] = $issue['priority_score'] === null
        ? null
        : (float) $issue['priority_score'];
    $issue['report_count'] = (int) $issue['report_count'];

    $issue['priority_band'] = $issue['priority_score'] === null
        ? 'Unscored'
        : getPriorityBand($issue['priority_score']);
}

unset($issue);

/*
 * Fetch the active geographic regions assigned
 * to the logged-in authority.
 */
$regionStmt = $pdo->prepare("
    SELECT
        r.region_id,
        r.name,
        r.boundary_geojson AS boundary_geojson
    FROM authority_regions ar
    INNER JOIN regions r
        ON r.id = ar.region_id
    WHERE ar.authority_id = ?
      AND ar.is_active = TRUE
      AND r.is_active = TRUE
      AND r.boundary_geojson IS NOT NULL
");

$regionStmt->execute([$authorityId]);

$regions = $regionStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($regions as &$region) {
    $region['boundary'] = json_decode(
        $region['boundary_geojson'],
        true
    );

    unset($region['boundary_geojson']);
}

unset($region);

echo json_encode([
    'success' => true,
    'status_filter' => $status,
    'count' => count($issues),
    'issues' => $issues,
    'regions' => $regions
]);
