<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['auth_token'])) {
    header('Location: ../index.php');
    exit();
}

// Générer un token CSRF si nécessaire
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Récupérer les infos utilisateur
$user = null;
$description = '';
try {
    $stmt = $pdo->prepare("SELECT username, email, profile_photo, is_admin FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $user = $stmt->fetch();
} catch (PDOException $ex) {
    error_log("Error fetching user data: " . $ex->getMessage());
}

// Charger la description depuis profiles (table optionnelle)
if ($user) {
    try {
        $descStmt = $pdo->prepare("SELECT description FROM profile WHERE auth_token = ?");
        $descStmt->execute([$_SESSION['auth_token']]);
        $profile = $descStmt->fetch();
        $description = $profile ? ($profile['description'] ?? '') : '';
    } catch (PDOException $ex) {
        $description = '';
    }
}

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
    header('Location: ../index.php?account_deleted=1');
    exit();
}

$successMessage = '';
$errorMessage = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'description':
            $successMessage = "Description mise à jour avec succès !";
            break;
        case 'deleted':
            $successMessage = "Annonce supprimée.";
            break;
        default:
            $successMessage = $_GET['success'];
            break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'delete_failed':
            $errorMessage = "Erreur lors de la suppression de l'annonce.";
            break;
        default:
            $errorMessage = $_GET['error'];
            break;
    }
}

// Traitement POST : mise à jour de la description
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_description'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Token de sécurité invalide.";
    } else {
        $newDescription = trim($_POST['description'] ?? '');
        if (mb_strlen($newDescription) > 500) {
            $errorMessage = "La description ne peut pas dépasser 500 caractères.";
        } else {
            try {
                $upsert = $pdo->prepare("INSERT INTO profile (auth_token, description) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE description = VALUES(description)");
                $upsert->execute([$_SESSION['auth_token'], $newDescription]);
                header('Location: account.php?success=description');
                exit();
            } catch (PDOException $ex) {
                $errorMessage = "Erreur lors de la mise à jour de la description.";
                error_log("Error updating description: " . $ex->getMessage());
            }
        }
    }
}

$profilePhoto = $user['profile_photo'] ?? null;
$profilePhotoExists = $profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto);
$username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');

