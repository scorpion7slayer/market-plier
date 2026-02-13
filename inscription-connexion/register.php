<!-- TODO si adresse e-mail existe déjà dans la base de données et que l'adresse mail google correspond s'incroniser les 2 ou demander d'utiliser google pour se connecter car adresse e-mail inscrite via google -->
<?php
session_start();
if (isset($_SESSION['auth_token'])) {
  header('Location: dashboard.php');
  exit();
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../styles/register.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - S'inscrire</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="" style="width: 120%; height: auto;">
  </div>

  <div class="container">

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
          <input
            type="password"
            class="email-input"
            name="password"
            id="password"
            placeholder="mot de passe"
            required>

          <input
            type="password"
            class="email-input"
            name="confirm_password"
            id="confirm_password"
            placeholder="confirmer le mot de passe"
            required>
        </div>

        <button type="submit" class="submit-btn">
          S'inscrire
        </button>

        <div class="divider">
          <span class="divider-text">ou</span>
        </div>

        <!-- Bouton officiel Google -->
        <div id="g_id_onload"
          data-client_id="194449123581-g833377olkfj16lqhjlnemvt4u6106vk.apps.googleusercontent.com"
          data-callback="gestionnaireReponse"
          data-auto_prompt="false">
        </div>
        <div class="g_id_signin"
          data-type="standard"
          data-theme="outline"
          data-text="signup_with"
          data-size="large"
          data-shape="pill"
          data-logo_alignment="left">
        </div>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
        <script>
          function gestionnaireReponse(response) {
            window.location.href = 'google_login.php?id_token=' + encodeURIComponent(response.credential);
          }
        </script>
      </form>

      <p class="auth-link">Vous avez déjà un compte ? <a href="login.php">Se connecter</a></p>
    </main>
  </div>
</body>

</html>