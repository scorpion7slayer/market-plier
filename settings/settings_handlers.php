<?php

// --- Fonctions utilitaires ---

function destroySessionAndRedirect()
{
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    $cookieName = ini_get('session.name');
    setcookie($cookieName, '', [
      'expires' => time() - 42000,
      'path' => $params['path'],
      'domain' => $params['domain'],
      'secure' => $params['secure'],
      'httponly' => $params['httponly'],
      'samesite' => $params['samesite'] ?? 'Lax'
    ]);
  }
  session_destroy();
  header('Location: ../index.php?account_deleted=1');
  exit();
}

function fetchUserData($pdo)
{
  $stmt = $pdo->prepare("SELECT username, email, is_admin, profile_photo, auth_provider, password_hash FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  return $stmt->fetch();
}

function initPageState($pdo)
{
  $state = [
    'isAdmin' => false,
    'username' => $_SESSION['username'] ?? '',
    'email' => '',
    'profilePhoto' => null,
    'authProvider' => 'local',
    'hasPassword' => false,
    'successMessage' => '',
    'errorMessage' => '',
  ];

  if (isset($_GET['success']) && $_GET['success'] === 'photo') {
    $state['successMessage'] = "Photo de profil mise à jour !";
  }

  if (!isset($pdo)) return $state;

  try {
    $userData = fetchUserData($pdo);
    if (!$userData) {
      destroySessionAndRedirect();
    }
    $state['username'] = $userData['username'];
    $state['email'] = $userData['email'];
    $state['isAdmin'] = ($userData['is_admin'] == 1);
    $state['profilePhoto'] = $userData['profile_photo'];
    $state['authProvider'] = $userData['auth_provider'] ?? 'local';
    $state['hasPassword'] = !empty($userData['password_hash']);
  } catch (PDOException $ex) {
    error_log("Error fetching user data: " . $ex->getMessage());
  }

  return $state;
}

function validateSettingsCsrf()
{
  return isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token'];
}

// --- Helpers de validation ---

function validateUploadedPhoto($file)
{
  if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
    return "Aucun fichier sélectionné.";
  }
  $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!in_array($file['type'], $allowedTypes)) {
    return "Format non autorisé. Utilisez JPG, PNG ou WEBP.";
  }
  if ($file['size'] > 5 * 1024 * 1024) {
    return "Fichier trop volumineux (max 5MB).";
  }
  return null;
}

function saveProfilePhoto($pdo, $file, &$profilePhoto)
{
  $uploadDir = '../uploads/profiles/';
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
  $newFileName = 'user_' . $_SESSION['auth_token'] . '_' . time() . '.' . $extension;
  $uploadPath = $uploadDir . $newFileName;

  if ($profilePhoto && file_exists($uploadDir . $profilePhoto)) {
    unlink($uploadDir . $profilePhoto);
  }

  if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    return "Erreur lors de l'upload.";
  }

  $pdo->prepare("UPDATE users SET profile_photo = ? WHERE auth_token = ?")
    ->execute([$newFileName, $_SESSION['auth_token']]);
  $profilePhoto = $newFileName;
  return null;
}

function validateProfileInput()
{
  $username = trim($_POST['username'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if (empty($username) || empty($email)) {
    return [null, null, "Tous les champs sont requis."];
  }
  if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
    return [null, null, "Nom d'utilisateur : 3-30 caractères alphanumériques."];
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    return [null, null, "Adresse email invalide."];
  }
  return [$username, $email, null];
}

function validatePasswordInput($hasPassword)
{
  $currentPassword = $_POST['current_password'] ?? '';
  $newPassword = $_POST['new_password'] ?? '';
  $confirmPassword = $_POST['confirm_password'] ?? '';

  if (empty($currentPassword) && $hasPassword) {
    return [null, null, "Veuillez entrer votre mot de passe actuel."];
  }
  if (empty($newPassword) || empty($confirmPassword)) {
    return [null, null, "Veuillez remplir tous les champs."];
  }
  if (strlen($newPassword) < 6) {
    return [null, null, "Minimum 6 caractères pour le mot de passe."];
  }
  if ($newPassword !== $confirmPassword) {
    return [null, null, "Les mots de passe ne correspondent pas."];
  }
  return [$currentPassword, $newPassword, null];
}

function verifyCurrentPassword($pdo, $password)
{
  $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE auth_token = ?");
  $stmt->execute([$_SESSION['auth_token']]);
  $data = $stmt->fetch();
  return $data && password_verify($password, $data['password_hash']);
}

function validateDeleteInput($pdo, $email, $hasPassword)
{
  $confirmEmail = trim($_POST['confirm_email'] ?? '');
  $confirmPassword = $_POST['confirm_delete_password'] ?? '';

  if (empty($confirmEmail)) return "Entrez votre email pour confirmer.";
  if ($confirmEmail !== $email) return "L'email ne correspond pas.";
  if ($hasPassword && empty($confirmPassword)) return "Entrez votre mot de passe pour confirmer.";
  if ($hasPassword && !verifyCurrentPassword($pdo, $confirmPassword)) return "Mot de passe incorrect.";
  return null;
}

// --- Handlers ---

function handlePhotoUpload($pdo, array &$state)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['changer_photo'])) return;

  if (!validateSettingsCsrf()) {
    $state['errorMessage'] = "Token de sécurité invalide.";
    return;
  }

  $error = validateUploadedPhoto($_FILES['photo'] ?? null);
  if ($error) {
    $state['errorMessage'] = $error;
    return;
  }

  try {
    $error = saveProfilePhoto($pdo, $_FILES['photo'], $state['profilePhoto']);
    if ($error) {
      $state['errorMessage'] = $error;
      return;
    }
    $state['successMessage'] = "Photo de profil mise à jour !";
  } catch (PDOException $ex) {
    $state['errorMessage'] = "Erreur lors de la mise à jour.";
    error_log("Error updating profile photo: " . $ex->getMessage());
  }
}

