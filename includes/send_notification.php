<?php
/**
 * Helper : créer une notification in-app + envoyer un Web Push.
 *
 * Usage :
 *   sendNotification($pdo, $authToken, [
 *       'type'    => 'sale',        // sale, message, system...
 *       'title'   => 'Titre',
 *       'content' => 'Corps du message',
 *       'link'    => 'notifications/',  // chemin relatif
 *   ]);
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

function sendNotification(PDO $pdo, string $authToken, array $data): void
{
    $type = $data['type'] ?? 'system';
    $title = $data['title'] ?? 'Market Plier';
    $content = $data['content'] ?? '';
    $link = $data['link'] ?? null;

    // 1. Insérer notification en base
    $stmt = $pdo->prepare("
        INSERT INTO notifications (auth_token, type, title, content, link, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt->execute([$authToken, $type, $title, $content, $link]);

    // 2. Envoyer Web Push à tous les appareils de l'utilisateur
    sendWebPush($pdo, $authToken, [
        'title' => $title,
        'body'  => $content,
        'link'  => $link ? ($_ENV['APP_URL'] ?? '/market-plier') . '/' . $link : ($_ENV['APP_URL'] ?? '/market-plier') . '/',
    ]);
}

function sendWebPush(PDO $pdo, string $authToken, array $payload): void
{
    // Charger .env si pas encore fait
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile) && empty($_ENV['VAPID_PUBLIC_KEY'])) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }

    $vapidPublic = $_ENV['VAPID_PUBLIC_KEY'] ?? '';
    $vapidPrivate = $_ENV['VAPID_PRIVATE_KEY'] ?? '';
    $vapidSubject = $_ENV['VAPID_SUBJECT'] ?? 'mailto:contact@market-plier.com';

    if (empty($vapidPublic) || empty($vapidPrivate)) {
        return;
    }

    // Récupérer les subscriptions
    $stmt = $pdo->prepare("SELECT endpoint, p256dh, auth_key FROM push_subscriptions WHERE auth_token = ?");
    $stmt->execute([$authToken]);
    $subscriptions = $stmt->fetchAll();

    if (empty($subscriptions)) {
        return;
    }

    $auth = [
        'VAPID' => [
            'subject' => $vapidSubject,
            'publicKey' => $vapidPublic,
            'privateKey' => $vapidPrivate,
        ],
    ];

    try {
        $webPush = new WebPush($auth);

        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh'],
                'authToken' => $sub['auth_key'],
            ]);

            $webPush->queueNotification($subscription, json_encode($payload));
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                // Supprimer la subscription expirée
                $endpoint = $report->getRequest()->getUri()->__toString();
                $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ? AND auth_token = ?")
                    ->execute([$endpoint, $authToken]);
            }
        }
    } catch (\Exception $e) {
        error_log("Web Push error: " . $e->getMessage());
    }
}
