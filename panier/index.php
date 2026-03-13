<?php
session_start();

require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';
require_once '../includes/cart.php';

$user = null;
if (isset($_SESSION['auth_token'])) {
    $stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$categoryLabels = [
    'vetements' => t('cat_vetements'),
    'electronique' => t('cat_electronique'),
    'livres' => t('cat_livres'),
    'maison' => t('cat_maison'),
    'sport' => t('cat_sport'),
    'vehicules' => t('cat_vehicules'),
    'autre' => t('cat_autre'),
];
$conditionLabels = [
    'neuf' => t('condition_neuf'),
    'tres_bon_etat' => t('condition_tres_bon_etat'),
    'bon_etat' => t('condition_bon_etat'),
    'etat_correct' => t('condition_etat_correct'),
    'pour_pieces' => t('condition_pour_pieces'),
];

$cartIds = cart_get_ids();
$cartItems = [];
$cartTotal = 0.0;

if (! empty($cartIds)) {
    $placeholders = implode(',', array_fill(0, count($cartIds), '?'));

    $stmt = $pdo->prepare("
    SELECT l.id, l.title, l.price, l.category, l.item_condition, l.location, l.auth_token,
           u.username AS seller_name,
           (SELECT li.id FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1) AS image_id,
           COALESCE(
             (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
             l.image
           ) AS image_path
    FROM listings l
    JOIN users u ON u.auth_token = l.auth_token
    WHERE l.status = 'active' AND l.id IN ($placeholders)
  ");
    $stmt->execute($cartIds);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itemsById = [];
    foreach ($rows as $row) {
        $itemsById[(int) $row['id']] = $row;
    }

    foreach ($cartIds as $cartId) {
        if (! isset($itemsById[$cartId])) {
            cart_remove($cartId);
            continue;
        }

        $item = $itemsById[$cartId];
        $cartItems[] = $item;
        $cartTotal += (float) $item['price'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars(t('cart_page_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/cart.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
$headerUser = $user;
include '../header.php';
?>

  <main class="cart-main">
    <div class="cart-shell">
      <div class="cart-top">
        <div>
          <h1 class="cart-title"><i class="fa-solid fa-basket-shopping"></i> <?= htmlspecialchars(t('cart_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
          <p class="cart-subtitle"><?= htmlspecialchars(t('cart_subtitle'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <?php if (! empty($cartItems)): ?>
          <span class="cart-count"><?= count($cartItems) ?> <?= htmlspecialchars(count($cartItems) > 1 ? t('cart_items_plural') : t('cart_items_singular'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($cartItems)): ?>
        <section class="cart-empty">
          <i class="fa-solid fa-basket-shopping"></i>
          <h2><?= htmlspecialchars(t('cart_empty_title'), ENT_QUOTES, 'UTF-8') ?></h2>
          <p><?= htmlspecialchars(t('cart_empty_text'), ENT_QUOTES, 'UTF-8') ?></p>
          <a href="../shop/search.php" class="cart-primary-link">
            <i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('cart_browse'), ENT_QUOTES, 'UTF-8') ?>
          </a>
        </section>
      <?php else: ?>
        <div class="cart-layout">
          <section class="cart-list">
            <?php foreach ($cartItems as $item): ?>
              <article class="cart-card" data-listing-id="<?= (int) $item['id'] ?>" data-price="<?= htmlspecialchars((string) $item['price'], ENT_QUOTES, 'UTF-8') ?>">
                <a href="../shop/buy.php?id=<?= (int) $item['id'] ?>" class="cart-card-media">
                  <?php if ($item['image_id']): ?>
                    <img src="../api/image.php?id=<?= (int) $item['image_id'] ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                  <?php elseif ($item['image_path']): ?>
                    <img src="../uploads/listings/<?= htmlspecialchars($item['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
                  <?php else: ?>
                    <span class="cart-card-placeholder"><i class="fa-solid fa-image"></i></span>
                  <?php endif; ?>
                </a>

                <div class="cart-card-body">
                  <div class="cart-card-head">
                    <div>
                      <div class="cart-card-price"><?= number_format((float) $item['price'], 2, ',', ' ') ?> €</div>
                      <a href="../shop/buy.php?id=<?= (int) $item['id'] ?>" class="cart-card-title"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></a>
                    </div>
                    <button type="button" class="cart-remove-btn" data-listing-id="<?= (int) $item['id'] ?>" title="<?= htmlspecialchars(t('cart_remove'), ENT_QUOTES, 'UTF-8') ?>">
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                  </div>

                  <div class="cart-card-meta">
                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($item['seller_name'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($categoryLabels[$item['category']] ?? $item['category'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span><i class="fa-solid fa-circle-info"></i> <?= htmlspecialchars($conditionLabels[$item['item_condition']] ?? $item['item_condition'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (! empty($item['location'])): ?>
                      <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($item['location'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php endif; ?>
                  </div>

                  <div class="cart-card-actions">
                    <a href="../shop/buy.php?id=<?= (int) $item['id'] ?>" class="cart-secondary-link">
                      <i class="fa-solid fa-eye"></i> <?= htmlspecialchars(t('cart_view_listing'), ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </section>

          <aside class="cart-summary">
            <h2><?= htmlspecialchars(t('cart_summary'), ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="cart-summary-row">
              <span><?= htmlspecialchars(t('cart_total_items'), ENT_QUOTES, 'UTF-8') ?></span>
              <strong id="cartItemCount"><?= count($cartItems) ?></strong>
            </div>
            <div class="cart-summary-row">
              <span><?= htmlspecialchars(t('cart_total_price'), ENT_QUOTES, 'UTF-8') ?></span>
              <strong id="cartTotalValue"><?= number_format($cartTotal, 2, ',', ' ') ?> €</strong>
            </div>
            <p class="cart-summary-note"><?= htmlspecialchars(t('cart_summary_note'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (! $user): ?>
              <a href="../inscription-connexion/login.php" class="cart-primary-link">
                <i class="fa-solid fa-right-to-bracket"></i> <?= htmlspecialchars(t('cart_login_cta'), ENT_QUOTES, 'UTF-8') ?>
              </a>
            <?php endif; ?>
            <a href="../shop/search.php" class="cart-secondary-link cart-secondary-link-full">
              <i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(t('cart_continue_shopping'), ENT_QUOTES, 'UTF-8') ?>
            </a>
            <button type="button" class="cart-clear-btn" id="clearCartBtn">
              <i class="fa-solid fa-trash"></i> <?= htmlspecialchars(t('cart_clear'), ENT_QUOTES, 'UTF-8') ?>
            </button>
          </aside>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include '../footer.php'; ?>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;
      var locale = <?= json_encode(getUserLang()) ?>;
      var localeMap = {
        fr: 'fr-FR',
        en: 'en-US',
        es: 'es-ES',
        de: 'de-DE'
      };
      var i18n = <?= json_encode([
        'cart_removed' => t('cart_removed'),
        'cart_cleared' => t('cart_cleared'),
        'error' => t('generic_error'),
    ]) ?>;

      var cartMain = document.querySelector('.cart-main');
      var countNode = document.getElementById('cartItemCount');
      var totalNode = document.getElementById('cartTotalValue');
      var clearBtn = document.getElementById('clearCartBtn');

      function formatPrice(value) {
        return new Intl.NumberFormat(localeMap[locale] || 'fr-FR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        }).format(value) + ' €';
      }

      function refreshState(count) {
        var cards = document.querySelectorAll('.cart-card');
        var total = 0;

        cards.forEach(function(card) {
          total += parseFloat(card.getAttribute('data-price') || '0');
        });

        if (countNode) countNode.textContent = cards.length;
        if (totalNode) totalNode.textContent = formatPrice(total);
        if (typeof window.mpUpdateCartBadges === 'function') {
          window.mpUpdateCartBadges(count);
        }

        if (cards.length === 0) {
          window.location.reload();
        }
      }

      function sendCartAction(payload) {
        return fetch('../api/toggle_cart.php', {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            if (!data.success) {
              if (typeof mpShowToast === 'function') mpShowToast(data.error || i18n.error, 'error');
              var handledError = new Error(data.error || i18n.error);
              handledError.handled = true;
              throw handledError;
            }
            return data;
          })
          .catch(function(error) {
            if (!error || !error.handled) {
              if (typeof mpShowToast === 'function') mpShowToast(i18n.error, 'error');
            }
            return Promise.reject(error);
          });
      }

      document.querySelectorAll('.cart-remove-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var card = btn.closest('.cart-card');
          if (!card) return;

          btn.disabled = true;

          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('listing_id', btn.getAttribute('data-listing-id'));
          fd.append('action', 'remove');

          sendCartAction(fd)
            .then(function(data) {
              card.style.opacity = '0';
              card.style.transform = 'translateY(8px)';
              setTimeout(function() {
                card.remove();
                refreshState(data.count || 0);
              }, 180);

              if (typeof mpShowToast === 'function') mpShowToast(i18n.cart_removed, 'success');
            })
            .catch(function() {
              btn.disabled = false;
            });
        });
      });

      if (clearBtn) {
        clearBtn.addEventListener('click', function() {
          clearBtn.disabled = true;

          var fd = new FormData();
          fd.append('csrf_token', csrfToken);
          fd.append('action', 'clear');

          sendCartAction(fd)
            .then(function(data) {
              if (typeof mpShowToast === 'function') mpShowToast(i18n.cart_cleared, 'success');
              if (typeof window.mpUpdateCartBadges === 'function') {
                window.mpUpdateCartBadges(data.count || 0);
              }
              window.location.reload();
            })
            .catch(function() {
              clearBtn.disabled = false;
            });
        });
      }
    })();
  </script>
</body>

</html>