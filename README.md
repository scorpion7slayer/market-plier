# market

## Frameworks

[Bootstrap 5.3 Introduction](https://getbootstrap.com/docs/5.3/getting-started/introduction/)

[fontawesome](https://fontawesome.com/search?ic=free-collection)

[cropper.js](https://fengyuanchen.github.io/cropperjs/)

## Notes

Pour moyen de paiement on va passer par [Stripe](https://stripe.com/)

[Stripe carte de test](https://docs.stripe.com/testing#cards)

## API

[ipapi](https://ipapi.co/)

## Truc a faire

1. Page de recherche pour produits (chercher par catégorie : pays, prix, etc)
2. Fonction panier et acheter
3. Système de commentaire vendeur

## Installation et configuration

Pour mettre en place l'application en local sous Windows (WampServer ou équivalent) :

1. **Récupérer le code**
    - Cloner le dépôt : `git clone https://github.com/scorpion7slayer/market-plier.git`
    - Ouvrir le dossier dans l'IDE.

2. **Dépendances**
    - PHP : aucune dépendance tierce en dehors de celles chargées via Composer.
    - Exécuter `composer install` depuis la racine pour charger la bibliothèque Google/OAuth et autres (fournies dans `vendor/`).
    - Installer les assets front (Bootstrap, FontAwesome) avec npm : `npm install`.
    - Aucune compilation n'est nécessaire ; les fichiers CSS/JS sont servis tels quels.

3. **Base de données**
    - Démarrer MySQL (via WampServer, MAMP, DDEV, etc.).
    - Créer une base `marketplier` (ou ajuster le nom dans `database/db.php`).
    - Importer le schéma SQL : ouvrir `database/marketplier.sql` dans phpMyAdmin ou `mysql < database/marketplier.sql`.
    - Vérifier le nom de la table de profil (`profile` vs `profiles`) et corriger si besoin (voir NOTE dans CLAUDE.md).
    - Configurer les identifiants MySQL dans `database/db.php` (hôte, utilisateur, mot de passe).

4. **Configuration Google OAuth (optionnel)**
    - Copier `config/google_oauth.php.example` ou créer `config/google_oauth.php` en renseignant `CLIENT_ID`, `CLIENT_SECRET` et `REDIRECT_URI`.
    - Mettre à jour `.gitignore` pour exclure ce fichier si ce n'est pas déjà fait.

5. **Serveur web**
    - Placer le dossier dans le répertoire de votre serveur (e.g. `www/market-plier` sous Wamp).
    - Accéder via `http://localhost/market-plier/`.
    - S'assurer que `session_start()` fonctionne et que le dossier `uploads/` est accessible en écriture.

6. **Utilisation**
    - S'inscrire via `inscription-connexion/register.php` ou se connecter.
    - Les jetons CSRF sont générés automatiquement ; pas d'action requise.
    - Les mots de passe sont hashés avec `password_hash(..., PASSWORD_BCRYPT)`.

7. **Conseils**
    - Lors de la modification des chemins ou de l'ajout de sous-dossiers, ajuster `$headerBasePath` avant d'inclure `header.php`.
    - Stocker les thèmes dans `localStorage` sous la clé `theme` ; voir `header.php` pour l'exemple de lecture/écriture.
