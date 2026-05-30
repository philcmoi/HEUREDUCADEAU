<?php
// catalogue.php - Version ULTRA SIMPLIFIÉE - SANS ANIMATIONS PROBLÉMATIQUES
// Date: 2026-05-29

require_once 'session_verification.php';

// Configuration de la pagination
$produits_par_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $produits_par_page;

// Récupération et validation de la catégorie
$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$is_all_categories = ($categorie_id <= 0);

$pdo = getPDOConnection();
$produits = [];
$categorie = null;
$total_produits = 0;
$total_pages = 1;
$erreur_bdd = false;
$message_erreur = '';

// ==============================================
// FONCTIONS DE GESTION DES PROMOTIONS
// ==============================================

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
        $reduction_amount = round($reduction_amount, 2);
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

if ($pdo) {
    try {
        if (!$is_all_categories) {
            $stmt_cat = $pdo->prepare("SELECT * FROM categories WHERE id_categorie = ? AND active = 1");
            $stmt_cat->execute([$categorie_id]);
            $categorie = $stmt_cat->fetch();
            
            if (!$categorie) {
                header('HTTP/1.0 404 Not Found');
                $erreur_bdd = true;
                $message_erreur = "Catégorie non trouvée";
            }
        }
        
        if (!$erreur_bdd) {
            if ($is_all_categories) {
                $stmt_count = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 'actif'");
            } else {
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE statut = 'actif' AND id_categorie = ?");
                $stmt_count->execute([$categorie_id]);
            }
            $total_produits = $stmt_count->fetchColumn();
            $total_pages = ceil($total_produits / $produits_par_page);
            
            if ($total_pages > 0 && $page > $total_pages) {
                $page = $total_pages;
                $offset = ($page - 1) * $produits_par_page;
            }
            
            if ($is_all_categories) {
                $sql = "SELECT DISTINCT p.*, c.nom as categorie_nom, c.id_categorie as categorie_id
                        FROM produits p
                        LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                        WHERE p.statut = 'actif'
                        ORDER BY p.date_creation DESC, p.id_produit DESC
                        LIMIT ? OFFSET ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$produits_par_page, $offset]);
            } else {
                $sql = "SELECT DISTINCT p.*, c.nom as categorie_nom, c.id_categorie as categorie_id
                        FROM produits p
                        LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                        WHERE p.statut = 'actif' AND p.id_categorie = ?
                        ORDER BY p.date_creation DESC, p.id_produit DESC
                        LIMIT ? OFFSET ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$categorie_id, $produits_par_page, $offset]);
            }
            
            $produits = $stmt->fetchAll();
            
            $produits_js = [];
            $images = [];
            
            if (!empty($produits)) {
                $ids = array_unique(array_column($produits, 'id_produit'));
                if (!empty($ids)) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt_imgs = $pdo->prepare("SELECT id_produit, url_image, principale 
                                                FROM images_produits 
                                                WHERE id_produit IN ($placeholders)
                                                ORDER BY principale DESC, ordre ASC, id_image ASC");
                    $stmt_imgs->execute($ids);
                    
                    while ($img = $stmt_imgs->fetch()) {
                        if (!isset($images[$img['id_produit']])) {
                            $images[$img['id_produit']] = $img['url_image'];
                        }
                    }
                }
                
                foreach ($produits as &$p) {
                    $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
                    if ($promo) {
                        $price_info = calculateDiscountedPrice(floatval($p['prix_ttc']), $promo);
                        $p['has_promotion'] = $price_info['has_promotion'];
                        $p['reduction_percent'] = $price_info['reduction_percent'];
                        $p['prix_promo'] = $price_info['price'];
                        $p['prix_original'] = $price_info['original_price'];
                        $p['reduction_amount'] = $price_info['reduction_amount'];
                        $p['promo_code'] = $price_info['code'];
                        $p['promo_type'] = $price_info['type'] ?? null;
                    } else {
                        $p['has_promotion'] = false;
                        $p['reduction_percent'] = 0;
                        $p['prix_promo'] = $p['prix_ttc'];
                        $p['prix_original'] = $p['prix_ttc'];
                        $p['reduction_amount'] = 0;
                        $p['promo_code'] = null;
                        $p['promo_type'] = null;
                    }
                }
            }
            
            foreach ($produits as $p) {
                $image_url = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                
                $produits_js[$p['id_produit']] = [
                    'id' => (int)$p['id_produit'],
                    'nom' => $p['nom'],
                    'reference' => $p['reference'],
                    'prix_ttc' => floatval($p['prix_promo']),
                    'prix_original' => floatval($p['prix_original']),
                    'reduction_percent' => $p['reduction_percent'],
                    'has_promotion' => $p['has_promotion'],
                    'description_courte' => $p['description_courte'],
                    'quantite_stock' => (int)$p['quantite_stock'],
                    'image' => $image_url,
                    'categorie_nom' => $p['categorie_nom'] ?? 'Cadeau'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Erreur récupération catalogue: " . $e->getMessage());
        $erreur_bdd = true;
        $message_erreur = $e->getMessage();
    }
} else {
    $erreur_bdd = true;
    $message_erreur = "Impossible de se connecter à la base de données";
}

