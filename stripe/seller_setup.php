<?php
/**
 * Page intermédiaire après la publication d'une annonce.
 * Invite le vendeur à configurer son compte de paiement Stripe.
 */
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';
require_once '../includes/lang.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

$user = null;
$stmt = $pdo->prepare("SELECT username, email, profile_photo, auth_token FROM users WHERE auth_token = ?");
$stmt->execute([$_SESSION['auth_token']]);
$user = $stmt->fetch();

$listingId = filter_input(INPUT_GET, 'listing_id', FILTER_VALIDATE_INT);
$isPending = ($_GET['pending'] ?? '0') === '1';

// Récupérer le titre de l'annonce si disponible
$listingTitle = null;
if ($listingId) {
    $lstmt = $pdo->prepare("SELECT title FROM listings WHERE id = ? AND auth_token = ?");
    $lstmt->execute([$listingId, $_SESSION['auth_token']]);
    $row = $lstmt->fetch();
    $listingTitle = $row ? $row['title'] : null;
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(getUserLang(), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '../includes/theme_init.php'; ?>
    <title>Activez vos paiements — Market Plier</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/index.css">
    <link rel="stylesheet" href="../styles/theme.css">
    <style>
        .setup-main {
            max-width: 560px;
            margin: 60px auto;
            padding: 0 20px 80px;
        }
        /* Badge "Annonce publiée" */
        .setup-listing-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
            border-radius: 999px;
            background: rgba(127, 184, 133, 0.15);
            color: #7fb885;
            font-size: 0.85rem;
            font-weight: 700;
            border: 1px solid rgba(127, 184, 133, 0.3);
            margin-bottom: 28px;
        }
        /* Carte principale */
        .setup-card {
            background: var(--mp-card-bg);
            border: 1.5px solid var(--mp-border);
            border-radius: 24px;
            padding: 40px 36px;
            text-align: center;
        }
        .setup-icon-wrap {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: rgba(127, 184, 133, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 2.2rem;
            color: #7fb885;
        }
        .setup-title {
            font-size: 1.55rem;
            font-weight: 800;
            color: var(--mp-text);
            margin-bottom: 10px;
            font-style: italic;
            font-family: "Archivo", sans-serif;
        }
        .setup-subtitle {
            color: var(--mp-text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        /* Steps */
        .setup-steps {
            display: flex;
            flex-direction: column;
            gap: 12px;
            text-align: left;
            margin-bottom: 32px;
        }
        .setup-step {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            background: var(--mp-border-light);
            border-radius: 14px;
        }
        .setup-step-num {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #7fb885;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 800;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .setup-step-text { font-size: 0.9rem; color: var(--mp-text); line-height: 1.5; }
        .setup-step-text strong { color: var(--mp-text); }
        /* Boutons */
        .setup-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 24px;
            border-radius: 999px;
            background: #7fb885;
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
            border: none;
            cursor: pointer;
            margin-bottom: 12px;
        }
        .setup-cta:hover {
            background: #6da873;
            color: #fff;
            transform: translateY(-1px);
        }
        .setup-later {
            display: block;
            text-align: center;
            color: var(--mp-text-muted);
            font-size: 0.88rem;
            text-decoration: none;
            padding: 8px;
        }
        .setup-later:hover { color: var(--mp-text); }
        /* Note sécurité */
        .setup-security {
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--mp-text-muted);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <main class="setup-main">

        <?php if (($_GET['incomplete'] ?? '0') === '1'): ?>
            <div style="padding:12px 18px; border-radius:12px; background:rgba(245,158,11,0.1); color:#b45309; border:1.5px solid #f59e0b; margin-bottom:20px; font-size:0.9rem;">
                <i class="fa-solid fa-triangle-exclamation"></i>
                Vérification incomplète — Veuillez terminer toutes les étapes sur Stripe pour activer les paiements.
            </div>
        <?php endif; ?>

        <?php if ($isPending): ?>
            <div class="setup-listing-badge">
                <i class="fa-solid fa-clock"></i> Annonce en attente de validation
            </div>
        <?php else: ?>
            <div class="setup-listing-badge">
                <i class="fa-solid fa-check"></i>
                <?= $listingTitle
                    ? '« ' . htmlspecialchars($listingTitle, ENT_QUOTES, 'UTF-8') . ' » est en ligne !'
                    : 'Annonce publiée !' ?>
            </div>
        <?php endif; ?>

        <div class="setup-card">
            <div class="setup-icon-wrap">
                <i class="fa-solid fa-wallet"></i>
            </div>

            <h1 class="setup-title">Activez vos paiements</h1>
            <p class="setup-subtitle">
                Pour recevoir l'argent de vos ventes, vous devez configurer votre compte de paiement.<br>
                C'est rapide, sécurisé, et ne se fait qu'une seule fois.
            </p>

            <div class="setup-steps">
                <div class="setup-step">
                    <div class="setup-step-num">1</div>
                    <div class="setup-step-text">
                        <strong>Identité</strong> — Vérifiez votre identité (pièce d'identité ou passeport).
                    </div>
                </div>
                <div class="setup-step">
                    <div class="setup-step-num">2</div>
                    <div class="setup-step-text">
                        <strong>Coordonnées bancaires</strong> — Ajoutez votre IBAN pour recevoir vos virements.
                    </div>
                </div>
                <div class="setup-step">
                    <div class="setup-step-num">3</div>
                    <div class="setup-step-text">
                        <strong>C'est tout !</strong> — Vous recevrez automatiquement l'argent après chaque vente (commission plateforme : 5%).
                    </div>
                </div>
            </div>

            <a href="../stripe/onboarding.php" class="setup-cta">
                <i class="fa-solid fa-arrow-right"></i>
                Configurer mes paiements
            </a>

            <a href="../inscription-connexion/account.php" class="setup-later">
                <i class="fa-regular fa-clock"></i> Faire ça plus tard
            </a>

            <div class="setup-security">
                <i class="fa-solid fa-lock"></i>
                Sécurisé par <strong style="margin-left:3px;">Stripe</strong> — Market Plier ne stocke jamais vos données bancaires.
            </div>
        </div>
    </main>

    <?php include '../footer.php'; ?>
    <script src="../styles/theme.js"></script>
</body>
</html>
