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
      <a href="<?= $headerBasePath ?>shop/sell.php">vendre</a>
      <a href="#">langue</a>
      <a href="#">aide</a>
      <button id="theme-toggle" class="theme-toggle" aria-label="Changer de thème">
        <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="5"></circle>
          <line x1="12" y1="1" x2="12" y2="3"></line>
          <line x1="12" y1="21" x2="12" y2="23"></line>
          <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
          <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
          <line x1="1" y1="12" x2="3" y2="12"></line>
          <line x1="21" y1="12" x2="23" y2="12"></line>
          <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
          <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
        </svg>
        <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
        </svg>
      </button>
    </nav>
  </div>
</header>

<script>
(function() {
  const toggle = document.getElementById('theme-toggle');
  const html = document.documentElement;
  
  // Check localStorage or system preference
  const savedTheme = localStorage.getItem('theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const theme = savedTheme || (prefersDark ? 'dark' : 'light');
  
  // Apply theme on load
  if (theme === 'dark') {
    html.classList.add('dark-mode');
  }
  
  // Toggle handler
  toggle.addEventListener('click', () => {
    html.classList.toggle('dark-mode');
    const newTheme = html.classList.contains('dark-mode') ? 'dark' : 'light';
    localStorage.setItem('theme', newTheme);
  });
})();
</script>
