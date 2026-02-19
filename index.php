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
  
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-ENjdO4Dr2bkBIFxQpeoYz1FQ5jJZTVq1VrB5zR0xzZ5Jz5B5u5t5U5U5U5U5U5U5" crossorigin="anonymous">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css" />
  <!-- Custom CSS -->
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
</head>

<body class="bg-light">

  <!-- ═══ HEADER ═══════════════════════════════════════════════ -->
  <header class="bg-success py-3 sticky-top shadow-sm">
    <div class="container-fluid px-3">
      <!-- Top row: logo | search | avatar -->
      <div class="row align-items-center g-2">
        <!-- Logo -->
        <div class="col-4 col-md-3 col-lg-2">
          <div class="logo-icon">
            <img src="assets/images/logo.svg" alt="Market Plier Logo" class="img-fluid">
          </div>
        </div>
        
        <!-- Search bar -->
        <div class="col-4 col-md-6 col-lg-7">
          <input class="form-control search-bar" type="text" placeholder="Rechercher" />
        </div>
        
        <!-- Profile avatar -->
        <div class="col-4 col-md-3 col-lg-3 text-end">
          <?php
          $profilePhoto = isset($user['profile_photo']) ? $user['profile_photo'] : null;
          if ($profilePhoto && file_exists(__DIR__ . '/uploads/profiles/' . $profilePhoto)):
          ?>
            <a href="inscription-connexion/account.php">
              <img src="uploads/profiles/<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>"
                alt="Photo de profil"
                class="profile-photo rounded-circle">
            </a>
          <?php else: ?>
            <a href="<?= isset($_SESSION['auth_token']) ? 'inscription-connexion/account.php' : 'inscription-connexion/register.php' ?>" class="profile-photo-placeholder rounded-circle d-inline-flex align-items-center justify-content-center">
              <i class="fas fa-user text-white"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <!-- Bottom row: nav links -->
      <div class="row mt-2 mt-md-1">
        <div class="col-12">
          <nav class="nav justify-content-start gap-3 gap-md-4">
            <a href="#" class="nav-link text-white fw-bold fst-italic py-0">vendre</a>
            <a href="#" class="nav-link text-white fw-bold fst-italic py-0">langue</a>
            <a href="#" class="nav-link text-white fw-bold fst-italic py-0">aide</a>
          </nav>
        </div>
      </div>
    </div>
  </header>

  <!-- ═══ MAIN ═══════════════════════════════════════════════ -->
  <main class="container py-3 py-md-4">
    <!-- Greeting -->
    <div class="greeting mb-4">
      Bonjour, <?php echo htmlspecialchars($user['username'] ?? ''); ?>
    </div>

    <!-- Trending section -->
    <section class="mb-4 mb-md-5">
      <div class="section-title mb-3">Articles tendances</div>
      <div class="row align-items-center g-2">
        <div class="col-11">
          <div class="d-flex gap-2 gap-md-3 overflow-auto pb-2 trending-cards">
            <div class="flex-shrink-0 circle-card">
              <img src="https://picsum.photos/seed/t1/200/200" alt="" class="img-fluid rounded-circle">
            </div>
            <div class="flex-shrink-0 circle-card">
              <img src="https://picsum.photos/seed/t2/200/200" alt="" class="img-fluid rounded-circle">
            </div>
            <div class="flex-shrink-0 circle-card">
              <img src="https://picsum.photos/seed/t3/200/200" alt="" class="img-fluid rounded-circle">
            </div>
            <div class="flex-shrink-0 circle-card">
              <img src="https://picsum.photos/seed/t4/200/200" alt="" class="img-fluid rounded-circle">
            </div>
          </div>
        </div>
        <div class="col-1 text-end">
          <i class="fa-solid fa-caret-right chevron"></i>
        </div>
      </div>
    </section>

    <!-- Recently viewed section -->
    <section>
      <div class="section-title mb-3">Achats consulté dernierement</div>
      <div class="row align-items-center g-2">
        <div class="col-11">
          <div class="d-flex gap-2 gap-md-3 overflow-auto pb-2 recent-cards">
            <div class="flex-shrink-0 rect-card">
              <img src="https://picsum.photos/seed/r1/200/160" alt="" class="img-fluid rounded-3">
            </div>
            <div class="flex-shrink-0 rect-card">
              <img src="https://picsum.photos/seed/r2/200/160" alt="" class="img-fluid rounded-3">
            </div>
            <div class="flex-shrink-0 rect-card">
              <img src="https://picsum.photos/seed/r3/200/160" alt="" class="img-fluid rounded-3">
            </div>
            <div class="flex-shrink-0 rect-card">
              <img src="https://picsum.photos/seed/r4/200/160" alt="" class="img-fluid rounded-3">
            </div>
          </div>
        </div>
        <div class="col-1 text-end">
          <i class="fa-solid fa-caret-right chevron"></i>
        </div>
      </div>
    </section>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-ENjdO4Dr2bkBIFxQpeoYz1FQ5jJZTVq1VrB5zR0xzZ5Jz5B5u5t5U5U5U5U5U5U5" crossorigin="anonymous"></script>
</body>

</html>
