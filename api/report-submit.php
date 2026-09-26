<?php

header('Content-Type: application/json');


/*
 * Helper: send a JSON error response and stop execution.
 */

function sendError(string $message, int $statusCode = 400): void
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => false,
        'message' => $message
    ]);

    exit;
}


/*
 * Get submitted values.
 */

$category = $_POST['issue_category'] ?? '';
$description = $_POST['description'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';


/*
 * Allowed issue categories.
 */

$allowedCategories = [
    'pothole',
    'open-manhole',
    'broken-streetlight',
    'garbage',
    'water-leakage',
    'exposed-wiring',
    'blocked-drainage'
];


/*
 * Validate category.
 */

if (!in_array($category, $allowedCategories, true)) {
    sendError('Invalid issue category.');
}


/*
 * Validate location.
 */

if ($latitude === '' || $longitude === '') {
    sendError('Current location is required.');
}


/*
 * Convert coordinates to numbers.
 */

$latitude = (float) $latitude;
$longitude = (float) $longitude;


/*
 * Check coordinate ranges.
 */

if (
    $latitude < -90 ||
    $latitude > 90 ||
    $longitude < -180 ||
    $longitude > 180
) {
    sendError('Invalid location coordinates.');
}


/*
 * Normalize and validate description.
 */

$description = str_replace(["\r\n", "\r"], "\n", $description);

if (mb_strlen($description) > 500) {
    sendError('Description must not exceed 500 characters.');
}


/*
 * Validate photograph upload.
 */

if (!isset($_FILES['issue_photo'])) {
    sendError('A photograph is required.');
}

$photo = $_FILES['issue_photo'];


/*
 * Check for upload errors.
 */

if ($photo['error'] !== UPLOAD_ERR_OK) {
    sendError('There was a problem uploading the photograph.');
}


/*
 * Check photograph size.
 */

$maxPhotoSize = 5 * 1024 * 1024;

if ($photo['size'] > $maxPhotoSize) {
    sendError('The photograph must be smaller than 5 MB.');
}


/*
 * Verify that the uploaded file is actually an image.
 */

$imageInfo = getimagesize($photo['tmp_name']);

if ($imageInfo === false) {
    sendError('The uploaded file is not a valid image.');
}


/*
 * Check allowed image formats.
 */

$allowedImageTypes = [
    IMAGETYPE_JPEG,
    IMAGETYPE_PNG,
    IMAGETYPE_WEBP
];

if (!in_array($imageInfo[2], $allowedImageTypes, true)) {
    sendError('Only JPEG, PNG, and WebP images are allowed.');
}


/*
 * All validation passed.
 */

echo json_encode([
    'success' => true,
    'message' => 'Report data passed server-side validation.',
    'report' => [
        'category' => $category,
        'description' => $description,
        'latitude' => $latitude,
        'longitude' => $longitude
    ]
]);