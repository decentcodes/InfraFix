<?php

// Copy this file to includes/db.php and set credentials for your environment.
// Do not commit includes/db.php or place real credentials in this template.

date_default_timezone_set('Asia/Kolkata');

$host = 'HOST_NAME';
$dbname = 'DATABASE_NAME';
$username = 'USER_NAME';
$password = 'PASSWORD';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Make MySQL's NOW()/CURRENT_TIMESTAMP match the PHP timezone above.
    // Hosting providers often run the database server in another timezone
    // (for example US Pacific), which shifts every saved timestamp.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    // Do not disclose database details to visitors.
    error_log('InfraFix database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Application database is unavailable.');
}
