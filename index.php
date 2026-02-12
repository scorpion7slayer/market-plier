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
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Ubuntu:ital,wght@1,700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html, body {
      width: 100%;
      height: 100%;
      overflow: hidden;
      font-family: 'Ubuntu', Arial, sans-serif;
      background: #fff;
    }

    /* ─── HEADER ─────────────────────────────────────────────── */
    header {
      background-color: #8faf72;
      display: flex;
      flex-direction: column;
      justify-content: center;
      height: 100px;
      padding: 0 18px;
      border-bottom: 3px solid #4a90a4;
      gap: 0;
    }

    /* Top row: logo | divider | searchbar | divider | avatar */
    .header-top {
      display: flex;
      align-items: center;
      gap: 0;
      width: 100%;
    }

    /* Bottom row: nav links */
    .header-bottom {
      display: flex;
      align-items: center;
      padding-left: 320px; /* align under searchbar - adjusted */
      margin-top: 6px;
    }

    /* Logo zone - ÉLARGI */
    .logo-area {
      display: flex;
      align-items: center;
      gap: 6px;
      width: 290px; /* augmenté de 210px à 290px */
      flex-shrink: 0;
    }

    .logo-icon {
      width: 100%; /* prend toute la largeur disponible */
      height: 48px;
      flex-shrink: 0;
    }

    .logo-icon img {
      width: 100%;
      height: 100%;
      object-fit: contain; /* garde les proportions de l'image */
    }

    .logo-text {
      font-family: 'Ubuntu', sans-serif;
      font-style: italic;
      font-weight: 700;
      font-size: 28px;
      color: #fff;
      line-height: 1;
      white-space: nowrap;
      letter-spacing: -0.5px;
      display: none; /* caché car on utilise une image */
    }

    /* Divider */
    .header-divider {
      width: 2px;
      height: 60px;
      background: #aec89a;
      margin: 0 14px;
      flex-shrink: 0;
    }

    .search-bar {
      background: #ddecd4;
      border: 1.5px solid #b5d4a0;
      border-radius: 30px;
      height: 34px;
      flex: 1;
      max-width: 400px; /* limite la largeur pour compenser le logo plus large */
      padding: 0 14px;
      font-size: 15px;
      font-style: italic;
      color: #555;
      outline: none;
    }

    .search-bar::placeholder {
      color: #888;
      font-style: italic;
    }

    nav {
      display: flex;
      gap: 40px;
    }

    nav a {
      font-family: 'Ubuntu', sans-serif;
      font-style: italic;
      font-weight: 700;
      font-size: 12px;
      color: #fff;
      text-decoration: none;
      letter-spacing: 0.3px;
    }

    /* Avatar - AGRANDI */
    .avatar {
      width: 80px; /* augmenté de 64px à 80px */
      height: 80px; /* augmenté de 64px à 80px */
      border-radius: 50%;
      background: #c8c8c8;
      flex-shrink: 0;
      overflow: hidden;
    }

    .avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ─── MAIN ────────────────────────────────────────────────── */
    main {
      /* Fill remaining height after header (100px) */
      height: calc(100vh - 100px);
      padding: 20px 30px 10px 30px;
      display: flex;
      flex-direction: column;
      gap: 0;
      overflow: hidden;
    }

    .greeting {
      font-family: 'Ubuntu', sans-serif;
      font-style: italic;
      font-weight: 700;
      font-size: 30px;
      color: #111;
      margin-bottom: 30px;
      flex-shrink: 0;
    }

    /* ─── SECTION ─────────────────────────────────────────────── */
    section {
      flex: 1;
      min-height: 0;
      display: flex;
      flex-direction: column;
    }

    section + section {
      margin-top: 28px;
    }

    .section-title {
      font-family: 'Ubuntu', sans-serif;
      font-style: italic;
      font-weight: 700;
      font-size: 22px;
      color: #111;
      padding-bottom: 8px;
      border-bottom: 1.5px solid #ccc;
      margin-bottom: 14px;
    }

    /* ─── CARDS ROW ───────────────────────────────────────────── */
    .cards-row {
      display: flex;
      align-items: center;
      gap: 22px;
      flex: 1;
      min-height: 0;
    }

    .chevron {
      font-size: 36px;
      font-weight: 900;
      color: #111;
      cursor: pointer;
      user-select: none;
      flex-shrink: 0;
      line-height: 1;
    }

    /* ─── TRENDING: circles ───────────────────────────────────── */
    .trending-cards {
      display: flex;
      gap: 22px;
      flex: 1;
    }

    .circle-card {
      flex: 1;
      aspect-ratio: 1;
      border-radius: 50%;
      background: #d4d4d4;
      overflow: hidden;
      max-height: 170px;
      max-width: 170px;
    }

    .circle-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    /* ─── RECENTLY VIEWED: rounded rectangles ─────────────────── */
    .recent-cards {
      display: flex;
      gap: 18px;
      flex: 1;
      align-items: stretch;
    }

    .rect-card {
      flex: 1;
      border-radius: 14px;
      background: #d4d4d4;
      overflow: hidden;
      height: 150px;
      min-height: 120px;
    }

    .rect-card img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 14px;
    }
  </style>
</head>
<body>

  <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
  <header>
    <!-- Top row: logo | divider | search | divider | avatar -->
    <div class="header-top">
      <div class="logo-area">
        <div class="logo-icon">
          <!-- Remplace cette URL par ton image de logo -->
          <img src="https://via.placeholder.com/290x48/8faf72/ffffff?text=Market+Plier" alt="Market Plier Logo">
        </div>
      </div>
      <div class="header-divider"></div>
      <input class="search-bar" type="text" placeholder="Rechercher" />
      <div class="header-divider"></div>
      <div class="avatar">
        <!-- Remplace cette URL par ta photo de profil -->
        <img src="https://picsum.photos/seed/profile/80/80" alt="Photo de profil">
      </div>
    </div>

    <!-- Bottom row: nav links -->
    <div class="header-bottom">
      <nav>
        <a href="#">vendre</a>
        <a href="#">lange</a>
        <a href="#">aide</a>
      </nav>
    </div>
  </header>

  <!-- ═══ MAIN ══════════════════════════════════════════════════ -->
  <main>
    <div class="greeting">Bonjour, Gaspmi</div>

    <!-- Trending section -->
    <section>
      <div class="section-title">Articles tendances</div>
      <div class="cards-row">
        <div class="trending-cards">
          <div class="circle-card"><img src="https://picsum.photos/seed/t1/200/200" alt=""></div>
          <div class="circle-card"><img src="https://picsum.photos/seed/t2/200/200" alt=""></div>
          <div class="circle-card"><img src="https://picsum.photos/seed/t3/200/200" alt=""></div>
          <div class="circle-card"><img src="https://picsum.photos/seed/t4/200/200" alt=""></div>
        </div>
        <span class="chevron">&#62;</span>
      </div>
    </section>

    <!-- Recently viewed section -->
    <section>
      <div class="section-title">Achats consulté dernierement</div>
      <div class="cards-row">
        <div class="recent-cards">
          <div class="rect-card"><img src="https://picsum.photos/seed/r1/200/160" alt=""></div>
          <div class="rect-card"><img src="https://picsum.photos/seed/r2/200/160" alt=""></div>
          <div class="rect-card"><img src="https://picsum.photos/seed/r3/200/160" alt=""></div>
          <div class="rect-card"><img src="https://picsum.photos/seed/r4/200/160" alt=""></div>
        </div>
        <span class="chevron">&#62;</span>
      </div>
    </section>
  </main>

</body>
</html>
