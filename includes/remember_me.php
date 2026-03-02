<?php

/**
 * Remember Me - Auto-login via cookie persistant.
 * À inclure APRÈS session_start() et require db.php.
 *
 * Si l'utilisateur n'a pas de session active mais possède un cookie
 * "mp_remember", on vérifie le token en base et on restaure la session.
 */
if (!isset($_SESSION['auth_token']) && isset($_COOKIE['mp_remember'])) {
  $rememberToken = $_COOKIE['mp_remember'];

  // Valider le format (hex 64 chars)
  if (preg_match('/^[a-f0-9]{64}$/', $rememberToken)) {
    $tokenHash = hash('sha256', $rememberToken);

    $stmtRemember = $pdo->prepare(
      "SELECT auth_token, username FROM users WHERE remember_token = ?"
    );
    $stmtRemember->execute([$tokenHash]);
    $rememberUser = $stmtRemember->fetch();

    if ($rememberUser) {
      session_regenerate_id(true);
      $_SESSION['auth_token'] = $rememberUser['auth_token'];
      $_SESSION['username'] = $rememberUser['username'];
      if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
      }
    } else {
      // Token invalide : supprimer le cookie
      setcookie('mp_remember', '', time() - 3600, '/', '', false, true);
    }
  } else {
    // Format invalide : supprimer le cookie
    setcookie('mp_remember', '', time() - 3600, '/', '', false, true);
  }
}
