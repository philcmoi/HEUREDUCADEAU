<?php
/**
 * admin_protection.php - VERSION SIMPLIFIÉE SANS ERREURS
 * @version 1.0 - Stable
 */

// ============ CLASSE CSRFProtection ============
if (!class_exists('CSRFProtection')) {
    class CSRFProtection {
        private static $tokenName = 'admin_csrf_token';
        
        public static function generateToken() {
            if (!isset($_SESSION[self::$tokenName])) {
                $_SESSION[self::$tokenName] = bin2hex(random_bytes(32));
            }
            return $_SESSION[self::$tokenName];
        }
        
        public static function validateToken($token) {
            if (!isset($_SESSION[self::$tokenName])) {
                return false;
            }
            
            $isValid = hash_equals($_SESSION[self::$tokenName], $token);
            
            if ($isValid) {
                unset($_SESSION[self::$tokenName]);
            }
            
            return $isValid;
        }
    }
}

// ============ FONCTIONS DE BASE ============
function isAuthenticated() {
    return isset($_SESSION['admin_logged_in']) && 
           $_SESSION['admin_logged_in'] === true &&
           isset($_SESSION['admin_username']);
}

function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    // Vérifier les headers proxy
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED'];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && filter_var($_SERVER[$header], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER[$header];
            break;
        }
    }
    
    return $ip;
}

function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ============ FONCTION DE CONNEXION ============
function adminLogin($username, $password) {
    // Mode démo simple
    $validUsers = [
        'admin' => password_hash('admin123', PASSWORD_DEFAULT),
        'superadmin' => password_hash('SuperAdmin123!', PASSWORD_DEFAULT)
    ];
    
    // Nettoyer l'input
    $username = cleanInput($username);
    
    // Vérifier l'utilisateur
    if (isset($validUsers[$username]) && password_verify($password, $validUsers[$username])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_role'] = ($username === 'superadmin') ? 'superadmin' : 'admin';
        $_SESSION['admin_ip'] = getClientIp();
        $_SESSION['last_activity'] = time();
        
        return [
            'success' => true,
            'redirect' => 'dashboard.php'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Nom d\'utilisateur ou mot de passe incorrect.'
        ];
    }
}

// ============ FONCTION DE PROTECTION ============
function requireAdmin($requiredRole = 'editor', $redirectOnFail = true) {
    if (!isAuthenticated()) {
        if ($redirectOnFail) {
            header('Location: login.php?expired=1');
            exit;
        }
        return false;
    }
    
    // Vérification des rôles simple
    $userRole = $_SESSION['admin_role'] ?? 'editor';
    $roleHierarchy = [
        'superadmin' => 4,
        'admin' => 3, 
        'moderator' => 2,
        'editor' => 1
    ];
    
    $userLevel = $roleHierarchy[$userRole] ?? 0;
    $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
    
    if ($userLevel < $requiredLevel) {
        if ($redirectOnFail) {
            header('HTTP/1.0 403 Forbidden');
            echo '<h1>Accès refusé</h1><p>Vous n\'avez pas les permissions nécessaires.</p>';
            exit;
        }
        return false;
    }
    
    return true;
}

// ============ FONCTION DE DÉCONNEXION ============
function adminLogout() {
    $_SESSION = array();
    
    // Détruire le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// ============ FONCTION POUR PAGE PROTÉGÉE ============
function secureAdminPage($requiredRole = 'editor') {
    // Vérifier l'accès
    requireAdmin($requiredRole);
    
    // Ajouter des en-têtes de sécurité basiques
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
}
?>