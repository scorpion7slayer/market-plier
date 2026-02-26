<?php
// FICHIER TEMPORAIRE - À SUPPRIMER APRÈS DEBUG

$envFile = __DIR__ . '/.env';
$envExists = file_exists($envFile);

if ($envExists) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "<pre>";
echo ".env trouvé : " . ($envExists ? "OUI" : "NON") . "\n\n";
echo "GOOGLE_CLIENT_ID    : " . ($_ENV['GOOGLE_CLIENT_ID'] ?? '(vide)') . "\n";
echo "GOOGLE_CLIENT_SECRET: " . (isset($_ENV['GOOGLE_CLIENT_SECRET']) ? substr($_ENV['GOOGLE_CLIENT_SECRET'], 0, 10) . '...' : '(vide)') . "\n";
echo "GOOGLE_REDIRECT_URI : " . ($_ENV['GOOGLE_REDIRECT_URI'] ?? '(vide)') . "\n\n";

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
$client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
$client->addScope('openid');
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');

echo "URL générée par le client :\n";
echo $client->createAuthUrl() . "\n";
echo "</pre>";
