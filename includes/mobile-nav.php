<nav class="mobile-nav">

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

</nav>