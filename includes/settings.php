<?php

require_once __DIR__ . '/bootstrap.php';

/*
 * Small key/value settings store (table: app_settings).
 * Used for demo-friendly switches such as OTP mode and the current
 * weather condition, so they can change without editing code.
 */

function getAppSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare(
            'SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1'
        );
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value === false ? $default : (string) $value;
    } catch (Throwable $e) {
        // Table missing (schema not migrated yet): fall back to the default.
        error_log('InfraFix settings read failed: ' . $e->getMessage());
        return $default;
    }
}

function setAppSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}
