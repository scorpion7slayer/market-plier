<?php

/**
 * Helper de paramètres du site — lit et écrit les options d'administration.
 *
 * La table `site_settings` stocke des paires clé/valeur.
 * Utilise un cache mémoire simple pour éviter de relancer des requêtes dans une même requête.
 */

function ensureSiteSettingsTable(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
        `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
        `setting_value` VARCHAR(255) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ajouter la colonne status aux annonces si elle manque (pour la modération)
    $cols = $pdo->query("SHOW COLUMNS FROM `listings` LIKE 'status'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `listings` ADD COLUMN `status` ENUM('active','pending') NOT NULL DEFAULT 'active'");
    }

    // Ajouter image_data (BLOB) + mime_type à listing_images (stocker l'image en DB)
    $imgCols = $pdo->query("SHOW COLUMNS FROM `listing_images` LIKE 'image_data'")->fetchAll();
    if (empty($imgCols)) {
        $pdo->exec("ALTER TABLE `listing_images` ADD COLUMN `image_data` LONGBLOB DEFAULT NULL");
        $pdo->exec("ALTER TABLE `listing_images` ADD COLUMN `mime_type` VARCHAR(50) DEFAULT NULL");
    }
}

/**
 * Default values for every known setting.
 */
function getDefaultSiteSettings(): array
{
    return [
        'maintenance_mode'   => '0',
        'registration_open'  => '1',
        'moderation_enabled' => '0',
        'listing_limit'      => '0',
        'google_login'       => '1',
    ];
}

/**
 * Return all site settings as an associative array, merged with defaults.
 */
function getSiteSettings(PDO $pdo): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $defaults = getDefaultSiteSettings();

    try {
        ensureSiteSettingsTable($pdo);
        $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
        foreach ($rows as $row) {
            if (array_key_exists($row['setting_key'], $defaults)) {
                $defaults[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (PDOException $e) {
        error_log("getSiteSettings error: " . $e->getMessage());
    }

    $cache = $defaults;
    return $cache;
}

/**
 * Get a single setting value.
 */
function getSiteSetting(PDO $pdo, string $key): string
{
    $all = getSiteSettings($pdo);
    return $all[$key] ?? '';
}

/**
 * Update a single setting. Returns true on success.
 */
function setSiteSetting(PDO $pdo, string $key, string $value): bool
{
    $allowed = array_keys(getDefaultSiteSettings());
    if (!in_array($key, $allowed, true)) {
        return false;
    }

    try {
        ensureSiteSettingsTable($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $stmt->execute([$key, $value]);
        return true;
    } catch (PDOException $e) {
        error_log("setSiteSetting error: " . $e->getMessage());
        return false;
    }
}
