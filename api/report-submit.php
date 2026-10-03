<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/auth.php';


/*
 * Helper: send a JSON error response and stop execution.
 */
function sendError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}


/*
 * Generate a unique public ID.
 */
function generateId(string $prefix): string
{
    return $prefix . '-' . strtoupper(bin2hex(random_bytes(8)));
}


/*
 * Calculate distance between two coordinates in metres.
 */
function calculateDistance(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
): float {

    $earthRadius = 6371000;

    $latitudeDifference = deg2rad(
        $latitude2 - $latitude1
    );

    $longitudeDifference = deg2rad(
        $longitude2 - $longitude1
    );

    $latitude1Radians = deg2rad($latitude1);
    $latitude2Radians = deg2rad($latitude2);

    $a =
        sin($latitudeDifference / 2) ** 2 +
        cos($latitude1Radians) *
        cos($latitude2Radians) *
        sin($longitudeDifference / 2) ** 2;

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}


/*
 * Get submitted values.
 */
$category = $_POST['issue_category'] ?? '';
$description = $_POST['description'] ?? '';

$reporterLatitude = $_POST['reporter_latitude'] ?? '';
$reporterLongitude = $_POST['reporter_longitude'] ?? '';

$reportedLatitude = $_POST['reported_latitude'] ?? '';
$reportedLongitude = $_POST['reported_longitude'] ?? '';

$useExistingIssue = $_POST['use_existing_issue'] ?? null;


/*
 * Allowed issue categories.
 */
$allowedCategories = [
    'pothole',
    'open-manhole',
    'broken-streetlight',
    'garbage',
    'water-leakage',
    'exposed-wiring',
    'blocked-drainage'
];


/*
 * Validate category.
 */
if (!in_array($category, $allowedCategories, true)) {
    sendError('Invalid issue category.');
}


/*
 * Validate reporter location.
 */
if (
    $reporterLatitude === '' ||
    $reporterLongitude === ''
) {
    sendError('Current location is required.');
}


/*
 * Validate reported issue location.
 */
if (
    $reportedLatitude === '' ||
    $reportedLongitude === ''
) {
    sendError('Issue location is required.');
}


/*
 * Convert coordinates to numbers.
 */
$reporterLatitude = (float) $reporterLatitude;
$reporterLongitude = (float) $reporterLongitude;

$reportedLatitude = (float) $reportedLatitude;
$reportedLongitude = (float) $reportedLongitude;


/*
 * Validate coordinate ranges.
 */
foreach (
    [
        [$reporterLatitude, $reporterLongitude],
        [$reportedLatitude, $reportedLongitude]
    ] as [$latitude, $longitude]
) {

    if (
        $latitude < -90 ||
        $latitude > 90 ||
        $longitude < -180 ||
        $longitude > 180
    ) {
        sendError('Invalid location coordinates.');
    }
}


/*
 * Normalize and validate description.
 */
$description = str_replace(
    ["\r\n", "\r"],
    "\n",
    $description
);

if (mb_strlen($description) > 500) {
    sendError(
        'Description must not exceed 500 characters.'
    );
}


/*
 * Validate photograph upload.
 */
if (!isset($_FILES['issue_photo'])) {
    sendError('A photograph is required.');
}

$photo = $_FILES['issue_photo'];


/*
 * Check upload errors.
 */
if ($photo['error'] !== UPLOAD_ERR_OK) {
    sendError(
        'There was a problem uploading the photograph.'
    );
}


/*
 * Check photograph size.
 */
$maxPhotoSize = 5 * 1024 * 1024;

if ($photo['size'] > $maxPhotoSize) {
    sendError(
        'The photograph must be smaller than 5 MB.'
    );
}


/*
 * Verify that the uploaded file is actually an image.
 */
$imageInfo = getimagesize($photo['tmp_name']);

if ($imageInfo === false) {
    sendError(
        'The uploaded file is not a valid image.'
    );
}


/*
 * Check allowed image formats.
 */
$allowedImageTypes = [
    IMAGETYPE_JPEG,
    IMAGETYPE_PNG,
    IMAGETYPE_WEBP
];

