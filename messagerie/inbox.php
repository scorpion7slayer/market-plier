<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

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

// Récupérer toutes les conversations avec le dernier message et infos de l'autre utilisateur
$stmt = $pdo->prepare("
    SELECT c.id AS conversation_id, c.listing_id, c.updated_at,
           CASE WHEN c.user1_token = ? THEN c.user2_token ELSE c.user1_token END AS other_token,
           u.username AS other_username, u.profile_photo AS other_photo,
           l.title AS listing_title, l.image AS listing_image,
           m.content AS last_message, m.sender_token AS last_sender, m.created_at AS last_message_at,
           (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_token != ? AND is_read = 0) AS unread_count
    FROM conversations c
    JOIN users u ON u.auth_token = CASE WHEN c.user1_token = ? THEN c.user2_token ELSE c.user1_token END
    LEFT JOIN listings l ON l.id = c.listing_id
    LEFT JOIN messages m ON m.id = (
        SELECT id FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1
    )
    WHERE c.user1_token = ? OR c.user2_token = ?
    ORDER BY c.updated_at DESC
");
$stmt->execute([$myToken, $myToken, $myToken, $myToken, $myToken]);
$conversations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars(t('msg_inbox_title'), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
  <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../styles/index.css">
  <link rel="stylesheet" href="../styles/messagerie.css">
  <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
  <?php
  $headerBasePath = '../';
  $headerUser = $user;
  include '../header.php';
  ?>

  <main class="msg-main">
    <div class="msg-container">
      <div class="msg-header">
        <h1 class="msg-title"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars(t('msg_inbox_heading'), ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($conversations)): ?>
          <span class="msg-count"><?= count($conversations) ?> <?= htmlspecialchars(count($conversations) > 1 ? t('msg_count_plural') : t('msg_count_singular'), ENT_QUOTES, 'UTF-8') ?></span>
        <?php endif; ?>
      </div>

      <?php if (empty($conversations)): ?>
        <div class="msg-empty">
          <i class="fa-solid fa-comments"></i>
          <p><?= htmlspecialchars(t('msg_no_conversations'), ENT_QUOTES, 'UTF-8') ?></p>
          <span><?= htmlspecialchars(t('msg_no_conversations_sub'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php else: ?>
        <div class="msg-list">
          <?php foreach ($conversations as $conv): ?>
            <a href="conversation.php?id=<?= (int) $conv['conversation_id'] ?>" class="msg-item <?= $conv['unread_count'] > 0 ? 'msg-unread' : '' ?>">
              <div class="msg-avatar">
                <img src="../api/profile_photo.php?token=<?= urlencode($conv['other_token']) ?>" alt="">
              </div>
              <div class="msg-content">
                <div class="msg-top">
                  <span class="msg-username"><?= htmlspecialchars($conv['other_username'], ENT_QUOTES, 'UTF-8') ?></span>
                  <?php if ($conv['last_message_at']): ?>
                    <span class="msg-time" data-utc="<?= htmlspecialchars($conv['last_message_at'], ENT_QUOTES, 'UTF-8') ?>"></span>
                  <?php endif; ?>
                </div>
                <?php if ($conv['listing_title']): ?>
                  <div class="msg-listing-ref">
                    <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($conv['listing_title'], ENT_QUOTES, 'UTF-8') ?>
                  </div>
                <?php endif; ?>
                <div class="msg-preview">
                  <?php if ($conv['last_message']): ?>
                    <?php if ($conv['last_sender'] === $myToken): ?>
                      <span class="msg-you"><?= htmlspecialchars(t('msg_you'), ENT_QUOTES, 'UTF-8') ?> </span>
                    <?php endif; ?>
                    <?= htmlspecialchars(mb_strimwidth($conv['last_message'], 0, 80, '...'), ENT_QUOTES, 'UTF-8') ?>
                  <?php else: ?>
                    <em><?= htmlspecialchars(t('msg_new_conversation'), ENT_QUOTES, 'UTF-8') ?></em>
                  <?php endif; ?>
                </div>
              </div>
              <?php if ($conv['unread_count'] > 0): ?>
                <span class="msg-badge"><?= (int) $conv['unread_count'] ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <?php include '../footer.php'; ?>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var locale = <?= json_encode(getUserLocale()) ?>;
      var i18n = <?= json_encode([
                    'yesterday' => t('msg_yesterday'),
                  ]) ?>;

      // Convertir les timestamps UTC en heures locales
      function utcToLocal(utcStr) {
        return new Date(utcStr.replace(' ', 'T') + 'Z');
      }

      function formatInboxTime(date) {
        var now = new Date();
        if (date.toDateString() === now.toDateString()) {
          return date.toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
        }
        var yesterday = new Date(now);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) {
          return i18n.yesterday;
        }
        return date.toLocaleDateString(locale, { day: '2-digit', month: '2-digit', year: 'numeric' });
      }

      // Appliquer les heures locales
      document.querySelectorAll('.msg-time[data-utc]').forEach(function(el) {
        var d = utcToLocal(el.getAttribute('data-utc'));
        el.textContent = formatInboxTime(d);
      });

      // Rafraîchir la page automatiquement toutes les 10s pour voir les nouvelles conversations
      setInterval(function() {
        fetch(window.location.href, { credentials: 'same-origin' })
          .then(function(r) { return r.text(); })
          .then(function(html) {
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var newList = doc.querySelector('.msg-list');
            var oldList = document.querySelector('.msg-list');
            var newEmpty = doc.querySelector('.msg-empty');
            var oldEmpty = document.querySelector('.msg-empty');
            var container = document.querySelector('.msg-container');

            if (newList && oldList) {
              oldList.parentNode.replaceChild(newList, oldList);
              // Re-appliquer les heures locales
              document.querySelectorAll('.msg-time[data-utc]').forEach(function(el) {
                var d = utcToLocal(el.getAttribute('data-utc'));
                el.textContent = formatInboxTime(d);
              });
            } else if (newList && oldEmpty) {
              oldEmpty.parentNode.replaceChild(newList, oldEmpty);
              document.querySelectorAll('.msg-time[data-utc]').forEach(function(el) {
                var d = utcToLocal(el.getAttribute('data-utc'));
                el.textContent = formatInboxTime(d);
              });
            }

            // Mettre à jour le compteur
            var newCount = doc.querySelector('.msg-count');
            var oldCount = document.querySelector('.msg-count');
            if (newCount && oldCount) {
              oldCount.textContent = newCount.textContent;
            }
          })
          .catch(function() {});
      }, 10000);
    })();
  </script>
</body>

</html>
