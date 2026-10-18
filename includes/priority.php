<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/weather.php';


/**
 * Calculate the priority of an Issue.
 *
 * @param int $issueId Internal database ID of the Issue.
 * @return array
 */

/**
 * Calculate the distance between two coordinates in metres.
 */
function calculateDistanceMeters(
    float $latitude1,
    float $longitude1,
    float $latitude2,
    float $longitude2
): float {
    $earthRadius = 6371000;

    $lat1 = deg2rad($latitude1);
    $lat2 = deg2rad($latitude2);

    $deltaLat = deg2rad($latitude2 - $latitude1);
    $deltaLon = deg2rad($longitude2 - $longitude1);

    $a =
        sin($deltaLat / 2) * sin($deltaLat / 2)
        + cos($lat1)
        * cos($lat2)
        * sin($deltaLon / 2)
        * sin($deltaLon / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $earthRadius * $c;
}

/**
 * Get the priority band for a final priority score.
 *
 * @param float $score Priority score from 0 to 100.
 * @return string
 */
function getPriorityBand(float $score): string
{
    if ($score >= 75) {
        return 'Critical';
    }

    if ($score >= 50) {
        return 'High';
    }

    if ($score >= 25) {
        return 'Moderate';
    }

    return 'Low';
}

function calculateIssuePriority(int $issueId): array
{
    global $pdo;

    // --------------------------------------------------
    // Get Issue and category
    // --------------------------------------------------

    $sql = "
    SELECT
        i.id,
        i.issue_id,
        i.category_id,
        i.latitude,
        i.longitude,
        i.created_at,
        c.slug AS category_slug
    FROM issues i
    INNER JOIN categories c
        ON c.id = i.category_id
    WHERE i.id = ?
    LIMIT 1
";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$issueId]);

    $issue = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$issue) {
        throw new Exception(
            "Issue not found."
        );
    }

    // --------------------------------------------------
    // Get Safety baseline
    // --------------------------------------------------

    $safetySql = "
        SELECT
            cpb.base_level,
            pf.max_level,
            pf.max_contribution
        FROM category_priority_baselines cpb
        INNER JOIN priority_factors pf
            ON pf.id = cpb.factor_id
        WHERE cpb.category_id = ?
          AND pf.slug = 'safety'
          AND pf.is_active = TRUE
        LIMIT 1
    ";

    $safetyStmt = $pdo->prepare($safetySql);
    $safetyStmt->execute([$issue['category_id']]);

    $safety = $safetyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$safety) {
        throw new Exception(
            "Safety configuration not found for this Issue category."
        );
    }

    // --------------------------------------------------
    // Calculate Safety contribution
    // --------------------------------------------------

    $safetyLevel = (float) $safety['base_level'];
    $safetyMaxLevel = (float) $safety['max_level'];
    $safetyMaxContribution = (float) $safety['max_contribution'];

    $safetyContribution =
        ($safetyLevel / $safetyMaxLevel)
        * $safetyMaxContribution;


    // --------------------------------------------------
    // Get Severity baseline
    // --------------------------------------------------

    $severitySql = "
        SELECT
            cpb.base_level,
            pf.max_level,
            pf.max_contribution
        FROM category_priority_baselines cpb
        INNER JOIN priority_factors pf
            ON pf.id = cpb.factor_id
        WHERE cpb.category_id = ?
          AND pf.slug = 'severity'
          AND pf.is_active = TRUE
        LIMIT 1
    ";

    $severityStmt = $pdo->prepare($severitySql);
    $severityStmt->execute([$issue['category_id']]);

    $severity = $severityStmt->fetch(PDO::FETCH_ASSOC);

    if (!$severity) {
        throw new Exception(
            "Severity configuration not found for this Issue category."
        );
    }

    // --------------------------------------------------
    // Calculate Severity contribution
    // --------------------------------------------------

    $severityLevel = (float) $severity['base_level'];
    $severityMaxLevel = (float) $severity['max_level'];
    $severityMaxContribution = (float) $severity['max_contribution'];

    $severityContribution =
        ($severityLevel / $severityMaxLevel)
        * $severityMaxContribution;

    // --------------------------------------------------
    // Get nearby POIs
    // --------------------------------------------------

    $poiSql = "
        SELECT
            p.id,
            p.name,
            p.latitude,
            p.longitude,
            pt.base_criticality
        FROM points_of_interest p
        INNER JOIN poi_types pt
            ON pt.id = p.poi_type_id
        WHERE p.is_active = TRUE
          AND pt.is_active = TRUE
          AND p.latitude BETWEEN :min_lat AND :max_lat
          AND p.longitude BETWEEN :min_lng AND :max_lng
    ";

    // Only POIs within ~600 m can matter (influence is zero beyond 300 m),
    // so skip the rest instead of scoring every POI in the city.
    $poiBoxDegrees = 0.006;

    $poiStmt = $pdo->prepare($poiSql);
    $poiStmt->execute([
        ':min_lat' => (float) $issue['latitude'] - $poiBoxDegrees,
        ':max_lat' => (float) $issue['latitude'] + $poiBoxDegrees,
        ':min_lng' => (float) $issue['longitude'] - $poiBoxDegrees,
        ':max_lng' => (float) $issue['longitude'] + $poiBoxDegrees
    ]);

    $pois = $poiStmt->fetchAll(PDO::FETCH_ASSOC);

    // --------------------------------------------------
    // Calculate Location Criticality
    // --------------------------------------------------

    $locationLevel = 0.0;

    foreach ($pois as $poi) {

        $distance = calculateDistanceMeters(
            (float) $issue['latitude'],
            (float) $issue['longitude'],
            (float) $poi['latitude'],
            (float) $poi['longitude']
        );

        // Find the configured distance factor.
        $distanceSql = "
            SELECT
                distance_factor
            FROM poi_distance_rules
            WHERE ? >= min_distance_m
              AND (
                    max_distance_m IS NULL
                    OR ? <= max_distance_m
                  )
            LIMIT 1
        ";

        $distanceStmt = $pdo->prepare($distanceSql);
        $distanceStmt->execute([
            $distance,
            $distance
        ]);

        $distanceRule = $distanceStmt->fetch(PDO::FETCH_ASSOC);

        if (!$distanceRule) {
            continue;
        }

        $distanceFactor = (float) $distanceRule['distance_factor'];

        $poiInfluence =
            (float) $poi['base_criticality']
            * $distanceFactor;

        // Use the maximum influence, not the sum.
        if ($poiInfluence > $locationLevel) {
            $locationLevel = $poiInfluence;
        }
    }

    // --------------------------------------------------
    // Get Location Criticality configuration
    // --------------------------------------------------

    $locationSql = "
        SELECT
            max_level,
            max_contribution
        FROM priority_factors
        WHERE slug = 'location-criticality'
          AND is_active = TRUE
        LIMIT 1
    ";

    $locationStmt = $pdo->prepare($locationSql);
    $locationStmt->execute();

    $location = $locationStmt->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        throw new Exception(
            "Location Criticality configuration not found."
        );
    }

    // --------------------------------------------------
    // Calculate Location contribution
    // --------------------------------------------------

    $locationMaxLevel = (float) $location['max_level'];
    $locationMaxContribution = (float) $location['max_contribution'];

    $locationContribution =
        ($locationLevel / $locationMaxLevel)
        * $locationMaxContribution;

    // --------------------------------------------------
    // Get Weather/Context configuration
    // --------------------------------------------------

    $weatherFactorSql = "
        SELECT
            max_level,
            max_contribution
        FROM priority_factors
        WHERE slug = 'weather-context'
          AND is_active = TRUE
        LIMIT 1
    ";

    $weatherFactorStmt = $pdo->prepare($weatherFactorSql);
    $weatherFactorStmt->execute();

    $weatherFactor = $weatherFactorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$weatherFactor) {
        throw new Exception(
            "Weather/Context configuration not found."
        );
    }

    $weatherMaxLevel = (float) $weatherFactor['max_level'];
    $weatherMaxContribution =
        (float) $weatherFactor['max_contribution'];

    // --------------------------------------------------
    // Get Weather/Context level
    // --------------------------------------------------

    $weatherCondition = getEffectiveWeatherCondition($pdo);

    $weatherSql = "
        SELECT
            context_level
        FROM priority_weather_rules
        WHERE category_id = ?
          AND weather_condition = ?
          AND is_active = TRUE
        LIMIT 1
    ";

    $weather = ['context_level' => 0];

    if ($weatherCondition !== 'None') {
        $weatherStmt = $pdo->prepare($weatherSql);
        $weatherStmt->execute([
            $issue['category_id'],
            $weatherCondition
        ]);

        $weather = $weatherStmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$weather) {
        throw new Exception(
            "Weather/Context rule not found for this Issue category."
        );
    }

    $weatherLevel = (float) $weather['context_level'];

    // --------------------------------------------------
    // Calculate Weather/Context contribution
    // --------------------------------------------------

    $weatherContribution =
        ($weatherLevel / $weatherMaxLevel)
        * $weatherMaxContribution;

    // --------------------------------------------------
    // Get Report Volume
    // --------------------------------------------------

    $reportCountSql = "
        SELECT COUNT(*) AS report_count
        FROM reports
        WHERE issue_id = ?
    ";

    $reportCountStmt = $pdo->prepare($reportCountSql);
    $reportCountStmt->execute([$issueId]);

    $reportData = $reportCountStmt->fetch(PDO::FETCH_ASSOC);

    $reportCount = (int) $reportData['report_count'];

    // --------------------------------------------------
    // Get Report Volume configuration
    // --------------------------------------------------

    $reportFactorSql = "
        SELECT
            max_level,
            max_contribution
        FROM priority_factors
        WHERE slug = 'report-volume'
          AND is_active = TRUE
        LIMIT 1
    ";

    $reportFactorStmt = $pdo->prepare($reportFactorSql);
    $reportFactorStmt->execute();

    $reportFactor = $reportFactorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reportFactor) {
        throw new Exception(
            "Report Volume configuration not found."
        );
    }

    $reportMaxLevel = (float) $reportFactor['max_level'];
    $reportMaxContribution =
        (float) $reportFactor['max_contribution'];

    // --------------------------------------------------
    // Get Report Volume level
    // --------------------------------------------------

    $reportLevelSql = "
        SELECT
            level
        FROM priority_report_levels
        WHERE ? >= min_reports
          AND (
                max_reports IS NULL
                OR ? <= max_reports
              )
        LIMIT 1
    ";

    $reportLevelStmt = $pdo->prepare($reportLevelSql);
    $reportLevelStmt->execute([
        $reportCount,
        $reportCount
    ]);

    $reportLevelData =
        $reportLevelStmt->fetch(PDO::FETCH_ASSOC);

    if (!$reportLevelData) {
        throw new Exception(
            "Report Volume level not found for this report count."
        );
    }

    $reportLevel = (float) $reportLevelData['level'];

    // --------------------------------------------------
    // Calculate Report Volume contribution
    // --------------------------------------------------

    $reportContribution =
        ($reportLevel / $reportMaxLevel)
        * $reportMaxContribution;

    // --------------------------------------------------
    // Calculate Issue Duration
    // --------------------------------------------------

    $createdAt = new DateTime($issue['created_at']);
    $now = new DateTime();

    $durationDays = $createdAt->diff($now)->days;

    // --------------------------------------------------
    // Get Duration configuration
    // --------------------------------------------------

    $durationFactorSql = "
    SELECT
        max_level,
        max_contribution
    FROM priority_factors
    WHERE slug = 'duration'
      AND is_active = TRUE
    LIMIT 1
