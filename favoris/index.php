<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

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
           COALESCE(
               (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
               l.image
           ) AS image,
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
  'vetements' => 'Vêtements', 'electronique' => 'Électronique', 'livres' => 'Livres & Médias',
  'maison' => 'Maison & Jardin', 'sport' => 'Sport & Loisirs', 'vehicules' => 'Véhicules', 'autre' => 'Autre',
];
$conditionLabels = [
  'neuf' => 'Neuf', 'tres_bon_etat' => 'Très bon état', 'bon_etat' => 'Bon état',
  'etat_correct' => 'État correct', 'pour_pieces' => 'Pour pièces',
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title>Mes favoris — Market Plier</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
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
        <h1 class="fav-title"><i class="fa-solid fa-heart"></i> Mes favoris</h1>
        <?php if (!empty($favorites)): ?>
          <span class="fav-count"><?= count($favorites) ?> article<?= count($favorites) > 1 ? 's' : '' ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($favorites)): ?>
        <div class="fav-empty">
          <i class="fa-regular fa-heart"></i>
          <p>Aucun favori pour le moment.</p>
          <span>Parcourez les annonces et ajoutez-en à vos favoris !</span>
          <a href="../shop/search.php" class="fav-browse-btn">
            <i class="fa-solid fa-magnifying-glass"></i> Parcourir les annonces
          </a>
        </div>
      <?php else: ?>
        <div class="fav-grid">
          <?php foreach ($favorites as $fav): ?>
            <div class="fav-card" data-fav-id="<?= (int) $fav['fav_id'] ?>" data-listing-id="<?= (int) $fav['listing_id'] ?>">
              <a href="../shop/buy.php?id=<?= (int) $fav['listing_id'] ?>" class="fav-card-link">
                <div class="fav-card-img">
                  <?php if ($fav['image']): ?>
                    <img src="../uploads/listings/<?= htmlspecialchars($fav['image'], ENT_QUOTES, 'UTF-8') ?>"
                      alt="<?= htmlspecialchars($fav['title'], ENT_QUOTES, 'UTF-8') ?>">
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
              <button class="fav-remove-btn" data-listing-id="<?= (int) $fav['listing_id'] ?>" title="Retirer des favoris">
                <i class="fa-solid fa-heart-crack"></i>
              </button>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
      document.querySelectorAll('.fav-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          var listingId = this.getAttribute('data-listing-id');
          var card = this.closest('.fav-card');
          btn.disabled = true;

          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('listing_id', listingId);

          fetch('../api/toggle_favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.success && !data.favorited) {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(function() { card.remove(); }, 300);
                if (typeof mpShowToast === 'function') {
                  mpShowToast('Retiré des favoris', 'success');
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
