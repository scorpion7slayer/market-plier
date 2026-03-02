<?php
session_start();

try {
  require '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (login): " . $e->getMessage());
  header("Location: login.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
  exit;
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  header("Location: login.php?error=" . urlencode("Token de sécurité invalide. Veuillez réessayer."));
  exit;
}

// Token CSRF par session : pas de régénération après chaque requête

if (empty($_POST['email']) || empty($_POST['password'])) {
  header("Location: login.php?error=" . urlencode("Veuillez remplir tous les champs"));
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT auth_token, username, email, password_hash, auth_provider FROM users WHERE email = ?");
  $stmt->execute([$_POST['email']]);
  $user = $stmt->fetch();

  if ($user && $user['password_hash'] === null) {
    // Compte Google-only : pas de mot de passe défini
    header("Location: login.php?error=" . urlencode("Ce compte utilise la connexion Google. Veuillez utiliser le bouton « Se connecter avec google »."));
    exit;
  }

  if ($user && password_verify($_POST['password'], $user['password_hash'])) {
    // Régénérer l'ID de session pour éviter la fixation de session
    session_regenerate_id(true);
    // Nouveau token CSRF pour la session authentifiée
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['auth_token'] = $user['auth_token'];
    $_SESSION['username'] = $user['username'];
    header("Location: ../settings/settings.php");
    exit;
  } else {
    header("Location: login.php?error=" . urlencode("Email ou mot de passe incorrect"));
    exit;
  }
} catch (PDOException $e) {
  header("Location: login.php?error=" . urlencode("Erreur lors de la connexion"));
  exit;
}
