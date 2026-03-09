<?php

$headerBasePath = $headerBasePath ?? '';
$headerUser = $headerUser ?? null;

require_once __DIR__ . '/includes/lang.php';


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
      <input class="search-bar" type="text" name="q" placeholder="<?= htmlspecialchars(t('header_search'), ENT_QUOTES, 'UTF-8') ?>"
        value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
        id="searchInput" aria-label="Rechercher" />
      <div class="autocomplete-dropdown" id="autocompleteDropdown"></div>
    </form>
    <div class="header-divider"></div>
    <?php if (isset($_SESSION['auth_token'])): ?>
      <!-- Icônes header : favoris, messagerie, notifications -->
      <div class="header-icons">
        <a href="<?= $headerBasePath ?>favoris/" class="header-icon-link" title="<?= htmlspecialchars(t('header_favorites'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-heart"></i>
        </a>
        <a href="<?= $headerBasePath ?>messagerie/inbox.php" class="header-icon-link" title="<?= htmlspecialchars(t('header_messages'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-envelope"></i>
          <span class="header-badge header-badge-msg" id="badgeMsg" style="display:none;"></span>
        </a>
        <a href="<?= $headerBasePath ?>notifications/" class="header-icon-link" title="<?= htmlspecialchars(t('header_notifications'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-bell"></i>
          <span class="header-badge header-badge-notif" id="badgeNotif" style="display:none;"></span>
        </a>
      </div>
    <?php endif; ?>

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
      <?php if (isset($_SESSION['auth_token'])): ?>
        <span class="header-badge" id="badgeHamburger" style="display:none;"></span>
      <?php endif; ?>
    </button>
  </div>

  <div class="header-bottom">
    <nav>
      <a href="<?= $headerBasePath ?>shop/sell.php"><?= htmlspecialchars(t('header_sell'), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= $headerBasePath ?>settings/settings.php"><?= htmlspecialchars(t('header_settings'), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= $headerBasePath ?>support/help.php"><?= htmlspecialchars(t('header_help'), ENT_QUOTES, 'UTF-8') ?></a>
    </nav>
  </div>
</header>

<!-- Menu mobile (slide-in) -->
<div class="mobile-menu-overlay"></div>
<div class="mobile-menu">
  <div class="mobile-menu-header">
    <span><?= htmlspecialchars(t('header_menu'), ENT_QUOTES, 'UTF-8') ?></span>
    <button class="mobile-menu-close" aria-label="Fermer">&times;</button>
  </div>
  <nav>
    <a href="<?= $headerBasePath ?>shop/sell.php"><i class="fa-solid fa-tag"></i>&nbsp; <?= htmlspecialchars(t('header_sell'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php if (isset($_SESSION['auth_token'])): ?>
      <a href="<?= $headerBasePath ?>messagerie/inbox.php"><i class="fa-solid fa-envelope"></i>&nbsp; <?= htmlspecialchars(t('header_messages'), ENT_QUOTES, 'UTF-8') ?><span class="mobile-menu-badge" id="badgeMsgMobile" style="display:none;"></span></a>
      <a href="<?= $headerBasePath ?>favoris/"><i class="fa-solid fa-heart"></i>&nbsp; <?= htmlspecialchars(t('header_favorites'), ENT_QUOTES, 'UTF-8') ?></a>
      <a href="<?= $headerBasePath ?>notifications/"><i class="fa-solid fa-bell"></i>&nbsp; <?= htmlspecialchars(t('header_notifications'), ENT_QUOTES, 'UTF-8') ?><span class="mobile-menu-badge" id="badgeNotifMobile" style="display:none;"></span></a>
    <?php endif; ?>
    <a href="<?= $headerBasePath ?>settings/settings.php"><i class="fa-solid fa-gear"></i>&nbsp; <?= htmlspecialchars(t('header_settings'), ENT_QUOTES, 'UTF-8') ?></a>
    <a href="<?= $headerBasePath ?>support/help.php"><i class="fa-solid fa-circle-question"></i>&nbsp; <?= htmlspecialchars(t('header_help'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php if (isset($_SESSION['auth_token'])): ?>
      <a href="<?= $headerBasePath ?>inscription-connexion/account.php"><i class="fa-solid fa-user"></i>&nbsp; <?= htmlspecialchars(t('header_my_profile'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php else: ?>
      <a href="<?= $headerBasePath ?>inscription-connexion/register.php"><i class="fa-solid fa-user-plus"></i>&nbsp; <?= htmlspecialchars(t('header_register'), ENT_QUOTES, 'UTF-8') ?></a>
    <?php endif; ?>
  </nav>
  <div class="theme-toggle-mobile">
    <button data-theme-toggle>
      <i class="fa-solid fa-circle-half-stroke"></i>
      <span class="theme-toggle-label"><?= htmlspecialchars(t('header_dark_mode'), ENT_QUOTES, 'UTF-8') ?></span>
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
      <h3 class="deleted-account-title"><?= htmlspecialchars(t('header_account_deleted'), ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="deleted-account-text">
        <?= htmlspecialchars(t('header_account_deleted_text'), ENT_QUOTES, 'UTF-8') ?>
      </p>
      <button class="deleted-account-btn" onclick="window.location.href='<?= $headerBasePath ?>inscription-connexion/logout.php'">
        <i class="fa-solid fa-right-from-bracket"></i> <?= htmlspecialchars(t('header_close'), ENT_QUOTES, 'UTF-8') ?>
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
      <strong><i class="fa-solid fa-cookie-bite"></i> <?= htmlspecialchars(t('cookie_title'), ENT_QUOTES, 'UTF-8') ?></strong><br>
      <?= htmlspecialchars(t('cookie_text'), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="cookie-banner-actions">
      <button class="cookie-btn cookie-btn-accept" id="cookieAcceptAll"><?= htmlspecialchars(t('cookie_accept'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="cookie-btn cookie-btn-refuse" id="cookieRefuseAll"><?= htmlspecialchars(t('cookie_refuse'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="cookie-btn cookie-btn-settings" id="cookieSettings"><?= htmlspecialchars(t('cookie_customize'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</div>

<!-- Modal personnalisation cookies -->
<div class="cookie-modal-overlay" id="cookieModal">
  <div class="cookie-modal">
    <h3><?= htmlspecialchars(t('cookie_settings_title'), ENT_QUOTES, 'UTF-8') ?></h3>
    <p><?= htmlspecialchars(t('cookie_settings_text'), ENT_QUOTES, 'UTF-8') ?></p>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name"><?= htmlspecialchars(t('cookie_necessary'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cookie-category-desc"><?= htmlspecialchars(t('cookie_necessary_desc'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieNecessary" checked disabled>
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name"><?= htmlspecialchars(t('cookie_analytics'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cookie-category-desc"><?= htmlspecialchars(t('cookie_analytics_desc'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieAnalytics">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name"><?= htmlspecialchars(t('cookie_marketing'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cookie-category-desc"><?= htmlspecialchars(t('cookie_marketing_desc'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookieMarketing">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-category">
      <div class="cookie-category-info">
        <div class="cookie-category-name"><?= htmlspecialchars(t('cookie_preferences'), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="cookie-category-desc"><?= htmlspecialchars(t('cookie_preferences_desc'), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <label class="cookie-toggle">
        <input type="checkbox" id="cookiePreferences">
        <span class="cookie-toggle-slider"></span>
      </label>
    </div>

    <div class="cookie-modal-actions">
      <button class="cookie-btn cookie-btn-refuse" id="cookieModalClose"><?= htmlspecialchars(t('cancel'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="cookie-btn cookie-btn-accept" id="cookieModalSave"><?= htmlspecialchars(t('cookie_save_choices'), ENT_QUOTES, 'UTF-8') ?></button>
      <button class="cookie-btn cookie-btn-accept" id="cookieModalAccept"><?= htmlspecialchars(t('cookie_accept'), ENT_QUOTES, 'UTF-8') ?></button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="<?= $headerBasePath ?>styles/cookie-banner.css" />
<script src="<?= $headerBasePath ?>styles/cookie-banner.js"></script>

<?php if (isset($_SESSION['auth_token'])): ?>
  <!-- ═══ BADGES TEMPS RÉEL ══════════════════════════════════ -->
  <script>
    (function() {
      var basePath = <?= json_encode($headerBasePath) ?>;
      var _notifCount = 0,
        _msgCount = 0;

      function updateHamburger() {
        var total = _notifCount + _msgCount;
        var badge = document.getElementById('badgeHamburger');
        if (!badge) return;
        if (total > 0) {
          badge.textContent = total > 99 ? '99+' : total;
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
      }

      function updateBadges() {
        // Notifications count
        fetch(basePath + 'api/notifications.php?action=count', {
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            _notifCount = data.unread_count || 0;
            var count = _notifCount > 0 ? (_notifCount > 99 ? '99+' : _notifCount) : null;
            ['badgeNotif', 'badgeNotifMobile'].forEach(function(id) {
              var badge = document.getElementById(id);
              if (!badge) return;
              if (count) {
                badge.textContent = count;
                badge.style.display = '';
              } else {
                badge.style.display = 'none';
              }
            });
            updateHamburger();
          })
          .catch(function() {});

        // Unread messages count
        fetch(basePath + 'api/unread_messages.php', {
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            _msgCount = data.unread_count || 0;
            var count = _msgCount > 0 ? (_msgCount > 99 ? '99+' : _msgCount) : null;
            ['badgeMsg', 'badgeMsgMobile'].forEach(function(id) {
              var badge = document.getElementById(id);
              if (!badge) return;
              if (count) {
                badge.textContent = count;
                badge.style.display = '';
              } else {
                badge.style.display = 'none';
              }
            });
            updateHamburger();
          })
          .catch(function() {});
      }
      updateBadges();
      setInterval(updateBadges, 15000);
    })();
  </script>
<?php endif; ?>

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
    var activeController = null;
    var requestSequence = 0;

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

    function resetDropdown() {
      dropdown.innerHTML = '';
      currentItems = [];
      hide();
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

    function createCategoryLink(cat, q) {
      var a = document.createElement('a');
      var params = new URLSearchParams();
      params.set('category', cat.key);
      if (cat.preserve_query && q) {
        params.set('q', q);
      }
      a.href = basePath + 'shop/search.php?' + params.toString();
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
      if (item.image_id) {
        imgHtml = '<img class="ac-thumb" src="' + basePath + 'api/image.php?id=' + item.image_id + '" alt="">';
      } else if (item.image_path) {
        imgHtml = '<img class="ac-thumb" src="' + basePath + 'uploads/listings/' + escapeHtml(item.image_path) + '" alt="">';
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
        if (activeController) {
          activeController.abort();
          activeController = null;
        }
        resetDropdown();
        return;
      }

      requestSequence += 1;
      var requestId = requestSequence;

      if (activeController) {
        activeController.abort();
      }

      activeController = typeof AbortController !== 'undefined' ? new AbortController() : null;

      var fetchOptions = {};
      if (activeController) {
        fetchOptions.signal = activeController.signal;
      }

      fetch(basePath + 'api/autocomplete.php?q=' + encodeURIComponent(q), fetchOptions)
        .then(function(r) {
          if (!r.ok) {
            throw new Error('Autocomplete request failed');
          }
          return r.json();
        })
        .then(function(data) {
          if (requestId !== requestSequence || input.value.trim() !== q) {
            return;
          }

          var categories = Array.isArray(data.categories) ? data.categories : [];
          var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];

          resetDropdown();

          if (categories.length > 0) {
            categories.forEach(function(cat) {
              var a = createCategoryLink(cat, q);
              dropdown.appendChild(a);
              currentItems.push(a);
            });
          }

          if (suggestions.length > 0) {
            if (categories.length > 0) appendSeparator();

            suggestions.forEach(function(item) {
              var a = createListingLink(item);
              dropdown.appendChild(a);
              currentItems.push(a);
            });
          }

          if (currentItems.length > 0) {
            appendSeparator();
          }

          var allLink = createSearchAllLink(q);
          dropdown.appendChild(allLink);
          currentItems.push(allLink);

          if (currentItems.length > 0) show();
          else hide();
        })
        .catch(function(error) {
          if (error && error.name === 'AbortError') {
            return;
          }

          if (requestId === requestSequence) {
            resetDropdown();
          }
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

<?php include __DIR__ . '/includes/toast.php'; ?>