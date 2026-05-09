<?php
// ============================================
// session_verification.php - Gestion des sessions et connexion BDD
// VERSION CORRIGÉE FINALE - Compatible panier.php
// ============================================

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONSTANTES DE SESSION
// ============================================
if (!defined('SESSION_KEY_PANIER')) {
    define('SESSION_KEY_PANIER', 'panier');
}
if (!defined('SESSION_KEY_PANIER_ID')) {
    define('SESSION_KEY_PANIER_ID', 'panier_id');
}
if (!defined('SESSION_KEY_CLIENT_ID')) {
    define('SESSION_KEY_CLIENT_ID', 'id_client');
}
if (!defined('SESSION_KEY_CHECKOUT')) {
    define('SESSION_KEY_CHECKOUT', 'checkout');
}
if (!defined('SESSION_KEY_COMMANDE')) {
    define('SESSION_KEY_COMMANDE', 'commande_en_cours');
}

// ============================================
// CONSTANTES DE CONFIGURATION BDD
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');
define('DB_CHARSET', 'utf8mb4');

// Cache pour les résultats
$GLOBALS['cart_cache'] = null;
$GLOBALS['cart_cache_time'] = 0;
$GLOBALS['cart_cache_ttl'] = 30;

// ============================================
// FONCTIONS DE CONNEXION BDD
// ============================================

/**
 * Établit une connexion PDO à la base de données
 * @return PDO|null Retourne l'objet PDO ou null en cas d'échec
 */
function getPDOConnection() {
    static $pdo = null;
    
    // Retourner la connexion existante si elle est toujours valide
    if ($pdo !== null) {
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
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE " . DB_CHARSET . "_unicode_ci",
            PDO::ATTR_TIMEOUT => 5
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        error_log("Connexion BDD établie avec succès");
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Erreur de connexion BDD: " . $e->getMessage());
        return null;
    }
}

/**
 * Vérifie la connexion à la base de données
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

// ============================================
// FONCTIONS DE GESTION DU PANIER (SESSION UNIQUEMENT)
// ============================================

/**
 * Initialise le panier en session s'il n'existe pas
 */
function initPanier() {
    if (!isset($_SESSION[SESSION_KEY_PANIER])) {
        $_SESSION[SESSION_KEY_PANIER] = [];
    }
}

/**
 * Vérifie si le panier contient des articles
 * @return bool
 */
function hasValidCart() {
    initPanier();
    return !empty($_SESSION[SESSION_KEY_PANIER]);
}

/**
 * Compte le nombre d'articles dans le panier (session uniquement)
 * @param bool $forceRefresh Forcer le rafraîchissement du cache
 * @return int Nombre total d'articles
 */
function countCartItemsSession($forceRefresh = false) {
    // Vérifier le cache
    if (!$forceRefresh && 
        $GLOBALS['cart_cache'] !== null && 
        (time() - $GLOBALS['cart_cache_time']) < $GLOBALS['cart_cache_ttl']) {
        return $GLOBALS['cart_cache'];
    }
    
    initPanier();
    $total = 0;
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $total += intval($item['quantite'] ?? 0);
    }
    
    // Mettre en cache
    $GLOBALS['cart_cache'] = $total;
    $GLOBALS['cart_cache_time'] = time();
    
    return $total;
}

/**
 * Alias pour compatibilité ascendante
 */
function countCartItems($forceRefresh = false) {
    return countCartItemsSession($forceRefresh);
}

/**
 * Récupère le contenu complet du panier (session uniquement)
 * @return array
 */
function getCartItemsSession() {
    initPanier();
    return $_SESSION[SESSION_KEY_PANIER];
}

/**
 * Alias pour compatibilité ascendante
 */
function getCartItems() {
    return getCartItemsSession();
}

/**
 * Récupère le contenu complet du panier depuis la BDD avec format API
 * @param PDO $pdo Connexion PDO
 * @return array Tableau formaté pour l'API
 */
