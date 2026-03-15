<?php
/**
 * Sert la photo de profil depuis la DB (BLOB).
 * Usage : api/profile_photo.php?token=<auth_token>
 * Auto-migre les anciennes photos du disque vers la DB.
 */
require_once __DIR__ . '/../database/db.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    http_response_code(400);
    exit;
}

$stmt = $pdo->prepare("SELECT profile_photo_data, profile_photo_mime, profile_photo FROM users WHERE auth_token = ?");
$stmt->execute([$token]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit;
}

// Préférer le BLOB en DB
if (!empty($row['profile_photo_data'])) {
    $mime = $row['profile_photo_mime'] ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . strlen($row['profile_photo_data']));
    header('Cache-Control: no-cache, must-revalidate');
    echo $row['profile_photo_data'];
    exit;
}

// Fallback : fichier sur disque (legacy) — auto-migration vers DB
if (!empty($row['profile_photo'])) {
    $path = __DIR__ . '/../uploads/profiles/' . basename($row['profile_photo']);
    if (file_exists($path)) {
        $imageData = file_get_contents($path);
        $mime = mime_content_type($path) ?: 'image/jpeg';

        // Auto-migrer vers la DB
        if ($imageData !== false && strlen($imageData) > 0) {
            try {
                $update = $pdo->prepare("UPDATE users SET profile_photo_data = ?, profile_photo_mime = ? WHERE auth_token = ?");
                $update->bindValue(1, $imageData, PDO::PARAM_LOB);
                $update->bindValue(2, $mime);
                $update->bindValue(3, $token);
                $update->execute();
            } catch (PDOException $e) {
                error_log("Auto-migration profile photo error: " . $e->getMessage());
            }
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: no-cache, must-revalidate');
        echo $imageData;
        exit;
    }
}

// Pas de photo : renvoyer le default avatar
$defaultPath = __DIR__ . '/../assets/images/default-avatar.svg';
if (file_exists($defaultPath)) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: public, max-age=86400');
    readfile($defaultPath);
    exit;
}

http_response_code(404);
