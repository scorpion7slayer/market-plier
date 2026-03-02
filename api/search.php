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

// Count total results
$countSQL = "SELECT COUNT(*) FROM listings l $whereSQL";
$countStmt = $pdo->prepare($countSQL);
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

// Fetch results with first image
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
