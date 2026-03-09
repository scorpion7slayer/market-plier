<?php
/**
 * Include this file at the top of public pages to enforce maintenance mode.
 * Admins bypass maintenance mode. Login/register pages are excluded so admins can still log in.
 *
 * Requires $pdo to be available and site_settings.php to be included.
 */
require_once __DIR__ . '/site_settings.php';

if (getSiteSetting($pdo, 'maintenance_mode') === '1') {
    // Let admins through
    $isAdminBypass = false;
    if (isset($_SESSION['auth_token'])) {
        $mStmt = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
        $mStmt->execute([$_SESSION['auth_token']]);
        $mUser = $mStmt->fetch();
        $isAdminBypass = ($mUser && $mUser['is_admin'] == 1);
    }

    if (!$isAdminBypass) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Market Plier - Maintenance</title>
            <link rel="stylesheet" href="/market-plier/node_modules/@fortawesome/fontawesome-free/css/all.min.css">
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    min-height: 100vh; display: flex; align-items: center; justify-content: center;
                    background: #1a1a1a; color: #ccc; font-family: 'Archivo', system-ui, sans-serif;
                }
                .maintenance {
                    text-align: center; padding: 48px 32px;
                    background: #222; border-radius: 18px; max-width: 460px; margin: 20px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
                }
                .maintenance-icon { font-size: 2.5rem; margin-bottom: 16px; }
                .maintenance h1 { font-size: 1.4rem; font-weight: 700; font-style: italic; color: #fff; margin-bottom: 8px; }
                .maintenance p { font-style: italic; color: #999; font-size: 0.95rem; line-height: 1.5; }
            </style>
        </head>
        <body>
            <div class="maintenance">
                <div class="maintenance-icon"><i class="fa-solid fa-wrench"></i></div>
                <h1>Site en maintenance</h1>
                <p>Market Plier est temporairement indisponible pour maintenance. Veuillez réessayer plus tard.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
