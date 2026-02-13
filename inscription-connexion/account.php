<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Plier - Profil Utilisateur</title>
    <!-- Bootstrap local -->
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../styles/account.css">
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo.svg">
</head>

<body>
    <!-- Header -->
    <header>
        <div class="header-top">
            <div class="logo-area">
                <div class="logo-icon">
                    <img src="../assets/images/logo.svg" alt="Logo Market Plier" style="width: 180%; height: 150%;">

                </div>
            </div>

            <div class="header-divider"></div>

            <div class="search-container">
                <input type="text" class="search-bar" placeholder="Rechercher...">
            </div>
        </div>

        <div class="header-bottom">
            <nav>
                <a href="#">vendre</a>
                <a href="#">langues</a>
                <a href="#">aide</a>
                <a href="#">paramètres</a>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container-fluid">
        <!-- Profile Section - en haut -->
        <div class="row">
            <div class="col-12">
                <div class="profile-section">
                    <!-- Profile à gauche -->
                    <aside class="profile-sidebar">
                        <div class="profile-header">
                            <div class="avatar-container">
                                <div class="avatar"></div>
                                <button class="edit-avatar">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>

                            <div class="username-section">
                                <div class="username">Utilisateur</div>
                                <span class="verified">✓</span>
                                <button class="edit-username">
                                    <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" stroke="#1a1a1a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="description-section">
                            <h2 class="description-title">Description</h2>
                            <p class="description-text">jaime les pieds sales</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>

        <!-- Articles Section - en dessous en pleine largeur -->
        <div class="row">
            <div class="col-12">
                <div class="articles-section">
                    <h2 class="section-title">Vos articles</h2>
                    <div class="articles-grid">
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                        <div class="article-card"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Management Link - en bas à gauche -->
    <a href="../inscription-connexion/dashboard.php" class="account-link">Gérer le compte</a>

    <!-- Bootstrap JS local -->
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>