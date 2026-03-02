<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sell.php');
    exit();
}

// Détecter si post_max_size a été dépassé (PHP vide $_POST dans ce cas,
// ce qui causerait une fausse erreur CSRF non informative).
if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $limit = ini_get('post_max_size');
    error_log("handle_sell: post_max_size dépassé, CONTENT_LENGTH=" . $_SERVER['CONTENT_LENGTH'] . ", limit=" . $limit);
    header('Location: sell.php?error=' . urlencode("Les fichiers envoyés sont trop volumineux (limite : " . $limit . "). Réduisez la taille ou le nombre de photos."));
    exit();
}

// CSRF
// Comparer contre le jeton stocké (utiliser l'opérateur ?? pour éviter
// un avertissement si la session n'en contient pas encore).
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    // enregistrer le cas pour faciliter le debug (valeurs tronquées)
    $posted = $_POST['csrf_token'] ?? '(absent)';
    $stored = $_SESSION['csrf_token'] ?? '(absent)';
    error_log("CSRF mismatch in handle_sell.php: posted={$posted}, session={$stored}");

    header('Location: sell.php?error=' . urlencode("Token de sécurité invalide ou expiré. Veuillez actualiser la page et réessayer."));
    exit();
}
// Token CSRF par session : pas de régénération après chaque requête

// Récupération des champs
$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$price       = trim($_POST['price']       ?? '');
$category    = trim($_POST['category']    ?? '');
$condition   = trim($_POST['condition']   ?? '');
$location    = trim($_POST['location']    ?? '');

// Validation serveur
$errors = [];

if (mb_strlen($title) < 3) {
    $errors[] = "Le titre doit faire au moins 3 caractères.";
} elseif (mb_strlen($title) > 100) {
    $errors[] = "Le titre ne peut pas dépasser 100 caractères.";
}

if (mb_strlen($description) < 10) {
    $errors[] = "La description doit faire au moins 10 caractères.";
}

if (!is_numeric($price) || (float)$price < 0) {
    $errors[] = "Le prix doit être un nombre positif.";
} elseif ((float)$price > 99999.99) {
    $errors[] = "Le prix ne peut pas dépasser 99 999 €.";
}

$validCategories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
if (!in_array($category, $validCategories, true)) {
    $errors[] = "Veuillez sélectionner une catégorie valide.";
}

$validConditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];
if (!in_array($condition, $validConditions, true)) {
    $errors[] = "Veuillez sélectionner un état valide.";
}

// Upload images
$uploadedImages = [];
$maxImages = 5;

if (!empty($_FILES['images']['name'][0])) {
    $fileCount = count($_FILES['images']['name']);

    if ($fileCount > $maxImages) {
        $errors[] = "Vous ne pouvez télécharger que $maxImages images maximum.";
    } else {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5 Mo

        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i]
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Erreur lors de l'envoi de l'image " . ($i + 1) . " (code " . $file['error'] . ").";
                continue;
            }

            if ($file['size'] > $maxSize) {
                $errors[] = "L'image " . ($i + 1) . " ne doit pas dépasser 5 Mo.";
                continue;
            }

            $tmpFile = $file['tmp_name'];
            $mimeType = mime_content_type($tmpFile);
            if (!in_array($mimeType, $allowedMime, true)) {
                $errors[] = "Format de l'image " . ($i + 1) . " non supporté. Utilisez JPG, PNG ou WEBP.";
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $imageName = 'listing_' . bin2hex(random_bytes(8)) . '_' . time() . '_' . $i . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/listings/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
                $uploadedImages[] = $imageName;
            } else {
                $errors[] = "Impossible de sauvegarder l'image " . ($i + 1) . ". Vérifiez les permissions du dossier.";
            }
        }
    }
}

// Première image pour le champ image de la table listings (rétrocompatibilité)
$mainImage = !empty($uploadedImages) ? $uploadedImages[0] : null;

if (!empty($errors)) {
    // Supprime les images déjà uploadées en cas d'erreur
    foreach ($uploadedImages as $img) {
        $imgPath = __DIR__ . '/../uploads/listings/' . $img;
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }
    header('Location: sell.php?error=' . urlencode(implode(' ', $errors)));
    exit();
}

// Insertion en base
try {
    $pdo->beginTransaction();

    // Insertion de l'annonce principale
    $stmt = $pdo->prepare(
        "INSERT INTO listings (auth_token, title, description, price, category, item_condition, image, location)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['auth_token'],
        $title,
        $description,
        round((float)$price, 2),
        $category,
        $condition,
        $mainImage,
        $location !== '' ? $location : null,
    ]);

    $listingId = $pdo->lastInsertId();

    // Insertion des images additionnelles
    if (!empty($uploadedImages)) {
        $stmtImg = $pdo->prepare("INSERT INTO listing_images (listing_id, image_path, sort_order) VALUES (?, ?, ?)");
        foreach ($uploadedImages as $index => $imgName) {
            $stmtImg->execute([$listingId, $imgName, $index]);
        }
    }

    $pdo->commit();

    header('Location: sell.php?success=' . urlencode("Votre annonce a été publiée avec succès !"));
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    // Supprime les images uploadées en cas d'erreur DB
    foreach ($uploadedImages as $img) {
        $imgPath = __DIR__ . '/../uploads/listings/' . $img;
        if (file_exists($imgPath)) {
            unlink($imgPath);
        }
    }
    error_log("handle_sell PDO error: " . $e->getMessage());
    header('Location: sell.php?error=' . urlencode("Une erreur est survenue lors de la publication. Veuillez réessayer."));
    exit();
}
