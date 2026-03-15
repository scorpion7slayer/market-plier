<?php
// Utilisation : php scripts/seed_test_data.php
// Ce script crée 50 utilisateurs de test (test@mail.com, test1@mail.com, …) et
// ajoute 10 annonces factices pour chacun.
// Il nettoie aussi les données de test précédentes pour pouvoir relancer le script en toute sécurité.

require_once __DIR__ . '/../database/db.php';

// Génère un token d'authentification aléatoire
function gen_token(): string
{
  return bin2hex(random_bytes(32));
}

// Catégories/conditions disponibles dans l'application
$categories = ['vetements', 'electronique', 'livres', 'maison', 'sport', 'vehicules', 'autre'];
$conditions = ['neuf', 'tres_bon_etat', 'bon_etat', 'etat_correct', 'pour_pieces'];

try {
  $pdo->beginTransaction();

  // Nettoyer les données de test existantes
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
    // La colonne auth_token est VARCHAR(64). On veut un préfixe pour repérer les données de test.
    // Générer 60 caractères hexadécimaux + préfixe "test" => 64 caractères au total.
    $token = 'test' . bin2hex(random_bytes(30));
    $passwordHash = password_hash('password', PASSWORD_BCRYPT, ['cost' => 12]);

    $userStmt->execute([$username, $email, $passwordHash, $token]);

    // Ajouter une description de profil simple
    $profileStmt->execute([$token, "Description pour l'utilisateur {$username}"]);

    // Insérer 10 annonces pour cet utilisateur
    for ($j = 1; $j <= 10; $j++) {
      $title = "Article {$j} de {$username}";
      $desc = "Description de l'annonce {$j} pour l'utilisateur {$username}.";
      $price = rand(1, 1000) / 10; // prix aléatoire entre 0.1 et 100.0
      $cat = $categories[array_rand($categories)];
      $cond = $conditions[array_rand($conditions)];
      $location = "Ville{$i}";
      $listingStmt->execute([$token, $title, $desc, $price, $cat, $cond, null, $location]);
    }
  }

  // Récupérer tous les tokens pour les références croisées
  $allTokens = [];
  $tokenStmt = $pdo->prepare("SELECT auth_token FROM users WHERE email LIKE 'test%@mail.com'");
  $tokenStmt->execute();
  $allTokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

  // Avis
  $reviewStmt = $pdo->prepare("INSERT INTO reviews (reviewer_token, seller_token, listing_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
  $comments = [
    'Très bon vendeur, article conforme !',
    'Envoi rapide et bien emballé.',
    'Communication excellente, je recommande.',
    'Article en bon état comme décrit.',
    'Super expérience, merci !',
    null, // certains sans commentaire
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

    // Obtenir une annonce aléatoire du vendeur
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
      // doublon, ignorer
    }
  }
  echo "Reviews: {$reviewCount} inserted.\n";

  // Favoris
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
      // doublon, ignorer
    }
  }
  echo "Favorites: {$favCount} inserted.\n";

  // Conversations et messages
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

    // Obtenir une annonce de u2
    $lstStmt = $pdo->prepare("SELECT id FROM listings WHERE auth_token = ? LIMIT 1");
    $lstStmt->execute([$u2]);
    $lst = $lstStmt->fetch();
    $listingId = $lst ? $lst['id'] : null;

    try {
      $convStmt->execute([$tokens[0], $tokens[1], $listingId]);
      $convId = (int) $pdo->lastInsertId();
      $convCount++;

      // Ajouter 3 à 6 messages
      $numMsgs = rand(3, 6);
      for ($m = 0; $m < $numMsgs; $m++) {
        $sender = ($m % 2 === 0) ? $u1 : $u2;
        $content = $sampleMessages[$m % count($sampleMessages)];
        $msgStmt->execute([$convId, $sender, $content]);
        $msgCount++;
      }
    } catch (PDOException $e) {
      // doublon, ignorer
    }
  }
  echo "Conversations: {$convCount}, Messages: {$msgCount}.\n";

  // Notifications
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