";

    $durationFactorStmt = $pdo->prepare($durationFactorSql);
    $durationFactorStmt->execute();

    $durationFactor = $durationFactorStmt->fetch(PDO::FETCH_ASSOC);

    if (!$durationFactor) {
        throw new Exception(
            "Duration configuration not found."
        );
    }

    $durationMaxLevel = (float) $durationFactor['max_level'];
    $durationMaxContribution =
        (float) $durationFactor['max_contribution'];

    // --------------------------------------------------
    // Get Duration level
    // --------------------------------------------------

    $durationLevelSql = "
    SELECT
        level
    FROM priority_duration_levels
    WHERE ? >= min_days
      AND (
            max_days IS NULL
            OR ? <= max_days
          )
    LIMIT 1
";

    $durationLevelStmt = $pdo->prepare($durationLevelSql);
    $durationLevelStmt->execute([
        $durationDays,
        $durationDays
    ]);

    $durationLevelData =
        $durationLevelStmt->fetch(PDO::FETCH_ASSOC);

    if (!$durationLevelData) {
        throw new Exception(
            "Duration level not found for this Issue duration."
        );
    }

    $durationLevel = (float) $durationLevelData['level'];

    // --------------------------------------------------
    // Calculate Duration contribution
    // --------------------------------------------------

    $durationContribution =
        ($durationLevel / $durationMaxLevel)
        * $durationMaxContribution;

    // --------------------------------------------------
    // Calculate Final Priority Score
    // --------------------------------------------------

    $priorityScore =
        $safetyContribution
        + $locationContribution
        + $severityContribution
        + $weatherContribution
        + $reportContribution
        + $durationContribution;

    // --------------------------------------------------
    // Return current priority information
    // --------------------------------------------------

    return [
        'issue_id' => (int) $issue['id'],
        'public_issue_id' => $issue['issue_id'],
        'category_id' => (int) $issue['category_id'],
        'category_slug' => $issue['category_slug'],

        'safety' => [
            'level' => $safetyLevel,
            'max_level' => $safetyMaxLevel,
            'contribution' => $safetyContribution
        ],

        'severity' => [
            'level' => $severityLevel,
            'max_level' => $severityMaxLevel,
            'contribution' => $severityContribution
        ],

        'location' => [
            'level' => $locationLevel,
            'max_level' => $locationMaxLevel,
            'contribution' => $locationContribution
        ],

        'weather' => [
            'condition' => $weatherCondition,
            'level' => $weatherLevel,
            'max_level' => $weatherMaxLevel,
            'contribution' => $weatherContribution
        ],

        'report_volume' => [
            'report_count' => $reportCount,
            'level' => $reportLevel,
            'max_level' => $reportMaxLevel,
            'contribution' => $reportContribution
        ],

        'duration' => [
            'duration_days' => $durationDays,
            'level' => $durationLevel,
            'max_level' => $durationMaxLevel,
            'contribution' => $durationContribution
        ],

        'priority_score' => $priorityScore
    ];
}

