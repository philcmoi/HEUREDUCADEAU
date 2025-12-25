<?php
// api/panier.php - VERSION COMPLÈTE AVEC INIT_CHECKOUT

// Activer le débogage temporairement
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers API - IMPORTANT: mettre AVANT toute sortie
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// Gérer les requêtes OPTIONS (CORS préflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Définir une constante pour autoriser l'accès à db_config.php
define('API_CALL', true);

// Vérifier si db_config.php existe et est accessible
$dbConfigPath = __DIR__ . '/../db_config.php';
if (!file_exists($dbConfigPath)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Fichier de configuration DB introuvable',
        'path' => realpath($dbConfigPath) ?: $dbConfigPath
    ]);
    exit;
}

// Inclure db_config.php
require_once $dbConfigPath;

// Configuration des sessions
$sessionPath = dirname(__DIR__) . '/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0755, true);
}

ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiser le panier dans la session s'il n'existe pas
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
}

// ============================================
// FONCTIONS UTILITAIRES BDD
// ============================================

/**
 * Compter les articles dans le panier session
 */
function compterArticlesPanier() {
    $total = 0;
    if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $item) {
            $total += intval($item['quantite'] ?? 0);
        }
    }
    return $total;
}

/**
 * Obtenir les détails d'un produit depuis la BDD
 */
