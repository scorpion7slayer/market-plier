<?php
session_start();
if (!isset($_SESSION['auth_token'])) {
  header('Location: login.php');
  exit();
}

try {
  require_once '../database/db.php';
} catch (PDOException $e) {
  error_log("DB connection error (dashboard): " . $e->getMessage());
}

// Générer un token CSRF si nécessaire
if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$isAdmin = false;
$username = $_SESSION['username'] ?? '';
$email = '';
$profilePhoto = null;
$successMessage = '';
$errorMessage = '';

// Récupérer les informations utilisateur
$authProvider = 'local';
$hasPassword = false;
if (isset($pdo)) {
  try {
    $stmt = $pdo->prepare("SELECT username, email, is_admin, profile_photo, auth_provider, password_hash FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $userData = $stmt->fetch();

    if ($userData) {
      $username = $userData['username'];
      $email = $userData['email'];
      $isAdmin = ($userData['is_admin'] == 1);
      $profilePhoto = $userData['profile_photo'];
      $authProvider = $userData['auth_provider'] ?? 'local';
      $hasPassword = !empty($userData['password_hash']);
    }
  } catch (PDOException $ex) {
    error_log("Error fetching user data: " . $ex->getMessage());
  }
}

// Traitement de l'upload de photo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changer_photo'])) {
  // Vérification CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
      $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
      $maxSize = 5 * 1024 * 1024; // 5MB

      $fileType = $_FILES['photo']['type'];
      $fileSize = $_FILES['photo']['size'];

      if (!in_array($fileType, $allowedTypes)) {
        $errorMessage = "Format de fichier non autorisé. Utilisez JPG, PNG ou WEBP.";
      } elseif ($fileSize > $maxSize) {
        $errorMessage = "Le fichier est trop volumineux (max 5MB).";
      } else {
        // Créer le dossier uploads/profiles s'il n'existe pas
        $uploadDir = '../uploads/profiles/';
        if (!is_dir($uploadDir)) {
          mkdir($uploadDir, 0755, true);
        }

        // Générer un nom de fichier unique
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $newFileName = 'user_' . $_SESSION['auth_token'] . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . $newFileName;

        // Supprimer l'ancienne photo si elle existe
        if ($profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto)) {
          unlink('../uploads/profiles/' . $profilePhoto);
        }

        // Déplacer le fichier uploadé
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
          try {
            $updateStmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE auth_token = ?");
            $updateStmt->execute([$newFileName, $_SESSION['auth_token']]);
            $profilePhoto = $newFileName;
            $successMessage = "Photo de profil mise à jour avec succès !";
          } catch (PDOException $ex) {
            $errorMessage = "Erreur lors de la mise à jour de la base de données.";
            error_log("Error updating profile photo: " . $ex->getMessage());
          }
        } else {
          $errorMessage = "Erreur lors de l'upload du fichier.";
        }
      }
    } else {
      $errorMessage = "Aucun fichier sélectionné ou erreur d'upload.";
    }

    // Régénérer le token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
}

// Traitement de la mise à jour du profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  // Vérification CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $newUsername = trim($_POST['username'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');

    // Validation
    if (empty($newUsername) || empty($newEmail)) {
      $errorMessage = "Tous les champs sont requis.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $newUsername)) {
      $errorMessage = "Le nom d'utilisateur doit contenir entre 3 et 30 caractères alphanumériques.";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
      $errorMessage = "Adresse email invalide.";
    } else {
      try {
        // Vérifier si le username ou email existe déjà (sauf pour l'utilisateur actuel)
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND auth_token != ?");
        $checkStmt->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);

        if ($checkStmt->fetch()) {
          $errorMessage = "Ce nom d'utilisateur ou email est déjà utilisé.";
        } else {
          $updateStmt = $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE auth_token = ?");
          $updateStmt->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);

          $username = $newUsername;
          $email = $newEmail;
          $_SESSION['username'] = $newUsername;
          $successMessage = "Profil mis à jour avec succès !";
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors de la mise à jour du profil.";
        error_log("Error updating profile: " . $ex->getMessage());
      }
    }

    // Régénérer le token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
}

