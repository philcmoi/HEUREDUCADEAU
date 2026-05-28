<?php
// ============================================
// CONFIGURATION CENTRALISÉE POUR LES PAIEMENTS
// ============================================

// Activer l'affichage des erreurs en développement (désactiver en production)
if (file_exists(__DIR__ . '/.env.php')) {
    require_once __DIR__ . '/.env.php';
} else {
    // Configuration par défaut (à remplacer par variables d'environnement en prod)
    define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
    define('PAYPAL_CLIENT_SECRET', getenv('PAYPAL_CLIENT_SECRET') ?: 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
    define('PAYPAL_MODE', getenv('PAYPAL_MODE') ?: 'sandbox'); // 'sandbox' ou 'live'
}

define('PAYPAL_BASE_URL', (PAYPAL_MODE === 'sandbox') 
    ? 'https://api-m.sandbox.paypal.com' 
    : 'https://api-m.paypal.com');

define('PAYPAL_WEB_URL', (PAYPAL_MODE === 'sandbox') 
    ? 'https://www.sandbox.paypal.com' 
    : 'https://www.paypal.com');

// Configuration des emails (à déplacer dans .env.php en production)
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: 'contact@heureducadeau.fr');
define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'HEURE DU CADEAU');
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');

// Configuration du site
define('SITE_URL', getenv('SITE_URL') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/'));

// Fonction pour obtenir la configuration PayPal
function getPayPalConfig() {
    return [
        'client_id' => PAYPAL_CLIENT_ID,
        'client_secret' => PAYPAL_CLIENT_SECRET,
        'mode' => PAYPAL_MODE,
        'api_url' => PAYPAL_BASE_URL,
        'web_url' => PAYPAL_WEB_URL
    ];
}

// Fonction pour obtenir la configuration SMTP
function getSmtpConfig() {
    return [
        'host' => SMTP_HOST,
        'port' => SMTP_PORT,
        'username' => SMTP_USERNAME,
        'password' => SMTP_PASSWORD,
        'from_email' => SMTP_FROM_EMAIL,
        'from_name' => SMTP_FROM_NAME,
        'secure' => SMTP_SECURE
    ];
}

// Vérifier si les emails sont configurés
function isEmailConfigured() {
    return !empty(SMTP_USERNAME) && !empty(SMTP_PASSWORD);
}
?>