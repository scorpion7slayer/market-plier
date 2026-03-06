<?php
session_start();

// Supprimer le token "Rester connecté" en base
if (isset($_SESSION['auth_token'])) {
  try {
    require '../database/db.php';
    $stmtClear = $pdo->prepare("UPDATE users SET remember_token = NULL WHERE auth_token = ?");
    $stmtClear->execute([$_SESSION['auth_token']]);
  } catch (Exception $e) {
    // Silencieux : la déconnexion doit toujours fonctionner
  }
}

// Supprimer le cookie remember_me
setcookie('mp_remember', '', time() - 3600, '/', '', false, true);

// Supprimer toutes les variables de session
$_SESSION = [];

// Supprimer le cookie de session
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

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: login.php");
exit;
