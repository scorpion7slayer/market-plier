<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

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

// Récupérer toutes les images de l'annonce
$imgStmt = $pdo->prepare("
    SELECT image_path FROM listing_images
    WHERE listing_id = ?
    ORDER BY sort_order ASC
");
$imgStmt->execute([$listingId]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

// Fallback sur l'image principale si pas d'images dans listing_images
if (empty($images) && !empty($listing['image'])) {
  $images = [$listing['image']];
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
  'vetements'    => 'Vêtements',
  'electronique' => 'Électronique',
  'livres'       => 'Livres & Médias',
  'maison'       => 'Maison & Jardin',
  'sport'        => 'Sport & Loisirs',
  'vehicules'    => 'Véhicules',
  'autre'        => 'Autre',
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
  'neuf'          => 'Neuf',
  'tres_bon_etat' => 'Très bon état',
  'bon_etat'      => 'Bon état',
  'etat_correct'  => 'État correct',
  'pour_pieces'   => 'Pour pièces',
];

$isOwner = $user && $user['auth_token'] === $listing['auth_token'];
$categoryLabel = $categoryLabels[$listing['category']] ?? $listing['category'];
$categoryIcon  = $categoryIcons[$listing['category']] ?? 'fa-tag';
$conditionLabel = $conditionLabels[$listing['item_condition']] ?? $listing['item_condition'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?> — Market Plier</title>
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
      <a href="search.php"><i class="fa-solid fa-arrow-left"></i> Retour aux résultats</a>
    </nav>

    <div class="buy-layout">
      <!-- Colonne gauche : images -->
      <div class="buy-gallery">
        <?php if (!empty($images)): ?>
          <!-- Image principale -->
          <div class="gallery-main" id="galleryMain">
            <img
              src="../uploads/listings/<?= htmlspecialchars($images[0], ENT_QUOTES, 'UTF-8') ?>"
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
                    src="../uploads/listings/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
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
            Publiée le <?= date('d/m/Y', strtotime($listing['created_at'])) ?>
          </div>
        </div>

        <!-- Actions -->
        <div class="buy-card buy-card-actions">
          <?php if ($isOwner): ?>
            <a href="edit_listing.php?id=<?= (int) $listing['id'] ?>" class="buy-btn buy-btn-primary">
              <i class="fa-solid fa-pen"></i> Modifier l'annonce
            </a>
            <button type="button" class="buy-btn buy-btn-danger" id="openDeleteListingModal">
              <i class="fa-solid fa-trash"></i> Supprimer
            </button>
          <?php else: ?>
            <button class="buy-btn buy-btn-primary" id="contactSellerBtn" type="button">
              <i class="fa-solid fa-envelope"></i> Contacter le vendeur
            </button>
          <?php endif; ?>
        </div>

        <!-- Description -->
        <div class="buy-card buy-card-description">
          <h2 class="buy-section-title">Description</h2>
          <div class="buy-description-text">
            <?= nl2br(htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8')) ?>
          </div>
        </div>

        <!-- Vendeur -->
        <div class="buy-card buy-card-seller">
          <h2 class="buy-section-title">Vendeur</h2>
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
                <i class="fa-solid fa-box-open"></i> <?= $sellerListingCount ?> annonce<?= $sellerListingCount > 1 ? 's' : '' ?>
              </span>
              <span class="seller-meta">
                <i class="fa-regular fa-calendar"></i> Membre depuis <?= date('m/Y', strtotime($listing['seller_since'])) ?>
              </span>
            </div>
          </a>
          <?php if ($sellerDescription): ?>
            <p class="seller-bio"><?= nl2br(htmlspecialchars($sellerDescription, ENT_QUOTES, 'UTF-8')) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <?php if ($isOwner): ?>
  <!-- Modal suppression annonce -->
  <div class="confirm-modal-overlay" id="deleteListingOverlay">
    <div class="confirm-modal">
      <div class="confirm-modal-icon">
        <i class="fa-solid fa-trash-alt"></i>
      </div>
      <h3 class="confirm-modal-title">Supprimer cette annonce ?</h3>
      <p class="confirm-modal-text">
        L'annonce « <strong><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></strong> » sera supprimée définitivement.
      </p>
      <div class="confirm-modal-actions">
        <button type="button" class="confirm-modal-btn confirm-modal-btn-cancel" id="deleteListingCancel">Annuler</button>
        <a href="delete_listing.php?id=<?= (int) $listing['id'] ?>" class="confirm-modal-btn confirm-modal-btn-danger">
          <i class="fa-solid fa-trash-alt"></i> Supprimer
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
                      return '../uploads/listings/' . $img;
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
</body>

</html>