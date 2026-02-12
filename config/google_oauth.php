<?php

// Configuration Google OAuth 2.0
// IMPORTANT : Remplacer les valeurs ci-dessous par vos identifiants Google Cloud Console
// https://console.cloud.google.com/apis/credentials

define('GOOGLE_CLIENT_ID', '194449123581-g833377olkfj16lqhjlnemvt4u6106vk.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/market-plier/inscription-connexion/google_callback.php');

// Endpoints Google OAuth 2.0
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

// Scopes demandés
define('GOOGLE_SCOPES', 'openid email profile');
