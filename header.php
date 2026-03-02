<?php

$headerBasePath = $headerBasePath ?? '';
$headerUser = $headerUser ?? null;

$profilePhoto = $headerUser['profile_photo'] ?? null;
$profilePhotoExists = $profilePhoto && file_exists(__DIR__ . '/uploads/profiles/' . $profilePhoto);
?>
<header>
  <div class="header-top">
    <div class="logo-area">
      <a href="<?= $headerBasePath ?>index.php" class="logo-icon">
        <img src="<?= $headerBasePath ?>assets/images/logo.svg" alt="Logo Market Plier" class="logo-img">
      </a>
    </div>
    <div class="header-divider"></div>
    <input class="search-bar" type="text" placeholder="Rechercher" />
    <div class="header-divider"></div>
    <a class="profile-photo-container" href="<?= $headerBasePath ?><?= isset($_SESSION['auth_token']) ? 'inscription-connexion/account.php' : 'inscription-connexion/register.php' ?>">
      <img src="<?= $profilePhotoExists ? $headerBasePath . 'uploads/profiles/' . htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') : $headerBasePath . 'assets/images/default-avatar.svg' ?>"
        alt="Photo de profil"
        class="profile-photo" />
    </a>

    <!-- Theme toggle desktop -->
    <button class="theme-toggle" data-theme-toggle title="Changer le thème">
      <i class="fa-solid fa-moon"></i>
      <i class="fa-solid fa-sun"></i>
    </button>

    <!-- Hamburger mobile -->
    <button class="hamburger-btn" aria-label="Menu">
      <i class="fa-solid fa-bars"></i>
    </button>
  </div>

  <div class="header-bottom">
    <nav>
      <a href="<?= $headerBasePath ?>shop/sell.php">vendre</a>
      <a href="#">langue</a>
      <a href="<?= $headerBasePath ?>settings/settings.php">paramètres</a>
      <a href="#">aide</a>
    </nav>
  </div>
</header>

<!-- Menu mobile (slide-in) -->
<div class="mobile-menu-overlay"></div>
<div class="mobile-menu">
  <div class="mobile-menu-header">
    <span>Menu</span>
    <button class="mobile-menu-close" aria-label="Fermer">&times;</button>
  </div>
  <nav>
    <a href="<?= $headerBasePath ?>shop/sell.php"><i class="fa-solid fa-tag"></i>&nbsp; Vendre</a>
    <a href="#"><i class="fa-solid fa-language"></i>&nbsp; Langue</a>
    <a href="<?= $headerBasePath ?>settings/settings.php"><i class="fa-solid fa-gear"></i>&nbsp; Paramètres</a>
    <a href="#"><i class="fa-solid fa-circle-question"></i>&nbsp; Aide</a>
    <?php if (isset($_SESSION['auth_token'])): ?>
      <a href="<?= $headerBasePath ?>inscription-connexion/account.php"><i class="fa-solid fa-user"></i>&nbsp; Mon profil</a>
    <?php else: ?>
      <a href="<?= $headerBasePath ?>inscription-connexion/register.php"><i class="fa-solid fa-user-plus"></i>&nbsp; S'inscrire</a>
    <?php endif; ?>
  </nav>
  <div class="theme-toggle-mobile">
    <button data-theme-toggle>
      <i class="fa-solid fa-circle-half-stroke"></i>
      <span class="theme-toggle-label">Mode sombre</span>
    </button>
  </div>
</div>
