<?php
session_start();

if (isset($_SESSION['auth_token'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../database/db.php';
require_once '../includes/site_settings.php';

if (getSiteSetting($pdo, 'google_login') === '0') {
    header('Location: ../inscription-connexion/login.php?error=' . urlencode("La connexion via Google est actuellement désactivée."));
    exit();
}

require_once 'google_oauth.php';

$client = getGoogleClient();

// Générer un state token CSRF pour OAuth
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;
$client->setState($state);

header('Location: ' . $client->createAuthUrl());
exit();
