<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Configuration PayPal - SANDBOX (test)
define('PAYPAL_CLIENT_ID', 'AS123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890');
define('PAYPAL_SECRET', 'ES123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890');
define('PAYPAL_ENVIRONMENT', 'sandbox'); // 'sandbox' ou 'live'

// Configuration du site
define('SITE_URL', 'https://localhost/heureducadeau');
define('CURRENCY', 'EUR');
define('RETURN_URL', SITE_URL . '/paiement-reussi.php');
define('CANCEL_URL', SITE_URL . '/paiement-annule.php');

// Connexion PDO
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BD: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// Fonction pour obtenir l'URL PayPal selon l'environnement
function getPayPalBaseUrl() {
    return PAYPAL_ENVIRONMENT === 'sandbox' 
        ? 'https://api.sandbox.paypal.com' 
        : 'https://api.paypal.com';
}

// Fonction pour obtenir l'URL du SDK PayPal
function getPayPalSDKUrl() {
    return 'https://www.paypal.com/sdk/js?' . http_build_query([
        'client-id' => PAYPAL_CLIENT_ID,
        'currency' => CURRENCY,
        'intent' => 'capture',
        'disable-funding' => 'card,credit',
        'enable-funding' => 'venmo'
    ]);
}
?>