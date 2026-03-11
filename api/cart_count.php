<?php
session_start();

require_once '../includes/cart.php';

header('Content-Type: application/json');

echo json_encode([
  'success' => true,
  'count' => cart_count(),
]);