$panier_data = getCartItemsFromBDD($pdo);
$panier_items = $panier_data['panier'] ?? [];
$panier_count = $panier_data['total_items'] ?? 0;
$panier_total = $panier_data['sous_total'] ?? 0;

if ($panier_count === 0 && isset($_SESSION[SESSION_KEY_PANIER]) && !empty($_SESSION[SESSION_KEY_PANIER])) {
    $panier_count = countCartItemsSession();
}

if ($is_all_categories) {
    $page_title = "Tous nos cadeaux";
} else {
    $page_title = $categorie ? htmlspecialchars($categorie['nom']) : "Catégorie introuvable";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           STYLES SIMPLIFIÉS - SANS ANIMATIONS PROBLÉMATIQUES
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; width: 100%; }
        
        /* HEADER */
        header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        
        .logo {
            color: white;
            text-decoration: none;
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo i { color: #e74c3c; }
        
        nav { display: flex; gap: 25px; align-items: center; flex: 1; justify-content: center; }
        
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.3s;
        }
        
        nav a:hover { background: rgba(255,255,255,0.1); }
        nav a.active { background: rgba(255,255,255,0.15); }
        
        .cart-wrapper { position: relative; }
        
        .cart-link {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.15);
            padding: 8px 15px;
            border-radius: 30px;
            margin-left: 10px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .cart-link:hover { background: #e74c3c; }
        
        .cart-count {
            background: #e74c3c;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 8px;
        }
        
        .nav-mobile {
            display: none;
            background: #34495e;
            padding: 20px;
            margin-top: 15px;
            border-radius: 0 0 12px 12px;
        }
        
        .nav-mobile.show { display: block; }
        
        .nav-mobile-list { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        
        .nav-mobile-link {
            color: white;
            text-decoration: none;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(255,255,255,0.05);
        }
        
        .nav-mobile-link:hover { background: #e74c3c; }
        
        /* DROPDOWN PANIER */
        .cart-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin-top: 10px;
            z-index: 1001;
            display: none;
            max-height: 500px;
            overflow-y: auto;
        }
        
        .cart-dropdown.show { display: block; }
        
        .cart-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 30px;
            width: 16px;
            height: 16px;
            background: white;
            transform: rotate(45deg);
        }
        
        .cart-dropdown-header {
            padding: 15px 20px;
            border-bottom: 2px solid #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
        }
        
        .cart-dropdown-header span { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.8rem; }
        
        .cart-dropdown-items { max-height: 300px; overflow-y: auto; }
        
        .cart-dropdown-item {
            display: flex;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
            position: relative;
        }
        
        .cart-dropdown-item:hover { background: #f8f9fa; }
        
        .cart-dropdown-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            background: #e9ecef;
        }
        
        .cart-dropdown-item-image img { width: 100%; height: 100%; object-fit: cover; }
        
        .cart-dropdown-item-info { flex: 1; }
        
        .cart-dropdown-item-info h4 {
            font-size: 0.95rem;
            color: #2c3e50;
            margin-bottom: 5px;
            padding-right: 25px;
        }
        
        .cart-dropdown-item-details { display: flex; justify-content: space-between; font-size: 0.9rem; }
        .cart-dropdown-item-price { color: #e74c3c; font-weight: 700; }
        .cart-dropdown-item-quantity { color: #7f8c8d; }
        
        .cart-dropdown-item-remove {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #95a5a6;
            cursor: pointer;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .cart-dropdown-item-remove:hover { color: #e74c3c; background: rgba(231,76,60,0.1); }
        
        .cart-dropdown-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            border-radius: 0 0 12px 12px;
        }
        
        .cart-dropdown-total { display: flex; justify-content: space-between; margin-bottom: 15px; font-weight: 600; }
        .cart-dropdown-total span:last-child { color: #e74c3c; font-size: 1.2rem; }
        
        .cart-dropdown-buttons { display: flex; gap: 10px; }
        
        .cart-dropdown-buttons .btn {
            flex: 1;
            padding: 12px;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
        }
        
        .cart-dropdown-buttons .btn-primary { background: #27ae60; color: white; }
        .cart-dropdown-buttons .btn-primary:hover { background: #219653; }
        .cart-dropdown-buttons .btn-secondary { background: #3498db; color: white; }
        .cart-dropdown-buttons .btn-secondary:hover { background: #2980b9; }
        
        .cart-dropdown-empty { padding: 40px 20px; text-align: center; color: #7f8c8d; }
        .cart-dropdown-empty i { font-size: 3rem; color: #bdc3c7; margin-bottom: 10px; }
        
        /* CATALOGUE HEADER */
        .catalogue-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px 0;
            margin-bottom: 40px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .catalogue-header h1 { font-size: 2.5rem; color: #2c3e50; margin-bottom: 10px; }
        .catalogue-header p { color: #7f8c8d; font-size: 1.1rem; max-width: 800px; }
        
        .breadcrumb { margin-bottom: 20px; color: #7f8c8d; }
        .breadcrumb a { color: #3498db; text-decoration: none; }
        .breadcrumb a:hover { color: #e74c3c; }
        .breadcrumb i { margin: 0 10px; font-size: 0.8rem; }
        
        /* GRILLE PRODUITS - STYLE SIMPLE SANS ANIMATION */
        .products-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            color: #7f8c8d;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
            min-height: 400px;
        }
        
        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: #e9ecef;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img { transform: scale(1.05); }
        
        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(52,152,219,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .product-card:hover .product-overlay { opacity: 1; }
        
        .product-overlay i {
            color: white;
            font-size: 2rem;
            background: rgba(255,255,255,0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: 0 4px 12px rgba(231,76,60,0.4);
        }
        
        .stock-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 1;
        }
        
        .stock-faible { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        .stock-rupture { background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white; }
        
        .product-price-wrapper { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; margin: 10px 0; }
        .old-price { font-size: 0.9rem; color: #95a5a6; text-decoration: line-through; }
        .new-price { font-size: 1.4rem; font-weight: 800; color: #e74c3c; }
        .product-price { font-size: 1.4rem; font-weight: 800; color: #2c3e50; margin: 10px 0; }
        
        .product-info { padding: 20px; }
        
        .product-category {
            display: inline-block;
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        
        .product-info h3 {
            font-size: 1.2rem;
            color: #2c3e50;
            margin-bottom: 10px;
            height: 50px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-rating { display: flex; align-items: center; gap: 5px; margin: 10px 0; }
        .product-rating i { color: #f1c40f; font-size: 0.9rem; }
        .rating-count { color: #7f8c8d; font-size: 0.85rem; }
        
        .product-actions { display: flex; gap: 10px; margin-top: 15px; }
        
        .btn-add-to-cart {
            flex: 1;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-add-to-cart:hover:not(:disabled) { background: linear-gradient(135deg, #219653, #1e8449); transform: translateY(-2px); }
        .btn-add-to-cart:disabled { background: #95a5a6; cursor: not-allowed; }
        
        .btn-view {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-view:hover { background: linear-gradient(135deg, #2980b9, #2573a7); transform: translateY(-2px); }
        
        /* PAGINATION */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin: 40px 0; flex-wrap: wrap; }
        
        .pagination-btn {
            background: white;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 8px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .pagination-btn:hover:not(:disabled) { background: #3498db; color: white; border-color: #3498db; }
        .pagination-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .pagination-numbers { display: flex; gap: 5px; flex-wrap: wrap; }
        
        .page-number {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .page-number:hover { background: #3498db; color: white; border-color: #3498db; }
        .page-number.active { background: #3498db; color: white; border-color: #3498db; font-weight: 600; }
        
        .page-dots { display: flex; align-items: center; padding: 0 5px; color: #7f8c8d; }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
        }
        
        .empty-state i { font-size: 4rem; color: #95a5a6; margin-bottom: 20px; }
        .empty-state h3 { font-size: 1.8rem; color: #2c3e50; margin-bottom: 10px; }
        
        /* FOOTER */
        footer {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            padding: 50px 0 30px;
            margin-top: 60px;
        }
        
        .footer-content { text-align: center; }
        .footer-content p { margin-bottom: 10px; color: #bdc3c7; font-size: 0.9rem; }
        .footer-content i { margin: 0 5px; font-size: 1.5rem; color: #bdc3c7; transition: color 0.3s; }
        .footer-content i:hover { color: #e74c3c; }
        
        /* MODAL */
        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .cart-modal.show { display: flex; }
        
        .cart-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .cart-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 2px solid #f8f9fa;
            position: sticky;
            top: 0;
            background: white;
        }
        
        .cart-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
        }
        
        .cart-modal-close:hover { background: #f8f9fa; color: #e74c3c; }
        
        .cart-modal-body { padding: 20px; }
        
        .cart-modal-product { display: flex; align-items: center; gap: 20px; padding: 20px; }
        
        .modal-product-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            overflow: hidden;
            background: #e9ecef;
        }
        
        .modal-product-image img { width: 100%; height: 100%; object-fit: cover; }
        
        .modal-product-info { flex: 1; }
        .modal-product-info h4 { margin-bottom: 10px; color: #2c3e50; }
        .modal-product-price { font-weight: 700; color: #e74c3c; font-size: 1.2rem; margin: 10px 0; }
        .modal-success-message { color: #27ae60; display: flex; align-items: center; gap: 8px; }
        
        .cart-modal-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            display: flex;
            gap: 12px;
        }
        
        .cart-modal-footer .btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        
        /* NOTIFICATIONS */
        .notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: #27ae60;
            color: white;
            padding: 18px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 2.5s forwards;
            min-width: 300px;
            max-width: 400px;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(100%); }
        }
        
        .notification.error { background: #e74c3c; }
        .notification i { font-size: 1.5rem; }
        
        .cart-count.pulse { animation: pulse 0.6s ease; }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .text-center { text-align: center; }
        
        /* RESPONSIVE */
        @media (max-width: 992px) {
            .header-content { flex-wrap: wrap; }
            nav { display: none; }
            .menu-toggle { display: block; }
            .cart-link { margin-left: auto; margin-right: 15px; }
            .cart-dropdown { width: 350px; }
        }
        
        @media (max-width: 768px) {
            .logo { font-size: 1.5rem; }
            .cart-link span:not(.cart-count) { display: none; }
            .cart-link { padding: 8px 12px; }
            .catalogue-header h1 { font-size: 2rem; }
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 20px; }
            .product-actions { flex-direction: column; }
            .btn-add-to-cart, .btn-view { width: 100%; }
            .cart-dropdown { width: 300px; right: 10px; }
        }
        
        @media (max-width: 576px) {
            .products-grid { grid-template-columns: 1fr; }
            .pagination-numbers { order: -1; width: 100%; justify-content: center; margin-bottom: 10px; }
            .page-number { min-width: 35px; height: 35px; }
            .cart-modal-product { flex-direction: column; text-align: center; }
            .modal-product-image { width: 100%; height: 150px; }
            .cart-modal-footer { flex-direction: column; }
            .cart-dropdown { width: 280px; right: 5px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"><i class="fas fa-gift"></i> HEURE DU CADEAU</a>
                <nav>
                    <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                    <a href="catalogue.php" class="active"><i class="fas fa-box-open"></i> Cadeaux</a>
                    <a href="apropos.html"><i class="fas fa-info-circle"></i> À propos</a>
                    <a href="contact.html"><i class="fas fa-envelope"></i> Contact</a>
                </nav>
                <div class="cart-wrapper">
                    <a href="panier.html" class="cart-link" id="cartToggle">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Panier</span>
                        <span class="cart-count" id="cartCount"><?= $panier_count ?></span>
                    </a>
                    <div class="cart-dropdown" id="cartDropdown">
                        <div class="cart-dropdown-header">Mon panier <span id="cartDropdownCount"><?= $panier_count ?> article(s)</span></div>
                        <div class="cart-dropdown-items" id="cartDropdownItems">
                            <?php if (empty($panier_items)): ?>
                                <div class="cart-dropdown-empty"><i class="fas fa-shopping-cart"></i><p>Votre panier est vide</p></div>
                            <?php else: 
                                $total_panier = 0;
                                foreach ($panier_items as $item): 
                                    $nom = $item['nom'] ?? 'Produit #' . $item['id_produit'];
                                    $prix = floatval($item['prix_unitaire'] ?? 0);
                                    $quantite = intval($item['quantite'] ?? 1);
                                    $sous_total = $prix * $quantite;
                                    $total_panier += $sous_total;
                                    $image = $item['image'] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                            ?>
                                <div class="cart-dropdown-item" data-id="<?= $item['id_produit'] ?>" data-item-id="<?= $item['id_item'] ?? '' ?>">
                                    <div class="cart-dropdown-item-image"><img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($nom) ?>" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'"></div>
                                    <div class="cart-dropdown-item-info">
                                        <h4><?= htmlspecialchars(mb_substr($nom, 0, 30)) ?><?= mb_strlen($nom) > 30 ? '...' : '' ?></h4>
                                        <div class="cart-dropdown-item-details"><span class="cart-dropdown-item-price"><?= number_format($prix, 2, ',', ' ') ?> €</span><span class="cart-dropdown-item-quantity">x<?= $quantite ?></span></div>
                                    </div>
                                    <div class="cart-dropdown-item-remove" onclick="panierManager.supprimerDuPanier(<?= $item['id_produit'] ?>, <?= $item['id_item'] ?? 0 ?>, event)"><i class="fas fa-times"></i></div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="cart-dropdown-footer">
                            <div class="cart-dropdown-total"><span>Total TTC</span><span id="cartDropdownTotal"><?= number_format($total_panier ?? 0, 2, ',', ' ') ?> €</span></div>
                            <div class="cart-dropdown-buttons">
                                <a href="panier.html" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Voir le panier</a>
                                <?php if (!empty($panier_items)): ?>
                                <a href="livraison_form.php" class="btn btn-secondary"><i class="fas fa-credit-card"></i> Commander</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
            </div>
            <nav class="nav-mobile" id="navMobile">
                <ul class="nav-mobile-list">
                    <li><a href="index.php" class="nav-mobile-link"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="catalogue.php" class="nav-mobile-link active"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                    <li><a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                    <li><a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <section class="catalogue-header">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php">Accueil</a> <i class="fas fa-chevron-right"></i>
                <?php if ($is_all_categories): ?>
                    <span>Catalogue</span>
                <?php else: ?>
                    <a href="catalogue.php">Catalogue</a> <i class="fas fa-chevron-right"></i>
                    <span><?= htmlspecialchars($categorie['nom']) ?></span>
                <?php endif; ?>
            </div>
            <h1><?= $page_title ?></h1>
            <?php if (!$is_all_categories && !empty($categorie['description'])): ?>
                <p><?= nl2br(htmlspecialchars($categorie['description'])) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="container">
        <div class="products-info">
            <span>Page <?= $page ?> sur <?= $total_pages ?></span>
            <span><?= count($produits) ?> produits sur <?= $total_produits ?></span>
        </div>

        <div class="products-grid" id="featuredProducts">
            <?php if ($erreur_bdd): ?>
                <div class="empty-state"><i class="fas fa-exclamation-triangle"></i><h3>Erreur de chargement</h3><p><?= htmlspecialchars($message_erreur) ?></p><button onclick="window.location.reload()" class="btn-add-to-cart" style="display: inline-block;"><i class="fas fa-sync-alt"></i> Réessayer</button></div>
            <?php elseif (empty($produits)): ?>
                <div class="empty-state"><i class="fas fa-box-open"></i><h3>Aucun produit <?= !$is_all_categories ? 'dans cette catégorie' : '' ?></h3><p>Découvrez nos autres catégories de cadeaux</p><a href="index.php" class="btn-add-to-cart" style="display: inline-block;"><i class="fas fa-arrow-left"></i> Voir toutes les catégories</a></div>
            <?php else: ?>
                <?php foreach ($produits as $produit): 
                    $prix_original = $produit['prix_original'] ?? $produit['prix_ttc'];
                    $prix_promo = $produit['prix_promo'] ?? $prix_original;
                    $reduction_percent = $produit['reduction_percent'] ?? 0;
                    $has_promotion = $produit['has_promotion'] ?? false;
                    $prix_affiche = number_format($prix_promo, 2, ',', ' ');
                    $prix_original_affiche = number_format($prix_original, 2, ',', ' ');
                    $image_url = isset($images[$produit['id_produit']]) ? $images[$produit['id_produit']] : 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                    $stock = $produit['quantite_stock'] ?? 0;
                    $en_stock = $stock > 0;
                    $stock_faible = $stock > 0 && $stock <= 5;
                ?>
                <div class="product-card" data-id="<?= $produit['id_produit'] ?>" id="product-<?= $produit['id_produit'] ?>">
                    <?php if ($has_promotion && $reduction_percent > 0): ?><span class="discount-badge">-<?= round($reduction_percent) ?>%</span><?php endif; ?>
                    <?php if (!$en_stock): ?><span class="stock-badge stock-rupture">Rupture</span><?php elseif ($stock_faible): ?><span class="stock-badge stock-faible">Plus que <?= $stock ?></span><?php endif; ?>
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($image_url) ?>" alt="<?= htmlspecialchars($produit['nom'] ?? 'Produit') ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        <div class="product-overlay"><i class="fas fa-eye"></i></div>
                    </div>
                    <div class="product-info">
                        <span class="product-category"><?= htmlspecialchars($produit['categorie_nom'] ?? ($is_all_categories ? 'Cadeau' : $categorie['nom'])) ?></span>
                        <h3><?= htmlspecialchars($produit['nom'] ?? 'Produit sans nom') ?></h3>
                        <?php if ($has_promotion && $prix_promo < $prix_original): ?>
                            <div class="product-price-wrapper"><span class="old-price"><?= $prix_original_affiche ?> €</span><span class="new-price"><?= $prix_affiche ?> €</span></div>
                        <?php else: ?>
                            <div class="product-price"><?= $prix_affiche ?> €</div>
                        <?php endif; ?>
                        <div class="product-rating"><?php for($i = 1; $i <= 5; $i++): ?><i class="fas fa-star"></i><?php endfor; ?><span class="rating-count">(<?= rand(5, 50) ?>)</span></div>
                        <div class="product-actions">
                            <button class="btn-add-to-cart" data-id="<?= $produit['id_produit'] ?>" <?= !$en_stock ? 'disabled' : '' ?> data-nom="<?= htmlspecialchars($produit['nom']) ?>" data-prix="<?= $prix_promo ?>" data-image="<?= $image_url ?>"><i class="fas fa-cart-plus"></i> <?= $en_stock ? 'Ajouter' : 'Rupture' ?></button>
                            <a href="produit.php?id=<?= $produit['id_produit'] ?>" class="btn-view"><i class="fas fa-eye"></i> Voir</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1 && !empty($produits)): ?>
        <div class="pagination">
            <?php $base_url = $is_all_categories ? 'catalogue.php' : 'catalogue.php?categorie=' . $categorie_id; ?>
            <?php if ($page > 1): ?><a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i> Précédent</a><?php else: ?><button class="pagination-btn" disabled><i class="fas fa-chevron-left"></i> Précédent</button><?php endif; ?>
            <div class="pagination-numbers"><?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                if ($start > 1) { echo '<a href="' . $base_url . '&page=1" class="page-number">1</a>'; if ($start > 2) echo '<span class="page-dots">...</span>'; }
                for ($i = $start; $i <= $end; $i++): ?><a href="<?= $base_url ?>&page=<?= $i ?>" class="page-number <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor;
                if ($end < $total_pages) { if ($end < $total_pages - 1) echo '<span class="page-dots">...</span>'; echo '<a href="' . $base_url . '&page=' . $total_pages . '" class="page-number">' . $total_pages . '</a>'; }
            ?></div>
            <?php if ($page < $total_pages): ?><a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="pagination-btn">Suivant <i class="fas fa-chevron-right"></i></a><?php else: ?><button class="pagination-btn" disabled>Suivant <i class="fas fa-chevron-right"></i></button><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center" style="margin-top: 20px; margin-bottom: 40px;">
            <a href="index.php" class="btn-home" style="display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #27ae60, #219653); color: white; border-radius: 8px; text-decoration: none; font-weight: 600;"><i class="fas fa-home"></i> Retour à l'accueil</a>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 HEURE DU CADEAU - Tous droits réservés</p>
                <p>Votre boutique de cadeaux élégants en ligne</p>
                <p style="margin-top: 15px"><i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i> <i class="fab fa-cc-paypal"></i></p>
            </div>
        </div>
    </footer>

    <div class="cart-modal" id="cartModal">
        <div class="cart-modal-content">
            <div class="cart-modal-header"><h3>Article ajouté au panier</h3><button class="cart-modal-close" id="closeCartModal">&times;</button></div>
            <div class="cart-modal-body" id="cartModalBody"></div>
            <div class="cart-modal-footer"><a href="panier.html" class="btn-add-to-cart">Voir le panier</a><button class="btn-view" id="continueShopping">Continuer mes achats</button></div>
        </div>
    </div>

    <script>
        const produitsData = <?= json_encode($produits_js ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
        const API_PANIER_URL = "panier.php";

        document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.getElementById("menuToggle");
            const navMobile = document.getElementById("navMobile");
            if (menuToggle && navMobile) {
                menuToggle.addEventListener("click", function(e) {
                    e.preventDefault();
                    navMobile.classList.toggle("show");
                    const icon = menuToggle.querySelector("i");
                    if (navMobile.classList.contains("show")) {
                        icon.classList.remove("fa-bars");
                        icon.classList.add("fa-times");
                    } else {
                        icon.classList.remove("fa-times");
                        icon.classList.add("fa-bars");
                    }
                });
                document.addEventListener("click", function(e) {
                    if (!navMobile.contains(e.target) && !menuToggle.contains(e.target) && navMobile.classList.contains("show")) {
                        navMobile.classList.remove("show");
                        const icon = menuToggle.querySelector("i");
                        icon.classList.remove("fa-times");
                        icon.classList.add("fa-bars");
                    }
                });
            }
        });

        class PanierManager {
            constructor() {
                this.apiUrl = API_PANIER_URL;
                this.cartModal = document.getElementById("cartModal");
                this.cartModalBody = document.getElementById("cartModalBody");
                this.cartCountElements = document.querySelectorAll(".cart-count");
                this.cartDropdown = document.getElementById("cartDropdown");
                this.cartDropdownItems = document.getElementById("cartDropdownItems");
                this.cartDropdownTotal = document.getElementById("cartDropdownTotal");
                this.cartDropdownCount = document.getElementById("cartDropdownCount");
                this.updateInProgress = false;
                this.produitsData = produitsData;
                this.initEvents();
                this.updateCartCount();
                this.refreshCartDropdown();
            }

            initEvents() {
                document.getElementById("closeCartModal")?.addEventListener("click", () => this.cartModal.classList.remove("show"));
                document.getElementById("continueShopping")?.addEventListener("click", () => this.cartModal.classList.remove("show"));
                this.cartModal?.addEventListener("click", (e) => { if (e.target === this.cartModal) this.cartModal.classList.remove("show"); });
                const cartToggle = document.getElementById("cartToggle");
                if (cartToggle && this.cartDropdown) {
                    cartToggle.addEventListener("click", (e) => { e.preventDefault(); this.cartDropdown.classList.toggle("show"); });
                    document.addEventListener("click", (e) => { if (!this.cartDropdown.contains(e.target) && !cartToggle.contains(e.target)) this.cartDropdown.classList.remove("show"); });
                }
                document.addEventListener("click", async (e) => {
                    const addToCartBtn = e.target.closest(".btn-add-to-cart");
                    if (addToCartBtn && !addToCartBtn.disabled && !addToCartBtn.closest('.pagination') && !addToCartBtn.closest('.empty-state') && !addToCartBtn.closest('.cart-modal-footer')) {
                        e.preventDefault();
                        e.stopPropagation();
                        const id_produit = addToCartBtn.dataset.id ? parseInt(addToCartBtn.dataset.id) : null;
                        if (id_produit) await this.ajouterAuPanier(id_produit, 1, addToCartBtn);
                    }
                });
            }

            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                if (!id_produit || id_produit <= 0) { this.showNotification("Erreur: Produit invalide", "error"); return false; }
                let produitInfo = this.produitsData[id_produit];
                if (!produitInfo && button) {
                    produitInfo = { id: id_produit, nom: button.dataset.nom || 'Produit', reference: 'REF' + id_produit, prix_ttc: parseFloat(button.dataset.prix) || 0, image: button.dataset.image || 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit' };
                }
                if (!produitInfo) { this.showNotification("Erreur: Produit non trouvé", "error"); return false; }
                let originalHTML = "", originalDisabled = false;
                if (button) { originalHTML = button.innerHTML; originalDisabled = button.disabled; button.disabled = true; button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...'; button.classList.add("loading"); }
                try {
                    const response = await fetch(this.apiUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: "ajouter", id_produit: parseInt(id_produit), quantite: parseInt(quantite) })
                    });
                    const data = await response.json();
                    if (data.success) {
                        await this.updateCartCount();
                        await this.refreshCartDropdown();
                        this.showCartModal(produitInfo);
                        this.showNotification(`"${produitInfo.nom}" ajouté au panier !`);
                        return true;
                    } else { this.showNotification(data.message || "Erreur lors de l'ajout", "error"); return false; }
                } catch (error) { console.error("Erreur ajout panier:", error); this.showNotification("Erreur de connexion au serveur", "error"); return false;
                } finally { if (button) { setTimeout(() => { button.disabled = originalDisabled; button.innerHTML = originalHTML; button.classList.remove("loading"); }, 1000); } }
            }

            async supprimerDuPanier(id_produit, id_item, event) {
                if (event) { event.preventDefault(); event.stopPropagation(); }
                try {
                    const response = await fetch(this.apiUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: "supprimer", id_produit: parseInt(id_produit), id_item: parseInt(id_item) })
                    });
                    const data = await response.json();
                    if (data.success) { await this.updateCartCount(); await this.refreshCartDropdown(); this.showNotification("Article retiré du panier"); }
                    else { this.showNotification(data.message || "Erreur de suppression", "error"); }
                } catch (error) { console.error("Erreur suppression:", error); this.showNotification("Erreur de connexion", "error"); }
            }

            async refreshCartDropdown() {
                try {
                    const response = await fetch(`${this.apiUrl}?action=get&_=${Date.now()}`);
                    const data = await response.json();
                    if (data.success && this.cartDropdownCount && this.cartDropdownTotal) {
                        this.cartDropdownCount.textContent = data.total_items + ' article(s)';
                        const total = (data.sous_total || 0).toFixed(2).replace('.', ',');
                        this.cartDropdownTotal.textContent = total + ' €';
                        if (this.cartDropdownItems && data.panier && data.panier.length > 0) {
                            let itemsHtml = '';
                            for (const item of data.panier) {
                                itemsHtml += `<div class="cart-dropdown-item" data-id="${item.id_produit}" data-item-id="${item.id_item}">
                                    <div class="cart-dropdown-item-image"><img src="${item.image || 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'}" alt="${item.nom}" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'"></div>
                                    <div class="cart-dropdown-item-info"><h4>${item.nom.substring(0, 30)}${item.nom.length > 30 ? '...' : ''}</h4>
                                    <div class="cart-dropdown-item-details"><span class="cart-dropdown-item-price">${parseFloat(item.prix_unitaire).toFixed(2).replace('.', ',')} €</span><span class="cart-dropdown-item-quantity">x${item.quantite}</span></div></div>
                                    <div class="cart-dropdown-item-remove" onclick="panierManager.supprimerDuPanier(${item.id_produit}, ${item.id_item}, event)"><i class="fas fa-times"></i></div>
                                </div>`;
                            }
                            this.cartDropdownItems.innerHTML = itemsHtml;
                        } else if (this.cartDropdownItems) {
                            this.cartDropdownItems.innerHTML = `<div class="cart-dropdown-empty"><i class="fas fa-shopping-cart"></i><p>Votre panier est vide</p></div>`;
                        }
                    }
                } catch (error) { console.error("Erreur refresh dropdown:", error); }
            }

            showCartModal(product) {
                if (!product || !this.cartModalBody) return;
                const prix = product.prix_ttc ? parseFloat(product.prix_ttc).toFixed(2).replace(".", ",") : "0,00";
                this.cartModalBody.innerHTML = `<div class="cart-modal-product"><div class="modal-product-image"><img src="${product.image}" alt="${product.nom}" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'"></div><div class="modal-product-info"><h4>${product.nom}</h4><p class="modal-product-ref">Réf: ${product.reference || 'REF' + product.id}</p><p class="modal-product-price">${prix} €</p><p class="modal-success-message"><i class="fas fa-check-circle"></i> Article ajouté avec succès !</p></div></div>`;
                this.cartModal.classList.add("show");
            }

            async updateCartCount() {
                if (this.updateInProgress) return;
                this.updateInProgress = true;
                try {
                    const response = await fetch(`${this.apiUrl}?action=compter&_=${Date.now()}`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) { this.updateCartCountDisplay(data.total || 0); return data.total || 0; }
                    }
                    this.updateCartCountDisplay(0);
                    return 0;
                } catch (error) { console.error("Erreur mise à jour compteur:", error); this.updateCartCountDisplay(0); return 0;
                } finally { this.updateInProgress = false; }
            }

            updateCartCountDisplay(count) {
                this.cartCountElements.forEach((element) => {
                    if (count > 0) { element.textContent = count > 99 ? "99+" : count; element.style.display = "inline-flex"; element.classList.add("pulse"); setTimeout(() => element.classList.remove("pulse"), 600); }
                    else { element.textContent = "0"; element.style.display = "inline-flex"; }
                });
            }

            showNotification(message, type = "success") {
                document.querySelectorAll(".notification").forEach(toast => toast.remove());
                const notification = document.createElement("div");
                notification.className = `notification ${type}`;
                const icon = type === "success" ? "check-circle" : "exclamation-triangle";
                notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
                document.body.appendChild(notification);
                setTimeout(() => { if (notification.parentElement) notification.remove(); }, 3000);
            }
        }

        window.panierManager = new PanierManager();
        window.ajouterAuPanier = function(id_produit, quantite = 1, button = null) {
            if (!window.panierManager) window.panierManager = new PanierManager();
            return window.panierManager.ajouterAuPanier(id_produit, quantite, button);
        };
    </script>
</body>
</html>