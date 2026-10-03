<?php

require_once '../../includes/auth.php';

requireCitizenAuth();

require_once '../../includes/db.php';


/*
 * Get the currently authenticated citizen.
 */
$citizenId = getLoggedInCitizenId();

if ($citizenId === null) {
    header('Location: ../auth/login.php');
    exit;
}


/*
 * Fetch reports submitted by this citizen.
 *
 * Each row represents one Report.
 * Multiple Reports may belong to the same Issue.
 */
$query = "
    SELECT
        r.report_id,
        r.description,
        r.reported_at,

        i.issue_id,
        i.status,
        i.created_at AS issue_created_at,
        i.updated_at AS issue_updated_at,

        c.name AS category_name,

        a.public_name AS authority_name,

        (
            SELECT COUNT(*)
            FROM reports AS r2
            WHERE r2.issue_id = r.issue_id
        ) AS report_count

    FROM reports AS r

    INNER JOIN issues AS i
        ON i.id = r.issue_id

    INNER JOIN categories AS c
        ON c.id = r.category_id

    LEFT JOIN authorities AS a
        ON a.id = i.authority_id

    WHERE r.citizen_id = :citizen_id

    ORDER BY r.reported_at DESC
";


$statement = $pdo->prepare($query);

$statement->execute([
    ':citizen_id' => $citizenId
]);


$reports = $statement->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>My Reports — InfraFix</title>

    <link
        rel="stylesheet"
        href="../../assets/css/style.css">

</head>


<body>

    <?php include '../../includes/header.php'; ?>


    <main class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>


        <section class="app-content">

            <div class="page-heading">

                <p class="eyebrow">
                    Citizen Portal
                </p>

                <h1>
                    My Reports
                </h1>

                <p>
                    View the infrastructure issues you have
                    reported and track their current status.
                </p>

            </div>


            <?php if (empty($reports)): ?>

                <div class="empty-state">

                    <h2>
                        No reports yet
                    </h2>

                    <p>
                        You have not reported any infrastructure
                        issues yet.
                    </p>

                    <a
                        href="report.php"
                        class="btn btn-primary">
                        Report an Issue
                    </a>

                </div>

            <?php else: ?>

                <div class="reports-list">

                    <?php foreach ($reports as $report): ?>

                        <article class="report-card">

                            <div class="report-card-header">

                                <div>

                                    <p class="report-card-id">
                                        <?php
                                        echo htmlspecialchars(
                                            $report['report_id']
                                        );
                                        ?>
                                    </p>

                                    <h2>
                                        <?php
                                        echo htmlspecialchars(
                                            $report['category_name']
                                        );
                                        ?>
                                    </h2>

                                </div>


                                <span
                                    class="status-badge status-<?php
                                                                echo strtolower(
                                                                    str_replace(
                                                                        ' ',
                                                                        '-',
                                                                        $report['status']
                                                                    )
                                                                );
                                                                ?>">
                                    <?php
                                    echo htmlspecialchars(
                                        $report['status']
                                    );
                                    ?>
                                </span>

                            </div>


                            <div class="report-card-details">

                                <div>
                                    <span class="detail-label">
                                        Issue
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo htmlspecialchars(
                                            $report['issue_id']
                                        );
                                        ?>
                                    </span>
                                </div>


                                <div>
                                    <span class="detail-label">
                                        Reported
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo date(
                                            'd M Y',
                                            strtotime(
                                                $report['reported_at']
                                            )
                                        );
                                        ?>
                                    </span>
                                </div>


                                <div>
                                    <span class="detail-label">
                                        Reports for this issue
                                    </span>

                                    <span class="detail-value">
                                        <?php
                                        echo htmlspecialchars(
                                            $report['report_count']
                                        );
                                        ?>
                                    </span>
                                </div>


                                <?php if (
                                    !empty($report['authority_name'])
                                ): ?>

                                    <div>
                                        <span class="detail-label">
                                            Responsible authority
                                        </span>

                                        <span class="detail-value">
                                            <?php
                                            echo htmlspecialchars(
                                                $report['authority_name']
                                            );
                                            ?>
                                        </span>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="report-card-footer">

                                <a
                                    href="report-details.php?report_id=<?php echo urlencode($report['report_id']); ?>"
                                    class="btn btn-secondary">
                                    View Report
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <?php include '../../includes/mobile-nav.php'; ?>


    <script src="../../assets/js/app.js"></script>

</body>

</html>