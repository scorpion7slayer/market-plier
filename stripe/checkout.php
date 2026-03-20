<?php
/**
 * Crée une Stripe Checkout Session pour acheter une annonce.
 * POST : listing_id, csrf_token
 * Utilise destination charges : le vendeur reçoit le paiement, la plateforme prend 5%.
 */
session_start();
require_once '../database/db.php';
require_once '../config/stripe.php';
require_once '../includes/lang.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit();
}

// CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
    header('Location: ../index.php?error=csrf');
    exit();
}

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
if (!$listingId) {
    header('Location: ../shop/search.php');
    exit();
}

$buyerToken = $_SESSION['auth_token'];

// Récupérer l'annonce + vendeur
$stmt = $pdo->prepare("
    SELECT l.id, l.title, l.price, l.quantity, l.auth_token AS seller_token, l.status,
           u.stripe_account_id, u.stripe_onboarding_complete, u.username AS seller_name
    FROM listings l
    JOIN users u ON u.auth_token = l.auth_token
    WHERE l.id = ?
");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    header('Location: ../shop/search.php?error=' . urlencode('Annonce introuvable.'));
    exit();
}

if ($listing['status'] !== 'active' || (int)($listing['quantity'] ?? 1) < 1) {
    header('Location: ../shop/buy.php?id=' . $listingId . '&error=' . urlencode('Cette annonce n\'est plus disponible.'));
    exit();
}

// Pas s'acheter soi-même
if ($listing['seller_token'] === $buyerToken) {
    header('Location: ../shop/buy.php?id=' . $listingId . '&error=' . urlencode('Vous ne pouvez pas acheter votre propre annonce.'));
    exit();
}

// Vérifier que le vendeur a un compte Stripe actif
if (empty($listing['stripe_account_id']) || !$listing['stripe_onboarding_complete']) {
    header('Location: ../shop/buy.php?id=' . $listingId . '&error=' . urlencode('Ce vendeur n\'a pas encore activé les paiements.'));
    exit();
}


$amountCents = (int) round($listing['price'] * 100);
$feeCents = (int) round($amountCents * $stripeConfig['platform_fee_pct'] / 100);

// Image pour Stripe (première image de l'annonce)
$imgStmt = $pdo->prepare("SELECT id FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC LIMIT 1");
$imgStmt->execute([$listingId]);
$imgRow = $imgStmt->fetch();
$productImages = [];
if ($imgRow) {
    $productImages[] = ($_ENV['APP_URL'] ?? 'http://localhost/market-plier') . '/api/image.php?id=' . $imgRow['id'];
}

try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $listing['title'],
                    'description' => 'Vendu par ' . $listing['seller_name'],
                    'images' => $productImages,
                ],
                'unit_amount' => $amountCents,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'shipping_address_collection' => [
            'allowed_countries' => ['FR', 'BE', 'CH', 'LU', 'MC'],
        ],
        'payment_intent_data' => [
            'application_fee_amount' => $feeCents,
            'transfer_data' => [
                'destination' => $listing['stripe_account_id'],
            ],
            'metadata' => [
                'listing_id' => $listingId,
                'buyer_token' => $buyerToken,
                'seller_token' => $listing['seller_token'],
            ],
        ],
        'metadata' => [
            'listing_id' => $listingId,
            'buyer_token' => $buyerToken,
            'seller_token' => $listing['seller_token'],
        ],
        'success_url' => ($_ENV['APP_URL'] ?? 'http://localhost/market-plier') . '/stripe/success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => ($_ENV['APP_URL'] ?? 'http://localhost/market-plier') . '/shop/buy.php?id=' . $listingId,
    ]);

    header('Location: ' . $session->url);
    exit();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe checkout error: " . $e->getMessage());

    // Si l'erreur vient du compte connecté (vendeur), réinitialiser son flag et afficher un message clair
    $stripeCode = $e->getStripeCode() ?? '';
    $errMsg = $e->getMessage();
    $isAccountError = in_array($stripeCode, ['account_invalid', 'account_country_invalid_address', 'no_account'], true)
        || str_contains($errMsg, 'destination')
        || str_contains($errMsg, 'transfer')
        || str_contains($errMsg, 'connected account')
        || str_contains($errMsg, 'acct_');

    if ($isAccountError) {
        // Réinitialiser le flag onboarding en DB pour forcer le vendeur à re-vérifier
        $pdo->prepare("UPDATE users SET stripe_onboarding_complete = 0 WHERE stripe_account_id = ?")
            ->execute([$listing['stripe_account_id']]);
        header('Location: ../shop/buy.php?id=' . $listingId . '&error=' . urlencode('Ce vendeur ne peut pas encore recevoir de paiements. Veuillez le contacter.'));
    } else {
        header('Location: ../shop/buy.php?id=' . $listingId . '&error=' . urlencode('Erreur lors de la création du paiement. Veuillez réessayer.'));
    }
    exit();
}
