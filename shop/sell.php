<?php
session_start();

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

require_once '../database/db.php';

// Générer un token CSRF uniquement s'il n'existe pas encore dans la session.
// Régénérer inconditionnellement provoquait des erreurs CSRF : naviguer vers
// une autre page (ex. l'accueil) écrasait le token, rendant le formulaire
// déjà affiché invalide (notamment via le cache bfcache du navigateur).
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    <script>
      (function(){var t=localStorage.getItem('theme')||(window.matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');document.documentElement.setAttribute('data-bs-theme',t);})();
    </script>
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

            <!-- Photos -->
            <div class="form-section">
                <span class="section-label">Photos <span class="photo-counter">(<span id="photo-count">0</span>/5)</span></span>
                <div class="photos-container" id="photos-container">
                    <!-- Zone d'upload principale -->
                    <label class="photo-upload-zone" id="upload-zone">
                        <input type="file" name="images[]" id="photo-input" accept=".jpg,.jpeg,.png,.webp" multiple>
                        <div class="upload-placeholder" id="upload-placeholder">
                            <div class="upload-icon-circle">
                                <i class="fas fa-camera"></i>
                            </div>
                            <span class="upload-text">Ajouter des photos</span>
                            <span class="upload-hint">JPG, PNG ou WEBP &mdash; max 5&nbsp;Mo par image</span>
                        </div>
                    </label>
                    <!-- Les aperçus des photos seront insérés ici -->
                </div>
                <p class="photos-help-text">Vous pouvez ajouter jusqu'à 5 photos. Glissez-déposez pour réorganiser.</p>
            </div>

            <!-- L'essentiel -->
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

            <!-- Détails -->
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

            <!-- Publier -->
            <button type="submit" class="publish-btn">
                <i class="fas fa-paper-plane"></i>&nbsp; Publier l'annonce
            </button>
        </form>
    </main>

    <script>
        var photoInput = document.getElementById('photo-input');
        var photosContainer = document.getElementById('photos-container');
        var uploadPlaceholder = document.getElementById('upload-placeholder');
        var photoCountSpan = document.getElementById('photo-count');
        var selectedFiles = [];
        var maxFiles = 5;

        // Redimensionne et compresse une image via Canvas avant l'upload.
        // maxPx : dimension max (largeur ou hauteur), quality : 0-1 pour JPEG.
        // Retourne une Promise<File> avec le fichier compressé.
        function compressImage(file, maxPx, quality) {
            return new Promise(function(resolve) {
                var reader = new FileReader();
                reader.onerror = function() { resolve(file); };
                reader.onload = function(e) {
                    var img = new Image();
                    img.onerror = function() { resolve(file); };
                    img.onload = function() {
                        var w = img.width, h = img.height;
                        if (w > maxPx || h > maxPx) {
                            var ratio = Math.min(maxPx / w, maxPx / h);
                            w = Math.round(w * ratio);
                            h = Math.round(h * ratio);
                        }
                        var canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        canvas.toBlob(function(blob) {
                            if (!blob) { resolve(file); return; }
                            var name = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                            resolve(new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() }));
                        }, 'image/jpeg', quality);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        // Compresse un tableau de fichiers puis appelle callback(fichiersCompressés).
        function processAndAddFiles(files, callback) {
            Promise.all(files.map(function(f) {
                return compressImage(f, 1920, 0.85);
            })).then(callback);
        }

        function updatePhotoCount() {
            photoCountSpan.textContent = selectedFiles.length;
            uploadPlaceholder.style.display = selectedFiles.length >= maxFiles ? 'none' : 'flex';
        }

        function createPhotoPreview(file, index) {
            var wrapper = document.createElement('div');
            wrapper.className = 'photo-preview-wrapper';
            wrapper.setAttribute('data-index', index);
            wrapper.draggable = true;

            var img = document.createElement('img');
            img.className = 'photo-preview-img';
            img.alt = 'Aperçu';

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'photo-remove-btn';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';

            var orderBadge = document.createElement('span');
            orderBadge.className = 'photo-order-badge';
            orderBadge.textContent = index + 1;

            // Bouton pour définir comme principale
            var mainBtn = document.createElement('button');
            mainBtn.type = 'button';
            mainBtn.className = 'photo-main-btn' + (index === 0 ? ' active' : '');
            mainBtn.innerHTML = '<i class="fas fa-star"></i>';
            mainBtn.title = 'Définir comme image principale';

            // Input hidden pour indiquer l'image principale
            var mainInput = document.createElement('input');
            mainInput.type = 'hidden';
            mainInput.name = 'main_image_index';
            mainInput.value = '0';
            mainInput.className = 'main-image-input';

            var reader = new FileReader();
            reader.onload = function(e) {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);

            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                selectedFiles.splice(index, 1);
                renderPreviews();
            });

            // Marquer comme principale
            mainBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                // Réorganiser les fichiers pour mettre celle-ci en premier
                var clickedIndex = parseInt(wrapper.getAttribute('data-index'));
                if (clickedIndex > 0) {
                    var temp = selectedFiles[clickedIndex];
                    selectedFiles.splice(clickedIndex, 1);
                    selectedFiles.unshift(temp);
                    renderPreviews();
                }
            });

            // Drag and drop
            wrapper.addEventListener('dragstart', function(e) {
                wrapper.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', index);
            });

            wrapper.addEventListener('dragend', function() {
                wrapper.classList.remove('dragging');
            });

            wrapper.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                wrapper.classList.add('drag-over');
            });

            wrapper.addEventListener('dragleave', function() {
                wrapper.classList.remove('drag-over');
            });

            wrapper.addEventListener('drop', function(e) {
                e.preventDefault();
                wrapper.classList.remove('drag-over');
                var draggedIndex = parseInt(e.dataTransfer.getData('text/plain'));
                var targetIndex = parseInt(wrapper.getAttribute('data-index'));
                if (draggedIndex !== targetIndex) {
                    var temp = selectedFiles[draggedIndex];
                    selectedFiles.splice(draggedIndex, 1);
                    selectedFiles.splice(targetIndex, 0, temp);
                    renderPreviews();
                }
            });

            wrapper.appendChild(img);
            wrapper.appendChild(orderBadge);
            wrapper.appendChild(mainBtn);
            wrapper.appendChild(removeBtn);

            if (index === 0) {
                wrapper.appendChild(mainInput);
            }

            return wrapper;
        }

        function renderPreviews() {
            // Supprime tous les aperçus existants
            var existingPreviews = photosContainer.querySelectorAll('.photo-preview-wrapper');
            existingPreviews.forEach(function(el) {
                el.remove();
            });

            // Recrée les aperçus
            selectedFiles.forEach(function(file, index) {
                var preview = createPhotoPreview(file, index);
                photosContainer.appendChild(preview);
            });

            updatePhotoCount();
            updateFileInput();
        }

        function updateFileInput() {
            var dataTransfer = new DataTransfer();
            selectedFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            photoInput.files = dataTransfer.files;
        }

        photoInput.addEventListener('change', function() {
            var newFiles = Array.from(this.files);
            var remainingSlots = maxFiles - selectedFiles.length;

            if (newFiles.length > remainingSlots) {
                newFiles = newFiles.slice(0, remainingSlots);
                alert('Vous ne pouvez ajouter que ' + maxFiles + ' photos maximum.');
            }

            processAndAddFiles(newFiles, function(compressed) {
                selectedFiles = selectedFiles.concat(compressed);
                renderPreviews();
            });
        });

        // Drag and drop sur la zone d'upload
        var uploadZone = document.getElementById('upload-zone');

        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadZone.classList.add('drag-over');
        });

        uploadZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadZone.classList.remove('drag-over');
        });

        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            uploadZone.classList.remove('drag-over');

            var files = Array.from(e.dataTransfer.files);
            var imageFiles = files.filter(function(file) {
                return file.type.startsWith('image/');
            });

            var remainingSlots = maxFiles - selectedFiles.length;
            if (imageFiles.length > remainingSlots) {
                imageFiles = imageFiles.slice(0, remainingSlots);
                alert('Vous ne pouvez ajouter que ' + maxFiles + ' photos maximum.');
            }

            processAndAddFiles(imageFiles, function(compressed) {
                selectedFiles = selectedFiles.concat(compressed);
                renderPreviews();
            });
        });
    </script>
    <script src="../styles/form-validation.js"></script>
</body>

</html>