<?php
// api/panier.php - VERSION CORRIGÉE COMPLÈTE AVEC UPDATE_QUANTITE

// ============================================
// CONFIGURATION DES SESSIONS - CRITIQUE
// ============================================

// Définir le chemin de sauvegarde des sessions
$sessionPath = dirname(__DIR__) . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}

ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 86400); // 24 heures
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Démarrer la session AVANT tout autre code
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiser le panier s'il n'existe pas
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// ============================================
// HEADERS API
// ============================================

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Gérer les requêtes OPTIONS (CORS préflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// CONFIGURATION BASE DE DONNÉES
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Fonction de connexion PDO
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BD: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

// Fonction pour compter les articles
function compterArticlesPanier() {
    $total = 0;
    if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $item) {
            $total += intval($item['quantite'] ?? 0);
        }
    }
    return $total;
}

// Fonction pour obtenir les détails d'un produit
function getProductDetails($id_produit) {
    $pdo = getPDOConnection();
    if (!$pdo) return null;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.id_produit, p.nom, p.prix_ttc, p.quantite_stock, p.statut, p.reference,
                   p.description_courte, p.id_categorie,
                   c.nom as categorie_nom,
                   (SELECT ip.url_image FROM images_produits ip 
                    WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.id_produit = ? AND p.statut = 'actif'
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if ($produit && empty($produit['image'])) {
            $produit['image'] = 'img/default-product.jpg';
        }
        
        return $produit;
    } catch (PDOException $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return null;
    }
}

// Fonction pour logger les actions
function logPanierAction($action, $details = []) {
    $log = [
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'action' => $action,
        'details' => $details,
        'panier_count' => count($_SESSION['panier'] ?? []),
        'panier_total' => compterArticlesPanier()
    ];
    
    $logFile = dirname(__DIR__) . '/logs/panier.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, json_encode($log) . PHP_EOL, FILE_APPEND);
}

// ============================================
// TRAITEMENT DE LA REQUÊTE
// ============================================

// Récupérer la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Récupérer l'action depuis GET ou POST
$action = $_GET['action'] ?? '';

// Traiter les données d'entrée
$inputData = [];
if ($method === 'POST') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $inputData = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $inputData = [];
        }
    }
    
    // Fallback sur $_POST
    if (empty($inputData) && !empty($_POST)) {
        $inputData = $_POST;
    }
    
    // Si pas d'action dans GET, chercher dans les données
    if (empty($action) && isset($inputData['action'])) {
        $action = $inputData['action'];
    }
}

// Si pas d'action, retourner erreur
if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action non spécifiée',
        'available_actions' => ['compter', 'ajouter', 'get', 'supprimer', 'update_quantite', 'vider', 'test']
    ]);
    exit;
}

// ============================================
// GESTION DES ACTIONS
// ============================================

