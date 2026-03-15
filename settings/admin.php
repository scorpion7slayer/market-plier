<?php
session_start();

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (admin): " . $e->getMessage());
  die("Erreur de connexion à la base de données.");
}
require_once '../includes/remember_me.php';
require_once '../includes/site_settings.php';

// FONCTIONS

function initAdminPage($pdo)
{
  if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
  }
  if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  $currentToken = $_SESSION['auth_token'];
  if (!checkIsAdmin($pdo, $currentToken)) {
    header('Location: settings.php');
    exit();
  }
  return $currentToken;
}

function checkIsAdmin($pdo, $token)
{
  if (!isset($pdo)) return false;
  $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
  $stmt->execute([$token]);
  $check = $stmt->fetch();
  return $check && $check['is_admin'] == 1;
}

function fetchAdminData($pdo, $listingPage = 1, $listingsPerPage = 20)
{
  $data = ['users' => [], 'listings' => [], 'pending_listings' => [], 'stats' => ['users' => 0, 'listings' => 0, 'admins' => 0, 'new_month' => 0, 'pending' => 0], 'listings_total' => 0, 'listings_page' => $listingPage, 'listings_per_page' => $listingsPerPage, 'listings_total_pages' => 1];
  try {
    $data['stats']['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $data['stats']['listings'] = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'active'")->fetchColumn();
    $data['stats']['admins'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1")->fetchColumn();
    $data['stats']['new_month'] = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $data['stats']['pending'] = $pdo->query("SELECT COUNT(*) FROM listings WHERE status = 'pending'")->fetchColumn();
    $data['users'] = $pdo->query("SELECT username, email, auth_provider, is_admin, created_at, auth_token, profile_photo FROM users ORDER BY created_at DESC")->fetchAll();

    // Pagination des annonces
    $data['listings_total'] = (int) $pdo->query("SELECT COUNT(*) FROM listings l JOIN users u ON l.auth_token = u.auth_token")->fetchColumn();
    $data['listings_total_pages'] = max(1, (int) ceil($data['listings_total'] / $listingsPerPage));
    $data['listings_page'] = max(1, min($listingPage, $data['listings_total_pages']));
    $offset = ($data['listings_page'] - 1) * $listingsPerPage;

    $stmt = $pdo->prepare("SELECT l.id, l.title, l.price, l.category, l.created_at, l.status, u.username FROM listings l JOIN users u ON l.auth_token = u.auth_token ORDER BY l.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $listingsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $data['listings'] = $stmt->fetchAll();

    $data['pending_listings'] = $pdo->query("SELECT l.id, l.title, l.price, l.category, l.created_at, u.username FROM listings l JOIN users u ON l.auth_token = u.auth_token WHERE l.status = 'pending' ORDER BY l.created_at ASC")->fetchAll();
  } catch (PDOException $ex) {
    error_log("Error fetching admin data: " . $ex->getMessage());
  }
  return $data;
}

/**
 * Validate CSRF token
 */
function validateCsrfToken()
{
  return isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'];
}

/**
 * Handle toggle admin action
 */
function handleToggleAdmin($pdo, $currentToken, &$successMessage, &$errorMessage)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['toggle_admin'])) {
    return;
  }

  if (!validateCsrfToken()) {
    $errorMessage = "Token CSRF invalide.";
    return;
  }

  $targetToken = $_POST['target_token'] ?? '';
  $newStatus = (int) ($_POST['new_admin_status'] ?? 0);

  if ($targetToken === $currentToken) {
    $errorMessage = "Vous ne pouvez pas modifier votre propre statut admin.";
    return;
  }

  if (empty($targetToken)) {
    return;
  }

  try {
    $pdo->prepare("UPDATE users SET is_admin = ? WHERE auth_token = ?")->execute([$newStatus, $targetToken]);
    $successMessage = $newStatus ? "Utilisateur promu administrateur." : "Droits admin retirés.";
  } catch (PDOException $ex) {
    $errorMessage = "Erreur lors de la modification.";
    error_log("Error toggling admin: " . $ex->getMessage());
  }
}

/**
 * Delete user profile photo
 */
