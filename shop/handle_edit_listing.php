<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

// Détecter si post_max_size a été dépassé
if (empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $limit = ini_get('post_max_size');
    error_log("handle_edit_listing: post_max_size dépassé, CONTENT_LENGTH=" . $_SERVER['CONTENT_LENGTH']);
    header('Location: ../inscription-connexion/account.php?error=' . urlencode("Les fichiers envoyés sont trop volumineux (limite : " . $limit . ")."));
    exit();
}

// CSRF
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    error_log("CSRF mismatch in handle_edit_listing.php");
    header('Location: ../inscription-connexion/account.php?error=' . urlencode("Token de sécurité invalide ou expiré. Veuillez actualiser la page et réessayer."));
    exit();
}

$listingId = isset($_POST['listing_id']) ? (int)$_POST['listing_id'] : 0;
if ($listingId <= 0) {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

// Vérifier la propriété de l'annonce
try {
    $stmt = $pdo->prepare("SELECT id, image FROM listings WHERE id = ? AND auth_token = ?");
    $stmt->execute([$listingId, $_SESSION['auth_token']]);
    $listing = $stmt->fetch();
} catch (PDOException $e) {
    error_log("handle_edit_listing: " . $e->getMessage());
    header('Location: ../inscription-connexion/account.php?error=' . urlencode("Erreur lors de la vérification de l'annonce."));
    exit();
}

if (!$listing) {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

$redirectUrl = 'edit_listing.php?id=' . $listingId;

// Récupération des champs
$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$price       = trim($_POST['price']       ?? '');
$quantity    = trim($_POST['quantity']    ?? '1');
$category    = trim($_POST['category']    ?? '');
$condition   = trim($_POST['condition']   ?? '');
$location    = trim($_POST['location']    ?? '');

// Images à conserver (IDs)
$keepImages  = $_POST['keep_images'] ?? [];
$imageOrder  = trim($_POST['image_order'] ?? '');

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

if (!ctype_digit($quantity) || (int)$quantity < 1) {
    $errors[] = "La quantité doit être un entier positif.";
} elseif ((int)$quantity > 9999) {
    $errors[] = "La quantité ne peut pas dépasser 9 999.";
}

$validCategories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
if (!in_array($category, $validCategories, true)) {
    $errors[] = "Veuillez sélectionner une catégorie valide.";
}

$validConditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];
if (!in_array($condition, $validConditions, true)) {
    $errors[] = "Veuillez sélectionner un état valide.";
}

// Valider les IDs d'images à conserver (doivent appartenir à cette annonce)
$validKeepIds = [];
if (!empty($keepImages)) {
    $keepImages = array_map('intval', $keepImages);
    try {
        $placeholders = implode(',', array_fill(0, count($keepImages), '?'));
        $checkStmt = $pdo->prepare("SELECT id, image_path FROM listing_images WHERE listing_id = ? AND id IN ($placeholders)");
        $checkStmt->execute(array_merge([$listingId], $keepImages));
        $validKeepRows = $checkStmt->fetchAll();
        foreach ($validKeepRows as $row) {
            $validKeepIds[$row['id']] = $row['image_path'];
        }
    } catch (PDOException $e) {
        error_log("handle_edit_listing: error validating keep_images: " . $e->getMessage());
    }
}

// Upload de nouvelles images
$uploadedImages = [];
$maxImages = 5;
$currentKeptCount = count($validKeepIds);

if (!empty($_FILES['new_images']['name'][0])) {
    $fileCount = count($_FILES['new_images']['name']);
    $remainingSlots = $maxImages - $currentKeptCount;

    if ($fileCount > $remainingSlots) {
        $errors[] = "Vous ne pouvez ajouter que $remainingSlots nouvelle(s) image(s) (5 maximum au total).";
    } else {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name'     => $_FILES['new_images']['name'][$i],
                'type'     => $_FILES['new_images']['type'][$i],
                'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                'error'    => $_FILES['new_images']['error'][$i],
                'size'     => $_FILES['new_images']['size'][$i],
            ];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Erreur lors de l'envoi de l'image " . ($i + 1) . " (code " . $file['error'] . ").";
                continue;
            }

            if ($file['size'] > $maxSize) {
                $errors[] = "L'image " . ($i + 1) . " ne doit pas dépasser 5 Mo.";
                continue;
            }

            $mimeType = mime_content_type($file['tmp_name']);
            if (!in_array($mimeType, $allowedMime, true)) {
                $errors[] = "Format de l'image " . ($i + 1) . " non supporté. Utilisez JPG, PNG ou WEBP.";
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $imageName = 'listing_' . bin2hex(random_bytes(8)) . '_' . time() . '_' . $i . '.' . $ext;
            $imageData = file_get_contents($file['tmp_name']);
            if ($imageData === false) {
                $errors[] = "Impossible de lire l'image " . ($i + 1) . ".";
                continue;
            }
            $uploadedImages[] = ['name' => $imageName, 'data' => $imageData, 'mime' => $mimeType];
        }
    }
}

