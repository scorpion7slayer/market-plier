<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

// Utilisateur connecté (pour le header)
$currentUser = null;
if (isset($_SESSION['auth_token'])) {
  $stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $currentUser = $stmt->fetch();
}

// Récupérer le profil demandé via ?user=username
$requestedUsername = trim($_GET['user'] ?? '');
if ($requestedUsername === '') {
  header('Location: ../index.php');
  exit();
}

// Si c'est le propre profil de l'utilisateur connecté, rediriger vers account.php
if ($currentUser && $currentUser['username'] === $requestedUsername) {
  header('Location: account.php');
  exit();
}

// Récupérer les infos du profil demandé
$profileUser = null;
try {
  $stmt = $pdo->prepare("SELECT username, profile_photo, auth_token, created_at FROM users WHERE username = ?");
  $stmt->execute([$requestedUsername]);
  $profileUser = $stmt->fetch();
} catch (PDOException $e) {
  error_log("Error fetching profile: " . $e->getMessage());
}

if (!$profileUser) {
  header('Location: ../index.php');
  exit();
}

// Description du profil
$profileDescription = '';
try {
  $descStmt = $pdo->prepare("SELECT description FROM profile WHERE auth_token = ?");
  $descStmt->execute([$profileUser['auth_token']]);
  $profile = $descStmt->fetch();
  $profileDescription = $profile ? ($profile['description'] ?? '') : '';
} catch (PDOException $e) {
  $profileDescription = '';
}

// Annonces de l'utilisateur
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

$userListings = [];
try {
  $listingsStmt = $pdo->prepare("
        SELECT l.id, l.title, l.price, l.category, l.item_condition, l.location, l.created_at,
               COALESCE(
                   (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
                   l.image
               ) AS image
        FROM listings l
        WHERE l.auth_token = ?
        ORDER BY l.created_at DESC
    ");
  $listingsStmt->execute([$profileUser['auth_token']]);
  $userListings = $listingsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log("Error fetching user listings: " . $e->getMessage());
}

$listingCount = count($userListings);
$username = htmlspecialchars($profileUser['username'], ENT_QUOTES, 'UTF-8');
$memberSince = date('m/Y', strtotime($profileUser['created_at']));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= $username ?> — Market Plier</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/profile.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $currentUser;
  include '../header.php';

  // Assigné APRÈS l'include header.php qui écrase $profilePhoto avec celle du user connecté
  $profilePhoto = $profileUser['profile_photo'] ?? null;
  $profilePhotoExists = $profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto);
  ?>

  <main class="profile-main">
    <!-- En-tête du profil -->
    <div class="profile-hero">
      <div class="profile-hero-avatar">
        <?php if ($profilePhotoExists): ?>
          <img src="../uploads/profiles/<?= htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= $username ?>">
        <?php else: ?>
          <img src="../assets/images/default-avatar.svg" alt="<?= $username ?>">
        <?php endif; ?>
      </div>
      <div class="profile-hero-info">
        <h1 class="profile-hero-name"><?= $username ?></h1>
        <div class="profile-hero-stats">
          <span class="profile-stat">
            <i class="fa-solid fa-box-open"></i>
            <?= $listingCount ?> annonce<?= $listingCount > 1 ? 's' : '' ?>
          </span>
          <span class="profile-stat">
            <i class="fa-regular fa-calendar"></i>
            Membre depuis <?= $memberSince ?>
          </span>
        </div>
        <?php if ($profileDescription): ?>
          <p class="profile-hero-bio"><?= nl2br(htmlspecialchars($profileDescription, ENT_QUOTES, 'UTF-8')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Annonces du vendeur -->
    <section class="profile-listings-section">
      <h2 class="profile-section-title">
        <i class="fa-solid fa-store"></i>
        Annonces de <?= $username ?>
        <span class="profile-section-count"><?= $listingCount ?></span>
      </h2>

      <?php if (empty($userListings)): ?>
        <div class="profile-no-listings">
          <i class="fa-solid fa-box-open"></i>
          <p>Cet utilisateur n'a pas encore d'annonces.</p>
        </div>
      <?php else: ?>
        <div class="profile-listings-grid">
          <?php foreach ($userListings as $listing): ?>
            <a href="../shop/buy.php?id=<?= (int) $listing['id'] ?>" class="profile-listing-card">
              <div class="profile-listing-img">
                <?php if ($listing['image']): ?>
                  <img src="../uploads/listings/<?= htmlspecialchars($listing['image'], ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
                <?php else: ?>
                  <div class="profile-listing-placeholder">
                    <i class="fa-solid fa-image"></i>
                  </div>
                <?php endif; ?>
                <span class="profile-listing-condition">
                  <?= htmlspecialchars($conditionLabels[$listing['item_condition']] ?? $listing['item_condition'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <div class="profile-listing-body">
                <div class="profile-listing-price"><?= number_format((float) $listing['price'], 2, ',', ' ') ?> €</div>
                <div class="profile-listing-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="profile-listing-meta">
                  <?php if ($listing['location']): ?>
                    <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php endif; ?>
                  <span class="profile-listing-category">
                    <i class="fa-solid <?= $categoryIcons[$listing['category']] ?? 'fa-tag' ?>"></i>
                    <?= htmlspecialchars($categoryLabels[$listing['category']] ?? $listing['category'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <script src="../styles/theme.js"></script>
</body>

</html>