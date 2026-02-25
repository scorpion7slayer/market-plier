<?php

// Load environment variables from .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_NAME'] ?? 'marketplier';
$dbUser = $_ENV['DB_USER'] ?? 'theo';
$dbPass = $_ENV['DB_PASS'] ?? '';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

$pdoOptions = [
     PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES => false,
];

try {
     $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $ex) {
     throw new PDOException($ex->getMessage(), (int)$ex->getCode());
}
