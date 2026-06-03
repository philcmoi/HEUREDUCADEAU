<?php
// panier.php - API de gestion du panier
// VERSION CORRIGÉE - PREND EN COMPTE LES PRIX PROMOTIONNELS

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

// ============================================
// FONCTIONS DE PROMOTIONS
// ============================================

/**
 * Récupère la meilleure promotion active pour un produit
 */
function getBestActivePromotionForProduct($pdo, $product_id) {
    $sql = "SELECT p.*, pp.reduction_personnalisee 
            FROM promotions p
            INNER JOIN promotions_produits pp ON p.id_promotion = pp.id_promotion
            WHERE pp.id_produit = :product_id
              AND p.actif = 1 
              AND p.date_debut <= NOW() 
              AND p.date_fin >= NOW()
            ORDER BY 
                CASE 
                    WHEN p.type_promotion = 'pourcentage' AND pp.reduction_personnalisee IS NOT NULL 
                        THEN pp.reduction_personnalisee
                    WHEN p.type_promotion = 'pourcentage' THEN p.valeur
                    WHEN p.type_promotion = 'montant_fixe' THEN 100
                    ELSE 0
                END DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $product_id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($promo) {
        return $promo;
    }
    
    // Vérifier les promotions par catégorie
    $sql = "SELECT p.* 
            FROM promotions p
            INNER JOIN promotions_categories pc ON p.id_promotion = pc.id_promotion
            INNER JOIN produits pr ON pr.id_categorie = pc.id_categorie
            WHERE pr.id_produit = :product_id
              AND p.actif = 1 
              AND p.date_debut <= NOW() 
              AND p.date_fin >= NOW()
              AND p.type_promotion != 'livraison_gratuite'
            ORDER BY 
                CASE 
                    WHEN p.type_promotion = 'pourcentage' THEN p.valeur
                    WHEN p.type_promotion = 'montant_fixe' THEN 100
                    ELSE 0
                END DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $product_id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $promo ?: null;
}

/**
 * Calcule le prix avec réduction
 */
function calculateDiscountedPrice($original_price, $promotion) {
    if (!$promotion) {
        return [
            'price' => $original_price,
            'original_price' => $original_price,
            'reduction_amount' => 0,
            'reduction_percent' => 0,
            'has_promotion' => false
        ];
    }
    
    $discounted_price = $original_price;
    $reduction_percent = 0;
    $reduction_amount = 0;
    
    $reduction_value = $promotion['reduction_personnalisee'] ?? $promotion['valeur'];
    
    if ($promotion['type_promotion'] == 'pourcentage') {
        $reduction_percent = floatval($reduction_value);
        $reduction_amount = $original_price * ($reduction_percent / 100);
        $discounted_price = $original_price - $reduction_amount;
        $discounted_price = round($discounted_price, 2);
    } elseif ($promotion['type_promotion'] == 'montant_fixe') {
        $reduction_amount = floatval($reduction_value);
        $discounted_price = max(0, $original_price - $reduction_amount);
        $reduction_percent = ($reduction_amount / $original_price) * 100;
        $discounted_price = round($discounted_price, 2);
    }
    
    return [
        'price' => $discounted_price,
        'original_price' => $original_price,
        'reduction_amount' => $reduction_amount,
        'reduction_percent' => round($reduction_percent),
        'has_promotion' => true,
        'type' => $promotion['type_promotion'],
        'code' => $promotion['code_promotion']
    ];
}

/**
 * Récupère le contenu du panier depuis la BDD avec prix promotionnels
 */