switch ($action) {
    
    case 'test':
        // Test de connexion et session
        logPanierAction('test', ['ip' => $_SERVER['REMOTE_ADDR']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'session' => [
                'id' => session_id(),
                'panier_items' => count($_SESSION['panier']),
                'panier_total' => compterArticlesPanier(),
                'data' => $_SESSION['panier']
            ],
            'server' => [
                'method' => $method,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        break;
        
    case 'compter':
        // Compter les articles du panier
        logPanierAction('compter');
        
        $total = compterArticlesPanier();
        
        echo json_encode([
            'success' => true,
            'total' => $total,
            'session_id' => substr(session_id(), 0, 10) . '...'
        ]);
        break;
        
    case 'ajouter':
        // Ajouter un produit au panier
        $id_produit = intval($inputData['id_produit'] ?? 0);
        $quantite = intval($inputData['quantite'] ?? 1);
        
        logPanierAction('ajouter', ['id_produit' => $id_produit, 'quantite' => $quantite]);
        
        if ($id_produit <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID produit invalide'
            ]);
            exit;
        }
        
        if ($quantite <= 0) {
            $quantite = 1;
        }
        
        try {
            // Vérifier si le produit existe
            $produit = getProductDetails($id_produit);
            
            if (!$produit) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ]);
                exit;
            }
            
            // Vérifier le statut
            if ($produit['statut'] !== 'actif') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produit indisponible'
                ]);
                exit;
            }
            
            // Vérifier le stock
            if ($produit['quantite_stock'] < $quantite) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Stock insuffisant. Disponible : ' . $produit['quantite_stock']
                ]);
                exit;
            }
            
            // S'assurer que le panier est initialisé
            if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
                $_SESSION['panier'] = [];
            }
            
            // Rechercher le produit dans le panier
            $produitIndex = -1;
            foreach ($_SESSION['panier'] as $index => $item) {
                if ($item['id_produit'] == $id_produit) {
                    $produitIndex = $index;
                    break;
                }
            }
            
            if ($produitIndex >= 0) {
                // Mettre à jour la quantité
                $nouvelle_quantite = $_SESSION['panier'][$produitIndex]['quantite'] + $quantite;
                
                // Vérifier à nouveau le stock
                if ($produit['quantite_stock'] < $nouvelle_quantite) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Stock insuffisant pour cette quantité totale'
                    ]);
                    exit;
                }
                
                $_SESSION['panier'][$produitIndex]['quantite'] = $nouvelle_quantite;
                $_SESSION['panier'][$produitIndex]['date_maj'] = date('Y-m-d H:i:s');
                
            } else {
                // Ajouter un nouvel item
                $_SESSION['panier'][] = [
                    'id_produit' => $id_produit,
                    'quantite' => $quantite,
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc']),
                    'reference' => $produit['reference'],
                    'image' => $produit['image'],
                    'categorie' => $produit['categorie_nom'],
                    'date_ajout' => date('Y-m-d H:i:s'),
                    'date_maj' => date('Y-m-d H:i:s')
                ];
            }
            
            // Forcer l'écriture de la session
            session_write_close();
            session_start(); // Re-démarrer pour la prochaine requête
            
            // Calculer le nouveau total
            $total = compterArticlesPanier();
            
            echo json_encode([
                'success' => true,
                'message' => 'Produit ajouté au panier',
                'produit_nom' => $produit['nom'],
                'produit_prix' => $produit['prix_ttc'],
                'produit_image' => $produit['image'],
                'total_articles' => $total,
                'quantite_ajoutee' => $quantite,
                'panier_count' => count($_SESSION['panier'])
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout au panier',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'get':
        // Récupérer les détails du panier
        logPanierAction('get');
        
        try {
            $panier_details = [];
            $sous_total = 0;
            $total_items = 0;
            
            if (isset($_SESSION['panier']) && is_array($_SESSION['panier']) && count($_SESSION['panier']) > 0) {
                foreach ($_SESSION['panier'] as $item) {
                    // Mettre à jour les infos depuis la base
                    $produit = getProductDetails($item['id_produit']);
                    
                    if ($produit) {
                        $prix_unitaire = floatval($produit['prix_ttc']);
                        $prix_total = $prix_unitaire * $item['quantite'];
                        
                        $item_detail = [
                            'id_produit' => $item['id_produit'],
                            'quantite' => $item['quantite'],
                            'nom' => $produit['nom'],
                            'prix_unitaire' => $prix_unitaire,
                            'prix_total' => $prix_total,
                            'reference' => $produit['reference'],
                            'image' => $produit['image'],
                            'quantite_stock' => $produit['quantite_stock'],
                            'statut' => $produit['statut'],
                            'disponible' => ($produit['statut'] === 'actif' && $produit['quantite_stock'] >= $item['quantite']),
                            'categorie' => $produit['categorie_nom']
                        ];
                        
                        $panier_details[] = $item_detail;
                        $sous_total += $prix_total;
                        $total_items += $item['quantite'];
                    }
                }
            }
            
            // Calculer les frais de livraison
            $frais_livraison = ($sous_total >= 50) ? 0 : 4.90;
            $total = $sous_total + $frais_livraison;
            
            echo json_encode([
                'success' => true,
                'panier' => $panier_details,
                'sous_total' => round($sous_total, 2),
                'total_items' => $total_items,
                'frais_livraison' => $frais_livraison,
                'total' => round($total, 2),
                'seuil_livraison_gratuite' => 50.00,
                'panier_vide' => empty($panier_details)
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération du panier',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'update_quantite':
        // Mettre à jour la quantité d'un produit
        $id_produit = intval($inputData['id_produit'] ?? 0);
        $quantite = intval($inputData['quantite'] ?? 1);
        
        logPanierAction('update_quantite', ['id_produit' => $id_produit, 'quantite' => $quantite]);
        
        if ($id_produit <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID produit invalide'
            ]);
            exit;
        }
        
        if ($quantite < 0) {
            $quantite = 0;
        }
        
        try {
            // Si quantité = 0, supprimer l'article
            if ($quantite == 0) {
                $nouveau_panier = [];
                $supprime = false;
                
                if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
                    foreach ($_SESSION['panier'] as $item) {
                        if ($item['id_produit'] != $id_produit) {
                            $nouveau_panier[] = $item;
                        } else {
                            $supprime = true;
                        }
                    }
                    
                    $_SESSION['panier'] = $nouveau_panier;
                }
                
                $total = compterArticlesPanier();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Produit retiré du panier',
                    'total_articles' => $total
                ]);
                exit;
            }
            
            // Vérifier si le produit existe dans la base
            $produit = getProductDetails($id_produit);
            
            if (!$produit) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produit non trouvé'
                ]);
                exit;
            }
            
            // Vérifier le statut
            if ($produit['statut'] !== 'actif') {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produit indisponible'
                ]);
                exit;
            }
            
            // Vérifier le stock
            if ($produit['quantite_stock'] < $quantite) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Stock insuffisant. Disponible : ' . $produit['quantite_stock'],
                    'stock_max' => $produit['quantite_stock']
                ]);
                exit;
            }
            
            // Rechercher le produit dans le panier
            if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
                $_SESSION['panier'] = [];
            }
            
            $produitIndex = -1;
            $found = false;
            foreach ($_SESSION['panier'] as $index => $item) {
                if ($item['id_produit'] == $id_produit) {
                    $produitIndex = $index;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                // Mettre à jour la quantité
                $_SESSION['panier'][$produitIndex]['quantite'] = $quantite;
                $_SESSION['panier'][$produitIndex]['date_maj'] = date('Y-m-d H:i:s');
                
                // Mettre à jour les autres infos si besoin
                $_SESSION['panier'][$produitIndex]['nom'] = $produit['nom'];
                $_SESSION['panier'][$produitIndex]['prix'] = floatval($produit['prix_ttc']);
                $_SESSION['panier'][$produitIndex]['reference'] = $produit['reference'];
                $_SESSION['panier'][$produitIndex]['image'] = $produit['image'];
                $_SESSION['panier'][$produitIndex]['categorie'] = $produit['categorie_nom'];
                
                // Calculer le prix total pour ce produit
                $prix_total = floatval($produit['prix_ttc']) * $quantite;
                
                $total = compterArticlesPanier();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Quantité mise à jour',
                    'quantite' => $quantite,
                    'prix_unitaire' => floatval($produit['prix_ttc']),
                    'prix_total' => $prix_total,
                    'total_articles' => $total,
                    'stock_disponible' => $produit['quantite_stock']
                ]);
            } else {
                // Produit non trouvé dans le panier, l'ajouter
                $_SESSION['panier'][] = [
                    'id_produit' => $id_produit,
                    'quantite' => $quantite,
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc']),
                    'reference' => $produit['reference'],
                    'image' => $produit['image'],
                    'categorie' => $produit['categorie_nom'],
                    'date_ajout' => date('Y-m-d H:i:s'),
                    'date_maj' => date('Y-m-d H:i:s')
                ];
                
                $total = compterArticlesPanier();
                $prix_total = floatval($produit['prix_ttc']) * $quantite;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Produit ajouté au panier',
                    'quantite' => $quantite,
                    'prix_unitaire' => floatval($produit['prix_ttc']),
                    'prix_total' => $prix_total,
                    'total_articles' => $total,
                    'stock_disponible' => $produit['quantite_stock']
                ]);
            }
            
            // Forcer l'écriture de la session
            session_write_close();
            session_start();
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la quantité',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'supprimer':
        // Supprimer un produit du panier
        $id_produit = intval($inputData['id_produit'] ?? 0);
        
        logPanierAction('supprimer', ['id_produit' => $id_produit]);
        
        if ($id_produit <= 0) {
            echo json_encode([
                'success' => false,
                'message' => 'ID produit invalide'
            ]);
            exit;
        }
        
        if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
            $nouveau_panier = [];
            $supprime = false;
            
            foreach ($_SESSION['panier'] as $item) {
                if ($item['id_produit'] != $id_produit) {
                    $nouveau_panier[] = $item;
                } else {
                    $supprime = true;
                }
            }
            
            $_SESSION['panier'] = $nouveau_panier;
            
            if ($supprime) {
                $total = compterArticlesPanier();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Produit retiré du panier',
                    'total_articles' => $total
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Produit non trouvé dans le panier'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Panier vide'
            ]);
        }
        break;
        
    case 'vider':
        // Vider tout le panier
        logPanierAction('vider');
        
        $_SESSION['panier'] = [];
        
        echo json_encode([
            'success' => true,
            'message' => 'Panier vidé',
            'total_articles' => 0
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue',
            'available_actions' => ['compter', 'ajouter', 'get', 'supprimer', 'update_quantite', 'vider', 'test']
        ]);
        break;
}

// Fin du script
exit;
?>