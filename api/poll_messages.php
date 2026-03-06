<?php
session_start();
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['auth_token'])) {
  echo json_encode(['messages' => [], 'read_ids' => []]);
  exit();
}

$myToken = $_SESSION['auth_token'];
$conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
$afterId = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT) ?: 0;

if (!$conversationId) {
  echo json_encode(['messages' => [], 'read_ids' => []]);
  exit();
}

try {
  // Vérifier participation
  $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (user1_token = ? OR user2_token = ?)");
  $stmt->execute([$conversationId, $myToken, $myToken]);
  if (!$stmt->fetch()) {
    echo json_encode(['messages' => [], 'read_ids' => []]);
    exit();
  }

  // Récupérer les IDs des messages envoyés par moi qui sont maintenant lus
  $readStmt = $pdo->prepare("
      SELECT id FROM messages
      WHERE conversation_id = ? AND sender_token = ? AND is_read = 1 AND id <= ?
  ");
  $readStmt->execute([$conversationId, $myToken, $afterId]);
  $readIds = $readStmt->fetchAll(PDO::FETCH_COLUMN);

  // Marquer les messages reçus comme lus
  $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_token != ? AND is_read = 0")
    ->execute([$conversationId, $myToken]);

  // Récupérer les nouveaux messages (y compris les miens, pour avancer lastMsgId)
  $stmt = $pdo->prepare("
      SELECT m.id, m.sender_token, m.content, m.created_at
      FROM messages m
      WHERE m.conversation_id = ? AND m.id > ?
      ORDER BY m.created_at ASC
  ");
  $stmt->execute([$conversationId, $afterId]);
  $rows = $stmt->fetchAll();

  $messages = [];
  foreach ($rows as $r) {
    $messages[] = [
      'id' => (int) $r['id'],
      'sender_token' => $r['sender_token'],
      'content' => $r['content'],
      'utc' => $r['created_at']
    ];
  }

  echo json_encode([
    'messages' => $messages,
    'read_ids' => array_map('intval', $readIds)
  ]);
} catch (PDOException $e) {
  echo json_encode(['messages' => [], 'read_ids' => []]);
}
