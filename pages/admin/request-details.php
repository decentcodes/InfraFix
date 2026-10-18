<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';


/*
 * ------------------------------------------------------------
 * Get request ID
 * ------------------------------------------------------------
 */

$requestDatabaseId = (int) ($_GET['id'] ?? 0);

if ($requestDatabaseId <= 0) {
    header('Location: requests.php');
    exit;
}


/*
 * ------------------------------------------------------------
 * Fetch authority access request
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        aar.id,
        aar.request_id,
        aar.public_name,
        aar.phone,
        aar.email,
        aar.requested_region_id,
        aar.requested_parent_authority_id,
        aar.status,
        aar.phone_verified_at,
        aar.email_verified_at,
        aar.rejection_reason,
        aar.created_at,
        aar.updated_at,

        r.name AS region_name,

        parent.public_name AS parent_authority_name

    FROM authority_access_requests aar

    LEFT JOIN regions r
        ON r.id = aar.requested_region_id

    LEFT JOIN authorities parent
        ON parent.id = aar.requested_parent_authority_id

    WHERE aar.id = ?

    LIMIT 1
");

$stmt->execute([$requestDatabaseId]);

$request = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$request) {
    header('Location: requests.php');
    exit;
}


/*
 * ------------------------------------------------------------
 * Fetch requested service categories
 * ------------------------------------------------------------
 */

$stmt = $pdo->prepare("
    SELECT
        c.name

    FROM authority_access_request_categories arcc

    INNER JOIN categories c
        ON c.id = arcc.category_id

    WHERE arcc.request_id = ?

    ORDER BY c.name
");

$stmt->execute([$requestDatabaseId]);

$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);


require_once __DIR__ . '/../../includes/header.php';

?>

<div class="page-container">

    <div class="page-header">

        <h1>Authority Access Request</h1>

        <p>
            Review the details submitted by the authority.
        </p>

    </div>


    <!-- Request Information -->

    <div class="dashboard-section">

        <h2>Request Information</h2>

        <div class="dashboard-card">

            <p>
                <strong>Request ID:</strong>
                <?= htmlspecialchars($request['request_id']) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <span class="status-badge status-<?= strtolower(htmlspecialchars($request['status'])) ?>">
                    <?= htmlspecialchars($request['status']) ?>
                </span>
            </p>

            <p>
                <strong>Submitted:</strong>
                <?= htmlspecialchars($request['created_at']) ?>
            </p>

        </div>

    </div>


    <!-- Authority Information -->

    <div class="dashboard-section">

        <h2>Authority Information</h2>

        <div class="dashboard-card">

            <p>
                <strong>Public Authority Name:</strong>
                <?= htmlspecialchars($request['public_name']) ?>
            </p>

            <p>
                <strong>Official Phone:</strong>
                <?= htmlspecialchars($request['phone']) ?>
            </p>

            <p>
                <strong>Official Email:</strong>
                <?= htmlspecialchars($request['email']) ?>
            </p>

        </div>

    </div>


    <!-- Requested Administrative Scope -->

    <div class="dashboard-section">

        <h2>Requested Administrative Scope</h2>

        <div class="dashboard-card">

            <p>
                <strong>Requested Region:</strong>
                <?= htmlspecialchars($request['region_name'] ?? 'Not specified') ?>
            </p>


            <p>
                <strong>Proposed Parent Authority:</strong>

                <?=
                htmlspecialchars(
                    $request['parent_authority_name']
                        ?? 'None / Not Applicable'
                )
                ?>

            </p>


            <p>
                <strong>Requested Service Categories:</strong>
            </p>



            <?php if (count($categories) > 0): ?>
                <ul class="admin-category-list">
                    <?php foreach ($categories as $category): ?>
                        <li>
                            <?= htmlspecialchars($category) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No service categories were requested.</p>
            <?php endif; ?>


        </div>

    </div>


    <!-- Verification -->

    <div class="dashboard-section">

        <h2>Contact Verification</h2>

        <div class="dashboard-card">

            <p>
                <strong>Phone Verification:</strong>
                <?php if ($request['phone_verified_at']): ?>
                    <span class="status-badge status-approved">Verified</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Not verified</span>
                <?php endif; ?>
            </p>


            <p>
                <strong>Email Verification:</strong>
                <?php if ($request['email_verified_at']): ?>
                    <span class="status-badge status-approved">Verified</span>
                <?php else: ?>
                    <span class="status-badge status-pending">Not verified</span>
                <?php endif; ?>
            </p>

        </div>

    </div>


    <!-- Rejection Information -->

    <?php if ($request['status'] === 'Rejected'): ?>

        <div class="dashboard-section">

            <h2>Rejection Information</h2>

            <div class="dashboard-card">

                <p>
                    <strong>Reason:</strong>
                    <?= htmlspecialchars(
                        $request['rejection_reason'] ?: 'No reason recorded.'
                    ) ?>
                </p>

            </div>

        </div>

    <?php endif; ?>


    <!-- Admin Decision -->

    <?php if ($request['status'] === 'Pending'): ?>

        <div class="dashboard-section">

            <h2>Admin Decision</h2>

            <div class="dashboard-card">

                <p>
                    This request is awaiting administrative review.
                </p>

                <div class="dashboard-actions admin-decision-actions">

                    <button
                        type="button"
                        class="btn btn-primary"
                        id="approve-request">
                        Approve Request
                    </button>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="reject-request">
                        Reject Request
                    </button>

                </div>

                <p
                    id="decision-message"
                    class="form-message admin-decision-message"
                    aria-live="polite"></p>

            </div>

        </div>

    <?php endif; ?>


    <!-- Navigation -->

    <div class="dashboard-section">

        <a
            href="requests.php"
            class="btn btn-secondary">
            Back to Requests
        </a>

    </div>

