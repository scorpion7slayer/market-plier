<?php
session_start();

require_once 'google_oauth.php';

try {
    require '../database/db.php';
} catch (PDOException $e) {
    error_log("DB connection error (google_callback): " . $e->getMessage());
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Une erreur interne est survenue. Veuillez réessayer plus tard."));
    exit;
}

// --- Vérification des erreurs Google ---
if (isset($_GET['error'])) {
    $errorMsg = $_GET['error'] === 'access_denied'
        ? "Vous avez annulé la connexion avec Google."
        : "Erreur lors de la connexion avec Google.";
    header("Location: ../inscription-connexion/login.php?error=" . urlencode($errorMsg));
    exit;
}

// --- Vérification du code d'autorisation ---
if (empty($_GET['code'])) {
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Code d'autorisation manquant."));
    exit;
}

// --- Vérification du state token CSRF ---
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Token de sécurité invalide. Veuillez réessayer."));
    exit;
}

unset($_SESSION['google_oauth_state']);

$client = getGoogleClient();

// --- Échange du code contre un token d'accès ---
$tokenData = $client->fetchAccessTokenWithAuthCode($_GET['code']);

if (isset($tokenData['error'])) {
    error_log("Google token error: " . json_encode($tokenData));
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Erreur lors de l'authentification Google. Veuillez réessayer."));
    exit;
}

$client->setAccessToken($tokenData);

// --- Récupérer le profil utilisateur Google ---
try {
    $oauth2Service = new Google\Service\Oauth2($client);
    $googleUser    = $oauth2Service->userinfo->get();
} catch (Google\Service\Exception $e) {
    error_log("Google userinfo error: " . $e->getMessage());
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Impossible de récupérer votre profil Google."));
    exit;
}

$googleId    = $googleUser->getId();
$googleEmail = $googleUser->getEmail();
$googleName  = $googleUser->getName() ?? '';

if (empty($googleId) || empty($googleEmail)) {
    error_log("Google userinfo incomplete: id=$googleId email=$googleEmail");
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Profil Google incomplet. Veuillez réessayer."));
    exit;
}

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
        $_SESSION['username']   = $user['username'];
        header("Location: ../settings/settings.php");
        exit;
    }

    // Cas B : Recherche par email (compte local existant)
    $stmt = $pdo->prepare("SELECT auth_token, username, email, google_id, auth_provider FROM users WHERE email = ?");
    $stmt->execute([$googleEmail]);
    $user = $stmt->fetch();

    if ($user) {
        // Liaison du compte Google avec le compte local existant
        $stmt = $pdo->prepare("UPDATE users SET google_id = ?, auth_provider = 'both' WHERE auth_token = ?");
        $stmt->execute([$googleId, $user['auth_token']]);

        session_regenerate_id(true);
        $_SESSION['auth_token'] = $user['auth_token'];
        $_SESSION['username']   = $user['username'];
        header("Location: ../settings/settings.php");
        exit;
    }

    // Cas C : Nouvel utilisateur -> création du compte
    $baseUsername = '';
    if (!empty($googleName)) {
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', $googleName));
    }
    if (empty($baseUsername) || strlen($baseUsername) < 3) {
        $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', explode('@', $googleEmail)[0]);
    }
    $baseUsername = substr($baseUsername, 0, 25);
    if (strlen($baseUsername) < 3) {
        $baseUsername = 'user';
    }

    // S'assurer que le username est unique
    $username = $baseUsername;
    $suffix   = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT auth_token FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            break;
        }
        $username = $baseUsername . '_' . $suffix;
        $suffix++;
    }

    $auth_token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, auth_provider, auth_token, password_hash) VALUES (?, ?, ?, 'google', ?, NULL)");
    $stmt->execute([$username, $googleEmail, $googleId, $auth_token]);

    session_regenerate_id(true);
    $_SESSION['auth_token'] = $auth_token;
    $_SESSION['username']   = $username;
    header("Location: ../settings/settings.php");
    exit;
} catch (PDOException $e) {
    error_log("Google OAuth DB error: " . $e->getMessage());
    header("Location: ../inscription-connexion/login.php?error=" . urlencode("Une erreur est survenue lors de la création du compte. Veuillez réessayer."));
    exit;
}
