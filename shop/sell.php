<?php
session_start();

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

require_once '../database/db.php';

// Toujours générer un nouveau token CSRF à chaque affichage du formulaire
// (évite les jetons obsolètes si l'utilisateur revient en arrière / recharge la page).
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$user = null;
try {
    $stmt = $pdo->prepare("SELECT username, email, profile_photo FROM users WHERE auth_token = ?");
    $stmt->execute([$_SESSION['auth_token']]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching user (sell.php): " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Plier - Poster une annonce</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/index.css">
    <link rel="stylesheet" href="../styles/sell.css">
</head>

<body>
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <main>
        <h1 class="page-title">Poster une annonce</h1>

        <?php if (!empty($_GET['error'])): ?>
            <div class="sell-alert sell-alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($_GET['success'])): ?>
            <div class="sell-alert sell-alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="handle_sell.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

            <!-- ── Photo ─────────────────────────────────────────── -->
            <div class="form-section">
                <span class="section-label">Photos</span>
                <label class="photo-upload-zone" id="upload-zone">
                    <input type="file" name="image" id="photo" accept=".jpg,.jpeg,.png,.webp">
                    <div class="upload-placeholder" id="upload-placeholder">
                        <div class="upload-icon-circle">
                            <i class="fas fa-camera"></i>
                        </div>
                        <span class="upload-text">Ajouter une photo</span>
                        <span class="upload-hint">JPG, PNG ou WEBP &mdash; max 5&nbsp;Mo</span>
                    </div>
                    <div id="photo-preview-container">
                        <img id="photo-preview" src="" alt="Aperçu">
                        <button type="button" class="preview-change-btn" id="change-photo-btn">
                            <i class="fas fa-sync-alt"></i> Changer
                        </button>
                    </div>
                </label>
            </div>

            <!-- ── L'essentiel ───────────────────────────────────── -->
            <div class="form-section">
                <span class="section-label">L'essentiel</span>

                <div class="field-group">
                    <label class="field-label" for="title">Titre de l'annonce</label>
                    <input
                        type="text"
                        class="sell-input"
                        id="title"
                        name="title"
                        placeholder="Ex : iPhone 14 Pro, Vélo de route Decathlon..."
                        maxlength="100"
                        required
                        value="<?php echo htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field-row" style="margin-top: 18px;">
                    <div class="field-group">
                        <label class="field-label" for="category">Catégorie</label>
                        <select class="sell-select" id="category" name="category" required>
                            <option value="" disabled <?php echo empty($_POST['category']) ? 'selected' : ''; ?>>Choisir...</option>
                            <option value="vetements" <?php echo ($_POST['category'] ?? '') === 'vetements'    ? 'selected' : ''; ?>>Vêtements</option>
                            <option value="electronique" <?php echo ($_POST['category'] ?? '') === 'electronique' ? 'selected' : ''; ?>>Électronique</option>
                            <option value="livres" <?php echo ($_POST['category'] ?? '') === 'livres'       ? 'selected' : ''; ?>>Livres &amp; Médias</option>
                            <option value="maison" <?php echo ($_POST['category'] ?? '') === 'maison'       ? 'selected' : ''; ?>>Maison &amp; Jardin</option>
                            <option value="sport" <?php echo ($_POST['category'] ?? '') === 'sport'        ? 'selected' : ''; ?>>Sport &amp; Loisirs</option>
                            <option value="vehicules" <?php echo ($_POST['category'] ?? '') === 'vehicules'    ? 'selected' : ''; ?>>Véhicules</option>
                            <option value="autre" <?php echo ($_POST['category'] ?? '') === 'autre'        ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="condition">État</label>
                        <select class="sell-select" id="condition" name="condition" required>
                            <option value="" disabled <?php echo empty($_POST['condition']) ? 'selected' : ''; ?>>Choisir...</option>
                            <option value="neuf" <?php echo ($_POST['condition'] ?? '') === 'neuf'          ? 'selected' : ''; ?>>Neuf</option>
                            <option value="tres_bon_etat" <?php echo ($_POST['condition'] ?? '') === 'tres_bon_etat' ? 'selected' : ''; ?>>Très bon état</option>
                            <option value="bon_etat" <?php echo ($_POST['condition'] ?? '') === 'bon_etat'      ? 'selected' : ''; ?>>Bon état</option>
                            <option value="etat_correct" <?php echo ($_POST['condition'] ?? '') === 'etat_correct'  ? 'selected' : ''; ?>>État correct</option>
                            <option value="pour_pieces" <?php echo ($_POST['condition'] ?? '') === 'pour_pieces'   ? 'selected' : ''; ?>>Pour pièces</option>
                        </select>
                    </div>
                </div>

                <div class="field-group" style="margin-top: 18px;">
                    <label class="field-label" for="price">Prix</label>
                    <div class="price-wrapper">
                        <input
                            type="number"
                            class="sell-input"
                            id="price"
                            name="price"
                            placeholder="0.00"
                            min="0"
                            max="99999"
                            step="0.01"
                            required
                            value="<?php echo htmlspecialchars($_POST['price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="price-symbol">€</span>
                    </div>
                </div>
            </div>

            <!-- ── Détails ────────────────────────────────────────── -->
            <div class="form-section">
                <span class="section-label">Détails</span>

                <div class="field-group">
                    <label class="field-label" for="description">Description</label>
                    <textarea
                        class="sell-textarea"
                        id="description"
                        name="description"
                        placeholder="Décrivez votre article : état, dimensions, raison de la vente..."
                        minlength="10"
                        required><?php echo htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <div class="field-group">
                    <label class="field-label" for="location">
                        Lieu <span style="font-weight:400; color:#bbb;">(facultatif)</span>
                    </label>
                    <input
                        type="text"
                        class="sell-input"
                        id="location"
                        name="location"
                        placeholder="Ex : Paris, Lyon..."
                        maxlength="100"
                        value="<?php echo htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <!-- ── Publier ────────────────────────────────────────── -->
            <button type="submit" class="publish-btn">
                <i class="fas fa-paper-plane"></i>&nbsp; Publier l'annonce
            </button>
        </form>
    </main>

    <script>
        var photoInput = document.getElementById('photo');
        var preview = document.getElementById('photo-preview');
        var container = document.getElementById('photo-preview-container');
        var placeholder = document.getElementById('upload-placeholder');
        var changeBtn = document.getElementById('change-photo-btn');

        photoInput.addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                placeholder.style.display = 'none';
                container.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });

        changeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            photoInput.value = '';
            container.style.display = 'none';
            placeholder.style.display = 'flex';
        });
    </script>
    <script src="../styles/form-validation.js"></script>
</body>

</html>