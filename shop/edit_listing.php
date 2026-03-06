<?php
session_start();
require_once '../database/db.php';
require_once '../includes/remember_me.php';

if (!isset($_SESSION['auth_token'])) {
    header('Location: ../inscription-connexion/login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$listingId = (int)$_GET['id'];

// Récupérer l'annonce et vérifier la propriété
try {
    $stmt = $pdo->prepare(
        "SELECT l.*, u.username, u.email, u.profile_photo
         FROM listings l
         JOIN users u ON u.auth_token = l.auth_token
         WHERE l.id = ? AND l.auth_token = ?"
    );
    $stmt->execute([$listingId, $_SESSION['auth_token']]);
    $listing = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching listing (edit_listing.php): " . $e->getMessage());
    $listing = null;
}

if (!$listing) {
    header('Location: ../inscription-connexion/account.php');
    exit();
}

// Récupérer les images existantes
$existingImages = [];
try {
    $imgStmt = $pdo->prepare("SELECT id, image_path, sort_order FROM listing_images WHERE listing_id = ? ORDER BY sort_order ASC");
    $imgStmt->execute([$listingId]);
    $existingImages = $imgStmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching listing images: " . $e->getMessage());
}

$user = [
    'username' => $listing['username'],
    'email' => $listing['email'],
    'profile_photo' => $listing['profile_photo'],
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '../includes/theme_init.php'; ?>
    <title>Market Plier - Modifier l'annonce</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../node_modules/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="../styles/index.css">
    <link rel="stylesheet" href="../styles/sell.css">
    <link rel="stylesheet" href="../styles/theme.css">
</head>

<body>
    <?php
    $headerBasePath = '../';
    $headerUser = $user;
    include '../header.php';
    ?>

    <main>
        <h1 class="page-title">Modifier l'annonce</h1>

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

        <form action="handle_edit_listing.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token"
                value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">

            <!-- Photos -->
            <div class="form-section">
                <span class="section-label">Photos <span class="photo-counter">(<span id="photo-count"><?php echo count($existingImages); ?></span>/5)</span></span>

                <div class="photos-container" id="photos-container">
                    <?php foreach ($existingImages as $index => $img): ?>
                        <div class="photo-preview-wrapper existing-photo" data-image-id="<?php echo $img['id']; ?>" data-index="<?php echo $index; ?>">
                            <img class="photo-preview-img" src="../uploads/listings/<?php echo htmlspecialchars($img['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Photo existante">
                            <span class="photo-order-badge"><?php echo $index + 1; ?></span>
                            <button type="button" class="photo-main-btn<?php echo $index === 0 ? ' active' : ''; ?>" title="Définir comme image principale">
                                <i class="fas fa-star"></i>
                            </button>
                            <button type="button" class="photo-remove-btn" title="Supprimer cette photo">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>

                    <!-- Zone d'upload pour nouvelles photos -->
                    <label class="photo-upload-zone" id="upload-zone" <?php echo count($existingImages) >= 5 ? 'style="display:none;"' : ''; ?>>
                        <input type="file" name="new_images[]" id="photo-input" accept=".jpg,.jpeg,.png,.webp" multiple>
                        <div class="upload-placeholder" id="upload-placeholder">
                            <div class="upload-icon-circle">
                                <i class="fas fa-camera"></i>
                            </div>
                            <span class="upload-text">Ajouter des photos</span>
                            <span class="upload-hint">JPG, PNG ou WEBP &mdash; max 5&nbsp;Mo par image</span>
                        </div>
                    </label>
                </div>

                <!-- Hidden inputs pour les images à conserver -->
                <div id="kept-images-inputs">
                    <?php foreach ($existingImages as $img): ?>
                        <input type="hidden" name="keep_images[]" value="<?php echo $img['id']; ?>">
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="image_order" id="image-order" value="">

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
                        value="<?php echo htmlspecialchars($listing['title'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="field-row" style="margin-top: 18px;">
                    <div class="field-group">
                        <label class="field-label" for="category">Catégorie</label>
                        <select class="sell-select" id="category" name="category" required>
                            <option value="" disabled>Choisir...</option>
                            <option value="vetements" <?php echo $listing['category'] === 'vetements' ? 'selected' : ''; ?>>Vêtements</option>
                            <option value="electronique" <?php echo $listing['category'] === 'electronique' ? 'selected' : ''; ?>>Électronique</option>
                            <option value="livres" <?php echo $listing['category'] === 'livres' ? 'selected' : ''; ?>>Livres &amp; Médias</option>
                            <option value="maison" <?php echo $listing['category'] === 'maison' ? 'selected' : ''; ?>>Maison &amp; Jardin</option>
                            <option value="sport" <?php echo $listing['category'] === 'sport' ? 'selected' : ''; ?>>Sport &amp; Loisirs</option>
                            <option value="vehicules" <?php echo $listing['category'] === 'vehicules' ? 'selected' : ''; ?>>Véhicules</option>
                            <option value="autre" <?php echo $listing['category'] === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>

                    <div class="field-group">
                        <label class="field-label" for="condition">État</label>
                        <select class="sell-select" id="condition" name="condition" required>
                            <option value="" disabled>Choisir...</option>
                            <option value="neuf" <?php echo $listing['item_condition'] === 'neuf' ? 'selected' : ''; ?>>Neuf</option>
                            <option value="tres_bon_etat" <?php echo $listing['item_condition'] === 'tres_bon_etat' ? 'selected' : ''; ?>>Très bon état</option>
                            <option value="bon_etat" <?php echo $listing['item_condition'] === 'bon_etat' ? 'selected' : ''; ?>>Bon état</option>
                            <option value="etat_correct" <?php echo $listing['item_condition'] === 'etat_correct' ? 'selected' : ''; ?>>État correct</option>
                            <option value="pour_pieces" <?php echo $listing['item_condition'] === 'pour_pieces' ? 'selected' : ''; ?>>Pour pièces</option>
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
                            value="<?php echo htmlspecialchars(rtrim(rtrim(number_format((float)$listing['price'], 2, '.', ''), '0'), '.'), ENT_QUOTES, 'UTF-8'); ?>">
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
                        required><?php echo htmlspecialchars($listing['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
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
                        value="<?php echo htmlspecialchars($listing['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            </div>

            <!-- Enregistrer -->
            <button type="submit" class="publish-btn">
                <i class="fas fa-save"></i>&nbsp; Enregistrer les modifications
            </button>
        </form>
    </main>

    <script>
        // Données des images existantes
        var existingImages = <?php echo json_encode(array_map(function ($img) {
                                    return ['id' => $img['id'], 'path' => $img['image_path']];
                                }, $existingImages)); ?>;

        var photoInput = document.getElementById('photo-input');
        var photosContainer = document.getElementById('photos-container');
        var photoCountSpan = document.getElementById('photo-count');
        var keptImagesDiv = document.getElementById('kept-images-inputs');
        var imageOrderInput = document.getElementById('image-order');
        var newFiles = [];
        var maxFiles = 5;

        function compressImage(file, maxPx, quality) {
            return new Promise(function(resolve) {
                var reader = new FileReader();
                reader.onerror = function() {
                    resolve(file);
                };
                reader.onload = function(e) {
                    var img = new Image();
                    img.onerror = function() {
                        resolve(file);
                    };
                    img.onload = function() {
                        var w = img.width,
                            h = img.height;
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
                            if (!blob) {
                                resolve(file);
                                return;
                            }
                            var name = file.name.replace(/\.[^/.]+$/, '') + '.jpg';
                            resolve(new File([blob], name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            }));
                        }, 'image/jpeg', quality);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function processAndAddFiles(files, callback) {
            Promise.all(files.map(function(f) {
                return compressImage(f, 1920, 0.85);
            })).then(callback);
        }

        function getTotalCount() {
            return existingImages.length + newFiles.length;
        }

        function updatePhotoCount() {
            photoCountSpan.textContent = getTotalCount();
            document.getElementById('upload-zone').style.display = getTotalCount() >= maxFiles ? 'none' : 'flex';
        }

        function updateKeptImagesInputs() {
            keptImagesDiv.innerHTML = '';
            existingImages.forEach(function(img) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'keep_images[]';
                input.value = img.id;
                keptImagesDiv.appendChild(input);
            });
        }

        function updateImageOrder() {
            var order = [];
            existingImages.forEach(function(img) {
                order.push('existing:' + img.id);
            });
            newFiles.forEach(function(f, i) {
                order.push('new:' + i);
            });
            imageOrderInput.value = order.join(',');
        }

        function updateFileInput() {
            var dataTransfer = new DataTransfer();
            newFiles.forEach(function(file) {
                dataTransfer.items.add(file);
            });
            photoInput.files = dataTransfer.files;
        }

        function renderAll() {
            var previews = photosContainer.querySelectorAll('.photo-preview-wrapper');
            previews.forEach(function(el) {
                el.remove();
            });

            var uploadZone = document.getElementById('upload-zone');
            var globalIndex = 0;

            // Images existantes
            existingImages.forEach(function(img, i) {
                var wrapper = createPreviewWrapper({
                    globalIndex: globalIndex,
                    type: 'existing',
                    localIndex: i,
                    imgData: img,
                    file: null
                });
                photosContainer.insertBefore(wrapper, uploadZone);
                globalIndex++;
            });

            // Nouvelles images
            newFiles.forEach(function(file, i) {
                var wrapper = createPreviewWrapper({
                    globalIndex: globalIndex,
                    type: 'new',
                    localIndex: i,
                    imgData: null,
                    file: file
                });
                photosContainer.insertBefore(wrapper, uploadZone);
                globalIndex++;
            });

            updatePhotoCount();
            updateKeptImagesInputs();
            updateImageOrder();
            updateFileInput();
        }

        function buildAllItems() {
            var allItems = [];
            existingImages.forEach(function(img) {
                allItems.push({
                    type: 'existing',
                    data: img
                });
            });
            newFiles.forEach(function(f) {
                allItems.push({
                    type: 'new',
                    data: f
                });
            });
            return allItems;
        }

        function applyAllItems(allItems) {
            existingImages = [];
            newFiles = [];
            allItems.forEach(function(it) {
                if (it.type === 'existing') existingImages.push(it.data);
                else newFiles.push(it.data);
            });
            renderAll();
        }

        function setupDragDrop(wrapper, globalIndex) {
            wrapper.addEventListener('dragstart', function(e) {
                wrapper.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(globalIndex));
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
                var fromIndex = parseInt(e.dataTransfer.getData('text/plain'));
                if (fromIndex === globalIndex) return;
                var allItems = buildAllItems();
                var item = allItems.splice(fromIndex, 1)[0];
                allItems.splice(globalIndex, 0, item);
                applyAllItems(allItems);
            });
        }

        function createPreviewWrapper(opts) {
            var globalIndex = opts.globalIndex;
            var type = opts.type;
            var localIndex = opts.localIndex;
            var imgData = opts.imgData;
            var file = opts.file;

            var wrapper = document.createElement('div');
            wrapper.className = 'photo-preview-wrapper';
            wrapper.setAttribute('data-global-index', globalIndex);
            wrapper.setAttribute('data-type', type);
            wrapper.setAttribute('data-local-index', localIndex);
            if (type === 'existing') wrapper.setAttribute('data-image-id', imgData.id);
            wrapper.draggable = true;

            var imgEl = document.createElement('img');
            imgEl.className = 'photo-preview-img';
            imgEl.alt = 'Aperçu';

            if (type === 'existing') {
                imgEl.src = '../uploads/listings/' + imgData.path;
            } else {
                var reader = new FileReader();
                reader.onload = function(e) {
                    imgEl.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }

            var badge = document.createElement('span');
            badge.className = 'photo-order-badge';
            badge.textContent = globalIndex + 1;

            var mainBtn = document.createElement('button');
            mainBtn.type = 'button';
            mainBtn.className = 'photo-main-btn' + (globalIndex === 0 ? ' active' : '');
            mainBtn.innerHTML = '<i class="fas fa-star"></i>';
            mainBtn.title = 'Définir comme image principale';

            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'photo-remove-btn';
            removeBtn.innerHTML = '<i class="fas fa-times"></i>';
            removeBtn.title = 'Supprimer cette photo';

            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (type === 'existing') {
                    existingImages.splice(localIndex, 1);
                } else {
                    newFiles.splice(localIndex, 1);
                }
                renderAll();
            });

            mainBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (globalIndex === 0) return;
                var allItems = buildAllItems();
                var item = allItems.splice(globalIndex, 1)[0];
                allItems.unshift(item);
                applyAllItems(allItems);
            });

            setupDragDrop(wrapper, globalIndex);

            wrapper.appendChild(imgEl);
            wrapper.appendChild(badge);
            wrapper.appendChild(mainBtn);
            wrapper.appendChild(removeBtn);
            return wrapper;
        }

        photoInput.addEventListener('change', function() {
            var files = Array.from(this.files);
            var remaining = maxFiles - getTotalCount();
            if (files.length > remaining) {
                files = files.slice(0, remaining);
                alert('Vous ne pouvez ajouter que ' + maxFiles + ' photos maximum.');
            }
            if (files.length === 0) return;
            processAndAddFiles(files, function(compressed) {
                newFiles = newFiles.concat(compressed);
                renderAll();
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
            var files = Array.from(e.dataTransfer.files).filter(function(f) {
                return f.type.startsWith('image/');
            });
            var remaining = maxFiles - getTotalCount();
            if (files.length > remaining) {
                files = files.slice(0, remaining);
                alert('Vous ne pouvez ajouter que ' + maxFiles + ' photos maximum.');
            }
            if (files.length === 0) return;
            processAndAddFiles(files, function(compressed) {
                newFiles = newFiles.concat(compressed);
                renderAll();
            });
        });

        // Initialiser : remplacer les éléments PHP statiques par les éléments JS interactifs
        renderAll();
    </script>
    <script>
        // Custom dropdown
        (function() {
            var selects = document.querySelectorAll('.sell-select');

            selects.forEach(function(nativeSelect) {
                var wrapper = document.createElement('div');
                wrapper.className = 'custom-select-wrapper';

                var trigger = document.createElement('div');
                trigger.className = 'custom-select-trigger';
                trigger.setAttribute('tabindex', '0');
                trigger.setAttribute('role', 'listbox');

                var textSpan = document.createElement('span');
                textSpan.className = 'custom-select-text';

                var arrow = document.createElement('span');
                arrow.className = 'custom-select-arrow';
                arrow.innerHTML = '<i class="fas fa-chevron-down"></i>';

                trigger.appendChild(textSpan);
                trigger.appendChild(arrow);

                var panel = document.createElement('div');
                panel.className = 'custom-select-panel';

                var selectedOpt = nativeSelect.options[nativeSelect.selectedIndex];
                var hasValue = selectedOpt && selectedOpt.value !== '';
                textSpan.textContent = hasValue ? selectedOpt.text : 'Choisir...';
                if (!hasValue) trigger.classList.add('placeholder');

                Array.from(nativeSelect.options).forEach(function(opt) {
                    if (opt.disabled && opt.value === '') return;

                    var optDiv = document.createElement('div');
                    optDiv.className = 'custom-select-option';
                    if (opt.selected && opt.value !== '') optDiv.classList.add('selected');
                    optDiv.setAttribute('data-value', opt.value);
                    optDiv.setAttribute('role', 'option');

                    var optText = document.createElement('span');
                    optText.textContent = opt.text;

                    var checkIcon = document.createElement('i');
                    checkIcon.className = 'fas fa-check check-icon';

                    optDiv.appendChild(optText);
                    optDiv.appendChild(checkIcon);

                    optDiv.addEventListener('click', function(e) {
                        e.stopPropagation();
                        nativeSelect.value = opt.value;
                        textSpan.textContent = opt.text;
                        trigger.classList.remove('placeholder');

                        panel.querySelectorAll('.custom-select-option').forEach(function(o) {
                            o.classList.remove('selected');
                        });
                        optDiv.classList.add('selected');

                        trigger.classList.remove('open');
                        panel.classList.remove('open');

                        nativeSelect.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    });

                    panel.appendChild(optDiv);
                });

                nativeSelect.parentNode.insertBefore(wrapper, nativeSelect);
                wrapper.appendChild(trigger);
                wrapper.appendChild(panel);
                nativeSelect.style.display = 'none';
                wrapper.appendChild(nativeSelect);

                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isOpen = trigger.classList.contains('open');

                    document.querySelectorAll('.custom-select-trigger.open').forEach(function(t) {
                        t.classList.remove('open');
                        t.parentNode.querySelector('.custom-select-panel').classList.remove('open');
                    });

                    if (!isOpen) {
                        trigger.classList.add('open');
                        panel.classList.add('open');
                    }
                });

                trigger.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        trigger.click();
                    } else if (e.key === 'Escape') {
                        trigger.classList.remove('open');
                        panel.classList.remove('open');
                    }
                });
            });

            document.addEventListener('click', function() {
                document.querySelectorAll('.custom-select-trigger.open').forEach(function(t) {
                    t.classList.remove('open');
                    t.parentNode.querySelector('.custom-select-panel').classList.remove('open');
                });
            });
        })();
    </script>
    <script src="../styles/theme.js"></script>
    <script src="../styles/form-validation.js"></script>
</body>

</html>