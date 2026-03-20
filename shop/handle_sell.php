<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/site_settings.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

// Vérifier la limite d'annonces par utilisateur
if (getSiteSetting($pdo, 'listing_limit') === '1') {
    $maxListings = 10;
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE auth_token = ? AND status = 'active'");
    $countStmt->execute([$_SESSION['auth_token']]);
    if ((int)$countStmt->fetchColumn() >= $maxListings) {
        header('Location: sell.php?error=' . urlencode("Vous avez atteint la limite de $maxListings annonces actives."));
        exit();
    }
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
$quantity    = trim($_POST['quantity']    ?? '1');
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
            $imageData = file_get_contents($file['tmp_name']);
            if ($imageData === false) {
                $errors[] = "Impossible de lire l'image " . ($i + 1) . ".";
                continue;
            }
            $uploadedImages[] = ['name' => $imageName, 'data' => $imageData, 'mime' => $mimeType];
        }
    }
}

// Première image pour le champ image de la table listings (rétrocompatibilité)
$mainImage = !empty($uploadedImages) ? $uploadedImages[0]['name'] : null;

if (!empty($errors)) {
    header('Location: sell.php?error=' . urlencode(implode(' ', $errors)));
    exit();
}

// Déterminer le statut de l'annonce
$listingStatus = (getSiteSetting($pdo, 'moderation_enabled') === '1') ? 'pending' : 'active';

// Insertion en base
try {
    $pdo->beginTransaction();

    // Insertion de l'annonce principale
    $stmt = $pdo->prepare(
        "INSERT INTO listings (auth_token, title, description, price, quantity, category, item_condition, image, location, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $_SESSION['auth_token'],
        $title,
        $description,
        round((float)$price, 2),
        (int)$quantity,
        $category,
        $condition,
        $mainImage,
        $location !== '' ? $location : null,
        $listingStatus,
    ]);

    $listingId = $pdo->lastInsertId();

    // Insertion des images additionnelles (BLOB en DB uniquement)
    if (!empty($uploadedImages)) {
        $stmtImg = $pdo->prepare("INSERT INTO listing_images (listing_id, image_path, sort_order, image_data, mime_type) VALUES (?, ?, ?, ?, ?)");
        foreach ($uploadedImages as $index => $img) {
            $stmtImg->bindValue(1, $listingId);
            $stmtImg->bindValue(2, $img['name']);
            $stmtImg->bindValue(3, $index);
            $stmtImg->bindValue(4, $img['data'], PDO::PARAM_LOB);
            $stmtImg->bindValue(5, $img['mime']);
            $stmtImg->execute();
        }
    }

    $pdo->commit();

    // Vérifier si le vendeur a déjà configuré Stripe
    $stripeRow = $pdo->prepare("SELECT stripe_account_id, stripe_onboarding_complete, email FROM users WHERE auth_token = ?");
    $stripeRow->execute([$_SESSION['auth_token']]);
    $stripeUser = $stripeRow->fetch();

    // Si Stripe non configuré, créer le compte Express silencieusement et rediriger vers la page setup
    if (empty($stripeUser['stripe_onboarding_complete'])) {
        if (empty($stripeUser['stripe_account_id'])) {
            try {
                require_once '../config/stripe.php';
                $account = \Stripe\Account::create([
                    'type'  => 'express',
                    'email' => $stripeUser['email'],
                    'capabilities' => ['transfers' => ['requested' => true]],
                    'business_profile' => [
                        'product_description' => 'Vendeur sur Market Plier',
                    ],
                ]);
                $pdo->prepare("UPDATE users SET stripe_account_id = ? WHERE auth_token = ?")
                    ->execute([$account->id, $_SESSION['auth_token']]);
            } catch (\Exception $e) {
                error_log("Auto Stripe account creation failed: " . $e->getMessage());
                // Échec Stripe : on redirige quand même vers seller_setup (pas d'exit ici)
            }
        }
        // Toujours rediriger vers seller_setup si l'onboarding n'est pas complet
        $pending = ($listingStatus === 'pending') ? '1' : '0';
        header('Location: ../stripe/seller_setup.php?listing_id=' . $listingId . '&pending=' . $pending);
        exit();
    }

    $successMsg = ($listingStatus === 'pending')
        ? "Votre annonce a été soumise et sera visible après validation par un administrateur."
        : "Votre annonce a été publiée avec succès !";
    header('Location: sell.php?success=' . urlencode($successMsg));
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("handle_sell PDO error: " . $e->getMessage());
    header('Location: sell.php?error=' . urlencode("Une erreur est survenue lors de la publication. Veuillez réessayer."));
    exit();
}
