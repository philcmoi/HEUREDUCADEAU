<?php
// catalogue.php - Page catalogue avec gestion panier, promotions ET FILTRAGE PAR CATÉGORIE
// VERSION CORRIGÉE - Même présentation que index.php (étoiles, badges, stock)
// Date: 2026-06-08

require_once 'session_verification.php';

// Récupération du filtre catégorie
$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$categorie_nom = '';

$pdo = getPDOConnection();

// ==============================================
// FONCTIONS DE GESTION DES PROMOTIONS (IDENTIQUES À INDEX.PHP)
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

// ==============================================
// RÉCUPÉRATION DES CATÉGORIES POUR LE FILTRE (DYNAMIQUE)
// ==============================================

$categories_list = [];
try {
    $stmt_cat_list = $pdo->prepare("SELECT id_categorie, nom, slug FROM categories WHERE active = 1 AND parent_id IS NULL ORDER BY ordre ASC, nom ASC");
    $stmt_cat_list->execute();
    $categories_list = $stmt_cat_list->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Erreur récupération catégories: " . $e->getMessage());
}

// Récupérer le nom de la catégorie si un filtre est actif
if ($categorie_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT nom FROM categories WHERE id_categorie = ? AND active = 1");
    $stmt_cat->execute([$categorie_id]);
    $categorie_nom = $stmt_cat->fetchColumn();
    if (!$categorie_nom) {
        $categorie_id = 0;
    }
}

// ==============================================
// RÉCUPÉRATION DES PRODUITS AVEC OU SANS FILTRE
// ==============================================

