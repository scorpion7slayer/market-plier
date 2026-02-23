<?php
session_start();
require_once 'database/db.php';

// Génération token CSRF si absent
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['auth_token'])) {
  $stmt = $pdo->prepare("SELECT auth_token, username, email, profile_photo FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $user = $stmt->fetch();
}
// Vérifier si l'utilisateur est admin
$user = $user ?? null;
$isAdmin = false;
if ($user) {
  try {
    $checkAdmin = $pdo->prepare("SELECT is_admin FROM users WHERE auth_token = ?");
    $checkAdmin->execute([$_SESSION['auth_token']]);
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
  <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
</head>

<body>

  <?php
  $headerBasePath = '';
  $headerUser = $user;
  include 'header.php';
  ?>

  <!-- ═══ MAIN ══════════════════════════════════════════════════ -->
  <main>
    <div class="greeting">Bonjour, <?php echo htmlspecialchars($user['username'] ?? ''); ?></div>

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
        <i class="fa-solid fa-caret-right chevron"></i>
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
        <i class="fa-solid fa-caret-right chevron"></i>
      </div>
    </section>
  </main>

</body>

</html>