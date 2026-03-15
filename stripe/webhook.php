<?php
/**
 * Webhook Stripe — reçoit les événements Stripe et traite les paiements.
 * URL à configurer dans le dashboard Stripe ou via Stripe CLI :
 *   stripe listen --forward-to localhost/market-plier/stripe/webhook.php
 */
require_once '../database/db.php';
require_once '../config/stripe.php';
require_once '../includes/send_notification.php';

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $stripeConfig['webhook_secret']
    );
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit('Invalid payload');
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit('Invalid signature');
}

switch ($event->type) {
    case 'checkout.session.completed':
        handleCheckoutCompleted($event->data->object, $pdo);
        break;

    case 'account.updated':
        handleAccountUpdated($event->data->object, $pdo);
        break;
}

http_response_code(200);
echo json_encode(['received' => true]);

/**
 * Paiement terminé : créer la commande, notifier le vendeur, marquer l'annonce comme vendue.
 */
function handleCheckoutCompleted($session, $pdo)
{
    $listingId = $session->metadata->listing_id ?? null;
    $buyerToken = $session->metadata->buyer_token ?? null;
    $sellerToken = $session->metadata->seller_token ?? null;

    if (!$listingId || !$buyerToken || !$sellerToken) {
        error_log("Webhook: missing metadata in session " . $session->id);
        return;
    }

    // Éviter les doublons
    $check = $pdo->prepare("SELECT id FROM orders WHERE stripe_session_id = ?");
    $check->execute([$session->id]);
    if ($check->fetch()) {
        return;
    }

    $amountTotal = ($session->amount_total ?? 0) / 100;

    // Récupérer le fee depuis le PaymentIntent
    $feePct = 5;
    $platformFee = round($amountTotal * $feePct / 100, 2);

    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO orders (listing_id, buyer_token, seller_token, stripe_session_id, stripe_payment_intent, amount, platform_fee, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')
    ");
    $stmt->execute([
        $listingId,
        $buyerToken,
        $sellerToken,
        $session->id,
        $session->payment_intent,
        $amountTotal,
        $platformFee,
    ]);

    // Marquer l'annonce comme vendue
    $pdo->prepare("UPDATE listings SET status = 'sold' WHERE id = ? AND status = 'active'")
        ->execute([$listingId]);

    // Récupérer le titre de l'annonce et le nom de l'acheteur
    $listingStmt = $pdo->prepare("SELECT title FROM listings WHERE id = ?");
    $listingStmt->execute([$listingId]);
    $listingRow = $listingStmt->fetch();
    $listingTitle = $listingRow ? $listingRow['title'] : 'Article';

    $buyerStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
    $buyerStmt->execute([$buyerToken]);
    $buyerRow = $buyerStmt->fetch();
    $buyerName = $buyerRow ? $buyerRow['username'] : 'Acheteur';

    // Notifier le vendeur (in-app + push)
    sendNotification($pdo, $sellerToken, [
        'type' => 'sale',
        'title' => 'Vente réalisée !',
        'content' => $buyerName . ' a acheté « ' . $listingTitle . ' » pour ' . number_format($amountTotal, 2, ',', ' ') . ' €',
        'link' => 'notifications/',
    ]);
}

/**
 * Compte vendeur mis à jour : vérifier si onboarding complété.
 */
function handleAccountUpdated($account, $pdo)
{
    $transfersActive = isset($account->capabilities->transfers)
        && $account->capabilities->transfers === 'active';

    if ($transfersActive) {
        $pdo->prepare("UPDATE users SET stripe_onboarding_complete = 1 WHERE stripe_account_id = ?")
            ->execute([$account->id]);
    }
}
