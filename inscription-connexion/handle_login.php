<?php
session_start();

try {
  require '../database/db.php';
} catch (PDOException $e) {
  header("Location: login.php?error=" . urlencode("Erreur de connexion à la base de données : " . $e->getMessage()));
  exit;
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  header("Location: login.php?error=" . urlencode("Token de sécurité invalide. Veuillez réessayer."));
  exit;
}

// Régénérer le token CSRF après utilisation
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if (empty($_POST['email']) || empty($_POST['password'])) {
  header("Location: login.php?error=" . urlencode("Veuillez remplir tous les champs"));
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT id, username, email, password_hash FROM users WHERE email = ?");
  $stmt->execute([$_POST['email']]);
  $user = $stmt->fetch();

  if ($user && password_verify($_POST['password'], $user['password_hash'])) {
    // Régénérer l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    header("Location: dashboard.php");
    exit;
  } else {
    header("Location: login.php?error=" . urlencode("Email ou mot de passe incorrect"));
    exit;
  }
} catch (PDOException $e) {
  header("Location: login.php?error=" . urlencode("Erreur lors de la connexion"));
  exit;
}
