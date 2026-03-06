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
$sellerToken = trim($_POST['seller_token'] ?? '');
$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
$comment = trim($_POST['comment'] ?? '');

if ($sellerToken === '' || $sellerToken === $myToken) {
  echo json_encode(['success' => false, 'error' => 'Impossible de donner un avis sur vous-même']);
  exit();
}

if (!$rating || $rating < 1 || $rating > 5) {
  echo json_encode(['success' => false, 'error' => 'Note invalide (1-5)']);
  exit();
}

if (mb_strlen($comment) > 1000) {
  echo json_encode(['success' => false, 'error' => 'Commentaire trop long (max 1000 caractères)']);
  exit();
}

try {
  // Vérifier que le vendeur existe
  $stmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
  $stmt->execute([$sellerToken]);
  $seller = $stmt->fetch();
  if (!$seller) {
    echo json_encode(['success' => false, 'error' => 'Vendeur introuvable']);
    exit();
  }

  // Vérifier si un avis existe déjà
  $stmt = $pdo->prepare("SELECT id FROM reviews WHERE reviewer_token = ? AND seller_token = ? AND listing_id = ?");
  $stmt->execute([$myToken, $sellerToken, $listingId]);
  if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Vous avez déjà donné un avis']);
    exit();
  }

  // Insérer l'avis
  $stmt = $pdo->prepare("INSERT INTO reviews (reviewer_token, seller_token, listing_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
  $stmt->execute([$myToken, $sellerToken, $listingId, $rating, $comment ?: null]);

  // Notification au vendeur
  $reviewerStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
  $reviewerStmt->execute([$myToken]);
  $reviewer = $reviewerStmt->fetch();

  $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
  $pdo->prepare("INSERT INTO notifications (auth_token, type, title, content, link) VALUES (?, 'review', ?, ?, ?)")
    ->execute([
      $sellerToken,
      ($reviewer['username'] ?? 'Quelqu\'un') . ' vous a donné un avis ' . $stars,
      $comment ? mb_strimwidth($comment, 0, 100, '...') : null,
      'inscription-connexion/profile.php?user=' . urlencode($seller['username'])
    ]);

  // Récupérer les stats mises à jour
  $avgStmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE seller_token = ?");
  $avgStmt->execute([$sellerToken]);
  $stats = $avgStmt->fetch();

  echo json_encode([
    'success' => true,
    'avg_rating' => round((float) $stats['avg_rating'], 1),
    'review_count' => (int) $stats['review_count']
  ]);
} catch (PDOException $e) {
  error_log("submit_review error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
