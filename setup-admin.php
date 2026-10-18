<?php

/*
 * One-time admin creation page.
 *
 * 1. Change SETUP_KEY below to a long random string BEFORE uploading.
 * 2. Open /setup-admin.php, enter the key and the new admin credentials.
 * 3. DELETE this file from the server immediately afterwards.
 *
 * It refuses to run once any admin account exists.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// Replace everything between the quotes with a long random string (16+ characters).
const SETUP_KEY = 'CHANGE-THIS-BEFORE-UPLOAD';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$message = '';
$done = false;
$locked = false;

if (strlen(SETUP_KEY) < 16 || stripos(SETUP_KEY, 'CHANGE-THIS') === 0) {
    $locked = true;
    $message = 'Set SETUP_KEY in setup-admin.php to a random string of at least 16 characters first.';
} else {
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();

    if ($adminCount > 0) {
        $locked = true;
        $message = 'An admin account already exists. Delete this file.';
    }
}

if (!$locked && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');
    $key = (string) ($_POST['setup_key'] ?? '');

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $message = 'Session expired. Reload the page and try again.';
    } elseif (!hash_equals(SETUP_KEY, $key)) {
        $message = 'Wrong setup key.';
    } elseif (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        $message = 'Username: 3-50 letters, digits, dot, dash or underscore.';
    } elseif (strlen($password) < 10) {
        $message = 'Use a password of at least 10 characters.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO admins (admin_id, username, password_hash, is_active)
             VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([
            'ADMIN-' . strtoupper(bin2hex(random_bytes(4))),
            $username,
            password_hash($password, PASSWORD_DEFAULT)
        ]);

        $done = true;
        $message = 'Admin created. Delete setup-admin.php from the server now.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InfraFix admin setup</title>
</head>
<body style="font-family: sans-serif; max-width: 420px; margin: 2rem auto; padding: 0 1rem;">
    <h1>InfraFix admin setup</h1>

    <?php if ($message !== ''): ?>
        <p><strong><?= h($message) ?></strong></p>
    <?php endif; ?>

    <?php if (!$locked && !$done): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <p><label>Setup key<br>
                <input type="password" name="setup_key" required style="width:100%"></label></p>
            <p><label>Admin username<br>
                <input type="text" name="username" required style="width:100%"></label></p>
            <p><label>Password (10+ characters)<br>
                <input type="password" name="password" required style="width:100%"></label></p>
            <p><label>Confirm password<br>
                <input type="password" name="confirm" required style="width:100%"></label></p>
            <button type="submit">Create admin</button>
        </form>
    <?php endif; ?>
</body>
</html>
