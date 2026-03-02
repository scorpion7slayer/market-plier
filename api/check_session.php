<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['auth_token'])) {
  echo json_encode(['valid' => false]);
  exit;
}

try {
  require_once __DIR__ . '/../database/db.php';
  $stmt = $pdo->prepare("SELECT 1 FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);

  if (!$stmt->fetch()) {
    // L'utilisateur a été supprimé — détruire la session
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    echo json_encode(['valid' => false]);
    exit;
  }

  echo json_encode(['valid' => true]);
} catch (Exception $e) {
  // En cas d'erreur DB, ne pas déconnecter l'utilisateur
  echo json_encode(['valid' => true]);
}
