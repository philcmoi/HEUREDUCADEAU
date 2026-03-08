<?php
// session_verification.php - Gestion des sessions et connexion BDD
// VERSION CORRIGÉE FINALE - Optimisée pour performance et stabilité

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Constantes de configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe'); // À modifier selon votre configuration
define('DB_PASS', 'l@99339R'); // À modifier selon votre configuration
define('DB_CHARSET', 'utf8mb4');

// Cache pour les résultats de requêtes fréquentes
$GLOBALS['cart_cache'] = null;
$GLOBALS['cart_cache_time'] = 0;
$GLOBALS['cart_cache_ttl'] = 30; // 30 secondes de cache pour le panier

/**
 * Établit une connexion PDO à la base de données
 * Version optimisée avec gestionnaire d'erreurs amélioré
 * 
 * @return PDO|null Retourne l'objet PDO ou null en cas d'échec
 */
function getPDOConnection() {
    static $pdo = null;
    static $lastError = null;
    
    // Retourner la connexion existante si elle est toujours valide
    if ($pdo !== null) {
        // Vérifier rapidement si la connexion est toujours active
        try {
            $pdo->query("SELECT 1");
            return $pdo;
        } catch (PDOException $e) {
            // Connexion perdue, on va en recréer une nouvelle
            error_log("Connexion PDO perdue, reconnexion...");
            $pdo = null;
        }
    }
    
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true, // Connexion persistante pour meilleures performances
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
            PDO::ATTR_TIMEOUT => 5 // Timeout de connexion réduit
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        
        // Optimisations supplémentaires
        $pdo->exec("SET SESSION sql_mode = 'TRADITIONAL'");
        $pdo->exec("SET SESSION group_concat_max_len = 10000");
        
        error_log("Connexion BDD établie avec succès");
        return $pdo;
        
    } catch (PDOException $e) {
        $errorMsg = "Erreur de connexion BDD: " . $e->getMessage();
        error_log($errorMsg);
        
        // Enregistrer l'erreur pour diagnostic
        $lastError = $errorMsg;
        
        // Journalisation plus détaillée en environnement de développement
        if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
            error_log("DSN utilisé: mysql:host=" . DB_HOST . ";dbname=" . DB_NAME);
            error_log("Code erreur: " . $e->getCode());
        }
        
        return null;
    }
}

/**
 * Vérifie la connexion à la base de données
 * Utile pour les diagnostics
 * 
 * @return bool True si la connexion est OK
 */
function checkDatabaseConnection() {
    $pdo = getPDOConnection();
    
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->query("SELECT 1");
        return $stmt !== false;
    } catch (Exception $e) {
        error_log("checkDatabaseConnection: " . $e->getMessage());
        return false;
    }
}

/**
 * Compte le nombre d'articles dans le panier
 * Version optimisée avec cache pour éviter les appels répétés
 * 
 * @param bool $forceRefresh Forcer le rafraîchissement du cache
 * @return int Nombre total d'articles dans le panier
 */
