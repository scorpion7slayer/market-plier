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

// Toujours générer un nouveau token CSRF à chaque chargement de la page
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$isAdmin = false;
$username = $_SESSION['username'] ?? '';
$email = '';
$profilePhoto = null;
$successMessage = '';
$errorMessage = '';

// Récupérer les informations utilisateur
if (isset($pdo)) {
  try {
    $stmt = $pdo->prepare("SELECT username, email, is_admin, profile_photo FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $userData = $stmt->fetch();

    if ($userData) {
      $username = $userData['username'];
      $email = $userData['email'];
      $isAdmin = ($userData['is_admin'] == 1);
      $profilePhoto = $userData['profile_photo'];
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
        $checkStmt = $pdo->prepare("SELECT auth_token FROM users WHERE (username = ? OR email = ?) AND auth_token != ?");
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
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/settings.css">
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg" />
  <title>Market Plier - Mon Profil</title>
</head>

<body>
  <div class="logo">
    <img src="../assets/images/logo.svg" alt="Market Plier Logo" style="width: 80%; height: auto;">
  </div>



  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-lg-8">





        <!-- Formulaire de modification du profil -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white">
            <h5 class="mb-0"><i class="fa-solid fa-gear"></i>Paramètres du site</h5>
          </div>
          <div class="card-body">
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">



              <div class="mb-3">
                <label for="email" class="form-label">thème du site</label>
              </div>

              <div class="d-grid gap-2">
                <button id="theme-button" type="submit" name="update_profile" class="btn btn-primary">
                  <i class="fas fa-save"></i>
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

      </div>
    </div>
  </div>

  <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const html = document.documentElement;
    const toggleBtn = document.getElementById('theme-button');
    const storedTheme = localStorage.getItem('theme');

    // Appliquer thème initial
    if (storedTheme === 'dark') {
      html.setAttribute('data-bs-theme', 'dark');
      toggleBtn.innerHTML = '<svg class="bi theme-icon" width="20" height="20" fill="currentColor"><use href="#moon-icon"></use></svg>'; // Icône lune
    }

    // Toggle au clic
    toggleBtn.addEventListener('click', () => {
      const currentTheme = html.getAttribute('data-bs-theme');
      const newTheme = currentTheme === 'light' ? 'dark' : 'light';

      html.setAttribute('data-bs-theme', newTheme);
      localStorage.setItem('theme', newTheme);

      // Changer icône (optionnel)
      if (newTheme === 'dark') {
        toggleBtn.innerHTML = '<svg class="bi theme-icon" width="20" height="20" fill="currentColor"><use href="#moon-icon"></use></svg>';
      } else {
        toggleBtn.innerHTML = '<svg class="bi theme-icon" width="20" height="20" fill="currentColor"><use href="#sun-icon"></use></svg>';
      }
    });
  </script>
</body>

</html>