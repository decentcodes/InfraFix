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
 * Get the requested report ID.
 */
$reportId = $_GET['report_id'] ?? '';

if ($reportId === '') {
    header('Location: reports.php');
    exit;
}


/*
 * Fetch the report and its associated Issue.
 *
 * The citizen_id condition is important:
 * a citizen can only view their own report.
 */
$query = "
    SELECT
        r.report_id,
        r.description,
        r.reported_latitude,
        r.reported_longitude,
        r.reporter_latitude,
        r.reporter_longitude,
        r.location_verified,
        r.location_verification_distance_m,
        r.reported_at,

        i.issue_id,
        i.status,
        i.latitude AS issue_latitude,
        i.longitude AS issue_longitude,
        i.created_at AS issue_created_at,
        i.updated_at AS issue_updated_at,

        c.name AS category_name,

        a.public_name AS authority_name

    FROM reports AS r

    INNER JOIN issues AS i
        ON i.id = r.issue_id

    INNER JOIN categories AS c
        ON c.id = r.category_id

    LEFT JOIN authorities AS a
        ON a.id = i.authority_id

    WHERE r.report_id = :report_id
      AND r.citizen_id = :citizen_id

    LIMIT 1
";


$statement = $pdo->prepare($query);

$statement->execute([
    ':report_id' => $reportId,
    ':citizen_id' => $citizenId
]);


$report = $statement->fetch(PDO::FETCH_ASSOC);


/*
 * If the report does not exist or does not belong
 * to the logged-in citizen, return to My Reports.
 */
if (!$report) {
    header('Location: reports.php');
    exit;
}


/*
 * Count all reports associated with this Issue.
 */
$countQuery = "
    SELECT COUNT(*) AS report_count
    FROM reports
    WHERE issue_id = (
        SELECT id
        FROM issues
        WHERE issue_id = :issue_id
    )
";


$countStatement = $pdo->prepare($countQuery);

$countStatement->execute([
    ':issue_id' => $report['issue_id']
]);


$reportCount = $countStatement->fetchColumn();


/*
 * Fetch all reports associated with this Issue.
 *
 * We do not expose the identity of the citizens
 * who submitted those reports.
 */
$issueReportsQuery = "
    SELECT
        report_id,
        reported_at
    FROM reports
    WHERE issue_id = (
        SELECT id
        FROM issues
        WHERE issue_id = :issue_id
    )
    ORDER BY reported_at ASC
";


$issueReportsStatement = $pdo->prepare($issueReportsQuery);

$issueReportsStatement->execute([
    ':issue_id' => $report['issue_id']
]);


$issueReports = $issueReportsStatement->fetchAll(PDO::FETCH_ASSOC);


/*
 * Fetch the photo associated with this report.
 */
$photoQuery = "
    SELECT
        file_path,
        original_filename,
        mime_type
    FROM report_photos
    WHERE report_id = (
        SELECT id
        FROM reports
        WHERE report_id = :report_id
    )
    ORDER BY uploaded_at ASC
    LIMIT 1
";


$photoStatement = $pdo->prepare($photoQuery);

$photoStatement->execute([
    ':report_id' => $report['report_id']
]);


$photo = $photoStatement->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Report Details — InfraFix
    </title>

    <link
        rel="stylesheet"
        href="../../assets/css/style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

</head>


