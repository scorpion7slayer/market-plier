<?php
session_start();

// Supprimer toutes les variables de session
$_SESSION = [];

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(), // session_name() sans argument retourne le nom de la session
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