/**
 * Calculate and save the current priority of an Issue.
 *
 * @param int $issueId Internal database ID of the Issue.
 * @return array
 */
function updateIssuePriority(int $issueId): array
{
    global $pdo;

    $priority = calculateIssuePriority($issueId);

    // --------------------------------------------------
    // Save current priority score
    // --------------------------------------------------

    $scoreId = 'IPS-' . strtoupper(bin2hex(random_bytes(8)));

    $scoreSql = "
        INSERT INTO issue_priority_scores (
            issue_priority_score_id,
            issue_id,
            priority_score
        )
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            priority_score = VALUES(priority_score),
            updated_at = CURRENT_TIMESTAMP
    ";

    $scoreStmt = $pdo->prepare($scoreSql);

    $scoreStmt->execute([
        $scoreId,
        $issueId,
        $priority['priority_score']
    ]);

    // Get the internal numeric ID of the saved score.
    $scoreIdSql = "
        SELECT id
        FROM issue_priority_scores
        WHERE issue_id = ?
        LIMIT 1
    ";

    $scoreIdStmt = $pdo->prepare($scoreIdSql);
    $scoreIdStmt->execute([$issueId]);

    $scoreData = $scoreIdStmt->fetch(PDO::FETCH_ASSOC);

    if (!$scoreData) {
        throw new Exception(
            "Unable to retrieve saved priority score."
        );
    }

    $internalScoreId = (int) $scoreData['id'];

    // --------------------------------------------------
    // Replace previous factor values
    // --------------------------------------------------

    $deleteFactorsSql = "
        DELETE FROM issue_priority_score_factors
        WHERE issue_priority_score_id = ?
    ";

    $deleteFactorsStmt = $pdo->prepare($deleteFactorsSql);
    $deleteFactorsStmt->execute([$internalScoreId]);

    // --------------------------------------------------
    // Get factor IDs
    // --------------------------------------------------

    $factorSql = "
        SELECT id, slug
        FROM priority_factors
        WHERE is_active = TRUE
    ";

    $factorStmt = $pdo->prepare($factorSql);
    $factorStmt->execute();

    $factors = [];

    while ($factor = $factorStmt->fetch(PDO::FETCH_ASSOC)) {
        $factors[$factor['slug']] = (int) $factor['id'];
    }

    // --------------------------------------------------
    // Save factor results
    // --------------------------------------------------

    $factorInsertSql = "
        INSERT INTO issue_priority_score_factors (
            issue_priority_score_id,
            factor_id,
            factor_level,
            contribution
        )
        VALUES (?, ?, ?, ?)
    ";

    $factorInsertStmt = $pdo->prepare($factorInsertSql);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['safety'],
        $priority['safety']['level'],
        $priority['safety']['contribution']
    ]);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['location-criticality'],
        $priority['location']['level'],
        $priority['location']['contribution']
    ]);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['severity'],
        $priority['severity']['level'],
        $priority['severity']['contribution']
    ]);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['weather-context'],
        $priority['weather']['level'],
        $priority['weather']['contribution']
    ]);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['report-volume'],
        $priority['report_volume']['level'],
        $priority['report_volume']['contribution']
    ]);

    $factorInsertStmt->execute([
        $internalScoreId,
        $factors['duration'],
        $priority['duration']['level'],
        $priority['duration']['contribution']
    ]);

    return $priority;
}


