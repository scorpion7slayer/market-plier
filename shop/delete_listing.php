<?php
session_start();

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

require_once '../database/db.php';

$listingId = (int)$_GET['id'];

try {
    // Vérifier que l'annonce appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM listings WHERE id = ? AND auth_token = ?");
    $stmt->execute([$listingId, $_SESSION['auth_token']]);
    $listing = $stmt->fetch();

    if (!$listing) {
        header('Location: ../inscription-connexion/account.php');
        exit();
    }

    // Récupérer toutes les images pour les supprimer
    $imagesStmt = $pdo->prepare("SELECT image_path FROM listing_images WHERE listing_id = ?");
    $imagesStmt->execute([$listingId]);
    $images = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Récupérer l'image principale
    $mainStmt = $pdo->prepare("SELECT image FROM listings WHERE id = ?");
    $mainStmt->execute([$listingId]);
    $mainImage = $mainStmt->fetchColumn();

    if ($mainImage) {
        $images[] = $mainImage;
    }

    // Supprimer les images du serveur
    $uploadDir = __DIR__ . '/../uploads/listings/';
    foreach ($images as $img) {
        $imgPath = $uploadDir . $img;
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }

    // Supprimer les images de la base
    $pdo->prepare("DELETE FROM listing_images WHERE listing_id = ?")->execute([$listingId]);

    // Supprimer l'annonce
    $pdo->prepare("DELETE FROM listings WHERE id = ?")->execute([$listingId]);

    header('Location: ../inscription-connexion/account.php?success=deleted');
    exit();
} catch (PDOException $e) {
    error_log("Error deleting listing: " . $e->getMessage());
    header('Location: ../inscription-connexion/account.php?error=delete_failed');
    exit();
}
