<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Citizen Home — InfraFix</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

    <header class="app-header">

        <a href="../../index.php" class="logo">
            InfraFix
        </a>

        <div class="app-header-actions">
            <span>Citizen</span>
            <a href="../auth/login.php">Logout</a>
        </div>

    </header>


    <main class="app-layout">

        <aside class="sidebar">

            <nav class="sidebar-nav">

                <a href="home.php" class="nav-item active">
                    Home
                </a>

                <a href="#" class="nav-item">
                    Report Issue
                </a>

                <a href="#" class="nav-item">
                    My Reports
                </a>

                <a href="#" class="nav-item">
                    Map
                </a>

                <a href="#" class="nav-item">
                    Profile
                </a>

            </nav>

        </aside>


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


    <nav class="mobile-nav">

        <a href="home.php" class="active">
            Home
        </a>

        <a href="#">
            Report
        </a>

        <a href="#">
            Map
        </a>

        <a href="#">
            Profile
        </a>

    </nav>


    <script src="../../assets/js/app.js"></script>

</body>

</html>