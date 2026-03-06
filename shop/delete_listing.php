<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

// Detect AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['auth_token'])) {
    if ($isAjax) {
        http_response_code(401);
        echo json_encode(['error' => 'Non authentifié']);
        exit();
    }
    header('Location: ../inscription-connexion/login.php');
    exit();
}

// Accept listing ID from POST (AJAX) or GET (legacy)
$rawId = $_POST['id'] ?? $_GET['id'] ?? null;
if (!$rawId || !is_numeric($rawId)) {
    if ($isAjax) {
        http_response_code(400);
        echo json_encode(['error' => 'ID invalide']);
        exit();
    }
    header('Location: ../inscription-connexion/account.php');
    exit();
}

// CSRF check for AJAX POST
if ($isAjax) {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfToken) || $csrfToken !== ($_SESSION['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF invalide']);
        exit();
    }
}

$listingId = (int)$rawId;

try {
    // Vérifier que l'annonce appartient à l'utilisateur
    $stmt = $pdo->prepare("SELECT id FROM listings WHERE id = ? AND auth_token = ?");
    $stmt->execute([$listingId, $_SESSION['auth_token']]);
    $listing = $stmt->fetch();

    if (!$listing) {
        if ($isAjax) {
            http_response_code(403);
            echo json_encode(['error' => 'Annonce introuvable']);
            exit();
        }
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

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Annonce supprimée.']);
        exit();
    }
    header('Location: ../inscription-connexion/account.php?success=deleted');
    exit();
} catch (PDOException $e) {
    error_log("Error deleting listing: " . $e->getMessage());
    if ($isAjax) {
        http_response_code(500);
        echo json_encode(['error' => 'Erreur lors de la suppression.']);
        exit();
    }
    header('Location: ../inscription-connexion/account.php?error=delete_failed');
    exit();
}
