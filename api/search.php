<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/db.php';

$query = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$allowedCategories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
$allowedConditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];
$allowedSorts = ['newest', 'oldest', 'cheapest', 'expensive'];

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

$priceMin = trim($_GET['price_min'] ?? '');
$priceMax = trim($_GET['price_max'] ?? '');
$conditionFilter = trim($_GET['condition'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');

if ($priceMin !== '' && is_numeric($priceMin) && (float)$priceMin >= 0) {
  $where[] = 'l.price >= ?';
  $params[] = (float)$priceMin;
}
if ($priceMax !== '' && is_numeric($priceMax) && (float)$priceMax > 0) {
  $where[] = 'l.price <= ?';
  $params[] = (float)$priceMax;
}
if ($conditionFilter !== '' && in_array($conditionFilter, $allowedConditions, true)) {
  $where[] = 'l.item_condition = ?';
  $params[] = $conditionFilter;
}

if (!in_array($sort, $allowedSorts, true)) $sort = 'newest';

$orderBy = match($sort) {
  'oldest'    => 'l.created_at ASC',
  'cheapest'  => 'l.price ASC, l.created_at DESC',
  'expensive' => 'l.price DESC, l.created_at DESC',
  default     => 'l.created_at DESC',
};

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// Count total results
$countSQL = "SELECT COUNT(*) FROM listings l $whereSQL";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Fetch results with first image
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
        LIMIT ? OFFSET ?";

$stmtParams = array_merge($params, [$perPage, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($stmtParams);
$listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
  'results' => $listings,
  'total' => $total,
  'page' => $page,
  'perPage' => $perPage,
  'totalPages' => (int) ceil($total / $perPage),
], JSON_UNESCAPED_UNICODE);
