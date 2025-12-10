<?php
// admin_protection.php - CORRIGÉ
// Démarrer la session UNIQUEMENT si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CORRECTION : Éviter la déclaration multiple
// ============================================
if (!function_exists('getClientIp')) {
    function getClientIp() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        // Pour les proxies, prendre le premier IP si liste
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return $ip;
    }
}

// ============================================
// VÉRIFICATION DE LA SESSION ET DU RÔLE
// ============================================

// Définir les rôles autorisés (ajuster selon vos besoins)
$allowed_roles = ['superAdmin', 'admin', 'moderator'];

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    error_log("Redirection login: user_id non défini");
    header('Location: login.php?error=not_logged_in');
    exit();
}

// Vérifier si le rôle est défini
if (!isset($_SESSION['user_role']) || empty($_SESSION['user_role'])) {
    error_log("Redirection login: user_role non défini");
    header('Location: login.php?error=no_role');
    exit();
}

// Vérifier si le rôle est autorisé
$user_role = $_SESSION['user_role'];
if (!in_array($user_role, $allowed_roles)) {
    error_log("Redirection login: rôle non autorisé - " . $user_role);
    header('Location: login.php?error=insufficient_privileges&role=' . urlencode($user_role));
    exit();
}

// Optionnel : Vérifier si le compte est actif
if (isset($_SESSION['user_active']) && $_SESSION['user_active'] != 1) {
    error_log("Redirection login: compte inactif");
    header('Location: login.php?error=account_inactive');
    exit();
}

// Vérifier le timeout de session (optionnel)
$session_timeout = 3600; // 1 heure en secondes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // Session expirée
    session_unset();
    session_destroy();
    error_log("Redirection login: session expirée");
    header('Location: login.php?error=session_expired');
    exit();
}

// Mettre à jour le timestamp de dernière activité
$_SESSION['last_activity'] = time();

// Debug mode : afficher les infos de session si demandé
if (isset($_GET['debug_session']) && $_GET['debug_session'] == 1) {
    echo '<pre>Session debug:<br>';
    print_r($_SESSION);
    echo '</pre>';
}
?>