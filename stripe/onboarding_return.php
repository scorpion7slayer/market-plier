<?php
/**
 * Callback retour après onboarding Stripe Express.
 * Vérifie le statut du compte et met à jour la DB.
 */
session_start();
require_once '../database/db.php';
require_once '../config/stripe.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

$authToken = $_SESSION['auth_token'];

$stmt = $pdo->prepare("SELECT stripe_account_id FROM users WHERE auth_token = ?");
$stmt->execute([$authToken]);
$user = $stmt->fetch();

if (!$user || empty($user['stripe_account_id'])) {
    header('Location: ../settings/settings.php?error=' . urlencode('Compte Stripe introuvable.'));
    exit();
}

try {
    $account = \Stripe\Account::retrieve($user['stripe_account_id']);

    $transfersActive = isset($account->capabilities->transfers)
        && $account->capabilities->transfers === 'active';

    if ($transfersActive) {
        $pdo->prepare("UPDATE users SET stripe_onboarding_complete = 1 WHERE auth_token = ?")
            ->execute([$authToken]);
        header('Location: ../settings/settings.php?success=' . urlencode('Compte vendeur activé ! Vous pouvez maintenant recevoir des paiements.'));
    } else {
        header('Location: ../settings/settings.php?error=' . urlencode('Vérification Stripe incomplète. Veuillez réessayer.'));
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    error_log("Stripe return error: " . $e->getMessage());
    header('Location: ../settings/settings.php?error=' . urlencode('Erreur lors de la vérification Stripe.'));
}
exit();
