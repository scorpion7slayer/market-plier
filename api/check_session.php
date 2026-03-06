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
      setcookie(ini_get('session.name'), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
      ]);
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
