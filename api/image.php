<?php

require_once __DIR__ . '/../database/db.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("SELECT image_data, mime_type, image_path FROM listing_images WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit;
}

// Prefer DB blob
if ($row['image_data']) {
    $mime = $row['mime_type'] ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($row['image_data']));
    header('Cache-Control: public, max-age=604800, immutable');
    echo $row['image_data'];
    exit;
}

// Fallback: file on disk
if ($row['image_path']) {
    $path = __DIR__ . '/../uploads/listings/' . basename($row['image_path']);
    if (file_exists($path)) {
        $mime = mime_content_type($path) ?: 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=604800, immutable');
        readfile($path);
        exit;
    }
}

http_response_code(404);
