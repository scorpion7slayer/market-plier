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
  <link href="https://fonts.googleapis.com/css2?family=Syne:ital,wght@1,800&family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
      width: 100%;
      height: 100%;
      font-family: 'Inter', sans-serif;
      background: #fff;
      color: #111;
      overflow-x: hidden;
    }

    /* ═══════════════ NAV ═══════════════ */
    nav {
      width: 100%;
      height: 90px;
      background: #8faf78;
      display: grid;
      grid-template-columns: auto 1px 1fr 1px auto;
      align-items: stretch;
    }

    /* Logo col */
    .nav-logo {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 28px;
      text-decoration: none;
    }
    .nav-logo svg { width: 42px; height: 42px; flex-shrink: 0; }
    .nav-logo span {
      font-family: 'Syne', sans-serif;
      font-style: italic;
      font-weight: 800;
      font-size: 1.55rem;
      color: #fff;
      white-space: nowrap;
    }

    /* Divider cols */
    .nav-sep {
      background: rgba(255,255,255,0.4);
      margin: 12px 0;
    }

    /* Center col */
    .nav-center {
      display: flex;
      flex-direction: column;
      justify-content: center;
      gap: 6px;
      padding: 14px 40px;
    }
    .nav-search {
      width: 100%;
      background: rgba(180,215,160,0.55);
      border: none;
      border-radius: 999px;
      padding: 7px 18px;
      font-family: 'Inter', sans-serif;
      font-style: italic;
      font-size: 0.82rem;
      color: #333;
      outline: none;
    }
    .nav-search::placeholder { color: #5a7a4a; }
    .nav-subnav {
      display: flex;
      gap: 32px;
      list-style: none;
      padding-left: 14px;
    }
    .nav-subnav a {
      font-style: italic;
      font-weight: 600;
      font-size: 0.72rem;
      color: #fff;
      text-decoration: none;
    }

    /* Avatar col */
    .nav-avatar-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 28px;
    }
    .nav-avatar {
      width: 54px;
      height: 54px;
      border-radius: 50%;
      overflow: hidden;
      background: #c2d9ad;
    }
    .nav-avatar img { width: 100%; height: 100%; object-fit: cover; }

    /* ═══════════════ PAGE ═══════════════ */
    .page {
      width: 100%;
      padding: 36px 48px 40px;
      max-width: none;
    }

    .greeting {
      font-family: 'Syne', sans-serif;
      font-style: italic;
      font-weight: 800;
      font-size: 2rem;
      margin-bottom: 40px;
    }

    /* ═══════════════ SECTION ═══════════════ */
    .section { margin-bottom: 44px; }

    .section-title {
      font-family: 'Syne', sans-serif;
      font-style: italic;
      font-weight: 800;
      font-size: 1.4rem;
      margin-bottom: 8px;
    }

    .section-line {
      border: none;
      border-top: 1.5px solid #ccc;
      margin-bottom: 24px;
    }

    /* ═══════════════ CAROUSEL ROW ═══════════════ */
    .row {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .items {
      display: flex;
      gap: 20px;
      flex: 1;
    }

    .arrow {
      font-size: 2rem;
      font-weight: 900;
      cursor: pointer;
      color: #111;
      flex-shrink: 0;
      user-select: none;
      line-height: 1;
    }

    /* ═══════════════ CIRCLES ═══════════════ */
    .circle-card {
      flex: 1;
      min-width: 0;
    }
    .circle-img {
      width: 100%;
      aspect-ratio: 1/1;
      border-radius: 50%;
      overflow: hidden;
      background: #d9d9d9;
    }
    .circle-img img { width: 100%; height: 100%; object-fit: cover; display: block; }

    /* ═══════════════ SQUARES ═══════════════ */
    .square-card {
      flex: 1;
      min-width: 0;
    }
    .square-img {
      width: 100%;
      aspect-ratio: 1/1;
      border-radius: 16px;
      overflow: hidden;
      background: #d9d9d9;
    }
    .square-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
  </style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <a class="nav-logo" href="#">
    <svg viewBox="0 0 42 42" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M5 8 Q2 4 5 2" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
      <path d="M6 8h5l5 15h15l4-10H14" stroke="white" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="18" cy="31" r="2.8" fill="white"/>
      <circle cx="29" cy="31" r="2.8" fill="white"/>
    </svg>
    <span>Market Plier</span>
  </a>

  <div class="nav-sep"></div>

  <div class="nav-center">
    <input class="nav-search" type="text" placeholder="Rechercher" />
    <ul class="nav-subnav">
      <li><a href="#">vendre</a></li>
      <li><a href="#">lange</a></li>
      <li><a href="#">aide</a></li>
    </ul>
  </div>

  <div class="nav-sep"></div>

  <div class="nav-avatar-wrap">
    <div class="nav-avatar">
      <img src="https://picsum.photos/seed/avatar1/120/120" alt="profil" />
    </div>
  </div>
</nav>

<!-- ── PAGE ── -->
<div class="page">

  <h1 class="greeting">Bonjour, Gaspmi</h1>

  <!-- Articles tendances -->
  <div class="section">
    <h2 class="section-title">Articles tendances</h2>
    <hr class="section-line" />
    <div class="row">
      <div class="items">
        <div class="circle-card"><div class="circle-img"><img src="https://picsum.photos/seed/t1/400/400" alt="" /></div></div>
        <div class="circle-card"><div class="circle-img"><img src="https://picsum.photos/seed/t2/400/400" alt="" /></div></div>
        <div class="circle-card"><div class="circle-img"><img src="https://picsum.photos/seed/t3/400/400" alt="" /></div></div>
        <div class="circle-card"><div class="circle-img"><img src="https://picsum.photos/seed/t4/400/400" alt="" /></div></div>
      </div>
      <span class="arrow">›</span>
    </div>
  </div>

  <!-- Achats consultés -->
  <div class="section">
    <h2 class="section-title">Achats consulté dernierement</h2>
    <hr class="section-line" />
    <div class="row">
      <div class="items">
        <div class="square-card"><div class="square-img"><img src="https://picsum.photos/seed/p1/400/400" alt="" /></div></div>
        <div class="square-card"><div class="square-img"><img src="https://picsum.photos/seed/p2/400/400" alt="" /></div></div>
        <div class="square-card"><div class="square-img"><img src="https://picsum.photos/seed/p3/400/400" alt="" /></div></div>
        <div class="square-card"><div class="square-img"><img src="https://picsum.photos/seed/p4/400/400" alt="" /></div></div>
      </div>
      <span class="arrow">›</span>
    </div>
  </div>

</div>

</body>
</html>