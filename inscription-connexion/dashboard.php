<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit();
}

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (dashboard): " . $e->getMessage());
}

$isAdmin = false;
$username = $_SESSION['username'] ?? '';
if (isset($pdo)) {
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../styles/register.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - Tableau de bord</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="" style="width: 120%; height: auto;">
  </div>

  <div class="container">
    <main class="form-container">
      <h2 class="title">Bienvenue, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h2>

      <?php if ($isAdmin): ?>
        <p class="auth-link" style="margin-bottom: 20px;">Vous êtes administrateur.</p>
      <?php endif; ?>

      <a href="logout.php" class="submit-btn" style="display: block; text-align: center; text-decoration: none;">
        Se déconnecter
      </a>
    </main>
  </div>
</body>

</html>
