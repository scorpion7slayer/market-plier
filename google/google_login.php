<?php
session_start();

if (isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/dashboard.php');
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
