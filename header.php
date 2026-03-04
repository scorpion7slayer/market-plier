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
    <form class="search-form" action="<?= $headerBasePath ?>shop/search.php" method="GET" autocomplete="off">
      <input class="search-bar" type="text" name="q" placeholder="Rechercher"
        value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        id="searchInput" />
      <div class="autocomplete-dropdown" id="autocompleteDropdown"></div>
    </form>
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

<?php if (isset($_SESSION['auth_token'])): ?>
  <!-- Modal compte supprimé -->
  <div class="deleted-account-overlay" id="deletedAccountOverlay">
    <div class="deleted-account-modal">
      <div class="deleted-account-icon">
        <i class="fa-solid fa-user-slash"></i>
      </div>
      <h3 class="deleted-account-title">Compte supprimé</h3>
      <p class="deleted-account-text">
        Votre compte a été supprimé par un administrateur.<br>
        Vous allez être déconnecté automatiquement.
      </p>
      <button class="deleted-account-btn" onclick="window.location.href='<?= $headerBasePath ?>inscription-connexion/logout.php'">
        <i class="fa-solid fa-right-from-bracket"></i> Fermer
      </button>
      <div class="deleted-account-countdown" id="deletedCountdown"></div>
    </div>
  </div>
  <script>
    (function() {
      var basePath = <?= json_encode($headerBasePath) ?>;
      var checkUrl = basePath + 'api/check_session.php';
      var logoutUrl = basePath + 'inscription-connexion/logout.php';
      var intervalId = null;
      var shown = false;

      function showDeletedModal() {
        if (shown) return;
        shown = true;
        if (intervalId) clearInterval(intervalId);

        var overlay = document.getElementById('deletedAccountOverlay');
        var countdown = document.getElementById('deletedCountdown');
        if (!overlay) return;

        overlay.classList.add('visible');

        var seconds = 8;
        countdown.textContent = 'Redirection dans ' + seconds + 's...';
        var timer = setInterval(function() {
          seconds--;
          if (seconds <= 0) {
            clearInterval(timer);
            window.location.href = logoutUrl;
          } else {
            countdown.textContent = 'Redirection dans ' + seconds + 's...';
          }
        }, 1000);
      }

      function checkSession() {
        fetch(checkUrl, {
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            if (!data.valid) showDeletedModal();
          })
          .catch(function() {});
      }

      // Vérifier toutes les 10 secondes
      intervalId = setInterval(checkSession, 10000);
      // Vérifier aussi au retour sur l'onglet
      document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !shown) checkSession();
      });
    })();
  </script>
<?php endif; ?>

<!-- ═══ COOKIE CONSENT BANNER ═══════════════════════════════ -->
<div class="cookie-banner" id="cookieBanner">
  <div class="cookie-banner-inner">
    <div class="cookie-banner-text">
      <strong><i class="fa-solid fa-cookie-bite"></i> Nous utilisons des cookies</strong><br>
      Ce site utilise des cookies pour améliorer votre expérience, analyser le trafic et personnaliser le contenu.
      Vous pouvez accepter tous les cookies, les refuser ou personnaliser vos préférences.
    </div>
    <div class="cookie-banner-actions">
      <button class="cookie-btn cookie-btn-accept" id="cookieAcceptAll">Tout accepter</button>
      <button class="cookie-btn cookie-btn-refuse" id="cookieRefuseAll">Tout refuser</button>
      <button class="cookie-btn cookie-btn-settings" id="cookieSettings">Personnaliser</button>
    </div>
  </div>
</div>

<!-- Modal personnalisation cookies -->
<div class="cookie-modal-overlay" id="cookieModal">
  <div class="cookie-modal">
    <h3>Paramètres des cookies</h3>
    <p>Choisissez les types de cookies que vous souhaitez autoriser. Les cookies nécessaires sont toujours actifs car ils sont indispensables au fonctionnement du site.</p>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name">Nécessaires</div>
        <div class="cookie-category-desc">Essentiels au fonctionnement du site (session, sécurité).</div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieNecessary" checked disabled>
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name">Analytiques</div>
        <div class="cookie-category-desc">Nous aident à comprendre comment les visiteurs utilisent le site.</div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieAnalytics">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name">Marketing</div>
        <div class="cookie-category-desc">Utilisés pour afficher des publicités pertinentes.</div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieMarketing">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name">Préférences</div>
        <div class="cookie-category-desc">Mémorisent vos choix (thème, langue) pour une meilleure expérience.</div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookiePreferences">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-modal-actions">
      <button class="cookie-btn cookie-btn-refuse" id="cookieModalClose">Annuler</button>
      <button class="cookie-btn cookie-btn-accept" id="cookieModalSave">Enregistrer mes choix</button>
      <button class="cookie-btn cookie-btn-accept" id="cookieModalAccept">Tout accepter</button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= $headerBasePath ?>styles/cookie-banner.css" />
<script src="<?= $headerBasePath ?>styles/cookie-banner.js"></script>

