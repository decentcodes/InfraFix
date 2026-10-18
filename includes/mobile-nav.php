<?php

require_once __DIR__ . '/auth.php';

$isAuthorityPortal = strpos(
    $_SERVER['SCRIPT_NAME'],
    '/pages/authority/'
) !== false;

$isCitizenPortal = strpos(
    $_SERVER['SCRIPT_NAME'],
    '/pages/citizen/'
) !== false;

?>

<nav class="mobile-nav">


    <?php if ($isAuthorityPortal): ?>

        <a href="dashboard.php"
            class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
            Dashboard
        </a>

        <a href="issues.php"
            class="<?= in_array(
                        basename($_SERVER['PHP_SELF']),
                        [
                            'issues.php',
                            'issue-details.php',
                            'issue-reports.php',
                            'reports.php',
                            'evidence.php'
                        ]
                    ) ? 'active' : ''; ?>">
            Issues
        </a>

        <a href="profile.php"
            class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            Profile
        </a>

    <?php elseif ($isCitizenPortal): ?>


        <a href="home.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'home.php' ? 'active' : ''; ?>">
            Home
        </a>

        <a href="report.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'report.php' ? 'active' : ''; ?>">
            Report
        </a>

        <a href="nearby.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'nearby.php' ? 'active' : ''; ?>">
            Map
        </a>

        <a href="reports.php"
            class="<?php
                    echo in_array(
                        basename($_SERVER['PHP_SELF']),
                        ['reports.php', 'report-details.php']
                    ) ? 'active' : '';
                    ?>">
            My Reports
        </a>

        <a href="profile.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
            Profile
        </a>

    <?php endif; ?>

</nav>