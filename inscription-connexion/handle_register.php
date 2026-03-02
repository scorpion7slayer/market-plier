<?php
session_start();

try {
  require '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (register): " . $e->getMessage());
  header("Location: register.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
  exit;
}

// Vérification CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
  header("Location: register.php?error=" . urlencode("Token de sécurité invalide. Veuillez réessayer."));
  exit;
}

// Token CSRF par session : pas de régénération après chaque requête

if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password']) || empty($_POST['confirm_password'])) {
  header("Location: register.php?error=" . urlencode("Veuillez remplir tous les champs"));
  exit;
}

if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
  header("Location: register.php?error=" . urlencode("Format d'email invalide"));
  exit;
}

if ($_POST['password'] !== $_POST['confirm_password']) {
  header("Location: register.php?error=" . urlencode("Les mots de passe ne correspondent pas"));
  exit;
}

if (strlen($_POST['password']) < 6) {
  header("Location: register.php?error=" . urlencode("Mot de passe trop court (min 6 caractères)"));
  exit;
}

// Valider le nom d'utilisateur (alphanumérique uniquement)
if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $_POST['username'])) {
  header("Location: register.php?error=" . urlencode("Nom d'utilisateur invalide (3-30 caractères, alphanumériques et underscore uniquement)"));
  exit;
}

$password_hash = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);

// Vérifier si l'email est déjà utilisé via Google
try {
  $checkGoogle = $pdo->prepare("SELECT auth_provider FROM users WHERE email = ?");
  $checkGoogle->execute([$_POST['email']]);
  $existing = $checkGoogle->fetch();

  if ($existing) {
    if ($existing['auth_provider'] === 'google') {
      header("Location: register.php?error=" . urlencode("Cette adresse email est déjà associée à un compte Google. Veuillez utiliser le bouton Google pour vous connecter."));
      exit;
    }
    // Vérifier plus précisément si c'est le username ou l'email qui est en doublon
    $checkUsername = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
    $checkUsername->execute([$_POST['username']]);
    if ($checkUsername->fetch()) {
      header("Location: register.php?error=" . urlencode("Ce nom d'utilisateur est déjà utilisé."));
    } else {
      header("Location: register.php?error=" . urlencode("Cette adresse email est déjà utilisée."));
    }
    exit;
  }

  // Vérifier si le username existe déjà
  $checkUsername = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
  $checkUsername->execute([$_POST['username']]);
  if ($checkUsername->fetch()) {
    header("Location: register.php?error=" . urlencode("Ce nom d'utilisateur est déjà utilisé."));
    exit;
  }
} catch (PDOException $e) {
  error_log("Register check error: " . $e->getMessage());
  header("Location: register.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
  exit;
}

// Générer un token d'authentification sécurisé
$auth_token = bin2hex(random_bytes(32));

try {
  $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, auth_token, auth_provider) VALUES (?, ?, ?, ?, 'local')");
  $stmt->execute([$_POST['username'], $_POST['email'], $password_hash, $auth_token]);
  header("Location: login.php?success=" . urlencode("Compte créé avec succès"));
  exit;
} catch (PDOException $e) {
  if ($e->getCode() == 23000) {
    header("Location: register.php?error=" . urlencode("Ce nom d'utilisateur ou email existe déjà"));
  } else {
    error_log("Register error: " . $e->getMessage());
    header("Location: register.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
  }
  exit;
}