<!-- ═══ AUTOCOMPLETE ════════════════════════════════════════ -->
<script>
  (function() {
    var input = document.getElementById('searchInput');
    var dropdown = document.getElementById('autocompleteDropdown');
    if (!input || !dropdown) return;

    var basePath = <?= json_encode($headerBasePath) ?>;
    var debounceTimer = null;
    var activeIndex = -1;
    var currentItems = [];

    var categoryIcons = {
      vetements: 'fa-shirt',
      electronique: 'fa-laptop',
      livres: 'fa-book',
      maison: 'fa-house',
      sport: 'fa-futbol',
      vehicules: 'fa-car',
      autre: 'fa-ellipsis'
    };

    function escapeHtml(t) {
      var d = document.createElement('div');
      d.textContent = t;
      return d.innerHTML;
    }

    function hide() {
      dropdown.classList.remove('visible');
      activeIndex = -1;
    }

    function show() {
      if (dropdown.children.length > 0) dropdown.classList.add('visible');
    }

    function setActive(idx) {
      var items = dropdown.querySelectorAll('.ac-item');
      items.forEach(function(el) {
        el.classList.remove('ac-active');
      });
      if (idx >= 0 && idx < items.length) {
        items[idx].classList.add('ac-active');
        items[idx].scrollIntoView({
          block: 'nearest'
        });
      }
      activeIndex = idx;
    }

    function createCategoryLink(cat) {
      var a = document.createElement('a');
      a.href = basePath + 'shop/search.php?category=' + cat.key;
      a.className = 'ac-item ac-category';
      a.innerHTML =
        '<i class="fa-solid ' + (categoryIcons[cat.key] || 'fa-tag') + ' ac-icon"></i>' +
        '<div class="ac-text">' +
        '<span class="ac-label">' + escapeHtml(cat.label) + '</span>' +
        '<span class="ac-count">' + cat.count + ' annonce' + (cat.count > 1 ? 's' : '') + '</span>' +
        '</div>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      return a;
    }

    function createListingLink(item) {
      var a = document.createElement('a');
      a.href = basePath + 'shop/buy.php?id=' + item.id;
      a.className = 'ac-item ac-listing';

      var imgHtml = '';
      if (item.image) {
        imgHtml = '<img class="ac-thumb" src="' + basePath + 'uploads/listings/' + escapeHtml(item.image) + '" alt="">';
      } else {
        imgHtml = '<div class="ac-thumb ac-thumb-empty"><i class="fa-solid fa-image"></i></div>';
      }

      var price = parseFloat(item.price).toLocaleString('fr-FR', {
        minimumFractionDigits: 2
      }) + ' €';
      a.innerHTML = imgHtml +
        '<div class="ac-text">' +
        '<span class="ac-label">' + escapeHtml(item.title) + '</span>' +
        '<span class="ac-price">' + price + '</span>' +
        '</div>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      return a;
    }

    function createSearchAllLink(q) {
      var allLink = document.createElement('a');
      allLink.href = basePath + 'shop/search.php?q=' + encodeURIComponent(q);
      allLink.className = 'ac-item ac-all';
      allLink.innerHTML =
        '<i class="fa-solid fa-magnifying-glass ac-icon"></i>' +
        '<span class="ac-label">Rechercher « ' + escapeHtml(q) + ' »</span>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      return allLink;
    }

    function appendSeparator() {
      var sep = document.createElement('div');
      sep.className = 'ac-separator';
      dropdown.appendChild(sep);
    }

    function doSearch(q) {
      if (q.length < 2) {
        hide();
        dropdown.innerHTML = '';
        return;
      }

      fetch(basePath + 'api/autocomplete.php?q=' + encodeURIComponent(q))
        .then(function(r) {
          return r.json();
        })
        .then(function(data) {
          dropdown.innerHTML = '';
          currentItems = [];
          activeIndex = -1;

          if (data.categories && data.categories.length > 0) {
            data.categories.forEach(function(cat) {
              var a = createCategoryLink(cat);
              dropdown.appendChild(a);
              currentItems.push(a);
            });
          }

          if (data.suggestions && data.suggestions.length > 0) {
            if (data.categories && data.categories.length > 0) appendSeparator();

            data.suggestions.forEach(function(item) {
              var a = createListingLink(item);
              dropdown.appendChild(a);
              currentItems.push(a);
            });
          }

          if (data.suggestions.length > 0 || data.categories.length > 0) {
            appendSeparator();
            var allLink = createSearchAllLink(q);
            dropdown.appendChild(allLink);
            currentItems.push(allLink);
          }

          if (currentItems.length > 0) show();
          else hide();
        })
        .catch(function() {
          hide();
        });
    }

    input.addEventListener('input', function() {
      clearTimeout(debounceTimer);
      var q = input.value.trim();
      debounceTimer = setTimeout(function() {
        doSearch(q);
      }, 250);
    });

    input.addEventListener('focus', function() {
      if (input.value.trim().length >= 2 && dropdown.children.length > 0) show();
    });

    input.addEventListener('keydown', function(e) {
      if (!dropdown.classList.contains('visible')) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        setActive(Math.min(activeIndex + 1, currentItems.length - 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        setActive(Math.max(activeIndex - 1, -1));
      } else if (e.key === 'Enter' && activeIndex >= 0) {
        e.preventDefault();
        currentItems[activeIndex].click();
      } else if (e.key === 'Escape') {
        hide();
      }
    });

    document.addEventListener('click', function(e) {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) hide();
    });
  })();
</script>