<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

if (!isset($_SESSION['auth_token'])) {
  header('Location: ../inscription-connexion/login.php');
  exit();
}

$myToken = $_SESSION['auth_token'];

$stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
$stmt->execute([$myToken]);
$user = $stmt->fetch();

if (!$user) {
  session_destroy();
  header('Location: ../index.php');
  exit();
}

// Récupérer les notifications
$stmt = $pdo->prepare("
    SELECT id, type, title, content, link, is_read, created_at
    FROM notifications
    WHERE auth_token = ?
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute([$myToken]);
$notifications = $stmt->fetchAll();

$unreadCount = 0;
foreach ($notifications as $n) {
  if (!$n['is_read']) $unreadCount++;
}

// Marquer toutes comme lues
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE auth_token = ? AND is_read = 0")->execute([$myToken]);

$typeIcons = [
  'message'  => 'fa-envelope',
  'review'   => 'fa-star',
  'favorite' => 'fa-heart',
  'system'   => 'fa-bell',
];
$typeColors = [
  'message'  => '#3b82f6',
  'review'   => '#f59e0b',
  'favorite' => '#ef4444',
  'system'   => '#7fb885',
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title>Notifications — Market Plier</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/notifications.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $user;
  include '../header.php';
  ?>

  <main class="notif-main">
    <div class="notif-container">
      <div class="notif-header">
        <h1 class="notif-title"><i class="fa-solid fa-bell"></i> Notifications</h1>
        <?php if ($unreadCount > 0): ?>
          <span class="notif-unread-badge"><?= $unreadCount ?> nouvelle<?= $unreadCount > 1 ? 's' : '' ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($notifications)): ?>
        <div class="notif-empty">
          <i class="fa-regular fa-bell-slash"></i>
          <p>Aucune notification.</p>
          <span>Vos notifications apparaîtront ici.</span>
        </div>
      <?php else: ?>
        <div class="notif-list">
          <?php foreach ($notifications as $notif):
            $icon = $typeIcons[$notif['type']] ?? 'fa-bell';
            $color = $typeColors[$notif['type']] ?? '#7fb885';
            $isNew = !$notif['is_read'];
            $timeAgo = time() - strtotime($notif['created_at']);
            if ($timeAgo < 60) $timeLabel = 'À l\'instant';
            elseif ($timeAgo < 3600) $timeLabel = floor($timeAgo / 60) . ' min';
            elseif ($timeAgo < 86400) $timeLabel = floor($timeAgo / 3600) . ' h';
            elseif ($timeAgo < 604800) $timeLabel = floor($timeAgo / 86400) . ' j';
            else $timeLabel = date('d/m/Y', strtotime($notif['created_at']));
          ?>
            <?php if ($notif['link']): ?>
              <a href="../<?= htmlspecialchars($notif['link'], ENT_QUOTES, 'UTF-8') ?>" class="notif-item <?= $isNew ? 'notif-new' : '' ?>">
              <?php else: ?>
                <div class="notif-item <?= $isNew ? 'notif-new' : '' ?>">
                <?php endif; ?>
                <div class="notif-icon" style="background: <?= $color ?>20; color: <?= $color ?>">
                  <i class="fa-solid <?= $icon ?>"></i>
                </div>
                <div class="notif-body">
                  <div class="notif-item-title"><?= htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if ($notif['content']): ?>
                    <div class="notif-item-content"><?= htmlspecialchars($notif['content'], ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                  <div class="notif-item-time"><?= $timeLabel ?></div>
                </div>
                <?php if ($isNew): ?>
                  <span class="notif-dot"></span>
                <?php endif; ?>
                <?php if ($notif['link']): ?>
              </a>
            <?php else: ?>
        </div>
      <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <script src="../styles/theme.js"></script>
</body>

</html>
