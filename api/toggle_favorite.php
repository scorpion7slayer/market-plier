<?php
session_start();
require_once '../database/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
  exit();
}

if (!isset($_SESSION['auth_token'])) {
  echo json_encode(['success' => false, 'error' => 'Non connecté']);
  exit();
}

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
  echo json_encode(['success' => false, 'error' => 'Token CSRF invalide']);
  exit();
}

$myToken = $_SESSION['auth_token'];
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);

if (!$listingId) {
  echo json_encode(['success' => false, 'error' => 'Annonce invalide']);
  exit();
}

try {
  // Vérifier que l'annonce existe
  $stmt = $pdo->prepare("SELECT id, auth_token, title FROM listings WHERE id = ?");
  $stmt->execute([$listingId]);
  $listing = $stmt->fetch();

  if (!$listing) {
    echo json_encode(['success' => false, 'error' => 'Annonce introuvable']);
    exit();
  }

  // Vérifier si déjà en favori
  $stmt = $pdo->prepare("SELECT id FROM favorites WHERE auth_token = ? AND listing_id = ?");
  $stmt->execute([$myToken, $listingId]);
  $existing = $stmt->fetch();

  if ($existing) {
    // Retirer des favoris
    $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$existing['id']]);
    echo json_encode(['success' => true, 'favorited' => false]);
  } else {
    // Ajouter aux favoris
    $pdo->prepare("INSERT INTO favorites (auth_token, listing_id) VALUES (?, ?)")->execute([$myToken, $listingId]);

    // Notification au vendeur (si ce n'est pas soi-même)
    if ($listing['auth_token'] !== $myToken) {
      $userStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
      $userStmt->execute([$myToken]);
      $userData = $userStmt->fetch();

      $pdo->prepare("INSERT INTO notifications (auth_token, type, title, content, link) VALUES (?, 'favorite', ?, ?, ?)")
        ->execute([
          $listing['auth_token'],
          ($userData['username'] ?? 'Quelqu\'un') . ' a ajouté votre annonce en favori',
          $listing['title'],
          'shop/buy.php?id=' . $listingId
        ]);
    }

    echo json_encode(['success' => true, 'favorited' => true]);
  }
} catch (PDOException $e) {
  error_log("toggle_favorite error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
