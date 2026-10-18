<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';

$authorityDatabaseId = getLoggedInAuthorityId();

$issueId = trim($_GET['issue_id'] ?? '');

if ($issueId === '') {
    header('Location: dashboard.php');
    exit;
}


/*
 * Fetch the Issue.
 *
 * The authority scope is checked before displaying any reports.
 */
$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.issue_id,
        c.name AS category_name,
        i.status
    FROM issues i
    INNER JOIN categories c
        ON c.id = i.category_id
    WHERE i.id = ?
      AND i.authority_id = ?
      AND EXISTS (
          SELECT 1
          FROM authority_categories ac
          WHERE ac.authority_id = ?
            AND ac.category_id = i.category_id
            AND ac.is_active = TRUE
      )
    LIMIT 1
");

$stmt->execute([
    $issueId,
    $authorityDatabaseId,
    $authorityDatabaseId
]);

$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    header('Location: dashboard.php');
    exit;
}


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
 * Count reports.
 */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports
    WHERE issue_id = ?
");

$stmt->execute([$issue['id']]);

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
 * Fetch the current page of reports.
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
        (
            SELECT COUNT(*)
            FROM report_photos rp
            WHERE rp.report_id = r.id
        ) AS photo_count
    FROM reports r
    WHERE r.issue_id = ?
    ORDER BY r.reported_at DESC
    LIMIT ? OFFSET ?
");

$stmt->bindValue(1, $issue['id'], PDO::PARAM_INT);
$stmt->bindValue(2, $perPage, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';
?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">

                <h1>Issue Reports</h1>

                <p>
                    Issue ID:
                    <strong>
                        <?= htmlspecialchars($issue['issue_id']) ?>
                    </strong>
                </p>

                <p>
                    Category:
                    <?= htmlspecialchars($issue['category_name']) ?>
                </p>

                <p>
                    Status:
                    <?= htmlspecialchars($issue['status']) ?>
                </p>

            </div>


            <div class="dashboard-section">

                <h2>Reports</h2>

                <p>
                    Showing
                    <?= $totalReports === 0 ? 0 : $offset + 1 ?>
                    -
                    <?= min($offset + $perPage, $totalReports) ?>
                    of
                    <?= $totalReports ?>
                    report(s).
                </p>


                <?php if (count($reports) === 0): ?>

                    <div class="dashboard-card">
                        <p>No reports have been submitted for this Issue.</p>
                    </div>

                <?php else: ?>

                    <?php foreach ($reports as $report): ?>

                        <div class="dashboard-card">

                            <h3>
                                <?= htmlspecialchars($report['report_id']) ?>
                            </h3>

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
                                <?= (int) $report['photo_count'] ?> photo(s)
                            </p>

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
                                href="reports.php?issue_id=<?= (int) $issue['id'] ?>&page=<?= $page - 1 ?>"
                                class="btn btn-secondary">
                                Previous
                            </a>

                        <?php endif; ?>


                        <span>
                            Page <?= $page ?> of <?= $totalPages ?>
                        </span>


                        <?php if ($page < $totalPages): ?>

                            <a
                                href="reports.php?issue_id=<?= (int) $issue['id'] ?>&page=<?= $page + 1 ?>"
                                class="btn btn-secondary">
                                Next
                            </a>

                        <?php endif; ?>

                    </div>

                </div>

            <?php endif; ?>


            <div class="dashboard-section">

                <div class="dashboard-actions">

                    <a
                        href="issue-details.php?id=<?= (int) $issue['id'] ?>"
                        class="btn btn-secondary">
                        Back to Issue
                    </a>

                </div>

            </div>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>