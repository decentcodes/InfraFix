<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuthorityAuth();

$authorityDatabaseId = getLoggedInAuthorityId();

$issueId = filter_input(
    INPUT_GET,
    'issue_id',
    FILTER_VALIDATE_INT
);

if (!$issueId) {
    die('Invalid Issue.');
}

/*
 * Fetch the Issue and verify that:
 * 1. It belongs to the logged-in Authority.
 * 2. The Authority is currently mapped to the Issue category.
 */
$stmt = $pdo->prepare("
    SELECT
        i.id,
        i.issue_id,
        c.name AS category_name
    FROM issues i
    INNER JOIN categories c
        ON c.id = i.category_id
    INNER JOIN authority_categories ac
        ON ac.authority_id = i.authority_id
       AND ac.category_id = i.category_id
       AND ac.is_active = TRUE
    WHERE i.id = ?
      AND i.authority_id = ?
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
    $authorityDatabaseId
]);

$issue = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$issue) {
    die('Issue not found or access denied.');
}

/*
 * Fetch evidence photos belonging to reports
 * associated with this Issue.
 *
 * Citizen identity is intentionally not fetched.
 */
$stmt = $pdo->prepare("
    SELECT
        rp.id,
        rp.report_photo_id,
        rp.file_path,
        rp.original_filename,
        rp.mime_type,
        rp.file_size,
        rp.uploaded_at,
        r.report_id
    FROM report_photos rp
    INNER JOIN reports r
        ON r.id = rp.report_id
    WHERE r.issue_id = ?
    ORDER BY rp.uploaded_at DESC
");

$stmt->execute([$issueId]);

$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$photoCount = count($photos);

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="app-layout">

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <section class="app-content">

        <div class="page-container">

            <div class="page-header">
                <div>
                    <h1>Issue Evidence</h1>
                    <p>
                        <?= htmlspecialchars($issue['issue_id']) ?>
                        ·
                        <?= htmlspecialchars($issue['category_name']) ?>
                    </p>
                </div>

                <a
                    href="issue-details.php?id=<?= (int) $issue['id'] ?>"
                    class="btn btn-secondary">
                    Back to Issue
                </a>
            </div>

            <section class="card">

                <div class="card-header">
                    <div>
                        <h2>Evidence Photos</h2>
                        <p>
                            <?= $photoCount ?>
                            <?= $photoCount === 1 ? 'photo' : 'photos' ?>
                            attached to reports for this Issue.
                        </p>
                    </div>
                </div>

                <?php if (empty($photos)): ?>

                    <div class="empty-state">
                        <p>No evidence photos have been submitted for this Issue.</p>
                    </div>

                <?php else: ?>

                    <div class="evidence-grid">

                        <?php foreach ($photos as $photo): ?>

                            <article class="evidence-card">

                                <div class="evidence-image-wrapper">
                                    <img
                                        src="../../<?= htmlspecialchars($photo['file_path']) ?>"
                                        alt="Evidence submitted with <?= htmlspecialchars($photo['report_id']) ?>"
                                        class="evidence-image">
                                </div>

                                <div class="evidence-info">

                                    <div>
                                        <strong>Report</strong>
                                        <span>
                                            <?= htmlspecialchars($photo['report_id']) ?>
                                        </span>
                                    </div>

                                    <div>
                                        <strong>Filename</strong>
                                        <span>
                                            <?= htmlspecialchars($photo['original_filename']) ?>
                                        </span>
                                    </div>

                                    <div>
                                        <strong>Uploaded</strong>
                                        <span>
                                            <?= htmlspecialchars($photo['uploaded_at']) ?>
                                        </span>
                                    </div>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </section>

</main>

<?php require_once __DIR__ . '/../../includes/mobile-nav.php'; ?>