<?php

/*
 * Admin-only database backup.
 *
 * Streams a complete SQL dump (structure + data of every table) to the
 * admin's browser as a downloaded .sql file. Nothing is stored on the
 * server, so the backup always ends up on the admin's own computer.
 *
 * Written with plain PDO because shared hosts usually do not allow
 * mysqldump or shell commands.
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAdminAuth();

require_once __DIR__ . '/../../includes/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the "Download database backup" button on the Settings page.');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Your session token is missing or expired. Go back, refresh the page and try again.');
}

if (function_exists('set_time_limit')) {
    @set_time_limit(55);
}

function quoteIdentifier($name)
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/*
 * Remove collation names that only one database brand understands
 * (MariaDB 11 and MySQL 8 each default to their own), so the backup
 * restores on either one.
 */
function portableCreateStatement($sql)
{
    return preg_replace(
        '/\s+COLLATE[= ]\s*utf8mb4_(?:uca1400|0900)_\w+/i',
        '',
        $sql
    );
}

try {

    $tables = $pdo
        ->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")
        ->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {

    error_log('InfraFix backup failed to list tables: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not read the database.');
}

$filename = 'infrafix-backup-' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo "-- InfraFix database backup\n";
echo '-- Created: ' . date('Y-m-d H:i:s') . " (Asia/Kolkata)\n";
echo '-- Tables: ' . count($tables) . "\n";
echo "-- Restore: import into an EMPTY database with phpMyAdmin or the mysql\n";
echo "-- command line. Do not open this file in MySQL Workbench (very long lines).\n";
echo "-- The last line of a complete backup is: -- Backup complete\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET time_zone = '+05:30';\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

try {

    foreach ($tables as $table) {

        $quotedTable = quoteIdentifier($table);

        $createRow = $pdo
            ->query('SHOW CREATE TABLE ' . $quotedTable)
            ->fetch(PDO::FETCH_NUM);

        echo '-- Table ' . $table . "\n";
        echo 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n";
        echo portableCreateStatement($createRow[1]) . ";\n\n";

        $rows = $pdo->query('SELECT * FROM ' . $quotedTable, PDO::FETCH_ASSOC);

        $columns = null;
        $batch = [];

        foreach ($rows as $row) {

            if ($columns === null) {
                $columns = implode(
                    ', ',
                    array_map('quoteIdentifier', array_keys($row))
                );
            }

            $values = [];

            foreach ($row as $value) {
                $values[] = $value === null
                    ? 'NULL'
                    : $pdo->quote((string) $value);
            }

            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) >= 100) {
                echo 'INSERT INTO ' . $quotedTable
                    . ' (' . $columns . ") VALUES\n"
                    . implode(",\n", $batch) . ";\n";
                $batch = [];
            }
        }

        if ($batch) {
            echo 'INSERT INTO ' . $quotedTable
                . ' (' . $columns . ") VALUES\n"
                . implode(",\n", $batch) . ";\n";
        }

        echo "\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n\n";
    echo "-- Backup complete\n";

} catch (Throwable $e) {

    error_log('InfraFix backup failed: ' . $e->getMessage());

    // The download has already started; mark the file as unusable.
    echo "\n-- BACKUP INCOMPLETE: an error stopped the export. Do not rely on this file.\n";
}
