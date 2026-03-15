<?php
/**
 * Crée un compte Stripe Express pour le vendeur et redirige vers l'onboarding Stripe.
 */
session_start();
require_once '../database/db.php';
require_once '../config/stripe.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

$authToken = $_SESSION['auth_token'];

// Récupérer l'utilisateur
$stmt = $pdo->prepare("SELECT username, email, stripe_account_id, stripe_onboarding_complete FROM users WHERE auth_token = ?");
$stmt->execute([$authToken]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../index.php');
    exit();
}

// Vérifier que le compte Stripe existe toujours (peut avoir été supprimé)
if (!empty($user['stripe_account_id'])) {
    try {
        \Stripe\Account::retrieve($user['stripe_account_id']);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        // Compte supprimé ou inexistant — réinitialiser
        $pdo->prepare("UPDATE users SET stripe_account_id = NULL, stripe_onboarding_complete = 0 WHERE auth_token = ?")
            ->execute([$authToken]);
        $user['stripe_account_id'] = null;
        $user['stripe_onboarding_complete'] = 0;
    }
}

// Si déjà onboardé, rediriger vers le dashboard Stripe Express
if ($user['stripe_onboarding_complete'] && !empty($user['stripe_account_id'])) {
    $loginLink = \Stripe\Account::createLoginLink($user['stripe_account_id']);
    header('Location: ' . $loginLink->url);
    exit();
}

try {
    // Créer le compte Express si pas encore fait
    if (empty($user['stripe_account_id'])) {
        $account = \Stripe\Account::create([
            'type' => 'express',
            'email' => $user['email'],
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'business_profile' => [
                'name' => $user['username'],
                'product_description' => 'Vendeur sur Market Plier',
            ],
        ]);

        $pdo->prepare("UPDATE users SET stripe_account_id = ?, stripe_onboarding_complete = 0 WHERE auth_token = ?")
            ->execute([$account->id, $authToken]);

        $stripeAccountId = $account->id;
    } else {
        $stripeAccountId = $user['stripe_account_id'];
    }

    // Créer le lien d'onboarding
    $accountLink = \Stripe\AccountLink::create([
        'account' => $stripeAccountId,
        'type' => 'account_onboarding',
        'refresh_url' => ($_ENV['APP_URL'] ?? 'http://localhost/market-plier') . '/stripe/onboarding.php',
        'return_url' => ($_ENV['APP_URL'] ?? 'http://localhost/market-plier') . '/stripe/onboarding_return.php',
    ]);

    header('Location: ' . $accountLink->url);
    exit();

} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe onboarding error: " . $e->getMessage());
    header('Location: ../settings/settings.php?error=' . urlencode('Erreur Stripe : ' . $e->getMessage()));
    exit();
}
