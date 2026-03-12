<?php

/**
 * Système de traduction global.
 *
 * Inclure ce fichier auto-initialise $_translations depuis la session.
 * Utiliser t('key') pour obtenir une traduction.
 */

function normalizeLang($lang)
{
  $allowed = ['fr', 'en', 'es', 'de'];
  return in_array($lang, $allowed, true) ? $lang : 'fr';
}

function loadTranslations($lang = 'fr')
{
  $lang = normalizeLang($lang);

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
    $_SESSION['user_lang'] = normalizeLang($_SESSION['user_lang']);
    return $_SESSION['user_lang'];
  }

  // 2. DB si connecté et $pdo disponible
  if (isset($_SESSION['auth_token']) && isset($GLOBALS['pdo'])) {
    try {
      $stmt = $GLOBALS['pdo']->prepare("SELECT language FROM user_settings WHERE auth_token = ?");
      $stmt->execute([$_SESSION['auth_token']]);
      $row = $stmt->fetch();
      if ($row && !empty($row['language'])) {
        $_SESSION['user_lang'] = normalizeLang($row['language']);
        return $_SESSION['user_lang'];
      }
    } catch (\PDOException $e) {
      // Table n'existe pas encore, on utilise le défaut
    }
  }

  return 'fr';
}

function getUserLocale()
{
  $localeMap = [
    'fr' => 'fr-FR',
    'en' => 'en-US',
    'es' => 'es-ES',
    'de' => 'de-DE',
  ];

  return $localeMap[getUserLang()] ?? 'fr-FR';
}

function formatLocalizedDate($dateTime, $dateType = 2, $timeType = -1)
{
  $timestamp = strtotime((string) $dateTime);
  if ($timestamp === false) {
    return '';
  }

  if (class_exists(\IntlDateFormatter::class)) {
    $formatter = new \IntlDateFormatter(
      getUserLocale(),
      $dateType,
      $timeType,
      date_default_timezone_get()
    );

    $formatted = $formatter->format($timestamp);
    if ($formatted !== false) {
      return $formatted;
    }
  }

  if ($dateType === -1 && $timeType !== -1) {
    return date('H:i', $timestamp);
  }

  if ($dateType === 1) {
    return date('d/m/Y', $timestamp);
  }

  return date('d/m/Y', $timestamp);
}

function formatLocalizedMonthYear($dateTime)
{
  $timestamp = strtotime((string) $dateTime);
  if ($timestamp === false) {
    return '';
  }

  if (class_exists(\IntlDateFormatter::class)) {
    $formatter = new \IntlDateFormatter(
      getUserLocale(),
      -1,
      -1,
      date_default_timezone_get(),
      null,
      'MM/yyyy'
    );

    $formatted = $formatter->format($timestamp);
    if ($formatted !== false) {
      return $formatted;
    }
  }

  return date('m/Y', $timestamp);
}

// Auto-initialisation : charger les traductions si pas encore fait
if (!isset($_translations)) {
  $_translations = loadTranslations(getUserLang());
}
