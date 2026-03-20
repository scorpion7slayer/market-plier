<?php
/**
 * Mes achats — historique et suivi des commandes de l'acheteur.
 */
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

$authToken = $_SESSION['auth_token'];

$user = null;
$stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
$stmt->execute([$authToken]);
$user = $stmt->fetch();

// Récupérer toutes les commandes de l'acheteur
$ordersStmt = $pdo->prepare("
    SELECT o.*,
           l.title   AS listing_title,
           l.id      AS listing_id,
           l.category,
           u.username AS seller_name,
           (SELECT id FROM listing_images WHERE listing_id = l.id ORDER BY sort_order ASC LIMIT 1) AS image_id
    FROM orders o
    JOIN listings l ON l.id = o.listing_id
    JOIN users u ON u.auth_token = o.seller_token
    WHERE o.buyer_token = ?
    ORDER BY o.created_at DESC
");
$ordersStmt->execute([$authToken]);
$orders = $ordersStmt->fetchAll();

$totalSpent = array_sum(array_column($orders, 'amount'));

$categoryLabels = [
    'vetements'    => t('cat_vetements'),
    'electronique' => t('cat_electronique'),
    'livres'       => t('cat_livres'),
    'maison'       => t('cat_maison'),
    'sport'        => t('cat_sport'),
    'vehicules'    => t('cat_vehicules'),
    'autre'        => t('cat_autre'),
];

$statusConfig = [
    'completed' => ['label' => 'Payé',        'color' => '#7fb885', 'bg' => 'rgba(127,184,133,0.14)', 'icon' => 'fa-check'],
    'refunded'  => ['label' => 'Remboursé',   'color' => '#b38600', 'bg' => 'rgba(255,193,7,0.12)',   'icon' => 'fa-rotate-left'],
    'pending'   => ['label' => 'En attente',  'color' => 'var(--mp-text-muted)', 'bg' => 'var(--mp-border-light)', 'icon' => 'fa-clock'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '../includes/theme_init.php'; ?>
    <title>Mes achats — Market Plier</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/index.css">
    <link rel="stylesheet" href="../styles/search.css">
    <link rel="stylesheet" href="../styles/cart.css">
    <link rel="stylesheet" href="../styles/theme.css">
    <style>
        .order-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
        }
        .order-address-block {
            margin-top: 4px;
            padding: 10px 14px;
            background: var(--mp-border-light);
            border-radius: 12px;
            font-size: 0.83rem;
            color: var(--mp-text-secondary);
            line-height: 1.6;
        }
        .order-address-block strong {
            color: var(--mp-text);
            display: block;
            margin-bottom: 2px;
        }
        .cart-summary-total {
            padding: 14px 0 0;
            display: flex;
            justify-content: space-between;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--mp-text);
        }
    </style>
</head>

<body>
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <main class="cart-main">
        <div class="cart-shell">
            <div class="cart-top">
                <div>
                    <h1 class="cart-title">
                        <i class="fa-solid fa-bag-shopping"></i> Mes achats
                    </h1>
                    <p class="cart-subtitle">Historique et suivi de vos commandes.</p>
                </div>
                <?php if (!empty($orders)): ?>
                    <span class="cart-count"><?= count($orders) ?> <?= count($orders) > 1 ? 'commandes' : 'commande' ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($orders)): ?>
                <section class="cart-empty">
                    <i class="fa-solid fa-bag-shopping"></i>
                    <h2>Aucun achat pour l'instant</h2>
                    <p>Vous n'avez pas encore effectué de commande. Parcourez les annonces pour trouver ce qu'il vous faut.</p>
                    <a href="../shop/search.php" class="category-chip">
                        <i class="fa-solid fa-magnifying-glass"></i> Parcourir les annonces
                    </a>
                </section>
            <?php else: ?>
                <div class="cart-layout">
                    <section class="cart-list">
                        <?php foreach ($orders as $order):
                            $delivery = !empty($order['delivery_address'])
                                ? json_decode($order['delivery_address'], true)
                                : null;
                            $sc = $statusConfig[$order['status']] ?? $statusConfig['pending'];
                            $date = date('d/m/Y à H:i', strtotime($order['created_at']));
                            $catLabel = $categoryLabels[$order['category']] ?? ($order['category'] ?? '');
                        ?>
                        <article class="cart-card">
                            <a href="../shop/buy.php?id=<?= (int)$order['listing_id'] ?>" class="cart-card-media">
                                <?php if ($order['image_id']): ?>
                                    <img src="../api/image.php?id=<?= (int)$order['image_id'] ?>"
                                         alt="<?= htmlspecialchars($order['listing_title'], ENT_QUOTES, 'UTF-8') ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <span class="cart-card-placeholder"><i class="fa-solid fa-image"></i></span>
                                <?php endif; ?>
                            </a>

                            <div class="cart-card-body">
                                <div class="cart-card-head">
                                    <div>
                                        <div class="cart-card-price"><?= number_format((float)$order['amount'], 2, ',', ' ') ?> €</div>
                                        <a href="../shop/buy.php?id=<?= (int)$order['listing_id'] ?>" class="cart-card-title">
                                            <?= htmlspecialchars($order['listing_title'], ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </div>
                                    <span class="order-status-badge" style="background:<?= $sc['bg'] ?>; color:<?= $sc['color'] ?>; flex-shrink:0;">
                                        <i class="fa-solid <?= $sc['icon'] ?>"></i>
                                        <?= $sc['label'] ?>
                                    </span>
                                </div>

                                <div class="cart-card-meta">
                                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($order['seller_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($catLabel): ?>
                                        <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                    <span><i class="fa-regular fa-clock"></i> <?= $date ?></span>
                                    <?php if ($delivery): ?>
                                        <span><i class="fa-solid fa-truck"></i> Livraison</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($delivery):
                                    $addrLines = array_filter([
                                        $delivery['name']    ?? '',
                                        $delivery['line1']   ?? '',
                                        $delivery['line2']   ?? '',
                                        trim(($delivery['postal_code'] ?? '') . ' ' . ($delivery['city'] ?? '')),
                                        $delivery['country'] ?? '',
                                    ]);
                                ?>
                                <div class="order-address-block">
                                    <strong><i class="fa-solid fa-location-dot"></i> Adresse de livraison</strong>
                                    <?= implode('<br>', array_map(fn($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $addrLines)) ?>
                                </div>
                                <?php endif; ?>

                                <div class="cart-card-actions">
                                    <a href="../shop/buy.php?id=<?= (int)$order['listing_id'] ?>" class="cart-secondary-link">
                                        <i class="fa-solid fa-eye"></i> Voir l'annonce
                                    </a>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </section>

                    <aside class="cart-summary">
                        <h2>Récapitulatif</h2>
                        <div class="cart-summary-row">
                            <span>Commandes</span>
                            <strong><?= count($orders) ?></strong>
                        </div>
                        <div class="cart-summary-row">
                            <span>Payées</span>
                            <strong><?= count(array_filter($orders, fn($o) => $o['status'] === 'completed')) ?></strong>
                        </div>
                        <div class="cart-summary-row">
                            <span>Remboursées</span>
                            <strong><?= count(array_filter($orders, fn($o) => $o['status'] === 'refunded')) ?></strong>
                        </div>
                        <div class="cart-summary-total">
                            <span>Total dépensé</span>
                            <span><?= number_format($totalSpent, 2, ',', ' ') ?> €</span>
                        </div>
                        <p class="cart-summary-note">Les remboursements peuvent prendre 5 à 10 jours ouvrés selon votre banque.</p>
                        <a href="../shop/search.php" class="cart-secondary-link cart-secondary-link-full">
                            <i class="fa-solid fa-arrow-left"></i> Continuer mes achats
                        </a>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    <script src="../styles/theme.js"></script>
</body>

</html>
