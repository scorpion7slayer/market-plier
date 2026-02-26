<?php
session_start();

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: sell.php');
    exit();
}

require_once '../database/db.php';

// ── CSRF ────────────────────────────────────────────────────
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: sell.php?error=' . urlencode("Token de sécurité invalide. Veuillez réessayer."));
    exit();
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Récupération des champs ──────────────────────────────────
$title       = trim($_POST['title']       ?? '');
$description = trim($_POST['description'] ?? '');
$price       = trim($_POST['price']       ?? '');
$category    = trim($_POST['category']    ?? '');
$condition   = trim($_POST['condition']   ?? '');
$location    = trim($_POST['location']    ?? '');

// ── Validation serveur ───────────────────────────────────────
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

// ── Upload image ─────────────────────────────────────────────
$imageName = null;

if (!empty($_FILES['image']['name'])) {
    $file       = $_FILES['image'];
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
    $maxSize    = 5 * 1024 * 1024; // 5 Mo

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Erreur lors de l'envoi de l'image (code " . $file['error'] . ").";
    } elseif ($file['size'] > $maxSize) {
        $errors[] = "L'image ne doit pas dépasser 5 Mo.";
    } elseif (!in_array(mime_content_type($file['tmp_name']), $allowedMime, true)) {
        $errors[] = "Format non supporté. Utilisez JPG, PNG ou WEBP.";
    } else {
        $ext       = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $imageName = 'listing_' . bin2hex(random_bytes(8)) . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../uploads/listings/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $imageName)) {
            $errors[] = "Impossible de sauvegarder l'image. Vérifiez les permissions du dossier.";
            $imageName = null;
        }
    }
}

if (!empty($errors)) {
    header('Location: sell.php?error=' . urlencode(implode(' ', $errors)));
    exit();
}

// ── Insertion en base ────────────────────────────────────────
try {
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
        $imageName,
        $location !== '' ? $location : null,
    ]);

    header('Location: sell.php?success=' . urlencode("Votre annonce a été publiée avec succès !"));
    exit();
} catch (PDOException $e) {
    error_log("handle_sell PDO error: " . $e->getMessage());
    header('Location: sell.php?error=' . urlencode("Une erreur est survenue lors de la publication. Veuillez réessayer."));
    exit();
}
