<?php
$scriptPath = $_SERVER['SCRIPT_NAME'];

if (strpos($scriptPath, '/pages/authority/') !== false) {
    $logoHref = '../authority/dashboard.php';
    $portalName = 'Authority';
} elseif (strpos($scriptPath, '/pages/admin/') !== false) {
    $logoHref = '../admin/dashboard.php';
    $portalName = 'Admin';
} else {
    $logoHref = '../citizen/home.php';
    $portalName = 'Citizen';
}
?>

<?php require_once __DIR__ . '/favicon.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<script>
    if (localStorage.getItem('infrafix-theme') === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    }
</script>

<link rel="stylesheet" href="../../assets/css/style.css">

<header class="app-header">

    <a href="<?= htmlspecialchars($logoHref) ?>" class="logo">
        InfraFix
    </a>

    <div class="app-header-actions">
        <span><?= htmlspecialchars($portalName) ?></span>
    </div>

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</header>