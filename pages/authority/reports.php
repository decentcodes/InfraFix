<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';

$authorityDatabaseId = getLoggedInAuthorityId();


/*
 * Pagination
 */

$perPage = 10;

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT
);

if (!$page || $page < 1) {
    $page = 1;
}


/*
 * Count Reports
 *
 * Only Reports belonging to Issues that are:
 * - assigned to the logged-in authority
 * - inside an active region of that authority
 * - from a category actively handled by that authority
 */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports r
    INNER JOIN issues i
        ON i.id = r.issue_id
    WHERE i.authority_id = ?
      AND EXISTS (
          SELECT 1
          FROM authority_categories ac
          WHERE ac.authority_id = ?
            AND ac.category_id = i.category_id
            AND ac.is_active = TRUE
      )
      AND EXISTS (
          SELECT 1
          FROM authority_regions ar
          WHERE ar.authority_id = ?
            AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
            AND ar.is_active = TRUE
      )
");

$stmt->execute([
    $authorityDatabaseId,
    $authorityDatabaseId,
    $authorityDatabaseId
]);

$totalReports = (int) $stmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalReports / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;


/*
 * Fetch Reports
 *
 * Citizen identity is deliberately not selected.
 */

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.report_id,
        r.description,
        r.reported_latitude,
        r.reported_longitude,
        r.location_verified,
        r.location_verification_distance_m,
        r.reported_at,

        i.id AS issue_database_id,
        i.issue_id,
        i.status,

        c.name AS category_name,

        (
            SELECT COUNT(*)
            FROM report_photos rp
            WHERE rp.report_id = r.id
        ) AS photo_count

    FROM reports r

    INNER JOIN issues i
        ON i.id = r.issue_id

    INNER JOIN categories c
        ON c.id = i.category_id

    WHERE i.authority_id = ?

      AND EXISTS (
          SELECT 1
          FROM authority_categories ac
          WHERE ac.authority_id = ?
            AND ac.category_id = i.category_id
            AND ac.is_active = TRUE
      )

      AND EXISTS (
          SELECT 1
          FROM authority_regions ar
          WHERE ar.authority_id = ?
            AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
            AND ar.is_active = TRUE
      )

    ORDER BY r.reported_at DESC

    LIMIT ? OFFSET ?
");

$stmt->bindValue(
    1,
    $authorityDatabaseId,
    PDO::PARAM_INT
);

$stmt->bindValue(
    2,
    $authorityDatabaseId,
    PDO::PARAM_INT
);

$stmt->bindValue(
    3,
    $authorityDatabaseId,
    PDO::PARAM_INT
);

$stmt->bindValue(
    4,
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    5,
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';
?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">

                <h1>Reports</h1>

                <p>
                    Citizen reports received for Issues within your
                    authority's assigned scope.
                </p>

            </div>


            <div class="dashboard-section">

                <div class="section-header">

                    <h2>All Reports</h2>

                    <p>
                        Showing
                        <?= $totalReports === 0 ? 0 : $offset + 1 ?>
                        -
                        <?= min($offset + $perPage, $totalReports) ?>
                        of
                        <?= $totalReports ?>
                        report(s).
                    </p>

                </div>


                <?php if (count($reports) === 0): ?>

                    <div class="dashboard-card">

                        <p>
                            No reports have been submitted for the Issues
                            currently within your authority scope.
                        </p>

                    </div>

                <?php else: ?>

                    <?php foreach ($reports as $report): ?>

                        <div class="dashboard-card">

                            <div class="section-header">

                                <h3>
                                    <?= htmlspecialchars($report['report_id']) ?>
                                </h3>

                                <span>
                                    <?= htmlspecialchars($report['status']) ?>
                                </span>

                            </div>


                            <p>
                                <strong>Issue:</strong>

                                <a
                                    href="issue-details.php?id=<?= (int) $report['issue_database_id'] ?>">
                                    <?= htmlspecialchars($report['issue_id']) ?>
                                </a>
                            </p>


                            <p>
                                <strong>Category:</strong>
                                <?= htmlspecialchars($report['category_name']) ?>
                            </p>


                            <p>
                                <strong>Reported:</strong>
                                <?= htmlspecialchars($report['reported_at']) ?>
                            </p>


                            <?php if (trim($report['description']) !== ''): ?>

                                <p>
                                    <strong>Description:</strong>
                                </p>

                                <p>
                                    <?= nl2br(
                                        htmlspecialchars($report['description'])
                                    ) ?>
                                </p>

                            <?php else: ?>

                                <p>
                                    <strong>Description:</strong>
                                    No description provided.
                                </p>

                            <?php endif; ?>


                            <p>
                                <strong>Reported Location:</strong>
                                <?= htmlspecialchars($report['reported_latitude']) ?>,
                                <?= htmlspecialchars($report['reported_longitude']) ?>
                            </p>


                            <p>
                                <strong>Location Verified:</strong>

                                <?= $report['location_verified']
                                    ? 'Yes'
                                    : 'No' ?>
                            </p>


                            <?php if (
                                $report['location_verification_distance_m']
                                !== null
                            ): ?>

                                <p>
                                    <strong>
                                        Verification Distance:
                                    </strong>

                                    <?= number_format(
                                        (float)
                                        $report['location_verification_distance_m'],
                                        2
                                    ) ?>
                                    m
                                </p>

                            <?php endif; ?>


                            <p>
                                <strong>Evidence:</strong>

                                <?= (int) $report['photo_count'] ?>
                                photo(s)
                            </p>


                            <div class="dashboard-actions">

                                <a
                                    href="issue-details.php?id=<?= (int) $report['issue_database_id'] ?>"
                                    class="btn btn-secondary">
                                    View Report
                                </a>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- Pagination -->

            <?php if ($totalPages > 1): ?>

                <div class="dashboard-section">

                    <div class="dashboard-actions">

                        <?php if ($page > 1): ?>

                            <a
                                href="reports.php?page=<?= $page - 1 ?>"
                                class="btn btn-secondary">
                                Previous
                            </a>

                        <?php endif; ?>


                        <span>
                            Page <?= $page ?> of <?= $totalPages ?>
                        </span>


                        <?php if ($page < $totalPages): ?>

                            <a
                                href="reports.php?page=<?= $page + 1 ?>"
                                class="btn btn-secondary">
                                Next
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>