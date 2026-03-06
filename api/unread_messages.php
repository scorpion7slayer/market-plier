<?php
session_start();
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['auth_token'])) {
  echo json_encode(['unread_count' => 0]);
  exit();
}

try {
  $stmt = $pdo->prepare("
      SELECT COUNT(*) FROM messages m
      JOIN conversations c ON c.id = m.conversation_id
      WHERE m.is_read = 0 AND m.sender_token != ?
        AND (c.user1_token = ? OR c.user2_token = ?)
  ");
  $stmt->execute([$_SESSION['auth_token'], $_SESSION['auth_token'], $_SESSION['auth_token']]);
  echo json_encode(['unread_count' => (int) $stmt->fetchColumn()]);
} catch (PDOException $e) {
  echo json_encode(['unread_count' => 0]);
}