if (!in_array($imageInfo[2], $allowedImageTypes, true)) {
    sendError(
        'Only JPEG, PNG, and WebP images are allowed.'
    );
}


/*
 * Calculate reporter-to-issue distance.
 *
 * U = 50 metres.
 */
$verificationDistance = calculateDistance(
    $reporterLatitude,
    $reporterLongitude,
    $reportedLatitude,
    $reportedLongitude
);

$maximumVerificationDistance = 50;

if ($verificationDistance > $maximumVerificationDistance) {
    sendError(
        'You must be within 50 metres of the reported issue location.'
    );
}


/*
 * Database connection.
 */
require_once __DIR__ . '/../includes/db.php';


/*
 * Resolve the category database record.
 */
$categoryQuery = "
    SELECT id, category_id, name, slug
    FROM categories
    WHERE slug = :slug
      AND is_active = 1
    LIMIT 1
";

$categoryStatement = $pdo->prepare($categoryQuery);

$categoryStatement->execute([
    ':slug' => $category
]);

$categoryRecord = $categoryStatement->fetch(
    PDO::FETCH_ASSOC
);

if ($categoryRecord === false) {
    sendError(
        'The selected issue category is not available.'
    );
}


/*
 * Find an active same-category Issue within D = 20 metres.
 *
 * Only Open and In Progress Issues can receive
 * another Report in V1.
 */
$nearbyIssueDistance = 20;

$nearbyIssueQuery = "
    SELECT
        i.id,
        i.issue_id,
        i.latitude,
        i.longitude,
        i.status
    FROM issues AS i
    WHERE i.category_id = :category_id
      AND i.status IN ('Open', 'In Progress')
      AND (
          6371000 * 2 * ASIN(
              SQRT(
                  POWER(
                      SIN(
                          RADIANS(i.latitude - :reported_latitude) / 2
                      ),
                      2
                  ) +
                  COS(
                      RADIANS(:reported_latitude_2)
                  ) *
                  COS(
                      RADIANS(i.latitude)
                  ) *
                  POWER(
                      SIN(
                          RADIANS(
                              i.longitude - :reported_longitude
                          ) / 2
                      ),
                      2
                  )
              )
          )
      ) <= :nearby_distance
    ORDER BY i.id ASC
    LIMIT 1
";

$nearbyIssueStatement = $pdo->prepare(
    $nearbyIssueQuery
);

$nearbyIssueStatement->execute([
    ':category_id' => $categoryRecord['id'],
    ':reported_latitude' => $reportedLatitude,
    ':reported_latitude_2' => $reportedLatitude,
    ':reported_longitude' => $reportedLongitude,
    ':nearby_distance' => $nearbyIssueDistance
]);

$nearbyIssue = $nearbyIssueStatement->fetch(
    PDO::FETCH_ASSOC
);


/*
 * If the citizen has not made a duplicate decision yet,
 * return the validation result.
 *
 * IMPORTANT:
 * No database records are created at this stage.
 */
if ($useExistingIssue === null) {

    echo json_encode([
        'success' => true,
        'message' => 'Report data passed server-side validation.',
        'report' => [
            'category' => $category,
            'description' => $description,

            'reporter_latitude' =>
            $reporterLatitude,

            'reporter_longitude' =>
            $reporterLongitude,

            'reported_latitude' =>
            $reportedLatitude,

            'reported_longitude' =>
            $reportedLongitude,

            'location_verification_distance_m' =>
            round(
                $verificationDistance,
                2
            ),

            'nearby_issue_found' =>
            $nearbyIssue !== false,

            'nearby_issue' =>
            $nearbyIssue !== false
                ? [
                    'issue_id' =>
                    $nearbyIssue['issue_id'],

                    'latitude' =>
                    (float) $nearbyIssue['latitude'],

                    'longitude' =>
                    (float) $nearbyIssue['longitude'],

                    'status' =>
                    $nearbyIssue['status']
                ]
                : null
        ]
    ]);

    exit;
}


/*
 * Validate the duplicate decision.
 */
if (
    $useExistingIssue !== '0' &&
    $useExistingIssue !== '1'
) {
    sendError(
        'Invalid existing-issue decision.'
    );
}


