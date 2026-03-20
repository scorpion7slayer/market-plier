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

    // Extraire l'adresse de livraison collectée par Stripe
    $deliveryAddress = null;
    if (!empty($session->shipping_details)) {
        $addr = $session->shipping_details->address;
        $deliveryAddress = json_encode([
            'name'        => $session->shipping_details->name ?? '',
            'line1'       => $addr->line1 ?? '',
            'line2'       => $addr->line2 ?? '',
            'city'        => $addr->city ?? '',
            'postal_code' => $addr->postal_code ?? '',
            'country'     => $addr->country ?? '',
        ], JSON_UNESCAPED_UNICODE);
    }

    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO orders (listing_id, buyer_token, seller_token, stripe_session_id, stripe_payment_intent, amount, platform_fee, delivery_address, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')
    ");
    $stmt->execute([
        $listingId,
        $buyerToken,
        $sellerToken,
        $session->id,
        $session->payment_intent,
        $amountTotal,
        $platformFee,
        $deliveryAddress,
    ]);

    // Décrémenter la quantité et marquer comme vendue si stock épuisé
    $pdo->prepare("UPDATE listings SET quantity = GREATEST(0, quantity - 1), status = IF(quantity <= 1, 'sold', status) WHERE id = ? AND status = 'active'")
        ->execute([$listingId]);

    // Récupérer le titre et le nouveau statut de l'annonce
    $listingStmt = $pdo->prepare("SELECT title, status FROM listings WHERE id = ?");
    $listingStmt->execute([$listingId]);
    $listingRow = $listingStmt->fetch();
    $listingTitle = $listingRow ? $listingRow['title'] : 'Article';
    $listingNowSold = $listingRow && $listingRow['status'] === 'sold';

    $buyerStmt = $pdo->prepare("SELECT username FROM users WHERE auth_token = ?");
    $buyerStmt->execute([$buyerToken]);
    $buyerRow = $buyerStmt->fetch();
    $buyerName = $buyerRow ? $buyerRow['username'] : 'Acheteur';

    // Notifier le vendeur : vente réalisée
    sendNotification($pdo, $sellerToken, [
        'type'    => 'sale',
        'title'   => 'Vente réalisée !',
        'content' => $buyerName . ' a acheté « ' . $listingTitle . ' » pour ' . number_format($amountTotal, 2, ',', ' ') . ' €',
        'link'    => 'notifications/',
    ]);

    // Notification supplémentaire si stock épuisé
    if ($listingNowSold) {
        sendNotification($pdo, $sellerToken, [
            'type'    => 'stock',
            'title'   => 'Stock épuisé',
            'content' => 'Votre annonce « ' . $listingTitle . ' » est maintenant épuisée et a été retirée de la vente.',
            'link'    => 'inscription-connexion/account.php',
        ]);
    }
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
