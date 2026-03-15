<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

if (!isset($_SESSION['auth_token'])) {
  header('Location: ../inscription-connexion/login.php');
  exit();
}

$myToken = $_SESSION['auth_token'];

$stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
$stmt->execute([$myToken]);
$user = $stmt->fetch();

if (!$user) {
  session_destroy();
  header('Location: ../index.php');
  exit();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les favoris avec les infos des annonces
$stmt = $pdo->prepare("
    SELECT f.id AS fav_id, f.created_at AS fav_date,
           l.id AS listing_id, l.title, l.price, l.category, l.item_condition, l.location,
           (SELECT li.id FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1) AS image_id,
           COALESCE(
               (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
               l.image
           ) AS image_path,
           u.username AS seller_name
    FROM favorites f 
    JOIN listings l ON l.id = f.listing_id
    JOIN users u ON u.auth_token = l.auth_token
    WHERE f.auth_token = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$myToken]);
$favorites = $stmt->fetchAll();

$categoryLabels = [
  'vetements' => t('cat_vetements'),
  'electronique' => t('cat_electronique'),
  'livres' => t('cat_livres'),
  'maison' => t('cat_maison'),
  'sport' => t('cat_sport'),
  'vehicules' => t('cat_vehicules'),
  'autre' => t('cat_autre'),
];
$conditionLabels = [
  'neuf' => t('condition_neuf'),
  'tres_bon_etat' => t('condition_tres_bon_etat'),
  'bon_etat' => t('condition_bon_etat'),
  'etat_correct' => t('condition_etat_correct'),
  'pour_pieces' => t('condition_pour_pieces'),
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars(t('favorites_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/search.css">
  <link rel="stylesheet" href="../styles/favoris.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $user;
  include '../header.php';
  ?>

  <main class="fav-main">
    <div class="fav-container">
      <div class="fav-header">
        <h1 class="fav-title"><i class="fa-solid fa-heart"></i> <?= htmlspecialchars(t('favorites_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($favorites)): ?>
          <span class="fav-count"><?= count($favorites) ?> <?= htmlspecialchars(count($favorites) > 1 ? t('favorites_items_plural') : t('favorites_items_singular'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($favorites)): ?>
        <div class="fav-empty">
          <i class="fa-regular fa-heart"></i>
          <p><?= htmlspecialchars(t('favorites_empty'), ENT_QUOTES, 'UTF-8') ?></p>
          <span><?= htmlspecialchars(t('favorites_empty_sub'), ENT_QUOTES, 'UTF-8') ?></span>
          <a href="../shop/search.php" class="category-chip">
            <i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('favorites_browse'), ENT_QUOTES, 'UTF-8') ?>
          </a>
        </div>
      <?php else: ?>
        <div class="fav-grid">
          <?php foreach ($favorites as $fav): ?>
            <div class="fav-card" data-fav-id="<?= (int) $fav['fav_id'] ?>" data-listing-id="<?= (int) $fav['listing_id'] ?>">
              <a href="../shop/buy.php?id=<?= (int) $fav['listing_id'] ?>" class="fav-card-link">
                <div class="fav-card-img">
                  <?php if ($fav['image_id']): ?>
                    <img src="../api/image.php?id=<?= (int) $fav['image_id'] ?>"
                      alt="<?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                  <?php elseif ($fav['image_path']): ?>
                    <img src="../uploads/listings/<?= htmlspecialchars($fav['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                      alt="<?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                  <?php else: ?>
                    <div class="fav-card-placeholder"><i class="fa-solid fa-image"></i></div>
                  <?php endif; ?>
                  <span class="fav-card-condition">
                    <?= htmlspecialchars($conditionLabels[$fav['item_condition']] ?? $fav['item_condition'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
                <div class="fav-card-body">
                  <div class="fav-card-price"><?= number_format((float) $fav['price'], 2, ',', ' ') ?> €</div>
                  <div class="fav-card-title"><?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?></div>
                  <div class="fav-card-seller">
                    <i class="fa-solid fa-user"></i> <?= htmlspecialchars($fav['seller_name'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                  <?php if ($fav['location']): ?>
                    <div class="fav-card-location">
                      <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($fav['location'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                  <?php endif; ?>
                </div>
              </a>
              <button class="fav-remove-btn" data-listing-id="<?= (int) $fav['listing_id'] ?>" title="<?= htmlspecialchars(t('favorites_remove'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-heart-crack"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include '../footer.php'; ?>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
      var i18n = <?= json_encode([
                    'favorites_removed' => t('favorites_removed'),
                  ]) ?>;

      document.querySelectorAll('.fav-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          var listingId = this.getAttribute('data-listing-id');
          var card = this.closest('.fav-card');
          btn.disabled = true;

          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('listing_id', listingId);

          fetch('../api/toggle_favorite.php', {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            })
            .then(function(r) {
              return r.json();
            })
            .then(function(data) {
              if (data.success && !data.favorited) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(function() {
                  card.remove();
                }, 300);
                if (typeof mpShowToast === 'function') {
                  mpShowToast(i18n.favorites_removed, 'success');
                }
              }
            })
            .catch(function() {
              btn.disabled = false;
            });
        });
      });
    })();
  </script>
</body>

</html>
