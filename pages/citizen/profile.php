<?php

require_once __DIR__ . '/../../includes/auth.php';

requireCitizenAuth();

$citizenId = getLoggedInCitizenId();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profile - InfraFix</title>

    <link rel="stylesheet" href="../../assets/css/style.css">
</head>

<body>

    <?php include '../../includes/header.php'; ?>

    <div class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>

        <main class="app-content">

            <div class="page-heading">
                <h1>Profile</h1>
                <p>Manage your InfraFix account.</p>
            </div>

            <section class="profile-card">

                <div class="profile-section">
                    <h2>Account Information</h2>

                    <div class="profile-info">

                        <div class="profile-info-row">
                            <span class="profile-info-label">Citizen ID</span>
                            <span class="profile-info-value">
                                <?php echo htmlspecialchars($citizenId); ?>
                            </span>
                        </div>

                    </div>
                </div>
                <div class="profile-section">

                    <h2>Appearance</h2>

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
                <div class="profile-section">
                    <h2>Account</h2>

                    <a href="../../api/auth/logout.php" class="profile-logout-button">
                        Log out
                    </a>
                </div>

            </section>

        </main>

    </div>

    <?php include '../../includes/mobile-nav.php'; ?>

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

</body>

</html>