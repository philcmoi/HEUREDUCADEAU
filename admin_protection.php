<?php
// admin_protection.php - Adapté à heureducadeau
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
// CONNEXION À LA BASE DE DONNÉES heureducadeau
// ============================================
$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// ============================================
// VÉRIFICATION DE LA SESSION ET DU RÔLE
// ============================================

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    error_log("Redirection login: admin_id non défini");
    header('Location: login.php?error=not_logged_in');
    exit();
}

// Vérifier si le rôle est défini
if (!isset($_SESSION['admin_role']) || empty($_SESSION['admin_role'])) {
    error_log("Redirection login: admin_role non défini");
    header('Location: login.php?error=no_role');
    exit();
}

// Définir les rôles autorisés selon votre table administrateurs
$allowed_roles = ['superadmin', 'admin', 'moderator', 'editor'];

// Vérifier si le rôle est autorisé
$admin_role = $_SESSION['admin_role'];
if (!in_array($admin_role, $allowed_roles)) {
    error_log("Redirection login: rôle non autorisé - " . $admin_role);
    header('Location: login.php?error=insufficient_privileges&role=' . urlencode($admin_role));
    exit();
}

// Vérifier si le compte est actif dans la base de données
try {
    $sql = "SELECT status FROM administrateurs WHERE id = :id AND username = :username";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username']
    ]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        error_log("Redirection login: admin non trouvé en base");
        session_destroy();
        header('Location: login.php?error=account_not_found');
        exit();
    }
    
    if ($admin['status'] !== 'active') {
        error_log("Redirection login: compte non actif - statut: " . $admin['status']);
        header('Location: login.php?error=account_inactive');
        exit();
    }
} catch(PDOException $e) {
    error_log("Erreur vérification admin: " . $e->getMessage());
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

// Mettre à jour la dernière connexion en base de données
if (isset($_SESSION['admin_id'])) {
    try {
        $sql = "UPDATE administrateurs SET last_login = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $_SESSION['admin_id']]);
    } catch(PDOException $e) {
        error_log("Erreur mise à jour last_login: " . $e->getMessage());
    }
}

// Debug mode : afficher les infos de session si demandé
if (isset($_GET['debug_session']) && $_GET['debug_session'] == 1) {
    echo '<pre>Session debug:<br>';
    print_r($_SESSION);
    echo '</pre>';
}
?>