if (!empty($errors)) {
    header('Location: ' . $redirectUrl . '&error=' . urlencode(implode(' ', $errors)));
    exit();
}

// Mise à jour en base
try {
    $pdo->beginTransaction();

    // 1. Mettre à jour les champs de l'annonce
    $stmt = $pdo->prepare(
        "UPDATE listings SET title = ?, description = ?, price = ?, quantity = ?, category = ?, item_condition = ?, location = ?
         WHERE id = ? AND auth_token = ?"
    );
    $stmt->execute([
        $title,
        $description,
        round((float)$price, 2),
        (int)$quantity,
        $category,
        $condition,
        $location !== '' ? $location : null,
        $listingId,
        $_SESSION['auth_token'],
    ]);

    // 2. Déterminer les images à supprimer (celles qui ne sont plus dans keep_images)
    $allExistingStmt = $pdo->prepare("SELECT id, image_path FROM listing_images WHERE listing_id = ?");
    $allExistingStmt->execute([$listingId]);
    $allExisting = $allExistingStmt->fetchAll();

    foreach ($allExisting as $row) {
        if (!isset($validKeepIds[$row['id']])) {
            $pdo->prepare("DELETE FROM listing_images WHERE id = ?")->execute([$row['id']]);
        }
    }

    // 3. Insérer les nouvelles images (BLOB en DB uniquement)
    if (!empty($uploadedImages)) {
        $stmtImg = $pdo->prepare("INSERT INTO listing_images (listing_id, image_path, sort_order, image_data, mime_type) VALUES (?, ?, ?, ?, ?)");
        $sortStart = $currentKeptCount;
        foreach ($uploadedImages as $index => $img) {
            $stmtImg->bindValue(1, $listingId);
            $stmtImg->bindValue(2, $img['name']);
            $stmtImg->bindValue(3, $sortStart + $index);
            $stmtImg->bindValue(4, $img['data'], PDO::PARAM_LOB);
            $stmtImg->bindValue(5, $img['mime']);
            $stmtImg->execute();
        }
    }

    // 4. Mettre à jour l'ordre des images selon image_order
    if (!empty($imageOrder)) {
        $orderParts = explode(',', $imageOrder);
        $sortIndex = 0;
        foreach ($orderParts as $part) {
            $part = trim($part);
            if (strpos($part, 'existing:') === 0) {
                $imgId = (int)substr($part, 9);
                if (isset($validKeepIds[$imgId])) {
                    $pdo->prepare("UPDATE listing_images SET sort_order = ? WHERE id = ? AND listing_id = ?")
                        ->execute([$sortIndex, $imgId, $listingId]);
                    $sortIndex++;
                }
            } elseif (strpos($part, 'new:') === 0) {
                // Les nouvelles images sont déjà insérées avec sort_order séquentiel
                // On met à jour le sort_order correct
                $newIdx = (int)substr($part, 4);
                if (isset($uploadedImages[$newIdx])) {
                    $pdo->prepare("UPDATE listing_images SET sort_order = ? WHERE listing_id = ? AND image_path = ?")
                        ->execute([$sortIndex, $listingId, $uploadedImages[$newIdx]['name']]);
                    $sortIndex++;
                }
            }
        }
    }

    // 5. Mettre à jour l'image principale (première image par sort_order)
    $mainImgStmt = $pdo->prepare("SELECT image_path FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC LIMIT 1");
    $mainImgStmt->execute([$listingId]);
    $mainImage = $mainImgStmt->fetchColumn();

    $pdo->prepare("UPDATE listings SET image = ? WHERE id = ?")->execute([$mainImage ?: null, $listingId]);

    $pdo->commit();

    header('Location: ../inscription-connexion/account.php?success=' . urlencode("Votre annonce a été modifiée avec succès !"));
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("handle_edit_listing PDO error: " . $e->getMessage());
    header('Location: ' . $redirectUrl . '&error=' . urlencode("Une erreur est survenue lors de la modification. Veuillez réessayer."));
    exit();
}
