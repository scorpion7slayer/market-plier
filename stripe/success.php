<?php
/**
 * Page de succès après paiement Stripe.
 * Affiche la confirmation de commande.
 */
session_start();
require_once '../database/db.php';
require_once '../config/stripe.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

$user = null;
if (isset($_SESSION['auth_token'])) {
    $stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $user = $stmt->fetch();
}

$sessionId = $_GET['session_id'] ?? '';
$order = null;
$listing = null;

if ($sessionId) {
    try {
        $stripeSession = \Stripe\Checkout\Session::retrieve($sessionId);

        $listingId = $stripeSession->metadata->listing_id ?? null;
        if ($listingId) {
            $stmt = $pdo->prepare("SELECT l.*, u.username AS seller_name FROM listings l JOIN users u ON u.auth_token = l.auth_token WHERE l.id = ?");
            $stmt->execute([$listingId]);
            $listing = $stmt->fetch();
        }

        // Chercher la commande
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE stripe_session_id = ?");
        $stmt->execute([$sessionId]);
        $order = $stmt->fetch();
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log("Stripe success page error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '../includes/theme_init.php'; ?>
    <title>Paiement confirmé — Market Plier</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/index.css">
    <link rel="stylesheet" href="../styles/theme.css">
    <style>
        .success-main { max-width: 600px; margin: 60px auto; padding: 0 20px; }
        .success-card {
            background: var(--mp-card-bg, #fff);
            border: 2px solid #7fb885;
            border-radius: 18px;
            padding: 40px;
            text-align: center;
        }
        .success-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(127, 184, 133, 0.15);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px; color: #7fb885;
        }
        .success-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; }
        .success-text { color: var(--mp-text-muted, #666); margin-bottom: 24px; }
        .success-details {
            background: var(--mp-bg-subtle, #f8f9fa);
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin-bottom: 24px;
        }
        .success-row { display: flex; justify-content: space-between; padding: 6px 0; }
        .success-row-label { color: var(--mp-text-muted, #666); }
        .success-row-value { font-weight: 600; }
        .success-total { border-top: 2px solid var(--mp-border, #eee); margin-top: 8px; padding-top: 12px; font-size: 1.1rem; }
        .success-actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .success-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 24px; border-radius: 50px; text-decoration: none;
            font-weight: 600; font-size: 0.95rem; transition: all 0.2s;
        }
        .success-btn-primary { background: #7fb885; color: #fff; }
        .success-btn-primary:hover { background: #6da873; color: #fff; }
        .success-btn-outline { border: 2px solid var(--mp-border, #ddd); color: var(--mp-text, #333); background: transparent; }
        .success-btn-outline:hover { border-color: #7fb885; color: #7fb885; }
    </style>
</head>
<body>
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <main class="success-main">
        <div class="success-card">
            <div class="success-icon">
                <i class="fa-solid fa-check"></i>
            </div>
            <h1 class="success-title">Paiement confirmé !</h1>
            <p class="success-text">Votre achat a été traité avec succès.</p>

            <?php if ($listing): ?>
                <div class="success-details">
                    <div class="success-row">
                        <span class="success-row-label">Article</span>
                        <span class="success-row-value"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="success-row">
                        <span class="success-row-label">Vendeur</span>
                        <span class="success-row-value"><?= htmlspecialchars($listing['seller_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="success-row success-total">
                        <span class="success-row-label">Total payé</span>
                        <span class="success-row-value"><?= number_format((float)$listing['price'], 2, ',', ' ') ?> €</span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="success-actions">
                <a href="../shop/search.php" class="success-btn success-btn-primary">
                    <i class="fa-solid fa-magnifying-glass"></i> Continuer mes achats
                </a>
                <a href="../inscription-connexion/account.php" class="success-btn success-btn-outline">
                    <i class="fa-solid fa-user"></i> Mon profil
                </a>
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    <script src="../styles/theme.js"></script>
</body>
</html>
