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

$conversationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$conversationId) {
  header('Location: inbox.php');
  exit();
}

// Vérifier que l'utilisateur participe à cette conversation
$stmt = $pdo->prepare("
    SELECT c.*,
           CASE WHEN c.user1_token = ? THEN c.user2_token ELSE c.user1_token END AS other_token,
           u.username AS other_username, u.profile_photo AS other_photo,
           l.title AS listing_title, l.id AS listing_id, l.image AS listing_image, l.price AS listing_price
    FROM conversations c
    JOIN users u ON u.auth_token = CASE WHEN c.user1_token = ? THEN c.user2_token ELSE c.user1_token END
    LEFT JOIN listings l ON l.id = c.listing_id
    WHERE c.id = ? AND (c.user1_token = ? OR c.user2_token = ?)
");
$stmt->execute([$myToken, $myToken, $conversationId, $myToken, $myToken]);
$conversation = $stmt->fetch();

if (!$conversation) {
  header('Location: inbox.php');
  exit();
}

// Marquer les messages non lus comme lus
$pdo->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_token != ? AND is_read = 0")
  ->execute([$conversationId, $myToken]);

// Récupérer les messages
$stmt = $pdo->prepare("
    SELECT m.*, u.username, u.profile_photo
    FROM messages m
    JOIN users u ON u.auth_token = m.sender_token
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
");
$stmt->execute([$conversationId]);
$messages = $stmt->fetchAll();

// CSRF token
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/theme_init.php'; ?>
  <title><?= htmlspecialchars(t('conv_title_prefix'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($conversation['other_username'], ENT_QUOTES, 'UTF-8') ?> — Market Plier</title>
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

  <main class="conv-main">
    <div class="conv-container">
      <!-- En-tête conversation -->
      <div class="conv-header">
        <a href="inbox.php" class="conv-back" aria-label="<?= htmlspecialchars(t('conv_back'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-arrow-left"></i></a>
        <a href="../inscription-connexion/profile.php?user=<?= urlencode($conversation['other_username']) ?>" class="conv-user-info">
          <div class="conv-avatar">
            <img src="../api/profile_photo.php?token=<?= urlencode($conversation['other_token']) ?>" alt="">
          </div>
          <span class="conv-username"><?= htmlspecialchars($conversation['other_username'], ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <?php if ($conversation['listing_title']): ?>
          <a href="../shop/buy.php?id=<?= (int) $conversation['listing_id'] ?>" class="conv-listing-badge">
            <i class="fa-solid fa-tag"></i> <?= htmlspecialchars($conversation['listing_title'], ENT_QUOTES, 'UTF-8') ?>
            <?php if ($conversation['listing_price']): ?>
              — <?= number_format((float) $conversation['listing_price'], 2, ',', ' ') ?> €
            <?php endif; ?>
          </a>
        <?php endif; ?>
      </div>

      <!-- Messages -->
      <div class="conv-messages" id="convMessages">
        <?php if (empty($messages)): ?>
          <div class="conv-empty">
            <i class="fa-regular fa-comment-dots"></i>
            <p><?= htmlspecialchars(t('conv_empty'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endif; ?>

        <?php
        $lastDate = '';
        foreach ($messages as $msg):
          $utc = $msg['created_at'];
          $msgDateKey = date('Y-m-d', strtotime($utc));
          if ($msgDateKey !== $lastDate):
            $lastDate = $msgDateKey;
        ?>
            <div class="conv-date-sep" data-utc="<?= htmlspecialchars($utc, ENT_QUOTES, 'UTF-8') ?>"></div>
          <?php endif; ?>

          <div class="conv-bubble <?= $msg['sender_token'] === $myToken ? 'conv-bubble-mine' : 'conv-bubble-other' ?>"
            data-msg-id="<?= (int) $msg['id'] ?>">
            <div class="conv-bubble-content">
              <?= nl2br(htmlspecialchars($msg['content'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
            <span class="conv-bubble-time" data-utc="<?= htmlspecialchars($utc, ENT_QUOTES, 'UTF-8') ?>">
              <?php if ($msg['sender_token'] === $myToken): ?>
                <i class="fa-solid <?= $msg['is_read'] ? 'fa-check-double conv-read-icon' : 'fa-check' ?> conv-check-icon"></i>
              <?php endif; ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Formulaire d'envoi -->
      <form class="conv-form" id="sendForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="conversation_id" value="<?= (int) $conversationId ?>">
        <textarea class="conv-input" name="content" id="msgInput" placeholder="<?= htmlspecialchars(t('msg_placeholder'), ENT_QUOTES, 'UTF-8') ?>" rows="1" maxlength="2000" required></textarea>
        <button type="submit" class="conv-send" id="sendBtn">
          <i class="fa-solid fa-paper-plane"></i>
        </button>
      </form>
    </div>
  </main>

  <?php include '../footer.php'; ?>

  <script src="../styles/theme.js"></script>
  <script>
    (function() {
      var messagesEl = document.getElementById('convMessages');
      var form = document.getElementById('sendForm');
      var input = document.getElementById('msgInput');
      var sendBtn = document.getElementById('sendBtn');
      var basePath = '../';
      var myToken = <?= json_encode($myToken) ?>;
      var lastMsgId = <?= !empty($messages) ? (int) end($messages)['id'] : 0 ?>;
      var locale = <?= json_encode(getUserLocale()) ?>;
      var i18n = <?= json_encode([
                    'today' => t('conv_today'),
                    'yesterday' => t('conv_yesterday'),
                    'send_error' => t('conv_send_error'),
                    'network_error' => t('network_error'),
                  ]) ?>;

      // TIMEZONE : convertir UTC -> heure locale
      function utcToLocal(utcStr) {
        // Le serveur renvoie "2026-03-06 13:00:15" en UTC
        // On ajoute 'Z' pour forcer l'interprétation UTC
        var d = new Date(utcStr.replace(' ', 'T') + 'Z');
        return d;
      }

      function formatTime(date) {
        return date.toLocaleTimeString(locale, {
          hour: '2-digit',
          minute: '2-digit'
        });
      }

      function formatDateSep(date) {
        var today = new Date();
        if (date.toDateString() === today.toDateString()) return i18n.today;
        var yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        if (date.toDateString() === yesterday.toDateString()) return i18n.yesterday;
        return date.toLocaleDateString(locale, {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric'
        });
      }

      // Appliquer les heures locales aux messages rendus par PHP
      document.querySelectorAll('.conv-bubble-time[data-utc]').forEach(function(el) {
        var d = utcToLocal(el.getAttribute('data-utc'));
        // Insérer l'heure avant les icônes existantes
        var icon = el.querySelector('.conv-check-icon');
        if (icon) {
          el.insertBefore(document.createTextNode(formatTime(d) + ' '), icon);
        } else {
          el.textContent = formatTime(d);
        }
      });

      // Appliquer les dates locales aux séparateurs
      document.querySelectorAll('.conv-date-sep[data-utc]').forEach(function(el) {
        var d = utcToLocal(el.getAttribute('data-utc'));
        el.textContent = formatDateSep(d);
      });

      // Défilement vers le bas
      function scrollBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      }
      scrollBottom();

      // Redimensionnement automatique du textarea
      input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
      });

      input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
          e.preventDefault();
          form.dispatchEvent(new Event('submit'));
        }
      });

      // Helpers
      function escapeText(t) {
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
      }

      function createBubble(content, className, timeStr, msgId) {
        var bubble = document.createElement('div');
        bubble.className = 'conv-bubble ' + className + ' conv-bubble-new';
        if (msgId) bubble.setAttribute('data-msg-id', msgId);

        var contentDiv = document.createElement('div');
        contentDiv.className = 'conv-bubble-content';
        contentDiv.innerHTML = escapeText(content).replace(/\n/g, '<br>');

        var timeSpan = document.createElement('span');
        timeSpan.className = 'conv-bubble-time';
        timeSpan.textContent = timeStr;

        // Ajouter check icon pour mes messages
        if (className.indexOf('conv-bubble-mine') !== -1) {
          timeSpan.textContent = timeStr + ' ';
          var checkIcon = document.createElement('i');
          checkIcon.className = 'fa-solid fa-check conv-check-icon';
          timeSpan.appendChild(checkIcon);
        }

        bubble.appendChild(contentDiv);
        bubble.appendChild(timeSpan);
        return bubble;
      }

      // Envoi AJAX
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        var content = input.value.trim();
        if (!content) return;

        sendBtn.disabled = true;
        var formData = new FormData(form);

        fetch(basePath + 'api/send_message.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            if (data.success) {
              var time = formatTime(new Date());
              var bubble = createBubble(content, 'conv-bubble-mine', time, data.message_id);
              messagesEl.appendChild(bubble);
              // Avancer lastMsgId pour éviter les doublons au polling
              if (data.message_id > lastMsgId) {
                lastMsgId = data.message_id;
              }
              input.value = '';
              input.style.height = 'auto';
              scrollBottom();
              var emptyMsg = messagesEl.querySelector('.conv-empty');
              if (emptyMsg) emptyMsg.remove();
            } else {
              if (typeof mpShowToast === 'function') {
                mpShowToast(data.error || i18n.send_error, 'error');
              }
            }
          })
          .catch(function() {
            if (typeof mpShowToast === 'function') {
              mpShowToast(i18n.network_error, 'error');
            }
          })
          .finally(function() {
            sendBtn.disabled = false;
            input.focus();
          });
      });

      // Polling (3s)
      setInterval(function() {
        fetch(basePath + 'api/poll_messages.php?conversation_id=<?= (int) $conversationId ?>&after=' + lastMsgId, {
            credentials: 'same-origin'
          })
          .then(function(r) {
            return r.json();
          })
          .then(function(data) {
            var scrolled = false;

            // Nouveaux messages de l'autre personne
            if (data.messages && data.messages.length > 0) {
              data.messages.forEach(function(msg) {
                // Avancer lastMsgId dans tous les cas
                if (msg.id > lastMsgId) lastMsgId = msg.id;
                // Ne pas afficher mes propres messages (déjà ajoutés à l'envoi)
                if (msg.sender_token === myToken) return;
                // Vérifier qu'il n'existe pas déjà dans le DOM
                if (messagesEl.querySelector('[data-msg-id="' + msg.id + '"]')) return;

                var d = utcToLocal(msg.utc);
                var bubble = createBubble(msg.content, 'conv-bubble-other', formatTime(d), msg.id);
                messagesEl.appendChild(bubble);
                scrolled = true;
              });
            }

            // Mise à jour des accusés de lecture (vus)
            if (data.read_ids && data.read_ids.length > 0) {
              data.read_ids.forEach(function(id) {
                var bubble = messagesEl.querySelector('[data-msg-id="' + id + '"]');
                if (!bubble) return;
                var icon = bubble.querySelector('.conv-check-icon');
                if (icon && !icon.classList.contains('fa-check-double')) {
                  icon.classList.remove('fa-check');
                  icon.classList.add('fa-check-double', 'conv-read-icon');
                }
              });
            }

            if (scrolled) scrollBottom();
          })
          .catch(function() {});
      }, 3000);
    })();
  </script>
</body>

</html>