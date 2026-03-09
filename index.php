<?php
session_start();
require_once 'database/db.php';
require_once 'includes/remember_me.php';
require_once 'includes/maintenance_check.php';
require_once 'includes/lang.php';

// Générer un token CSRF uniquement s'il n'existe pas encore.
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['auth_token'])) {
  $stmt = $pdo->prepare("SELECT auth_token, username, email, profile_photo FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $user = $stmt->fetch();

  // Compte supprimé par un admin : nettoyer la session
  if (!$user) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(ini_get('session.name'), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax'
      ]);
    }
    session_destroy();
    header('Location: index.php?account_deleted=1');
    exit();
  }
}
$user = $user ?? null;
$isAdmin = false;
if ($user) {
  try {
    $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
    $checkAdmin->execute([$_SESSION['auth_token']]);
    $userData = $checkAdmin->fetch();
    $isAdmin = ($userData && $userData['is_admin'] == 1);
  } catch (PDOException $ex) {
    $isAdmin = false;
  }
}

// --- Données pour la page d'accueil ---

$categoryLabels = [
  'vetements'    => t('cat_vetements'),
  'electronique' => t('cat_electronique'),
  'livres'       => t('cat_livres'),
  'maison'       => t('cat_maison'),
  'sport'        => t('cat_sport'),
  'vehicules'    => t('cat_vehicules'),
  'autre'        => t('cat_autre'),
];
$categoryIcons = [
  'vetements'    => 'fa-shirt',
  'electronique' => 'fa-laptop',
  'livres'       => 'fa-book',
  'maison'       => 'fa-house',
  'sport'        => 'fa-futbol',
  'vehicules'    => 'fa-car',
  'autre'        => 'fa-ellipsis',
];

