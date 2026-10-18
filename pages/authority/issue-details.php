<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAuthorityAuth();

require_once __DIR__ . '/../../includes/db.php';

$authorityDatabaseId = getLoggedInAuthorityId();

$issueId = trim($_GET['id'] ?? '');

if ($issueId === '') {
    header('Location: dashboard.php');
    exit;
}

/*
 * Fetch the Issue.
 *
 * The authority_id and category mapping checks are intentional.
 * They prevent an authority from viewing an Issue outside its scope.
 */
$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.issue_id,
        i.category_id,
        c.name AS category_name,
        i.region_id,
        i.authority_id,
        i.latitude,
        i.longitude,
        i.address,
        i.status,
        i.created_at,
        i.updated_at,
        ips.priority_score AS priority_score,
        (
            SELECT COUNT(*)
            FROM reports r
            WHERE r.issue_id = i.id
        ) AS report_count
    FROM issues i
    INNER JOIN categories c
        ON c.id = i.category_id
    LEFT JOIN issue_priority_scores ips
        ON ips.issue_id = i.id
    WHERE i.id = ?
      AND i.authority_id = ?
      AND EXISTS (
          SELECT 1
          FROM authority_categories ac
          WHERE ac.authority_id = ?
            AND ac.category_id = i.category_id
            AND ac.is_active = TRUE
      )
    AND EXISTS (
    SELECT 1
    FROM authority_regions ar
    WHERE ar.authority_id = i.authority_id
      AND (ar.region_id = i.region_id OR ar.region_id = (SELECT p1.parent_region_id FROM regions p1 WHERE p1.id = i.region_id) OR ar.region_id = (SELECT p2.parent_region_id FROM regions p2 WHERE p2.id = (SELECT p3.parent_region_id FROM regions p3 WHERE p3.id = i.region_id)))
      AND ar.is_active = TRUE
)
    LIMIT 1
");

$stmt->execute([
    $issueId,
    $authorityDatabaseId,
    $authorityDatabaseId
]);

$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    header('Location: dashboard.php');
    exit;
}


/*
 * Fetch all reports belonging to this Issue.
 *
 * Citizen identity is deliberately not selected.
 */
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.report_id,
        r.description,
        r.reported_latitude,
        r.reported_longitude,
        r.location_verified,
        r.location_verification_distance_m,
        r.reported_at
    FROM reports r
    WHERE r.issue_id = ?
    ORDER BY r.reported_at ASC
");

$stmt->execute([$issue['id']]);

$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Fetch report photos.
 */
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM report_photos rp
    INNER JOIN reports r
        ON r.id = rp.report_id
    WHERE r.issue_id = ?
");

$stmt->execute([$issue['id']]);

$photoCount = (int) $stmt->fetchColumn();


/*
 * Fetch status history.
 */
