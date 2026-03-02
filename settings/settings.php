<?php
session_start();
if (!isset($_SESSION['auth_token'])) {
  header('Location: ../inscription-connexion/login.php');
  exit();
}

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (settings): " . $e->getMessage());
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isAdmin = false;
$username = $_SESSION['username'] ?? '';
$email = '';
$profilePhoto = null;
$authProvider = 'local';
$hasPassword = false;
$successMessage = '';
$errorMessage = '';

// Récupérer les informations utilisateur
if (isset($pdo)) {
  try {
    $stmt = $pdo->prepare("SELECT username, email, is_admin, profile_photo, auth_provider, password_hash FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $userData = $stmt->fetch();

    if ($userData) {
      $username = $userData['username'];
      $email = $userData['email'];
      $isAdmin = ($userData['is_admin'] == 1);
      $profilePhoto = $userData['profile_photo'];
      $authProvider = $userData['auth_provider'] ?? 'local';
      $hasPassword = !empty($userData['password_hash']);
    } else {
      // Compte supprimé par un admin
      $_SESSION = [];
      if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
      }
      session_destroy();
      header('Location: ../index.php?account_deleted=1');
      exit();
    }
  } catch (PDOException $ex) {
    error_log("Error fetching user data: " . $ex->getMessage());
  }
}

// --- TRAITEMENT DES FORMULAIRES POST ---

// Upload de photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_photo'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      $maxSize = 5 * 1024 * 1024;
      $fileType = $_FILES['photo']['type'];
      $fileSize = $_FILES['photo']['size'];

      if (!in_array($fileType, $allowedTypes)) {
        $errorMessage = "Format non autorisé. Utilisez JPG, PNG ou WEBP.";
      } elseif ($fileSize > $maxSize) {
        $errorMessage = "Fichier trop volumineux (max 5MB).";
      } else {
        $uploadDir = '../uploads/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $newFileName = 'user_' . $_SESSION['auth_token'] . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $newFileName;

        if ($profilePhoto && file_exists($uploadDir . $profilePhoto)) {
          unlink($uploadDir . $profilePhoto);
        }

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
          try {
            $pdo->prepare("UPDATE users SET profile_photo = ? WHERE auth_token = ?")->execute([$newFileName, $_SESSION['auth_token']]);
            $profilePhoto = $newFileName;
            $successMessage = "Photo de profil mise à jour !";
          } catch (PDOException $ex) {
            $errorMessage = "Erreur lors de la mise à jour.";
            error_log("Error updating profile photo: " . $ex->getMessage());
          }
        } else {
          $errorMessage = "Erreur lors de l'upload.";
        }
      }
    } else {
      $errorMessage = "Aucun fichier sélectionné.";
    }
  }
}

// Mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');

    if (empty($newUsername) || empty($newEmail)) {
      $errorMessage = "Tous les champs sont requis.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername)) {
      $errorMessage = "Nom d'utilisateur : 3-30 caractères alphanumériques.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $errorMessage = "Adresse email invalide.";
    } else {
      try {
        $check = $pdo->prepare("SELECT auth_token FROM users WHERE (username = ? OR email = ?) AND auth_token != ?");
        $check->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);
        if ($check->fetch()) {
          $errorMessage = "Ce nom d'utilisateur ou email est déjà utilisé.";
        } else {
          $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE auth_token = ?")->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);
          $username = $newUsername;
          $email = $newEmail;
          $_SESSION['username'] = $newUsername;
          $successMessage = "Profil mis à jour !";
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors de la mise à jour.";
        error_log("Error updating profile: " . $ex->getMessage());
      }
    }
  }
}

// Changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) && $hasPassword) {
      $errorMessage = "Veuillez entrer votre mot de passe actuel.";
    } elseif (empty($newPassword) || empty($confirmPassword)) {
      $errorMessage = "Veuillez remplir tous les champs.";
    } elseif (strlen($newPassword) < 6) {
      $errorMessage = "Minimum 6 caractères pour le mot de passe.";
    } elseif ($newPassword !== $confirmPassword) {
      $errorMessage = "Les mots de passe ne correspondent pas.";
    } else {
      try {
        if ($hasPassword) {
          $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE auth_token = ?");
          $pwdStmt->execute([$_SESSION['auth_token']]);
          $pwdData = $pwdStmt->fetch();
          if (!$pwdData || !password_verify($currentPassword, $pwdData['password_hash'])) {
            $errorMessage = "Mot de passe actuel incorrect.";
          }
        }
        if (empty($errorMessage)) {
          $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
          $pdo->prepare("UPDATE users SET password_hash = ? WHERE auth_token = ?")->execute([$newHash, $_SESSION['auth_token']]);
          $hasPassword = true;
          $successMessage = "Mot de passe modifié !";
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors du changement de mot de passe.";
        error_log("Error changing password: " . $ex->getMessage());
      }
    }
  }
}

