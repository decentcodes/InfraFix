<?php

/*
 * Logic tests for includes/geo.php (no database needed).
 * Run: php tests/test-geo.php
 */

require_once __DIR__ . '/../includes/geo.php';

$failures = 0;

function check(string $label, bool $expected, bool $actual): void
{
    global $failures;

    if ($expected !== $actual) {
        $failures++;
        echo "FAIL: $label\n";
        return;
    }

    echo "ok:   $label\n";
}

// Square 0..10 (lng, lat) with a hole 4..6.
$polygon = [
    'type' => 'Polygon',
    'coordinates' => [
        [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]],
        [[4, 4], [6, 4], [6, 6], [4, 6], [4, 4]],
    ],
];

check('inside the square', true, pointInGeoJsonGeometry(2, 2, $polygon));
check('outside the square', false, pointInGeoJsonGeometry(12, 2, $polygon));
check('inside the hole', false, pointInGeoJsonGeometry(5, 5, $polygon));
check('lng/lat order matters', false, pointInGeoJsonGeometry(2, 12, $polygon));

$multi = [
    'type' => 'MultiPolygon',
    'coordinates' => [
        [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
        [[[20, 20], [22, 20], [22, 22], [20, 22], [20, 20]]],
    ],
];

check('multipolygon: first part', true, pointInGeoJsonGeometry(0.5, 0.5, $multi));
check('multipolygon: second part', true, pointInGeoJsonGeometry(21, 21, $multi));
check('multipolygon: between parts', false, pointInGeoJsonGeometry(10, 10, $multi));

$feature = ['type' => 'Feature', 'geometry' => $polygon];
check('Feature wrapper', true, pointInGeoJsonGeometry(2, 2, $feature));
check('invalid geometry', false, pointInGeoJsonGeometry(2, 2, ['type' => 'Point']));

echo $failures === 0 ? "\nAll geo tests passed.\n" : "\n$failures test(s) failed.\n";
exit($failures === 0 ? 0 : 1);
