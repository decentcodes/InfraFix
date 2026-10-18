<?php

/*
 * Weather condition used by the priority score (None / Rain / Snow).
 *
 * app_settings keys:
 *   weather_mode          manual | auto
 *   weather_condition     the manual value (None / Rain / Snow)
 *   weather_auto_value    last value fetched from Open-Meteo
 *   weather_auto_checked  unix time of the last successful fetch
 *   weather_auto_attempt  unix time of the last attempt (success or not)
 *
 * Auto mode asks Open-Meteo (free, no API key) for the current weather at
 * the centre of Delhi, at most every 30 minutes (5 minutes after a failed
 * attempt). If the request fails, the last good value (or the manual
 * value) is used, so scoring never breaks because of the network.
 */

require_once __DIR__ . '/settings.php';

const WEATHER_VALID_CONDITIONS = ['None', 'Rain', 'Snow'];
const WEATHER_DELHI_LATITUDE = 28.6139;
const WEATHER_DELHI_LONGITUDE = 77.2090;
const WEATHER_REFRESH_SECONDS = 1800;
const WEATHER_RETRY_SECONDS = 300;

function weatherHttpGet(string $url): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'InfraFix-college-project/1.0',
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return ($body !== false && $status === 200) ? (string) $body : null;
    }

    $context = stream_context_create([
        'http' => ['timeout' => 6, 'user_agent' => 'InfraFix-college-project/1.0'],
    ]);
    $body = @file_get_contents($url, false, $context);

    return $body === false ? null : $body;
}

/** Map an Open-Meteo "current" block to None / Rain / Snow. */
function weatherConditionFromCurrent(array $current): string
{
    $code = (int) ($current['weather_code'] ?? 0);
    $snowfall = (float) ($current['snowfall'] ?? 0);
    $rain = (float) ($current['rain'] ?? 0) + (float) ($current['showers'] ?? 0);

    $snowCodes = [71, 73, 75, 77, 85, 86];
    $rainCodes = [51, 53, 55, 56, 57, 61, 63, 65, 66, 67, 80, 81, 82, 95, 96, 99];

    if ($snowfall > 0 || in_array($code, $snowCodes, true)) {
        return 'Snow';
    }

    if ($rain > 0 || in_array($code, $rainCodes, true)) {
        return 'Rain';
    }

    return 'None';
}

function fetchLiveWeatherCondition(): ?string
{
    $url = 'https://api.open-meteo.com/v1/forecast?latitude='
        . WEATHER_DELHI_LATITUDE
        . '&longitude=' . WEATHER_DELHI_LONGITUDE
        . '&current=rain,showers,snowfall,weather_code&timezone=Asia%2FKolkata';

    $body = weatherHttpGet($url);

    if ($body === null) {
        return null;
    }

    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['current']) || !is_array($data['current'])) {
        return null;
    }

    return weatherConditionFromCurrent($data['current']);
}

function getManualWeatherCondition(PDO $pdo): string
{
    $value = getAppSetting($pdo, 'weather_condition', 'None');

    return in_array($value, WEATHER_VALID_CONDITIONS, true) ? $value : 'None';
}

/** The condition priority scoring should use right now. */
function getEffectiveWeatherCondition(PDO $pdo): string
{
    $manual = getManualWeatherCondition($pdo);

    if (getAppSetting($pdo, 'weather_mode', 'manual') !== 'auto') {
        return $manual;
    }

    $cached = getAppSetting($pdo, 'weather_auto_value', '');
    $checked = (int) getAppSetting($pdo, 'weather_auto_checked', '0');
    $attempt = (int) getAppSetting($pdo, 'weather_auto_attempt', '0');
    $now = time();

    $freshEnough = $cached !== '' && ($now - $checked) < WEATHER_REFRESH_SECONDS;
    $recentlyTried = ($now - $attempt) < WEATHER_RETRY_SECONDS;

    if (!$freshEnough && !$recentlyTried) {
        setAppSetting($pdo, 'weather_auto_attempt', (string) $now);

        $live = fetchLiveWeatherCondition();

        if ($live !== null) {
            setAppSetting($pdo, 'weather_auto_value', $live);
            setAppSetting($pdo, 'weather_auto_checked', (string) $now);
            $cached = $live;
        }
    }

    return in_array($cached, WEATHER_VALID_CONDITIONS, true) ? $cached : $manual;
}

/** Forget the cache so the next call fetches fresh data. */
function resetWeatherCache(PDO $pdo): void
{
    setAppSetting($pdo, 'weather_auto_checked', '0');
    setAppSetting($pdo, 'weather_auto_attempt', '0');
}