$stmt = $pdo->prepare("
    SELECT
        ish.id,
        ish.previous_status,
        ish.new_status,
        ish.remark,
        ish.changed_at
    FROM issue_status_history ish
    WHERE ish.issue_id = ?
    ORDER BY ish.changed_at ASC
");

$stmt->execute([$issue['id']]);

$statusHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
 * Fetch resolution information if available.
 */
$stmt = $pdo->prepare("
    SELECT
        ir.resolution_id,
        ir.remark,
        ir.resolved_at,
        ir.evidence_path,
        ir.created_at
    FROM issue_resolutions ir
    WHERE ir.issue_id = ?
    ORDER BY ir.created_at DESC
    LIMIT 1
");

$stmt->execute([$issue['id']]);

$resolution = $stmt->fetch(PDO::FETCH_ASSOC);


/*
 * Anonymous citizen feedback on the latest resolution.
 */
$feedbackSummary = ['count' => 0, 'average' => null];
$feedbackComments = [];

if ($resolution) {
    $stmt = $pdo->prepare("
        SELECT rf.rating, rf.comment, rf.submitted_at
        FROM resolution_feedback rf
        INNER JOIN issue_resolutions ir ON ir.id = rf.resolution_id
        WHERE ir.resolution_id = ?
        ORDER BY rf.submitted_at DESC
    ");
    $stmt->execute([$resolution['resolution_id']]);
    $feedbackRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $feedbackSummary['count'] = count($feedbackRows);

    if ($feedbackRows) {
        $feedbackSummary['average'] =
            array_sum(array_column($feedbackRows, 'rating')) / count($feedbackRows);
        $feedbackComments = array_filter(
            $feedbackRows,
            fn($row) => !empty($row['comment'])
        );
    }
}


/*
 * Fetch priority factor information.
 */
$stmt = $pdo->prepare("
    SELECT
        pf.name AS factor_name,
        ipsf.factor_level,
        ipsf.contribution
    FROM issue_priority_score_factors ipsf
    INNER JOIN issue_priority_scores ips
        ON ips.id = ipsf.issue_priority_score_id
    INNER JOIN priority_factors pf
        ON pf.id = ipsf.factor_id
    WHERE ips.issue_id = ?
    ORDER BY pf.id ASC
");

$stmt->execute([$issue['id']]);

$priorityFactors = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';
?>

<?php require_once __DIR__ . '/../../includes/header.php'; ?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">
        <div class="page-container">

            <div class="page-header">
                <h1>Issue Details</h1>

                <p>
                    Issue ID:
                    <strong><?= htmlspecialchars($issue['issue_id']) ?></strong>
                </p>
            </div>


            <!-- Basic Information -->

            <div class="dashboard-section">
                <h2>Issue Information</h2>

                <div class="dashboard-grid">

                    <div class="dashboard-card">
                        <h3>Category</h3>
                        <p><?= htmlspecialchars($issue['category_name']) ?></p>
                    </div>

                    <div class="dashboard-card">
                        <h3>Status</h3>
                        <p><?= htmlspecialchars($issue['status']) ?></p>
                    </div>

                    <div class="dashboard-card">
                        <h3>Priority</h3>
                        <p class="dashboard-stat">
                            <?php if ($issue['priority_score'] === null): ?>
                                Unscored
                            <?php else: ?>
                                <?= number_format((float) $issue['priority_score'], 2) ?>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="dashboard-card">
                        <h3>Reports</h3>
                        <p class="dashboard-stat">
                            <?= (int) $issue['report_count'] ?>
                        </p>
                    </div>

                </div>
            </div>

            <section class="card">
                <div class="card-header">
                    <div>
                        <h2>Issue Location</h2>
                        <p>
                            Location of the reported public infrastructure issue.
                        </p>
                    </div>
                </div>

                <div
                    id="issue-map"
                    class="issue-map"
                    style="height: 400px; width: 100%;"
                    data-lat="<?= htmlspecialchars($issue['latitude']) ?>"
                    data-lng="<?= htmlspecialchars($issue['longitude']) ?>"></div>
            </section>

            <!-- Location -->

            <div class="dashboard-section">
                <h2>Location</h2>

                <div class="dashboard-card">

                    <p>
                        <strong>Latitude:</strong>
                        <?= htmlspecialchars($issue['latitude']) ?>
                    </p>

                    <p>
                        <strong>Longitude:</strong>
                        <?= htmlspecialchars($issue['longitude']) ?>
                    </p>

                    <?php if (!empty($issue['address'])): ?>
                        <p>
                            <strong>Address:</strong>
                            <?= htmlspecialchars($issue['address']) ?>
                        </p>
                    <?php endif; ?>

                </div>
            </div>


            <!-- Reports -->

            <div class="dashboard-section">
                <h2>Reports</h2>

                <div class="dashboard-card">

                    <p>
                        <strong>Total Reports:</strong>
                        <?= (int) $issue['report_count'] ?>
                    </p>

                    <?php if (count($reports) > 0): ?>

                        <p>
                            <strong>First Reported:</strong>
                            <?= htmlspecialchars($reports[0]['reported_at']) ?>
                        </p>

                        <p>
                            <strong>Latest Report:</strong>
                            <?= htmlspecialchars($reports[count($reports) - 1]['reported_at']) ?>
                        </p>

                        <hr>

                        <h3>Recent Citizen Observations</h3>

                        <?php
                        $recentDescriptions = array_reverse(
                            array_filter(
                                $reports,
                                function ($report) {
                                    return trim($report['description']) !== '';
                                }
                            )
                        );

                        $recentDescriptions = array_slice($recentDescriptions, 0, 3);
                        ?>

                        <?php if (count($recentDescriptions) === 0): ?>

                            <p>No descriptions have been submitted for this Issue.</p>

                        <?php else: ?>

                            <?php foreach ($recentDescriptions as $report): ?>

                                <div class="dashboard-card">

                                    <p>
                                        <?= nl2br(
                                            htmlspecialchars($report['description'])
                                        ) ?>
                                    </p>

                                    <small>
                                        Reported:
                                        <?= htmlspecialchars($report['reported_at']) ?>
                                    </small>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        <div class="dashboard-actions">

                            <a
                                href="reports.php?issue_id=<?= (int) $issue['id'] ?>"
                                class="btn btn-secondary">
                                View All Reports
                            </a>

                        </div>

                    <?php else: ?>

                        <p>No reports have been submitted for this Issue.</p>

                    <?php endif; ?>

                </div>
            </div>


            <!-- Evidence -->

            <div class="dashboard-section">

                <h2>Evidence</h2>

                <div class="dashboard-card">

                    <?php if ($photoCount === 0): ?>

                        <p>No photos have been attached to this Issue.</p>

                    <?php else: ?>

                        <p>
                            <strong>Total Evidence:</strong>
                            <?= $photoCount ?> photo(s)
                        </p>

                        <p>
                            Photos submitted by citizens are available for review.
                        </p>

                        <div class="dashboard-actions">

                            <a
                                href="evidence.php?issue_id=<?= (int) $issue['id'] ?>"
                                class="btn btn-secondary">
                                View Evidence
                            </a>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Priority Factors -->

            <div class="dashboard-section">
                <h2>Priority</h2>

                <div class="dashboard-card">

                    <p>
                        <strong>Current Priority Score:</strong>
                        <?php if ($issue['priority_score'] === null): ?>
                            Unscored
                        <?php else: ?>
                            <?= number_format((float) $issue['priority_score'], 2) ?>
                        <?php endif; ?>
                    </p>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="recalculate-priority-button">
                        Recalculate Priority
                    </button>
                    <p id="priority-recalculation-message" role="status" aria-live="polite"></p>

                    <?php if ($issue['priority_score'] === null): ?>
                        <p>This issue does not have a saved priority score yet.</p>
                    <?php elseif (count($priorityFactors) > 0): ?>

                        <table>
                            <thead>
                                <tr>
                                    <th>Factor</th>
                                    <th>Level</th>
                                    <th>Contribution</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($priorityFactors as $factor): ?>

                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($factor['factor_name']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($factor['factor_level']) ?>
                                        </td>

                                        <td>
                                            <?= number_format(
                                                (float) $factor['contribution'],
                                                2
                                            ) ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>

                    <?php else: ?>

                        <p>No priority factor data is available.</p>

                    <?php endif; ?>

                </div>
            </div>

            <!-- Status Management -->

            <div class="dashboard-section">

                <h2>Manage Issue</h2>

                <div class="dashboard-card">

                    <p>
                        <strong>Current Status:</strong>
                        <?= htmlspecialchars($issue['status']) ?>
                    </p>


                    <?php if ($issue['status'] === 'Open'): ?>

                        <div class="dashboard-actions">

                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="showStatusForm('progress-form')">
                                Start Progress
                            </button>

                            <button
                                type="button"
                                class="btn btn-secondary"
                                onclick="showStatusForm('reject-form')">
                                Reject Issue
                            </button>

                        </div>


                        <!-- Start Progress Form -->

                        <div
                            id="progress-form"
                            class="status-form"
                            style="display: none;">

                            <hr>

                            <h3>Start Progress</h3>

                            <p>
                                Optionally provide a remark about beginning work
                                on this Issue.
                            </p>

                            <textarea
                                id="progress-remark"
                                rows="4"
                                maxlength="500"
                                placeholder="Enter an optional remark..."></textarea>

                            <div class="dashboard-actions">

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    onclick="submitStatusChange(
                            'In Progress',
                            'progress-remark'
                        )">
                                    Start Progress
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="hideStatusForms()">
                                    Cancel
                                </button>

                            </div>

                        </div>


                        <!-- Reject Form -->

                        <div
                            id="reject-form"
                            class="status-form"
                            style="display: none;">

                            <hr>

                            <h3>Reject Issue</h3>

                            <p>
                                A rejection reason is required.
                            </p>

                            <textarea
                                id="reject-remark"
                                rows="4"
                                maxlength="500"
                                placeholder="Enter the reason for rejection..."></textarea>

                            <div class="dashboard-actions">

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="submitStatusChange(
                            'Rejected',
                            'reject-remark'
                        )">
                                    Reject Issue
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="hideStatusForms()">
                                    Cancel
                                </button>

                            </div>

                        </div>


                    <?php elseif ($issue['status'] === 'In Progress'): ?>

                        <div class="dashboard-actions">

                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="showStatusForm('resolve-form')">
                                Mark as Resolved
                            </button>

                        </div>


                        <!-- Resolve Form -->

                        <div
                            id="resolve-form"
                            class="status-form"
                            style="display: none;">

                            <hr>

                            <h3>Mark as Resolved</h3>

                            <p>
                                Provide a resolution remark describing how
                                the Issue was addressed.
                            </p>

                            <textarea
                                id="resolve-remark"
                                rows="4"
                                maxlength="500"
                                placeholder="Describe the resolution..."></textarea>

                            <div class="dashboard-actions">

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    onclick="submitStatusChange(
                            'Resolved',
                            'resolve-remark'
                        )">
                                    Mark as Resolved
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="hideStatusForms()">
                                    Cancel
                                </button>

                            </div>

                        </div>


                    <?php elseif ($issue['status'] === 'Resolved'): ?>

                        <div class="dashboard-actions">

                            <button
                                type="button"
                                class="btn btn-primary"
                                onclick="showStatusForm('close-form')">
                                Close Issue
                            </button>

                        </div>


                        <!-- Close Form -->

                        <div
                            id="close-form"
                            class="status-form"
                            style="display: none;">

                            <hr>

                            <h3>Close Issue</h3>

                            <p>
                                Optionally provide a closure remark.
                            </p>

                            <textarea
                                id="close-remark"
                                rows="4"
                                maxlength="500"
                                placeholder="Enter an optional closure remark..."></textarea>

                            <div class="dashboard-actions">

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    onclick="submitStatusChange(
                            'Closed',
                            'close-remark'
                        )">
                                    Close Issue
                                </button>

                                <button
                                    type="button"
                                    class="btn btn-secondary"
                                    onclick="hideStatusForms()">
                                    Cancel
                                </button>

                            </div>

                        </div>


                    <?php elseif ($issue['status'] === 'Closed'): ?>

                        <p>
                            This Issue has been closed. No further status
                            changes are available.
                        </p>


                    <?php elseif ($issue['status'] === 'Rejected'): ?>

                        <p>
                            This Issue has been rejected. No further status
                            changes are available.
                        </p>

                    <?php endif; ?>

                </div>

            </div>

            <!-- Status History -->

            <div class="dashboard-section">
                <h2>Status History</h2>

                <?php if (count($statusHistory) === 0): ?>

                    <div class="dashboard-card">
                        <p>No status history is available.</p>
                    </div>

                <?php else: ?>

                    <div class="table-container">

                        <table>

                            <thead>
                                <tr>
                                    <th>Previous Status</th>
                                    <th>New Status</th>
                                    <th>Remark</th>
                                    <th>Changed At</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($statusHistory as $history): ?>

                                    <tr>

                                        <td>
                                            <?= htmlspecialchars(
                                                $history['previous_status'] ?? '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($history['new_status']) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $history['remark'] ?? '—'
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($history['changed_at']) ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <!-- Resolution -->

            <?php if ($resolution): ?>

                <div class="dashboard-section">

                    <h2>Resolution</h2>

                    <div class="dashboard-card">

                        <p>
                            <strong>Resolution ID:</strong>
                            <?= htmlspecialchars($resolution['resolution_id']) ?>
                        </p>

                        <p>
                            <strong>Resolved At:</strong>
                            <?= htmlspecialchars($resolution['resolved_at']) ?>
                        </p>

                        <?php if (!empty($resolution['remark'])): ?>

                            <p>
                                <strong>Remark:</strong>
                                <?= htmlspecialchars($resolution['remark']) ?>
                            </p>

                        <?php endif; ?>

                        <p>
                            <strong>Citizen feedback:</strong>
                            <?php if ($feedbackSummary['count'] > 0): ?>
                                <?= number_format($feedbackSummary['average'], 1) ?> / 5
                                from <?= (int) $feedbackSummary['count'] ?> citizen(s)
                            <?php else: ?>
                                none yet
                            <?php endif; ?>
                        </p>

                        <?php foreach ($feedbackComments as $feedbackRow): ?>
                            <p>
                                &ldquo;<?= htmlspecialchars($feedbackRow['comment']) ?>&rdquo;
                                (<?= (int) $feedbackRow['rating'] ?>/5)
                            </p>
                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- Navigation -->

            <div class="dashboard-section">

                <div class="dashboard-actions">

                    <a
                        href="dashboard.php"
                        class="btn btn-secondary">
                        Back to Dashboard
                    </a>

                </div>

            </div>

        </div>

        <script>
            const csrfToken = <?= json_encode(csrfToken()) ?>;

            async function recalculatePriority() {
                const button = document.getElementById('recalculate-priority-button');
                const message = document.getElementById('priority-recalculation-message');

                if (!button || !message) return;

                button.disabled = true;
                message.textContent = 'Recalculating priority…';

                const formData = new FormData();
                formData.append('issue_id', '<?= (int) $issue['id'] ?>');
                formData.append('csrf_token', csrfToken);

                try {
                    const response = await fetch(
                        '../../api/authority/recalculate-priority.php', {
                            method: 'POST',
                            body: formData
                        }
                    );
                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        message.textContent = data.message || 'Priority recalculation failed.';
                        return;
                    }

                    message.textContent = data.message;
                    window.location.reload();
                } catch (error) {
                    console.error(error);
                    message.textContent = 'An unexpected error occurred while recalculating priority.';
                } finally {
                    button.disabled = false;
                }
            }

            function hideStatusForms() {
                const forms = document.querySelectorAll('.status-form');

                forms.forEach(function(form) {
                    form.style.display = 'none';
                });
            }


            function showStatusForm(formId) {
                hideStatusForms();

                const form = document.getElementById(formId);

                if (form) {
                    form.style.display = 'block';
                }
            }


            async function submitStatusChange(newStatus, remarkFieldId) {
                const remarkField = document.getElementById(remarkFieldId);

                if (!remarkField) {
                    return;
                }

                const remark = remarkField.value.trim();


                /*
                 * Client-side validation.
                 */

                if (newStatus === 'Rejected' && remark === '') {

                    alert('A rejection reason is required.');

                    remarkField.focus();

                    return;
                }


                if (newStatus === 'Resolved' && remark === '') {

                    alert('A resolution remark is required.');

                    remarkField.focus();

                    return;
                }


                const confirmed = confirm(
                    'Are you sure you want to change the Issue status to "' +
                    newStatus +
                    '"?'
                );

                if (!confirmed) {
                    return;
                }


                const formData = new FormData();

                formData.append(
                    'issue_id',
                    '<?= (int) $issue['id'] ?>'
                );

                formData.append('csrf_token', csrfToken);

                formData.append(
                    'new_status',
                    newStatus
                );

                formData.append(
                    'remark',
                    remark
                );


                try {

                    const response = await fetch(
                        '../../api/authority/update-issue-status.php', {
                            method: 'POST',
                            body: formData
                        }
                    );


                    const data = await response.json();


                    if (!data.success) {

                        alert(data.message || 'Unable to update Issue status.');

                        return;
                    }


                    alert(data.message);


                    window.location.reload();

                } catch (error) {

                    console.error(error);

                    alert(
                        'An unexpected error occurred while updating the Issue status.'
                    );
                }
            }

            document.addEventListener('DOMContentLoaded', function() {

                const recalculateButton = document.getElementById('recalculate-priority-button');
                if (recalculateButton) {
                    recalculateButton.addEventListener('click', recalculatePriority);
                }

                const mapElement = document.getElementById('issue-map');

                if (!mapElement) {
                    return;
                }

                const latitude = parseFloat(
                    mapElement.dataset.lat
                );

                const longitude = parseFloat(
                    mapElement.dataset.lng
                );

                if (
                    Number.isNaN(latitude) ||
                    Number.isNaN(longitude)
                ) {
                    mapElement.innerHTML =
                        '<p>Issue location is unavailable.</p>';

                    return;
                }

                const map = L.map('issue-map').setView(
                    [latitude, longitude],
                    17
                );

                L.tileLayer(
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }
                ).addTo(map);

                const marker = L.marker([
                    latitude,
                    longitude
                ]).addTo(map);

                marker.bindPopup(
                    '<strong><?= htmlspecialchars($issue['issue_id']) ?></strong><br>' +
                    'Issue location'
                ).openPopup();

            });
        </script>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>