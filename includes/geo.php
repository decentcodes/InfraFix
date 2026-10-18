<?php

/*
 * Geographic helpers that work on any MySQL/MariaDB version.
 *
 * Region boundaries are stored as GeoJSON text in regions.boundary_geojson
 * (WGS84, coordinates ordered [longitude, latitude]) and tested in PHP,
 * so no database spatial functions are needed.
 */

/** Ray-casting test of a point against one linear ring. */
function pointInRing(float $lng, float $lat, array $ring): bool
{
    $inside = false;
    $count = count($ring);

    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $xi = (float) $ring[$i][0];
        $yi = (float) $ring[$i][1];
        $xj = (float) $ring[$j][0];
        $yj = (float) $ring[$j][1];

        if (($yi > $lat) !== ($yj > $lat)) {
            $xCross = ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi;

            if ($lng < $xCross) {
                $inside = !$inside;
            }
        }
    }

    return $inside;
}

/** Point in a GeoJSON Polygon coordinate set (outer ring minus holes). */
function pointInPolygonCoordinates(float $lng, float $lat, array $rings): bool
{
    if ($rings === [] || !pointInRing($lng, $lat, $rings[0])) {
        return false;
    }

    for ($i = 1, $n = count($rings); $i < $n; $i++) {
        if (pointInRing($lng, $lat, $rings[$i])) {
            return false; // inside a hole
        }
    }

    return true;
}

/** Point in a decoded GeoJSON geometry (Polygon, MultiPolygon, Feature). */
function pointInGeoJsonGeometry(float $lng, float $lat, $geometry): bool
{
    if (!is_array($geometry)) {
        return false;
    }

    if (($geometry['type'] ?? '') === 'Feature') {
        return pointInGeoJsonGeometry($lng, $lat, $geometry['geometry'] ?? null);
    }

    $type = $geometry['type'] ?? '';
    $coordinates = $geometry['coordinates'] ?? null;

    if (!is_array($coordinates)) {
        return false;
    }

    if ($type === 'Polygon') {
        return pointInPolygonCoordinates($lng, $lat, $coordinates);
    }

    if ($type === 'MultiPolygon') {
        foreach ($coordinates as $polygon) {
            if (is_array($polygon) && pointInPolygonCoordinates($lng, $lat, $polygon)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Find the most specific active region containing a point.
 *
 * Child regions (e.g. districts) are preferred over parents when both
 * have boundaries. Returns ['region_db_id' => int, 'region_id' => string]
 * or null when no active region contains the point.
 */
function findRegionForPoint(PDO $pdo, float $latitude, float $longitude): ?array
{
    $rows = $pdo->query(
        "SELECT id, region_id, parent_region_id, boundary_geojson
         FROM regions
         WHERE is_active = 1 AND boundary_geojson IS NOT NULL"
    )->fetchAll(PDO::FETCH_ASSOC);

    $parentById = [];
    foreach ($rows as $row) {
        $parentById[(int) $row['id']] =
            $row['parent_region_id'] === null ? null : (int) $row['parent_region_id'];
    }

    $depth = function (int $id) use ($parentById): int {
        $d = 0;
        $seen = [];
        while (isset($parentById[$id]) && $parentById[$id] !== null && !isset($seen[$id])) {
            $seen[$id] = true;
            $id = $parentById[$id];
            $d++;
        }
        return $d;
    };

    $best = null;
    $bestDepth = -1;

    foreach ($rows as $row) {
        $geometry = json_decode($row['boundary_geojson'], true);

        if (!pointInGeoJsonGeometry($longitude, $latitude, $geometry)) {
            continue;
        }

        $d = $depth((int) $row['id']);

        if ($d > $bestDepth) {
            $bestDepth = $d;
            $best = [
                'region_db_id' => (int) $row['id'],
                'region_id' => $row['region_id'],
            ];
        }
    }

    return $best;
}
