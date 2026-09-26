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

            <div class="page-heading">

                <p class="eyebrow">
                    Citizen Portal
                </p>

                <h1>
                    Welcome to InfraFix
                </h1>

                <p>
                    Report infrastructure issues and keep track of
                    their progress in your area.
                </p>

            </div>


            <div class="dashboard-grid">

                <article class="dashboard-card">
                    <h2>Report an issue</h2>

                    <p>
                        Found a pothole, broken streetlight or another
                        infrastructure problem?
                    </p>

                    <a href="#" class="btn btn-primary">
                        Report Issue
                    </a>
                </article>


                <article class="dashboard-card">
                    <h2>My reports</h2>

                    <p>
                        View the issues you have reported and their
                        current status.
                    </p>

                    <a href="#" class="btn btn-secondary">
                        View Reports
                    </a>
                </article>


                <article class="dashboard-card">
                    <h2>Nearby issues</h2>

                    <p>
                        See infrastructure issues reported around
                        your locality.
                    </p>

                    <a href="#" class="btn btn-secondary">
                        Open Map
                    </a>
                </article>

            </div>

        </section>

    </main>

    <?php include '../../includes/mobile-nav.php'; ?>

    <script src="../../assets/js/app.js"></script>

</body>

</html>