// Traitement du changement de mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
  // Vérification CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($currentPassword) && $hasPassword) {
      $errorMessage = "Veuillez entrer votre mot de passe actuel.";
    } elseif (empty($newPassword) || empty($confirmPassword)) {
      $errorMessage = "Veuillez remplir tous les champs du nouveau mot de passe.";
    } elseif (strlen($newPassword) < 6) {
      $errorMessage = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
    } elseif ($newPassword !== $confirmPassword) {
      $errorMessage = "Les nouveaux mots de passe ne correspondent pas.";
    } else {
      try {
        // Vérifier le mot de passe actuel si l'utilisateur en a un
        if ($hasPassword) {
          $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE auth_token = ?");
          $pwdStmt->execute([$_SESSION['auth_token']]);
          $pwdData = $pwdStmt->fetch();

          if (!$pwdData || !password_verify($currentPassword, $pwdData['password_hash'])) {
            $errorMessage = "Le mot de passe actuel est incorrect.";
          }
        }

        if (empty($errorMessage)) {
          $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
          $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE auth_token = ?");
          $updateStmt->execute([$newPasswordHash, $_SESSION['auth_token']]);
          $hasPassword = true;
          $successMessage = "Mot de passe modifié avec succès !";
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors du changement de mot de passe.";
        error_log("Error changing password: " . $ex->getMessage());
      }
    }

    // Régénérer le token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
}