// Récupérer les annonces de l'utilisateur avec toutes leurs images
$userListings = [];
try {
    $listingsStmt = $pdo->prepare("
        SELECT l.* 
        FROM listings l 
        WHERE l.auth_token = ? 
        ORDER BY l.created_at DESC
    ");
    $listingsStmt->execute([$_SESSION['auth_token']]);
    $listings = $listingsStmt->fetchAll();

    // Pour chaque annonce, récupérer toutes les images
    foreach ($listings as $listing) {
        $imagesStmt = $pdo->prepare("
            SELECT id, image_path FROM listing_images
            WHERE listing_id = ?
            ORDER BY sort_order ASC
        ");
        $imagesStmt->execute([$listing['id']]);
        $imageRows = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Construire le tableau d'images avec URLs API
        $allImages = [];
        foreach ($imageRows as $imgRow) {
            $allImages[] = '../api/image.php?id=' . (int)$imgRow['id'];
        }
        // Fallback sur l'image principale
        if (empty($allImages) && !empty($listing['image'])) {
            $allImages[] = '../uploads/listings/' . $listing['image'];
        }

        $listing['all_images'] = $allImages;
        $userListings[] = $listing;
    }
} catch (PDOException $ex) {
    error_log("Error fetching user listings: " . $ex->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '../includes/theme_init.php'; ?>
    <title><?= htmlspecialchars(t('account_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/account.css">
    <link rel="stylesheet" href="../styles/theme.css">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
</head>

<body>
    <!-- Header partagé -->
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    $toastSuccess = $successMessage;
    $toastError = $errorMessage;
    include '../header.php';
    ?>

    <!-- Main Content -->
    <div class="container-fluid">

        <!-- Profile Section -->
        <div class="row">
            <div class="col-12">
                <div class="profile-section">
                    <aside class="profile-sidebar">
                        <div class="profile-header">
                            <div class="avatar-container">
                                <img src="<?= $profilePhotoExists ? '../uploads/profiles/' . htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') : '../assets/images/default-avatar.svg' ?>"
                                    alt="Photo de profil"
                                    class="avatar"
                                    style="object-fit: cover;">
                            </div>
                            <div class="username-section">
                                <div class="username"><?= $username ?></div>
                                <span class="verified">✓</span>
                            </div>
                        </div>

                        <!-- Description éditable -->
                        <div class="description-section">
                            <h2 class="description-title">
                                <?= htmlspecialchars(t('account_description'), ENT_QUOTES, 'UTF-8') ?>
                                <button class="edit-description-btn" onclick="toggleDescriptionEdit()" title="Modifier la description">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </h2>

                            <!-- Affichage normal -->
                            <div id="description-display">
                                <p class="description-text"><?= htmlspecialchars($description ?: t('account_no_description'), ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <!-- Formulaire d'édition (masqué par défaut) -->
                            <div id="description-edit" style="display: none;">
                                <form method="POST" class="description-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <textarea name="description" class="form-control mb-2" maxlength="500" rows="3" placeholder="<?= htmlspecialchars(t('account_description_placeholder'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="update_description" class="btn btn-sm btn-brand">
                                            <i class="fa-solid fa-check"></i> <?= htmlspecialchars(t('account_save'), ENT_QUOTES, 'UTF-8') ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleDescriptionEdit()" style="border-radius: 50px;">
                                            Annuler
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Boutons d'actions -->
                        <div class="profile-actions">
                            <a href="../settings/settings.php" class="btn btn-brand">
                                <i class="fa-solid fa-gear"></i> Gérer le compte
                            </a>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <!-- Articles Section -->
        <div class="row">
            <div class="col-12">
                <div class="articles-section">
                    <h2 class="section-title">Vos articles en vente</h2>

                    <?php if (empty($userListings)): ?>
                        <div class="no-articles">
                            <i class="fa-solid fa-box-open"></i>
                            <p>Vous n'avez pas encore d'articles en vente.</p>
                            <a href="../shop/sell.php" class="btn btn-brand btn-sm">
                                Poster une annonce
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="articles-grid">
                            <?php foreach ($userListings as $listing): ?>
                                <?php
                                $imageUrl = !empty($listing['all_images']) ? $listing['all_images'][0] : '../assets/images/no-image.svg';
                                $price = number_format($listing['price'], 2, ',', ' ');
                                $conditionLabels = [
                                    'neuf' => 'Neuf',
                                    'tres_bon_etat' => 'Très bon état',
                                    'bon_etat' => 'Bon état',
                                    'etat_correct' => 'État correct',
                                    'pour_pieces' => 'Pour pièces'
                                ];
                                $conditionLabel = $conditionLabels[$listing['item_condition']] ?? $listing['item_condition'];
                                ?>
                                <div class="article-card" data-listing-id="<?= $listing['id'] ?>">
                                    <div class="article-image">
                                        <?php if (count($listing['all_images']) > 1): ?>
                                            <div id="carousel-<?= $listing['id'] ?>" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                                                <div class="carousel-inner">
                                                    <?php foreach ($listing['all_images'] as $index => $img): ?>
                                                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                                            <img src="<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                                                                class="d-block w-100"
                                                                alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <button class="carousel-control-prev" type="button" data-bs-target="#carousel-<?= $listing['id'] ?>" data-bs-slide="prev">
                                                    <i class="fa-solid fa-caret-left"></i>
                                                    <span class="visually-hidden">Précédent</span>
                                                </button>
                                                <button class="carousel-control-next" type="button" data-bs-target="#carousel-<?= $listing['id'] ?>" data-bs-slide="next">
                                                    <i class="fa-solid fa-caret-right"></i>
                                                    <span class="visually-hidden">Suivant</span>
                                                </button>
                                            </div>
                                        <?php elseif (!empty($listing['all_images'])): ?>
                                            <img src="<?= htmlspecialchars($listing['all_images'][0], ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="no-image-placeholder">
                                                <i class="fa-solid fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="article-condition"><?= htmlspecialchars($conditionLabel) ?></div>
                                    </div>
                                    <div class="article-info">
                                        <h3 class="article-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <p class="article-price"><?= $price ?> €</p>
                                        <?php if (!empty($listing['location'])): ?>
                                            <p class="article-location">
                                                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="article-actions">
                                            <a href="../shop/edit_listing.php?id=<?= $listing['id'] ?>" class="btn btn-edit" title="Modifier">
                                                <i class="fa-solid fa-pen"></i>
                                            </a>
                                            <button class="btn btn-delete" title="Supprimer" onclick="deleteListing(<?= $listing['id'] ?>)">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="custom-modal-overlay" id="deleteModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <h3>Confirmer la suppression</h3>
            </div>
            <div class="custom-modal-body">
                <p>Êtes-vous sûr de vouloir supprimer cette annonce ?</p>
                <p class="modal-warning">Cette action est irréversible.</p>
            </div>
            <div class="custom-modal-footer">
                <button class="btn btn-cancel" onclick="closeDeleteModal()">Annuler</button>
                <button class="btn btn-confirm-delete" id="confirmDeleteBtn">Supprimer</button>
            </div>
        </div>
    </div>

    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../styles/theme.js"></script>
    <script>
        var listingIdToDelete = null;
        var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        function openDeleteModal(id) {
            listingIdToDelete = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            listingIdToDelete = null;
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Confirmer la suppression (AJAX, pas de rechargement)
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!listingIdToDelete) return;
            var id = listingIdToDelete;
            var btn = this;
            btn.disabled = true;

            var formData = new FormData();
            formData.append('id', id);
            formData.append('csrf_token', csrfToken);

            fetch('../shop/delete_listing.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(r) {
                    return r.json().then(function(d) {
                        return {
                            ok: r.ok,
                            data: d
                        };
                    });
                })
                .then(function(res) {
                    closeDeleteModal();
                    btn.disabled = false;
                    if (res.ok && res.data.success) {
                        // Retirer la carte de l'annonce du DOM
                        var card = document.querySelector('[data-listing-id="' + id + '"]');
                        if (card) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.95)';
                            setTimeout(function() {
                                card.remove();
                            }, 300);
                        }
                        mpShowToast(res.data.message || 'Annonce supprimée.', 'success');
                    } else {
                        mpShowToast(res.data.error || 'Erreur lors de la suppression.', 'error');
                    }
                })
                .catch(function() {
                    closeDeleteModal();
                    btn.disabled = false;
                    mpShowToast('Erreur réseau. Veuillez réessayer.', 'error');
                });
        });

        // Fermer le modal en cliquant sur l'overlay
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Fermer avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        function toggleDescriptionEdit() {
            var display = document.getElementById('description-display');
            var edit = document.getElementById('description-edit');
            if (edit.style.display === 'none') {
                edit.style.display = 'block';
                display.style.display = 'none';
            } else {
                edit.style.display = 'none';
                display.style.display = 'block';
            }
        }

        function deleteListing(id) {
            openDeleteModal(id);
        }
    </script>
    <?php include '../footer.php'; ?>
</body>

</html>
