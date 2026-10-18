<?php

/*
 * One-time CLI helper: download Delhi points of interest from
 * OpenStreetMap (Overpass API) and load them into points_of_interest.
 *
 * Run on your own computer (needs internet):
 *   php tools/import-pois.php --apply      load straight into the local database
 *   php tools/import-pois.php > database/pois.sql
 *                                          write an SQL file for phpMyAdmin (host)
 *
 * If the download fails with an SSL or certificate error on Windows, enable
 * extension=openssl (and extension=curl) in php.ini and point
 * curl.cainfo / openssl.cafile at a cacert.pem file (https://curl.se/docs/caextract.html).
 *
 * Map data (c) OpenStreetMap contributors, ODbL. Types follow the spec:
 * Hospital, School, Market, Emergency (police / fire), Transport (rail / bus).
 * Re-running is safe: existing POIs are kept (INSERT IGNORE).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$apply = in_array('--apply', $argv, true);

// Delhi bounding box: south, west, north, east.
$bbox = '28.40,76.83,28.89,77.35';

$queries = [
    'POI-HOSPITAL' => ['amenity=hospital'],
    'POI-SCHOOL' => ['amenity=school'],
    'POI-MARKET' => ['amenity=marketplace', 'shop=mall'],
    'POI-EMERGENCY' => ['amenity=police', 'amenity=fire_station'],
    'POI-TRANSPORT' => ['railway=station', 'amenity=bus_station'],
];

$labels = [
    'POI-HOSPITAL' => 'Hospital',
    'POI-SCHOOL' => 'School',
    'POI-MARKET' => 'Market',
    'POI-EMERGENCY' => 'Emergency facility',
    'POI-TRANSPORT' => 'Transport facility',
];

function overpassRequest(string $url, string $body, ?string &$error): ?string
{
    $error = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 200,
            CURLOPT_USERAGENT => 'InfraFix-college-project/1.0',
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if ($raw === false) {
            $error = 'cURL: ' . curl_error($ch);
        } elseif ($status !== 200) {
            $error = "HTTP $status";
            $raw = false;
        }


        return $raw === false ? null : (string) $raw;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
                . "User-Agent: InfraFix-college-project/1.0\r\n",
            'content' => $body,
            'timeout' => 200,
            'ignore_errors' => true,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
        $last = error_get_last();
        $error = $last['message'] ?? 'unknown error';
        return null;
    }

    $statusLine = $http_response_header[0] ?? '';

    if (strpos($statusLine, ' 200') === false) {
        $error = $statusLine !== '' ? $statusLine : 'unexpected response';
        return null;
    }

    return $raw;
}

function overpassPost(string $query): ?array
{
    $body = http_build_query(['data' => $query]);
    $endpoints = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
    ];

    foreach ($endpoints as $url) {
        $raw = overpassRequest($url, $body, $error);

        if ($raw !== null) {
            $decoded = json_decode($raw, true);

            if (is_array($decoded) && isset($decoded['elements'])) {
                return $decoded['elements'];
            }

            $error = 'response was not valid Overpass JSON';
        }

        fwrite(STDERR, "Overpass endpoint failed: $url\n  reason: $error\n");
    }

    fwrite(STDERR, "\nIf the reason mentions SSL / certificate / openssl, see the note at the top of this file.\n");

    return null;
}

$pdo = null;
$typeIds = [];

if ($apply) {
    require_once __DIR__ . '/../includes/db.php';

    $rows = $pdo->query('SELECT id, poi_type_id FROM poi_types')->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $typeIds[$row['poi_type_id']] = (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT IGNORE INTO points_of_interest
            (poi_id, poi_type_id, name, latitude, longitude)
         VALUES (?, ?, ?, ?, ?)'
    );
} else {
    echo "-- Delhi POIs from OpenStreetMap (c) OpenStreetMap contributors, ODbL.\n";
    echo "-- Import after seed.sql. Safe to re-run (INSERT IGNORE).\n\n";
}

$total = 0;
$seen = [];

foreach ($queries as $typeSlug => $filters) {
    $parts = '';

    foreach ($filters as $filter) {
        [$key, $value] = explode('=', $filter, 2);
        foreach (['node', 'way', 'relation'] as $kind) {
            $parts .= "{$kind}[\"{$key}\"=\"{$value}\"]({$bbox});\n";
        }
    }

    $elements = overpassPost("[out:json][timeout:150];\n(\n$parts);\nout center tags;");

    if ($elements === null) {
        fwrite(STDERR, "Skipping $typeSlug (download failed). Re-run later.\n");
        continue;
    }

    $count = 0;

    foreach ($elements as $element) {
        $lat = $element['lat'] ?? ($element['center']['lat'] ?? null);
        $lng = $element['lon'] ?? ($element['center']['lon'] ?? null);

        if ($lat === null || $lng === null) {
            continue;
        }

        $poiId = 'OSM-' . strtoupper($element['type'][0]) . $element['id'];

        if (isset($seen[$poiId])) {
            continue;
        }

        $seen[$poiId] = true;

        $name = trim((string) ($element['tags']['name'] ?? ''));
        $name = $name === '' ? 'Unnamed ' . strtolower($labels[$typeSlug]) : $name;
        $name = mb_substr($name, 0, 255);

        $lat = round((float) $lat, 7);
        $lng = round((float) $lng, 7);

        if ($apply) {
            $insert->execute([$poiId, $typeIds[$typeSlug], $name, $lat, $lng]);
        } else {
            echo "INSERT IGNORE INTO points_of_interest (poi_id, poi_type_id, name, latitude, longitude) "
                . "SELECT '" . $poiId . "', id, '" . str_replace("'", "''", $name) . "', "
                . $lat . ', ' . $lng
                . " FROM poi_types WHERE poi_type_id = '" . $typeSlug . "';\n";
        }

        $count++;
    }

    $total += $count;
    fwrite(STDERR, str_pad($labels[$typeSlug], 20) . $count . "\n");

    sleep(2); // be polite to the public Overpass servers
}

fwrite(STDERR, "Total: $total POIs\n");
