<?php
// panier.php - API de gestion du panier
// VERSION CORRIGÉE - Utilise les nouvelles fonctions de session_verification.php
// Tout en conservant les acquis du passé

require_once 'session_verification.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Gestion des requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$pdo = getPDOConnection();
if (!$pdo) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de connexion à la base de données'
    ]);
    exit;
}

// Récupération de l'action
$action = $_GET['action'] ?? null;

// Si c'est une requête POST, essayer de lire le body JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['action'])) {
        $action = $input['action'];
    }
}

// Router des actions
switch ($action) {
    case 'ajouter':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        ajouterAuPanier($pdo, $input);
        break;
    
    case 'supprimer':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        supprimerDuPanier($pdo, $input);
        break;
    
    case 'modifier':
    case 'update_quantite':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
        $input = json_decode(file_get_contents('php://input'), true);
        modifierQuantite($pdo, $input);
        break;
    
    case 'compter':
        compterArticles($pdo);
        break;
    
    case 'vider':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
            break;
        }
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
        echo json_encode([
            'success' => false, 
            'message' => 'Action non valide: ' . $action,
            'available_actions' => ['ajouter', 'supprimer', 'modifier', 'compter', 'vider', 'get', 'init_checkout', 'test']
        ]);
}

/**
 * Récupère le contenu du panier depuis la BDD
 * Utilise la nouvelle fonction getCartItemsFromBDD() de session_verification.php
 */
function getPanier($pdo) {
    $result = getCartItemsFromBDD($pdo);
    echo json_encode($result);
}

/**
 * Compte les articles dans le panier
 */
function compterArticles($pdo) {
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    try {
        if ($client_id) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pi.quantite), 0) as total 
                FROM panier p
                INNER JOIN panier_items pi ON p.id_panier = pi.id_panier
                WHERE p.id_client = ? AND p.statut = 'actif'
            ");
            $stmt->execute([$client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(pi.quantite), 0) as total 
                FROM panier p
                INNER JOIN panier_items pi ON p.id_panier = pi.id_panier
                WHERE p.session_id = ? AND p.statut = 'actif'
            ");
            $stmt->execute([$session_id]);
        }
        
        $total = (int)$stmt->fetchColumn();
        
        echo json_encode([
            'success' => true,
            'total' => $total
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur compterArticles: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'total' => 0,
            'message' => $e->getMessage()
        ]);
    }
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
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
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
            $_SESSION[SESSION_KEY_PANIER_ID] = $id_panier;
        } else {
            $id_panier = $panier['id_panier'];
            $_SESSION[SESSION_KEY_PANIER_ID] = $id_panier;
            
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
            
            // Mettre à jour la session également
            addToCartSession($id_produit, $quantite, $produit['prix_ttc']);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout, date_modification) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id_panier, $id_produit, $quantite, $produit['prix_ttc']]);
            $quantite_finale = $quantite;
            
            // Ajouter à la session également
            addToCartSession($id_produit, $quantite, $produit['prix_ttc']);
        }
        
        // Journaliser l'action
        try {
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
        } catch (Exception $e) {
            // Ignorer les erreurs de log
        }
        
        $pdo->commit();
        
        // Compter le total des articles
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(quantite), 0) as total FROM panier_items WHERE id_panier = ?");
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
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Supprime un produit du panier
 */
