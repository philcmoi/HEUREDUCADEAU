<?php
// session_config_nocookie.php
// Configuration des sessions SANS cookies

function init_session_nocookie() {
    // Désactiver complètement l'utilisation des cookies pour les sessions
    ini_set('session.use_cookies', 0);
    ini_set('session.use_only_cookies', 0);
    ini_set('session.use_trans_sid', 1); // Utiliser les SID dans les URLs
    
    // Définir le chemin de session commun
    $sessionPath = '/var/www/sessions';
    ini_set('session.save_path', $sessionPath);
    ini_set('session.gc_maxlifetime', 86400);
    
    // Nom de session
    session_name('heure_du_cadeau');
    
    // Gérer l'ID de session via GET ou POST
    if (isset($_REQUEST[session_name()])) {
        session_id($_REQUEST[session_name()]);
    }
    
    // Démarrer la session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Initialiser le panier s'il n'existe pas
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    
    // Retourner l'ID de session
    return session_id();
}

// Fonction pour ajouter le SID aux URLs
function add_session_to_url($url) {
    $session_name = session_name();
    $session_id = session_id();
    
    if ($session_id && strpos($url, $session_name . '=') === false) {
        $separator = (strpos($url, '?') === false) ? '?' : '&';
        return $url . $separator . $session_name . '=' . $session_id;
    }
    return $url;
}
?>