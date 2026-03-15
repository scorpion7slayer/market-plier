<?php
/**
 * Configuration Stripe Connect (marketplace).
 * Les clés sont lues depuis .env (chargé par database/db.php).
 */

require_once __DIR__ . '/../vendor/autoload.php';

$stripeConfig = [
    'secret_key'      => $_ENV['STRIPE_SECRET_KEY'] ?? '',
    'public_key'      => $_ENV['STRIPE_PUBLIC_KEY'] ?? '',
    'webhook_secret'  => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
    'platform_fee_pct' => 5, // 5 % de commission plateforme
];

\Stripe\Stripe::setApiKey($stripeConfig['secret_key']);