function countCartItems($forceRefresh = false) {
    // Vérifier le cache
    if (!$forceRefresh && 
        $GLOBALS['cart_cache'] !== null && 
        (time() - $GLOBALS['cart_cache_time']) < $GLOBALS['cart_cache_ttl']) {
        return $GLOBALS['cart_cache'];
    }
    
    $pdo = getPDOConnection();
    if (!$pdo) {
        return 0;
    }
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        // Requête optimisée - UNION ALL est plus rapide que COALESCE
        if ($client_id) {
            // Pour client connecté
            $sql = "
                SELECT COALESCE(SUM(pi.quantite), 0) as total 
                FROM panier p
                INNER JOIN panier_items pi ON p.id_panier = pi.id_panier
                WHERE p.id_client = :client_id 
                  AND p.statut = 'actif'
            ";
            $params = [':client_id' => $client_id];
        } else {
            // Pour client non connecté (session)
            $sql = "
                SELECT COALESCE(SUM(pi.quantite), 0) as total 
                FROM panier p
                INNER JOIN panier_items pi ON p.id_panier = pi.id_panier
                WHERE p.session_id = :session_id 
                  AND p.statut = 'actif'
            ";
            $params = [':session_id' => $session_id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        $total = (int)($result['total'] ?? 0);
        
        // Mettre en cache
        $GLOBALS['cart_cache'] = $total;
        $GLOBALS['cart_cache_time'] = time();
        
        return $total;
        
    } catch (Exception $e) {
        error_log("Erreur countCartItems: " . $e->getMessage());
        
        // En cas d'erreur, retourner 0 mais ne pas cacher l'erreur
        return 0;
    }
}

/**
 * Récupère le contenu complet du panier
 * Version optimisée avec jointures
 * 
 * @return array Tableau contenant les articles du panier
 */
function getCartItems() {
    $pdo = getPDOConnection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Erreur de connexion'];
    }
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        if ($client_id) {
            $sql = "
                SELECT 
                    pi.id_item,
                    pi.id_produit,
                    pi.quantite,
                    pi.prix_unitaire,
                    pi.date_ajout,
                    p.nom,
                    p.reference,
                    p.slug,
                    p.quantite_stock,
                    (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image,
                    (pi.quantite * pi.prix_unitaire) as prix_total
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pa.id_client = :client_id 
                  AND pa.statut = 'actif'
                  AND p.statut = 'actif'
                ORDER BY pi.date_ajout DESC
            ";
            $params = [':client_id' => $client_id];
        } else {
            $sql = "
                SELECT 
                    pi.id_item,
                    pi.id_produit,
                    pi.quantite,
                    pi.prix_unitaire,
                    pi.date_ajout,
                    p.nom,
                    p.reference,
                    p.slug,
                    p.quantite_stock,
                    (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image,
                    (pi.quantite * pi.prix_unitaire) as prix_total
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pa.session_id = :session_id 
                  AND pa.statut = 'actif'
                  AND p.statut = 'actif'
                ORDER BY pi.date_ajout DESC
            ";
            $params = [':session_id' => $session_id];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        
        // Calculer les totaux
        $sous_total = 0;
        $total_items = 0;
        
        foreach ($items as &$item) {
            $sous_total += floatval($item['prix_total']);
            $total_items += intval($item['quantite']);
            
            // Vérifier la disponibilité
            $item['disponible'] = intval($item['quantite_stock']) >= intval($item['quantite']);
        }
        
        return [
            'success' => true,
            'panier' => $items,
            'sous_total' => round($sous_total, 2),
            'total_items' => $total_items,
            'timestamp' => time()
        ];
        
    } catch (Exception $e) {
        error_log("Erreur getCartItems: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur lors de la récupération du panier'
        ];
    }
}

/**
 * Vérifie si l'utilisateur est connecté
 * 
 * @return bool True si l'utilisateur est connecté
 */
function isUserLoggedIn() {
    return isset($_SESSION['id_client']) && $_SESSION['id_client'] > 0;
}

/**
 * Récupère l'ID du client connecté
 * 
 * @return int|null ID du client ou null si non connecté
 */
function getCurrentClientId() {
    return $_SESSION['id_client'] ?? null;
}

/**
 * Nettoie les paniers expirés
 * À appeler périodiquement
 * 
 * @param int $days Nombre de jours avant expiration
 * @return int Nombre de paniers nettoyés
 */
function cleanupExpiredCarts($days = 30) {
    $pdo = getPDOConnection();
    if (!$pdo) {
        return 0;
    }
    
    try {
        // Marquer les paniers inactifs comme abandonnés
        $sql = "
            UPDATE panier 
            SET statut = 'abandonne' 
            WHERE statut = 'actif' 
              AND date_modification < DATE_SUB(NOW(), INTERVAL :days DAY)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':days' => $days]);
        
        $updated = $stmt->rowCount();
        
        if ($updated > 0) {
            error_log("Cleanup: $updated paniers expirés nettoyés");
        }
        
        return $updated;
        
    } catch (Exception $e) {
        error_log("Erreur cleanupExpiredCarts: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fonction utilitaire pour logger les erreurs
 * 
 * @param string $message Message d'erreur
 * @param array $context Contexte supplémentaire
 */
function logError($message, $context = []) {
    $logEntry = date('Y-m-d H:i:s') . " - " . $message;
    
    if (!empty($context)) {
        $logEntry .= " - Contexte: " . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    
    error_log($logEntry);
}

/**
 * Fonction utilitaire pour obtenir des informations sur la session
 * 
 * @return array Informations sur la session
 */
function getSessionInfo() {
    return [
        'session_id' => session_id(),
        'client_id' => $_SESSION['id_client'] ?? null,
        'is_logged_in' => isUserLoggedIn(),
        'cart_count' => countCartItems(),
        'session_status' => session_status(),
        'timestamp' => time()
    ];
}

// Initialisation - nettoyage périodique (10% des requêtes)
if (rand(1, 100) <= 10) {
    // Nettoyer les paniers expirés (30 jours)
    cleanupExpiredCarts(30);
    
    // Vider le cache si trop vieux
    if ($GLOBALS['cart_cache_time'] > 0 && (time() - $GLOBALS['cart_cache_time']) > 3600) {
        $GLOBALS['cart_cache'] = null;
        $GLOBALS['cart_cache_time'] = 0;
    }
}

// Désactiver le rapport d'erreurs en production
if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'development') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Vérification rapide de la BDD au chargement
if (!isset($GLOBALS['db_check_done'])) {
    $GLOBALS['db_check_done'] = true;
    if (!checkDatabaseConnection()) {
        error_log("ATTENTION: La connexion à la base de données a échoué au démarrage");
    }
}

?>