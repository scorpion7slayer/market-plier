<?php

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

require_once __DIR__ . '/../vendor/autoload.php';

function getGoogleClient(): Google\Client
{
    $client = new Google\Client();
    $client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
    $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
    $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? 'https://market-plier.ddev.site/google/google_callback.php');
    $client->addScope('openid');
    $client->addScope('email');
    $client->addScope('profile');
    $client->setAccessType('online');
    $client->setPrompt('select_account');

    // Fix SSL certificate verification on WAMP/Windows
    $caBundle = __DIR__ . '/cacert.pem';
    if (file_exists($caBundle)) {
        $client->setHttpClient(new \GuzzleHttp\Client(['verify' => realpath($caBundle)]));
    }

    return $client;
}
