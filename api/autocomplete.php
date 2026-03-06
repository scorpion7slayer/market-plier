<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/db.php';

function normalizeAutocompleteText(string $value): string
{
  $value = trim($value);
  $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

  if ($normalized !== false) {
    $value = $normalized;
  }

  return mb_strtolower($value, 'UTF-8');
}

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
  echo json_encode([
    'suggestions' => [],
    'categories' => [],
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$searchTerm = '%' . $query . '%';

$categoryLabels = [
  'vetements'    => 'Vêtements',
  'electronique' => 'Électronique',
  'livres'       => 'Livres & Médias',
  'maison'       => 'Maison & Jardin',
  'sport'        => 'Sport & Loisirs',
  'vehicules'    => 'Véhicules',
  'autre'        => 'Autre',
];

// Fetch matching listings using the same scope as the full search page.
$stmt = $pdo->prepare(
  "SELECT l.id, l.title, l.price, l.category,
            COALESCE(
                (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
                l.image
            ) AS image 
     FROM listings l  
     WHERE l.title LIKE ? OR l.description LIKE ?
     ORDER BY l.created_at DESC
     LIMIT 6"
);
$stmt->execute([$searchTerm, $searchTerm]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Suggest categories that can further narrow the current text query.
$matchedCategories = [];
$categoryStmt = $pdo->prepare(
  "SELECT category, COUNT(*) AS count
   FROM listings
   WHERE title LIKE ? OR description LIKE ?
   GROUP BY category
   ORDER BY count DESC, category ASC
   LIMIT 4"
);
$categoryStmt->execute([$searchTerm, $searchTerm]);

foreach ($categoryStmt->fetchAll(PDO::FETCH_ASSOC) as $categoryRow) {
  $key = $categoryRow['category'];

  if (!isset($categoryLabels[$key])) {
    continue;
  }

  $matchedCategories[$key] = [
    'key' => $key,
    'label' => $categoryLabels[$key],
    'count' => (int) $categoryRow['count'],
    'preserve_query' => true,
  ];
}

$countStmt = $pdo->query(
  "SELECT category, COUNT(*) AS count
   FROM listings
   GROUP BY category"
);
$categoryTotals = [];

foreach ($countStmt->fetchAll(PDO::FETCH_ASSOC) as $categoryRow) {
  $categoryTotals[$categoryRow['category']] = (int) $categoryRow['count'];
}

$normalizedQuery = normalizeAutocompleteText($query);

foreach ($categoryLabels as $key => $label) {
  $normalizedLabel = normalizeAutocompleteText($label);
  $normalizedKey = normalizeAutocompleteText($key);

  if (
    $normalizedQuery !== ''
    && (mb_stripos($normalizedLabel, $normalizedQuery) !== false || mb_stripos($normalizedKey, $normalizedQuery) !== false)
    && !isset($matchedCategories[$key])
    && !empty($categoryTotals[$key])
  ) {
    $matchedCategories[$key] = [
      'key' => $key,
      'label' => $label,
      'count' => $categoryTotals[$key],
      'preserve_query' => false,
    ];
  }
}

echo json_encode([
  'suggestions' => $listings,
  'categories'  => array_values($matchedCategories),
], JSON_UNESCAPED_UNICODE);
