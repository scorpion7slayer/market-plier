<?php
session_start();

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit();
}

require_once '../config/google_oauth.php';

// Générer un state token CSRF pour OAuth
$state = bin2hex(random_bytes(32));
$_SESSION['google_oauth_state'] = $state;

// Construire l'URL d'autorisation Google
$params = [
  'client_id'     => GOOGLE_CLIENT_ID,
  'redirect_uri'  => GOOGLE_REDIRECT_URI,
  'response_type' => 'code',
  'scope'         => GOOGLE_SCOPES,
  'state'         => $state,
  'access_type'   => 'online',
  'prompt'        => 'select_account',
];

$authUrl = GOOGLE_AUTH_URL . '?' . http_build_query($params);

header('Location: ' . $authUrl);
exit();