/*
 * Get the currently authenticated citizen.
 */
$loggedInCitizenId = getLoggedInCitizenId();

if ($loggedInCitizenId === null) {
    sendError(
        'You must be logged in to submit a report.',
        401
    );
}

$citizenQuery = "
    SELECT id, citizen_id
    FROM citizens
    WHERE id = :id
      AND is_active = 1
    LIMIT 1
";

$citizenStatement = $pdo->prepare(
    $citizenQuery
);

$citizenStatement->execute([
    ':id' => $loggedInCitizenId
]);

$citizen = $citizenStatement->fetch(
    PDO::FETCH_ASSOC
);

if ($citizen === false) {
    sendError(
        'Your citizen account could not be found.',
        401
    );
}


/*
 * Prevent the same citizen from creating another active
 * report for the same category at essentially the same
 * location.
 *
 * This is deliberately based on ACTIVE Issues only. Once
 * an Issue is Resolved or Closed, the citizen may report a
 * genuinely new occurrence at the same location.
 */
$citizenDuplicateQuery = "
    SELECT
        r.report_id,
        i.issue_id,
        i.status
    FROM reports AS r
    INNER JOIN issues AS i
        ON i.id = r.issue_id
    WHERE r.citizen_id = :citizen_id
      AND r.category_id = :category_id
      AND i.status IN ('Open', 'In Progress')
      AND (
          6371000 * 2 * ASIN(
              SQRT(
                  POWER(
                      SIN(
                          RADIANS(r.reported_latitude - :reported_latitude) / 2
                      ),
                      2
                  ) +
                  COS(RADIANS(:reported_latitude_2)) *
                  COS(RADIANS(r.reported_latitude)) *
                  POWER(
                      SIN(
                          RADIANS(
                              r.reported_longitude - :reported_longitude
                          ) / 2
                      ),
                      2
                  )
              )
          ) <= :duplicate_distance
      )
    ORDER BY r.reported_at DESC
    LIMIT 1
";

$citizenDuplicateStatement = $pdo->prepare(
    $citizenDuplicateQuery
);

$citizenDuplicateStatement->execute([
    ':citizen_id' => $citizen['id'],
    ':category_id' => $categoryRecord['id'],
    ':reported_latitude' => $reportedLatitude,
    ':reported_latitude_2' => $reportedLatitude,
    ':reported_longitude' => $reportedLongitude,
    ':duplicate_distance' => $nearbyIssueDistance
]);

$citizenDuplicate = $citizenDuplicateStatement->fetch(
    PDO::FETCH_ASSOC
);

if ($citizenDuplicate !== false) {
    sendError(
        'You have already reported this issue. You can track it from My Reports.'
    );
}


/*
 * Determine which Issue will receive the Report.
 */
$issue = null;


/*
 * Citizen chose to link this Report
 * to the existing Issue.
 */
if ($useExistingIssue === '1') {

    /*
     * Re-run the D-check.
     *
     * We do NOT trust the Issue returned during
     * the first request because the database may
     * have changed between requests.
     */
    $recheckStatement = $pdo->prepare(
        $nearbyIssueQuery
    );

    $recheckStatement->execute([
        ':category_id' =>
        $categoryRecord['id'],

        ':reported_latitude' =>
        $reportedLatitude,

        ':reported_latitude_2' =>
        $reportedLatitude,

        ':reported_longitude' =>
        $reportedLongitude,

        ':nearby_distance' =>
        $nearbyIssueDistance
    ]);

    $issue = $recheckStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if ($issue === false) {
        sendError(
            'The nearby issue is no longer available. Please submit the report again.'
        );
    }
}


/*
 * Determine the responsible authority for a NEW Issue.
 *
 * Routing is based on:
 * 1. Issue location being inside an active region.
 * 2. Finding an authority covering that region.
 * 3. Checking whether that authority handles the category.
 * 4. If not, following the authority's parent hierarchy.
 */