<body>

    <?php include '../../includes/header.php'; ?>


    <main class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>


        <section class="app-content">

            <div class="page-heading">

                <p class="eyebrow">
                    My Reports
                </p>

                <h1>
                    Report Details
                </h1>

                <p>
                    View the details of your report and
                    the infrastructure issue it belongs to.
                </p>

            </div>


            <!-- Report information -->

            <section class="details-section">

                <div class="details-section-header">

                    <div>

                        <p class="eyebrow">
                            Your Report
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


                <div class="details-grid">

                    <div class="detail-item">

                        <span class="detail-label">
                            Report ID:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo htmlspecialchars(
                                $report['report_id']
                            );
                            ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            Reported on:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo date(
                                'd M Y, h:i A',
                                strtotime(
                                    $report['reported_at']
                                )
                            );
                            ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            Location verification:
                        </span>

                        <span class="detail-value">

                            <?php if (
                                $report['location_verified']
                            ): ?>

                                Verified

                                <?php if (
                                    $report['location_verification_distance_m'] !== null
                                ): ?>

                                    (
                                    <?php
                                    echo number_format(
                                        (float) $report['location_verification_distance_m'],
                                        1
                                    );
                                    ?>
                                    m away
                                    )

                                <?php endif; ?>

                            <?php else: ?>

                                Not verified

                            <?php endif; ?>

                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            Issue ID:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo htmlspecialchars(
                                $report['issue_id']
                            );
                            ?>
                        </span>

                    </div>

                </div>


                <?php if (
                    !empty($report['description'])
                ): ?>

                    <div class="details-description">

                        <span class="detail-label">
                            Description:
                        </span>

                        <p>
                            <?php
                            echo nl2br(
                                htmlspecialchars(
                                    $report['description']
                                )
                            );
                            ?>
                        </p>

                    </div>

                <?php endif; ?>


                <?php if ($photo): ?>

                    <div class="details-photo">

                        <span class="detail-label">
                            Evidence photo:
                        </span>

                        <img
                            src="../../<?php
                                        echo htmlspecialchars(
                                            $photo['file_path']
                                        );
                                        ?>"
                            alt="Report evidence">

                    </div>

                <?php endif; ?>

            </section>


            <!-- Issue information -->

            <section class="details-section">

                <div class="details-section-header">

                    <div>

                        <p class="eyebrow">
                            Infrastructure Issue
                        </p>

                        <h2>
                            <?php
                            echo htmlspecialchars(
                                $report['issue_id']
                            );
                            ?>
                        </h2>

                    </div>

                </div>


                <div class="details-grid">

                    <div class="detail-item">

                        <span class="detail-label">
                            Status:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo htmlspecialchars(
                                $report['status']
                            );
                            ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            First reported on:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo date(
                                'd M Y',
                                strtotime(
                                    $report['issue_created_at']
                                )
                            );
                            ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            Last updated on:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo date(
                                'd M Y',
                                strtotime(
                                    $report['issue_updated_at']
                                )
                            );
                            ?>
                        </span>

                    </div>


                    <div class="detail-item">

                        <span class="detail-label">
                            Total reports:
                        </span>

                        <span class="detail-value">
                            <?php
                            echo htmlspecialchars(
                                $reportCount
                            );
                            ?>
                        </span>

                    </div>


                    <?php if (
                        !empty($report['authority_name'])
                    ): ?>

                        <div class="detail-item">

                            <span class="detail-label">
                                Responsible authority:
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

            </section>

            <!-- Issue location -->

            <section class="details-section">

                <div class="details-section-header">

                    <div>

                        <p class="eyebrow">
                            Location
                        </p>

                        <h2>
                            Issue Location
                        </h2>

                    </div>

                </div>


                <div
                    id="issue-map"
                    data-latitude="<?php
                                    echo htmlspecialchars(
                                        $report['issue_latitude']
                                    );
                                    ?>"
                    data-longitude="<?php
                                    echo htmlspecialchars(
                                        $report['issue_longitude']
                                    );
                                    ?>"></div>

            </section>

            <!-- Reports belonging to this Issue -->

            <section class="details-section">

                <div class="details-section-header">

                    <div>

                        <p class="eyebrow">
                            Issue Activity
                        </p>

                        <h2>
                            Reports for this issue
                        </h2>

                    </div>

                </div>


                <div class="issue-reports-list">

                    <?php foreach (
                        $issueReports as $issueReport
                    ): ?>

                        <div class="issue-report-row">

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $issueReport['report_id']
                                );
                                ?>
                            </span>

                            <span>
                                <?php
                                echo date(
                                    'd M Y, h:i A',
                                    strtotime(
                                        $issueReport['reported_at']
                                    )
                                );
                                ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            </section>


            <div class="details-actions">

                <a
                    href="reports.php"
                    class="btn btn-secondary">
                    Back to My Reports
                </a>

            </div>

        </section>

    </main>


    <?php include '../../includes/mobile-nav.php'; ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        const issueMapElement =
            document.getElementById('issue-map');

        if (issueMapElement) {

            const latitude =
                parseFloat(
                    issueMapElement.dataset.latitude
                );

            const longitude =
                parseFloat(
                    issueMapElement.dataset.longitude
                );


            if (
                Number.isFinite(latitude) &&
                Number.isFinite(longitude)
            ) {

                const issueMap =
                    L.map('issue-map').setView(
                        [latitude, longitude],
                        16
                    );


                L.tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }
                ).addTo(issueMap);


                L.marker(
                        [latitude, longitude]
                    )
                    .addTo(issueMap)
                    .bindPopup('Reported issue')
                    .openPopup();

            }

        }
    </script>

    <script src="../../assets/js/app.js"></script>

</body>

</html>