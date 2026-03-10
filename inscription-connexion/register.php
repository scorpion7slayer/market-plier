<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/site_settings.php';
require_once '../includes/lang.php';

if (isset($_SESSION['auth_token'])) {
  header('Location: ../index.php');
  exit();
}

$registrationClosed = (getSiteSetting($pdo, 'registration_open') === '0');
$googleLoginEnabled = (getSiteSetting($pdo, 'google_login') === '1');

// Générer un token CSRF s'il n'existe pas encore (token par session)
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/register.css">
  <link rel="stylesheet" href="../styles/theme.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title><?= htmlspecialchars(t('register_title') . ' - Market Plier', ENT_QUOTES, 'UTF-8') ?></title>
</head>

<body>
  <?php include '../includes/toast.php'; ?>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="" class="auth-logo-img">
    <p class="auth-link" style="margin-top: 8px;"><a href="../index.php">&larr; <?= htmlspecialchars(t('login_back'), ENT_QUOTES, 'UTF-8') ?></a></p>
  </div>
  <!-- Theme toggle flottant -->
  <button class="theme-toggle" data-theme-toggle style="position: fixed; top: 20px; right: 20px; z-index: 1001; color: var(--mp-text-muted); font-size: 20px;" title="Changer le thème">
    <i class="fa-solid fa-moon"></i>
    <i class="fa-solid fa-sun"></i>
  </button>

  <div class="register-container">

    <main class="form-container">
      <h2 class="title"><?= htmlspecialchars(t('register_title'), ENT_QUOTES, 'UTF-8') ?></h2>

      <?php if ($registrationClosed): ?>
        <div style="text-align: center; padding: 24px 0;">
          <i class="fas fa-lock" style="font-size: 2rem; color: var(--mp-text-muted, #888); margin-bottom: 12px;"></i>
          <p style="color: var(--mp-text-muted, #888); font-style: italic;"><?= htmlspecialchars(t('register_closed'), ENT_QUOTES, 'UTF-8') ?></p>
          <p class="auth-link"><a href="login.php"><?= htmlspecialchars(t('register_login'), ENT_QUOTES, 'UTF-8') ?></a> &middot; <a href="../index.php"><?= htmlspecialchars(t('login_back'), ENT_QUOTES, 'UTF-8') ?></a></p>
        </div>
      <?php else: ?>

      <form class="signup-form" action="handle_register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <input
          type="text"
          class="email-input"
          name="username"
          id="username"
          placeholder="<?= htmlspecialchars(t('register_username_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
          required>

        <input
          type="email"
          class="email-input"
          name="email"
          id="email"
          placeholder="<?= htmlspecialchars(t('register_email_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
          required>

        <div class="password-row">
          <div class="password-field">
            <div class="password-wrapper">
              <input
                type="password"
                class="email-input"
                name="password"
                id="password"
                placeholder="<?= htmlspecialchars(t('register_password_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                required>
              <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
                <i class="fa-solid fa-eye"></i>
                <i class="fa-solid fa-eye-slash"></i>
              </button>
            </div>
          </div>
          <div class="password-field">
            <div class="password-wrapper">
              <input
                type="password"
                class="email-input"
                name="confirm_password"
                id="confirm_password"
                placeholder="<?= htmlspecialchars(t('register_confirm_placeholder'), ENT_QUOTES, 'UTF-8') ?>"
                required>
              <button type="button" class="password-toggle" aria-label="Afficher le mot de passe">
                <i class="fa-solid fa-eye"></i>
                <i class="fa-solid fa-eye-slash"></i>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" class="submit-btn">
          <?= htmlspecialchars(t('register_submit'), ENT_QUOTES, 'UTF-8') ?>
        </button>

        <?php if ($googleLoginEnabled): ?>
        <div class="divider">
          <span class="divider-text"><?= htmlspecialchars(t('login_or'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <a href="../google/google_login.php" class="btn-google">
          <img src="https://www.google.com/favicon.ico" alt="" width="18" height="18">
          <?= htmlspecialchars(t('register_google'), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php endif; ?>
      </form>

      <p class="auth-link"><?= htmlspecialchars(t('register_has_account'), ENT_QUOTES, 'UTF-8') ?> <a href="login.php"><?= htmlspecialchars(t('register_login'), ENT_QUOTES, 'UTF-8') ?></a></p>
      <?php endif; ?>
    </main>
  </div>
  <?php
  $footerBasePath = '../';
  include '../footer.php';
  ?>
  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../styles/theme.js"></script>
  <script src="../styles/form-validation.js"></script>
</body>

</html>
