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

<aside class="sidebar">
    <nav class="sidebar-nav">


        <?php if ($isAuthorityPortal): ?>

            <a href="../authority/dashboard.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                Dashboard
            </a>

            <a href="../authority/issues.php"
                class="nav-item <?= in_array(
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

            <a href="../authority/profile.php"
                class="nav-item <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                Profile
            </a>

        <?php elseif ($isCitizenPortal): ?>

            <a href="../citizen/home.php"
                class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'home.php' ? 'active' : ''; ?>">
                Home
            </a>

            <a href="../citizen/report.php"
                class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'report.php' ? 'active' : ''; ?>">
                Report
            </a>

            <a href="../citizen/nearby.php"
                class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'nearby.php' ? 'active' : ''; ?>">
                Map
            </a>

            <a href="../citizen/reports.php"
                class="nav-item <?php
                                echo in_array(
                                    basename($_SERVER['PHP_SELF']),
                                    ['reports.php', 'report-details.php']
                                ) ? 'active' : '';
                                ?>">
                My Reports
            </a>

            <a href="../citizen/profile.php"
                class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                Profile
            </a>

        <?php endif; ?>

    </nav>
</aside>