function getProductDetails($id_produit) {
    $db = getDB();
    if (!$db) return null;
    
    try {
        $stmt = $db->prepare("
            SELECT p.id_produit, p.nom, p.prix_ttc, p.quantite_stock, p.statut, p.reference,
                   p.description_courte, p.id_categorie,
                   c.nom as categorie_nom,
                   COALESCE(
                       (SELECT ip.url_image FROM images_produits ip 
                        WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1),
                       'img/default-product.jpg'
                   ) as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.id_produit = ? AND p.statut = 'actif'
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        return $produit;
    } catch (PDOException $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return null;
    }
}

/**
 * Crée ou récupère un panier en BDD
 */
function getOrCreatePanierBDD() {
    $db = getDB();
    if (!$db) return null;
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        // Chercher un panier existant
        $sql = "SELECT id_panier FROM panier WHERE statut = 'actif' AND (";
        $params = [];
        
        if ($client_id) {
            $sql .= "id_client = ?";
            $params[] = $client_id;
        } else {
            $sql .= "session_id = ?";
            $params[] = $session_id;
        }
        
        $sql .= ") ORDER BY date_creation DESC LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $panier = $stmt->fetch();
        
        if ($panier) {
            return $panier['id_panier'];
        }
        
        // Créer un nouveau panier si aucun n'existe
        $stmt = $db->prepare("
            INSERT INTO panier (session_id, id_client, date_creation, statut)
            VALUES (?, ?, NOW(), 'actif')
        ");
        $stmt->execute([$session_id, $client_id]);
        
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erreur getOrCreatePanierBDD: " . $e->getMessage());
        return null;
    }
}

/**
 * Synchronise le panier session avec la BDD
 */
function syncPanierToBDD($id_panier) {
    if (!$id_panier || !isset($_SESSION['panier'])) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        $db->beginTransaction();
        
        // Supprimer les anciens items
        $stmt = $db->prepare("DELETE FROM panier_items WHERE id_panier = ?");
        $stmt->execute([$id_panier]);
        
        // Ajouter les nouveaux items
        foreach ($_SESSION['panier'] as $item) {
            if (isset($item['id_produit']) && isset($item['quantite']) && $item['quantite'] > 0) {
                $produit = getProductDetails($item['id_produit']);
                if ($produit) {
                    $stmt = $db->prepare("
                        INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $id_panier,
                        $item['id_produit'],
                        $item['quantite'],
                        $produit['prix_ttc']
                    ]);
                }
            }
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Erreur syncPanierToBDD: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère le panier depuis la BDD
 */
function getPanierFromBDD($id_panier) {
    if (!$id_panier) return [];
    
    $db = getDB();
    if (!$db) return [];
    
    try {
        $stmt = $db->prepare("
            SELECT pi.*, p.nom, p.reference, p.statut, p.quantite_stock,
                   COALESCE(
                       (SELECT url_image FROM images_produits 
                        WHERE id_produit = pi.id_produit AND principale = 1 LIMIT 1),
                       'img/default-product.jpg'
                   ) as image
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            WHERE pi.id_panier = ?
            ORDER BY pi.date_ajout DESC
        ");
        $stmt->execute([$id_panier]);
        
        $items = $stmt->fetchAll();
        $panier_details = [];
        
        foreach ($items as $item) {
            $prix_total = $item['prix_unitaire'] * $item['quantite'];
            
            $panier_details[] = [
                'id_produit' => $item['id_produit'],
                'quantite' => $item['quantite'],
                'nom' => $item['nom'],
                'prix_unitaire' => $item['prix_unitaire'],
                'prix_total' => $prix_total,
                'reference' => $item['reference'],
                'image' => $item['image'],
                'quantite_stock' => $item['quantite_stock'],
                'statut' => $item['statut'],
                'disponible' => ($item['statut'] === 'actif' && $item['quantite_stock'] >= $item['quantite'])
            ];
        }
        
        return $panier_details;
    } catch (PDOException $e) {
        error_log("Erreur getPanierFromBDD: " . $e->getMessage());
        return [];
    }
}

/**
 * Mettre à jour un item dans la BDD
 */
function updateItemInBDD($id_panier, $id_produit, $quantite) {
    if (!$id_panier) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        if ($quantite <= 0) {
            // Supprimer l'item
            $stmt = $db->prepare("DELETE FROM panier_items WHERE id_panier = ? AND id_produit = ?");
            return $stmt->execute([$id_panier, $id_produit]);
        } else {
            // Vérifier si l'item existe
            $stmt = $db->prepare("SELECT id_item FROM panier_items WHERE id_panier = ? AND id_produit = ?");
            $stmt->execute([$id_panier, $id_produit]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Mettre à jour
                $stmt = $db->prepare("UPDATE panier_items SET quantite = ?, date_modification = NOW() WHERE id_panier = ? AND id_produit = ?");
                return $stmt->execute([$quantite, $id_panier, $id_produit]);
            } else {
                // Ajouter
                $produit = getProductDetails($id_produit);
                if ($produit) {
                    $stmt = $db->prepare("
                        INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    return $stmt->execute([$id_panier, $id_produit, $quantite, $produit['prix_ttc']]);
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur updateItemInBDD: " . $e->getMessage());
        return false;
    }
    
    return false;
}

/**
 * Calcule le total du panier
 */
function calculerTotauxPanier($panier_items) {
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($panier_items as $item) {
        if (isset($item['prix_total'])) {
            $sous_total += $item['prix_total'];
        } else if (isset($item['prix_unitaire']) && isset($item['quantite'])) {
            $sous_total += $item['prix_unitaire'] * $item['quantite'];
        }
        $total_items += $item['quantite'] ?? 0;
    }
    
    // Calcul des frais de livraison (gratuit à partir de 50€)
    $seuil_livraison_gratuite = 50.00;
    $frais_livraison_base = 4.90;
    $frais_livraison = ($sous_total >= $seuil_livraison_gratuite) ? 0 : $frais_livraison_base;
    
    // Total général
    $total = $sous_total + $frais_livraison;
    
    return [
        'sous_total' => round($sous_total, 2),
        'total_items' => $total_items,
        'frais_livraison' => $frais_livraison,
        'total' => round($total, 2),
        'seuil_livraison_gratuite' => $seuil_livraison_gratuite,
        'economie_livraison' => ($sous_total >= $seuil_livraison_gratuite) ? $frais_livraison_base : 0
    ];
}

// ============================================
// TRAITEMENT DE LA REQUÊTE
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$inputData = [];

if ($method === 'POST') {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $inputData = json_decode($input, true);
    }
    
    if (empty($inputData) && !empty($_POST)) {
        $inputData = $_POST;
    }
    
    if (empty($action) && isset($inputData['action'])) {
        $action = $inputData['action'];
    }
}

// Si pas d'action spécifiée, retourner une erreur
if (empty($action)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Action non spécifiée',
        'available_actions' => ['test', 'compter', 'ajouter', 'get', 'update_quantite', 'supprimer', 'vider', 'init_checkout'],
        'session_id' => session_id()
    ]);
    exit;
}

// Initialiser le panier BDD
$id_panier_bdd = getOrCreatePanierBDD();

switch ($action) {
    
    case 'test':
        $db = getDB();
        $db_connected = ($db !== null);
        $db_error = '';
        
        if ($db) {
            try {
                $db->query("SELECT 1");
            } catch (PDOException $e) {
                $db_connected = false;
                $db_error = $e->getMessage();
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'session' => [
                'id' => session_id(),
                'panier_items' => count($_SESSION['panier']),
                'panier_total' => compterArticlesPanier(),
                'panier_data' => $_SESSION['panier']
            ],
            'bdd' => [
                'available' => $db_connected,
                'panier_id' => $id_panier_bdd,
                'connected' => $db_connected,
                'error' => $db_error
            ],
            'server' => [
                'method' => $method,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => PHP_VERSION
            ],
            'config' => [
                'session_path' => ini_get('session.save_path'),
                'seuil_livraison_gratuite' => 50.00,
                'frais_livraison' => 4.90
            ]
        ]);
        break;
        
    case 'compter':
        $total = compterArticlesPanier();
        
        echo json_encode([
            'success' => true,
            'total' => $total,
            'session_id' => session_id(),
            'panier_bdd_id' => $id_panier_bdd
        ]);
        break;
        
    case 'ajouter':
        $id_produit = intval($inputData['id_produit'] ?? 0);
        $quantite = intval($inputData['quantite'] ?? 1);
        
        if ($id_produit <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
            exit;
        }
        
        if ($quantite <= 0) $quantite = 1;
        
        try {
            $produit = getProductDetails($id_produit);
            
            if (!$produit) {
                echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
                exit;
            }
            
            if ($produit['statut'] !== 'actif') {
                echo json_encode(['success' => false, 'message' => 'Produit indisponible']);
                exit;
            }
            
            // Vérifier le stock
            $stock_disponible = $produit['quantite_stock'];
            $quantite_demandee = $quantite;
            
            // Vérifier si le produit est déjà dans le panier
            $quantite_existante = 0;
            foreach ($_SESSION['panier'] as $item) {
                if ($item['id_produit'] == $id_produit) {
                    $quantite_existante = $item['quantite'];
                    break;
                }
            }
            
            $quantite_totale = $quantite_existante + $quantite_demandee;
            
            if ($stock_disponible < $quantite_totale) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Stock insuffisant', 
                    'stock_disponible' => $stock_disponible,
                    'quantite_existante' => $quantite_existante,
                    'quantite_demandee' => $quantite_demandee
                ]);
                exit;
            }
            
            // Mise à jour session
            if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
                $_SESSION['panier'] = [];
            }
            
            $produitIndex = -1;
            foreach ($_SESSION['panier'] as $index => $item) {
                if ($item['id_produit'] == $id_produit) {
                    $produitIndex = $index;
                    break;
                }
            }
            
            if ($produitIndex >= 0) {
                $_SESSION['panier'][$produitIndex]['quantite'] = $quantite_totale;
                $_SESSION['panier'][$produitIndex]['date_maj'] = date('Y-m-d H:i:s');
            } else {
                $_SESSION['panier'][] = [
                    'id_produit' => $id_produit,
                    'quantite' => $quantite,
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc']),
                    'reference' => $produit['reference'],
                    'image' => $produit['image'],
                    'date_ajout' => date('Y-m-d H:i:s'),
                    'date_maj' => date('Y-m-d H:i:s')
                ];
            }
            
            // Mise à jour BDD
            if ($id_panier_bdd) {
                updateItemInBDD($id_panier_bdd, $id_produit, 
                    $produitIndex >= 0 ? $quantite_totale : $quantite);
            }
            
            $total = compterArticlesPanier();
            
            echo json_encode([
                'success' => true,
                'message' => 'Produit ajouté au panier',
                'produit' => [
                    'id' => $produit['id_produit'],
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc']),
                    'image' => $produit['image'],
                    'reference' => $produit['reference']
                ],
                'quantite' => $quantite_totale,
                'total_articles' => $total,
                'panier_count' => count($_SESSION['panier']),
                'panier_bdd_id' => $id_panier_bdd
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
        try {
            $panier_details = [];
            $source = 'session';
            
            // Récupérer depuis BDD si disponible
            if ($id_panier_bdd) {
                $panier_details = getPanierFromBDD($id_panier_bdd);
                if (!empty($panier_details)) {
                    $source = 'bdd';
                }
            }
            
            // Si BDD vide ou non disponible, utiliser la session
            if (empty($panier_details) && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
                $source = 'session';
                foreach ($_SESSION['panier'] as $item) {
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
                            'disponible' => ($produit['statut'] === 'actif' && $produit['quantite_stock'] >= $item['quantite'])
                        ];
                        
                        $panier_details[] = $item_detail;
                    }
                }
            }
            
            // Calculer les totaux
            $totaux = calculerTotauxPanier($panier_details);
            
            echo json_encode([
                'success' => true,
                'panier' => $panier_details,
                'sous_total' => $totaux['sous_total'],
                'total_items' => $totaux['total_items'],
                'frais_livraison' => $totaux['frais_livraison'],
                'total' => $totaux['total'],
                'seuil_livraison_gratuite' => $totaux['seuil_livraison_gratuite'],
                'economie_livraison' => $totaux['economie_livraison'],
                'panier_vide' => empty($panier_details),
                'source' => $source,
                'panier_bdd_id' => $id_panier_bdd,
                'session_id' => session_id()
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Erreur récupération panier', 
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'update_quantite':
        $id_produit = intval($inputData['id_produit'] ?? 0);
        $quantite = intval($inputData['quantite'] ?? 1);
        
        if ($id_produit <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
            exit;
        }
        
        if ($quantite < 0) $quantite = 0;
        
        try {
            if ($quantite == 0) {
                // Suppression
                if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
                    $_SESSION['panier'] = array_values(array_filter($_SESSION['panier'], function($item) use ($id_produit) {
                        return $item['id_produit'] != $id_produit;
                    }));
                }
                
                if ($id_panier_bdd) {
                    updateItemInBDD($id_panier_bdd, $id_produit, 0);
                }
                
                $total = compterArticlesPanier();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produit retiré', 
                    'total_articles' => $total,
                    'panier_bdd_id' => $id_panier_bdd
                ]);
                exit;
            }
            
            $produit = getProductDetails($id_produit);
            
            if (!$produit) {
                echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
                exit;
            }
            
            if ($produit['statut'] !== 'actif') {
                echo json_encode(['success' => false, 'message' => 'Produit indisponible']);
                exit;
            }
            
            if ($produit['quantite_stock'] < $quantite) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Stock insuffisant', 
                    'stock_disponible' => $produit['quantite_stock'],
                    'quantite_demandee' => $quantite
                ]);
                exit;
            }
            
            // Mise à jour session
            if (!isset($_SESSION['panier'])) {
                $_SESSION['panier'] = [];
            }
            
            $found = false;
            foreach ($_SESSION['panier'] as &$item) {
                if ($item['id_produit'] == $id_produit) {
                    $item['quantite'] = $quantite;
                    $item['date_maj'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['panier'][] = [
                    'id_produit' => $id_produit,
                    'quantite' => $quantite,
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc']),
                    'reference' => $produit['reference'],
                    'image' => $produit['image'],
                    'date_ajout' => date('Y-m-d H:i:s'),
                    'date_maj' => date('Y-m-d H:i:s')
                ];
            }
            
            // Mise à jour BDD
            if ($id_panier_bdd) {
                updateItemInBDD($id_panier_bdd, $id_produit, $quantite);
            }
            
            $total = compterArticlesPanier();
            $prix_total = floatval($produit['prix_ttc']) * $quantite;
            
            echo json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour',
                'produit' => [
                    'id' => $produit['id_produit'],
                    'nom' => $produit['nom']
                ],
                'quantite' => $quantite,
                'prix_unitaire' => floatval($produit['prix_ttc']),
                'prix_total' => $prix_total,
                'total_articles' => $total,
                'stock_disponible' => $produit['quantite_stock'],
                'panier_bdd_id' => $id_panier_bdd
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Erreur mise à jour quantité', 
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'supprimer':
        $id_produit = intval($inputData['id_produit'] ?? 0);
        
        if ($id_produit <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
            exit;
        }
        
        if (isset($_SESSION['panier']) && is_array($_SESSION['panier'])) {
            $_SESSION['panier'] = array_values(array_filter($_SESSION['panier'], function($item) use ($id_produit) {
                return $item['id_produit'] != $id_produit;
            }));
        }
        
        if ($id_panier_bdd) {
            updateItemInBDD($id_panier_bdd, $id_produit, 0);
        }
        
        $total = compterArticlesPanier();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Produit retiré du panier', 
            'total_articles' => $total,
            'panier_bdd_id' => $id_panier_bdd
        ]);
        break;
        
    case 'vider':
        $_SESSION['panier'] = [];
        
        if ($id_panier_bdd) {
            $db = getDB();
            if ($db) {
                try {
                    $stmt = $db->prepare("DELETE FROM panier_items WHERE id_panier = ?");
                    $stmt->execute([$id_panier_bdd]);
                    
                    // Optionnel : marquer le panier comme inactif
                    $stmt = $db->prepare("UPDATE panier SET statut = 'inactif' WHERE id_panier = ?");
                    $stmt->execute([$id_panier_bdd]);
                } catch (PDOException $e) {
                    error_log("Erreur vider panier BDD: " . $e->getMessage());
                }
            }
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Panier vidé avec succès', 
            'total_articles' => 0,
            'panier_bdd_id' => $id_panier_bdd
        ]);
        break;
        
    case 'init_checkout':
        try {
            // Vérifier si le panier n'est pas vide
            $total = compterArticlesPanier();
            
            if ($total === 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Votre panier est vide'
                ]);
                exit;
            }
            
            // Vérifier la disponibilité des produits
            $panier_details = [];
            if ($id_panier_bdd) {
                $panier_details = getPanierFromBDD($id_panier_bdd);
            }
            
            if (empty($panier_details) && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
                foreach ($_SESSION['panier'] as $item) {
                    $produit = getProductDetails($item['id_produit']);
                    if ($produit) {
                        $panier_details[] = [
                            'id_produit' => $item['id_produit'],
                            'quantite' => $item['quantite'],
                            'nom' => $produit['nom'],
                            'disponible' => ($produit['statut'] === 'actif' && $produit['quantite_stock'] >= $item['quantite'])
                        ];
                    }
                }
            }
            
            // Vérifier si tous les produits sont disponibles
            $unavailable_items = array_filter($panier_details, function($item) {
                return !$item['disponible'];
            });
            
            if (count($unavailable_items) > 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Certains produits ne sont pas disponibles',
                    'unavailable_items' => $unavailable_items
                ]);
                exit;
            }
            
            // Si tout est bon, autoriser l'accès à livraison.php
            $_SESSION['checkout_authorized'] = true;
            $_SESSION['checkout_time'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Checkout autorisé',
                'redirect_url' => 'livraison_form.php',
                'items_count' => $total
            ]);
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => 'Erreur lors de l\'initialisation du checkout',
                'error' => $e->getMessage()
            ]);
        }
        break;
        
    case 'synchroniser':
        // Synchroniser la session avec la BDD
        if ($id_panier_bdd && !empty($_SESSION['panier'])) {
            $result = syncPanierToBDD($id_panier_bdd);
            
            echo json_encode([
                'success' => $result,
                'message' => $result ? 'Panier synchronisé avec BDD' : 'Erreur synchronisation',
                'panier_bdd_id' => $id_panier_bdd,
                'items_count' => count($_SESSION['panier'])
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Rien à synchroniser',
                'panier_bdd_id' => $id_panier_bdd,
                'session_items' => isset($_SESSION['panier']) ? count($_SESSION['panier']) : 0
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false, 
            'message' => 'Action non reconnue',
            'action_demandee' => $action,
            'actions_disponibles' => [
                'test', 
                'compter', 
                'ajouter', 
                'get', 
                'update_quantite', 
                'supprimer', 
                'vider',
                'init_checkout',
                'synchroniser'
            ]
        ]);
}

exit;
?>