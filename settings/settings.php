<?php
session_start();

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (settings): " . $e->getMessage());
}
require_once '../includes/remember_me.php';
require_once __DIR__ . '/settings_handlers.php';

if (!isset($_SESSION['auth_token'])) {
  header('Location: ../inscription-connexion/login.php');
  exit();
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- INITIALISATION ET TRAITEMENT ---
$state = initPageState($pdo ?? null);

if (isset($pdo) && $_SERVER['REQUEST_METHOD'] === 'POST') {
  handlePhotoUpload($pdo, $state);
  handleUpdateProfile($pdo, $state);
  handleChangePassword($pdo, $state);
  handleDeleteAccount($pdo, $state);
}

extract($state);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/settings.css">
  <link rel="stylesheet" href="../styles/theme.css">
  <script src="../node_modules/cropperjs/dist/cropper.js"></script>
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
                <div class="password-wrapper">
                  <input type="password" class="settings-input" id="confirm_delete_password"
                    name="confirm_delete_password" required>
                  <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
                    <i class="fa-solid fa-eye"></i>
                    <i class="fa-solid fa-eye-slash"></i>
                  </button>
                </div>
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
      <div id="photoDropzone" class="settings-dropzone" data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="file" id="photoInput" accept=".jpg,.jpeg,.png,.webp" style="position:absolute;left:-9999px;">
        <img src="<?= ($profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto)) ? '../uploads/profiles/' . htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') : '../assets/images/default-avatar.svg' ?>"
          alt="Photo de profil" class="settings-avatar" id="avatarPreview">
        <div class="settings-dropzone-text">
          <p class="settings-dropzone-main"><i class="fas fa-cloud-upload-alt"></i> Glissez votre photo ici</p>
          <p class="settings-dropzone-sub">ou <span class="settings-dropzone-link">parcourez vos fichiers</span></p>
          <p class="settings-dropzone-hint">JPG, PNG ou WEBP — compressé automatiquement</p>
        </div>
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
            <div class="password-wrapper">
              <input type="password" class="settings-input" id="current_password" name="current_password" required>
              <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
                <i class="fa-solid fa-eye"></i>
                <i class="fa-solid fa-eye-slash"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>
        <div class="settings-field">
          <label class="settings-label" for="new_password">
            <?= $hasPassword ? 'Nouveau mot de passe' : 'Définir un mot de passe' ?>
          </label>
          <div class="password-wrapper">
            <input type="password" class="settings-input" id="new_password" name="new_password" required minlength="6">
            <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
              <i class="fa-solid fa-eye"></i>
              <i class="fa-solid fa-eye-slash"></i>
            </button>
          </div>
          <span class="settings-hint">Minimum 6 caractères</span>
        </div>
        <div class="settings-field">
          <label class="settings-label" for="confirm_password">Confirmer le mot de passe</label>
          <div class="password-wrapper">
            <input type="password" class="settings-input" id="confirm_password" name="confirm_password" required minlength="6">
            <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
              <i class="fa-solid fa-eye"></i>
              <i class="fa-solid fa-eye-slash"></i>
            </button>
          </div>
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

  <!-- Modal recadrage -->
  <div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content crop-modal-content">
        <div class="crop-modal-header">
          <h5 class="crop-modal-title">
            <i class="fas fa-crop-alt"></i> Recadrer la photo
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="crop-modal-body">
          <div id="cropContainer"></div>
        </div>
        <div class="crop-modal-footer">
          <button type="button" class="settings-btn settings-btn-outline crop-btn-cancel" data-bs-dismiss="modal">Annuler</button>
          <button type="button" class="settings-btn settings-btn-primary" id="cropConfirmBtn">
            <i class="fas fa-check"></i> Valider
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../styles/theme.js"></script>
  <script src="../styles/form-validation.js"></script>
  <script>
    // Photo de profil : drag/drop + recadrage Cropper.js v2 + compression
    (function() {
      var dropzone = document.getElementById('photoDropzone');
      var input = document.getElementById('photoInput');
      if (!dropzone || !input) return;

      var csrfToken = dropzone.getAttribute('data-csrf');
      var cropModalEl = document.getElementById('cropModal');
      var cropModal = new bootstrap.Modal(cropModalEl);
      var cropContainer = document.getElementById('cropContainer');
      var confirmBtn = document.getElementById('cropConfirmBtn');

      function validateFile(file) {
        var allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (allowed.indexOf(file.type) === -1) {
          alert('Format non autorisé. Utilisez JPG, PNG ou WEBP.');
          return false;
        }
        if (file.size > 50 * 1024 * 1024) {
          alert('Fichier trop volumineux (max 50 MB).');
          return false;
        }
        return true;
      }

      var pendingImageSrc = null;

      function initCropperInModal() {
        if (!pendingImageSrc) return;
        cropContainer.innerHTML =
          '<cropper-canvas style="width:100%;height:100%;">' +
          '<cropper-image src="' + pendingImageSrc + '" alt="Recadrer" initial-center-size="contain" scalable rotatable translatable></cropper-image>' +
          '<cropper-shade hidden></cropper-shade>' +
          '<cropper-handle action="select" plain></cropper-handle>' +
          '<cropper-selection id="cropSelection" initial-coverage="0.5" aspect-ratio="1" movable resizable outlined theme-color="#7fb885">' +
          '<cropper-grid role="grid" bordered covered theme-color="rgba(127, 184, 133, 0.35)"></cropper-grid>' +
          '<cropper-crosshair centered theme-color="rgba(127, 184, 133, 0.5)"></cropper-crosshair>' +
          '<cropper-handle action="move" theme-color="rgba(127, 184, 133, 0.15)"></cropper-handle>' +
          '<cropper-handle action="n-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="e-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="s-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="w-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="ne-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="nw-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="se-resize" theme-color="#7fb885"></cropper-handle>' +
          '<cropper-handle action="sw-resize" theme-color="#7fb885"></cropper-handle>' +
          '</cropper-selection>' +
          '</cropper-canvas>';
        pendingImageSrc = null;
      }

      // Injecter le cropper une fois la modal visible (dimensions calculables)
      cropModalEl.addEventListener('shown.bs.modal', initCropperInModal);

      function openCropper(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
          pendingImageSrc = e.target.result;
          cropModal.show();
        };
        reader.readAsDataURL(file);
      }

      // Valider le recadrage
      confirmBtn.addEventListener('click', function() {
        var selection = document.getElementById('cropSelection');
        if (!selection || typeof selection.$toCanvas !== 'function') {
          alert('Erreur : le recadrage n\'est pas prêt.');
          return;
        }
        confirmBtn.disabled = true;
        selection.$toCanvas({
          width: 400,
          height: 400
        }).then(function(canvas) {
          canvas.toBlob(function(blob) {
            confirmBtn.disabled = false;
            if (!blob) {
              alert('Erreur de recadrage.');
              return;
            }
            uploadBlob(blob);
            cropModal.hide();
          }, 'image/jpeg', 0.85);
        }).catch(function() {
          confirmBtn.disabled = false;
          alert('Erreur lors du recadrage.');
        });
      });

      // Nettoyage à la fermeture de la modal
      cropModalEl.addEventListener('hidden.bs.modal', function() {
        cropContainer.innerHTML = '';
        input.value = '';
      });

      function uploadBlob(blob) {
        var fd = new FormData();
        fd.append('csrf_token', csrfToken);
        fd.append('changer_photo', '1');
        fd.append('photo', blob, 'avatar.jpg');
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'settings.php', true);
        xhr.onload = function() {
          window.location.href = 'settings.php?success=photo';
        };
        xhr.onerror = function() {
          alert('Erreur réseau.');
        };
        xhr.send(fd);
      }

      // Clic sur la zone = ouvrir le sélecteur de fichiers
      dropzone.addEventListener('click', function() {
        input.click();
      });

      // Fichier sélectionné via le sélecteur
      input.addEventListener('change', function() {
        if (input.files.length && validateFile(input.files[0])) {
          openCropper(input.files[0]);
        }
      });

      // Drag & drop
      ['dragenter', 'dragover'].forEach(function(evt) {
        dropzone.addEventListener(evt, function(e) {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.add('dragover');
        });
      });
      ['dragleave', 'drop'].forEach(function(evt) {
        dropzone.addEventListener(evt, function(e) {
          e.preventDefault();
          e.stopPropagation();
          dropzone.classList.remove('dragover');
        });
      });
      dropzone.addEventListener('drop', function(e) {
        var files = e.dataTransfer.files;
        if (files.length && validateFile(files[0])) {
          openCropper(files[0]);
        }
      });
    })();
  </script>
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