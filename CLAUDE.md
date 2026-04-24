# CLAUDE.md

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

## grepai - Semantic Code Search - IMPORTANT

Utiliser grepai comme outil principal pour la recherche sémantique. Fallback sur Grep/Glob si indisponible ou pour la recherche exacte de texte.

```bash
grepai search "user authentication flow" --json --compact
grepai trace callers "handle_register" --json
grepai trace graph "account" --depth 3 --json
```

- Requêtes en anglais, décrire l'intent (pas le nom de fichier)
- `--compact` économise ~80% de tokens, `--json` pour l'intégration AI

## Workflow Orchestration

### 1. Plan Node Default

- Enter plan mode for ANY non-trivial task (3+ steps or architectural decisions)
- If something goes sideways, STOP and re-plan immediately - don't keep pushing
- Use plan mode for verification steps, not just building
- Write detailed specs upfront to reduce ambiguity

### 2. Subagent Strategy

- Use subagents liberally to keep main context window clean
- Offload research, exploration, and parallel analysis to subagents
- For complex problems, throw more compute at it via subagents
- One tack per subagent for focused execution

### 3. Self-Improvement Loop

- After ANY correction from the user: update `tasks/lessons.md` with the pattern
- Write rules for yourself that prevent the same mistake
- Ruthlessly iterate on these lessons until mistake rate drops
- Review lessons at session start for relevant project

### 4. Verification Before Done

- Never mark a task complete without proving it works
- Diff behavior between main and your changes when relevant
- Ask yourself: "Would a staff engineer approve this?"
- Run tests, check logs, demonstrate correctness

### 5. Demand Elegance (Balanced)

- For non-trivial changes: pause and ask "is there a more elegant way?"
- If a fix feels hacky: "Knowing everything I know now, implement the elegant solution"
- Skip this for simple, obvious fixes - don't over-engineer
- Challenge your own work before presenting it

### 6. Autonomous Bug Fixing

- When given a bug report: just fix it. Don't ask for hand-holding
- Point at logs, errors, failing tests - then resolve them
- Zero context switching required from the user
- Go fix failing CI tests without being told how

## Task Management

1. **Plan First**: Write plan to `tasks/todo.md` with checkable items
2. **Verify Plan**: Check in before starting implementation
3. **Track Progress**: Mark items complete as you go
4. **Explain Changes**: High-level summary at each step
5. **Document Results**: Add review section to `tasks/todo.md`
6. **Capture Lessons**: Update `tasks/lessons.md` after corrections

## Core Principles

- **Simplicity First**: Make every change as simple as possible. Impact minimal code.
- **No Laziness**: Find root causes. No temporary fixes. Senior developer standards.
- **Minimat Impact**: Changes should only touch what's necessary. Avoid introducing bugs.
