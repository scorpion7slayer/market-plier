<?php

/**
 * Système de traduction global.
 *
 * Inclure ce fichier auto-initialise $_translations depuis la session.
 * Utiliser t('key') pour obtenir une traduction.
 */

function loadTranslations($lang = 'fr')
{
  $allowed = ['fr', 'en', 'es', 'de'];
  if (!in_array($lang, $allowed, true)) {
    $lang = 'fr';
  }

  $file = __DIR__ . '/translations/' . $lang . '.php';
  if (file_exists($file)) {
    return require $file;
  }

  return require __DIR__ . '/translations/fr.php';
}

function t($key, $translations = null)
{
  global $_translations;
  $t = $translations ?? $_translations ?? [];
  return $t[$key] ?? $key;
}

function getUserLang()
{
  // 1. Session (déjà chargé ou défini par save_settings)
  if (isset($_SESSION['user_lang'])) {
    return $_SESSION['user_lang'];
  }

  // 2. DB si connecté et $pdo disponible
  if (isset($_SESSION['auth_token']) && isset($GLOBALS['pdo'])) {
    try {
      $stmt = $GLOBALS['pdo']->prepare("SELECT language FROM user_settings WHERE auth_token = ?");
      $stmt->execute([$_SESSION['auth_token']]);
      $row = $stmt->fetch();
      if ($row && !empty($row['language'])) {
        $_SESSION['user_lang'] = $row['language'];
        return $row['language'];
      }
    } catch (\PDOException $e) {
      // Table n'existe pas encore, on utilise le défaut
    }
  }

  return 'fr';
}

// Auto-initialisation : charger les traductions si pas encore fait
if (!isset($_translations)) {
  $_translations = loadTranslations(getUserLang());
}