// Suppression de compte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $confirmEmail = trim($_POST['confirm_email'] ?? '');
    $confirmPassword = $_POST['confirm_delete_password'] ?? '';

    if (empty($confirmEmail)) {
      $errorMessage = "Entrez votre email pour confirmer.";
    } elseif ($confirmEmail !== $email) {
      $errorMessage = "L'email ne correspond pas.";
    } elseif ($hasPassword && empty($confirmPassword)) {
      $errorMessage = "Entrez votre mot de passe pour confirmer.";
    } else {
      try {
        if ($hasPassword) {
          $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE auth_token = ?");
          $pwdStmt->execute([$_SESSION['auth_token']]);
          $pwdData = $pwdStmt->fetch();
          if (!$pwdData || !password_verify($confirmPassword, $pwdData['password_hash'])) {
            $errorMessage = "Mot de passe incorrect.";
          }
        }
        if (empty($errorMessage)) {
          if ($profilePhoto && file_exists('../uploads/profiles/' . basename($profilePhoto))) {
            unlink('../uploads/profiles/' . basename($profilePhoto));
          }
          $pdo->prepare("DELETE FROM users WHERE auth_token = ?")->execute([$_SESSION['auth_token']]);
          session_destroy();
          header('Location: ../index.php?account_deleted=1');
          exit();
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors de la suppression.";
        error_log("Error deleting account: " . $ex->getMessage());
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/settings.css">
  <link rel="stylesheet" href="../styles/theme.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - Paramètres</title>
</head>

<body>
  <!-- Barre du haut -->
  <div class="settings-top-bar">
    <a href="../index.php" class="settings-logo">
      <img src="../assets/images/logo.svg" alt="Market Plier">
    </a>
    <a href="../inscription-connexion/account.php" class="settings-back-link">
      <i class="fas fa-arrow-left"></i> Retour au profil
    </a>
    <button class="theme-toggle" data-theme-toggle title="Changer le thème">
      <i class="fa-solid fa-moon"></i>
      <i class="fa-solid fa-sun"></i>
    </button>
  </div>

  <!-- Modal suppression de compte -->
  <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content" style="border-radius: 18px; border: 2px solid #e74c3c; overflow: hidden;">
        <div class="modal-header" style="background: #fde8e8; border-bottom: 2px solid #e74c3c;">
          <h5 class="modal-title" style="color: #c0392b; font-weight: 700; font-style: italic;">
            <i class="fas fa-exclamation-triangle"></i> Supprimer mon compte
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="modal-body" style="padding: 24px;">
            <p style="color: #c0392b; font-weight: 600; margin-bottom: 16px;">
              <i class="fas fa-exclamation-circle"></i> Cette action est irréversible. Toutes vos données seront supprimées.
            </p>
            <div class="settings-field">
              <label class="settings-label" for="confirm_email">Confirmez votre email</label>
              <input type="email" class="settings-input" id="confirm_email" name="confirm_email"
                placeholder="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <?php if ($hasPassword): ?>
              <div class="settings-field" style="margin-top: 12px;">
                <label class="settings-label" for="confirm_delete_password">Mot de passe</label>
                <input type="password" class="settings-input" id="confirm_delete_password"
                  name="confirm_delete_password" required>
              </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer" style="border-top: 1px solid #eee; padding: 16px 24px;">
            <button type="button" class="settings-btn settings-btn-outline" data-bs-dismiss="modal"
              style="border-color: var(--mp-border); color: var(--mp-text-muted);">Annuler</button>
            <button type="submit" name="delete_account" class="settings-btn settings-btn-danger">
              <i class="fas fa-trash-alt"></i> Supprimer définitivement
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <main class="settings-container">
    <h1 class="settings-title">Paramètres</h1>

    <!-- Messages -->
    <?php if ($successMessage): ?>
      <div class="settings-alert settings-alert-success">
        <i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
      <div class="settings-alert settings-alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <!-- Mon compte -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-user"></i> Mon compte</h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="update_profile" value="1">
        <div class="settings-field">
          <label class="settings-label" for="username">Nom d'utilisateur</label>
          <input type="text" class="settings-input" id="username" name="username"
            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
            required pattern="[a-zA-Z0-9_]{3,30}"
            data-pattern-message="3 à 30 caractères : lettres, chiffres et underscore">
          <span class="settings-hint">3-30 caractères alphanumériques et underscore.</span>
        </div>
        <div class="settings-field">
          <label class="settings-label" for="email">Adresse email</label>
          <input type="email" class="settings-input" id="email" name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <button type="submit" class="settings-btn settings-btn-primary">
          <i class="fas fa-save"></i> Enregistrer
        </button>
      </form>
    </section>

    <!-- Photo de profil -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-camera"></i> Photo de profil</h2>
      <div class="settings-photo-area">
        <img src="<?= ($profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto)) ? '../uploads/profiles/' . htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') : '../assets/images/default-avatar.svg' ?>"
          alt="Photo de profil" class="settings-avatar">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="changer_photo" value="1">
          <label class="settings-btn settings-btn-outline" style="cursor: pointer;">
            <i class="fas fa-upload"></i> Changer la photo
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" style="display:none;" onchange="this.form.submit()">
          </label>
        </form>
      </div>
    </section>

    <!-- Sécurité -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-lock"></i> Sécurité</h2>
      <?php if ($authProvider === 'google' && !$hasPassword): ?>
        <div class="settings-alert settings-alert-info" style="background: #e3f2fd; color: #1976d2; border: 1.5px solid #2196f3; margin-bottom: 16px;">
          <i class="fas fa-info-circle"></i>
          Connecté via Google. Définissez un mot de passe pour vous connecter aussi par email.
        </div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="change_password" value="1">
        <?php if ($hasPassword): ?>
          <div class="settings-field">
            <label class="settings-label" for="current_password">Mot de passe actuel</label>
            <input type="password" class="settings-input" id="current_password" name="current_password" required>
          </div>
        <?php endif; ?>
        <div class="settings-field">
          <label class="settings-label" for="new_password">
            <?= $hasPassword ? 'Nouveau mot de passe' : 'Définir un mot de passe' ?>
          </label>
          <input type="password" class="settings-input" id="new_password" name="new_password" required minlength="6">
          <span class="settings-hint">Minimum 6 caractères</span>
        </div>
        <div class="settings-field">
          <label class="settings-label" for="confirm_password">Confirmer le mot de passe</label>
          <input type="password" class="settings-input" id="confirm_password" name="confirm_password" required minlength="6">
        </div>
        <button type="submit" class="settings-btn settings-btn-primary">
          <i class="fas fa-key"></i> <?= $hasPassword ? 'Changer le mot de passe' : 'Définir le mot de passe' ?>
        </button>
      </form>
    </section>

    <!-- Apparence -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-palette"></i> Apparence</h2>
      <p class="settings-desc">Choisissez votre thème préféré.</p>
      <div class="theme-options">
        <button class="theme-option" id="theme-light">
          <i class="fas fa-sun"></i><span>Clair</span>
        </button>
        <button class="theme-option" id="theme-dark">
          <i class="fas fa-moon"></i><span>Sombre</span>
        </button>
      </div>
    </section>

    <!-- Notifications -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-bell"></i> Notifications</h2>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Notifications par email</span>
          <span class="toggle-desc">Recevez des emails pour les nouveaux messages</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Alertes de prix</span>
          <span class="toggle-desc">Soyez notifié des baisses de prix sur vos favoris</span>
        </div>
        <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Résumé hebdomadaire</span>
          <span class="toggle-desc">Recevez un résumé de l'activité chaque semaine</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
    </section>

    <!-- Confidentialité -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-shield-alt"></i> Confidentialité</h2>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Profil public</span>
          <span class="toggle-desc">Les autres utilisateurs peuvent voir votre profil</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Afficher l'email</span>
          <span class="toggle-desc">Votre adresse email est visible sur votre profil</span>
        </div>
        <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label">Historique d'activité</span>
          <span class="toggle-desc">Les autres peuvent voir vos annonces passées</span>
        </div>
        <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
      </div>
    </section>

    <!-- Langue -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-language"></i> Langue</h2>
      <div class="settings-field">
        <select class="settings-select" id="language">
          <option value="fr" selected>Français</option>
          <option value="en">English</option>
          <option value="es">Español</option>
          <option value="de">Deutsch</option>
        </select>
      </div>
    </section>

    <!-- Lien admin -->
    <?php if ($isAdmin): ?>
      <section class="settings-section admin-section">
        <h2 class="settings-section-title"><i class="fas fa-crown"></i> Administration</h2>
        <p class="settings-desc">Gérez les utilisateurs, les annonces et les options du site.</p>
        <a href="admin.php" class="settings-btn settings-btn-admin">
          <i class="fas fa-tools"></i> Ouvrir le panneau d'administration
        </a>
      </section>
    <?php endif; ?>

    <!-- Zone dangereuse -->
    <section class="settings-section danger-section">
      <h2 class="settings-section-title"><i class="fas fa-exclamation-triangle"></i> Zone dangereuse</h2>
      <div class="danger-actions">
        <a href="../inscription-connexion/logout.php" class="settings-btn settings-btn-danger">
          <i class="fas fa-sign-out-alt"></i> Se déconnecter
        </a>
        <button type="button" class="settings-btn settings-btn-danger-outline"
          data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
          <i class="fas fa-trash-alt"></i> Supprimer mon compte
        </button>
      </div>
    </section>
  </main>

  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../styles/theme.js"></script>
  <script src="../styles/form-validation.js"></script>
  <script>
    // Boutons de thème
    (function() {
      var themeLight = document.getElementById('theme-light');
      var themeDark = document.getElementById('theme-dark');

      function update() {
        var c = document.documentElement.getAttribute('data-bs-theme') || 'light';
        if (themeLight) themeLight.classList.toggle('active', c === 'light');
        if (themeDark) themeDark.classList.toggle('active', c === 'dark');
      }

      if (themeLight) themeLight.addEventListener('click', function() {
        document.documentElement.setAttribute('data-bs-theme', 'light');
        localStorage.setItem('mp-theme', 'light');
        update();
      });
      if (themeDark) themeDark.addEventListener('click', function() {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        localStorage.setItem('mp-theme', 'dark');
        update();
      });

      update();
      new MutationObserver(update).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
      });
    })();
  </script>
</body>

</html>