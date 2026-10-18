<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';

$authorityDatabaseId = getLoggedInAuthorityId();


/*
 * ------------------------------------------------------------
 * Fetch authority information
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        id,
        authority_id,
        public_name,
        phone,
        email,
        parent_authority_id,
        is_active,
        created_at
    FROM authorities
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$authorityDatabaseId]);

$authority = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$authority || !$authority['is_active']) {
    logoutAuthority();
    header('Location: login.php');
    exit;
}


/*
 * ------------------------------------------------------------
 * Fetch assigned regions
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        r.region_id,
        r.name,
        r.region_type
    FROM authority_regions ar
    INNER JOIN regions r
        ON r.id = ar.region_id
    WHERE ar.authority_id = ?
      AND ar.is_active = TRUE
      AND r.is_active = TRUE
    ORDER BY r.name
");

$stmt->execute([$authorityDatabaseId]);

$regions = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * ------------------------------------------------------------
 * Fetch active service categories
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        c.category_id,
        c.name
    FROM authority_categories ac
    INNER JOIN categories c
        ON c.id = ac.category_id
    WHERE ac.authority_id = ?
      AND ac.is_active = TRUE
      AND c.is_active = TRUE
    ORDER BY c.name
");

$stmt->execute([$authorityDatabaseId]);

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * ------------------------------------------------------------
 * Fetch parent authority, if any
 * ------------------------------------------------------------
 */

$parentAuthority = null;

if ($authority['parent_authority_id'] !== null) {

    $stmt = $pdo->prepare("
        SELECT
            authority_id,
            public_name
        FROM authorities
        WHERE id = ?
          AND is_active = TRUE
        LIMIT 1
    ");

    $stmt->execute([
        $authority['parent_authority_id']
    ]);

    $parentAuthority = $stmt->fetch(PDO::FETCH_ASSOC);
}


require_once __DIR__ . '/../../includes/header.php';

?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">

                <h1>Authority Profile</h1>

                <p>
                    View your authority's registered information,
                    assigned responsibilities, and account details.
                </p>

            </div>


            <!-- Authority Information -->

            <div class="dashboard-section">

                <h2>Authority Information</h2>

                <div class="dashboard-card">

                    <p>
                        <strong>Public Name:</strong>
                        <?= htmlspecialchars($authority['public_name']) ?>
                    </p>

                    <p>
                        <strong>Authority ID:</strong>
                        <?= htmlspecialchars($authority['authority_id']) ?>
                    </p>

                    <p>
                        <strong>Registered Phone:</strong>
                        <?= htmlspecialchars($authority['phone'] ?? 'Not provided') ?>
                    </p>

                    <p>
                        <strong>Registered Email:</strong>
                        <?= htmlspecialchars($authority['email'] ?? 'Not provided') ?>
                    </p>

                    <p>
                        <strong>Account Status:</strong>
                        <?= $authority['is_active'] ? 'Active' : 'Inactive' ?>
                    </p>

                    <p>
                        <strong>Registered On:</strong>
                        <?= htmlspecialchars($authority['created_at']) ?>
                    </p>

                </div>

            </div>


            <!-- Administrative Responsibilities -->

            <div class="dashboard-section">

                <h2>Administrative Responsibilities</h2>

                <div class="dashboard-card">

                    <h3>Assigned Regions</h3>

                    <?php if (count($regions) === 0): ?>

                        <p>No active regions are assigned to this authority.</p>

                    <?php else: ?>

                        <ul>

                            <?php foreach ($regions as $region): ?>

                                <li>
                                    <strong>
                                        <?= htmlspecialchars($region['name']) ?>
                                    </strong>

                                    (<?= htmlspecialchars($region['region_type']) ?>)
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>


                <div class="dashboard-card">

                    <h3>Service Categories</h3>

                    <?php if (count($categories) === 0): ?>

                        <p>
                            No active service categories are assigned.
                        </p>

                    <?php else: ?>

                        <ul>

                            <?php foreach ($categories as $category): ?>

                                <li>
                                    <?= htmlspecialchars($category['name']) ?>
                                </li>

                            <?php endforeach; ?>

                        </ul>

                    <?php endif; ?>

                </div>


                <div class="dashboard-card">

                    <h3>Parent Authority</h3>

                    <?php if ($parentAuthority): ?>

                        <p>
                            <strong>
                                <?= htmlspecialchars($parentAuthority['public_name']) ?>
                            </strong>
                        </p>

                        <p>
                            Authority ID:
                            <?= htmlspecialchars($parentAuthority['authority_id']) ?>
                        </p>

                    <?php else: ?>

                        <p>
                            No active parent authority is currently assigned.
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Account -->

            <div class="dashboard-section">
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


                <h2>Account</h2>

                <div class="dashboard-card">

                    <p>
                        You are signed in to the InfraFix Authority Portal.
                    </p>

                    <div class="dashboard-actions">

                        <form action="../../api/authority/logout.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="profile-logout-button">Log out</button>
                        </form>

                    </div>

                </div>

            </div>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>

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
