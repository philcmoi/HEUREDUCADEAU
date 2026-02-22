<?php
// panier.php - VERSION CORRIGÉE AVEC SESSIONS STANDARDISÉES

require_once __DIR__ . '/session_verification.php';

// 1. VIDER TOUT CONTENU ACCIDENTEL
ob_clean();

// 2. HEADERS API
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");

// 3. GÉRER LES REQUÊTES OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 4. CONNEXION BDD
$pdo = getPDOConnection();
$id_panier_bdd = null;

if ($pdo) {
    $id_panier_bdd = $_SESSION[SESSION_KEY_PANIER_ID] ?? null;
    if (!$id_panier_bdd && isset($_SESSION[SESSION_KEY_PANIER_ID])) {
        $id_panier_bdd = $_SESSION[SESSION_KEY_PANIER_ID];
    }
}

// ============================================
// TRAITEMENT DE LA REQUÊTE
// ============================================

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$inputData = [];

// Récupérer les données POST
if ($method === 'POST') {
    $input = @file_get_contents('php://input');
    if (!empty($input)) {
        $inputData = @json_decode($input, true) ?: [];
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

// TRAITEMENT DES ACTIONS
switch ($action) {
    
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'session' => [
                'id' => session_id(),
                'panier_items' => count($_SESSION[SESSION_KEY_PANIER] ?? []),
                'panier_total' => countCartItems()
            ],
            'server' => [
                'method' => $method,
                'action' => $action,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
        break;
        
    case 'compter':
        $total = countCartItems();
        echo json_encode([
            'success' => true,
            'total' => $total,
            'session_id' => session_id()
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
            $produit = getProductDetails($id_produit, $pdo);
            
            if (!$produit) {
                echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
                exit;
            }
            
            // Vérifier le stock
            $stock_disponible = $produit['quantite_stock'] ?? 100;
            $quantite_existante = 0;
            
            if (isset($_SESSION[SESSION_KEY_PANIER][$id_produit])) {
                $quantite_existante = $_SESSION[SESSION_KEY_PANIER][$id_produit]['quantite'] ?? 0;
            }
            
            $quantite_totale = $quantite_existante + $quantite;
            
            if ($stock_disponible < $quantite_totale) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Stock insuffisant', 
                    'stock_disponible' => $stock_disponible,
                    'quantite_existante' => $quantite_existante
                ]);
                exit;
            }
            
            // Initialiser le panier si nécessaire
            if (!isset($_SESSION[SESSION_KEY_PANIER]) || !is_array($_SESSION[SESSION_KEY_PANIER])) {
                $_SESSION[SESSION_KEY_PANIER] = [];
            }
            
            // Mise à jour session
            $_SESSION[SESSION_KEY_PANIER][$id_produit] = [
                'id_produit' => $id_produit,
                'quantite' => $quantite_totale,
                'prix' => floatval($produit['prix_ttc']),
                'nom' => $produit['nom'],
                'reference' => $produit['reference'],
                'image' => $produit['image'],
                'date_maj' => date('Y-m-d H:i:s')
            ];
            
            if (!isset($_SESSION[SESSION_KEY_PANIER][$id_produit]['date_ajout'])) {
                $_SESSION[SESSION_KEY_PANIER][$id_produit]['date_ajout'] = date('Y-m-d H:i:s');
            }
            
            // Mise à jour BDD
            if ($id_panier_bdd && $pdo) {
                try {
                    // Vérifier si l'item existe déjà
                    $stmt = $pdo->prepare("SELECT id_item FROM panier_items WHERE id_panier = ? AND id_produit = ?");
                    $stmt->execute([$id_panier_bdd, $id_produit]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        // Mise à jour
                        $stmt = $pdo->prepare("
                            UPDATE panier_items 
                            SET quantite = ?, date_modification = NOW() 
                            WHERE id_panier = ? AND id_produit = ?
                        ");
                        $stmt->execute([$quantite_totale, $id_panier_bdd, $id_produit]);
                    } else {
                        // Insertion
                        $stmt = $pdo->prepare("
                            INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$id_panier_bdd, $id_produit, $quantite_totale, $produit['prix_ttc']]);
                    }
                } catch (Exception $e) {
                    error_log("Erreur synchro BDD: " . $e->getMessage());
                }
            }
            
            $total = countCartItems();
            
            echo json_encode([
                'success' => true,
                'message' => 'Produit ajouté au panier',
                'produit_nom' => $produit['nom'],
                'produit' => [
                    'id' => $produit['id_produit'],
                    'nom' => $produit['nom'],
                    'prix' => floatval($produit['prix_ttc'])
                ],
                'quantite' => $quantite_totale,
                'total_articles' => $total,
                'panier_count' => count($_SESSION[SESSION_KEY_PANIER])
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
            
            if (hasValidCart()) {
                foreach ($_SESSION[SESSION_KEY_PANIER] as $id => $item) {
                    $produit = getProductDetails($item['id_produit'], $pdo);
                    $prix_unitaire = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
                    $quantite = intval($item['quantite'] ?? 1);
                    
                    $panier_details[] = [
                        'id_produit' => $item['id_produit'],
                        'quantite' => $quantite,
                        'nom' => $produit['nom'] ?? $item['nom'] ?? "Produit #{$item['id_produit']}",
                        'prix_unitaire' => $prix_unitaire,
                        'prix_total' => $quantite * $prix_unitaire,
                        'reference' => $produit['reference'] ?? $item['reference'] ?? "REF{$item['id_produit']}",
                        'image' => $produit['image'] ?? $item['image'] ?? 'img/default-product.jpg',
                        'quantite_stock' => $produit['quantite_stock'] ?? 100,
                        'disponible' => true
                    ];
                }
            }
            
            // Calculer les totaux
            $totaux = calculerTotauxPanier($panier_details, $_SESSION[SESSION_KEY_CHECKOUT] ?? []);
            
            echo json_encode([
                'success' => true,
                'panier' => $panier_details,
                'sous_total' => $totaux['sous_total'],
                'total_items' => $totaux['total_items'],
                'frais_livraison' => $totaux['frais_livraison'],
                'total' => $totaux['total'],
                'seuil_livraison_gratuite' => $totaux['seuil_livraison_gratuite'],
                'panier_vide' => empty($panier_details),
                'source' => 'session',
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
                unset($_SESSION[SESSION_KEY_PANIER][$id_produit]);
                
                if ($id_panier_bdd && $pdo) {
                    $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ? AND id_produit = ?");
                    $stmt->execute([$id_panier_bdd, $id_produit]);
                }
                
                $total = countCartItems();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Produit retiré', 
                    'total_articles' => $total
                ]);
                exit;
            }
            
            $produit = getProductDetails($id_produit, $pdo);
            
            // Vérifier le stock
            if (($produit['quantite_stock'] ?? 100) < $quantite) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Stock insuffisant',
                    'stock_disponible' => $produit['quantite_stock'] ?? 100
                ]);
                exit;
            }
            
            // Mise à jour session
            if (isset($_SESSION[SESSION_KEY_PANIER][$id_produit])) {
                $_SESSION[SESSION_KEY_PANIER][$id_produit]['quantite'] = $quantite;
                $_SESSION[SESSION_KEY_PANIER][$id_produit]['date_maj'] = date('Y-m-d H:i:s');
            }
            
            // Mise à jour BDD
            if ($id_panier_bdd && $pdo) {
                $stmt = $pdo->prepare("
                    UPDATE panier_items SET quantite = ?, date_modification = NOW()
                    WHERE id_panier = ? AND id_produit = ?
                ");
                $stmt->execute([$quantite, $id_panier_bdd, $id_produit]);
            }
            
            $total = countCartItems();
            $prix_total = floatval($produit['prix_ttc'] ?? 0) * $quantite;
            
            echo json_encode([
                'success' => true,
                'message' => 'Quantité mise à jour',
                'quantite' => $quantite,
                'prix_unitaire' => floatval($produit['prix_ttc'] ?? 0),
                'prix_total' => $prix_total,
                'total_articles' => $total
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
        
        unset($_SESSION[SESSION_KEY_PANIER][$id_produit]);
        
        if ($id_panier_bdd && $pdo) {
            $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ? AND id_produit = ?");
            $stmt->execute([$id_panier_bdd, $id_produit]);
        }
        
        $total = countCartItems();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Produit retiré du panier', 
            'total_articles' => $total
        ]);
        break;
        
    case 'vider':
        $_SESSION[SESSION_KEY_PANIER] = [];
        
        if ($id_panier_bdd && $pdo) {
            $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ?");
            $stmt->execute([$id_panier_bdd]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Panier vidé avec succès', 
            'total_articles' => 0
        ]);
        break;
        
    case 'init_checkout':
        try {
            $total = countCartItems();
            
            if ($total === 0) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Votre panier est vide'
                ]);
                exit;
            }
            
            if (!hasCheckout()) {
                initCheckout($_SESSION[SESSION_KEY_PANIER_ID] ?? null);
            }
            
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
        
    default:
        echo json_encode([
            'success' => false, 
            'message' => 'Action non reconnue',
            'action_demandee' => $action
        ]);
}

exit;
?>