function deleteUserProfilePhoto($pdo, $targetToken)
{
  $photoStmt = $pdo->prepare("SELECT profile_photo FROM users WHERE auth_token = ?");
  $photoStmt->execute([$targetToken]);
  $photoData = $photoStmt->fetch();

  if ($photoData && $photoData['profile_photo']) {
    // Supprimer le fichier sur disque s'il existe (legacy)
    $photoPath = '../uploads/profiles/' . $photoData['profile_photo'];
    if (file_exists($photoPath)) {
      @unlink($photoPath);
    }
  }

  // Supprimer les données BLOB en DB
  $pdo->prepare("UPDATE users SET profile_photo = NULL, profile_photo_data = NULL, profile_photo_mime = NULL WHERE auth_token = ?")
    ->execute([$targetToken]);
}

/**
 * Handle delete user action
 */
function handleDeleteUser($pdo, $currentToken, &$successMessage, &$errorMessage)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_user'])) {
    return;
  }

  if (!validateCsrfToken()) {
    $errorMessage = "Token CSRF invalide.";
    return;
  }

  $targetToken = $_POST['target_token'] ?? '';

  if ($targetToken === $currentToken) {
    $errorMessage = "Vous ne pouvez pas supprimer votre propre compte ici.";
    return;
  }

  if (empty($targetToken)) {
    return;
  }

  try {
    deleteUserProfilePhoto($pdo, $targetToken);
    $pdo->prepare("DELETE FROM users WHERE auth_token = ?")->execute([$targetToken]);
    $successMessage = "Utilisateur supprimé.";
  } catch (PDOException $ex) {
    $errorMessage = "Erreur lors de la suppression.";
    error_log("Error deleting user: " . $ex->getMessage());
  }
}

/**
 * Handle delete listing action
 */
function handleDeleteListing($pdo, &$successMessage, &$errorMessage)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_listing'])) {
    return;
  }

  if (!validateCsrfToken()) {
    $errorMessage = "Token CSRF invalide.";
    return;
  }

  $listingId = (int) ($_POST['listing_id'] ?? 0);

  if ($listingId <= 0) {
    return;
  }

  try {
    $pdo->prepare("DELETE FROM listings WHERE id = ?")->execute([$listingId]);
    $successMessage = "Annonce supprimée.";
  } catch (PDOException $ex) {
    $errorMessage = "Erreur lors de la suppression.";
    error_log("Error deleting listing: " . $ex->getMessage());
  }
}

/**
 * Handle approve listing action
 */
function handleApproveListing($pdo, &$successMessage, &$errorMessage)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['approve_listing'])) {
    return;
  }

  if (!validateCsrfToken()) {
    $errorMessage = "Token CSRF invalide.";
    return;
  }

  $listingId = (int) ($_POST['listing_id'] ?? 0);
  if ($listingId <= 0) return;

  try {
    $pdo->prepare("UPDATE listings SET status = 'active' WHERE id = ? AND status = 'pending'")->execute([$listingId]);
    $successMessage = "Annonce approuvée et publiée.";
  } catch (PDOException $ex) {
    $errorMessage = "Erreur lors de l'approbation.";
    error_log("Error approving listing: " . $ex->getMessage());
  }
}

// INITIALISATION
$currentToken = initAdminPage($pdo);
$successMessage = '';
$errorMessage = '';

// Process admin actions
handleToggleAdmin($pdo, $currentToken, $successMessage, $errorMessage);
handleDeleteUser($pdo, $currentToken, $successMessage, $errorMessage);
handleDeleteListing($pdo, $successMessage, $errorMessage);
handleApproveListing($pdo, $successMessage, $errorMessage);

// Fetch data
$listingPage = max(1, (int) ($_GET['lp'] ?? 1));
$adminData = fetchAdminData($pdo, $listingPage);
$users = $adminData['users'];
$listings = $adminData['listings'];
$pendingListings = $adminData['pending_listings'];
$stats = $adminData['stats'];
$listingsTotal = $adminData['listings_total'];
$listingsTotalPages = $adminData['listings_total_pages'];
$listingsCurrentPage = $adminData['listings_page'];
$siteSettings = getSiteSettings($pdo);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <?php include '../includes/theme_init.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/settings.css">
  <link rel="stylesheet" href="../styles/admin.css">
  <link rel="stylesheet" href="../styles/theme.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - Administration</title>
</head>

