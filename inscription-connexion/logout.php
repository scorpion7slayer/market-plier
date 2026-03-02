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
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de connexion
header("Location: login.php");
exit;
