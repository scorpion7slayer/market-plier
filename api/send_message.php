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
$conversationId = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);
$content = trim($_POST['content'] ?? '');

if (!$conversationId || $content === '') {
  echo json_encode(['success' => false, 'error' => 'Données manquantes']);
  exit();
}

if (mb_strlen($content) > 2000) {
  echo json_encode(['success' => false, 'error' => 'Message trop long (max 2000 caractères)']);
  exit();
}

try {
  // Vérifier que l'utilisateur participe à cette conversation
  $stmt = $pdo->prepare("SELECT id, user1_token, user2_token FROM conversations WHERE id = ? AND (user1_token = ? OR user2_token = ?)");
  $stmt->execute([$conversationId, $myToken, $myToken]);
  $conv = $stmt->fetch();

  if (!$conv) {
    echo json_encode(['success' => false, 'error' => 'Conversation introuvable']);
    exit();
  }

  // Insérer le message + mettre à jour la conversation atomiquement
  $pdo->beginTransaction();
  $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_token, content) VALUES (?, ?, ?)");
  $stmt->execute([$conversationId, $myToken, $content]);
  $messageId = $pdo->lastInsertId();
  $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
  $pdo->commit();

  // Notification au destinataire (in-app + Web Push)
  $recipientToken = $conv['user1_token'] === $myToken ? $conv['user2_token'] : $conv['user1_token'];
  $senderStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
  $senderStmt->execute([$myToken]);
  $sender = $senderStmt->fetch();

  sendNotification($pdo, $recipientToken, [
      'type' => 'message',
      'title' => ($sender['username'] ?? 'Quelqu\'un') . ' vous a envoyé un message',
      'content' => mb_strimwidth($content, 0, 120, '...'),
      'link' => 'messagerie/conversation.php?id=' . $conversationId,
  ]);

  echo json_encode(['success' => true, 'message_id' => (int) $messageId]);
} catch (PDOException $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log("send_message error: " . $e->getMessage());
  echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