function handleUpdateProfile($pdo, array &$state)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['update_profile'])) return;

  if (!validateSettingsCsrf()) {
    $state['errorMessage'] = "Token de sécurité invalide.";
    return;
  }

  [$newUsername, $newEmail, $error] = validateProfileInput();
  if ($error) {
    $state['errorMessage'] = $error;
    return;
  }

  try {
    $check = $pdo->prepare("SELECT auth_token FROM users WHERE (username = ? OR email = ?) AND auth_token != ?");
    $check->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);
    if ($check->fetch()) {
      $state['errorMessage'] = "Ce nom d'utilisateur ou email est déjà utilisé.";
      return;
    }
    $pdo->prepare("UPDATE users SET username = ?, email = ? WHERE auth_token = ?")
      ->execute([$newUsername, $newEmail, $_SESSION['auth_token']]);
    $state['username'] = $newUsername;
    $state['email'] = $newEmail;
    $_SESSION['username'] = $newUsername;
    $state['successMessage'] = "Profil mis à jour !";
  } catch (PDOException $ex) {
    $state['errorMessage'] = "Erreur lors de la mise à jour.";
    error_log("Error updating profile: " . $ex->getMessage());
  }
}

function handleChangePassword($pdo, array &$state)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['change_password'])) return;

  if (!validateSettingsCsrf()) {
    $state['errorMessage'] = "Token de sécurité invalide.";
    return;
  }

  [$currentPassword, $newPassword, $error] = validatePasswordInput($state['hasPassword']);
  if ($error) {
    $state['errorMessage'] = $error;
    return;
  }

  try {
    if ($state['hasPassword'] && !verifyCurrentPassword($pdo, $currentPassword)) {
      $state['errorMessage'] = "Mot de passe actuel incorrect.";
      return;
    }
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE auth_token = ?")
      ->execute([$newHash, $_SESSION['auth_token']]);
    $state['successMessage'] = "Mot de passe modifié !";
  } catch (PDOException $ex) {
    $state['errorMessage'] = "Erreur lors du changement de mot de passe.";
    error_log("Error changing password: " . $ex->getMessage());
  }
}

function handleDeleteAccount($pdo, array &$state)
{
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['delete_account'])) return;

  if (!validateSettingsCsrf()) {
    $state['errorMessage'] = "Token de sécurité invalide.";
    return;
  }

  $error = validateDeleteInput($pdo, $state['email'], $state['hasPassword']);
  if ($error) {
    $state['errorMessage'] = $error;
    return;
  }

  try {
    deleteUserData($pdo, $state['profilePhoto']);
  } catch (PDOException $ex) {
    error_log("Error deleting account: " . $ex->getMessage());
    $state['errorMessage'] = "Erreur lors de la suppression.";
  }
}

function deleteUserData($pdo, $profilePhoto)
{
  if ($profilePhoto && file_exists('../uploads/profiles/' . basename($profilePhoto))) {
    unlink('../uploads/profiles/' . basename($profilePhoto));
  }
  $pdo->prepare("DELETE FROM users WHERE auth_token = ?")->execute([$_SESSION['auth_token']]);
  session_destroy();
  header('Location: ../index.php?account_deleted=1');
  exit();
}
