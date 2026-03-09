<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

// Admin-only AJAX endpoint for toggling site settings
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    require_once '../database/db.php';
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

require_once '../includes/site_settings.php';

// Check authentication
if (!isset($_SESSION['auth_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Check admin
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
$stmt->execute([$_SESSION['auth_token']]);
$userData = $stmt->fetch();

if (!$userData || $userData['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

// Validate CSRF
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
    exit;
}

$key = $input['key'] ?? '';
$value = $input['value'] ?? '';

// Validate value is 0 or 1
if (!in_array($value, ['0', '1'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valeur invalide']);
    exit;
}

if (setSiteSetting($pdo, $key, $value)) {
    echo json_encode(['success' => true, 'key' => $key, 'value' => $value]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Clé de paramètre inconnue']);
}
