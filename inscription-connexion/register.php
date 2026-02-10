<?php
session_start();
if (isset($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit();
}

// Générer un token CSRF si non existant
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include '../header.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../styles/register.css">

  <title>Market Plier - S'inscrire</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="" style="width: 120%; height: auto;">
  </div>

  <div class="container">

    <main class="form-container">
      <h2 class="title">S'inscrire</h2>

      <form class="signup-form space-y-4" action="handle_register.php" method="POST">
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

        <button type="button" class="google-btn">
          S'inscrire avec google
        </button>
      </form>
    </main>
  </div>
</body>

</html>