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

/*
 * Page titles, keyed by "folder/file.php". A page can override its title
 * by setting $pageTitle before including this file.
 */
$pageTitles = [
    'admin/dashboard.php' => 'Admin Dashboard',
    'admin/login.php' => 'Admin Login',
    'admin/request-details.php' => 'Access Request Details',
    'admin/requests.php' => 'Access Requests',
    'admin/settings.php' => 'Platform Settings',
    'authority/dashboard.php' => 'Authority Dashboard',
    'authority/evidence.php' => 'Issue Evidence',
    'authority/issue-details.php' => 'Issue Details',
    'authority/issue-reports.php' => 'Issue Reports',
    'authority/issues.php' => 'Issues',
    'authority/login.php' => 'Authority Login',
    'authority/profile.php' => 'Authority Profile',
    'authority/reports.php' => 'Reports',
    'authority/request-access.php' => 'Request Authority Access',
];

$pageKey = basename(dirname($scriptPath)) . '/' . basename($scriptPath);

if (!isset($pageTitle)) {
    $pageTitle = $pageTitles[$pageKey] ?? '';
}
?>

<?php
/*
 * Pages that write their own <head> (citizen pages and citizen login)
 * already printed the favicon and a <title>. require_once returns true
 * when favicon.php was already included, so only the other pages print
 * a title here.
 */
$pageWroteOwnHead = (require_once __DIR__ . '/favicon.php') === true;

if (!$pageWroteOwnHead) {
    echo '<title>'
        . htmlspecialchars(($pageTitle !== '' ? $pageTitle . ' • ' : '') . 'InfraFix')
        . "</title>\n";
}
?>
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