# Market Plier

Marketplace web en PHP permettant aux utilisateurs d'acheter et vendre des articles entre particuliers. Paiements intégrés via Stripe, messagerie en temps réel, notifications push et connexion Google OAuth.

## Fonctionnalités

- Inscription / connexion locale et via Google OAuth
- Dépôt et gestion d'annonces (photos, catégorie, état, localisation)
- Paiement sécurisé via Stripe (Checkout + Connect pour les vendeurs)
- Messagerie instantanée entre acheteurs et vendeurs (SSE)
- Notifications push (Web Push API)
- Système de favoris et d'avis
- Tableau de bord admin (modération, paramètres du site)
- Thème clair / sombre persistant

## Stack technique

| Couche | Technologie |
|--------|-------------|
| Backend | PHP 8.1+ (PDO, sessions, CSRF) |
| Base de données | MySQL 8.0+ |
| Frontend | Bootstrap 5.3 · Font Awesome 7 |
| Paiements | Stripe Checkout + Stripe Connect |
| Auth tierce | Google OAuth 2.0 |
| Notifications | Web Push (minishlink/web-push) |
| Dépendances PHP | Composer |
| Dépendances JS/CSS | npm (pas de bundler) |

## Installation

### Prérequis

- PHP 8.1+
- MySQL 8.0+
- Composer
- Node.js / npm
- Un serveur web (Apache, Nginx, DDEV…)

### 1. Cloner le dépôt

```bash
git clone https://github.com/scorpion7slayer/market-plier.git
cd market-plier
```

### 2. Installer les dépendances

```bash
composer install
npm install
```

### 3. Variables d'environnement

Copier le fichier exemple et remplir les valeurs :

```bash
cp .env.example .env
```

| Variable | Description |
|----------|-------------|
| `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` | Connexion MySQL |
| `APP_URL` | URL de base sans slash final (ex. `http://localhost/market-plier`) |
| `STRIPE_SECRET_KEY` / `STRIPE_PUBLIC_KEY` / `STRIPE_WEBHOOK_SECRET` | Clés Stripe ([dashboard.stripe.com](https://dashboard.stripe.com/apikeys)) |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | OAuth Google ([console.cloud.google.com](https://console.cloud.google.com)) |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` / `VAPID_SUBJECT` | Notifications push Web Push |

Générer les clés VAPID :

```php
php -r "print_r(Minishlink\WebPush\VAPID::createVapidKeys());"
```

### 4. Base de données

Créer une base MySQL puis importer le schéma :

```bash
mysql -u <user> -p -e "CREATE DATABASE marketplier CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u <user> -p marketplier < database/example.sql
```

Cartes de test Stripe : [docs.stripe.com/testing#cards](https://docs.stripe.com/testing#cards)

### 5. Permissions

S'assurer que les dossiers d'upload sont accessibles en écriture :

```bash
chmod -R 775 uploads/
```

### 7. Lancer

Placer le dossier dans le répertoire web de votre serveur et accéder à :

```
http://localhost/market-plier/
```

## Structure du projet

```
index.php                    # Page d'accueil
header.php / footer.php      # Mise en page partagée
database/
  db.php                     # Connexion PDO
  example.sql                # Schéma de la base (sans données)
config/                      # Clés API (non versionnées)
inscription-connexion/       # Auth (register, login, Google OAuth)
shop/                        # Catalogue des annonces
messagerie/                  # Chat temps réel (SSE)
orders/                      # Commandes et suivi
stripe/                      # Webhooks Stripe
notifications/               # Notifications push
favoris/                     # Gestion des favoris
settings/                    # Paramètres utilisateur
assets/                      # Images, polices
styles/                      # CSS custom par page
uploads/                     # Médias uploadés (non versionnés)
```

## Sécurité

- Tokens CSRF sur tous les formulaires
- Mots de passe hashés avec `PASSWORD_BCRYPT` (cost 12)
- Identification via `auth_token` (jamais d'ID numérique exposé)
- Voir [security.md](security.md) pour l'historique des correctifs

## Licence

MIT — voir [LICENSE](LICENSE)
