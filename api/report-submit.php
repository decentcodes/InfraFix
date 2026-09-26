<?php

header('Content-Type: application/json');

$category = $_POST['issue_category'] ?? '';
$description = $_POST['description'] ?? '';
$latitude = $_POST['latitude'] ?? '';
$longitude = $_POST['longitude'] ?? '';

$photo = $_FILES['issue_photo'] ?? null;

echo json_encode([
    'success' => true,

    'report' => [
        'category' => $category,
        'description' => $description,
        'latitude' => $latitude,
        'longitude' => $longitude,
    ],

    'photo' => $photo
]);