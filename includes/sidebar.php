<aside class="sidebar">
    <nav class="sidebar-nav">

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

    </nav>
</aside>