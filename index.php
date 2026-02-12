<?php
session_start();
require_once 'database/db.php';

// Génération token CSRF si absent
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
  $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch();
}
// Vérifier si l'utilisateur est admin
$isAdmin = false;
if ($user) {
  try {
    $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE id = ?");
    $checkAdmin->execute([$_SESSION['user_id']]);
    $userData = $checkAdmin->fetch();
    $isAdmin = ($userData && $userData['is_admin'] == 1);
  } catch (PDOException $ex) {
    $isAdmin = false;
  }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Market Plier</title>
  <link rel="stylesheet" href="styles/index.css" />
</head>

<body>

  <!-- NAV -->
  <nav>
    <a class="logo" href="#">
      <svg viewBox="0 0 38 38" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M4 7 Q1 3 4 1" stroke="white" stroke-width="2.2" stroke-linecap="round" />
        <path d="M5 7h4l4 14h14l3-9H12" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        <circle cx="16" cy="28" r="2.5" fill="white" />
        <circle cx="26" cy="28" r="2.5" fill="white" />
      </svg>
      Market Plier
    </a>

    <div class="nav-divider"></div>

    <div class="nav-center">
      <input class="search-bar" type="text" placeholder="Rechercher" />
      <ul class="nav-links">
        <li><a href="#">vendre</a></li>
        <li><a href="#">lange</a></li>
        <li><a href="#">aide</a></li>
      </ul>
    </div>

    <div class="nav-divider"></div>

    <div class="nav-avatar-cell">
      <div class="avatar">
        <img src="https://picsum.photos/seed/avatar1/100/100" alt="profil" />
      </div>
    </div>
  </nav>

  <!-- PAGE -->
  <div class="page">

    <h1 class="greeting">Bonjour, Gaspmi</h1>

    <!-- TENDANCES -->
    <div class="section">
      <h2 class="section-title">Articles tendances</h2>
      <hr class="section-line" />
      <div class="row">
        <div class="items">
          <div class="circle-card">
            <div class="circle-img"><img src="https://picsum.photos/seed/t1/300/300" alt="" /></div>
          </div>
          <div class="circle-card">
            <div class="circle-img"><img src="https://picsum.photos/seed/t2/300/300" alt="" /></div>
          </div>
          <div class="circle-card">
            <div class="circle-img"><img src="https://picsum.photos/seed/t3/300/300" alt="" /></div>
          </div>
          <div class="circle-card">
            <div class="circle-img"><img src="https://picsum.photos/seed/t4/300/300" alt="" /></div>
          </div>
        </div>
        <span class="arrow">›</span>
      </div>
    </div>

    <!-- ACHATS RÉCENTS -->
    <div class="section">
      <h2 class="section-title">Achats consulté dernierement</h2>
      <hr class="section-line" />
      <div class="row">
        <div class="items">
          <div class="square-card">
            <div class="square-img"><img src="https://picsum.photos/seed/p1/300/300" alt="" /></div>
          </div>
          <div class="square-card">
            <div class="square-img"><img src="https://picsum.photos/seed/p2/300/300" alt="" /></div>
          </div>
          <div class="square-card">
            <div class="square-img"><img src="https://picsum.photos/seed/p3/300/300" alt="" /></div>
          </div>
          <div class="square-card">
            <div class="square-img"><img src="https://picsum.photos/seed/p4/300/300" alt="" /></div>
          </div>
        </div>
        <span class="arrow">›</span>
      </div>
    </div>

  </div>

</body>

</html>