<?php
session_start();

require_once '../database/db.php';
require_once '../includes/lang.php';
require_once '../includes/cart.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'error' => t('cart_method_not_allowed')]);
  exit();
}

if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
  echo json_encode(['success' => false, 'error' => t('cart_invalid_csrf')]);
  exit();
}

$action = $_POST['action'] ?? 'toggle';

if ($action === 'clear') {
  cart_clear();
  echo json_encode([
    'success' => true,
    'cleared' => true,
    'count' => cart_count(),
  ]);
  exit();
}

$listingId = filter_input(INPUT_POST, 'listing_id', FILTER_VALIDATE_INT);
if (!$listingId) {
  echo json_encode(['success' => false, 'error' => t('cart_invalid_listing')]);
  exit();
}

try {
  $stmt = $pdo->prepare("SELECT id, auth_token, status FROM listings WHERE id = ?");
  $stmt->execute([$listingId]);
  $listing = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$listing || (($listing['status'] ?? 'active') !== 'active')) {
    cart_remove($listingId);
    echo json_encode(['success' => false, 'error' => t('cart_listing_unavailable'), 'count' => cart_count()]);
    exit();
  }

  if (isset($_SESSION['auth_token']) && $_SESSION['auth_token'] === $listing['auth_token']) {
    echo json_encode(['success' => false, 'error' => t('cart_own_listing'), 'count' => cart_count()]);
    exit();
  }

  $inCart = match ($action) {
    'add' => (cart_add($listingId) || cart_has($listingId)),
    'remove' => (cart_remove($listingId) ? false : cart_has($listingId)),
    default => cart_toggle($listingId),
  };

  echo json_encode([
    'success' => true,
    'in_cart' => $inCart,
    'count' => cart_count(),
  ]);
} catch (PDOException $e) {
  error_log('toggle_cart error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'error' => t('save_error')]);
}

