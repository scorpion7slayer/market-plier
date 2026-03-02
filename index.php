<?php
session_start();
require_once 'database/db.php';

// Générer un token CSRF uniquement s'il n'existe pas encore.
// Régénérer inconditionnellement écrasait le token des formulaires ouverts
// dans d'autres onglets ou pages (ex. sell.php), causant des erreurs CSRF.
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
<html lang="fr" data-bs-theme="light">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Market Plier</title>
  <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="node_modules/@fortawesome/fontawesome-free/css/all.min.css" />
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="stylesheet" href="styles/theme.css" />
  <link rel="icon" type="image/svg+xml" href="assets/images/logo.svg" />
</head>

<body>
  <!-- Header partagé -->
  <?php
  $headerBasePath = './';
  $headerUser = $user;
  include 'header.php';
  ?>
  <!-- Message de confirmation suppression compte -->
  <?php if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1'): ?>
    <div class="alert alert-success alert-dismissible fade show m-3" role="alert" style="background-color: #d4edda; color: #155724; padding: 1rem; border-radius: 0.5rem; border: 1px solid #c3e6cb;">
      <i class="fas fa-check-circle"></i> Votre compte a été supprimé avec succès. Merci d'avoir utilisé Market Plier.
    </div>
  <?php endif; ?>



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

<script src="styles/theme.js"></script>
</body>

</html>