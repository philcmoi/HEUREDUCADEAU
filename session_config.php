<?php
// ============================================
// CONFIGURATION CENTRALISÉE DES SESSIONS
// ============================================

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    // Configuration uniforme des sessions
    $sessionPath = __DIR__ . '/sessions';
    if (!is_dir($sessionPath) && is_writable(__DIR__)) {
        mkdir($sessionPath, 0755, true);
    }
    
    ini_set('session.save_path', $sessionPath);
    ini_set('session.gc_maxlifetime', 86400); // 24 heures
    ini_set('session.cookie_lifetime', 86400);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

// ============================================
// CONSTANTES DE SESSION
// ============================================
define('SESSION_KEY_PANIER', 'panier');
define('SESSION_KEY_PANIER_ID', 'panier_id');
define('SESSION_KEY_CHECKOUT', 'checkout');
define('SESSION_KEY_COMMANDE', 'commande_en_cours');
define('SESSION_KEY_CLIENT_ID', 'client_id');
define('SESSION_KEY_MESSAGES', 'messages');
define('SESSION_KEY_ERRORS', 'checkout_errors');

// ============================================
// STRUCTURES STANDARD
// ============================================

/**
 * Structure standard d'un item de panier
 */
define('PANIER_ITEM_STRUCTURE', json_encode([
    'id_produit' => null,
    'quantite' => 1,
    'prix' => 0,
    'nom' => '',
    'reference' => '',
    'image' => '',
    'date_ajout' => null,
    'date_maj' => null
]));

/**
 * Structure standard du checkout
 */
define('CHECKOUT_STRUCTURE', json_encode([
    'panier_id' => null,
    'client_id' => null,
    'client_email' => null,
    'adresse_livraison' => [],
    'adresse_facturation' => [],
    'mode_livraison' => 'standard',
    'emballage_cadeau' => false,
    'instructions' => null,
    'etape' => 'livraison',
    'date_creation' => null,
    'date_modification' => null,
    'validation' => [
        'panier_valide' => false,
        'adresse_valide' => false,
        'paiement_autorise' => false
    ]
]));

// ============================================
// FONCTIONS DE GESTION DE SESSION
// ============================================

/**
 * Initialiser/Réinitialiser le panier avec la structure standard
 */
function initPanier() {
    $_SESSION[SESSION_KEY_PANIER] = [];
    $_SESSION[SESSION_KEY_PANIER_ID] = null;
}

/**
 * Initialiser/Réinitialiser le checkout avec la structure standard
 */
function initCheckout($panier_id = null) {
    $_SESSION[SESSION_KEY_CHECKOUT] = json_decode(CHECKOUT_STRUCTURE, true);
    $_SESSION[SESSION_KEY_CHECKOUT]['panier_id'] = $panier_id;
    $_SESSION[SESSION_KEY_CHECKOUT]['date_creation'] = date('Y-m-d H:i:s');
    $_SESSION[SESSION_KEY_CHECKOUT]['date_modification'] = date('Y-m-d H:i:s');
}

/**
 * Ajouter un message en session
 */
function addSessionMessage($message, $type = 'success') {
    if (!isset($_SESSION[SESSION_KEY_MESSAGES])) {
        $_SESSION[SESSION_KEY_MESSAGES] = [];
    }
    $_SESSION[SESSION_KEY_MESSAGES][] = [
        'message' => $message,
        'type' => $type,
        'date' => date('Y-m-d H:i:s')
    ];
}

/**
 * Récupérer et effacer les messages
 */
function getSessionMessages() {
    $messages = $_SESSION[SESSION_KEY_MESSAGES] ?? [];
    unset($_SESSION[SESSION_KEY_MESSAGES]);
    return $messages;
}

/**
 * Ajouter des erreurs de formulaire
 */
function addCheckoutErrors($errors) {
    $_SESSION[SESSION_KEY_ERRORS] = $errors;
}

/**
 * Récupérer et effacer les erreurs
 */
function getCheckoutErrors() {
    $errors = $_SESSION[SESSION_KEY_ERRORS] ?? [];
    unset($_SESSION[SESSION_KEY_ERRORS]);
    return $errors;
}

/**
 * Nettoyer complètement la session de l'utilisateur
 */
function cleanUserSession() {
    unset($_SESSION[SESSION_KEY_PANIER]);
    unset($_SESSION[SESSION_KEY_PANIER_ID]);
    unset($_SESSION[SESSION_KEY_CHECKOUT]);
    unset($_SESSION[SESSION_KEY_COMMANDE]);
    unset($_SESSION[SESSION_KEY_CLIENT_ID]);
    // Garder les messages si besoin
}
?>