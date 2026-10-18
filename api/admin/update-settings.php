<?php

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/priority.php';

// Stray PHP warnings must never corrupt the JSON response.
ini_set('display_errors', '0');

header('Content-Type: application/json');

function settingsResponse(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

if (!isAdminLoggedIn()) {
    settingsResponse(401, ['success' => false, 'message' => 'Admin login required.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    settingsResponse(405, ['success' => false, 'message' => 'POST is required.']);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    settingsResponse(403, [
        'success' => false,
        'message' => 'Your session token is missing or expired. Refresh the page and try again.'
    ]);
}

$otpMode = (string) ($_POST['otp_mode'] ?? '');
$weatherMode = (string) ($_POST['weather_mode'] ?? '');
$weatherCondition = (string) ($_POST['weather_condition'] ?? '');

if (!in_array($otpMode, ['demo', 'off'], true)
    || !in_array($weatherMode, ['manual', 'auto'], true)
    || !in_array($weatherCondition, WEATHER_VALID_CONDITIONS, true)) {
    settingsResponse(422, ['success' => false, 'message' => 'Invalid setting value.']);
}

try {
    setAppSetting($pdo, 'otp_mode', $otpMode);
    setAppSetting($pdo, 'weather_mode', $weatherMode);
    setAppSetting($pdo, 'weather_condition', $weatherCondition);

    // Force a fresh weather lookup, then rescore active issues if needed.
    resetWeatherCache($pdo);
    $effective = getEffectiveWeatherCondition($pdo);
    setAppSetting($pdo, 'weather_last_applied', $effective);

    // Saving always re-scores active issues, which also picks up newly
    // imported POIs or edited priority rules.
    $recalculated = recalculateAllActivePriorities();

    settingsResponse(200, [
        'success' => true,
        'message' => 'Settings saved.',
        'effective_weather' => $effective,
        'recalculated_issues' => $recalculated
    ]);
} catch (Throwable $e) {
    error_log('InfraFix settings update failed: ' . $e->getMessage());
    settingsResponse(500, ['success' => false, 'message' => 'Could not save settings.']);
}
