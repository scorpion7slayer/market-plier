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
        $descStmt = $pdo->prepare("SELECT description FROM profiles WHERE auth_token = ?");
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

$successMessage = '';
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
                $upsert = $pdo->prepare("INSERT INTO profiles (auth_token, description) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE description = VALUES(description)");
                $upsert->execute([$_SESSION['auth_token'], $newDescription]);
                $description = $newDescription;
                $successMessage = "Description mise à jour avec succès !";
            } catch (PDOException $ex) {
                $errorMessage = "Erreur lors de la mise à jour de la description.";
                error_log("Error updating description: " . $ex->getMessage());
            }
        }
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

$profilePhoto = $user['profile_photo'] ?? null;
$profilePhotoExists = $profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto);
$username = htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Plier - Profil Utilisateur</title>
    <!-- Bootstrap local -->
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <!-- FontAwesome local -->
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/account.css">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
</head>

<body>
    <!-- Header partagé -->
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Messages de succès/erreur -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success" style="border-radius: 50px; border: 2px solid #7fb885; background-color: #e8fde8; color: #27ae60; text-align: center; max-width: 600px; margin: 15px auto;">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger" style="border-radius: 50px; border: 2px solid #e74c3c; background-color: #fde8e8; color: #c0392b; text-align: center; max-width: 600px; margin: 15px auto;">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <!-- Profile Section -->
        <div class="row">
            <div class="col-12">
                <div class="profile-section">
                    <aside class="profile-sidebar">
                        <div class="profile-header">
                            <div class="avatar-container">
                                <?php if ($profilePhotoExists): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="Photo de profil"
                                        class="avatar"
                                        style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="avatar"></div>
                                <?php endif; ?>
                            </div>
                            <div class="username-section">
                                <div class="username"><?= $username ?></div>
                                <span class="verified">✓</span>
                                <a href="dashboard.php" class="btn btn-sm btn-brand" style="margin-left: 10px;">
                                    <i class="fa-solid fa-pen-to-square"></i> Modifier le profil
                                </a>
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
                            <a href="settings/settings.php" class="btn btn-brand">
                                <i class="fa-solid fa-gear"></i> Paramètres
                            </a>
                            <a href="dashboard.php" class="btn btn-brand">
                                <i class="fa-solid fa-pen-to-square"></i> Modifier le profil
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger" style="border-radius: 50px; font-weight: 600; font-style: italic;">
                                <i class="fa-solid fa-right-from-bracket"></i> Se déconnecter
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
                    <h2 class="section-title">Vos articles</h2>
                    <div class="articles-grid">
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS local -->
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>

</html>