// Traitement de la suppression de compte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
  // Vérification CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errorMessage = "Token de sécurité invalide.";
  } else {
    $confirmEmail = trim($_POST['confirm_email'] ?? '');
    $confirmPassword = $_POST['confirm_delete_password'] ?? '';

    // Validation
    if (empty($confirmEmail)) {
      $errorMessage = "Veuillez entrer votre adresse email pour confirmer.";
    } elseif ($confirmEmail !== $email) {
      $errorMessage = "L'adresse email ne correspond pas.";
    } elseif ($hasPassword && empty($confirmPassword)) {
      $errorMessage = "Veuillez entrer votre mot de passe pour confirmer.";
    } else {
      try {
        // Vérifier le mot de passe si l'utilisateur en a un
        if ($hasPassword) {
          $pwdStmt = $pdo->prepare("SELECT password_hash FROM users WHERE auth_token = ?");
          $pwdStmt->execute([$_SESSION['auth_token']]);
          $pwdData = $pwdStmt->fetch();

          if (!$pwdData || !password_verify($confirmPassword, $pwdData['password_hash'])) {
            $errorMessage = "Le mot de passe est incorrect.";
          }
        }

        if (empty($errorMessage)) {
          // Supprimer la photo de profil si elle existe
          if ($profilePhoto && file_exists('../uploads/profiles/' . basename($profilePhoto))) {
            unlink('../uploads/profiles/' . basename($profilePhoto));
          }

          // Supprimer l'utilisateur de la base de données
          $deleteStmt = $pdo->prepare("DELETE FROM users WHERE auth_token = ?");
          $deleteStmt->execute([$_SESSION['auth_token']]);

          // Détruire la session
          session_destroy();

          // Rediriger vers la page d'accueil avec un message
          header('Location: ../index.php?account_deleted=1');
          exit();
        }
      } catch (PDOException $ex) {
        $errorMessage = "Erreur lors de la suppression du compte.";
        error_log("Error deleting account: " . $ex->getMessage());
      }
    }

    // Régénérer le token CSRF
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/dashboard.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - Mon Profil</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="Market Plier Logo" style="width: 80%; height: auto;">
  </div>

  <!-- Modal pour changer la photo -->
  <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="photoModalLabel">Changer ma photo de profil</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
              <label for="photo" class="form-label">Sélectionnez une photo (JPG, PNG, WEBP - max 5MB)</label>
              <input type="file" class="form-control" id="photo" name="photo" accept=".jpg,.jpeg,.png,.webp" required>
            </div>
            <div class="alert alert-info">
              <i class="fas fa-info-circle"></i> La photo sera redimensionnée automatiquement.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" name="changer_photo" class="btn btn-primary">
              <i class="fas fa-upload"></i> Enregistrer
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal pour supprimer le compte -->
  <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-danger">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title" id="deleteAccountModalLabel">
            <i class="fas fa-exclamation-triangle"></i> Supprimer mon compte
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="modal-body">
            <div class="alert alert-danger mb-3">
              <i class="fas fa-exclamation-circle"></i> <strong>Attention !</strong> Cette action est irréversible. Toutes vos données seront supprimées définitivement.
            </div>
            <p class="mb-3">Pour confirmer la suppression, veuillez entrer votre adresse email :</p>
            <div class="mb-3">
              <label for="confirm_email" class="form-label">Adresse email</label>
              <input type="email"
                class="form-control"
                id="confirm_email"
                name="confirm_email"
                placeholder="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                required>
            </div>
            <?php if ($hasPassword): ?>
              <div class="mb-3">
                <label for="confirm_delete_password" class="form-label">Mot de passe</label>
                <input type="password"
                  class="form-control"
                  id="confirm_delete_password"
                  name="confirm_delete_password"
                  required>
              </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" name="delete_account" class="btn btn-danger">
              <i class="fas fa-trash"></i> Supprimer définitivement
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">

        <!-- Messages de succès/erreur -->
        <?php if ($successMessage): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- Carte de profil -->
        <div class="card shadow-sm mb-4">
          <div class="card-body text-center py-5">
            <div class="profile-photo-container mb-4">
              <?php if ($profilePhoto && file_exists('../uploads/profiles/' . $profilePhoto)): ?>
                <img src="../uploads/profiles/<?php echo htmlspecialchars($profilePhoto, ENT_QUOTES, 'UTF-8'); ?>"
                  alt="Photo de profil"
                  class="profile-photo">
              <?php else: ?>
                <img src="../assets/images/default-account.svg"
                  alt="Photo de profil par défaut"
                  class="profile-photo">
              <?php endif; ?>
              <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-bs-toggle="modal" data-bs-target="#photoModal">
                <i class="fas fa-camera"></i> Changer la photo
              </button>
            </div>

            <h2 class="mb-2"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="text-muted mb-3">
              <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>
            </p>

            <?php if ($isAdmin): ?>
              <span class="badge bg-success">
                <i class="fas fa-shield-alt"></i> Administrateur
              </span>
            <?php endif; ?>
          </div>
        </div>

        <!-- Formulaire de modification du profil -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-user-cog"></i> Modifier mon profil</h5>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

              <div class="mb-3">
                <label for="username" class="form-label">Nom d'utilisateur</label>
                <input type="text"
                  class="form-control"
                  id="username"
                  name="username"
                  value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>"
                  pattern="[a-zA-Z0-9_]{3,30}"
                  required>
                <div class="form-text">3-30 caractères alphanumériques et underscore uniquement.</div>
              </div>

              <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email"
                  class="form-control"
                  id="email"
                  name="email"
                  value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
                  required>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" name="update_profile" class="btn btn-primary">
                  <i class="fas fa-save"></i> Enregistrer les modifications
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Changement de mot de passe -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fas fa-lock"></i> Sécurité</h5>
          </div>
          <div class="card-body">
            <?php if ($authProvider === 'google' && !$hasPassword): ?>
              <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle"></i> Vous êtes connecté via Google. Définissez un mot de passe pour pouvoir vous connecter également avec votre email.
              </div>
            <?php endif; ?>

            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

              <?php if ($hasPassword): ?>
                <div class="mb-3">
                  <label for="current_password" class="form-label">Mot de passe actuel</label>
                  <input type="password"
                    class="form-control"
                    id="current_password"
                    name="current_password"
                    required>
                </div>
              <?php endif; ?>

              <div class="mb-3">
                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                <input type="password"
                  class="form-control"
                  id="new_password"
                  name="new_password"
                  minlength="6"
                  required>
                <div class="form-text">Minimum 6 caractères.</div>
              </div>

              <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                <input type="password"
                  class="form-control"
                  id="confirm_password"
                  name="confirm_password"
                  minlength="6"
                  required>
              </div>

              <div class="d-grid gap-2">
                <button type="submit" name="change_password" class="btn btn-warning">
                  <i class="fas fa-key"></i> Changer le mot de passe
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Actions -->
        <div class="card shadow-sm">
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="../inscription-connexion/account.php" class="btn btn-outline-secondary">
                <i class="fas fa-home"></i> Retour au profile
              </a>
              <a href="logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i> Se déconnecter
              </a>
            </div>
          </div>
        </div>

        <!-- Zone de danger -->
        <div class="card shadow-sm mt-4 border-danger">
          <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-skull-crossbones"></i> Zone de danger</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-3">Une fois votre compte supprimé, toutes vos données seront effacées définitivement. Cette action est irréversible.</p>
            <button type="button" class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
              <i class="fas fa-trash"></i> Supprimer mon compte
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>