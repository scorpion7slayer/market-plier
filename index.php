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
  
  <!-- Bootstrap CSS (local) -->
  <link href="node_modules/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome (local) -->
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css" />
  <!-- Custom CSS -->
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
</head>

<body class="bg-white">

  <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
  <header class="header-custom">
    <div class="container-fluid px-3">
      <div class="header-top">
        <div class="logo-area">
          <div class="logo-icon">
            <img src="assets/images/logo.svg" alt="Market Plier Logo">
          </div>
        </div>
        <div class="header-divider"></div>
        <input class="search-bar form-control" type="text" placeholder="Rechercher" />
        <div class="header-divider"></div>
        <?php
        $profilePhoto = isset($user['profile_photo']) ? $user['profile_photo'] : null;
        if ($profilePhoto && file_exists(__DIR__ . '/uploads/profiles/' . $profilePhoto)):
        ?>
          <a class="profile-photo-container" href="inscription-connexion/account.php">
            <img src="uploads/profiles/<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>"
              alt="Photo de profil"
              class="profile-photo rounded-circle">
          </a>
        <?php else: ?>
          <a class="profile-photo-container" href="<?= isset($_SESSION['auth_token']) ? 'inscription-connexion/account.php' : 'inscription-connexion/register.php' ?>"></a>
        <?php endif; ?>
      </div>

      <div class="header-bottom">
        <nav class="nav">
          <a href="#" class="nav-link">vendre</a>
          <a href="#" class="nav-link">langue</a>
          <a href="#" class="nav-link">aide</a>
        </nav>
      </div>
    </div>
  </header>

  <!-- ═══ MAIN ═══════════════════════════════════════════════ -->
  <main class="container-fluid px-3">
    <div class="greeting">Bonjour, <?php echo htmlspecialchars($user['username'] ?? ''); ?></div>

    <!-- Trending section -->
    <section>
      <div class="section-title">Articles tendances</div>
      <div class="cards-row">
        <div class="trending-cards d-flex gap-3">
          <div class="circle-card flex-shrink-0"><img src="https://picsum.photos/seed/t1/200/200" alt=""></div>
          <div class="circle-card flex-shrink-0"><img src="https://picsum.photos/seed/t2/200/200" alt=""></div>
          <div class="circle-card flex-shrink-0"><img src="https://picsum.photos/seed/t3/200/200" alt=""></div>
          <div class="circle-card flex-shrink-0"><img src="https://picsum.photos/seed/t4/200/200" alt=""></div>
        </div>
        <i class="fa-solid fa-caret-right chevron"></i>
      </div>
    </section>

    <!-- Recently viewed section -->
    <section>
      <div class="section-title">Achats consulté dernierement</div>
      <div class="cards-row">
        <div class="recent-cards d-flex gap-3">
          <div class="rect-card flex-shrink-0"><img src="https://picsum.photos/seed/r1/200/160" alt=""></div>
          <div class="rect-card flex-shrink-0"><img src="https://picsum.photos/seed/r2/200/160" alt=""></div>
          <div class="rect-card flex-shrink-0"><img src="https://picsum.photos/seed/r3/200/160" alt=""></div>
          <div class="rect-card flex-shrink-0"><img src="https://picsum.photos/seed/r4/200/160" alt=""></div>
        </div>
        <i class="fa-solid fa-caret-right chevron"></i>
      </div>
    </section>
  </main>

  <!-- Bootstrap JS (local) -->
  <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
