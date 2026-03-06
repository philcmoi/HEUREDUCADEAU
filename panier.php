<?php
// panier.php - API de gestion du panier
// VERSION CORRIGÉE COMPLÈTE - Avec tous les endpoints et logs corrigés
require_once 'session_verification.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getPDOConnection();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? null;

// Router des actions
switch ($action) {
    case 'ajouter':
        ajouterAuPanier($pdo, $input);
        break;
    
    case 'supprimer':
        supprimerDuPanier($pdo, $input);
        break;
    
    case 'modifier':
    case 'update_quantite': // Alias pour compatibilité
        modifierQuantite($pdo, $input);
        break;
    
    case 'compter':
        compterArticles($pdo);
        break;
    
    case 'vider':
        viderPanier($pdo);
        break;
    
    case 'get':
        getPanier($pdo);
        break;
    
    case 'init_checkout':
        initCheckout($pdo);
        break;
    
    case 'test':
        testAPI($pdo);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => 'Action non valide: ' . $action]);
}

/**
 * Ajoute un produit au panier
 */
function ajouterAuPanier($pdo, $input) {
    $id_produit = filter_var($input['id_produit'] ?? 0, FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$id_produit || $id_produit <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        return;
    }
    
    if (!$quantite || $quantite < 1) $quantite = 1;
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier le produit
        $stmt = $pdo->prepare("
            SELECT p.id_produit, p.nom, p.reference, p.prix_ttc, 
                   p.description_courte, p.quantite_stock
            FROM produits p 
            WHERE p.id_produit = ? AND p.statut = 'actif'
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if (!$produit) {
            throw new Exception("Produit non trouvé ou indisponible");
        }
        
        if ($produit['quantite_stock'] < $quantite) {
            throw new Exception("Stock insuffisant. Disponible: " . $produit['quantite_stock']);
        }
        
        // Récupérer ou créer le panier
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
            $stmt = $pdo->prepare("
                INSERT INTO panier (id_client, session_id, statut, date_creation, date_modification) 
                VALUES (?, ?, 'actif', NOW(), NOW())
            ");
            $stmt->execute([$client_id, $session_id]);
            $id_panier = $pdo->lastInsertId();
            $_SESSION['panier_id'] = $id_panier;
        } else {
            $id_panier = $panier['id_panier'];
            $_SESSION['panier_id'] = $id_panier;
            
            $stmt = $pdo->prepare("UPDATE panier SET date_modification = NOW() WHERE id_panier = ?");
            $stmt->execute([$id_panier]);
        }
        
        // Vérifier si le produit est déjà dans le panier
        $stmt = $pdo->prepare("
            SELECT id_item, quantite FROM panier_items 
            WHERE id_panier = ? AND id_produit = ?
        ");
        $stmt->execute([$id_panier, $id_produit]);
        $item = $stmt->fetch();
        
        if ($item) {
            $nouvelle_quantite = $item['quantite'] + $quantite;
            if ($produit['quantite_stock'] < $nouvelle_quantite) {
                throw new Exception("Stock insuffisant pour la quantité demandée. Déjà " . $item['quantite'] . " dans le panier.");
            }
            
            $stmt = $pdo->prepare("
                UPDATE panier_items 
                SET quantite = ?, date_modification = NOW() 
                WHERE id_item = ?
            ");
            $stmt->execute([$nouvelle_quantite, $item['id_item']]);
            $quantite_finale = $nouvelle_quantite;
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout, date_modification) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id_panier, $id_produit, $quantite, $produit['prix_ttc']]);
            $quantite_finale = $quantite;
        }
        
        // Journaliser l'action - CORRIGÉ : pas d'id_log manuel
        $stmt = $pdo->prepare("
            INSERT INTO panier_logs 
            (id_panier, session_id, action, id_produit, nouvelle_quantite, ip_address, date_action) 
            VALUES (?, ?, 'ajout', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_panier, 
            $session_id, 
            $id_produit, 
            $quantite_finale, 
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        $pdo->commit();
        
        // Compter le total des articles
        $stmt = $pdo->prepare("SELECT SUM(quantite) as total FROM panier_items WHERE id_panier = ?");
        $stmt->execute([$id_panier]);
        $total_articles = (int)$stmt->fetchColumn();
        
        // Image par défaut
        $images_defaut = [
            1 => 'https://via.placeholder.com/300x300/2c3e50/ffffff?text=Bougie',
            2 => 'https://via.placeholder.com/300x300/27ae60/ffffff?text=Coffret',
            3 => 'https://via.placeholder.com/300x300/3498db/ffffff?text=Montre',
            4 => 'https://via.placeholder.com/300x300/e74c3c/ffffff?text=Bijoux'
        ];
        $image_url = $images_defaut[$id_produit] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit ajouté au panier avec succès',
            'produit' => [
                'id' => (int)$produit['id_produit'],
                'nom' => $produit['nom'],
                'reference' => $produit['reference'],
                'prix_ttc' => floatval($produit['prix_ttc']),
                'description_courte' => $produit['description_courte'],
                'image' => $image_url,
                'quantite_stock' => (int)$produit['quantite_stock']
            ],
            'panier' => [
                'id' => (int)$id_panier,
                'quantite_produit' => $quantite_finale,
                'total_articles' => $total_articles
            ],
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur ajout panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Récupère le contenu du panier
 */
function getPanier($pdo) {
    $result = getCartItems();
    echo json_encode($result);
}

/**
 * Compte les articles
 */
function compterArticles($pdo) {
    $total = countCartItems(true); // Force refresh
    
    echo json_encode([
        'success' => true,
        'total' => $total,
        'session_id' => session_id(),
        'client_id' => $_SESSION['id_client'] ?? null
    ]);
}

/**
 * Supprime un produit du panier
 */
function supprimerDuPanier($pdo, $input) {
    // Accepter soit id_item, soit id_produit
    $id_item = filter_var($input['id_item'] ?? 0, FILTER_VALIDATE_INT);
    $id_produit = filter_var($input['id_produit'] ?? 0, FILTER_VALIDATE_INT);
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Si on a reçu id_produit, il faut trouver l'id_item correspondant
        if ($id_produit > 0 && $id_item == 0) {
            if ($client_id) {
                $stmt = $pdo->prepare("
                    SELECT pi.id_item, pi.id_panier, pi.quantite
                    FROM panier_items pi
                    INNER JOIN panier p ON pi.id_panier = p.id_panier
                    WHERE pi.id_produit = ? AND p.id_client = ? AND p.statut = 'actif'
                    ORDER BY pi.date_ajout DESC LIMIT 1
                ");
                $stmt->execute([$id_produit, $client_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT pi.id_item, pi.id_panier, pi.quantite
                    FROM panier_items pi
                    INNER JOIN panier p ON pi.id_panier = p.id_panier
                    WHERE pi.id_produit = ? AND p.session_id = ? AND p.statut = 'actif'
                    ORDER BY pi.date_ajout DESC LIMIT 1
                ");
                $stmt->execute([$id_produit, $session_id]);
            }
            $item_info = $stmt->fetch();
            
            if ($item_info) {
                $id_item = $item_info['id_item'];
                $id_panier = $item_info['id_panier'];
                $quantite = $item_info['quantite'];
            } else {
                throw new Exception("Produit non trouvé dans le panier");
            }
        } else {
            // Récupérer les infos par id_item
            $stmt = $pdo->prepare("
                SELECT pi.id_panier, pi.id_produit, pi.quantite
                FROM panier_items pi
                INNER JOIN panier p ON pi.id_panier = p.id_panier
                WHERE pi.id_item = ? 
                AND (
                    (p.id_client = ? AND ? IS NOT NULL) 
                    OR 
                    (p.session_id = ? AND ? IS NULL)
                )
                AND p.statut = 'actif'
            ");
            $stmt->execute([$id_item, $client_id, $client_id, $session_id, $client_id]);
            $item_info = $stmt->fetch();
            
            if (!$item_info) {
                throw new Exception("Article non trouvé ou non autorisé");
            }
            
            $id_panier = $item_info['id_panier'];
            $id_produit = $item_info['id_produit'];
            $quantite = $item_info['quantite'];
        }
        
        // Supprimer l'article
        $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_item = ?");
        $stmt->execute([$id_item]);
        
        // Journaliser - CORRIGÉ : pas d'id_log manuel
        $stmt = $pdo->prepare("
            INSERT INTO panier_logs 
            (id_panier, session_id, action, id_produit, ancienne_quantite, ip_address, date_action) 
            VALUES (?, ?, 'suppression', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_panier,
            $session_id,
            $id_produit,
            $quantite,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit retiré du panier'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Modifie la quantité d'un produit
 */
function modifierQuantite($pdo, $input) {
    // Accepter soit id_item, soit id_produit
    $id_item = filter_var($input['id_item'] ?? 0, FILTER_VALIDATE_INT);
    $id_produit = filter_var($input['id_produit'] ?? 0, FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if ($quantite < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantité invalide']);
        return;
    }
    
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Si on a reçu id_produit mais pas id_item, trouver l'item
        if ($id_produit > 0 && $id_item == 0) {
            if ($client_id) {
                $stmt = $pdo->prepare("
                    SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite
                    FROM panier_items pi
                    INNER JOIN panier p ON pi.id_panier = p.id_panier
                    WHERE pi.id_produit = ? AND p.id_client = ? AND p.statut = 'actif'
                    ORDER BY pi.date_ajout DESC LIMIT 1
                ");
                $stmt->execute([$id_produit, $client_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite
                    FROM panier_items pi
                    INNER JOIN panier p ON pi.id_panier = p.id_panier
                    WHERE pi.id_produit = ? AND p.session_id = ? AND p.statut = 'actif'
                    ORDER BY pi.date_ajout DESC LIMIT 1
                ");
                $stmt->execute([$id_produit, $session_id]);
            }
            $item_info = $stmt->fetch();
            
            if ($item_info) {
                $id_item = $item_info['id_item'];
                $id_panier = $item_info['id_panier'];
                $ancienne_quantite = $item_info['ancienne_quantite'];
            } else {
                throw new Exception("Produit non trouvé dans le panier");
            }
        } else {
            // Vérifier le stock et récupérer les infos
            $stmt = $pdo->prepare("
                SELECT p.quantite_stock, pi.id_panier, pi.id_produit, pi.quantite as ancienne_quantite
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_item = ? 
                AND (
                    (pa.id_client = ? AND ? IS NOT NULL) 
                    OR 
                    (pa.session_id = ? AND ? IS NULL)
                )
                AND pa.statut = 'actif'
            ");
            $stmt->execute([$id_item, $client_id, $client_id, $session_id, $client_id]);
            $item_info = $stmt->fetch();
            
            if (!$item_info) {
                throw new Exception("Article non trouvé ou non autorisé");
            }
            
            $id_panier = $item_info['id_panier'];
            $id_produit = $item_info['id_produit'];
            $ancienne_quantite = $item_info['ancienne_quantite'];
            
            if ($item_info['quantite_stock'] < $quantite) {
                throw new Exception("Stock insuffisant. Disponible: " . $item_info['quantite_stock']);
            }
        }
        
        // Mettre à jour la quantité
        $stmt = $pdo->prepare("UPDATE panier_items SET quantite = ?, date_modification = NOW() WHERE id_item = ?");
        $stmt->execute([$quantite, $id_item]);
        
        // Journaliser - CORRIGÉ : pas d'id_log manuel
        $stmt = $pdo->prepare("
            INSERT INTO panier_logs 
            (id_panier, session_id, action, id_produit, ancienne_quantite, nouvelle_quantite, ip_address, date_action) 
            VALUES (?, ?, 'modification', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_panier,
            $session_id,
            $id_produit,
            $ancienne_quantite,
            $quantite,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur modification panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Vide le panier
 */
function viderPanier($pdo) {
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        if ($client_id) {
            $stmt = $pdo->prepare("
                SELECT id_panier FROM panier 
                WHERE id_client = ? AND statut = 'actif'
            ");
            $stmt->execute([$client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT id_panier FROM panier 
                WHERE session_id = ? AND statut = 'actif'
            ");
            $stmt->execute([$session_id]);
        }
        
        $panier = $stmt->fetch();
        
        if ($panier) {
            // Journaliser - CORRIGÉ : pas d'id_log manuel
            $stmt = $pdo->prepare("
                INSERT INTO panier_logs 
                (id_panier, session_id, action, ip_address, date_action) 
                VALUES (?, ?, 'vider', ?, NOW())
            ");
            $stmt->execute([$panier['id_panier'], $session_id, $_SERVER['REMOTE_ADDR'] ?? null]);
            
            $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ?");
            $stmt->execute([$panier['id_panier']]);
            
            $stmt = $pdo->prepare("UPDATE panier SET date_modification = NOW() WHERE id_panier = ?");
            $stmt->execute([$panier['id_panier']]);
        }
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Panier vidé avec succès']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur vidage panier: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors du vidage du panier']);
    }
}

/**
 * Initialise le checkout
 */
function initCheckout($pdo) {
    $session_id = session_id();
    $client_id = $_SESSION['id_client'] ?? null;
    
    try {
        // Vérifier que le panier n'est pas vide
        $result = getCartItems();
        
        if (!$result['success'] || empty($result['panier'])) {
            echo json_encode(['success' => false, 'message' => 'Panier vide']);
            return;
        }
        
        // Vérifier la disponibilité des stocks
        foreach ($result['panier'] as $item) {
            if (!$item['disponible']) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Stock insuffisant pour: ' . $item['nom']
                ]);
                return;
            }
        }
        
        // Stocker les infos en session pour le checkout
        $_SESSION['checkout_data'] = [
            'panier' => $result['panier'],
            'sous_total' => $result['sous_total'],
            'total_items' => $result['total_items'],
            'timestamp' => time()
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Checkout initialisé',
            'redirect' => 'livraison_form.php'
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur initCheckout: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'initialisation']);
    }
}

/**
 * Test de l'API
 */
function testAPI($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM produits WHERE statut = 'actif'");
        $count = $stmt->fetchColumn();
        
        $session_id = session_id();
        $client_id = $_SESSION['id_client'] ?? null;
        
        echo json_encode([
            'success' => true,
            'message' => 'API panier fonctionnelle',
            'timestamp' => time(),
            'session' => [
                'id' => $session_id,
                'client_id' => $client_id,
                'is_logged_in' => $client_id ? true : false
            ],
            'database' => [
                'connected' => true,
                'produits_actifs' => (int)$count
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur test API: ' . $e->getMessage()
        ]);
    }
}
?>