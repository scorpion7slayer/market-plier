<?php
session_start();

require_once '../config/google_oauth.php';

// Chemin vers le bundle de certificats CA (nécessaire sur WAMP/Windows)
define('CURL_CA_BUNDLE', 'C:/wamp64/bin/php/php8.2.29/extras/ssl/cacert.pem');

try {
  require '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (google_callback): " . $e->getMessage());
  header("Location: login.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
  exit;
}

// --- Vérification des erreurs Google ---
if (isset($_GET['error'])) {
  $errorMsg = $_GET['error'] === 'access_denied'
    ? "Vous avez annulé la connexion avec Google."
    : "Erreur lors de la connexion avec Google.";
  header("Location: login.php?error=" . urlencode($errorMsg));
  exit;
}

// --- Vérification du code d'autorisation ---
if (empty($_GET['code'])) {
  header("Location: login.php?error=" . urlencode("Code d'autorisation manquant."));
  exit;
}

// --- Vérification du state token CSRF ---
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
  header("Location: login.php?error=" . urlencode("Token de sécurité invalide. Veuillez réessayer."));
  exit;
}

// Supprimer le state utilisé
unset($_SESSION['google_oauth_state']);

// --- Échanger le code contre un access token ---
$tokenData = [
  'code'          => $_GET['code'],
  'client_id'     => GOOGLE_CLIENT_ID,
  'client_secret' => GOOGLE_CLIENT_SECRET,
  'redirect_uri'  => GOOGLE_REDIRECT_URI,
  'grant_type'    => 'authorization_code',
];

$ch = curl_init(GOOGLE_TOKEN_URL);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($tokenData),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
  CURLOPT_TIMEOUT        => 15,
  CURLOPT_CAINFO         => CURL_CA_BUNDLE,
]);
$tokenResponse = curl_exec($ch);
$tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$tokenError = curl_error($ch);
curl_close($ch);

if ($tokenError || $tokenHttpCode !== 200) {
  error_log("Google token error: HTTP $tokenHttpCode - $tokenError - Response: $tokenResponse");
  header("Location: login.php?error=" . urlencode("Erreur lors de l'authentification Google. Veuillez réessayer."));
  exit;
}

$tokenResult = json_decode($tokenResponse, true);
if (empty($tokenResult['access_token'])) {
  error_log("Google token missing access_token: $tokenResponse");
  header("Location: login.php?error=" . urlencode("Erreur lors de l'authentification Google. Veuillez réessayer."));
  exit;
}

$accessToken = $tokenResult['access_token'];

// --- Récupérer le profil utilisateur Google ---
$ch = curl_init(GOOGLE_USERINFO_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
  CURLOPT_TIMEOUT        => 15,
  CURLOPT_CAINFO         => CURL_CA_BUNDLE,
]);
$userResponse = curl_exec($ch);
$userHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$userError = curl_error($ch);
curl_close($ch);

if ($userError || $userHttpCode !== 200) {
  error_log("Google userinfo error: HTTP $userHttpCode - $userError - Response: $userResponse");
  header("Location: login.php?error=" . urlencode("Impossible de récupérer votre profil Google."));
  exit;
}

$googleUser = json_decode($userResponse, true);
if (empty($googleUser['sub']) || empty($googleUser['email'])) {
  error_log("Google userinfo incomplete: $userResponse");
  header("Location: login.php?error=" . urlencode("Profil Google incomplet. Veuillez réessayer."));
  exit;
}

$googleId    = $googleUser['sub'];
$googleEmail = $googleUser['email'];
$googleName  = $googleUser['name'] ?? '';

// --- Recherche de l'utilisateur en BDD ---
try {
  // Cas A : Recherche par google_id
  $stmt = $pdo->prepare("SELECT auth_token, username, email, google_id, auth_provider FROM users WHERE google_id = ?");
  $stmt->execute([$googleId]);
  $user = $stmt->fetch();

  if ($user) {
    // Compte Google déjà lié -> connexion directe
    session_regenerate_id(true);
    $_SESSION['auth_token'] = $user['auth_token'];
    $_SESSION['username'] = $user['username'];
    header("Location: dashboard.php");
    exit;
  }

  // Cas B : Recherche par email (compte local existant)
  $stmt = $pdo->prepare("SELECT auth_token, id, username, email, google_id, auth_provider FROM users WHERE email = ?");
  $stmt->execute([$googleEmail]);
  $user = $stmt->fetch();

  if ($user) {
    // Liaison du compte Google avec le compte local existant
    $stmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'both' WHERE id = ?");
    $stmt->execute([$googleId, $user['id']]);

    session_regenerate_id(true);
    $_SESSION['auth_token'] = $user['auth_token'];
    $_SESSION['username'] = $user['username'];
    header("Location: dashboard.php");
    exit;
  }

  // Cas C : Nouvel utilisateur -> création du compte
  $baseUsername = '';
  if (!empty($googleName)) {
    // Dériver un username depuis le nom Google : supprimer les caractères non autorisés
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $googleName));
  }
  if (empty($baseUsername) || strlen($baseUsername) < 3) {
    // Fallback : utiliser la partie locale de l'email
    $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $googleEmail)[0]);
  }
  // Tronquer à 25 caractères pour laisser de la place au suffixe
  $baseUsername = substr($baseUsername, 0, 25);
  if (strlen($baseUsername) < 3) {
    $baseUsername = 'user';
  }

  // S'assurer que le username est unique
  $username = $baseUsername;
  $suffix = 1;
  while (true) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
      break;
    }
    $username = $baseUsername . '_' . $suffix;
    $suffix++;
  }

  // Générer un token d'authentification sécurisé
  $auth_token = bin2hex(random_bytes(32));

  $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, auth_provider, auth_token, password_hash) VALUES (?, ?, ?, 'google', ?, NULL)");
  $stmt->execute([$username, $googleEmail, $googleId, $auth_token]);

  session_regenerate_id(true);
  $_SESSION['auth_token'] = $auth_token;
  $_SESSION['username'] = $username;
  header("Location: dashboard.php");
  exit;

} catch (PDOException $e) {
  error_log("Google OAuth DB error: " . $e->getMessage());
  header("Location: login.php?error=" . urlencode("Une erreur est survenue lors de la création du compte. Veuillez réessayer."));
  exit;
}
