<?php
session_start();
require_once '../database/db.php';

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
    header('Location: ../index.php');
    exit();
}

$successMessage = isset($_GET['success']) && $_GET['success'] === 'description' ? "Description mise à jour avec succès !" : '';
$errorMessage = '';

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
            SELECT image_path FROM listing_images 
            WHERE listing_id = ? 
            ORDER BY sort_order ASC
        ");
        $imagesStmt->execute([$listing['id']]);
        $additionalImages = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);

        // Construire le tableau d'images (image principale + images additionnelles)
        $allImages = [];
        if (!empty($listing['image'])) {
            $allImages[] = $listing['image'];
        }
        foreach ($additionalImages as $img) {
            if ($img !== $listing['image']) {
                $allImages[] = $img;
            }
        }

        $listing['all_images'] = $allImages;
        $userListings[] = $listing;
    }
} catch (PDOException $ex) {
    error_log("Error fetching user listings: " . $ex->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Plier - Profil Utilisateur</title>
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
    include '../header.php';
    ?>

    <!-- Toasts -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
        <?php if ($successMessage): ?>
            <div id="toastSuccess" class="toast align-items-center text-white border-0" role="alert" style="background-color: #7fb885;">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa-solid fa-check me-2"></i><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div id="toastError" class="toast align-items-center text-white bg-danger border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        <?php endif; ?>
    </div>

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
                                Description
                                <button class="edit-description-btn" onclick="toggleDescriptionEdit()" title="Modifier la description">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            </h2>

                            <!-- Affichage normal -->
                            <div id="description-display">
                                <p class="description-text"><?= htmlspecialchars($description ?: 'Aucune description.', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>

                            <!-- Formulaire d'édition (masqué par défaut) -->
                            <div id="description-edit" style="display: none;">
                                <form method="POST" class="description-edit-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <textarea name="description" class="form-control mb-2" maxlength="500" rows="3" placeholder="Décrivez-vous en quelques mots..."><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="update_description" class="btn btn-sm btn-brand">
                                            <i class="fa-solid fa-check"></i> Enregistrer
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
                                // Déterminer l'image à afficher
                                $displayImage = $listing['image'] ?? $listing['additional_image'];
                                $imageUrl = $displayImage ? '../uploads/listings/' . htmlspecialchars($displayImage, ENT_QUOTES, 'UTF-8') : '../assets/images/no-image.svg';
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
                                <div class="article-card">
                                    <div class="article-image">
                                        <?php if (count($listing['all_images']) > 1): ?>
                                            <div id="carousel-<?= $listing['id'] ?>" class="carousel slide" data-bs-ride="carousel" data-bs-interval="3000">
                                                <div class="carousel-inner">
                                                    <?php foreach ($listing['all_images'] as $index => $img): ?>
                                                        <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                                            <img src="../uploads/listings/<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>"
                                                                class="d-block w-100"
                                                                alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
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
                                            <img src="../uploads/listings/<?= htmlspecialchars($listing['all_images'][0], ENT_QUOTES, 'UTF-8') ?>"
                                                alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
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

        function openDeleteModal(id) {
            listingIdToDelete = id;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            listingIdToDelete = null;
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Confirmer la suppression
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (listingIdToDelete) {
                window.location.href = '../shop/delete_listing.php?id=' + listingIdToDelete;
            }
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

        // Afficher les toasts automatiquement
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.toast').forEach(function(el) {
                new bootstrap.Toast(el, {
                    delay: 3000
                }).show();
            });
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
</body>

</html>