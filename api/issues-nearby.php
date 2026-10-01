<?php

header('Content-Type: application/json');

require_once '../includes/db.php';

function sendError($message, $statusCode = 400)
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}

/*
 * Read current user location.
 */
$latitude = filter_input(INPUT_GET, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_GET, 'longitude', FILTER_VALIDATE_FLOAT);

if ($latitude === false || $latitude === null) {
    sendError('Invalid latitude.');
}

if ($longitude === false || $longitude === null) {
    sendError('Invalid longitude.');
}

if ($latitude < -90 || $latitude > 90) {
    sendError('Latitude is out of range.');
}

if ($longitude < -180 || $longitude > 180) {
    sendError('Longitude is out of range.');
}

/*
 * Search radius for displaying existing issues.
 *
 * This is NOT the D-check.
 * D-check remains 20 metres and is performed during
 * report submission.
 */
$searchRadius = 500;

/*
 * Find active issues within the display radius.
 *
 * Open and In Progress issues are displayed.
 * Resolved/Closed/Rejected issues are not displayed
 * as currently active issues.
 */
$sql = "
    SELECT
        i.issue_id,
        c.name AS category,
        i.status,
        i.latitude,
        i.longitude,
        i.created_at,
        i.updated_at
    FROM issues i
    INNER JOIN categories c
        ON c.id = i.category_id
    WHERE i.status IN ('Open', 'In Progress')
      AND (
            6371000 * 2 * ASIN(
                SQRT(
                    POWER(
                        SIN(
                            RADIANS(i.latitude - :latitude) / 2
                        ),
                        2
                    )
                    +
                    COS(RADIANS(:latitude_2))
                    * COS(RADIANS(i.latitude))
                    * POWER(
                        SIN(
                            RADIANS(i.longitude - :longitude) / 2
                        ),
                        2
                    )
                )
            )
          ) <= :search_radius
    ORDER BY i.created_at DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':latitude' => $latitude,
    ':latitude_2' => $latitude,
    ':longitude' => $longitude,
    ':search_radius' => $searchRadius
]);

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'count' => count($issues),
    'issues' => $issues
]);