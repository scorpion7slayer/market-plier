<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/db.php';

$query = trim($_GET['q'] ?? '');

if (mb_strlen($query) < 2) {
  echo json_encode(['suggestions' => []]);
  exit;
}

$searchTerm = '%' . $query . '%';

// Fetch matching listings (title matches)
$stmt = $pdo->prepare(
  "SELECT l.id, l.title, l.price, l.category,
            COALESCE(
                (SELECT li.image_path FROM listing_images li WHERE li.listing_id = l.id ORDER BY li.sort_order ASC LIMIT 1),
                l.image
            ) AS image 
     FROM listings l 
     WHERE l.title LIKE ?
     ORDER BY l.created_at DESC
     LIMIT 6"
);
$stmt->execute([$searchTerm]);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch matching categories
$categoryLabels = [
  'vetements'    => 'Vêtements',
  'electronique' => 'Électronique',
  'livres'       => 'Livres & Médias',
  'maison'       => 'Maison & Jardin',
  'sport'        => 'Sport & Loisirs',
  'vehicules'    => 'Véhicules',
  'autre'        => 'Autre',
];

$matchedCategories = [];
foreach ($categoryLabels as $key => $label) {
  if (mb_stripos($label, $query) !== false || mb_stripos($key, $query) !== false) {
    // Count listings in this category
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM listings WHERE category = ?");
    $cStmt->execute([$key]);
    $count = (int) $cStmt->fetchColumn();
    if ($count > 0) {
      $matchedCategories[] = [
        'key'   => $key,
        'label' => $label,
        'count' => $count,
      ];
    }
  }
}

echo json_encode([
  'suggestions' => $listings,
  'categories'  => $matchedCategories,
], JSON_UNESCAPED_UNICODE);