if ($issue === null) {

    /*
     * If the first validation request found no nearby Issue,
     * check once more before creating a NEW Issue. This closes
     * the small gap where another submission could create an
     * Issue between the two requests.
     */
    if ($nearbyIssue === false) {
        $finalNearbyStatement = $pdo->prepare(
            $nearbyIssueQuery
        );

        $finalNearbyStatement->execute([
            ':category_id' => $categoryRecord['id'],
            ':reported_latitude' => $reportedLatitude,
            ':reported_latitude_2' => $reportedLatitude,
            ':reported_longitude' => $reportedLongitude,
            ':nearby_distance' => $nearbyIssueDistance
        ]);

        $finalNearbyIssue = $finalNearbyStatement->fetch(
            PDO::FETCH_ASSOC
        );

        if ($finalNearbyIssue !== false) {
            sendError(
                'A similar issue was reported nearby while your report was being submitted. Please submit again and choose whether it is the same issue.'
            );
        }
    }

    /*
     * First, find the active region containing
     * the reported issue location.
     */
    $regionQuery = "
        SELECT
            id AS region_db_id,
            region_id
        FROM regions
        WHERE is_active = 1
          AND boundary IS NOT NULL
          AND ST_Contains(
              boundary,
              ST_SRID(
                  ST_GeomFromText(
                      CONCAT(
                          'POINT(',
                          :reported_longitude,
                          ' ',
                          :reported_latitude,
                          ')'
                      )
                  ),
                  4326
              )
          )
        LIMIT 1
    ";

    $regionStatement = $pdo->prepare(
        $regionQuery
    );

    $regionStatement->execute([
        ':reported_longitude' =>
        $reportedLongitude,

        ':reported_latitude' =>
        $reportedLatitude
    ]);

    $region = $regionStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if ($region === false) {
        sendError(
            'The reported issue location is not inside an active service region.'
        );
    }


    /*
     * Find an active authority covering this region.
     *
     * We use the authority with the category
     * responsibility first, if one exists.
     */
    $authorityQuery = "
        SELECT
            a.id AS authority_db_id,
            a.authority_id,
            a.parent_authority_id
        FROM authorities AS a
        INNER JOIN authority_regions AS ar
            ON ar.authority_id = a.id
           AND ar.region_id = :region_db_id
           AND ar.is_active = 1
        WHERE a.is_active = 1
        ORDER BY a.id
        LIMIT 1
    ";

    $authorityStatement = $pdo->prepare(
        $authorityQuery
    );

    $authorityStatement->execute([
        ':region_db_id' =>
        $region['region_db_id']
    ]);

    $authority = $authorityStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if ($authority === false) {
        sendError(
            'No active authority is assigned to this service region.'
        );
    }


    /*
     * Follow the authority hierarchy until we find
     * an authority responsible for this category.
     */
    $currentAuthority = $authority;

    while ($currentAuthority !== false) {

        $categoryQuery = "
            SELECT
                a.id AS authority_db_id,
                a.authority_id,
                a.parent_authority_id
            FROM authorities AS a
            INNER JOIN authority_regions AS ar
                ON ar.authority_id = a.id
               AND ar.region_id = :region_db_id
               AND ar.is_active = 1

            INNER JOIN authority_categories AS ac
                ON ac.authority_id = a.id
               AND ac.category_id = :category_id
               AND ac.is_active = 1

            WHERE a.id = :authority_db_id
              AND a.is_active = 1
            LIMIT 1
        ";

        $categoryStatement = $pdo->prepare(
            $categoryQuery
        );

        $categoryStatement->execute([
            ':region_db_id' =>
            $region['region_db_id'],

            ':category_id' =>
            $categoryRecord['id'],

            ':authority_db_id' =>
            $currentAuthority['authority_db_id']
        ]);

        $responsibleAuthority =
            $categoryStatement->fetch(
                PDO::FETCH_ASSOC
            );

        if ($responsibleAuthority !== false) {

            $routing = [
                'region_db_id' =>
                $region['region_db_id'],

                'region_id' =>
                $region['region_id'],

                'authority_db_id' =>
                $responsibleAuthority['authority_db_id'],

                'authority_id' =>
                $responsibleAuthority['authority_id']
            ];

            break;
        }


        /*
         * The current authority does not handle
         * this category. Move to its parent.
         */
        if (
            empty($currentAuthority['parent_authority_id'])
        ) {
            $currentAuthority = false;
            break;
        }


        /*
         * Load the parent authority.
         */
        $parentQuery = "
            SELECT
                id AS authority_db_id,
                authority_id,
                parent_authority_id
            FROM authorities
            WHERE id = :parent_authority_id
              AND is_active = 1
            LIMIT 1
        ";

        $parentStatement = $pdo->prepare(
            $parentQuery
        );

        $parentStatement->execute([
            ':parent_authority_id' =>
            $currentAuthority['parent_authority_id']
        ]);

        $currentAuthority =
            $parentStatement->fetch(
                PDO::FETCH_ASSOC
            );
    }


    /*
     * No authority in the hierarchy handles
     * the selected category.
     */
    if (!isset($routing)) {
        sendError(
            'No authority is currently responsible for this location and issue category.'
        );
    }
}

