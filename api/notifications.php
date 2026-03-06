<?php
session_start();
require_once '../database/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['auth_token'])) {
  echo json_encode(['notifications' => [], 'unread_count' => 0]);
  exit();
}

$myToken = $_SESSION['auth_token'];
$action = $_GET['action'] ?? 'list';

try {
  switch ($action) {
    case 'count':
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE auth_token = ? AND is_read = 0");
      $stmt->execute([$myToken]);
      echo json_encode(['unread_count' => (int) $stmt->fetchColumn()]);
      break;

    case 'read':
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $notifId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($notifId) {
          $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND auth_token = ?")
            ->execute([$notifId, $myToken]);
        }
        echo json_encode(['success' => true]);
      }
      break;

    case 'read_all':
      if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE auth_token = ? AND is_read = 0")
          ->execute([$myToken]);
        echo json_encode(['success' => true]);
      }
      break;

    default: // list
      $stmt = $pdo->prepare("
          SELECT id, type, title, content, link, is_read, created_at
          FROM notifications
          WHERE auth_token = ?
          ORDER BY created_at DESC
          LIMIT 50
      ");
      $stmt->execute([$myToken]);
      $notifications = $stmt->fetchAll();

      $unreadStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE auth_token = ? AND is_read = 0");
      $unreadStmt->execute([$myToken]);
      $unreadCount = (int) $unreadStmt->fetchColumn();

      echo json_encode([
        'notifications' => $notifications,
        'unread_count' => $unreadCount
      ]);
      break;
  }
} catch (PDOException $e) {
  error_log("notifications error: " . $e->getMessage());
  echo json_encode(['notifications' => [], 'unread_count' => 0]);
}
