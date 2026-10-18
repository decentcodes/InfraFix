<?php

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';


/*
 * ------------------------------------------------------------
 * Request filter
 * ------------------------------------------------------------
 */

$status = $_GET['status'] ?? '';

$allowedStatuses = [
    'Pending',
    'Approved',
    'Rejected'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}


/*
 * ------------------------------------------------------------
 * Fetch authority access requests
 * ------------------------------------------------------------
 */

$sql = "
    SELECT
        id,
        request_id,
        public_name,
        phone,
        email,
        status,
        created_at
    FROM authority_access_requests
";

$params = [];

if ($status !== '') {

    $sql .= "
        WHERE status = ?
    ";

    $params[] = $status;
}

$sql .= "
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../../includes/header.php';

?>

<div class="page-container">

    <div class="page-header">

        <h1>Authority Access Requests</h1>

        <p>
            Review authority access applications submitted to InfraFix.
        </p>

    </div>


    <!-- Filters -->

    <div class="dashboard-section">

        <h2>Filter Requests</h2>

        <div class="dashboard-actions">

            <a
                href="requests.php"
                class="btn <?= $status === '' ? 'btn-primary' : 'btn-secondary' ?>"
            >
                All
            </a>

            <a
                href="requests.php?status=Pending"
                class="btn <?= $status === 'Pending' ? 'btn-primary' : 'btn-secondary' ?>"
            >
                Pending
            </a>

            <a
                href="requests.php?status=Approved"
                class="btn <?= $status === 'Approved' ? 'btn-primary' : 'btn-secondary' ?>"
            >
                Approved
            </a>

            <a
                href="requests.php?status=Rejected"
                class="btn <?= $status === 'Rejected' ? 'btn-primary' : 'btn-secondary' ?>"
            >
                Rejected
            </a>

        </div>

    </div>


    <!-- Requests -->

    <div class="dashboard-section">

        <h2>
            <?= $status !== '' ? htmlspecialchars($status) . ' Requests' : 'All Requests' ?>
        </h2>

        <?php if (count($requests) === 0): ?>

            <p>
                No authority access requests found.
            </p>

        <?php else: ?>

            <div class="table-container">

                <table>

                    <thead>

                        <tr>
                            <th>Request ID</th>
                            <th>Authority</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($requests as $request): ?>

                            <tr>

                                <td>
                                    <?= htmlspecialchars($request['request_id']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['public_name']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['email']) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request['phone']) ?>
                                </td>

                                
<td>
    <span class="status-badge status-<?= strtolower(htmlspecialchars($request['status'])) ?>">
        <?= htmlspecialchars($request['status']) ?>
    </span>
</td>


                                <td>
                                    <?= htmlspecialchars($request['created_at']) ?>
                                </td>

                                <td>

                                    <a
                                        href="request-details.php?id=<?= (int) $request['id'] ?>"
                                        class="btn btn-secondary"
                                    >
                                        View
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>


    <!-- Navigation -->

    <div class="dashboard-section">

        <a
            href="dashboard.php"
            class="btn btn-secondary"
        >
            Back to Dashboard
        </a>

    </div>

</div>


<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>