if ($categorie_id > 0) {
    $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif' 
            AND id_categorie = :categorie_id
            ORDER BY id_produit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['categorie_id' => $categorie_id]);
} else {
    $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif' 
            ORDER BY id_produit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dédoublonnage
$produits_temp = [];
foreach ($resultats as $row) {
    $produits_temp[$row['id_produit']] = $row;
}
$produits = array_values($produits_temp);

// Récupération des images
$images = [];
if (!empty($produits)) {
    $ids = array_column($produits, 'id_produit');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_img = $pdo->prepare("SELECT id_produit, url_image FROM images_produits WHERE id_produit IN ($placeholders) AND principale = 1");
    $stmt_img->execute($ids);
    while ($img = $stmt_img->fetch(PDO::FETCH_ASSOC)) {
        $images[$img['id_produit']] = $img['url_image'];
    }
}

// Traitement des promotions et préparation des données (comme index.php)
$produits_final = [];
foreach ($produits as $p) {
    $prix_ttc = round($p['prix_ht'] * (1 + $p['tva'] / 100), 2);
    
    $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
    
    if ($promo) {
        $price_info = calculateDiscountedPrice($prix_ttc, $promo);
        $has_promotion = $price_info['has_promotion'];
        $reduction_percent = $price_info['reduction_percent'];
        $prix_promo = $price_info['price'];
        $prix_original = $price_info['original_price'];
        $promo_code = $price_info['code'];
    } else {
        $has_promotion = false;
        $reduction_percent = 0;
        $prix_promo = $prix_ttc;
        $prix_original = $prix_ttc;
        $promo_code = null;
    }
    
    $produits_final[] = [
        'id_produit' => $p['id_produit'],
        'nom' => $p['nom'],
        'prix_ht' => $p['prix_ht'],
        'tva' => $p['tva'],
        'prix_ttc' => $prix_ttc,
        'quantite_stock' => $p['quantite_stock'],
        'has_promotion' => $has_promotion,
        'reduction_percent' => $reduction_percent,
        'prix_promo' => $prix_promo,
        'prix_original' => $prix_original,
        'promo_code' => $promo_code
    ];
}
$produits = $produits_final;

// Construction du tableau JS pour le panier
$produits_js = [];
foreach ($produits as $p) {
    $image_url = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=' . urlencode($p['nom']);
    
    $produits_js[$p['id_produit']] = [
        'id' => $p['id_produit'],
        'nom' => $p['nom'],
        'reference' => 'REF' . $p['id_produit'],
        'prix_ttc' => floatval($p['prix_promo']),
        'prix_original' => floatval($p['prix_original']),
        'reduction_percent' => $p['reduction_percent'],
        'has_promotion' => $p['has_promotion'],
        'description_courte' => '',
        'image' => $image_url,
        'quantite_stock' => intval($p['quantite_stock'])
    ];
}

$nb_articles = countCartItems();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue<?= $categorie_nom ? ' - ' . htmlspecialchars($categorie_nom) : '' ?> - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           STYLES COMPLETS - IDENTIQUES À INDEX.PHP
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
        
        /* HERO SECTION (version simplifiée pour catalogue) */
        .catalogue-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px 0;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .catalogue-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .catalogue-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        /* BARRE DE FILTRE CATÉGORIE */
        .category-filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .category-filter-bar .filter-links {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
        }
        
        .category-filter-bar .filter-links a {
            color: #2c3e50;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .category-filter-bar .filter-links a:hover {
            background: #3498db;
            color: white;
        }
        
        .category-filter-bar .filter-links a.active {
            background: #e74c3c;
            color: white;
        }
        
        .category-filter-bar .current-category {
            font-weight: bold;
            color: #2c3e50;
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 30px;
        }
        
        .category-filter-bar .current-category i {
            color: #e74c3c;
            margin-right: 8px;
        }
        
        .clear-filter {
            background: #f8f9fa;
            color: #2c3e50;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
            margin-left: 10px;
        }
        
        .clear-filter:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* GRILLE PRODUITS - IDENTIQUE À INDEX.PHP */
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
        
        .product-price-wrapper { display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; margin: 10px 0; }
        .old-price { font-size: 0.9rem; color: #95a5a6; text-decoration: line-through; }
        .new-price { font-size: 1.4rem; font-weight: 800; color: #e74c3c; }
        .product-price { font-size: 1.4rem; font-weight: 800; color: #2c3e50; margin: 10px 0; }
        
        .product-info { padding: 20px; }
        
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
        
        .stock {
            display: inline-block;
            font-size: 0.85rem;
            padding: 5px 12px;
            border-radius: 20px;
            margin: 10px 0;
        }
        
        .stock.in-stock {
            background: #d4edda;
            color: #155724;
        }
        
        .stock.low-stock {
            background: #fff3cd;
            color: #856404;
        }
        
        .stock.out-of-stock {
            background: #f8d7da;
            color: #721c24;
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
        .btn-add-to-cart.loading { opacity: 0.7; cursor: wait; }
        
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
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            grid-column: 1 / -1;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
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
        
        /* MODAL PANIER */
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
        
        .btn-primary { background: #27ae60; color: white; }
        .btn-primary:hover { background: #219653; }
        .btn-secondary { background: #3498db; color: white; }
        .btn-secondary:hover { background: #2980b9; }
        
        /* NOTIFICATIONS */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #27ae60;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            min-width: 280px;
            max-width: 400px;
        }
        
        .toast-notification.error { background: #e74c3c; }
        .toast-notification.warning { background: #f39c12; }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
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
            .category-filter-bar { flex-direction: column; align-items: flex-start; }
        }
        
        @media (max-width: 768px) {
            .logo { font-size: 1.5rem; }
            .cart-link span:not(.cart-count) { display: none; }
            .cart-link { padding: 8px 12px; }
            .catalogue-header h1 { font-size: 1.8rem; }
            .products-grid { grid-template-columns: 1fr; }
            .product-actions { flex-direction: column; }
            .btn-add-to-cart, .btn-view { width: 100%; }
        }
        
        @media (max-width: 480px) {
            .logo { font-size: 1.2rem; }
            .catalogue-header h1 { font-size: 1.5rem; }
            .cart-modal-product { flex-direction: column; text-align: center; }
            .modal-product-image { width: 100%; height: 150px; }
            .cart-modal-footer { flex-direction: column; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"><i class="fas fa-gift"></i> HEURE DU CADEAU</a>
                <nav>
                    <a href="index.php">Accueil</a>
                    <a href="catalogue.php" class="active"><i class="fas fa-box-open"></i> Cadeaux</a>
                    <a href="apropos.html"><i class="fas fa-info-circle"></i> À propos</a>
                    <a href="contact.html"><i class="fas fa-envelope"></i> Contact</a>
                </nav>
                <a href="panier.html" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Panier</span>
                    <span class="cart-count" id="cartCount"><?= $nb_articles ?></span>
                </a>
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

    <div class="catalogue-header">
        <div class="container">
            <h1><i class="fas fa-gift"></i> Notre catalogue</h1>
            <p><?= $categorie_nom ? "Cadeaux pour " . htmlspecialchars($categorie_nom) : "Trouvez le cadeau parfait pour toutes vos occasions" ?></p>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>
        
        <!-- Barre de filtre catégorie DYNAMIQUE -->
        <div class="category-filter-bar">
            <div class="filter-links">
                <i class="fas fa-filter"></i> Filtrer par catégorie :
                <a href="catalogue.php" class="<?= $categorie_id == 0 ? 'active' : '' ?>">Tous</a>
                <?php foreach ($categories_list as $cat): ?>
                    <a href="?categorie=<?= $cat['id_categorie'] ?>" class="<?= $categorie_id == $cat['id_categorie'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['nom']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($categorie_id > 0 && $categorie_nom): ?>
                <div class="current-category">
                    <i class="fas fa-tag"></i> Catégorie : <?= htmlspecialchars($categorie_nom) ?>
                    <a href="catalogue.php" class="clear-filter"><i class="fas fa-times"></i> Effacer</a>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>Aucun produit trouvé</h3>
                <p><?= $categorie_nom ? "Aucun produit n'est disponible dans la catégorie " . htmlspecialchars($categorie_nom) . " pour le moment." : "La boutique est actuellement vide." ?></p>
                <a href="catalogue.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block; width: auto;"><i class="fas fa-arrow-left"></i> Voir tous les produits</a>
            </div>
        <?php else: ?>
            <div class="products-info">
                <span><i class="fas fa-tag"></i> <?= count($produits) ?> produit(s) trouvé(s)</span>
                <span><i class="fas fa-shopping-cart"></i> Ajoutez vos articles au panier</span>
            </div>
            <div class="products-grid">
                <?php foreach ($produits as $produit): 
                    $prix_original = $produit['prix_original'] ?? $produit['prix_ttc'];
                    $prix_promo = $produit['prix_promo'] ?? $prix_original;
                    $reduction_percent = $produit['reduction_percent'] ?? 0;
                    $has_promotion = $produit['has_promotion'] ?? false;
                    $prix_affiche = number_format($prix_promo, 2, ',', ' ');
                    $prix_original_affiche = number_format($prix_original, 2, ',', ' ');
                    $image_url = isset($images[$produit['id_produit']]) ? $images[$produit['id_produit']] : 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=' . urlencode($produit['nom']);
                    $stock = $produit['quantite_stock'];
                    $stock_class = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
                    $stock_text = $stock > 10 ? 'En stock' : ($stock > 0 ? 'Stock faible : ' . $stock : 'Rupture de stock');
                    // Note aléatoire pour démonstration (à remplacer par la vraie note de la BDD)
                    $note = round($produit['note_moyenne'] ?? rand(35, 50) / 10, 1);
                    $nb_avis = $produit['nombre_avis'] ?? rand(5, 50);
                ?>
                <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                    <?php if ($has_promotion && $reduction_percent > 0): ?>
                        <span class="discount-badge">-<?= round($reduction_percent) ?>%</span>
                    <?php endif; ?>
                    <div class="product-image">
                        <img src="<?= htmlspecialchars($image_url) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        <div class="product-overlay">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                        <div class="stock <?= $stock_class ?>">
                            <i class="fas <?= $stock > 10 ? 'fa-check-circle' : ($stock > 0 ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                            <?= $stock_text ?>
                        </div>
                        <?php if ($has_promotion && $prix_promo < $prix_original): ?>
                            <div class="product-price-wrapper">
                                <span class="old-price"><?= $prix_original_affiche ?> €</span>
                                <span class="new-price"><?= $prix_affiche ?> €</span>
                            </div>
                        <?php else: ?>
                            <div class="product-price"><?= $prix_affiche ?> €</div>
                        <?php endif; ?>
                        
                        <!-- ÉTOILES DE SATISFACTION CLIENTS (COMME DANS INDEX.PHP) -->
                        <div class="product-rating">
                            <?php 
                            $note_entiere = floor($note);
                            $note_decimal = $note - $note_entiere;
                            for($i = 1; $i <= 5; $i++): 
                                if ($i <= $note_entiere): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i == $note_entiere + 1 && $note_decimal >= 0.5): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif;
                            endfor; ?>
                            <span class="rating-count">(<?= $nb_avis ?>)</span>
                        </div>
                        
                        <div class="product-actions">
                            <button class="btn-add-to-cart" 
                                    data-id="<?= $produit['id_produit'] ?>"
                                    data-nom="<?= htmlspecialchars($produit['nom']) ?>"
                                    data-prix="<?= $prix_promo ?>"
                                    data-image="<?= htmlspecialchars($image_url) ?>"
                                    <?= $stock <= 0 ? 'disabled' : '' ?>>
                                <i class="fas fa-cart-plus"></i> <?= $stock > 0 ? 'Ajouter au panier' : 'Indisponible' ?>
                            </button>
                            <a href="produit.php?id=<?= $produit['id_produit'] ?>" class="btn-view">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 HEURE DU CADEAU - Tous droits réservés</p>
                <p>Votre boutique de cadeaux élégants en ligne</p>
                <p style="margin-top: 15px"><i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i> <i class="fab fa-cc-paypal"></i></p>
            </div>
        </div>
    </footer>

    <!-- Modal panier -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h3><i class="fas fa-check-circle" style="color:#27ae60"></i> Article ajouté</h3>
                <button class="cart-modal-close" id="closeCartModal">&times;</button>
            </div>
            <div class="cart-modal-body" id="cartModalBody"></div>
            <div class="cart-modal-footer">
                <a href="panier.html" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Voir le panier</a>
                <button class="btn btn-secondary" id="continueShopping"><i class="fas fa-arrow-left"></i> Continuer</button>
            </div>
        </div>
    </div>

    <script>
        // Données des produits pour l'API panier
        const produitsData = <?= json_encode($produits_js ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
        const API_PANIER_URL = "panier.php";

        // Gestionnaire de panier (IDENTIQUE À INDEX.PHP)
        class PanierManager {
            constructor() {
                this.apiUrl = API_PANIER_URL;
                this.cartModal = document.getElementById("cartModal");
                this.cartModalBody = document.getElementById("cartModalBody");
                this.cartCountElements = document.querySelectorAll(".cart-count");
                this.updateInProgress = false;
                this.produitsData = produitsData;
                this.initEvents();
                this.updateCartCount();
            }

            initEvents() {
                document.getElementById("closeCartModal")?.addEventListener("click", () => this.closeModal());
                document.getElementById("continueShopping")?.addEventListener("click", () => this.closeModal());
                this.cartModal?.addEventListener("click", (e) => { if (e.target === this.cartModal) this.closeModal(); });
                document.addEventListener("click", async (e) => {
                    const addToCartBtn = e.target.closest(".btn-add-to-cart");
                    if (addToCartBtn && !addToCartBtn.disabled && !addToCartBtn.classList.contains("loading")) {
                        e.preventDefault();
                        e.stopPropagation();
                        const id_produit = addToCartBtn.dataset.id ? parseInt(addToCartBtn.dataset.id) : null;
                        if (id_produit) await this.ajouterAuPanier(id_produit, 1, addToCartBtn);
                    }
                });
            }

            closeModal() {
                this.cartModal?.classList.remove("show");
            }

            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                if (!id_produit || id_produit <= 0) {
                    this.showNotification("Erreur: Produit invalide", "error");
                    return false;
                }

                const produitInfo = this.produitsData[id_produit];
                if (produitInfo && produitInfo.quantite_stock <= 0) {
                    this.showNotification("Produit en rupture de stock", "error");
                    return false;
                }

                let finalInfo = produitInfo;
                
                if (!produitInfo && button) {
                    finalInfo = {
                        id: id_produit,
                        nom: button.dataset.nom || 'Produit',
                        reference: 'REF' + id_produit,
                        prix_ttc: parseFloat(button.dataset.prix) || 0,
                        image: button.dataset.image || 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'
                    };
                }
                
                if (!finalInfo) {
                    this.showNotification("Erreur: Produit non trouvé", "error");
                    return false;
                }

                let originalHTML = "", originalDisabled = false;
                if (button) {
                    originalHTML = button.innerHTML;
                    originalDisabled = button.disabled;
                    button.disabled = true;
                    button.classList.add("loading");
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ action: "ajouter", id_produit: parseInt(id_produit), quantite: parseInt(quantite) })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await this.updateCartCount();
                        this.showCartModal(finalInfo);
                        this.showNotification(`"${finalInfo.nom}" ajouté au panier !`);
                        return true;
                    } else {
                        this.showNotification(data.message || "Erreur lors de l'ajout", "error");
                        return false;
                    }
                } catch (error) {
                    console.error("Erreur ajout panier:", error);
                    this.showNotification("Erreur de connexion au serveur", "error");
                    return false;
                } finally {
                    if (button) {
                        setTimeout(() => {
                            button.disabled = originalDisabled;
                            button.innerHTML = originalHTML;
                            button.classList.remove("loading");
                        }, 800);
                    }
                }
            }

            showCartModal(product) {
                if (!product || !this.cartModalBody) return;
                
                const prix = product.prix_ttc ? parseFloat(product.prix_ttc).toFixed(2).replace(".", ",") : "0,00";
                
                this.cartModalBody.innerHTML = `
                    <div class="cart-modal-product">
                        <div class="modal-product-image">
                            <img src="${product.image}" alt="${product.nom}" 
                                 onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        </div>
                        <div class="modal-product-info">
                            <h4>${this.escapeHtml(product.nom)}</h4>
                            <p class="modal-product-ref">Réf: ${product.reference || 'REF' + product.id}</p>
                            <p class="modal-product-price">${prix} €</p>
                            <p class="modal-success-message">
                                <i class="fas fa-check-circle"></i> Article ajouté avec succès !
                            </p>
                        </div>
                    </div>
                `;
                this.cartModal.classList.add("show");
            }

            async updateCartCount() {
                if (this.updateInProgress) return;
                this.updateInProgress = true;
                
                try {
                    const response = await fetch(`${this.apiUrl}?action=compter&_=${Date.now()}`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            this.updateCartCountDisplay(data.total || 0);
                            return data.total || 0;
                        }
                    }
                    this.updateCartCountDisplay(0);
                    return 0;
                } catch (error) {
                    console.error("Erreur mise à jour compteur:", error);
                    this.updateCartCountDisplay(0);
                    return 0;
                } finally {
                    this.updateInProgress = false;
                }
            }

            updateCartCountDisplay(count) {
                this.cartCountElements.forEach((element) => {
                    if (count > 0) {
                        element.textContent = count > 99 ? "99+" : count;
                        element.style.display = "inline-flex";
                        element.classList.add("pulse");
                        setTimeout(() => element.classList.remove("pulse"), 600);
                    } else {
                        element.textContent = "0";
                        element.style.display = "inline-flex";
                    }
                });
            }

            showNotification(message, type = "success") {
                document.querySelectorAll(".toast-notification").forEach(toast => toast.remove());
                
                const notification = document.createElement("div");
                notification.className = `toast-notification ${type}`;
                
                let icon = "check-circle";
                if (type === "error") icon = "exclamation-triangle";
                else if (type === "warning") icon = "info-circle";
                
                notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = "slideOutRight 0.3s ease";
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }

            escapeHtml(text) {
                const div = document.createElement("div");
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialisation
        document.addEventListener("DOMContentLoaded", function() {
            window.panierManager = new PanierManager();
            
            // Menu mobile
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
    </script>
</body>
</html>