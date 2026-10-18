<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/priority.php';

// Re-score active issues if the weather changed since the last calculation.
syncPriorityWithWeather();


/*
 * ------------------------------------------------------------
 * Current authority
 * ------------------------------------------------------------
 */

$authorityDatabaseId = getLoggedInAuthorityId();

$stmt = $pdo->prepare("
    SELECT
        id,
        authority_id,
        public_name
    FROM authorities
    WHERE id = ?
      AND is_active = TRUE
    LIMIT 1
");

$stmt->execute([$authorityDatabaseId]);

$authority = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$authority) {

    logoutAuthority();

    header('Location: login.php');
    exit;
}


/*
 * ------------------------------------------------------------
 * Issue statistics
 *
 * An issue belongs to this authority when:
 *   1. Its authority_id matches this authority
 *   2. Its category is one of the authority's categories
 *
 * The authority_id condition is the primary scope restriction.
 * The category check additionally respects the authority's
 * configured service categories.
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_issues,

        SUM(status = 'Open') AS open_issues,

        SUM(status = 'In Progress') AS in_progress_issues,

        SUM(status = 'Resolved') AS resolved_issues,

        SUM(status = 'Closed') AS closed_issues,

        SUM(status = 'Rejected') AS rejected_issues

    FROM issues i

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
    WHERE ar.authority_id = i.authority_id
      AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
      AND ar.is_active = TRUE
)
");

$stmt->execute([
    $authorityDatabaseId,
    $authorityDatabaseId
]);

$stats = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * ------------------------------------------------------------
 * Priority-ordered active issues
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.issue_id,
        i.category_id,
        c.name AS category_name,
        i.status,
        i.latitude,
        i.longitude,
        i.created_at,
        i.updated_at,

        ips.priority_score AS priority_score,

        (
            SELECT COUNT(*)
            FROM reports r
            WHERE r.issue_id = i.id
        ) AS report_count

    FROM issues i

    INNER JOIN categories c
        ON c.id = i.category_id

    LEFT JOIN issue_priority_scores ips
        ON ips.issue_id = i.id

    WHERE i.authority_id = ?

      AND i.status IN ('Open', 'In Progress')

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
    WHERE ar.authority_id = i.authority_id
      AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
      AND ar.is_active = TRUE
)

    ORDER BY
    ips.priority_score DESC,
    i.created_at ASC

    LIMIT 5
");

$stmt->execute([
    $authorityDatabaseId,
    $authorityDatabaseId
]);

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';

?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">

                <h1>Authority Dashboard</h1>

                <p>
                    <?= htmlspecialchars($authority['public_name']) ?>
                </p>

                <p>
                    Authority ID:
                    <?= htmlspecialchars($authority['authority_id']) ?>
                </p>

            </div>


            <!-- Issue Statistics -->

            <div class="dashboard-section">

                <h2>Issue Overview</h2>

                <div class="dashboard-grid">


                    <div class="dashboard-card">

                        <h3>Total Issues</h3>

                        <p class="dashboard-stat">
                            <?= (int) ($stats['total_issues'] ?? 0) ?>
                        </p>

                    </div>


                    <div class="dashboard-card">

                        <h3>Open</h3>

                        <p class="dashboard-stat">
                            <?= (int) ($stats['open_issues'] ?? 0) ?>
                        </p>

                    </div>


                    <div class="dashboard-card">

                        <h3>In Progress</h3>

                        <p class="dashboard-stat">
                            <?= (int) ($stats['in_progress_issues'] ?? 0) ?>
                        </p>

                    </div>


                    <div class="dashboard-card">

                        <h3>Resolved</h3>

                        <p class="dashboard-stat">
                            <?= (int) ($stats['resolved_issues'] ?? 0) ?>
                        </p>

                    </div>


                </div>

            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2>Issues Map</h2>
                </div>

                <div
                    id="authority-map"
                    style="height: 500px; width: 100%;"></div>
            </div>

            <!-- Priority Queue -->

            <div class="dashboard-section">

                <h2>Priority Issues</h2>

                <p>
                    Active issues are ordered by their current priority score.
                </p>

                <div class="dashboard-actions">
                    <a
                        href="issues.php"
                        class="btn btn-secondary">
                        View All Issues →
                    </a>
                </div>


                <?php if (count($issues) === 0): ?>

                    <div class="dashboard-card">

                        <p>
                            There are currently no open or in-progress
                            issues assigned to this authority.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-container">

                        <table>

                            <thead>

                                <tr>

                                    <th>Issue ID</th>

                                    <th>Category</th>

                                    <th>Status</th>

                                    <th>Priority</th>

                                    <th>Reports</th>

                                    <th>Reported</th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($issues as $issue): ?>

                                    <tr>

                                        <td>
                                            <a href="issue-details.php?id=<?= (int) $issue['id'] ?>">
                                                <?= htmlspecialchars($issue['issue_id']) ?>
                                            </a>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($issue['category_name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($issue['status']) ?>
                                        </td>

                                        <td>
                                            <?php if ($issue['priority_score'] === null): ?>
                                                Unscored
                                            <?php else: ?>
                                                <?= number_format(
                                                    (float) $issue['priority_score'],
                                                    2
                                                ) ?>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?= (int) $issue['report_count'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($issue['created_at']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>

<script src="../../assets/js/app.js"></script>