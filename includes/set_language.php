<?php
session_start();

require_once __DIR__ . '/lang.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ../index.php');
  exit();
}

$language = normalizeLang($_POST['language'] ?? 'fr');
$_SESSION['user_lang'] = $language;

if (isset($_SESSION['auth_token'])) {
  try {
    require_once __DIR__ . '/../database/db.php';

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

    $authToken = $_SESSION['auth_token'];
    $checkStmt = $pdo->prepare("SELECT 1 FROM user_settings WHERE auth_token = ?");
    $checkStmt->execute([$authToken]);

    if ($checkStmt->fetch()) {
      $stmt = $pdo->prepare("UPDATE user_settings SET language = ? WHERE auth_token = ?");
      $stmt->execute([$language, $authToken]);
    } else {
      $stmt = $pdo->prepare("INSERT INTO user_settings (auth_token, language) VALUES (?, ?)");
      $stmt->execute([$authToken, $language]);
    }
  } catch (PDOException $e) {
    error_log('Error saving footer language: ' . $e->getMessage());
  }
}

$redirectTo = $_POST['redirect_to'] ?? '../index.php';
$redirectPath = parse_url($redirectTo, PHP_URL_PATH);
$redirectQuery = parse_url($redirectTo, PHP_URL_QUERY);

if (!is_string($redirectPath) || $redirectPath === '' || preg_match('#^https?://#i', $redirectTo)) {
  $redirectTo = '../index.php';
} else {
  $redirectTo = $redirectPath;
  if (is_string($redirectQuery) && $redirectQuery !== '') {
    $redirectTo .= '?' . $redirectQuery;
  }
}

header('Location: ' . $redirectTo);
exit();
