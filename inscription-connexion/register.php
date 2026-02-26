<?php
session_start();
if (isset($_SESSION['auth_token'])) {
  header('Location: dashboard.php');
  exit();
}

// Générer un nouveau token CSRF à chaque affichage du formulaire
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../styles/register.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - S'inscrire</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="" style="width: 120%; height: auto;">
    <p class="auth-link" style="margin-top: 8px;"><a href="../index.php">← Retour à l'accueil</a></p>
  </div>

  <div class="register-container">

    <main class="form-container">
      <h2 class="title">S'inscrire</h2>

      <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>
      <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form class="signup-form" action="handle_register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

        <input
          type="text"
          class="email-input"
          name="username"
          id="username"
          placeholder="nom d'utilisateur"
          required>

        <input
          type="email"
          class="email-input"
          name="email"
          id="email"
          placeholder="adresse email"
          required>

        <div class="password-row">
          <div class="password-field">
            <input
              type="password"
              class="email-input"
              name="password"
              id="password"
              placeholder="mot de passe"
              required>
          </div>
          <div class="password-field">
            <input
              type="password"
              class="email-input"
              name="confirm_password"
              id="confirm_password"
              placeholder="confirmer le mot de passe"
              required>
          </div>
        </div>

        <button type="submit" class="submit-btn">
          S'inscrire
        </button>

        <div class="divider">
          <span class="divider-text">ou</span>
        </div>

        <a href="../google/google_login.php" class="btn-google">
          <img src="https://www.google.com/favicon.ico" alt="" width="18" height="18">
          S'inscrire avec Google
        </a>
      </form>

      <p class="auth-link">Vous avez déjà un compte ? <a href="login.php">Se connecter</a></p>
    </main>
  </div>
  <script src="../styles/form-validation.js"></script>
</body>

</html>