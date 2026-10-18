<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/priority.php';

// Re-score active issues if the weather changed since the last calculation.
syncPriorityWithWeather();

$authorityDatabaseId = getLoggedInAuthorityId();


/*
 * ------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------
 */

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$sort = trim($_GET['sort'] ?? 'priority');

$allowedStatuses = [
    'Open',
    'In Progress',
    'Resolved',
    'Closed',
    'Rejected'
];

$allowedSorts = [
    'priority',
    'duration',
    'reports',
    'newest'
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'priority';
}

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}


/*
 * ------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------
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
 * ------------------------------------------------------------
 * Build common WHERE clause
 * ------------------------------------------------------------
 */

$where = [
    'i.authority_id = ?'
];

$params = [
    $authorityDatabaseId
];


/*
 * Active authority category
 */

$where[] = "
    EXISTS (
        SELECT 1
        FROM authority_categories ac
        WHERE ac.authority_id = ?
          AND ac.category_id = i.category_id
          AND ac.is_active = TRUE
    )
";

$params[] = $authorityDatabaseId;


/*
 * Active authority region
 */

$where[] = "
    EXISTS (
        SELECT 1
        FROM authority_regions ar
        WHERE ar.authority_id = ?
          AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
          AND ar.is_active = TRUE
    )
";

$params[] = $authorityDatabaseId;


/*
 * Status filter
 */

if ($status !== '') {

    $where[] = 'i.status = ?';

    $params[] = $status;
}


/*
 * Issue ID search
 */

if ($search !== '') {

    $where[] = 'i.issue_id LIKE ?';

    $params[] = '%' . $search . '%';
}


$whereSql = implode("\n AND ", $where);


/*
 * ------------------------------------------------------------
 * Count Issues
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM issues i
    WHERE $whereSql
");

$stmt->execute($params);

$totalIssues = (int) $stmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalIssues / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;


/*
 * ------------------------------------------------------------
 * Fetch Issues
 * ------------------------------------------------------------
 */

switch ($sort) {

    case 'duration':
        $orderBy = "
            TIMESTAMPDIFF(
                SECOND,
                i.created_at,
                NOW()
            ) DESC,
            i.created_at ASC
        ";
        break;

    case 'reports':
        $orderBy = "
            report_count DESC,
            i.created_at DESC
        ";
        break;

    case 'newest':
        $orderBy = "
            i.created_at DESC
        ";
        break;

    case 'priority':
    default:
        $orderBy = "
            priority_score DESC,
            i.created_at ASC
        ";
        break;
}

$sql = "
    SELECT
        i.id,
        i.issue_id,
        c.name AS category_name,
        i.status,
        i.created_at,

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

    WHERE $whereSql

    ORDER BY
        $orderBy

    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);

$parameterIndex = 1;

foreach ($params as $param) {

    $stmt->bindValue(
        $parameterIndex,
        $param
    );

    $parameterIndex++;
}

$stmt->bindValue(
    $parameterIndex,
    $perPage,
    PDO::PARAM_INT
);

$parameterIndex++;

$stmt->bindValue(
    $parameterIndex,
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';

?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">

                <h1>Issues</h1>

                <p>
                    All public infrastructure issues within your
                    authority's assigned scope.
                </p>

            </div>


            <!-- Filters -->

            <div class="dashboard-section">

                <div class="section-header">

                    <h2>Find Issues</h2>

                </div>


                <form method="GET">

                    <div class="dashboard-actions">

                        <input
                            type="text"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                            placeholder="Search Issue ID">


                        <select name="status">

                            <option value="">
                                All Statuses
                            </option>

                            <?php foreach ($allowedStatuses as $option): ?>

                                <option
                                    value="<?= htmlspecialchars($option) ?>"
                                    <?= $status === $option
                                        ? 'selected'
                                        : '' ?>>
                                    <?= htmlspecialchars($option) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <select name="sort">

                            <option
                                value="priority"
                                <?= $sort === 'priority' ? 'selected' : '' ?>>
                                Priority — Highest First
                            </option>

                            <option
                                value="duration"
                                <?= $sort === 'duration' ? 'selected' : '' ?>>
                                Duration — Longest First
                            </option>

                            <option
                                value="reports"
                                <?= $sort === 'reports' ? 'selected' : '' ?>>
                                Reports — Most First
                            </option>

                            <option
                                value="newest"
                                <?= $sort === 'newest' ? 'selected' : '' ?>>
                                Newest First
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="btn btn-primary">
                            Apply Filters
                        </button>


                        <?php if (
                            $search !== '' ||
                            $status !== '' ||
                            $sort !== 'priority'
                        ): ?>

                            <a
                                href="issues.php"
                                class="btn btn-secondary">
                                Clear
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </div>


            <!-- Issue List -->

            <div class="dashboard-section">

                <div class="section-header">

                    <h2>Issue List</h2>

                    <p>
                        Showing
                        <?= $totalIssues === 0 ? 0 : $offset + 1 ?>
                        -
                        <?= min(
                            $offset + $perPage,
                            $totalIssues
                        ) ?>
                        of
                        <?= $totalIssues ?>
                        issue(s).
                    </p>

                </div>


                <?php if (count($issues) === 0): ?>

                    <div class="dashboard-card">

                        <p>
                            No Issues match the selected filters.
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

                                            <a
                                                href="issue-details.php?id=<?= (int) $issue['id'] ?>">
                                                <?= htmlspecialchars(
                                                    $issue['issue_id']
                                                ) ?>
                                            </a>

                                        </td>


                                        <td>
                                            <?= htmlspecialchars(
                                                $issue['category_name']
                                            ) ?>
                                        </td>


                                        <td>
                                            <?= htmlspecialchars(
                                                $issue['status']
                                            ) ?>
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
                                            <?= htmlspecialchars(
                                                $issue['created_at']
                                            ) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <!-- Pagination -->

            <?php if ($totalPages > 1): ?>

                <div class="dashboard-section">

                    <div class="dashboard-actions">

                        <?php if ($page > 1): ?>

                            <a
                                href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page - 1 ?>"
                                class="btn btn-secondary">
                                Previous
                            </a>

                        <?php endif; ?>


                        <span>
                            Page <?= $page ?>
                            of
                            <?= $totalPages ?>
                        </span>


                        <?php if ($page < $totalPages): ?>

                            <a
                                href="?search=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>&page=<?= $page + 1 ?>"
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