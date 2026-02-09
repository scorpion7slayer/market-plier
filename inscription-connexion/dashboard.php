<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

require_once '../database/db.php';

$isAdmin = false;
try {
  $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
  $checkAdmin->execute([$_SESSION['user_id']]);
  $userData = $checkAdmin->fetch();
  $isAdmin = ($userData && $userData['is_admin'] == 1);
} catch (PDOException $ex) {
  $isAdmin = false;
}

include 'header.php';