function getCartItemsFromBDD($pdo) {
    if (!$pdo) {
        return [
            'success' => false,
            'message' => 'Erreur de connexion à la base de données',
            'panier' => [],
            'sous_total' => 0,
            'total_items' => 0
        ];
    }
    
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    try {
        // Récupérer le panier actif
        if ($client_id) {
            $stmt = $pdo->prepare("
                SELECT id_panier FROM panier 
                WHERE id_client = ? AND statut = 'actif'
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id_panier FROM panier 
                WHERE session_id = ? AND statut = 'actif'
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$session_id]);
        }
        
        $panier = $stmt->fetch();
        
        if (!$panier) {
            return [
                'success' => true,
                'panier' => [],
                'sous_total' => 0,
                'total_items' => 0
            ];
        }
        
        $id_panier = $panier['id_panier'];
        
        // Récupérer les articles avec les infos produits
        $stmt = $pdo->prepare("
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
                p.description_courte,
                (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image,
                (pi.quantite * pi.prix_unitaire) as prix_total
            FROM panier_items pi
            INNER JOIN produits p ON pi.id_produit = p.id_produit
            WHERE pi.id_panier = ?
            ORDER BY pi.date_ajout DESC
        ");
        $stmt->execute([$id_panier]);
        $items = $stmt->fetchAll();
        
        // Calculer les totaux
        $sous_total = 0;
        $total_items = 0;
        
        foreach ($items as &$item) {
            $sous_total += floatval($item['prix_total'] ?? 0);
            $total_items += intval($item['quantite'] ?? 0);
            
            // Vérifier la disponibilité
            $item['disponible'] = (intval($item['quantite_stock'] ?? 0) >= intval($item['quantite'] ?? 0));
            
            // Image par défaut si aucune
            if (empty($item['image'])) {
                $images_defaut = [
                    1 => 'https://via.placeholder.com/300x300/2c3e50/ffffff?text=Bougie',
                    2 => 'https://via.placeholder.com/300x300/27ae60/ffffff?text=Coffret',
                    3 => 'https://via.placeholder.com/300x300/3498db/ffffff?text=Montre',
                    4 => 'https://via.placeholder.com/300x300/e74c3c/ffffff?text=Bijoux'
                ];
                $item['image'] = $images_defaut[$item['id_produit']] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
            }
        }
        
        return [
            'success' => true,
            'panier' => $items,
            'sous_total' => round($sous_total, 2),
            'total_items' => $total_items,
            'panier_id' => (int)$id_panier
        ];
        
    } catch (Exception $e) {
        error_log("Erreur getCartItemsFromBDD: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur lors de la récupération du panier',
            'panier' => [],
            'sous_total' => 0,
            'total_items' => 0
        ];
    }
}

/**
 * Ajoute un article au panier (session uniquement)
 * @param int $id_produit ID du produit
 * @param int $quantite Quantité
 * @param float $prix Prix unitaire
 * @return bool
 */
function addToCartSession($id_produit, $quantite = 1, $prix = null) {
    initPanier();
    
    // Vérifier si le produit existe déjà
    foreach ($_SESSION[SESSION_KEY_PANIER] as &$item) {
        if ($item['id_produit'] == $id_produit) {
            $item['quantite'] += $quantite;
            if ($prix !== null) {
                $item['prix'] = $prix;
            }
            return true;
        }
    }
    
    // Ajouter un nouvel article
    $_SESSION[SESSION_KEY_PANIER][] = [
        'id_produit' => $id_produit,
        'quantite' => $quantite,
        'prix' => $prix
    ];
    return true;
}

/**
 * Alias pour compatibilité ascendante
 */
function addToCart($id_produit, $quantite = 1, $prix = null) {
    return addToCartSession($id_produit, $quantite, $prix);
}

/**
 * Supprime un article du panier (session uniquement)
 * @param int $id_produit ID du produit
 * @return bool
 */
function removeFromCartSession($id_produit) {
    initPanier();
    foreach ($_SESSION[SESSION_KEY_PANIER] as $key => $item) {
        if ($item['id_produit'] == $id_produit) {
            unset($_SESSION[SESSION_KEY_PANIER][$key]);
            $_SESSION[SESSION_KEY_PANIER] = array_values($_SESSION[SESSION_KEY_PANIER]);
            return true;
        }
    }
    return false;
}

/**
 * Alias pour compatibilité ascendante
 */
function removeFromCart($id_produit) {
    return removeFromCartSession($id_produit);
}

/**
 * Vide complètement le panier (session uniquement)
 */
function clearCartSession() {
    $_SESSION[SESSION_KEY_PANIER] = [];
    unset($_SESSION[SESSION_KEY_PANIER_ID]);
    $GLOBALS['cart_cache'] = null;
}

/**
 * Alias pour compatibilité ascendante
 */
function clearCart() {
    clearCartSession();
}

// ============================================
// FONCTIONS DE GESTION DES PRODUITS
// ============================================

/**
 * Récupère les détails d'un produit
 * @param int $id_produit ID du produit
 * @param PDO|null $pdo Connexion PDO optionnelle
 * @return array|null
 */
function getProductDetails($id_produit, $pdo = null) {
    if (!$pdo) {
        $pdo = getPDOConnection();
    }
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, 
                   c.nom as categorie_nom,
                   (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.id_produit = ? AND p.statut = 'actif'
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if ($produit) {
            // Ajouter une image par défaut si nécessaire
            if (empty($produit['image'])) {
                $images_defaut = [
                    1 => 'https://via.placeholder.com/300x300/2c3e50/ffffff?text=Bougie',
                    2 => 'https://via.placeholder.com/300x300/27ae60/ffffff?text=Coffret',
                    3 => 'https://via.placeholder.com/300x300/3498db/ffffff?text=Montre',
                    4 => 'https://via.placeholder.com/300x300/e74c3c/ffffff?text=Bijoux'
                ];
                $produit['image'] = $images_defaut[$produit['id_produit']] ?? 'img/default-product.jpg';
            }
        }
        
        return $produit ?: null;
        
    } catch (Exception $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return null;
    }
}

// ============================================
// FONCTIONS DE GESTION DE LA LIVRAISON
// ============================================

/**
 * Vérifie si l'utilisateur peut accéder à la page de livraison
 */
function checkLivraisonAccess() {
    if (!hasValidCart()) {
        addSessionMessage('Votre panier est vide.', 'error');
        header('Location: panier.html');
        exit;
    }
}

/**
 * Vérifie si l'utilisateur peut accéder à la page de paiement
 */
function checkPaiementAccess() {
    if (!hasValidCart()) {
        addSessionMessage('Votre panier est vide.', 'error');
        header('Location: panier.html');
        exit;
    }
    
    if (!hasShippingAddress()) {
        addSessionMessage('Veuillez d\'abord renseigner votre adresse de livraison.', 'error');
        header('Location: livraison_form.php');
        exit;
    }
}

/**
 * Vérifie si une adresse de livraison est définie
 * @return bool
 */
function hasShippingAddress() {
    return isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']) && 
           !empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
}

// ============================================
// FONCTIONS DE SYNCHRONISATION PANIER
// ============================================

/**
 * Synchronise le panier session avec la BDD
 * @param PDO $pdo Connexion PDO
 * @param string $session_id ID de session
 */
function synchroniserPanierSessionBDD($pdo, $session_id) {
    if (!$pdo) {
        return;
    }
    
    try {
        // Vérifier si un panier BDD existe pour cette session
        $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE session_id = ? AND statut = 'actif'");
        $stmt->execute([$session_id]);
        $panier_bdd = $stmt->fetch();
        
        if (!$panier_bdd && !empty($_SESSION[SESSION_KEY_PANIER])) {
            // Créer un nouveau panier en BDD
            $stmt = $pdo->prepare("INSERT INTO panier (session_id, statut, date_creation) VALUES (?, 'actif', NOW())");
            $stmt->execute([$session_id]);
            $panier_id = $pdo->lastInsertId();
            $_SESSION[SESSION_KEY_PANIER_ID] = $panier_id;
            
            // Ajouter les items
            $stmt_item = $pdo->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
                $produit = getProductDetails($item['id_produit'], $pdo);
                $prix = $produit['prix_ttc'] ?? $item['prix'] ?? 19.99;
                
                $stmt_item->execute([
                    $panier_id,
                    $item['id_produit'],
                    $item['quantite'],
                    $prix
                ]);
            }
        } elseif ($panier_bdd) {
            $_SESSION[SESSION_KEY_PANIER_ID] = $panier_bdd['id_panier'];
            
            // Récupérer les items de la BDD vers la session si nécessaire
            if (empty($_SESSION[SESSION_KEY_PANIER])) {
                $stmt_items = $pdo->prepare("
                    SELECT id_produit, quantite, prix_unitaire as prix
                    FROM panier_items
                    WHERE id_panier = ?
                ");
                $stmt_items->execute([$panier_bdd['id_panier']]);
                $items = $stmt_items->fetchAll();
                
                $_SESSION[SESSION_KEY_PANIER] = [];
                foreach ($items as $item) {
                    $_SESSION[SESSION_KEY_PANIER][] = [
                        'id_produit' => $item['id_produit'],
                        'quantite' => $item['quantite'],
                        'prix' => $item['prix']
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur synchronisation panier: " . $e->getMessage());
    }
}

// ============================================
// FONCTIONS DE CALCUL DES TOTAUX
// ============================================

/**
 * Calcule les totaux du panier
 * @param array $panier_details Détails du panier
 * @param array $checkout Données de checkout
 * @return array
 */
function calculerTotauxPanier($panier_details, $checkout = []) {
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($panier_details as $item) {
        $sous_total += floatval($item['prix_total'] ?? 0);
        $total_items += intval($item['quantite'] ?? 0);
    }
    
    // Frais de livraison
    $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
    $frais_livraison = 0;
    
    if ($mode_livraison === 'express') {
        $frais_livraison = 9.90;
    } elseif ($mode_livraison === 'relais') {
        $frais_livraison = 4.90;
    } elseif ($sous_total < 50) {
        $frais_livraison = 4.90;
    }
    
    $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
    $total = $sous_total + $frais_livraison + $frais_emballage;
    
    return [
        'sous_total' => round($sous_total, 2),
        'frais_livraison' => round($frais_livraison, 2),
        'frais_emballage' => round($frais_emballage, 2),
        'total' => round($total, 2),
        'total_items' => $total_items,
        'seuil_livraison_gratuite' => 50
    ];
}

// ============================================
// FONCTIONS DE GESTION DES MESSAGES
// ============================================

/**
 * Récupère et efface les messages de session
 * @return array
 */
function getSessionMessages() {
    $messages = [];
    if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
        $messages = $_SESSION['messages'];
        unset($_SESSION['messages']);
    }
    return $messages;
}

/**
 * Ajoute un message en session
 * @param string $message Le message
 * @param string $type Type de message (success, error, info)
 */
function addSessionMessage($message, $type = 'info') {
    if (!isset($_SESSION['messages'])) {
        $_SESSION['messages'] = [];
    }
    $_SESSION['messages'][] = [
        'message' => $message,
        'type' => $type,
        'time' => time()
    ];
}

/**
 * Récupère et efface les erreurs de checkout
 * @return array
 */
function getCheckoutErrors() {
    $errors = [];
    if (isset($_SESSION['checkout_errors']) && is_array($_SESSION['checkout_errors'])) {
        $errors = $_SESSION['checkout_errors'];
        unset($_SESSION['checkout_errors']);
    }
    return $errors;
}

/**
 * Ajoute une erreur de checkout
 * @param string|array $error
 */
function addCheckoutErrors($error) {
    if (!isset($_SESSION['checkout_errors'])) {
        $_SESSION['checkout_errors'] = [];
    }
    if (is_array($error)) {
        $_SESSION['checkout_errors'] = array_merge($_SESSION['checkout_errors'], $error);
    } else {
        $_SESSION['checkout_errors'][] = $error;
    }
}

// ============================================
// FONCTIONS DE GESTION PAYPAL
// ============================================

/**
 * Nettoie les flags PayPal en session
 */
function cleanPayPalFlags() {
    unset($_SESSION['paypal_processing']);
    unset($_SESSION['paypal_order_id']);
    unset($_SESSION['paypal_commande_id']);
    unset($_SESSION['paypal_token']);
}

/**
 * Nettoie la session utilisateur
 */
function cleanUserSession() {
    clearCartSession();
    unset($_SESSION[SESSION_KEY_CHECKOUT]);
    unset($_SESSION[SESSION_KEY_COMMANDE]);
    unset($_SESSION[SESSION_KEY_CLIENT_ID]);
    unset($_SESSION[SESSION_KEY_PANIER_ID]);
    cleanPayPalFlags();
}

// ============================================
// FONCTIONS D'INFORMATION SESSION
// ============================================

/**
 * Vérifie si l'utilisateur est connecté
 * @return bool
 */
function isUserLoggedIn() {
    return isset($_SESSION[SESSION_KEY_CLIENT_ID]) && $_SESSION[SESSION_KEY_CLIENT_ID] > 0;
}

/**
 * Récupère l'ID du client connecté
 * @return int|null
 */
function getCurrentClientId() {
    return $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
}

/**
 * Récupère des informations sur la session
 * @return array
 */
function getSessionInfo() {
    return [
        'session_id' => session_id(),
        'client_id' => $_SESSION[SESSION_KEY_CLIENT_ID] ?? null,
        'is_logged_in' => isUserLoggedIn(),
        'cart_count' => countCartItems(),
        'session_status' => session_status(),
        'has_cart' => hasValidCart(),
        'has_address' => hasShippingAddress(),
        'timestamp' => time()
    ];
}

// ============================================
// FONCTIONS DE NETTOYAGE
// ============================================

/**
 * Nettoie les paniers expirés
 * @param int $days Nombre de jours avant expiration
 * @return int
 */
function cleanupExpiredCarts($days = 30) {
    $pdo = getPDOConnection();
    if (!$pdo) {
        return 0;
    }
    
    try {
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

// ============================================
// INITIALISATION
// ============================================

// Initialiser le panier
initPanier();

// Nettoyage périodique (10% des requêtes)
if (rand(1, 100) <= 10) {
    cleanupExpiredCarts(30);
    
    // Vider le cache si trop vieux
    if ($GLOBALS['cart_cache_time'] > 0 && (time() - $GLOBALS['cart_cache_time']) > 3600) {
        $GLOBALS['cart_cache'] = null;
        $GLOBALS['cart_cache_time'] = 0;
    }
}

// Vérification rapide de la BDD au chargement
if (!isset($GLOBALS['db_check_done'])) {
    $GLOBALS['db_check_done'] = true;
    if (!checkDatabaseConnection()) {
        error_log("ATTENTION: La connexion à la base de données a échoué au démarrage");
    }
}

// Journalisation pour débogage
error_log("session_verification.php chargé - Session ID: " . session_id());
?>