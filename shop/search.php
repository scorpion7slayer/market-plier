<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

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
  'vetements'    => 'Vêtements',
  'electronique' => 'Électronique',
  'livres'       => 'Livres & Médias',
  'maison'       => 'Maison & Jardin',
  'sport'        => 'Sport & Loisirs',
  'vehicules'    => 'Véhicules',
  'autre'        => 'Autre',
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

// Server-side search for initial page load
$where = [];
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

$whereSQL = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM listings l $whereSQL");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$perPage = 20;
$sql = "SELECT l.id, l.title, l.price, l.category, l.item_condition, l.location, l.created_at,
               COALESCE(
                   (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
                   l.image
               ) AS image,
               u.username
        FROM listings l
        LEFT JOIN users u ON u.auth_token = l.auth_token
        $whereSQL
        ORDER BY l.created_at DESC
        LIMIT ? OFFSET 0";

$stmtParams = array_merge($params, [$perPage]);
$stmt = $pdo->prepare($sql);
$stmt->execute($stmtParams);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title>Recherche — Market Plier</title>
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
        <i class="fa-solid fa-border-all"></i> Tout
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
            <i class="fa-solid fa-border-all"></i> Toutes les catégories
          <?php endif; ?>
        </span>
        <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
      </button>
      <div class="category-dropdown-menu" id="categoryDropdownMenu">
        <a href="?<?= $query !== '' ? 'q=' . urlencode($query) : '' ?>"
          class="category-dropdown-item <?= $category === '' ? 'active' : '' ?>">
          <i class="fa-solid fa-border-all"></i> Tout
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

    <!-- Search info -->
    <div class="search-info">
      <?php if ($query !== '' || $category !== ''): ?>
        <span class="search-info-text">
          <?= $total ?> résultat<?= $total > 1 ? 's' : '' ?>
          <?php if ($query !== ''): ?>
            pour « <strong><?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?></strong> »
          <?php endif; ?>
          <?php if ($category !== '' && isset($categoryLabels[$category])): ?>
            dans <strong><?= htmlspecialchars($categoryLabels[$category], ENT_QUOTES, 'UTF-8') ?></strong>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="search-info-text">Toutes les annonces (<?= $total ?>)</span>
      <?php endif; ?>
    </div>

    <!-- Results grid -->
    <div class="search-results" id="searchResults">
      <?php if (empty($listings)): ?>
        <div class="no-results">
          <i class="fa-solid fa-magnifying-glass"></i>
          <p>Aucun résultat trouvé</p>
          <span>Essayez avec d'autres mots-clés ou une autre catégorie.</span>
        </div>
      <?php else: ?>
        <?php foreach ($listings as $listing): ?>
          <a href="../shop/buy.php?id=<?= (int) $listing['id'] ?>" class="listing-card">
            <div class="listing-card-img">
              <?php if ($listing['image']): ?>
                <img src="../uploads/listings/<?= htmlspecialchars($listing['image'], ENT_QUOTES, 'UTF-8') ?>"
                  alt="<?= htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8') ?>">
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
          <i class="fa-solid fa-arrow-down"></i> Voir plus
        </button>
      </div>
    <?php endif; ?>
  </main>

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
      var loadMoreBtn = document.getElementById('loadMoreBtn');
      var loadMoreContainer = document.getElementById('loadMoreContainer');
      var resultsContainer = document.getElementById('searchResults');

      var categoryIcons = <?= json_encode($categoryIcons) ?>;
      var categoryLabels = <?= json_encode($categoryLabels) ?>;

      if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
          currentPage++;
          loadMoreBtn.disabled = true;
          loadMoreBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Chargement...';

          var params = new URLSearchParams();
          if (query) params.set('q', query);
          if (category) params.set('category', category);
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
                if (listing.image) {
                  var img = document.createElement('img');
                  img.src = '../uploads/listings/' + listing.image;
                  img.alt = listing.title;
                  imgDiv.appendChild(img);
                } else {
                  imgDiv.innerHTML = '<div class="listing-card-placeholder"><i class="fa-solid fa-image"></i></div>';
                }

                var body = document.createElement('div');
                body.className = 'listing-card-body';

                var price = document.createElement('div');
                price.className = 'listing-card-price';
                price.textContent = parseFloat(listing.price).toLocaleString('fr-FR', {
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