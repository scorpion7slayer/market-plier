<?php

$footerBasePath = $footerBasePath ?? ($headerBasePath ?? '');
$footerLang = getUserLang();
$isLoggedIn = isset($_SESSION['auth_token']);
$accountHomeHref = $isLoggedIn ? 'inscription-connexion/account.php' : 'inscription-connexion/login.php';
$currentUri = $_SERVER['REQUEST_URI'] ?? ($footerBasePath . 'index.php');

$accountLinks = $isLoggedIn
  ? [
      ['href' => 'inscription-connexion/account.php', 'label' => t('header_my_profile')],
      ['href' => 'favoris/', 'label' => t('header_favorites')],
      ['href' => 'messagerie/inbox.php', 'label' => t('header_messages')],
      ['href' => 'notifications/', 'label' => t('header_notifications')],
    ]
  : [
      ['href' => 'inscription-connexion/register.php', 'label' => t('header_register')],
      ['href' => 'inscription-connexion/login.php', 'label' => t('login_title')],
      ['href' => 'shop/search.php', 'label' => t('footer_browse')],
      ['href' => 'shop/sell.php', 'label' => t('footer_post_listing')],
    ];
?>
<footer class="mp-footer">
  <div class="mp-footer-shell">
    <div class="mp-footer-grid">
      <div class="mp-footer-brand">
        <a href="<?= $footerBasePath ?>index.php" class="mp-footer-logo">Market Plier</a>
        <p class="mp-footer-tagline"><?= htmlspecialchars(t('footer_tagline'), ENT_QUOTES, 'UTF-8') ?></p>
      </div>

      <div class="mp-footer-column">
        <h2><?= htmlspecialchars(t('footer_buy'), ENT_QUOTES, 'UTF-8') ?></h2>
        <a href="<?= $footerBasePath ?>shop/search.php"><?= htmlspecialchars(t('footer_browse'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= $footerBasePath ?>index.php#home-categories"><?= htmlspecialchars(t('footer_categories'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= $footerBasePath ?>index.php#home-trending"><?= htmlspecialchars(t('footer_trending'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= $footerBasePath ?>index.php#home-recent"><?= htmlspecialchars(t('footer_recent'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>

      <div class="mp-footer-column">
        <h2><?= htmlspecialchars(t('footer_sell'), ENT_QUOTES, 'UTF-8') ?></h2>
        <a href="<?= $footerBasePath ?>shop/sell.php"><?= htmlspecialchars(t('footer_post_listing'), ENT_QUOTES, 'UTF-8') ?></a>
        <a href="<?= $footerBasePath . $accountHomeHref ?>"><?= htmlspecialchars(t('footer_my_listings'), ENT_QUOTES, 'UTF-8') ?></a>
      </div>

      <div class="mp-footer-column">
        <h2><?= htmlspecialchars(t('footer_account'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php foreach ($accountLinks as $link): ?>
          <a href="<?= $footerBasePath . $link['href'] ?>"><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
      </div>

      <div class="mp-footer-column">
        <h2><?= htmlspecialchars(t('footer_preferences'), ENT_QUOTES, 'UTF-8') ?></h2>
        <?php if ($isLoggedIn): ?>
          <a href="<?= $footerBasePath ?>settings/settings.php"><?= htmlspecialchars(t('header_settings'), ENT_QUOTES, 'UTF-8') ?></a>
        <?php endif; ?>
        <form class="mp-footer-language-form" method="POST" action="<?= $footerBasePath ?>includes/set_language.php">
          <input type="hidden" name="redirect_to" value="<?= htmlspecialchars($currentUri, ENT_QUOTES, 'UTF-8') ?>">
          <label class="mp-footer-language-label" for="footerLanguage">
            <?= htmlspecialchars(t('footer_language_label'), ENT_QUOTES, 'UTF-8') ?>
          </label>
          <div class="mp-footer-language-field">
            <i class="fa-solid fa-language"></i>
            <select id="footerLanguage" name="language" class="mp-footer-language-select">
              <option value="fr" <?= $footerLang === 'fr' ? 'selected' : '' ?>>Francais</option>
              <option value="en" <?= $footerLang === 'en' ? 'selected' : '' ?>>English</option>
              <option value="es" <?= $footerLang === 'es' ? 'selected' : '' ?>>Espanol</option>
              <option value="de" <?= $footerLang === 'de' ? 'selected' : '' ?>>Deutsch</option>
            </select>
          </div>
        </form>
      </div>
    </div>

    <div class="mp-footer-bottom">
      <p class="mp-footer-copy">
        &copy; <?= date('Y') ?> Market Plier. <?= htmlspecialchars(t('footer_rights'), ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
  </div>

  <button type="button" class="mp-back-to-top" id="mpBackToTop" aria-label="<?= htmlspecialchars(t('footer_back_to_top'), ENT_QUOTES, 'UTF-8') ?>">
    <i class="fa-solid fa-arrow-up"></i>
  </button>
</footer>

<script>
  (function() {
    var button = document.getElementById('mpBackToTop');
    var languageSelect = document.getElementById('footerLanguage');

    if (languageSelect) {
      languageSelect.addEventListener('change', function() {
        languageSelect.form.submit();
      });
    }

    if (!button) return;

    function toggleButton() {
      if (window.scrollY > 320) {
        button.classList.add('is-visible');
      } else {
        button.classList.remove('is-visible');
      }
    }

    button.addEventListener('click', function() {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });

    toggleButton();
    window.addEventListener('scroll', toggleButton, {
      passive: true
    });
  })();
</script>