/*
 * Begin database transaction.
 */
$pdo->beginTransaction();

$storedPhotoPath = null;

try {

    /*
     * -------------------------------------------------
     * Existing Issue
     * -------------------------------------------------
     */
    if ($issue !== null) {

        $issueDatabaseId = $issue['id'];
        $issuePublicId = $issue['issue_id'];
    }

    /*
     * -------------------------------------------------
     * New Issue
     * -------------------------------------------------
     */ else {

        $issueDatabaseId = null;
        $issuePublicId = generateId('ISS');

        $createIssueQuery = "
            INSERT INTO issues (
                issue_id,
                category_id,
                region_id,
                authority_id,
                latitude,
                longitude,
                status
            )
            VALUES (
                :issue_id,
                :category_id,
                :region_id,
                :authority_id,
                :latitude,
                :longitude,
                'Open'
            )
        ";

        $createIssueStatement = $pdo->prepare(
            $createIssueQuery
        );

        $createIssueStatement->execute([
            ':issue_id' =>
            $issuePublicId,

            ':category_id' =>
            $categoryRecord['id'],

            ':region_id' =>
            $routing['region_db_id'],

            ':authority_id' =>
            $routing['authority_db_id'],

            ':latitude' =>
            $reportedLatitude,

            ':longitude' =>
            $reportedLongitude
        ]);

        $issueDatabaseId = (int) $pdo->lastInsertId();
    }


    /*
     * Create the Report.
     */
    $reportPublicId = generateId('RPT');

    $createReportQuery = "
        INSERT INTO reports (
            report_id,
            issue_id,
            citizen_id,
            category_id,
            description,
            reported_latitude,
            reported_longitude,
            reporter_latitude,
            reporter_longitude,
            location_verified,
            location_verification_distance_m,
            reported_at
        )
        VALUES (
            :report_id,
            :issue_id,
            :citizen_id,
            :category_id,
            :description,
            :reported_latitude,
            :reported_longitude,
            :reporter_latitude,
            :reporter_longitude,
            1,
            :verification_distance,
            NOW()
        )
    ";

    $createReportStatement = $pdo->prepare(
        $createReportQuery
    );

    $createReportStatement->execute([
        ':report_id' =>
        $reportPublicId,

        ':issue_id' =>
        $issueDatabaseId,

        ':citizen_id' =>
        $citizen['id'],

        ':category_id' =>
        $categoryRecord['id'],

        ':description' =>
        $description,

        ':reported_latitude' =>
        $reportedLatitude,

        ':reported_longitude' =>
        $reportedLongitude,

        ':reporter_latitude' =>
        $reporterLatitude,

        ':reporter_longitude' =>
        $reporterLongitude,

        ':verification_distance' =>
        round(
            $verificationDistance,
            2
        )
    ]);

    $reportDatabaseId = (int) $pdo->lastInsertId();


    /*
     * Save the photograph.
     *
     * The directory is outside the database;
     * the database stores its relative path.
     */
    $uploadDirectory =
        __DIR__ .
        '/../uploads/reports/';

    if (!is_dir($uploadDirectory)) {

        if (!mkdir(
            $uploadDirectory,
            0755,
            true
        )) {
            throw new RuntimeException(
                'Could not create the report upload directory.'
            );
        }
    }


    /*
     * Determine the file extension from the
     * validated image type.
     */
    $extensionMap = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp'
    ];

    $extension = $extensionMap[$imageInfo[2]];

    $storedFilename =
        $reportPublicId .
        '-' .
        bin2hex(random_bytes(6)) .
        '.' .
        $extension;

    $storedPhotoPath =
        $uploadDirectory .
        $storedFilename;


    /*
     * Move uploaded photograph into permanent storage.
     */
    if (!move_uploaded_file(
        $photo['tmp_name'],
        $storedPhotoPath
    )) {
        throw new RuntimeException(
            'Could not store the photograph.'
        );
    }


    /*
     * Store photograph metadata.
     */
    $photoPublicId = generateId('RPH');

    $relativePhotoPath =
        'uploads/reports/' .
        $storedFilename;

    $createPhotoQuery = "
        INSERT INTO report_photos (
            report_photo_id,
            report_id,
            file_path,
            original_filename,
            mime_type,
            file_size,
            uploaded_at
        )
        VALUES (
            :report_photo_id,
            :report_id,
            :file_path,
            :original_filename,
            :mime_type,
            :file_size,
            NOW()
        )
    ";

    $createPhotoStatement = $pdo->prepare(
        $createPhotoQuery
    );

    $createPhotoStatement->execute([
        ':report_photo_id' =>
        $photoPublicId,

        ':report_id' =>
        $reportDatabaseId,

        ':file_path' =>
        $relativePhotoPath,

        ':original_filename' =>
        $photo['name'],

        ':mime_type' =>
        $imageInfo['mime'],

        ':file_size' =>
        $photo['size']
    ]);


    /*
     * Create initial Open status history
     * only when a NEW Issue was created.
     */
    if ($issue === null) {

        $statusHistoryPublicId =
            generateId('ISH');

        $createHistoryQuery = "
            INSERT INTO issue_status_history (
                issue_status_history_id,
                issue_id,
                previous_status,
                new_status,
                changed_by_authority_id,
                remark,
                changed_at
            )
            VALUES (
                :history_id,
                :issue_id,
                NULL,
                'Open',
                NULL,
                :remark,
                NOW()
            )
        ";

        $createHistoryStatement = $pdo->prepare(
            $createHistoryQuery
        );

        $createHistoryStatement->execute([
            ':history_id' =>
            $statusHistoryPublicId,

            ':issue_id' =>
            $issueDatabaseId,

            ':remark' =>
            'Issue registered through citizen report.'
        ]);
    }


    /*
     * Update Issue timestamp when an existing
     * Issue receives another Report.
     */
    if ($issue !== null) {

        $updateIssueQuery = "
            UPDATE issues
            SET updated_at = NOW()
            WHERE id = :issue_id
        ";

        $updateIssueStatement = $pdo->prepare(
            $updateIssueQuery
        );

        $updateIssueStatement->execute([
            ':issue_id' =>
            $issueDatabaseId
        ]);
    }


    /*
     * Everything succeeded.
     */
    $pdo->commit();


    echo json_encode([
        'success' => true,

        'message' =>
        $issue !== null
            ? 'Your report has been added to the existing issue.'
            : 'Your issue has been reported successfully.',

        'report' => [
            'report_id' =>
            $reportPublicId,

            'issue_id' =>
            $issuePublicId,

            'linked_to_existing_issue' =>
            $issue !== null,

            'category' =>
            $category,

            'location_verification_distance_m' =>
            round(
                $verificationDistance,
                2
            )
        ]
    ]);
} catch (Throwable $exception) {

    /*
     * Roll back database changes.
     */
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
     * A database transaction cannot undo a file move,
     * so remove the stored photograph if necessary.
     */
    if (
        $storedPhotoPath !== null &&
        file_exists($storedPhotoPath)
    ) {
        unlink($storedPhotoPath);
    }


    /*
     * Log the actual technical error.
     * Do not expose it to the citizen.
     */
    error_log(
        'InfraFix report creation failed: ' .
            $exception->getMessage()
    );


    sendError(
        'The report could not be saved. Please try again later.',
        500
    );
}
