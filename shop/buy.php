<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';
require_once '../includes/cart.php';

// Utilisateur connecté (pour le header)
$user = null;
if (isset($_SESSION['auth_token'])) {
  $stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $user = $stmt->fetch();
}

// Récupérer l'ID de l'annonce
$listingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$listingId) {
  header('Location: search.php');
  exit();
}

// Récupérer l'annonce avec les infos vendeur
$stmt = $pdo->prepare("
    SELECT l.*, u.username, u.profile_photo, u.auth_token AS seller_token, u.created_at AS seller_since
    FROM listings l
    JOIN users u ON u.auth_token = l.auth_token
    WHERE l.id = ?
");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
  header('Location: search.php');
  exit();
}

// Block access to pending listings for non-owners and non-admins
if (isset($listing['status']) && $listing['status'] === 'pending') {
  $isOwner = (isset($_SESSION['auth_token']) && $_SESSION['auth_token'] === $listing['auth_token']);
  $isAdminView = false;
  if (isset($_SESSION['auth_token'])) {
    $adCheck = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
    $adCheck->execute([$_SESSION['auth_token']]);
    $adRow = $adCheck->fetch();
    $isAdminView = ($adRow && $adRow['is_admin'] == 1);
  }
  if (!$isOwner && !$isAdminView) {
    header('Location: search.php');
    exit();
  }
}