<body>
  <div class="settings-top-bar">
    <a href="../index.php" class="settings-logo">
      <img src="../assets/images/logo.svg" alt="Market Plier">
    </a>
    <a href="settings.php" class="settings-back-link">
      <i class="fas fa-arrow-left"></i> Retour aux paramètres
    </a>
    <button class="theme-toggle" data-theme-toggle title="Changer le thème">
      <i class="fa-solid fa-moon"></i>
      <i class="fa-solid fa-sun"></i>
    </button>
  </div>

  <main class="admin-container">
    <h1 class="settings-title"><i class="fas fa-crown" style="color: #f0c040;"></i> Administration</h1>

    <?php
    $toastSuccess = $successMessage;
    $toastError = $errorMessage;
    include '../includes/toast.php';
    ?>

    <!-- Statistiques -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-chart-bar"></i> Vue d'ensemble</h2>
      <div class="admin-stats-grid-4">
        <div class="admin-stat-card">
          <i class="fas fa-users admin-stat-icon"></i>
          <div class="admin-stat-number"><?= $stats['users'] ?></div>
          <div class="admin-stat-label">Utilisateurs</div>
        </div>
        <div class="admin-stat-card">
          <i class="fas fa-tag admin-stat-icon"></i>
          <div class="admin-stat-number"><?= $stats['listings'] ?></div>
          <div class="admin-stat-label">Annonces</div>
        </div>
        <div class="admin-stat-card">
          <i class="fas fa-shield-alt admin-stat-icon" style="color: #e74c3c;"></i>
          <div class="admin-stat-number"><?= $stats['admins'] ?></div>
          <div class="admin-stat-label">Admins</div>
        </div>
        <div class="admin-stat-card">
          <i class="fas fa-user-plus admin-stat-icon" style="color: #2196f3;"></i>
          <div class="admin-stat-number"><?= $stats['new_month'] ?></div>
          <div class="admin-stat-label">Ce mois</div>
        </div>
      </div>
    </section>

    <!-- Gestion des utilisateurs -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-users-cog"></i> Gestion des utilisateurs</h2>
      <div class="admin-table-wrapper">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Utilisateur</th>
              <th>Email</th>
              <th>Connexion</th>
              <th>Rôle</th>
              <th>Inscrit le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td>
                  <div class="admin-user-cell">
                    <img src="../api/profile_photo.php?token=<?= urlencode($u['auth_token']) ?>"
                      class="admin-mini-avatar" alt="">
                    <span class="admin-username"><?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($u['auth_token'] === $currentToken): ?>
                      <span class="admin-you-badge">vous</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="admin-email"><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php
                  $providerIcons = ['local' => 'fas fa-envelope', 'google' => 'fab fa-google', 'both' => 'fas fa-link'];
                  $providerLabels = ['local' => 'Email', 'google' => 'Google', 'both' => 'Les deux'];
                  $prov = $u['auth_provider'] ?? 'local';
                  ?>
                  <span class="admin-provider">
                    <i class="<?= $providerIcons[$prov] ?? 'fas fa-question' ?>"></i>
                    <?= $providerLabels[$prov] ?? $prov ?>
                  </span>
                </td>
                <td>
                  <?php if ($u['is_admin']): ?>
                    <span class="admin-role-badge admin-role-admin"><i class="fas fa-crown"></i> Admin</span>
                  <?php else: ?>
                    <span class="admin-role-badge admin-role-user"><i class="fas fa-user"></i> Membre</span>
                  <?php endif; ?>
                </td>
                <td class="admin-date"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                <td>
                  <?php if ($u['auth_token'] !== $currentToken): ?>
                    <div class="admin-actions-cell">
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="target_token" value="<?= htmlspecialchars($u['auth_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="new_admin_status" value="<?= $u['is_admin'] ? '0' : '1' ?>">
                        <button type="submit" name="toggle_admin" class="admin-action-btn admin-action-toggle"
                          title="<?= $u['is_admin'] ? 'Retirer admin' : 'Promouvoir admin' ?>">
                          <i class="fas <?= $u['is_admin'] ? 'fa-user-minus' : 'fa-user-shield' ?>"></i>
                        </button>
                      </form>
                      <button type="button" class="admin-action-btn admin-action-delete"
                        title="Supprimer l'utilisateur"
                        data-open-delete
                        data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>"
                        data-token="<?= htmlspecialchars($u['auth_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  <?php else: ?>
                    <span class="admin-no-action">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Annonces en attente de modération -->
    <?php if (!empty($pendingListings)): ?>
      <section class="settings-section">
        <h2 class="settings-section-title"><i class="fas fa-hourglass-half" style="color: #f0c040;"></i> Annonces en attente (<?= count($pendingListings) ?>)</h2>
        <div class="admin-table-wrapper">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Titre</th>
                <th>Prix</th>
                <th>Catégorie</th>
                <th>Auteur</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pendingListings as $pl): ?>
                <tr>
                  <td class="admin-listing-title"><?= htmlspecialchars($pl['title'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><span class="admin-price"><?= number_format($pl['price'], 2, ',', ' ') ?> €</span></td>
                  <td class="admin-category"><?= htmlspecialchars($pl['category'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-username"><?= htmlspecialchars($pl['username'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-date"><?= date('d/m/Y', strtotime($pl['created_at'])) ?></td>
                  <td>
                    <div class="admin-actions-cell">
                      <form method="POST" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="listing_id" value="<?= (int) $pl['id'] ?>">
                        <button type="submit" name="approve_listing" class="admin-action-btn admin-action-toggle" title="Approuver">
                          <i class="fas fa-check"></i>
                        </button>
                      </form>
                      <button type="button" class="admin-action-btn admin-action-delete" title="Supprimer"
                        data-open-delete-listing
                        data-listing-id="<?= (int) $pl['id'] ?>"
                        data-listing-title="<?= htmlspecialchars($pl['title'], ENT_QUOTES, 'UTF-8') ?>">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <!-- Toutes les annonces -->
    <section class="settings-section" id="listings-section">
      <h2 class="settings-section-title">
        <i class="fas fa-clipboard-list"></i> Toutes les annonces
        <span class="admin-count-badge"><?= $listingsTotal ?></span>
      </h2>
      <?php if ($listingsTotal === 0): ?>
        <p class="settings-desc">Aucune annonce pour le moment.</p>
      <?php else: ?>
        <div class="admin-listing-filters">
          <input type="text" class="admin-listing-search" id="listingSearchInput" placeholder="Rechercher une annonce...">
          <select class="admin-listing-filter-select" id="listingStatusFilter">
            <option value="">Tous les statuts</option>
            <option value="active">Active</option>
            <option value="pending">En attente</option>
            <option value="sold">Vendue</option>
          </select>
        </div>
        <div class="admin-table-wrapper">
          <table class="admin-table" id="listingsTable">
            <thead>
              <tr>
                <th>Titre</th>
                <th>Prix</th>
                <th>Catégorie</th>
                <th>Auteur</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($listings as $l): ?>
                <tr data-status="<?= htmlspecialchars($l['status'], ENT_QUOTES, 'UTF-8') ?>">
                  <td class="admin-listing-title">
                    <a href="../shop/buy.php?id=<?= (int) $l['id'] ?>" class="admin-listing-link">
                      <?= htmlspecialchars($l['title'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  </td>
                  <td><span class="admin-price"><?= number_format($l['price'], 2, ',', ' ') ?> €</span></td>
                  <td class="admin-category"><?= htmlspecialchars($l['category'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td class="admin-username"><?= htmlspecialchars($l['username'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <?php
                    $statusLabels = ['active' => 'Active', 'pending' => 'En attente', 'sold' => 'Vendue'];
                    $statusClass = 'admin-status-' . ($l['status'] ?? 'active');
                    ?>
                    <span class="admin-status-badge <?= $statusClass ?>">
                      <?= $statusLabels[$l['status']] ?? ucfirst($l['status']) ?>
                    </span>
                  </td>
                  <td class="admin-date"><?= date('d/m/Y', strtotime($l['created_at'])) ?></td>
                  <td>
                    <button type="button" class="admin-action-btn admin-action-delete" title="Supprimer"
                      data-open-delete-listing
                      data-listing-id="<?= (int) $l['id'] ?>"
                      data-listing-title="<?= htmlspecialchars($l['title'], ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($listingsTotalPages > 1): ?>
          <nav class="admin-pagination">
            <?php if ($listingsCurrentPage > 1): ?>
              <a href="?lp=<?= $listingsCurrentPage - 1 ?>#listings-section" class="admin-page-btn">
                <i class="fas fa-chevron-left"></i>
              </a>
            <?php else: ?>
              <span class="admin-page-btn admin-page-disabled"><i class="fas fa-chevron-left"></i></span>
            <?php endif; ?>

            <?php
            // Calculer les pages à afficher
            $startPage = max(1, $listingsCurrentPage - 2);
            $endPage = min($listingsTotalPages, $listingsCurrentPage + 2);
            if ($startPage > 1): ?>
              <a href="?lp=1#listings-section" class="admin-page-btn">1</a>
              <?php if ($startPage > 2): ?><span class="admin-page-ellipsis">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
              <?php if ($p === $listingsCurrentPage): ?>
                <span class="admin-page-btn admin-page-active"><?= $p ?></span>
              <?php else: ?>
                <a href="?lp=<?= $p ?>#listings-section" class="admin-page-btn"><?= $p ?></a>
              <?php endif; ?>
            <?php endfor; ?>

            <?php if ($endPage < $listingsTotalPages): ?>
              <?php if ($endPage < $listingsTotalPages - 1): ?><span class="admin-page-ellipsis">...</span><?php endif; ?>
              <a href="?lp=<?= $listingsTotalPages ?>#listings-section" class="admin-page-btn"><?= $listingsTotalPages ?></a>
            <?php endif; ?>

            <?php if ($listingsCurrentPage < $listingsTotalPages): ?>
              <a href="?lp=<?= $listingsCurrentPage + 1 ?>#listings-section" class="admin-page-btn">
                <i class="fas fa-chevron-right"></i>
              </a>
            <?php else: ?>
              <span class="admin-page-btn admin-page-disabled"><i class="fas fa-chevron-right"></i></span>
            <?php endif; ?>
          </nav>
        <?php endif; ?>

        <div class="admin-listing-count-footer">
          Page <?= $listingsCurrentPage ?> / <?= $listingsTotalPages ?> — <?= $listingsTotal ?> annonce<?= $listingsTotal > 1 ? 's' : '' ?> au total
        </div>
      <?php endif; ?>
    </section>

    <!-- Options du site -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-cogs"></i> Options du site</h2>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Mode maintenance</span>
          <span class="toggle-desc">Désactive temporairement l'accès public au site</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="maintenance_mode" <?= $siteSettings['maintenance_mode'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Inscriptions ouvertes</span>
          <span class="toggle-desc">Autoriser les nouveaux utilisateurs à s'inscrire</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="registration_open" <?= $siteSettings['registration_open'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Modération des annonces</span>
          <span class="toggle-desc">Valider manuellement chaque nouvelle annonce avant publication</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="moderation_enabled" <?= $siteSettings['moderation_enabled'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Limite d'annonces par utilisateur</span>
          <span class="toggle-desc">Limiter le nombre maximum d'annonces actives par utilisateur</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="listing_limit" <?= $siteSettings['listing_limit'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Connexion Google</span>
          <span class="toggle-desc">Autoriser la connexion via Google OAuth</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="google_login" <?= $siteSettings['google_login'] === '1' ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
    </section>
  </main>

  <!-- Modal suppression utilisateur -->
  <div class="admin-modal-overlay" id="deleteUserOverlay">
    <div class="admin-modal">
      <div class="admin-modal-icon">
        <i class="fas fa-trash-alt"></i>
      </div>
      <h3 class="admin-modal-title">Supprimer cet utilisateur ?</h3>
      <p class="admin-modal-text">
        Le compte de <strong id="deleteUserName"></strong> sera supprimé définitivement,
        ainsi que son profil, ses annonces et ses photos.
      </p>
      <form method="POST" class="admin-modal-actions">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="target_token" id="deleteUserToken" value="">
        <button type="button" class="settings-btn admin-modal-btn-cancel" id="deleteUserCancel">Annuler</button>
        <button type="submit" name="delete_user" class="settings-btn settings-btn-danger">
          <i class="fas fa-trash-alt"></i> Supprimer
        </button>
      </form>
    </div>
  </div>

  <!-- Modal suppression annonce -->
  <div class="admin-modal-overlay" id="deleteListingOverlay">
    <div class="admin-modal">
      <div class="admin-modal-icon">
        <i class="fas fa-trash-alt"></i>
      </div>
      <h3 class="admin-modal-title">Supprimer cette annonce ?</h3>
      <p class="admin-modal-text">
        L'annonce « <strong id="deleteListingName"></strong> » sera supprimée définitivement.
      </p>
      <form method="POST" class="admin-modal-actions">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="listing_id" id="deleteListingId" value="">
        <button type="button" class="settings-btn admin-modal-btn-cancel" id="deleteListingCancel">Annuler</button>
        <button type="submit" name="delete_listing" class="settings-btn settings-btn-danger">
          <i class="fas fa-trash-alt"></i> Supprimer
        </button>
      </form>
    </div>
  </div>

  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      // Modal suppression utilisateur
      var userOverlay = document.getElementById('deleteUserOverlay');
      var tokenInput = document.getElementById('deleteUserToken');
      var nameEl = document.getElementById('deleteUserName');

      document.querySelectorAll('[data-open-delete]').forEach(function(btn) {
        btn.addEventListener('click', function() {
          tokenInput.value = btn.getAttribute('data-token');
          nameEl.textContent = btn.getAttribute('data-username');
          userOverlay.classList.add('visible');
        });
      });

      function closeUser() {
        userOverlay.classList.remove('visible');
      }

      document.getElementById('deleteUserCancel').addEventListener('click', closeUser);
      userOverlay.addEventListener('click', function(e) {
        if (e.target === userOverlay) closeUser();
      });

      // Modal suppression annonce
      var listingOverlay = document.getElementById('deleteListingOverlay');
      var listingIdInput = document.getElementById('deleteListingId');
      var listingNameEl = document.getElementById('deleteListingName');

      document.querySelectorAll('[data-open-delete-listing]').forEach(function(btn) {
        btn.addEventListener('click', function() {
          listingIdInput.value = btn.getAttribute('data-listing-id');
          listingNameEl.textContent = btn.getAttribute('data-listing-title');
          listingOverlay.classList.add('visible');
        });
      });

      function closeListing() {
        listingOverlay.classList.remove('visible');
      }

      document.getElementById('deleteListingCancel').addEventListener('click', closeListing);
      listingOverlay.addEventListener('click', function(e) {
        if (e.target === listingOverlay) closeListing();
      });

      // Escape ferme le modal visible
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeUser();
          closeListing();
        }
      });
    })();
  </script>
  <script>
    // Site settings toggles (AJAX)
    (function() {
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

      document.querySelectorAll('[data-setting]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
          var key = this.getAttribute('data-setting');
          var value = this.checked ? '1' : '0';
          var cb = this;

          fetch('handle_site_settings.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                key: key,
                value: value,
                csrf_token: csrfToken
              })
            })
            .then(function(res) {
              return res.json();
            })
            .then(function(data) {
              if (!data.success) {
                cb.checked = !cb.checked;
                alert('Erreur : ' + (data.message || 'Impossible de sauvegarder'));
              }
            })
            .catch(function() {
              cb.checked = !cb.checked;
              alert('Erreur réseau. Veuillez réessayer.');
            });
        });
      });
    })();
  </script>
  <script>
    // Preserve scroll position across form submissions
    (function() {
      var key = 'mp-scroll-admin';
      var saved = sessionStorage.getItem(key);
      if (saved) {
        sessionStorage.removeItem(key);
        window.scrollTo(0, parseInt(saved, 10));
      }
      document.querySelectorAll('form').forEach(function(f) {
        f.addEventListener('submit', function() {
          sessionStorage.setItem(key, window.scrollY);
        });
      });
    })();
  </script>
  <script>
    // Listings search + status filter (filtre côté client sur la page courante)
    (function() {
      var searchInput = document.getElementById('listingSearchInput');
      var statusFilter = document.getElementById('listingStatusFilter');
      var table = document.getElementById('listingsTable');
      if (!searchInput || !table) return;

      var rows = table.querySelectorAll('tbody tr');

      function filterListings() {
        var query = searchInput.value.trim().toLowerCase();
        var status = statusFilter ? statusFilter.value : '';

        rows.forEach(function(row) {
          var text = row.textContent.toLowerCase();
          var rowStatus = row.getAttribute('data-status') || '';
          var matchText = !query || text.indexOf(query) !== -1;
          var matchStatus = !status || rowStatus === status;
          row.style.display = (matchText && matchStatus) ? '' : 'none';
        });
      }

      searchInput.addEventListener('input', filterListings);
      if (statusFilter) statusFilter.addEventListener('change', filterListings);
    })();
  </script>
</body>

</html>