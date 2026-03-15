<?php
/**
 * Enregistre ou supprime une subscription Web Push.
 * POST JSON : { csrf_token, endpoint, p256dh, auth, action: "subscribe"|"unsubscribe" }
 */
session_start();
require_once '../database/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['auth_token'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// CSRF
if (($input['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$authToken = $_SESSION['auth_token'];
$action = $input['action'] ?? 'subscribe';

if ($action === 'unsubscribe') {
    $endpoint = $input['endpoint'] ?? '';
    if ($endpoint) {
        $pdo->prepare("DELETE FROM push_subscriptions WHERE auth_token = ? AND endpoint = ?")
            ->execute([$authToken, $endpoint]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// Subscribe
$endpoint = $input['endpoint'] ?? '';
$p256dh = $input['p256dh'] ?? '';
$auth = $input['auth'] ?? '';

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing subscription data']);
    exit;
}

try {
    // Upsert : supprimer l'ancienne subscription pour ce endpoint puis insérer
    $pdo->prepare("DELETE FROM push_subscriptions WHERE auth_token = ? AND endpoint = ?")
        ->execute([$authToken, $endpoint]);

    $pdo->prepare("INSERT INTO push_subscriptions (auth_token, endpoint, p256dh, auth_key) VALUES (?, ?, ?, ?)")
        ->execute([$authToken, $endpoint, $p256dh, $auth]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Push subscribe error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