function supprimerDuPanier($pdo, $input) {
    $id_produit = filter_var($input['id_produit'] ?? 0, FILTER_VALIDATE_INT);
    
    if (!$id_produit || $id_produit <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        return;
    }
    
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Trouver l'item et le panier
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
        
        if (!$item_info) {
            throw new Exception("Produit non trouvé dans le panier");
        }
        
        $id_item = $item_info['id_item'];
        $id_panier = $item_info['id_panier'];
        $quantite = $item_info['quantite'];
        
        // Supprimer l'article
        $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_item = ?");
        $stmt->execute([$id_item]);
        
        // Supprimer de la session également
        removeFromCartSession($id_produit);
        
        // Journaliser
        try {
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
        } catch (Exception $e) {
            // Ignorer les erreurs de log
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit retiré du panier'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Modifie la quantité d'un produit
 */
function modifierQuantite($pdo, $input) {
    $id_produit = filter_var($input['id_produit'] ?? 0, FILTER_VALIDATE_INT);
    $quantite = filter_var($input['quantite'] ?? 1, FILTER_VALIDATE_INT);
    
    if (!$id_produit || $id_produit <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        return;
    }
    
    if ($quantite < 1) {
        echo json_encode(['success' => false, 'message' => 'Quantité invalide']);
        return;
    }
    
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    try {
        $pdo->beginTransaction();
        
        // Trouver l'item et vérifier le stock
        if ($client_id) {
            $stmt = $pdo->prepare("
                SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite, p.quantite_stock
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_produit = ? AND pa.id_client = ? AND pa.statut = 'actif'
                ORDER BY pi.date_ajout DESC LIMIT 1
            ");
            $stmt->execute([$id_produit, $client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite, p.quantite_stock
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_produit = ? AND pa.session_id = ? AND pa.statut = 'actif'
                ORDER BY pi.date_ajout DESC LIMIT 1
            ");
            $stmt->execute([$id_produit, $session_id]);
        }
        
        $item_info = $stmt->fetch();
        
        if (!$item_info) {
            throw new Exception("Produit non trouvé dans le panier");
        }
        
        if ($item_info['quantite_stock'] < $quantite) {
            throw new Exception("Stock insuffisant. Disponible: " . $item_info['quantite_stock']);
        }
        
        $id_item = $item_info['id_item'];
        $id_panier = $item_info['id_panier'];
        $ancienne_quantite = $item_info['ancienne_quantite'];
        
        // Mettre à jour la quantité
        $stmt = $pdo->prepare("UPDATE panier_items SET quantite = ?, date_modification = NOW() WHERE id_item = ?");
        $stmt->execute([$quantite, $id_item]);
        
        // Mettre à jour la session
        // Pour la session, on doit d'abord supprimer l'ancien puis ajouter le nouveau
        removeFromCartSession($id_produit);
        addToCartSession($id_produit, $quantite, null); // Le prix sera récupéré plus tard
        
        // Journaliser
        try {
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
        } catch (Exception $e) {
            // Ignorer les erreurs de log
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quantité mise à jour'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur modification panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Vide le panier
 */
function viderPanier($pdo) {
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    try {
        $pdo->beginTransaction();
        
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
        
        if ($panier) {
            // Journaliser
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO panier_logs 
                    (id_panier, session_id, action, ip_address, date_action) 
                    VALUES (?, ?, 'vider', ?, NOW())
                ");
                $stmt->execute([$panier['id_panier'], $session_id, $_SERVER['REMOTE_ADDR'] ?? null]);
            } catch (Exception $e) {
                // Ignorer les erreurs de log
            }
            
            $stmt = $pdo->prepare("DELETE FROM panier_items WHERE id_panier = ?");
            $stmt->execute([$panier['id_panier']]);
            
            $stmt = $pdo->prepare("UPDATE panier SET date_modification = NOW() WHERE id_panier = ?");
            $stmt->execute([$panier['id_panier']]);
        }
        
        // Vider la session également
        clearCartSession();
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Panier vidé avec succès'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur vidage panier: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors du vidage du panier'
        ]);
    }
}

/**
 * Initialise le checkout
 */
function initCheckout($pdo) {
    try {
        // Récupérer le panier depuis la BDD
        $result = getCartItemsFromBDD($pdo);
        
        if (!$result['success'] || empty($result['panier'])) {
            echo json_encode([
                'success' => false, 
                'message' => 'Panier vide'
            ]);
            return;
        }
        
        // Vérifier la disponibilité des stocks
        foreach ($result['panier'] as $item) {
            if (isset($item['disponible']) && !$item['disponible']) {
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
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur lors de l\'initialisation'
        ]);
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
        $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
        
        // Tester la nouvelle fonction
        $panier_test = getCartItemsFromBDD($pdo);
        
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
            ],
            'panier_test' => [
                'count' => count($panier_test['panier'] ?? []),
                'total_items' => $panier_test['total_items'] ?? 0
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