/**
 * Recalculate the priority of every active Issue (Open / In Progress).
 *
 * @return int Number of Issues recalculated.
 */
function recalculateAllActivePriorities(): int
{
    global $pdo;

    if (function_exists('set_time_limit')) {
        @set_time_limit(55);
    }

    $ids = $pdo->query(
        "SELECT id FROM issues WHERE status IN ('Open', 'In Progress')"
    )->fetchAll(PDO::FETCH_COLUMN);

    $count = 0;

    foreach ($ids as $issueId) {
        try {
            updateIssuePriority((int) $issueId);
            $count++;
        } catch (Throwable $e) {
            error_log('InfraFix bulk priority update failed for issue '
                . $issueId . ': ' . $e->getMessage());
        }
    }

    return $count;
}

/**
 * If the effective weather changed since scores were last calculated,
 * recalculate all active Issues once. Cheap when nothing changed, and
 * never throws, so it is safe to call from page and API entry points.
 */
function syncPriorityWithWeather(): void
{
    global $pdo;

    try {
        $current = getEffectiveWeatherCondition($pdo);
        $applied = getAppSetting($pdo, 'weather_last_applied', '');

        if ($current === $applied) {
            return;
        }

        // Record first so concurrent requests do not all recalculate.
        setAppSetting($pdo, 'weather_last_applied', $current);
        recalculateAllActivePriorities();
    } catch (Throwable $e) {
        error_log('InfraFix weather sync failed: ' . $e->getMessage());
    }
}