// Récupérer toutes les images de l'annonce
$imgStmt = $pdo->prepare("
    SELECT id, image_path FROM listing_images
    WHERE listing_id = ?
    ORDER BY sort_order ASC
");
$imgStmt->execute([$listingId]);
$imageRows = $imgStmt->fetchAll(PDO::FETCH_ASSOC);

// Build image URLs: prefer api/image.php?id=X, fallback to file path
$images = [];
foreach ($imageRows as $imgRow) {
  $images[] = ['url' => '../api/image.php?id=' . (int)$imgRow['id'], 'id' => (int)$imgRow['id']];
}

// Fallback sur l'image principale si pas d'images dans listing_images
if (empty($images) && !empty($listing['image'])) {
  $images[] = ['url' => '../uploads/listings/' . $listing['image'], 'id' => null];
}

// Compter le nombre d'annonces du vendeur
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE auth_token = ?");
$countStmt->execute([$listing['auth_token']]);
$sellerListingCount = (int) $countStmt->fetchColumn();

// Description du vendeur
$descStmt = $pdo->prepare("SELECT description FROM profile WHERE auth_token = ?");
$descStmt->execute([$listing['auth_token']]);
$sellerProfile = $descStmt->fetch();
$sellerDescription = $sellerProfile ? ($sellerProfile['description'] ?? '') : '';

// Labels
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
$conditionLabels = [
  'neuf'          => t('condition_neuf'),
  'tres_bon_etat' => t('condition_tres_bon_etat'),
  'bon_etat'      => t('condition_bon_etat'),
  'etat_correct'  => t('condition_etat_correct'),
  'pour_pieces'   => t('condition_pour_pieces'),
];

$isOwner = $user && $user['auth_token'] === $listing['auth_token'];
$categoryLabel = $categoryLabels[$listing['category']] ?? $listing['category'];
$categoryIcon  = $categoryIcons[$listing['category']] ?? 'fa-tag';
$conditionLabel = $conditionLabels[$listing['item_condition']] ?? $listing['item_condition'];

// Vérifier si en favori
$isFavorited = false;
if ($user) {
  $favStmt = $pdo->prepare("SELECT id FROM favorites WHERE auth_token = ? AND listing_id = ?");
  $favStmt->execute([$user['auth_token'], $listingId]);
  $isFavorited = (bool) $favStmt->fetch();
}

// Compter les favoris de cette annonce
$favCountStmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE listing_id = ?");
$favCountStmt->execute([$listingId]);
$favoriteCount = (int) $favCountStmt->fetchColumn();
$isInCart = cart_has($listingId);

// Avis sur le vendeur
$reviewsStmt = $pdo->prepare("
    SELECT r.*, u.username, u.profile_photo
    FROM reviews r
    JOIN users u ON u.auth_token = r.reviewer_token
    WHERE r.seller_token = ?
    ORDER BY r.created_at DESC
    LIMIT 10
");
$reviewsStmt->execute([$listing['auth_token']]);
$reviews = $reviewsStmt->fetchAll();

$avgRatingStmt = $pdo->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM reviews WHERE seller_token = ?");
$avgRatingStmt->execute([$listing['auth_token']]);
$ratingStats = $avgRatingStmt->fetch();
$avgRating = $ratingStats['cnt'] > 0 ? round((float) $ratingStats['avg_rating'], 1) : 0;
$reviewCount = (int) $ratingStats['cnt'];

// Vérifier si l'utilisateur a déjà laissé un avis sur ce vendeur pour cette annonce
$hasReviewed = false;
if ($user && !$isOwner) {
  $revCheckStmt = $pdo->prepare("SELECT id FROM reviews WHERE reviewer_token = ? AND seller_token = ? AND listing_id = ?");
  $revCheckStmt->execute([$user['auth_token'], $listing['auth_token'], $listingId]);
  $hasReviewed = (bool) $revCheckStmt->fetch();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars(t('buy_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/buy.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $user;
  include '../header.php';
  ?>

  <main class="buy-main">
    <!-- Fil d'Ariane -->
    <nav class="buy-breadcrumb">
      <a href="search.php"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(t('buy_back_to_results'), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>

    <div class="buy-layout">
      <!-- Colonne gauche : images -->
      <div class="buy-gallery">
        <?php if (!empty($images)): ?>
          <!-- Image principale -->
          <div class="gallery-main" id="galleryMain">
            <img
              src="<?= htmlspecialchars($images[0]['url'], ENT_QUOTES, 'UTF-8') ?>"
              alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>"
              id="mainImage">
            <?php if (count($images) > 1): ?>
              <button class="gallery-nav gallery-nav-prev" id="prevBtn" type="button">
                <i class="fa-solid fa-chevron-left"></i>
              </button>
              <button class="gallery-nav gallery-nav-next" id="nextBtn" type="button">
                <i class="fa-solid fa-chevron-right"></i>
              </button>
              <span class="gallery-counter" id="galleryCounter">1 / <?= count($images) ?></span>
            <?php endif; ?>
          </div>

          <!-- Miniatures -->
          <?php if (count($images) > 1): ?>
            <div class="gallery-thumbs">
              <?php foreach ($images as $i => $img): ?>
                <button
                  class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                  data-index="<?= $i ?>"
                  type="button">
                  <img
                    src="<?= htmlspecialchars($img['url'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="Photo <?= $i + 1 ?>">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="gallery-main gallery-placeholder">
            <i class="fa-solid fa-image"></i>
            <span>Aucune photo</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Colonne droite : infos -->
      <div class="buy-details">
        <!-- Prix + titre -->
        <div class="buy-card buy-card-main">
          <div class="buy-price"><?= number_format((float) $listing['price'], 2, ',', ' ') ?> €</div>
          <h1 class="buy-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h1>

          <div class="buy-tags">
            <span class="buy-tag buy-tag-category">
              <i class="fa-solid <?= $categoryIcon ?>"></i>
              <?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="buy-tag buy-tag-condition">
              <i class="fa-solid fa-circle-info"></i>
              <?= htmlspecialchars($conditionLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>

          <?php if ($listing['location']): ?>
            <div class="buy-location">
              <i class="fa-solid fa-location-dot"></i>
              <?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?>
            </div>
          <?php endif; ?>

          <div class="buy-date">
            <i class="fa-regular fa-clock"></i>
            <?= htmlspecialchars(t('buy_posted_on'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(formatLocalizedDate($listing['created_at']), ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="buy-card buy-card-actions">
          <?php if ($isOwner): ?>
            <a href="edit_listing.php?id=<?= (int) $listing['id'] ?>" class="buy-btn buy-btn-primary">
              <i class="fa-solid fa-pen"></i> <?= htmlspecialchars(t('buy_edit_listing'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <button type="button" class="buy-btn buy-btn-danger" id="openDeleteListingModal">
              <i class="fa-solid fa-trash"></i> <?= htmlspecialchars(t('account_delete'), ENT_QUOTES, 'UTF-8') ?>
            </button>
          <?php else: ?>
            <button class="buy-btn buy-btn-cart <?= $isInCart ? 'buy-btn-cart-active' : '' ?>" id="cartBtn" type="button">
              <i class="fa-solid fa-basket-shopping"></i>
              <span id="cartText"><?= htmlspecialchars($isInCart ? t('cart_remove') : t('cart_add'), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
            <button class="buy-btn buy-btn-primary" id="contactSellerBtn" type="button">
              <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars(t('buy_contact_seller'), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <?php if ($user): ?>
              <button class="buy-btn buy-btn-fav <?= $isFavorited ? 'buy-btn-fav-active' : '' ?>" id="favBtn" type="button">
                <i class="fa-<?= $isFavorited ? 'solid' : 'regular' ?> fa-heart"></i>
                <span id="favText"><?= htmlspecialchars($isFavorited ? t('buy_remove_favorite') : t('buy_add_favorite'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($favoriteCount > 0): ?>
                  <span class="buy-fav-count" id="favCount"><?= $favoriteCount ?></span>
                <?php endif; ?>
              </button>
            <?php endif; ?>
          <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="buy-card buy-card-description">
          <h2 class="buy-section-title"><?= htmlspecialchars(t('buy_description'), ENT_QUOTES, 'UTF-8') ?></h2>
          <div class="buy-description-text">
            <?= nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
        </div>

        <!-- Vendeur -->
        <div class="buy-card buy-card-seller">
          <h2 class="buy-section-title"><?= htmlspecialchars(t('buy_seller'), ENT_QUOTES, 'UTF-8') ?></h2>
          <a href="../inscription-connexion/profile.php?user=<?= urlencode($listing['username']) ?>" class="seller-info seller-link">
            <div class="seller-avatar">
              <?php if ($listing['profile_photo'] && file_exists('../uploads/profiles/' . $listing['profile_photo'])): ?>
                <img src="../uploads/profiles/<?= htmlspecialchars($listing['profile_photo'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($listing['username'], ENT_QUOTES, 'UTF-8') ?>">
              <?php else: ?>
                <i class="fa-solid fa-user"></i>
              <?php endif; ?>
            </div>
            <div class="seller-details">
              <span class="seller-name"><?= htmlspecialchars($listing['username'], ENT_QUOTES, 'UTF-8') ?> <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.7rem;opacity:0.5;"></i></span>
              <span class="seller-meta">
                <i class="fa-solid fa-box-open"></i> <?= $sellerListingCount ?> <?= htmlspecialchars($sellerListingCount > 1 ? t('buy_listings_plural') : t('buy_listings_singular'), ENT_QUOTES, 'UTF-8') ?>
              </span>
              <span class="seller-meta">
                <i class="fa-regular fa-calendar"></i> <?= htmlspecialchars(t('buy_member_since'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars(formatLocalizedMonthYear($listing['seller_since']), ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          </a>
          <?php if ($sellerDescription): ?>
            <p class="seller-bio"><?= nl2br(htmlspecialchars($sellerDescription, ENT_QUOTES, 'UTF-8')) ?></p>
          <?php endif; ?>
          <?php if ($reviewCount > 0): ?>
            <div class="seller-rating">
              <span class="seller-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <i class="fa-<?= $i <= round($avgRating) ? 'solid' : 'regular' ?> fa-star"></i>
                <?php endfor; ?>
              </span>
              <span class="seller-rating-text"><?= $avgRating ?>/5 (<?= $reviewCount ?> <?= htmlspecialchars($reviewCount > 1 ? t('buy_reviews_plural') : t('buy_reviews_singular'), ENT_QUOTES, 'UTF-8') ?>)</span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Avis -->
        <div class="buy-card buy-card-reviews">
          <h2 class="buy-section-title">
            <?= htmlspecialchars(t('buy_reviews'), ENT_QUOTES, 'UTF-8') ?>
            <?php if ($reviewCount > 0): ?>
              <span class="buy-review-avg"><?= $avgRating ?>/5</span>
            <?php endif; ?>
          </h2>

          <?php if ($user && !$isOwner && !$hasReviewed): ?>
            <form class="review-form" id="reviewForm">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="seller_token" value="<?= htmlspecialchars($listing['auth_token'], ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">
              <div class="review-stars-input" id="starsInput">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <button type="button" class="review-star-btn" data-rating="<?= $i ?>">
                    <i class="fa-regular fa-star"></i>
                  </button>
                <?php endfor; ?>
                <input type="hidden" name="rating" id="ratingInput" value="0">
              </div>
              <textarea name="comment" class="review-textarea" placeholder="<?= htmlspecialchars(t('buy_review_comment_placeholder'), ENT_QUOTES, 'UTF-8') ?>" maxlength="1000"></textarea>
              <button type="submit" class="review-submit-btn" id="reviewSubmitBtn" disabled>
                <i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars(t('buy_submit_review'), ENT_QUOTES, 'UTF-8') ?>
              </button>
            </form>
          <?php endif; ?>

          <?php if (empty($reviews)): ?>
            <p class="review-empty"><?= htmlspecialchars(t('buy_no_reviews'), ENT_QUOTES, 'UTF-8') ?></p>
          <?php else: ?>
            <div class="review-list">
              <?php foreach ($reviews as $rev): ?>
                <div class="review-item">
                  <div class="review-header">
                    <div class="review-author">
                      <div class="review-author-avatar">
                        <?php if ($rev['profile_photo'] && file_exists('../uploads/profiles/' . $rev['profile_photo'])): ?>
                          <img src="../uploads/profiles/<?= htmlspecialchars($rev['profile_photo'], ENT_QUOTES, 'UTF-8') ?>" alt="">
                        <?php else: ?>
                          <img src="../assets/images/default-avatar.svg" alt="">
                        <?php endif; ?>
                      </div>
                      <span class="review-author-name"><?= htmlspecialchars($rev['username'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <div class="review-meta">
                      <span class="review-stars-display">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                          <i class="fa-<?= $i <= $rev['rating'] ? 'solid' : 'regular' ?> fa-star"></i>
                        <?php endfor; ?>
                      </span>
                      <span class="review-date"><?= htmlspecialchars(formatLocalizedDate($rev['created_at']), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  </div>
                  <?php if ($rev['comment']): ?>
                    <p class="review-comment"><?= nl2br(htmlspecialchars($rev['comment'], ENT_QUOTES, 'UTF-8')) ?></p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <?php include '../footer.php'; ?>

  <?php if ($user && !$isOwner): ?>
  <!-- Modal contacter le vendeur -->
  <div class="confirm-modal-overlay" id="contactOverlay">
    <div class="confirm-modal" style="max-width: 480px;">
      <div class="confirm-modal-icon" style="background: rgba(127, 184, 133, 0.1);">
        <i class="fa-solid fa-envelope" style="color: #7fb885;"></i>
      </div>
      <h3 class="confirm-modal-title"><?= htmlspecialchars(t('buy_contact_seller'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($listing['username'], ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="confirm-modal-text" style="margin-bottom: 12px;">
        <?= htmlspecialchars(t('buy_about_listing'), ENT_QUOTES, 'UTF-8') ?> « <strong><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></strong> »
      </p>
      <form id="contactForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="seller_token" value="<?= htmlspecialchars($listing['auth_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="listing_id" value="<?= (int) $listingId ?>">
        <textarea name="message" class="contact-textarea" placeholder="<?= htmlspecialchars(t('buy_contact_placeholder'), ENT_QUOTES, 'UTF-8') ?>" rows="4" maxlength="2000" required></textarea>
        <div class="confirm-modal-actions">
          <button type="button" class="confirm-modal-btn confirm-modal-btn-cancel" id="contactCancel"><?= htmlspecialchars(t('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="submit" class="confirm-modal-btn" style="background:#7fb885;color:#fff;" id="contactSend">
            <i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars(t('msg_send'), ENT_QUOTES, 'UTF-8') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($isOwner): ?>
  <!-- Modal suppression annonce -->
  <div class="confirm-modal-overlay" id="deleteListingOverlay">
    <div class="confirm-modal">
      <div class="confirm-modal-icon">
        <i class="fa-solid fa-trash-alt"></i>
      </div>
      <h3 class="confirm-modal-title"><?= htmlspecialchars(t('buy_delete_listing_title'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="confirm-modal-text">
        <?= htmlspecialchars(t('buy_delete_listing_text'), ENT_QUOTES, 'UTF-8') ?> « <strong><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></strong> ».
      </p>
      <div class="confirm-modal-actions">
        <button type="button" class="confirm-modal-btn confirm-modal-btn-cancel" id="deleteListingCancel"><?= htmlspecialchars(t('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
        <a href="delete_listing.php?id=<?= (int) $listing['id'] ?>" class="confirm-modal-btn confirm-modal-btn-danger">
          <i class="fa-solid fa-trash-alt"></i> <?= htmlspecialchars(t('account_delete'), ENT_QUOTES, 'UTF-8') ?>
        </a>
      </div>
    </div>
  </div>
  <script>
    (function() {
      var overlay = document.getElementById('deleteListingOverlay');
      document.getElementById('openDeleteListingModal').addEventListener('click', function() {
        overlay.classList.add('visible');
      });
      function close() { overlay.classList.remove('visible'); }
      document.getElementById('deleteListingCancel').addEventListener('click', close);
      overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });
      document.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
    })();
  </script>
  <?php endif; ?>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var images = <?= json_encode(array_map(function ($img) {
                      return $img['url'];
                    }, $images)) ?>;
      var currentIndex = 0;
      var mainImg = document.getElementById('mainImage');
      var counter = document.getElementById('galleryCounter');
      var thumbs = document.querySelectorAll('.gallery-thumb');

      function showImage(index) {
        if (!mainImg || images.length === 0) return;
        currentIndex = (index + images.length) % images.length;
        mainImg.src = images[currentIndex];
        if (counter) counter.textContent = (currentIndex + 1) + ' / ' + images.length;
        thumbs.forEach(function(t) {
          t.classList.remove('active');
        });
        if (thumbs[currentIndex]) thumbs[currentIndex].classList.add('active');
      }

      var prevBtn = document.getElementById('prevBtn');
      var nextBtn = document.getElementById('nextBtn');
      if (prevBtn) prevBtn.addEventListener('click', function() {
        showImage(currentIndex - 1);
      });
      if (nextBtn) nextBtn.addEventListener('click', function() {
        showImage(currentIndex + 1);
      });

      thumbs.forEach(function(thumb) {
        thumb.addEventListener('click', function() {
          showImage(parseInt(this.getAttribute('data-index')));
        });
      });

      // Navigation clavier
      document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') showImage(currentIndex - 1);
        if (e.key === 'ArrowRight') showImage(currentIndex + 1);
      });
    })();
  </script>

  <?php if (!$isOwner): ?>
  <script>
    (function() {
      var basePath = '../';
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
      var i18n = <?= json_encode([
                    'error' => t('generic_error'),
                    'favorite_added' => t('buy_favorite_added'),
                    'favorite_removed' => t('buy_favorite_removed'),
                    'review_sent' => t('buy_review_sent'),
                    'cart_added' => t('cart_added'),
                    'cart_removed' => t('cart_removed'),
                  ]) ?>;

      // ═══ CART TOGGLE ═══════════════════════════════════
      var cartBtn = document.getElementById('cartBtn');
      if (cartBtn) {
        cartBtn.addEventListener('click', function() {
          cartBtn.disabled = true;

          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('listing_id', <?= (int) $listingId ?>);
          fd.append('action', 'toggle');

          fetch(basePath + 'api/toggle_cart.php', {
              method: 'POST',
              body: fd,
              credentials: 'same-origin'
            })
            .then(function(r) {
              return r.json();
            })
            .then(function(data) {
              if (!data.success) {
                if (typeof mpShowToast === 'function') mpShowToast(data.error || i18n.error, 'error');
                return;
              }

              var cartText = document.getElementById('cartText');
              if (data.in_cart) {
                cartBtn.classList.add('buy-btn-cart-active');
                cartText.textContent = <?= json_encode(t('cart_remove')) ?>;
                if (typeof mpShowToast === 'function') mpShowToast(i18n.cart_added, 'success');
              } else {
                cartBtn.classList.remove('buy-btn-cart-active');
                cartText.textContent = <?= json_encode(t('cart_add')) ?>;
                if (typeof mpShowToast === 'function') mpShowToast(i18n.cart_removed, 'success');
              }

              if (typeof window.mpUpdateCartBadges === 'function') {
                window.mpUpdateCartBadges(data.count || 0);
              }
            })
            .catch(function() {
              if (typeof mpShowToast === 'function') mpShowToast(i18n.error, 'error');
            })
            .finally(function() {
              cartBtn.disabled = false;
            });
        });
      }

      // ═══ CONTACT SELLER ════════════════════════════════
      var contactBtn = document.getElementById('contactSellerBtn');
      var contactOverlay = document.getElementById('contactOverlay');
      var contactForm = document.getElementById('contactForm');
      var contactCancel = document.getElementById('contactCancel');

      if (contactBtn && contactOverlay) {
        contactBtn.addEventListener('click', function() {
          contactOverlay.classList.add('visible');
        });
        function closeContact() { contactOverlay.classList.remove('visible'); }
        contactCancel.addEventListener('click', closeContact);
        contactOverlay.addEventListener('click', function(e) { if (e.target === contactOverlay) closeContact(); });

        contactForm.addEventListener('submit', function(e) {
          e.preventDefault();
          var sendBtn = document.getElementById('contactSend');
          sendBtn.disabled = true;

          fetch(basePath + 'api/start_conversation.php', {
              method: 'POST',
              body: new FormData(contactForm),
              credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.success) {
                window.location.href = basePath + 'messagerie/conversation.php?id=' + data.conversation_id;
              } else {
                if (typeof mpShowToast === 'function') mpShowToast(data.error || i18n.error, 'error');
                sendBtn.disabled = false;
              }
            })
            .catch(function() {
              sendBtn.disabled = false;
            });
        });
      }

      // ═══ FAVORITE TOGGLE ═══════════════════════════════
      var favBtn = document.getElementById('favBtn');
      if (favBtn) {
        favBtn.addEventListener('click', function() {
          favBtn.disabled = true;
          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('listing_id', <?= (int) $listingId ?>);

          fetch(basePath + 'api/toggle_favorite.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.success) {
                var icon = favBtn.querySelector('i');
                var text = document.getElementById('favText');
                if (data.favorited) {
                  favBtn.classList.add('buy-btn-fav-active');
                  icon.className = 'fa-solid fa-heart';
                  text.textContent = <?= json_encode(t('buy_remove_favorite')) ?>;
                  if (typeof mpShowToast === 'function') mpShowToast(i18n.favorite_added, 'success');
                } else {
                  favBtn.classList.remove('buy-btn-fav-active');
                  icon.className = 'fa-regular fa-heart';
                  text.textContent = <?= json_encode(t('buy_add_favorite')) ?>;
                  if (typeof mpShowToast === 'function') mpShowToast(i18n.favorite_removed, 'success');
                }
              }
            })
            .finally(function() { favBtn.disabled = false; });
        });
      }

      // ═══ REVIEW STARS ══════════════════════════════════
      var starsInput = document.getElementById('starsInput');
      var ratingInput = document.getElementById('ratingInput');
      var reviewForm = document.getElementById('reviewForm');
      var submitBtn = document.getElementById('reviewSubmitBtn');

      if (starsInput) {
        var starBtns = starsInput.querySelectorAll('.review-star-btn');
        starBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var rating = parseInt(this.getAttribute('data-rating'));
            ratingInput.value = rating;
            submitBtn.disabled = false;
            starBtns.forEach(function(b, idx) {
              var icon = b.querySelector('i');
              icon.className = idx < rating ? 'fa-solid fa-star' : 'fa-regular fa-star';
            });
          });
        });

        reviewForm.addEventListener('submit', function(e) {
          e.preventDefault();
          if (ratingInput.value === '0') return;
          submitBtn.disabled = true;

          fetch(basePath + 'api/submit_review.php', {
              method: 'POST',
              body: new FormData(reviewForm),
              credentials: 'same-origin'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.success) {
                if (typeof mpShowToast === 'function') mpShowToast(i18n.review_sent, 'success');
                reviewForm.style.display = 'none';
              } else {
                if (typeof mpShowToast === 'function') mpShowToast(data.error || i18n.error, 'error');
                submitBtn.disabled = false;
              }
            })
            .catch(function() { submitBtn.disabled = false; });
        });
      }
    })();
  </script>
  <?php endif; ?>
</body>

</html>