// Articles tendances : les plus mis en favoris
$trendingStmt = $pdo->query("
    SELECT l.id, l.title, l.price, l.category,
           (SELECT li.id FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1) AS image_id,
           COALESCE(
               (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
               l.image
           ) AS image_path,
           COUNT(f.id) AS fav_count
    FROM listings l
    LEFT JOIN favorites f ON f.listing_id = l.id
    WHERE l.status = 'active'
    GROUP BY l.id
    ORDER BY fav_count DESC, l.created_at DESC
    LIMIT 8
");
$trending = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Dernières annonces
$recentStmt = $pdo->query("
    SELECT l.id, l.title, l.price, l.category, l.item_condition, l.location, l.created_at,
           (SELECT li.id FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1) AS image_id,
           COALESCE(
               (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
               l.image
           ) AS image_path,
           u.username
    FROM listings l
    LEFT JOIN users u ON u.auth_token = l.auth_token
    WHERE l.status = 'active'
    ORDER BY l.created_at DESC
    LIMIT 12
");
$recent = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Nombre d'annonces par catégorie
$catCountStmt = $pdo->query("
    SELECT category, COUNT(*) AS cnt
    FROM listings
    WHERE status = 'active'
    GROUP BY category
    ORDER BY cnt DESC
");
$categoryCounts = [];
foreach ($catCountStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
  $categoryCounts[$row['category']] = (int) $row['cnt'];
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php include 'includes/theme_init.php'; ?>
  <title>Market Plier</title>
  <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css" />
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="stylesheet" href="styles/search.css" />
  <link rel="stylesheet" href="styles/theme.css" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
</head>

<body>
  <?php
  $headerBasePath = './';
  $headerUser = $user;
  include 'header.php';
  ?>

  <?php if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1'): ?>
    <div style="max-width: 500px; margin: 40px auto; padding: 32px; background: var(--mp-card-bg, #fff); border-radius: 18px; box-shadow: 0 8px 32px var(--mp-card-shadow, rgba(0,0,0,0.08)); text-align: center; font-family: 'Archivo', sans-serif;">
      <div style="width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: rgba(231, 76, 60, 0.1); display: flex; align-items: center; justify-content: center;">
        <i class="fa-solid fa-user-slash" style="font-size: 1.3rem; color: #e74c3c;"></i>
      </div>
      <h3 style="font-weight: 700; font-style: italic; font-size: 1.1rem; color: var(--mp-text, #111); margin-bottom: 8px;"><?= htmlspecialchars(t('index_account_deleted'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p style="font-style: italic; font-size: 0.9rem; color: var(--mp-text-muted, #888); line-height: 1.5; margin-bottom: 0;">
        <?= htmlspecialchars(t('index_account_deleted_text'), ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
  <?php endif; ?>

  <main class="home-main">
    <?php if ($user): ?>
      <div class="greeting"><?= htmlspecialchars(t('index_hello'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($user['username']) ?></div>
    <?php else: ?>
      <div class="greeting"><?= htmlspecialchars(t('index_welcome'), ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <!-- Catégories -->
    <section>
      <div class="section-title"><?= htmlspecialchars(t('index_categories'), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="category-filters">
        <?php foreach ($categoryLabels as $catKey => $catLabel): ?>
          <a href="shop/search.php?category=<?= $catKey ?>" class="category-chip">
            <i class="fa-solid <?= $categoryIcons[$catKey] ?>"></i>
            <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
            <?php if (isset($categoryCounts[$catKey])): ?>
              <span class="cat-count">(<?= $categoryCounts[$catKey] ?>)</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- Articles tendances -->
    <?php if (!empty($trending)): ?>
      <section>
        <div class="section-title"><?= htmlspecialchars(t('index_trending'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="home-scroll-row">
          <?php foreach ($trending as $t): ?>
            <a href="shop/buy.php?id=<?= (int) $t['id'] ?>" class="home-trending-card">
              <div class="home-trending-img">
                <?php if ($t['image_id']): ?>
                  <img src="api/image.php?id=<?= (int) $t['image_id'] ?>" alt="<?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php elseif ($t['image_path']): ?>
                  <img src="uploads/listings/<?= htmlspecialchars($t['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php else: ?>
                  <div class="listing-card-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
              </div>
              <div class="home-trending-info">
                <span class="home-trending-price"><?= number_format((float)$t['price'], 2, ',', ' ') ?> €</span>
                <span class="home-trending-title"><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <!-- Dernières annonces -->
    <?php if (!empty($recent)): ?>
      <section>
        <div class="section-title"><?= htmlspecialchars(t('index_recent'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="search-results">
          <?php foreach ($recent as $listing): ?>
            <a href="shop/buy.php?id=<?= (int) $listing['id'] ?>" class="listing-card">
              <div class="listing-card-img">
                <?php if ($listing['image_id']): ?>
                  <img src="api/image.php?id=<?= (int) $listing['image_id'] ?>" alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php elseif ($listing['image_path']): ?>
                  <img src="uploads/listings/<?= htmlspecialchars($listing['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                <?php else: ?>
                  <div class="listing-card-placeholder"><i class="fa-solid fa-image"></i></div>
                <?php endif; ?>
              </div>
              <div class="listing-card-body">
                <div class="listing-card-price"><?= number_format((float) $listing['price'], 2, ',', ' ') ?> €</div>
                <div class="listing-card-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="listing-card-meta">
                  <?php if ($listing['location']): ?>
                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <span class="listing-card-category">
                    <i class="fa-solid <?= $categoryIcons[$listing['category']] ?? 'fa-tag' ?>"></i>
                    <?= htmlspecialchars($categoryLabels[$listing['category']] ?? $listing['category'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="home-see-all">
          <a href="shop/search.php" class="load-more-btn">
            <i class="fa-solid fa-arrow-right"></i> <?= htmlspecialchars(t('index_see_all'), ENT_QUOTES, 'UTF-8') ?>
          </a>
        </div>
      </section>
    <?php else: ?>
      <section>
        <div class="section-title"><?= htmlspecialchars(t('index_listings'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="no-results">
          <i class="fa-solid fa-store"></i>
          <p><?= htmlspecialchars(t('index_no_listings'), ENT_QUOTES, 'UTF-8') ?></p>
          <span><?= htmlspecialchars(t('index_be_first'), ENT_QUOTES, 'UTF-8') ?></span>
          <div style="margin-top: 16px;">
            <a href="shop/sell.php" class="load-more-btn"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(t('index_post_listing'), ENT_QUOTES, 'UTF-8') ?></a>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <script src="styles/theme.js"></script>
</body>

</html>