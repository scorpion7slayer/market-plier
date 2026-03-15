<?php

$headerBasePath = $headerBasePath ?? '';
$headerUser = $headerUser ?? null;

require_once __DIR__ . '/includes/lang.php';
require_once __DIR__ . '/includes/cart.php';

$profilePhoto = $headerUser['profile_photo'] ?? null;
$profilePhotoExists = !empty($profilePhoto);
$headerAuthToken = $_SESSION['auth_token'] ?? '';
$profilePhotoUrl = ($profilePhotoExists && $headerAuthToken)
  ? $headerBasePath . 'api/profile_photo.php?token=' . urlencode($headerAuthToken) . '&v=' . crc32($profilePhoto)
  : $headerBasePath . 'assets/images/default-avatar.svg';
$headerBrowserNotificationsEnabled = false;
$headerCartCount = cart_count();

if (isset($_SESSION['auth_token']) && isset($GLOBALS['pdo'])) {
  try {
    $stmt = $GLOBALS['pdo']->prepare("SELECT notif_email FROM user_settings WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $settingsRow = $stmt->fetch();
    $headerBrowserNotificationsEnabled = !empty($settingsRow['notif_email']);
  } catch (\PDOException $e) {
    $headerBrowserNotificationsEnabled = false;
  }
}
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
      <div class="header-icons">
        <a href="<?= $headerBasePath ?>shop/sell.php" class="header-icon-link" title="<?= htmlspecialchars(t('header_sell'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-plus"></i>
        </a>
        <a href="<?= $headerBasePath ?>panier/" class="header-icon-link" title="<?= htmlspecialchars(t('header_cart'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-basket-shopping"></i>
          <span class="header-badge header-badge-cart" id="badgeCart" style="<?= $headerCartCount > 0 ? '' : 'display:none;' ?>"><?= $headerCartCount > 99 ? '99+' : $headerCartCount ?></span>
        </a>
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
    <?php else: ?>
      <div class="header-icons">
        <a href="<?= $headerBasePath ?>panier/" class="header-icon-link" title="<?= htmlspecialchars(t('header_cart'), ENT_QUOTES, 'UTF-8') ?>">
          <i class="fa-solid fa-basket-shopping"></i>
          <span class="header-badge header-badge-cart" id="badgeCart" style="<?= $headerCartCount > 0 ? '' : 'display:none;' ?>"><?= $headerCartCount > 99 ? '99+' : $headerCartCount ?></span>
        </a>
      </div>
    <?php endif; ?>

    <a class="profile-photo-container" href="<?= $headerBasePath ?><?= isset($_SESSION['auth_token']) ? 'inscription-connexion/account.php' : 'inscription-connexion/register.php' ?>">
      <img src="<?= htmlspecialchars($profilePhotoUrl, ENT_QUOTES, 'UTF-8') ?>"
        alt="Photo de profil"
        class="profile-photo" />
    </a>

    <button class="theme-toggle" data-theme-toggle title="Changer le thème">
      <i class="fa-solid fa-moon"></i>
      <i class="fa-solid fa-sun"></i>
    </button>
  </div>
</header>

<!-- Panneau recherche mobile -->
<div class="mobile-search-panel" id="mobileSearchPanel">
  <form action="<?= $headerBasePath ?>shop/search.php" method="GET" autocomplete="off" class="mobile-search-form">
    <input type="text" name="q" class="mobile-search-input" id="mobileSearchInput"
      placeholder="<?= htmlspecialchars(t('header_search'), ENT_QUOTES, 'UTF-8') ?>"
      value="<?= htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>" />
    <button type="button" class="mobile-search-close" id="mobileSearchClose">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </form>
  <div class="mobile-search-results" id="mobileSearchResults"></div>
</div>

<!-- BOTTOM NAV MOBILE -->
<nav class="bottom-nav">
  <a href="<?= $headerBasePath ?>index.php" class="bottom-nav-item">
    <i class="fa-solid fa-house"></i>
    <span><?= htmlspecialchars(t('header_home'), ENT_QUOTES, 'UTF-8') ?></span>
  </a>
  <button type="button" class="bottom-nav-item" id="bottomNavSearch">
    <i class="fa-solid fa-magnifying-glass"></i>
    <span><?= htmlspecialchars(t('header_search'), ENT_QUOTES, 'UTF-8') ?></span>
  </button>
  <a href="<?= $headerBasePath ?>shop/sell.php" class="bottom-nav-item bottom-nav-sell">
    <i class="fa-solid fa-plus"></i>
  </a>
  <?php if (isset($_SESSION['auth_token'])): ?>
    <a href="<?= $headerBasePath ?>messagerie/inbox.php" class="bottom-nav-item">
      <i class="fa-solid fa-envelope"></i>
      <span><?= htmlspecialchars(t('header_messages'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="bottom-nav-badge" id="badgeMsgBottom" style="display:none;"></span>
    </a>
    <a href="<?= $headerBasePath ?>inscription-connexion/account.php" class="bottom-nav-item">
      <i class="fa-solid fa-user"></i>
      <span><?= htmlspecialchars(t('header_profile'), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
  <?php else: ?>
    <a href="<?= $headerBasePath ?>panier/" class="bottom-nav-item">
      <i class="fa-solid fa-basket-shopping"></i>
      <span><?= htmlspecialchars(t('header_cart'), ENT_QUOTES, 'UTF-8') ?></span>
      <span class="bottom-nav-badge" id="badgeCartBottom" style="<?= $headerCartCount > 0 ? '' : 'display:none;' ?>"><?= $headerCartCount > 0 ? ($headerCartCount > 99 ? '99+' : $headerCartCount) : '' ?></span>
    </a>
    <a href="<?= $headerBasePath ?>inscription-connexion/register.php" class="bottom-nav-item">
      <i class="fa-solid fa-user-plus"></i>
      <span><?= htmlspecialchars(t('header_signup'), ENT_QUOTES, 'UTF-8') ?></span>
    </a>
  <?php endif; ?>
</nav>

<script>
  (function() {
    var basePath = <?= json_encode($headerBasePath) ?>;

    function renderCartCount(count) {
      var safeCount = Math.max(0, parseInt(count, 10) || 0);
      var label = safeCount > 99 ? '99+' : String(safeCount);

      ['badgeCart', 'badgeCartBottom'].forEach(function(id) {
        var badge = document.getElementById(id);
        if (!badge) return;
        if (safeCount > 0) {
          badge.textContent = label;
          badge.style.display = '';
        } else {
          badge.style.display = 'none';
        }
      });

      window.mpCartCount = safeCount;
    }

    function refreshCartCount() {
      fetch(basePath + 'api/cart_count.php', {
          credentials: 'same-origin'
        })
        .then(function(r) {
          return r.json();
        })
        .then(function(data) {
          renderCartCount(data.count || 0);
        })
        .catch(function() {});
    }

    window.mpUpdateCartBadges = renderCartCount;
    window.mpRefreshCartBadge = refreshCartCount;

    renderCartCount(<?= (int) $headerCartCount ?>);
    setInterval(refreshCartCount, 15000);

    document.addEventListener('visibilitychange', function() {
      if (!document.hidden) refreshCartCount();
    });
  })();
</script>

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

<!-- COOKIE CONSENT BANNER -->
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
  <!-- BADGES TEMPS RÉEL -->
  <script>
    (function() {
      var basePath = <?= json_encode($headerBasePath) ?>;
      var browserNotificationsEnabled = <?= json_encode($headerBrowserNotificationsEnabled) ?>;
      var notificationStorageKey = <?= json_encode('mp-last-notification-id-' . ($_SESSION['auth_token'] ?? 'guest')) ?>;
      var vapidPublicKey = <?= json_encode($_ENV['VAPID_PUBLIC_KEY'] ?? '') ?>;
      var csrfTokenHeader = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

      // Enregistrer le service worker + souscrire aux push
      if ('serviceWorker' in navigator && browserNotificationsEnabled && vapidPublicKey) {
        navigator.serviceWorker.register(basePath + 'sw.js').then(function(reg) {
          return reg.pushManager.getSubscription().then(function(sub) {
            if (sub) return sub;
            var key = Uint8Array.from(atob(vapidPublicKey.replace(/-/g,'+').replace(/_/g,'/')), function(c){return c.charCodeAt(0);});
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
          });
        }).then(function(sub) {
          if (!sub) return;
          var key = sub.toJSON();
          fetch(basePath + 'api/push_subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              csrf_token: csrfTokenHeader,
              endpoint: key.endpoint,
              p256dh: key.keys.p256dh,
              auth: key.keys.auth,
              action: 'subscribe'
            }),
            credentials: 'same-origin'
          }).catch(function(){});
        }).catch(function(e){ console.log('SW/Push error:', e); });
      }
      var _notifCount = 0,
        _msgCount = 0;

      function rememberLatestNotificationId(notifications) {
        if (!window.localStorage || !notifications || notifications.length === 0) return 0;
        var maxId = 0;
        notifications.forEach(function(notif) {
          var id = parseInt(notif.id, 10) || 0;
          if (id > maxId) maxId = id;
        });
        localStorage.setItem(notificationStorageKey, String(maxId));
        return maxId;
      }

      function showBrowserNotification(notif) {
        if (!notif || !('Notification' in window) || Notification.permission !== 'granted') return;

        try {
          var browserNotif = new Notification(notif.title || 'Market Plier', {
            body: notif.content || '',
            icon: basePath + 'assets/images/logo.svg'
          });

          browserNotif.onclick = function() {
            window.focus();
            if (notif.link) {
              window.location.href = new URL(basePath + notif.link, window.location.href).href;
            }
            browserNotif.close();
          };

          setTimeout(function() {
            browserNotif.close();
          }, 8000);
        } catch (e) {}
      }

      function syncBrowserNotifications(notifications) {
        if (!browserNotificationsEnabled || !window.localStorage || !notifications || notifications.length === 0) return;
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        var stored = parseInt(localStorage.getItem(notificationStorageKey) || '0', 10);
        if (!stored) {
          rememberLatestNotificationId(notifications);
          return;
        }

        var maxId = stored;
        notifications
          .slice()
          .reverse()
          .forEach(function(notif) {
            var id = parseInt(notif.id, 10) || 0;
            if (id > stored && !notif.is_read) {
              showBrowserNotification(notif);
            }
            if (id > maxId) maxId = id;
          });

        if (maxId > stored) {
          localStorage.setItem(notificationStorageKey, String(maxId));
        }
      }

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
        // Notifications count + browser notifications
        fetch(basePath + 'api/notifications.php', {
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            _notifCount = data.unread_count || 0;
            syncBrowserNotifications(data.notifications || []);
            var count = _notifCount > 0 ? (_notifCount > 99 ? '99+' : _notifCount) : null;
            ['badgeNotif'].forEach(function(id) {
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
            ['badgeMsg', 'badgeMsgBottom'].forEach(function(id) {
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

<!-- AUTOCOMPLETE -->
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

    function createUserLink(user) {
      var a = document.createElement('a');
      a.href = basePath + 'inscription-connexion/profile.php?user=' + encodeURIComponent(user.username);
      a.className = 'ac-item ac-user';

      var avatarHtml = '';
      if (user.has_photo) {
        avatarHtml = '<img class="ac-thumb ac-thumb-round" src="' + basePath + 'api/profile_photo.php?token=' + encodeURIComponent(user.auth_token) + '" alt="">';
      } else {
        avatarHtml = '<div class="ac-thumb ac-thumb-round ac-thumb-empty"><i class="fa-solid fa-user"></i></div>';
      }

      a.innerHTML = avatarHtml +
        '<div class="ac-text">' +
        '<span class="ac-label">' + escapeHtml(user.username) + '</span>' +
        '<span class="ac-count">Profil utilisateur</span>' +
        '</div>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      return a;
    }

    function appendSeparator() {
      var sep = document.createElement('div');
      sep.className = 'ac-separator';
      dropdown.appendChild(sep);
    }

    function doSearch(q) {
      if (q.length < 1) {
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

          // Users
          var users = Array.isArray(data.users) ? data.users : [];
          if (users.length > 0) {
            if (categories.length > 0 || suggestions.length > 0) appendSeparator();
            users.forEach(function(user) {
              var a = createUserLink(user);
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
      if (input.value.trim().length >= 1 && dropdown.children.length > 0) show();
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

<!-- MOBILE SEARCH PANEL -->
<script>
(function() {
  var toggle = document.getElementById('mobileSearchToggle');
  var panel = document.getElementById('mobileSearchPanel');
  var closeBtn = document.getElementById('mobileSearchClose');
  var mobileInput = document.getElementById('mobileSearchInput');
  var mobileResults = document.getElementById('mobileSearchResults');
  if (!toggle || !panel) return;

  var basePath = <?= json_encode($headerBasePath) ?>;
  var mobileDebounce = null;
  var mobileController = null;
  var mobileSeq = 0;

  var categoryIcons = {
    vetements: 'fa-shirt', electronique: 'fa-laptop', livres: 'fa-book',
    maison: 'fa-house', sport: 'fa-futbol', vehicules: 'fa-car', autre: 'fa-ellipsis'
  };

  function escHtml(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  function openSearchPanel() {
    panel.classList.add('open');
    mobileInput.focus();
  }

  if (toggle) {
    toggle.addEventListener('click', function() {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) mobileInput.focus();
    });
  }

  var bottomSearchBtn = document.getElementById('bottomNavSearch');
  if (bottomSearchBtn) {
    bottomSearchBtn.addEventListener('click', openSearchPanel);
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', function() {
      panel.classList.remove('open');
      mobileInput.value = '';
      mobileResults.innerHTML = '';
    });
  }

  function renderMobileResults(data, q) {
    mobileResults.innerHTML = '';
    var categories = Array.isArray(data.categories) ? data.categories : [];
    var suggestions = Array.isArray(data.suggestions) ? data.suggestions : [];
    var users = Array.isArray(data.users) ? data.users : [];

    categories.forEach(function(cat) {
      var a = document.createElement('a');
      a.href = basePath + 'shop/search.php?category=' + cat.key + (cat.preserve_query && q ? '&q=' + encodeURIComponent(q) : '');
      a.className = 'ac-item';
      a.innerHTML = '<i class="fa-solid ' + (categoryIcons[cat.key] || 'fa-tag') + ' ac-icon"></i>' +
        '<span class="ac-label">' + escHtml(cat.label) + ' (' + cat.count + ')</span>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      mobileResults.appendChild(a);
    });

    if (suggestions.length > 0 && categories.length > 0) {
      var sep = document.createElement('div'); sep.className = 'ac-separator'; mobileResults.appendChild(sep);
    }

    suggestions.forEach(function(item) {
      var a = document.createElement('a');
      a.href = basePath + 'shop/buy.php?id=' + item.id;
      a.className = 'ac-item';
      var price = parseFloat(item.price).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' €';
      var imgHtml = item.image_id
        ? '<img class="ac-thumb" src="' + basePath + 'api/image.php?id=' + item.image_id + '" alt="">'
        : '<div class="ac-thumb ac-thumb-empty"><i class="fa-solid fa-image"></i></div>';
      a.innerHTML = imgHtml + '<div class="ac-text"><span class="ac-label">' + escHtml(item.title) +
        '</span><span class="ac-price">' + price + '</span></div><i class="fa-solid fa-arrow-right ac-arrow"></i>';
      mobileResults.appendChild(a);
    });

    if (users.length > 0) {
      if (suggestions.length > 0 || categories.length > 0) {
        var sep2 = document.createElement('div'); sep2.className = 'ac-separator'; mobileResults.appendChild(sep2);
      }
      users.forEach(function(user) {
        var a = document.createElement('a');
        a.href = basePath + 'inscription-connexion/profile.php?user=' + encodeURIComponent(user.username);
        a.className = 'ac-item';
        var avatar = user.has_photo
          ? '<img class="ac-thumb ac-thumb-round" src="' + basePath + 'api/profile_photo.php?token=' + encodeURIComponent(user.auth_token) + '" alt="">'
          : '<div class="ac-thumb ac-thumb-round ac-thumb-empty"><i class="fa-solid fa-user"></i></div>';
        a.innerHTML = avatar + '<div class="ac-text"><span class="ac-label">' + escHtml(user.username) +
          '</span><span class="ac-count">Profil</span></div><i class="fa-solid fa-arrow-right ac-arrow"></i>';
        mobileResults.appendChild(a);
      });
    }

    // Lien "tout voir"
    if (q) {
      if (mobileResults.children.length > 0) {
        var sep3 = document.createElement('div'); sep3.className = 'ac-separator'; mobileResults.appendChild(sep3);
      }
      var all = document.createElement('a');
      all.href = basePath + 'shop/search.php?q=' + encodeURIComponent(q);
      all.className = 'ac-item ac-all';
      all.innerHTML = '<i class="fa-solid fa-magnifying-glass ac-icon"></i>' +
        '<span class="ac-label">Rechercher « ' + escHtml(q) + ' »</span>' +
        '<i class="fa-solid fa-arrow-right ac-arrow"></i>';
      mobileResults.appendChild(all);
    }
  }

  function doMobileSearch(q) {
    if (q.length < 1) { mobileResults.innerHTML = ''; return; }
    mobileSeq++;
    var mySeq = mobileSeq;
    if (mobileController) mobileController.abort();
    mobileController = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var opts = mobileController ? { signal: mobileController.signal } : {};

    fetch(basePath + 'api/autocomplete.php?q=' + encodeURIComponent(q), opts)
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (mySeq === mobileSeq) renderMobileResults(data, q);
      })
      .catch(function(e) { if (e && e.name !== 'AbortError') mobileResults.innerHTML = ''; });
  }

  mobileInput.addEventListener('input', function() {
    clearTimeout(mobileDebounce);
    var q = mobileInput.value.trim();
    mobileDebounce = setTimeout(function() { doMobileSearch(q); }, 200);
  });
})();
</script>

<?php include __DIR__ . '/includes/toast.php'; ?>
