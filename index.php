<?php
session_start();
require_once 'database/db.php';
require_once 'includes/remember_me.php';

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

  // Compte supprimé par un admin : nettoyer la session
  if (!$user) {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header('Location: index.php?account_deleted=1');
    exit();
  }
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
  <!-- Message compte supprimé par admin -->
  <?php if (isset($_GET['account_deleted']) && $_GET['account_deleted'] === '1'): ?>
    <div style="max-width: 500px; margin: 40px auto; padding: 32px; background: var(--mp-card-bg, #fff); border-radius: 18px; box-shadow: 0 8px 32px var(--mp-card-shadow, rgba(0,0,0,0.08)); text-align: center; font-family: 'Archivo', sans-serif;">
      <div style="width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 50%; background: rgba(231, 76, 60, 0.1); display: flex; align-items: center; justify-content: center;">
        <i class="fa-solid fa-user-slash" style="font-size: 1.3rem; color: #e74c3c;"></i>
      </div>
      <h3 style="font-weight: 700; font-style: italic; font-size: 1.1rem; color: var(--mp-text, #111); margin-bottom: 8px;">Compte supprimé</h3>
      <p style="font-style: italic; font-size: 0.9rem; color: var(--mp-text-muted, #888); line-height: 1.5; margin-bottom: 0;">
        Votre compte a été supprimé par un administrateur.<br>Merci d'avoir utilisé Market Plier.
      </p>
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