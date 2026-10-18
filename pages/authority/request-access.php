<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/header.php';

$regionsStmt = $pdo->query("
    SELECT id, name
    FROM regions
    WHERE is_active = TRUE
    ORDER BY name
");
$regions = $regionsStmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesStmt = $pdo->query("
    SELECT id, name
    FROM categories
    WHERE is_active = TRUE
    ORDER BY name
");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$authoritiesStmt = $pdo->query("
    SELECT id, public_name
    FROM authorities
    WHERE is_active = TRUE
    ORDER BY public_name
");
$authorities = $authoritiesStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="auth-page">

    <div class="auth-card">

        <h1>Request Authority Access</h1>

        <p class="auth-subtitle">
            Submit your organization's details for InfraFix authority access.
            Your request will be reviewed by an InfraFix administrator.
        </p>

        <form id="authority-request-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

            <div class="form-group">
                <label for="public-name">Authority Name</label>
                <input
                    type="text"
                    id="public-name"
                    name="public_name"
                    placeholder="e.g. Municipal Corporation of Delhi"
                    maxlength="150"
                    required>
            </div>

            <div class="form-group">
                <label for="phone">Official Phone</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="Enter official phone number"
                    maxlength="15"
                    autocomplete="tel"
                    required>
            </div>

            <div class="form-group">
                <label for="email">Official Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="official@example.gov.in"
                    maxlength="255"
                    autocomplete="email"
                    required>
            </div>

            <div class="form-group">
                <label for="region">Proposed Administrative Region</label>

                <select id="region" name="requested_region_id" required>
                    <option value="">Select region</option>

                    <?php foreach ($regions as $region): ?>
                        <option value="<?= (int) $region['id'] ?>">
                            <?= htmlspecialchars($region['name']) ?>
                        </option>
                    <?php endforeach; ?>

                </select>
            </div>

            <div class="form-group">

                <label>Proposed Service Categories</label>

                <div class="checkbox-group">

                    <?php foreach ($categories as $category): ?>

                        <label>
                            <input
                                type="checkbox"
                                name="category_ids[]"
                                value="<?= (int) $category['id'] ?>">
                            <?= htmlspecialchars($category['name']) ?>
                        </label>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="form-group">

                <label for="parent-authority">
                    Proposed Parent Authority
                </label>

                <select
                    id="parent-authority"
                    name="requested_parent_authority_id">
                    <option value="">None / Not Applicable</option>

                    <?php foreach ($authorities as $authority): ?>

                        <option value="<?= (int) $authority['id'] ?>">
                            <?= htmlspecialchars($authority['public_name']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

                <small>
                    The final authority hierarchy will be determined during review.
                </small>

            </div>

            <div class="form-group">

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    required>

            </div>

            <div class="form-group">

                <label for="confirm-password">Confirm Password</label>

                <input
                    type="password"
                    id="confirm-password"
                    name="confirm_password"
                    autocomplete="new-password"
                    required>

            </div>

            <button
                type="submit"
                class="btn btn-primary">
                Submit Access Request
            </button>

            <p
                id="request-message"
                class="form-message"></p>

        </form>

    </div>

</div>

<script>
document.getElementById('authority-request-form').addEventListener('submit', async function (event) {

    event.preventDefault();

    const form = event.target;
    const message = document.getElementById('request-message');

    message.textContent = '';

    const formData = new FormData(form);

    try {

        const response = await fetch('../../api/authority/request-access.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!data.success) {
            message.textContent = data.message;
            return;
        }

        message.textContent =
            data.message + ' Request ID: ' + data.request_id;

        form.reset();

    } catch (error) {

        message.textContent =
            'Something went wrong. Please try again.';
    }

});
</script>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>