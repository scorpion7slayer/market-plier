<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['auth_token'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Non authentifié']);
  exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['csrf_token'])) {
  http_response_code(400);
  echo json_encode(['error' => 'Données invalides']);
  exit();
}

if ($input['csrf_token'] !== $_SESSION['csrf_token']) {
  http_response_code(403);
  echo json_encode(['error' => 'Token CSRF invalide']);
  exit();
}

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Erreur de connexion']);
  exit();
}

$authToken = $_SESSION['auth_token'];

// Colonnes autorisées (whitelist stricte)
$allowedBool = [
  'notif_email',
  'notif_price_alerts',
  'notif_weekly_summary',
  'privacy_public_profile',
  'privacy_show_email',
  'privacy_activity_history',
];
$allowedLanguages = ['fr', 'en', 'es', 'de'];

// Créer la table si elle n'existe pas
try {
  $pdo->query("SELECT 1 FROM user_settings LIMIT 1");
} catch (PDOException $e) {
  $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
    auth_token VARCHAR(64) NOT NULL PRIMARY KEY,
    notif_email TINYINT(1) NOT NULL DEFAULT 1,
    notif_price_alerts TINYINT(1) NOT NULL DEFAULT 0,
    notif_weekly_summary TINYINT(1) NOT NULL DEFAULT 1,
    privacy_public_profile TINYINT(1) NOT NULL DEFAULT 1,
    privacy_show_email TINYINT(1) NOT NULL DEFAULT 0,
    privacy_activity_history TINYINT(1) NOT NULL DEFAULT 1,
    language VARCHAR(5) NOT NULL DEFAULT 'fr',
    CONSTRAINT fk_user_settings_auth FOREIGN KEY (auth_token) REFERENCES users(auth_token) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci");
}

$setting = $input['setting'] ?? null;
$value = $input['value'] ?? null;

if ($setting === null || $value === null) {
  http_response_code(400);
  echo json_encode(['error' => 'Paramètres manquants']);
  exit();
}

// Valider le champ via whitelist
if (in_array($setting, $allowedBool, true)) {
  $value = $value ? 1 : 0;
  $column = $setting;
} elseif ($setting === 'language') {
  if (!in_array($value, $allowedLanguages, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Langue non supportée']);
    exit();
  }
  $column = 'language';
} else {
  http_response_code(400);
  echo json_encode(['error' => 'Paramètre inconnu']);
  exit();
}

// Mapping colonne -> placeholder (pas d'interpolation directe)
$columnMap = [
  'notif_email' => 'notif_email',
  'notif_price_alerts' => 'notif_price_alerts',
  'notif_weekly_summary' => 'notif_weekly_summary',
  'privacy_public_profile' => 'privacy_public_profile',
  'privacy_show_email' => 'privacy_show_email',
  'privacy_activity_history' => 'privacy_activity_history',
  'language' => 'language',
];

$safeColumn = $columnMap[$column];

try {
  $checkStmt = $pdo->prepare("SELECT 1 FROM user_settings WHERE auth_token = ?");
  $checkStmt->execute([$authToken]);

  if ($checkStmt->fetch()) {
    $sql = "UPDATE user_settings SET {$safeColumn} = ? WHERE auth_token = ?";
    $pdo->prepare($sql)->execute([$value, $authToken]);
  } else {
    $sql = "INSERT INTO user_settings (auth_token, {$safeColumn}) VALUES (?, ?)";
    $pdo->prepare($sql)->execute([$authToken, $value]);
  }

  // Stocker la langue en session pour les autres pages
  if ($setting === 'language') {
    $_SESSION['user_lang'] = $value;
  }

  echo json_encode(['success' => true]);
} catch (PDOException $e) {
  error_log("Error saving setting: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Erreur de sauvegarde']);
}
