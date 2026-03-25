<?php
session_start();
require_once '../database/db.php';

if (!isset($_SESSION['auth_token'])) {
    http_response_code(401);
    exit();
}

$myToken = $_SESSION['auth_token'];
$conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
$lastId = filter_input(INPUT_GET, 'after', FILTER_VALIDATE_INT) ?: 0;

// Libérer le verrou de session pour ne pas bloquer les autres requêtes
session_write_close();

if (!$conversationId) {
    http_response_code(400);
    exit();
}

// Vérifier participation
$stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (user1_token = ? OR user2_token = ?)");
$stmt->execute([$conversationId, $myToken, $myToken]);
if (!$stmt->fetch()) {
    http_response_code(403);
    exit();
}

// En-têtes SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Désactiver le buffering PHP
set_time_limit(0);
@ob_end_clean();
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);

// Confirmation de connexion
echo ": connected\n\n";
flush();

$lastReportedReadId = 0;
$keepaliveAt = time() + 15;
$endAt = time() + 300; // Fermeture après 5 min pour libérer les ressources

while (time() < $endAt) {
    if (connection_aborted()) break;

    try {
        // Nouveaux messages
        $stmt = $pdo->prepare("
            SELECT m.id, m.sender_token, m.content, m.created_at
            FROM messages m
            WHERE m.conversation_id = ? AND m.id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversationId, $lastId]);
        $rows = $stmt->fetchAll();

        if ($rows) {
            // Marquer les messages reçus comme lus
            $pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_token != ? AND is_read = 0")
                ->execute([$conversationId, $myToken]);

            $messages = [];
            foreach ($rows as $r) {
                if ((int) $r['id'] > $lastId) $lastId = (int) $r['id'];
                $messages[] = [
                    'id'           => (int) $r['id'],
                    'sender_token' => $r['sender_token'],
                    'content'      => $r['content'],
                    'utc'          => $r['created_at'],
                ];
            }

            echo "id: {$lastId}\n";
            echo "event: messages\n";
            echo "data: " . json_encode(['messages' => $messages]) . "\n\n";
            flush();
        }

        // Accusés de lecture pour mes messages envoyés
        $stmt = $pdo->prepare("
            SELECT id FROM messages
            WHERE conversation_id = ? AND sender_token = ? AND is_read = 1 AND id > ?
        ");
        $stmt->execute([$conversationId, $myToken, $lastReportedReadId]);
        $newReadIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($newReadIds) {
            $lastReportedReadId = max(array_map('intval', $newReadIds));
            echo "event: read_receipts\n";
            echo "data: " . json_encode(['read_ids' => array_map('intval', $newReadIds)]) . "\n\n";
            flush();
        }
    } catch (PDOException $e) {
        // Erreur DB silencieuse, on continue
    }

    // Keepalive toutes les 15s pour maintenir la connexion
    if (time() >= $keepaliveAt) {
        echo ": ka\n\n";
        flush();
        $keepaliveAt = time() + 15;
    }

    sleep(1);
}

// Signal de timeout : le client va se reconnecter
echo "event: timeout\n";
echo "data: {}\n\n";
flush();
