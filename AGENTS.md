# AGENTS.md

## Projet

Market Plier est une marketplace web en PHP natif (sans framework) avec authentification utilisateur. L'interface est en français. Le projet tourne sur WampServer (Windows) et se sert directement via Apache à l'adresse `http://localhost/market-plier/`.

## Stack technique

- **Backend** : PHP natif avec sessions, PDO (MySQL), protection CSRF
- **Base de données** : MySQL distante (`marketplier`), connexion configurée dans `database/db.php`
- **Frontend** : HTML/CSS custom + Bootstrap 5.3 (via npm)
- **Icônes** : Font Awesome 7 (via npm, `node_modules/@fortawesome/fontawesome-free/`)
- **Polices** : Archivo (variable font, locale dans `assets/fonts/`)
- **Pas de build system** : pas de bundler, pas de transpilation. Les fichiers sont servis directement par Apache.

## Architecture

```
index.php                    # Page d'accueil (vérifie session + rôle admin)
header.php                   # Header partagé (racine)
database/db.php              # Connexion PDO unique, importée via require
database/marketplier.sql     # Dump SQL du schéma (phpMyAdmin)
config/google_oauth.php      # Configuration OAuth Google (client ID/secret)
inscription-connexion/       # Module authentification
  register.php               # Formulaire d'inscription
  handle_register.php        # Traitement POST inscription (validation, hash bcrypt, insertion DB)
  login.php                  # Formulaire de connexion
  handle_login.php           # Traitement POST connexion
  account.php                # Page profil utilisateur (photo, description)
  dashboard.php              # Tableau de bord utilisateur connecté (vérifie is_admin)
  logout.php                 # Destruction de session complète
  google_login.php           # Redirection vers Google OAuth
  google_callback.php        # Callback retour Google OAuth
settings/settings.php        # Page de paramètres utilisateur
styles/                      # CSS custom par page (register.css, account.css, dashboard.css, index.css)
uploads/profiles/            # Photos de profil uploadées (nommées user_{auth_token}_{timestamp}.png)
assets/images/               # Logo SVG
assets/fonts/                # Polices Archivo et Noto Color Emoji
```

## Conventions clés

- **Connexion DB** : Toujours utiliser `require_once 'database/db.php'` (ou `../database/db.php` depuis un sous-dossier). La variable `$pdo` est globale.
- **Sessions** : `session_start()` est appelé en haut de chaque fichier PHP qui en a besoin (pas de fichier bootstrap centralisé).
- **Identification** : La session stocke `$_SESSION['auth_token']` (pas un user ID). Les requêtes DB utilisent `WHERE auth_token = ?` pour identifier l'utilisateur.
- **CSRF** : Chaque formulaire inclut un token CSRF (`$_SESSION['csrf_token']`), vérifié côté serveur et régénéré après usage.
- **Mots de passe** : Hashés avec `password_hash()` / `PASSWORD_BCRYPT` (cost 12).
- **Validation utilisateur** : Username alphanumérique + underscore (3-30 chars), email validé avec `FILTER_VALIDATE_EMAIL`, mot de passe min 6 chars.
- **Redirections** : Les erreurs/succès sont passés via query params (`?error=...`, `?success=...`).
- **Couleur principale** : Vert `#7fb885` (boutons, bordures).
- **Chemins relatifs** : Les includes PHP et les liens CSS/images utilisent des chemins relatifs (adapter selon la profondeur du dossier).
- **Header include** : Avant `include 'header.php'`, définir `$headerBasePath` (chemin relatif vers la racine, `''` ou `'../'`) et `$headerUser` (tableau user ou `null`).

## Base de données

Tables principales :

- `users` : `username` (unique), `email` (unique), `password_hash`, `google_id`, `auth_provider` (local/google/both), `profile_photo`, `is_admin`, `created_at`, `auth_token` (unique, utilisé pour l'identification en session)
- `profile` : `description` (profil utilisateur)
- Pas de colonne `id` auto-incrémentée — l'identification se fait via `auth_token`.

## Authentification Google OAuth

- Flux OAuth 2.0 via `google_login.php` → Google → `google_callback.php`
- Config dans `config/google_oauth.php` (client ID, secret, redirect URI)
- Les utilisateurs Google sont créés avec `auth_provider = 'google'` et `password_hash = NULL`

## Développement

Pas de commandes build/lint/test. Pour développer :

1. S'assurer que WampServer est lancé (Apache + MySQL)
2. `npm install` (une seule fois, pour Bootstrap + Font Awesome)
3. Accéder à `http://localhost/market-plier/` dans le navigateur
4. Modifier les fichiers PHP/CSS directement, rafraîchir le navigateur

## Gotchas

- **Table `profile` vs `profiles`** : Le dump SQL crée `profile` (singulier), mais `account.php` requête `profiles` (pluriel). Vérifier le nom réel en base avant toute requête sur cette table.
- **Credentials DB** : En dur dans `database/db.php` (pas dans `.env`). Le fichier `.env` existe mais est vide.

## Sécurité

- Les identifiants DB sont en dur dans `database/db.php` - ne pas les committer dans un repo public.
- Le `.gitignore` contient des règles pour `profiles/` et `config/google_oauth.php` (partiellement commentées). `node_modules/` devrait y être ajouté.

## grepai - Semantic Code Search

Utiliser grepai comme outil principal pour la recherche sémantique. Fallback sur Grep/Glob si indisponible ou pour la recherche exacte de texte.

```bash
grepai search "user authentication flow" --json --compact
grepai trace callers "handle_register" --json
grepai trace graph "account" --depth 3 --json
```

- Requêtes en anglais, décrire l'intent (pas le nom de fichier)
- `--compact` économise ~80% de tokens, `--json` pour l'intégration AI
