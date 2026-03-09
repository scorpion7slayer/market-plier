<?php
session_start();

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (settings): " . $e->getMessage());
}
require_once '../includes/remember_me.php';
require_once __DIR__ . '/settings_handlers.php';
require_once __DIR__ . '/../includes/lang.php';

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

// --- TRADUCTIONS ---
$_translations = loadTranslations($userSettings['language'] ?? 'fr');
$htmlLang = ($userSettings['language'] ?? 'fr') === 'fr' ? 'fr' : $userSettings['language'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8') ?>">

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
  <title><?= htmlspecialchars(t('page_title'), ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body>
  <!-- Barre du haut -->
  <div class="settings-top-bar">
    <a href="../index.php" class="settings-logo">
      <img src="../assets/images/logo.svg" alt="Market Plier">
    </a>
    <a href="../inscription-connexion/account.php" class="settings-back-link">
      <i class="fas fa-arrow-left"></i> <?= htmlspecialchars(t('back_to_profile'), ENT_QUOTES, 'UTF-8') ?>
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
            <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars(t('delete_account_title'), ENT_QUOTES, 'UTF-8') ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <div class="modal-body" style="padding: 24px;">
            <p style="color: #c0392b; font-weight: 600; margin-bottom: 16px;">
              <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars(t('delete_warning'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <div class="settings-field">
              <label class="settings-label" for="confirm_email"><?= htmlspecialchars(t('confirm_email'), ENT_QUOTES, 'UTF-8') ?></label>
              <input type="email" class="settings-input" id="confirm_email" name="confirm_email"
                placeholder="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <?php if ($hasPassword): ?>
              <div class="settings-field" style="margin-top: 12px;">
                <label class="settings-label" for="confirm_delete_password"><?= htmlspecialchars(t('password'), ENT_QUOTES, 'UTF-8') ?></label>
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
              style="border-color: var(--mp-border); color: var(--mp-text-muted);"><?= htmlspecialchars(t('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
            <button type="submit" name="delete_account" class="settings-btn settings-btn-danger">
              <i class="fas fa-trash-alt"></i> <?= htmlspecialchars(t('delete_confirm'), ENT_QUOTES, 'UTF-8') ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <main class="settings-container">
    <h1 class="settings-title"><?= htmlspecialchars(t('settings_title'), ENT_QUOTES, 'UTF-8') ?></h1>

    <?php
    $toastSuccess = $successMessage;
    $toastError = $errorMessage;
    include '../includes/toast.php';
    ?>

    <!-- Mon compte -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-user"></i> <?= htmlspecialchars(t('my_account'), ENT_QUOTES, 'UTF-8') ?></h2>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="update_profile" value="1">
        <div class="settings-field">
          <label class="settings-label" for="username"><?= htmlspecialchars(t('username'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="text" class="settings-input" id="username" name="username"
            value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
            required pattern="[a-zA-Z0-9_]{3,30}"
            data-pattern-message="<?= htmlspecialchars(t('username_hint'), ENT_QUOTES, 'UTF-8') ?>">
          <span class="settings-hint"><?= htmlspecialchars(t('username_hint'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="settings-field">
          <label class="settings-label" for="email"><?= htmlspecialchars(t('email'), ENT_QUOTES, 'UTF-8') ?></label>
          <input type="email" class="settings-input" id="email" name="email"
            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
        </div>
        <button type="submit" class="settings-btn settings-btn-primary">
          <i class="fas fa-save"></i> <?= htmlspecialchars(t('save'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </form>
    </section>

    <!-- Photo de profil -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-camera"></i> <?= htmlspecialchars(t('profile_photo'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div id="photoDropzone" class="settings-dropzone" data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="file" id="photoInput" accept=".jpg,.jpeg,.png,.webp" style="position:absolute;left:-9999px;">
        <img src="<?= ($profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto)) ? '../uploads/profiles/' . htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') : '../assets/images/default-avatar.svg' ?>"
          alt="<?= htmlspecialchars(t('profile_photo'), ENT_QUOTES, 'UTF-8') ?>" class="settings-avatar" id="avatarPreview">
        <div class="settings-dropzone-text">
          <p class="settings-dropzone-main"><i class="fas fa-cloud-upload-alt"></i> <?= htmlspecialchars(t('drag_photo'), ENT_QUOTES, 'UTF-8') ?></p>
          <p class="settings-dropzone-sub"><?= t('or_browse') ?></p>
          <p class="settings-dropzone-hint"><?= htmlspecialchars(t('photo_hint'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
      </div>
    </section>

    <!-- Sécurité -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-lock"></i> <?= htmlspecialchars(t('security'), ENT_QUOTES, 'UTF-8') ?></h2>
      <?php if ($authProvider === 'google' && !$hasPassword): ?>
        <div class="settings-alert settings-alert-info" style="background: #e3f2fd; color: #1976d2; border: 1.5px solid #2196f3; margin-bottom: 16px;">
          <i class="fas fa-info-circle"></i>
          <?= htmlspecialchars(t('google_info'), ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="change_password" value="1">
        <?php if ($hasPassword): ?>
          <div class="settings-field">
            <label class="settings-label" for="current_password"><?= htmlspecialchars(t('current_password'), ENT_QUOTES, 'UTF-8') ?></label>
            <div class="password-wrapper">
              <input type="password" class="settings-input" id="current_password" name="current_password" required>
              <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars(t('show_password'), ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-eye"></i>
                <i class="fa-solid fa-eye-slash"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>
        <div class="settings-field">
          <label class="settings-label" for="new_password">
            <?= htmlspecialchars($hasPassword ? t('new_password') : t('set_password'), ENT_QUOTES, 'UTF-8') ?>
          </label>
          <div class="password-wrapper">
            <input type="password" class="settings-input" id="new_password" name="new_password" required minlength="6">
            <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars(t('show_password'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="fa-solid fa-eye"></i>
              <i class="fa-solid fa-eye-slash"></i>
            </button>
          </div>
          <span class="settings-hint"><?= htmlspecialchars(t('password_hint'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="settings-field">
          <label class="settings-label" for="confirm_password"><?= htmlspecialchars(t('confirm_password'), ENT_QUOTES, 'UTF-8') ?></label>
          <div class="password-wrapper">
            <input type="password" class="settings-input" id="confirm_password" name="confirm_password" required minlength="6">
            <button type="button" class="password-toggle" aria-label="<?= htmlspecialchars(t('show_password'), ENT_QUOTES, 'UTF-8') ?>">
              <i class="fa-solid fa-eye"></i>
              <i class="fa-solid fa-eye-slash"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="settings-btn settings-btn-primary">
          <i class="fas fa-key"></i> <?= htmlspecialchars($hasPassword ? t('change_password') : t('set_password_btn'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </form>
    </section>

    <!-- Apparence -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-palette"></i> <?= htmlspecialchars(t('appearance'), ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="settings-desc"><?= htmlspecialchars(t('appearance_desc'), ENT_QUOTES, 'UTF-8') ?></p>
      <div class="theme-options">
        <button class="theme-option" id="theme-light">
          <i class="fas fa-sun"></i><span><?= htmlspecialchars(t('theme_light'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <button class="theme-option" id="theme-dark">
          <i class="fas fa-moon"></i><span><?= htmlspecialchars(t('theme_dark'), ENT_QUOTES, 'UTF-8') ?></span>
        </button>
      </div>
    </section>

    <!-- Notifications -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-bell"></i> <?= htmlspecialchars(t('notifications'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="toggle-row" id="notifBrowserRow">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('notif_browser'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('notif_browser_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" id="notifBrowserToggle" data-setting="notif_email" <?= $userSettings['notif_email'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('notif_price_alerts'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('notif_price_alerts_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="notif_price_alerts" <?= $userSettings['notif_price_alerts'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('notif_weekly_summary'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('notif_weekly_summary_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="notif_weekly_summary" <?= $userSettings['notif_weekly_summary'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
    </section>

    <!-- Confidentialité -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-shield-alt"></i> <?= htmlspecialchars(t('privacy'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('privacy_public_profile'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('privacy_public_profile_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="privacy_public_profile" <?= $userSettings['privacy_public_profile'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('privacy_show_email'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('privacy_show_email_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="privacy_show_email" <?= $userSettings['privacy_show_email'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
      <div class="toggle-row">
        <div class="toggle-info">
          <span class="toggle-label"><?= htmlspecialchars(t('privacy_activity_history'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="toggle-desc"><?= htmlspecialchars(t('privacy_activity_history_desc'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <label class="toggle-switch"><input type="checkbox" data-setting="privacy_activity_history" <?= $userSettings['privacy_activity_history'] ? 'checked' : '' ?>><span class="toggle-slider"></span></label>
      </div>
    </section>

    <!-- Langue -->
    <section class="settings-section">
      <h2 class="settings-section-title"><i class="fas fa-language"></i> <?= htmlspecialchars(t('language'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="settings-field">
        <select class="settings-select" id="language" data-setting="language">
          <option value="fr" <?= $userSettings['language'] === 'fr' ? 'selected' : '' ?>>Français</option>
          <option value="en" <?= $userSettings['language'] === 'en' ? 'selected' : '' ?>>English</option>
          <option value="es" <?= $userSettings['language'] === 'es' ? 'selected' : '' ?>>Español</option>
          <option value="de" <?= $userSettings['language'] === 'de' ? 'selected' : '' ?>>Deutsch</option>
        </select>
      </div>
    </section>

    <!-- Lien admin -->
    <?php if ($isAdmin): ?>
      <section class="settings-section admin-section">
        <h2 class="settings-section-title"><i class="fas fa-crown"></i> <?= htmlspecialchars(t('admin'), ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="settings-desc"><?= htmlspecialchars(t('admin_desc'), ENT_QUOTES, 'UTF-8') ?></p>
        <a href="admin.php" class="settings-btn settings-btn-admin">
          <i class="fas fa-tools"></i> <?= htmlspecialchars(t('admin_panel'), ENT_QUOTES, 'UTF-8') ?>
        </a>
      </section>
    <?php endif; ?>

    <!-- Zone dangereuse -->
    <section class="settings-section danger-section">
      <h2 class="settings-section-title"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars(t('danger_zone'), ENT_QUOTES, 'UTF-8') ?></h2>
      <div class="danger-actions">
        <a href="../inscription-connexion/logout.php" class="settings-btn settings-btn-danger">
          <i class="fas fa-sign-out-alt"></i> <?= htmlspecialchars(t('logout'), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <button type="button" class="settings-btn settings-btn-danger-outline"
          data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
          <i class="fas fa-trash-alt"></i> <?= htmlspecialchars(t('delete_account'), ENT_QUOTES, 'UTF-8') ?>
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
            <i class="fas fa-crop-alt"></i> <?= htmlspecialchars(t('crop_title'), ENT_QUOTES, 'UTF-8') ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="crop-modal-body">
          <div id="cropContainer"></div>
        </div>
        <div class="crop-modal-footer">
          <button type="button" class="settings-btn settings-btn-outline crop-btn-cancel" data-bs-dismiss="modal"><?= htmlspecialchars(t('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
          <button type="button" class="settings-btn settings-btn-primary" id="cropConfirmBtn">
            <i class="fas fa-check"></i> <?= htmlspecialchars(t('crop_confirm'), ENT_QUOTES, 'UTF-8') ?>
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
  <script>
    // Preserve scroll position across form submissions
    (function() {
      var key = 'mp-scroll-settings';
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
    // Sauvegarde AJAX des paramètres (toggles + langue) + notifications navigateur
    (function() {
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
      var i18n = <?= json_encode([
        'setting_saved' => t('setting_saved'),
        'network_error' => t('network_error'),
        'save_error' => t('save_error'),
        'notif_permission_denied' => t('notif_permission_denied'),
        'notif_not_supported' => t('notif_not_supported'),
      ]) ?>;

      function saveSetting(setting, value, toggleRow) {
        if (toggleRow) toggleRow.classList.add('saving');

        return fetch('save_settings.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            csrf_token: csrfToken,
            setting: setting,
            value: value
          })
        })
        .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
        .then(function(result) {
          if (toggleRow) toggleRow.classList.remove('saving');
          if (!result.ok) {
            showSettingsToast(result.data.error || i18n.save_error, 'error');
            return false;
          } else {
            showSettingsToast(i18n.setting_saved, 'success');
            return true;
          }
        })
        .catch(function() {
          if (toggleRow) toggleRow.classList.remove('saving');
          showSettingsToast(i18n.network_error, 'error');
          return false;
        });
      }

      // Mini toast pour feedback
      function showSettingsToast(message, type) {
        var existing = document.querySelector('.settings-mini-toast');
        if (existing) existing.remove();

        var toast = document.createElement('div');
        toast.className = 'settings-mini-toast settings-mini-toast-' + type;
        toast.textContent = message;
        document.body.appendChild(toast);

        requestAnimationFrame(function() {
          toast.classList.add('show');
        });
        setTimeout(function() {
          toast.classList.remove('show');
          setTimeout(function() { toast.remove(); }, 300);
        }, 2000);
      }

      // --- Notifications navigateur ---
      var notifToggle = document.getElementById('notifBrowserToggle');
      if (notifToggle) {
        // Synchroniser l'état avec la permission du navigateur au chargement
        if (!('Notification' in window)) {
          notifToggle.checked = false;
          notifToggle.disabled = true;
          notifToggle.closest('.toggle-row').querySelector('.toggle-desc').textContent = i18n.notif_not_supported;
        } else if (Notification.permission === 'denied') {
          notifToggle.checked = false;
        }

        notifToggle.addEventListener('change', function() {
          var row = notifToggle.closest('.toggle-row');

          if (!('Notification' in window)) {
            notifToggle.checked = false;
            showSettingsToast(i18n.notif_not_supported, 'error');
            return;
          }

          if (notifToggle.checked) {
            // Demander la permission du navigateur
            Notification.requestPermission().then(function(permission) {
              if (permission === 'granted') {
                saveSetting('notif_email', true, row);
                // Notification test
                new Notification('Market Plier', {
                  body: i18n.setting_saved,
                  icon: '../assets/images/logo.svg'
                });
              } else {
                // Permission refusée : décocher
                notifToggle.checked = false;
                saveSetting('notif_email', false, row);
                showSettingsToast(i18n.notif_permission_denied, 'error');
              }
            });
          } else {
            saveSetting('notif_email', false, row);
          }
        });
      }

      // Toggles (checkboxes avec data-setting, sauf notif navigateur géré au-dessus)
      document.querySelectorAll('input[type="checkbox"][data-setting]').forEach(function(cb) {
        if (cb.id === 'notifBrowserToggle') return;
        cb.addEventListener('change', function() {
          var row = cb.closest('.toggle-row');
          saveSetting(cb.getAttribute('data-setting'), cb.checked, row);
        });
      });

      // Sélecteur de langue : sauvegarder puis recharger la page
      var langSelect = document.getElementById('language');
      if (langSelect) {
        langSelect.addEventListener('change', function() {
          var field = langSelect.closest('.settings-field');
          saveSetting('language', langSelect.value, field).then(function(success) {
            if (success) {
              // Recharger pour appliquer les traductions
              window.location.reload();
            }
          });
        });
      }
    })();
  </script>
</body>

</html>