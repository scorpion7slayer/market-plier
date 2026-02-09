<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit();
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../header.php';