</div>

<script>
    const approveButton = document.getElementById('approve-request');
    const rejectButton = document.getElementById('reject-request');
    const decisionMessage = document.getElementById('decision-message');

    const requestId = <?= (int) $request['id'] ?>;
    const csrfToken = <?= json_encode(csrfToken()) ?>;


    /*
     * ------------------------------------------------------------
     * Handle request action
     * ------------------------------------------------------------
     */

    async function handleRequest(action, rejectionReason = '') {

        decisionMessage.textContent = '';
        approveButton.disabled = true;
        rejectButton.disabled = true;

        const formData = new FormData();

        formData.append('csrf_token', csrfToken);
        formData.append('request_id', requestId);
        formData.append('action', action);

        if (action === 'reject') {
            formData.append('rejection_reason', rejectionReason);
        }

        try {

            const response = await fetch(
                '../../api/admin/handle-request.php', {
                    method: 'POST',
                    body: formData
                }
            );

            const data = await response.json();


            if (!data.success) {

                decisionMessage.textContent = data.message;

                approveButton.disabled = false;
                rejectButton.disabled = false;

                return;
            }


            decisionMessage.textContent = data.message;


            /*
             * Give the Admin a moment to see the
             * successful result before returning
             * to the request list.
             */

            setTimeout(function() {

                window.location.href = 'requests.php';

            }, 1200);


        } catch (error) {

            decisionMessage.textContent =
                'Something went wrong. Please try again.';

            approveButton.disabled = false;
            rejectButton.disabled = false;
        }

    }


    /*
     * ------------------------------------------------------------
     * Approve
     * ------------------------------------------------------------
     */

    if (approveButton) {

        approveButton.addEventListener('click', function() {

            const confirmed = confirm(
                'Are you sure you want to approve this authority access request?'
            );

            if (!confirmed) {
                return;
            }

            handleRequest('approve');

        });

    }


    /*
     * ------------------------------------------------------------
     * Reject
     * ------------------------------------------------------------
     */

    if (rejectButton) {

        rejectButton.addEventListener('click', function() {

            const rejectionReason = prompt(
                'Please enter the reason for rejecting this request:'
            );


            /*
             * Cancelled prompt
             */

            if (rejectionReason === null) {
                return;
            }


            const trimmedReason = rejectionReason.trim();


            if (trimmedReason === '') {

                decisionMessage.textContent =
                    'A rejection reason is required.';

                return;
            }


            handleRequest(
                'reject',
                trimmedReason
            );

        });

    }
</script>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>