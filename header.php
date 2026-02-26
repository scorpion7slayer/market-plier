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
        <img src="<?= $headerBasePath ?>assets/images/logo.svg" alt="Logo Market Plier" style="width: auto; height: 100%; margin-left: 250%;">
      </a>
    </div>
    <div class="header-divider"></div>
    <input class="search-bar" type="text" placeholder="Rechercher" />
    <div class="header-divider"></div>
    <?php if ($profilePhotoExists): ?>
      <a class="profile-photo-container" href="<?= $headerBasePath ?>inscription-connexion/account.php">
        <img src="<?= $headerBasePath ?>uploads/profiles/<?= htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8') ?>"
          alt="Photo de profil"
          class="profile-photo"
          style="object-fit: cover" />
      </a>
    <?php else: ?>
      <a class="profile-photo-container" href="<?= $headerBasePath ?><?= isset($_SESSION['auth_token']) ? 'inscription-connexion/account.php' : 'inscription-connexion/register.php' ?>"></a>
    <?php endif; ?>
  </div>

  <div class="header-bottom">
    <nav>
      <a href="#">vendre</a>
      <a href="#">langue</a>
      <a href="#">thème</a>
      <a href="#">aide</a>
    </nav>
  </div>
</header>