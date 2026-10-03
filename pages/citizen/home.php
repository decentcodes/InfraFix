<?php
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

requireCitizenAuth();

$citizenId = $_SESSION['citizen_id'];


// Total reports submitted by this citizen
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM reports
    WHERE citizen_id = :citizen_id
");

$stmt->execute([
    ':citizen_id' => $citizenId
]);

$totalReports = (int) $stmt->fetchColumn();


// Active reports
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports r
    INNER JOIN issues i
        ON r.issue_id = i.id
    WHERE r.citizen_id = :citizen_id
      AND i.status IN ('Open', 'In Progress')
");

$stmt->execute([
    ':citizen_id' => $citizenId
]);

$activeReports = (int) $stmt->fetchColumn();


// Resolved reports
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM reports r
    INNER JOIN issues i
        ON r.issue_id = i.id
    WHERE r.citizen_id = :citizen_id
      AND i.status IN ('Resolved', 'Closed')
");

$stmt->execute([
    ':citizen_id' => $citizenId
]);

$resolvedReports = (int) $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Citizen Home — InfraFix</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

    <?php include '../../includes/header.php'; ?>

    <main class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>

        <section class="app-content">

            <div class="page-heading citizen-home-heading">
                <p class="eyebrow">
                    Citizen Portal
                </p>

                <h1>
                    Welcome to InfraFix
                </h1>

                <p>
                    Report public infrastructure issues and keep track
                    of what is happening in your area.
                </p>
            </div>


            <!-- Primary action -->

            <section class="home-report-card">

                <div class="home-report-content">

                    <p class="eyebrow">
                        See something that needs attention?
                    </p>

                    <h2>
                        Report an infrastructure issue
                    </h2>

                    <p>
                        Help your local authorities identify problems
                        such as potholes, garbage, broken streetlights
                        and drainage issues.
                    </p>

                    <a href="report.php" class="btn btn-primary">
                        Report an Issue
                    </a>

                </div>

            </section>


            <!-- Activity -->

            <section class="home-section">

                <div class="home-section-heading">
                    <div>
                        <p class="eyebrow">
                            Your activity
                        </p>

                        <h2>
                            Reports overview
                        </h2>
                    </div>

                    <a href="reports.php" class="text-link">
                        View all
                    </a>
                </div>


                <div class="home-stats-grid">

                    <article class="home-stat-card">
                        <span class="home-stat-value">
                            <?php echo $totalReports; ?>
                        </span>
                        <span class="home-stat-label">Total reports</span>
                    </article>

                    <article class="home-stat-card">
                        <span class="home-stat-value">
                            <?php echo $activeReports; ?>
                        </span>
                        <span class="home-stat-label">Active reports</span>
                    </article>

                    <article class="home-stat-card">
                        <span class="home-stat-value">
                            <?php echo $resolvedReports; ?>
                        </span>
                        <span class="home-stat-label">Resolved reports</span>
                    </article>

                </div>

            </section>


            <!-- Explore -->

            <section class="home-section">

                <div class="home-section-heading">
                    <div>
                        <p class="eyebrow">
                            Explore
                        </p>

                        <h2>
                            What's happening around you?
                        </h2>
                    </div>
                </div>


                <article class="home-nearby-card">

                    <div class="home-nearby-content">

                        <h3>
                            Issues near you
                        </h3>

                        <p>
                            View infrastructure issues reported
                            around your locality and stay informed
                            about problems in your area.
                        </p>

                        <a href="nearby.php" class="text-link">
                            View nearby issues
                        </a>

                    </div>

                </article>

            </section>

        </section>

    </main>

    <?php include '../../includes/mobile-nav.php'; ?>

    <script src="../../assets/js/app.js"></script>

</body>

</html>