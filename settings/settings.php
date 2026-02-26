<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['auth_token'])) {
    header('Location: ../index.php');
    exit();
}

// Generate CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Database connection
try {
    require_once '../database/db.php';
} catch (PDOException $e) {
    error_log("DB connection error (settings): " . $e->getMessage());
}

$user = null;
$username = $_SESSION['username'] ?? '';

// Fetch user info
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT username, email, profile_photo, is_admin FROM users WHERE auth_token = ?");
        $stmt->execute([$_SESSION['auth_token']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $username = $user['username'];
        }
    } catch (PDOException $ex) {
        error_log("Error fetching user data: " . $ex->getMessage());
    }
}

if (!$user) {
    header('Location: ../index.php');
    exit();
}

$successMessage = '';
$errorMessage = '';
?>

<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Plier - Paramètres</title>
    <!-- Bootstrap local -->
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <!-- FontAwesome local -->
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/settings.css">
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
    <div class="container-fluid py-4">
        <!-- Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success" style="border-radius: 50px; border: 2px solid #7fb885; background-color: #e8fde8; color: #27ae60; text-align: center; max-width: 600px; margin: 15px auto;">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger" style="border-radius: 50px; border: 2px solid #e74c3c; background-color: #fde8e8; color: #c0392b; text-align: center; max-width: 600px; margin: 15px auto;">
                <i class="fa-solid fa-exclamation-triangle"></i> <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                
                <!-- Appearance Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-palette"></i> Apparence
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-moon"></i> Mode sombre
                                </h6>
                                <p>Activer le thème sombre pour une lecture</p>
                             confortable la nuit</div>
                            <label class="theme-toggle">
                                <input type="checkbox" id="themeToggle">
                                <span class="theme-slider">
                                    <i class="fa-solid fa-sun theme-icon sun-icon"></i>
                                    <i class="fa-solid fa-moon theme-icon moon-icon"></i>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Account Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-user-gear"></i> Compte
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-user"></i> Nom d'utilisateur
                                </h6>
                                <p><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-pen"></i> Modifier
                            </a>
                        </div>
                        
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-envelope"></i> Email
                                </h6>
                                <p><?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-pen"></i> Modifier
                            </a>
                        </div>
                        
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-image"></i> Photo de profil
                                </h6>
                                <p>Changer votre photo de profil</p>
                            </div>
                            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fa-solid fa-camera"></i> Changer
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-shield-halved"></i> Sécurité
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-right-from-bracket"></i> Déconnexion
                                </h6>
                                <p>Se déconnecter de votre compte sur cet appareil</p>
                            </div>
                            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                                <i class="fa-solid fa-sign-out-alt"></i> Se déconnecter
                            </a>
                        </div>
                        
                        <div class="settings-item">
                            <div class="settings-item-info">
                                <h6>
                                    <i class="fa-solid fa-trash"></i> Supprimer le compte
                                </h6>
                                <p>Supprimer définitivement votre compte et toutes vos données</p>
                            </div>
                            <a href="dashboard.php#delete" class="btn btn-danger btn-sm">
                                <i class="fa-solid fa-trash-alt"></i> Supprimer
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../inscription-connexion/account.php" class="btn btn-outline-secondary">
                                <i class="fa-solid fa-arrow-left"></i> Retour au profil
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS local -->
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
<script>console.log("inline script");// Theme Toggle
document.addEventListener("DOMContentLoaded",function(){var t=document.getElementById("themeToggle"),e=document.documentElement;if(!t)return void console.error("Toggle not found");var n=localStorage.getItem("theme")||(window.matchMedia("(prefers-color-scheme:dark)").matches?"dark":"light");e.setAttribute("data-bs-theme",n),t.checked="dark"===n,t.addEventListener("change",function(){var t=this.checked?"dark":"light";e.setAttribute("data-bs-theme",t),localStorage.setItem("theme",t)})});</script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var toggle = document.getElementById("themeToggle");
        var html = document.documentElement;
        
        if (!toggle) {
            console.error("Toggle not found!");
            return;
        }
        
        var theme = localStorage.getItem("theme");
        if (!theme) {
            theme = window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        
        html.setAttribute("data-bs-theme", theme);
        toggle.checked = (theme === "dark");
        
        toggle.addEventListener("change", function() {
            var newTheme = this.checked ? "dark" : "light";
            html.setAttribute("data-bs-theme", newTheme);
            localStorage.setItem("theme", newTheme);
        });
    });
    </script>
</body>

</html>
