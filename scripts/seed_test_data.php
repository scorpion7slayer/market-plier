<?php
// Usage: php scripts/seed_test_data.php
// This script creates 50 test users (test@mail.com, test1@mail.com, …) and
// for each user inserts 10 dummy listings. It also cleans up any previous
// test data before seeding so it can be rerun safely.

require_once __DIR__ . '/../database/db.php';

// helper to generate a random auth token
function gen_token(): string
{
  return bin2hex(random_bytes(32));
}

// categories/conditions available in the application
$categories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
$conditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];

try {
  $pdo->beginTransaction();

  // cleanup existing test data
  $pdo->exec("DELETE FROM notifications WHERE auth_token LIKE 'test%'");
  $pdo->exec("DELETE FROM messages WHERE sender_token LIKE 'test%'");
  $pdo->exec("DELETE FROM conversations WHERE user1_token LIKE 'test%' OR user2_token LIKE 'test%'");
  $pdo->exec("DELETE FROM reviews WHERE reviewer_token LIKE 'test%' OR seller_token LIKE 'test%'");
  $pdo->exec("DELETE FROM favorites WHERE auth_token LIKE 'test%'");
  $pdo->exec("DELETE FROM listing_images WHERE listing_id IN (SELECT id FROM listings WHERE auth_token LIKE 'test%')");
  $pdo->exec("DELETE FROM listings WHERE auth_token LIKE 'test%'");
  $pdo->exec("DELETE FROM profile WHERE auth_token LIKE 'test%'");
  $pdo->exec("DELETE FROM users WHERE email LIKE 'test%@mail.com'");

  $userStmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, auth_token, auth_provider) VALUES (?, ?, ?, ?, 'local')");
  $profileStmt = $pdo->prepare("INSERT INTO profile (auth_token, description) VALUES (?, ?)");
  $listingStmt = $pdo->prepare("INSERT INTO listings (auth_token, title, description, price, category, item_condition, image, location) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

  for ($i = 0; $i < 50; $i++) {
    $email = ($i === 0) ? 'test@mail.com' : "test{$i}@mail.com";
    $username = ($i === 0) ? 'test' : "test{$i}";
    // auth_token column is VARCHAR(64). We want a prefix to identify test data
    // so generate 60 hex characters + "test" prefix => total 64 characters.
    $token = 'test' . bin2hex(random_bytes(30));
    $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);

    $userStmt->execute([$username, $email, $passwordHash, $token]);

    // add a simple profile description
    $profileStmt->execute([$token, "Description for user {$username}"]);

    // insert 10 listings for this user
    for ($j = 1; $j <= 10; $j++) {
      $title = "Article {$j} de {$username}";
      $desc = "Description de l'annonce {$j} pour l'utilisateur {$username}.";
      $price = rand(1, 1000) / 10; // random price between 0.1 and 100.0
      $cat = $categories[array_rand($categories)];
      $cond = $conditions[array_rand($conditions)];
      $location = "Ville{$i}";
      $listingStmt->execute([$token, $title, $desc, $price, $cat, $cond, null, $location]);
    }
  }

  // Collect all tokens for cross-referencing
  $allTokens = [];
  $tokenStmt = $pdo->prepare("SELECT auth_token FROM users WHERE email LIKE 'test%@mail.com'");
  $tokenStmt->execute();
  $allTokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

  // ═══ REVIEWS ══════════════════════════════════════════
  $reviewStmt = $pdo->prepare("INSERT INTO reviews (reviewer_token, seller_token, listing_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
  $comments = [
    'Très bon vendeur, article conforme !',
    'Envoi rapide et bien emballé.',
    'Communication excellente, je recommande.',
    'Article en bon état comme décrit.',
    'Super expérience, merci !',
    null, // some without comment
    'Vendeur sérieux et réactif.',
    'Bon rapport qualité-prix.',
    null,
    'Transaction parfaite.',
  ];

  $reviewCount = 0;
  for ($r = 0; $r < 200; $r++) {
    $reviewer = $allTokens[array_rand($allTokens)];
    $seller = $allTokens[array_rand($allTokens)];
    if ($reviewer === $seller) continue;

    // Get a random listing from the seller
    $lstStmt = $pdo->prepare("SELECT id FROM listings WHERE auth_token = ? ORDER BY RAND() LIMIT 1");
    $lstStmt->execute([$seller]);
    $lst = $lstStmt->fetch();
    if (!$lst) continue;

    try {
      $reviewStmt->execute([
        $reviewer,
        $seller,
        $lst['id'],
        rand(3, 5),
        $comments[array_rand($comments)]
      ]);
      $reviewCount++;
    } catch (PDOException $e) {
      // duplicate, skip
    }
  }
  echo "Reviews: {$reviewCount} inserted.\n";

  // ═══ FAVORITES ════════════════════════════════════════
  $favStmt = $pdo->prepare("INSERT INTO favorites (auth_token, listing_id) VALUES (?, ?)");
  $favCount = 0;
  for ($f = 0; $f < 300; $f++) {
    $userToken = $allTokens[array_rand($allTokens)];
    $lstStmt = $pdo->prepare("SELECT id FROM listings WHERE auth_token != ? ORDER BY RAND() LIMIT 1");
    $lstStmt->execute([$userToken]);
    $lst = $lstStmt->fetch();
    if (!$lst) continue;

    try {
      $favStmt->execute([$userToken, $lst['id']]);
      $favCount++;
    } catch (PDOException $e) {
      // duplicate, skip
    }
  }
  echo "Favorites: {$favCount} inserted.\n";

  // ═══ CONVERSATIONS & MESSAGES ═════════════════════════
  $convStmt = $pdo->prepare("INSERT INTO conversations (user1_token, user2_token, listing_id) VALUES (?, ?, ?)");
  $msgStmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_token, content) VALUES (?, ?, ?)");

  $sampleMessages = [
    'Bonjour, votre annonce m\'intéresse !',
    'Est-ce encore disponible ?',
    'Oui, toujours disponible !',
    'Quel est votre meilleur prix ?',
    'Je peux faire un petit geste sur le prix.',
    'Super, je suis intéressé.',
    'On peut se retrouver en centre-ville ?',
    'Parfait, demain à 14h ?',
    'Ça me va, à demain !',
    'Merci pour la transaction !',
  ];

  $convCount = 0;
  $msgCount = 0;
  for ($c = 0; $c < 30; $c++) {
    $u1 = $allTokens[$c % count($allTokens)];
    $u2 = $allTokens[($c + 1) % count($allTokens)];
    if ($u1 === $u2) continue;

    $tokens = [$u1, $u2];
    sort($tokens);

    // Get a listing from u2
    $lstStmt = $pdo->prepare("SELECT id FROM listings WHERE auth_token = ? LIMIT 1");
    $lstStmt->execute([$u2]);
    $lst = $lstStmt->fetch();
    $listingId = $lst ? $lst['id'] : null;

    try {
      $convStmt->execute([$tokens[0], $tokens[1], $listingId]);
      $convId = (int) $pdo->lastInsertId();
      $convCount++;

      // Add 3-6 messages
      $numMsgs = rand(3, 6);
      for ($m = 0; $m < $numMsgs; $m++) {
        $sender = ($m % 2 === 0) ? $u1 : $u2;
        $content = $sampleMessages[$m % count($sampleMessages)];
        $msgStmt->execute([$convId, $sender, $content]);
        $msgCount++;
      }
    } catch (PDOException $e) {
      // duplicate conversation, skip
    }
  }
  echo "Conversations: {$convCount}, Messages: {$msgCount}.\n";

  // ═══ NOTIFICATIONS ════════════════════════════════════
  $notifStmt = $pdo->prepare("INSERT INTO notifications (auth_token, type, title, content, link) VALUES (?, ?, ?, ?, ?)");
  $notifCount = 0;
  for ($n = 0; $n < 50; $n++) {
    $token = $allTokens[array_rand($allTokens)];
    $types = ['message', 'review', 'favorite', 'system'];
    $type = $types[array_rand($types)];
    $titles = [
      'message'  => 'Nouveau message',
      'review'   => 'Nouvel avis reçu ★★★★★',
      'favorite' => 'Votre annonce a été ajoutée en favori',
      'system'   => 'Bienvenue sur Market Plier !',
    ];
    $notifStmt->execute([
      $token,
      $type,
      $titles[$type],
      'Contenu de la notification de test #' . ($n + 1),
      null
    ]);
    $notifCount++;
  }
  echo "Notifications: {$notifCount} inserted.\n";

  $pdo->commit();
  echo "\nSeed complete: 50 users, 500 listings + reviews, favorites, conversations, notifications.\n";
} catch (PDOException $e) {
  $pdo->rollBack();
  echo "Error seeding data: " . $e->getMessage() . "\n";
  exit(1);
}
