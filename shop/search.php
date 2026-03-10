<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

$user = null;
if (isset($_SESSION['auth_token'])) {
  $stmt = $pdo->prepare("SELECT username, email, profile_photo FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $user = $stmt->fetch();
}

$query = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$allowedCategories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
$categoryLabels = [
  'vetements'    => t('cat_vetements'),
  'electronique' => t('cat_electronique'),
  'livres'       => t('cat_livres'),
  'maison'       => t('cat_maison'),
  'sport'        => t('cat_sport'),
  'vehicules'    => t('cat_vehicules'),
  'autre'        => t('cat_autre'),
];
$categoryIcons = [
  'vetements'    => 'fa-shirt',
  'electronique' => 'fa-laptop',
  'livres'       => 'fa-book',
  'maison'       => 'fa-house',
  'sport'        => 'fa-futbol',
  'vehicules'    => 'fa-car',
  'autre'        => 'fa-ellipsis',
];

// Server-side search for initial page load — only show active listings
$where = ["l.status = 'active'"];
$params = [];

if ($query !== '') {
  $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
  $params[] = '%' . $query . '%';
  $params[] = '%' . $query . '%';
}

if ($category !== '' && in_array($category, $allowedCategories, true)) {
  $where[] = 'l.category = ?';
  $params[] = $category;
}

// Filtres avancés (doivent s'appliquer avant le calcul du total)
$priceMin = trim($_GET['price_min'] ?? '');
$priceMax = trim($_GET['price_max'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

$allowedConditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];
$allowedSorts = ['newest', 'oldest', 'cheapest', 'expensive'];

if ($priceMin !== '' && is_numeric($priceMin) && (float)$priceMin >= 0) {
  $where[] = 'l.price >= ?';
  $params[] = (float)$priceMin;
}
if ($priceMax !== '' && is_numeric($priceMax) && (float)$priceMax > 0) {
  $where[] = 'l.price <= ?';
  $params[] = (float)$priceMax;
}
if ($condition !== '' && in_array($condition, $allowedConditions, true)) {
  $where[] = 'l.item_condition = ?';
  $params[] = $condition;
}

// construire la clause WHERE après avoir ajouté toutes les conditions
$whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// total des annonces correspondant aux critères
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings l $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

if (!in_array($sort, $allowedSorts, true)) $sort = 'newest';

$orderBy = match ($sort) {
  'oldest'    => 'l.created_at ASC',
  'cheapest'  => 'l.price ASC, l.created_at DESC',
  'expensive' => 'l.price DESC, l.created_at DESC',
  default     => 'l.created_at DESC',
};

$conditionLabels = [
  'neuf'          => t('condition_neuf'),
  'tres_bon_etat' => t('condition_tres_bon_etat'),
  'bon_etat'      => t('condition_bon_etat'),
  'etat_correct'  => t('condition_etat_correct'),
  'pour_pieces'   => t('condition_pour_pieces'),
];

$perPage = 20;
$sql = "SELECT l.id, l.title, l.price, l.category, l.item_condition, l.location, l.created_at,
               (SELECT li.id FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1) AS image_id,
               COALESCE(
                   (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
                   l.image
               ) AS image_path,
               u.username
        FROM listings l
        LEFT JOIN users u ON u.auth_token = l.auth_token
        $whereSQL
        ORDER BY $orderBy
        LIMIT ? OFFSET 0";

$stmtParams = array_merge($params, [$perPage]);
$stmt = $pdo->prepare($sql);
$stmt->execute($stmtParams);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars(t('search_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/search.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $user;
  include '../header.php';
  ?>

  <main class="search-main">
    <!-- Category filters (desktop) -->
    <div class="category-filters">
      <a href="?<?= $query !== '' ? 'q=' . urlencode($query) : '' ?>"
        class="category-chip <?= $category === '' ? 'active' : '' ?>">
        <i class="fa-solid fa-border-all"></i> <?= htmlspecialchars(t('search_filter_all'), ENT_QUOTES, 'UTF-8') ?>
      </a>
      <?php foreach ($allowedCategories as $cat): ?>
        <a href="?<?= $query !== '' ? 'q=' . urlencode($query) . '&' : '' ?>category=<?= $cat ?>"
          class="category-chip <?= $category === $cat ? 'active' : '' ?>">
          <i class="fa-solid <?= $categoryIcons[$cat] ?>"></i>
          <?= htmlspecialchars($categoryLabels[$cat], ENT_QUOTES, 'UTF-8') ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Category dropdown (mobile) -->
    <div class="category-dropdown">
      <button class="category-dropdown-toggle <?= $category !== '' ? 'active' : '' ?>" id="categoryDropdownToggle">
        <span>
          <?php if ($category !== '' && isset($categoryLabels[$category])): ?>
            <i class="fa-solid <?= $categoryIcons[$category] ?>"></i>
            <?= htmlspecialchars($categoryLabels[$category], ENT_QUOTES, 'UTF-8') ?>
          <?php else: ?>
            <i class="fa-solid fa-border-all"></i> <?= htmlspecialchars(t('search_all_categories'), ENT_QUOTES, 'UTF-8') ?>
          <?php endif; ?>
        </span>
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </button>
      <div class="category-dropdown-menu" id="categoryDropdownMenu">
        <a href="?<?= $query !== '' ? 'q=' . urlencode($query) : '' ?>"
          class="category-dropdown-item <?= $category === '' ? 'active' : '' ?>">
          <i class="fa-solid fa-border-all"></i> <?= htmlspecialchars(t('search_filter_all'), ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php foreach ($allowedCategories as $cat): ?>
          <a href="?<?= $query !== '' ? 'q=' . urlencode($query) . '&' : '' ?>category=<?= $cat ?>"
            class="category-dropdown-item <?= $category === $cat ? 'active' : '' ?>">
            <i class="fa-solid <?= $categoryIcons[$cat] ?>"></i>
            <?= htmlspecialchars($categoryLabels[$cat], ENT_QUOTES, 'UTF-8') ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Filtres avancés -->
    <div class="search-filters">
      <form method="GET" class="filters-form" id="filtersForm">
        <?php if ($query !== ''): ?><input type="hidden" name="q" value="<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
        <div class="filter-group">
          <label class="filter-label"><?= htmlspecialchars(t('search_filter_price'), ENT_QUOTES, 'UTF-8') ?></label>
          <div class="filter-price-row">
            <input type="number" name="price_min" class="filter-input" placeholder="<?= htmlspecialchars(t('search_price_min'), ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01"
              value="<?= htmlspecialchars($priceMin, ENT_QUOTES, 'UTF-8') ?>">
            <span class="filter-sep">—</span>
            <input type="number" name="price_max" class="filter-input" placeholder="<?= htmlspecialchars(t('search_price_max'), ENT_QUOTES, 'UTF-8') ?>" min="0" step="0.01"
              value="<?= htmlspecialchars($priceMax, ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
        <div class="filter-group">
          <label class="filter-label"><?= htmlspecialchars(t('search_filter_condition'), ENT_QUOTES, 'UTF-8') ?></label>
          <select name="condition" class="filter-select">
            <option value=""><?= htmlspecialchars(t('search_filter_all'), ENT_QUOTES, 'UTF-8') ?></option>
            <?php foreach ($conditionLabels as $ck => $cl): ?>
              <option value="<?= $ck ?>" <?= $condition === $ck ? 'selected' : '' ?>><?= htmlspecialchars($cl, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label class="filter-label"><?= htmlspecialchars(t('search_filter_sort'), ENT_QUOTES, 'UTF-8') ?></label>
          <select name="sort" class="filter-select">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= htmlspecialchars(t('search_sort_recent'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>><?= htmlspecialchars(t('search_sort_oldest'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="cheapest" <?= $sort === 'cheapest' ? 'selected' : '' ?>><?= htmlspecialchars(t('search_sort_price_asc'), ENT_QUOTES, 'UTF-8') ?></option>
            <option value="expensive" <?= $sort === 'expensive' ? 'selected' : '' ?>><?= htmlspecialchars(t('search_sort_price_desc'), ENT_QUOTES, 'UTF-8') ?></option>
          </select>
        </div>
        <button type="submit" class="filter-btn"><i class="fa-solid fa-filter"></i> <?= htmlspecialchars(t('search_filter_apply'), ENT_QUOTES, 'UTF-8') ?></button>
      </form>
    </div>

    <!-- Search info -->
    <div class="search-info">
      <?php if ($query !== '' || $category !== ''): ?>
        <span class="search-info-text">
          <?= $total ?> <?= htmlspecialchars($total > 1 ? t('search_listings_count') : t('search_listing_count'), ENT_QUOTES, 'UTF-8') ?>
          <?php if ($query !== ''): ?>
            <?= htmlspecialchars(t('search_for'), ENT_QUOTES, 'UTF-8') ?> « <strong><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></strong> »
          <?php endif; ?>
          <?php if ($category !== '' && isset($categoryLabels[$category])): ?>
            <?= htmlspecialchars(t('search_in_category'), ENT_QUOTES, 'UTF-8') ?> <strong><?= htmlspecialchars($categoryLabels[$category], ENT_QUOTES, 'UTF-8') ?></strong>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="search-info-text"><?= htmlspecialchars(t('search_all_listings'), ENT_QUOTES, 'UTF-8') ?> (<?= $total ?>)</span>
      <?php endif; ?>
    </div>

    <!-- Results grid -->
    <div class="search-results" id="searchResults">
      <?php if (empty($listings)): ?>
        <div class="no-results">
          <i class="fa-solid fa-magnifying-glass"></i>
          <p><?= htmlspecialchars(t('search_no_results'), ENT_QUOTES, 'UTF-8') ?></p>
          <span><?= htmlspecialchars(t('search_no_results_text'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php else: ?>
        <?php foreach ($listings as $listing): ?>
          <a href="../shop/buy.php?id=<?= (int) $listing['id'] ?>" class="listing-card">
            <div class="listing-card-img">
              <?php if ($listing['image_id']): ?>
                <img src="../api/image.php?id=<?= (int) $listing['image_id'] ?>"
                  alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
              <?php elseif ($listing['image_path']): ?>
                <img src="../uploads/listings/<?= htmlspecialchars($listing['image_path'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>" loading="lazy">
              <?php else: ?>
                <div class="listing-card-placeholder">
                  <i class="fa-solid fa-image"></i>
                </div>
              <?php endif; ?>
            </div>
            <div class="listing-card-body">
              <div class="listing-card-price"><?= number_format((float) $listing['price'], 2, ',', ' ') ?> €</div>
              <div class="listing-card-title"><?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?></div>
              <div class="listing-card-meta">
                <?php if ($listing['location']): ?>
                  <span><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($listing['location'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
                <span class="listing-card-category">
                  <i class="fa-solid <?= $categoryIcons[$listing['category']] ?? 'fa-tag' ?>"></i>
                  <?= htmlspecialchars($categoryLabels[$listing['category']] ?? $listing['category'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Load more (JS-driven pagination) -->
    <?php if ($total > $perPage): ?>
      <div class="load-more-container" id="loadMoreContainer">
        <button class="load-more-btn" id="loadMoreBtn">
          <i class="fa-solid fa-arrow-down"></i> <?= htmlspecialchars(t('search_load_more'), ENT_QUOTES, 'UTF-8') ?>
        </button>
      </div>
    <?php endif; ?>
  </main>

  <?php include '../footer.php'; ?>

  <script src="../styles/theme.js"></script>
  <script>
    // Category dropdown (mobile)
    (function() {
      var toggle = document.getElementById('categoryDropdownToggle');
      var menu = document.getElementById('categoryDropdownMenu');
      if (!toggle || !menu) return;

      toggle.addEventListener('click', function() {
        var isOpen = menu.classList.contains('open');
        if (isOpen) {
          menu.classList.remove('open');
          toggle.classList.remove('open');
        } else {
          menu.classList.add('open');
          toggle.classList.add('open');
        }
      });

      document.addEventListener('click', function(e) {
        if (!toggle.contains(e.target) && !menu.contains(e.target)) {
          menu.classList.remove('open');
          toggle.classList.remove('open');
        }
      });
    })();
  </script>
  <script>
    (function() {
      var currentPage = 1;
      var totalPages = <?= (int) ceil($total / $perPage) ?>;
      var query = <?= json_encode($query) ?>;
      var category = <?= json_encode($category) ?>;
      var priceMin = <?= json_encode($priceMin) ?>;
      var priceMax = <?= json_encode($priceMax) ?>;
      var condition = <?= json_encode($condition) ?>;
      var sort = <?= json_encode($sort) ?>;
      var loadMoreBtn = document.getElementById('loadMoreBtn');
      var loadMoreContainer = document.getElementById('loadMoreContainer');
      var resultsContainer = document.getElementById('searchResults');
      var locale = <?= json_encode(getUserLocale()) ?>;
      var i18n = <?= json_encode([
                    'loading' => t('search_loading'),
                  ]) ?>;

      var categoryIcons = <?= json_encode($categoryIcons) ?>;
      var categoryLabels = <?= json_encode($categoryLabels) ?>;

      if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
          currentPage++;
          loadMoreBtn.disabled = true;
          loadMoreBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + i18n.loading;

          var params = new URLSearchParams();
          if (query) params.set('q', query);
          if (category) params.set('category', category);
          if (priceMin) params.set('price_min', priceMin);
          if (priceMax) params.set('price_max', priceMax);
          if (condition) params.set('condition', condition);
          if (sort) params.set('sort', sort);
          params.set('page', currentPage);

          fetch('../api/search.php?' + params.toString())
            .then(function(r) {
              return r.json();
            })
            .then(function(data) {
              data.results.forEach(function(listing) {
                var card = document.createElement('a');
                card.href = '../shop/buy.php?id=' + listing.id;
                card.className = 'listing-card';

                var imgDiv = document.createElement('div');
                imgDiv.className = 'listing-card-img';
                if (listing.image_id) {
                  var img = document.createElement('img');
                  img.src = '../api/image.php?id=' + listing.image_id;
                  img.alt = listing.title;
                  img.loading = 'lazy';
                  imgDiv.appendChild(img);
                } else if (listing.image_path) {
                  var img = document.createElement('img');
                  img.src = '../uploads/listings/' + listing.image_path;
                  img.alt = listing.title;
                  img.loading = 'lazy';
                  imgDiv.appendChild(img);
                } else {
                  var ph = document.createElement('div');
                  ph.className = 'listing-card-placeholder';
                  var icon = document.createElement('i');
                  icon.className = 'fa-solid fa-image';
                  ph.appendChild(icon);
                  imgDiv.appendChild(ph);
                }

                var body = document.createElement('div');
                body.className = 'listing-card-body';

                var price = document.createElement('div');
                price.className = 'listing-card-price';
                price.textContent = parseFloat(listing.price).toLocaleString(locale, {
                  minimumFractionDigits: 2
                }) + ' €';

                var title = document.createElement('div');
                title.className = 'listing-card-title';
                title.textContent = listing.title;

                var meta = document.createElement('div');
                meta.className = 'listing-card-meta';
                if (listing.location) {
                  var loc = document.createElement('span');
                  loc.innerHTML = '<i class="fa-solid fa-location-dot"></i> ' + listing.location;
                  meta.appendChild(loc);
                }
                var catSpan = document.createElement('span');
                catSpan.className = 'listing-card-category';
                var icon = categoryIcons[listing.category] || 'fa-tag';
                var label = categoryLabels[listing.category] || listing.category;
                catSpan.innerHTML = '<i class="fa-solid ' + icon + '"></i> ' + label;
                meta.appendChild(catSpan);

                body.appendChild(price);
                body.appendChild(title);
                body.appendChild(meta);
                card.appendChild(imgDiv);
                card.appendChild(body);
                resultsContainer.appendChild(card);
              });

              if (currentPage >= totalPages) {
                loadMoreContainer.style.display = 'none';
              } else {
                loadMoreBtn.disabled = false;
                loadMoreBtn.innerHTML = '<i class="fa-solid fa-arrow-down"></i> Voir plus';
              }
            })
            .catch(function() {
              loadMoreBtn.disabled = false;
              loadMoreBtn.innerHTML = '<i class="fa-solid fa-arrow-down"></i> Voir plus';
            });
        });
      }
    })();
  </script>
</body>

</html>
