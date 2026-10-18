<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';

$adminCode = $_SESSION['admin_code'] ?? 'Admin';


/*
 * ------------------------------------------------------------
 * Authority request statistics
 * ------------------------------------------------------------
 */

$stmt = $pdo->query("
    SELECT
        COUNT(*) AS total_requests,
        SUM(status = 'Pending') AS pending_requests,
        SUM(status = 'Approved') AS approved_requests,
        SUM(status = 'Rejected') AS rejected_requests
    FROM authority_access_requests
");

$requestStats = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * ------------------------------------------------------------
 * Active authorities
 * ------------------------------------------------------------
 */

$stmt = $pdo->query("
    SELECT COUNT(*) AS active_authorities
    FROM authorities
    WHERE is_active = TRUE
");

$activeAuthorities = $stmt->fetchColumn();


require_once __DIR__ . '/../../includes/header.php';

?>

<div class="page-container">

    <div class="page-header">

        <h1>Admin Dashboard</h1>

        <p>
            Welcome, <?= htmlspecialchars($adminCode) ?>
        </p>

    </div>


    <!-- Authority Request Statistics -->

    <div class="dashboard-section">

        <h2>Authority Access</h2>

        <div class="dashboard-grid">

            <div class="dashboard-card">

                <h3>Pending Requests</h3>

                <p class="dashboard-stat">
                    <?= (int) ($requestStats['pending_requests'] ?? 0) ?>
                </p>

                <a
                    href="requests.php?status=Pending"
                    class="btn btn-secondary">
                    View Pending
                </a>

            </div>


            <div class="dashboard-card">

                <h3>Total Requests</h3>

                <p class="dashboard-stat">
                    <?= (int) ($requestStats['total_requests'] ?? 0) ?>
                </p>

                <a
                    href="requests.php"
                    class="btn btn-secondary">
                    View Requests
                </a>

            </div>


            <div class="dashboard-card">

                <h3>Approved</h3>

                <p class="dashboard-stat">
                    <?= (int) ($requestStats['approved_requests'] ?? 0) ?>
                </p>

            </div>


            <div class="dashboard-card">

                <h3>Rejected</h3>

                <p class="dashboard-stat">
                    <?= (int) ($requestStats['rejected_requests'] ?? 0) ?>
                </p>

            </div>

        </div>

    </div>


    <!-- Active Authorities -->

    <div class="dashboard-section">

        <h2>Registered Authorities</h2>

        <div class="dashboard-card">

            <h3>Active Authorities</h3>

            <p class="dashboard-stat">
                <?= (int) $activeAuthorities ?>
            </p>

        </div>

    </div>

    <div class="dashboard-section">
        <h2>Appearance</h2>

        <div class="dashboard-card">
            <div class="profile-setting-row">
                <div>
                    <span class="profile-setting-title">Dark Theme</span>
                    <p class="profile-setting-description">
                        Use a darker appearance across InfraFix.
                    </p>
                </div>

                <label class="theme-switch">
                    <input type="checkbox" id="theme-toggle">
                    <span class="theme-switch-slider"></span>
                </label>
            </div>
        </div>
    </div>

    <!-- Admin Actions -->

    <div class="dashboard-section">

        <h2>Administration</h2>

        <div class="dashboard-actions">

            <a
                href="requests.php"
                class="btn btn-primary">
                Manage Authority Requests
            </a>

            <form action="../../api/admin/logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="profile-logout-button">Logout</button>
            </form>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>


<div class="page-container">
    <div class="dashboard-section">
        <h2>Platform</h2>
        <div class="dashboard-card">
            <h3>Settings</h3>
            <p>Weather source and citizen OTP mode.</p>
            <a href="settings.php" class="btn btn-secondary">Open Settings</a>
        </div>
    </div>
</div>

<script>
    const themeToggle = document.getElementById('theme-toggle');

    if (themeToggle) {
        themeToggle.checked =
            localStorage.getItem('infrafix-theme') === 'dark';

        themeToggle.addEventListener('change', () => {
            if (themeToggle.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('infrafix-theme', 'dark');
            } else {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('infrafix-theme', 'light');
            }
        });
    }
</script>