function getPanier($pdo) {
    $session_id = session_id();
    $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? null;
    
    $panier_items = [];
    $total_items = 0;
    $sous_total = 0;
    
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
        
        if ($panier) {
            $_SESSION[SESSION_KEY_PANIER_ID] = $panier['id_panier'];
            
            // Récupérer les items
            $stmt_items = $pdo->prepare("
                SELECT pi.id_item, pi.id_produit, pi.quantite, pi.prix_unitaire,
                       p.nom, p.reference, p.prix_ttc, p.quantite_stock, p.statut
                FROM panier_items pi
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_panier = ?
            ");
            $stmt_items->execute([$panier['id_panier']]);
            $items = $stmt_items->fetchAll();
            
            foreach ($items as $item) {
                $prix_original = floatval($item['prix_ttc']);
                $quantite = intval($item['quantite']);
                
                // Vérifier la promotion
                $promo = getBestActivePromotionForProduct($pdo, $item['id_produit']);
                $discount = calculateDiscountedPrice($prix_original, $promo);
                $prix_unitaire = $discount['price'];
                $prix_total = $prix_unitaire * $quantite;
                
                $total_items += $quantite;
                $sous_total += $prix_total;
                
                // Récupérer l'image
                $stmt_img = $pdo->prepare("
                    SELECT url_image FROM images_produits 
                    WHERE id_produit = ? AND principale = 1 
                    LIMIT 1
                ");
                $stmt_img->execute([$item['id_produit']]);
                $image = $stmt_img->fetchColumn();
                
                $panier_items[] = [
                    'id_item' => (int)$item['id_item'],
                    'id_produit' => (int)$item['id_produit'],
                    'nom' => $item['nom'],
                    'reference' => $item['reference'],
                    'quantite' => $quantite,
                    'prix_unitaire' => $prix_unitaire,
                    'prix_original' => $prix_original,
                    'prix_total' => $prix_total,
                    'has_promotion' => $discount['has_promotion'],
                    'reduction_percent' => $discount['reduction_percent'],
                    'image' => $image ?: $this->getDefaultImage($item['id_produit']),
                    'stock' => (int)$item['quantite_stock'],
                    'disponible' => $item['quantite_stock'] >= $quantite && $item['statut'] === 'actif'
                ];
            }
        }
        
        // Mettre à jour la session
        $_SESSION[SESSION_KEY_PANIER] = [];
        foreach ($panier_items as $item) {
            addToCartSession($item['id_produit'], $item['quantite'], $item['prix_unitaire']);
        }
        
        echo json_encode([
            'success' => true,
            'panier' => $panier_items,
            'total_items' => $total_items,
            'sous_total' => round($sous_total, 2),
            'timestamp' => time()
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur getPanier: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement du panier',
            'panier' => [],
            'total_items' => 0,
            'sous_total' => 0
        ]);
    }
}

/**
 * Image par défaut
 */
function getDefaultImage($productId) {
    $images = [
        31 => '/uploads/produits/69fec8e82a0c3_20260509_054056.jpg',
        42 => '/uploads/produits/6a190930be4a3_20260529_033408.png',
    ];
    return $images[$productId] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
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
        
        // Vérifier le produit avec son prix
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
        
        // Vérifier la promotion pour avoir le prix correct
        $promo = getBestActivePromotionForProduct($pdo, $id_produit);
        $discount = calculateDiscountedPrice(floatval($produit['prix_ttc']), $promo);
        $prix_promo = $discount['price'];
        
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
                SET quantite = ?, date_modification = NOW(), prix_unitaire = ? 
                WHERE id_item = ?
            ");
            $stmt->execute([$nouvelle_quantite, $prix_promo, $item['id_item']]);
            $quantite_finale = $nouvelle_quantite;
            
            // Mettre à jour la session
            addToCartSession($id_produit, $quantite, $prix_promo);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout, date_modification) 
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$id_panier, $id_produit, $quantite, $prix_promo]);
            $quantite_finale = $quantite;
            
            // Ajouter à la session
            addToCartSession($id_produit, $quantite, $prix_promo);
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
        
        // Récupérer l'image
        $stmt_img = $pdo->prepare("SELECT url_image FROM images_produits WHERE id_produit = ? AND principale = 1 LIMIT 1");
        $stmt_img->execute([$id_produit]);
        $image_url = $stmt_img->fetchColumn() ?: getDefaultImage($id_produit);
        
        echo json_encode([
            'success' => true,
            'message' => 'Produit ajouté au panier avec succès',
            'produit' => [
                'id' => (int)$produit['id_produit'],
                'nom' => $produit['nom'],
                'reference' => $produit['reference'],
                'prix_ttc' => $prix_promo,
                'prix_original' => floatval($produit['prix_ttc']),
                'has_promotion' => $discount['has_promotion'],
                'reduction_percent' => $discount['reduction_percent'],
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
                SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite, 
                       p.quantite_stock, p.prix_ttc
                FROM panier_items pi
                INNER JOIN panier pa ON pi.id_panier = pa.id_panier
                INNER JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_produit = ? AND pa.id_client = ? AND pa.statut = 'actif'
                ORDER BY pi.date_ajout DESC LIMIT 1
            ");
            $stmt->execute([$id_produit, $client_id]);
        } else {
            $stmt = $pdo->prepare("
                SELECT pi.id_item, pi.id_panier, pi.quantite as ancienne_quantite,
                       p.quantite_stock, p.prix_ttc
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
        
        // Vérifier la promotion pour le prix
        $promo = getBestActivePromotionForProduct($pdo, $id_produit);
        $discount = calculateDiscountedPrice(floatval($item_info['prix_ttc']), $promo);
        $prix_promo = $discount['price'];
        
        // Mettre à jour la quantité et le prix
        $stmt = $pdo->prepare("
            UPDATE panier_items 
            SET quantite = ?, date_modification = NOW(), prix_unitaire = ? 
            WHERE id_item = ?
        ");
        $stmt->execute([$quantite, $prix_promo, $id_item]);
        
        // Mettre à jour la session
        removeFromCartSession($id_produit);
        addToCartSession($id_produit, $quantite, $prix_promo);
        
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
        $result = json_decode(getPanierData($pdo), true);
        
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
        $panier_test = json_decode(getPanierData($pdo), true);
        
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

/**
 * Récupère les données du panier (helper)
 */
function getPanierData($pdo) {
    ob_start();
    getPanier($pdo);
    return ob_get_clean();
}
?>