<?php
session_start();
require_once '../database/db.php';
require_once '../includes/send_notification.php';
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
$message = trim($_POST['message'] ?? '');

if ($sellerToken === '' || $sellerToken === $myToken) {
  echo json_encode(['success' => false, 'error' => 'Destinataire invalide']);
  exit();
}

if ($message === '') {
  echo json_encode(['success' => false, 'error' => 'Le message ne peut pas être vide']);
  exit();
}

if (mb_strlen($message) > 2000) {
  echo json_encode(['success' => false, 'error' => 'Message trop long']);
  exit();
}

try {
  // Vérifier que le vendeur existe
  $stmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
  $stmt->execute([$sellerToken]);
  if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Utilisateur introuvable']);
    exit();
  }

  // Ordonner les tokens pour la contrainte unique
  $tokens = [$myToken, $sellerToken];
  sort($tokens);

  // Chercher une conversation existante
  $stmt = $pdo->prepare("
      SELECT id FROM conversations
      WHERE user1_token = ? AND user2_token = ? AND (listing_id = ? OR (listing_id IS NULL AND ? IS NULL))
  ");
  $stmt->execute([$tokens[0], $tokens[1], $listingId, $listingId]);
  $existing = $stmt->fetch();

  if ($existing) {
    $conversationId = (int) $existing['id'];
  } else {
    $stmt = $pdo->prepare("INSERT INTO conversations (user1_token, user2_token, listing_id) VALUES (?, ?, ?)");
    $stmt->execute([$tokens[0], $tokens[1], $listingId]);
    $conversationId = (int) $pdo->lastInsertId();
  }

  // Insérer le message
  $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_token, content) VALUES (?, ?, ?)");
  $stmt->execute([$conversationId, $myToken, $message]);

  // Mettre à jour updated_at
  $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);

  // Notification au destinataire
  $senderStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
  $senderStmt->execute([$myToken]);
  $sender = $senderStmt->fetch();

  sendNotification($pdo, $sellerToken, [
      'type' => 'message',
      'title' => ($sender['username'] ?? 'Quelqu\'un') . ' vous a envoyé un message',
      'content' => mb_strimwidth($message, 0, 120, '...'),
      'link' => 'messagerie/conversation.php?id=' . $conversationId,
    ]);

  echo json_encode(['success' => true, 'conversation_id' => $conversationId]);
} catch (PDOException $e) {
  error_log("start_conversation error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
