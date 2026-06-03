<?php
// index.php - Page d'accueil avec gestion panier, pagination ET PROMOTIONS
// VERSION CORRIGÉE - Avec modal message modifiable
// Date: 2026-06-03

require_once 'session_verification.php';

// Configuration de la pagination
$produits_par_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $produits_par_page;

$pdo = getPDOConnection();
$produits = [];
$total_produits = 0;
$total_pages = 1;
$erreur_bdd = false;
$message_erreur = '';

// ==============================================
// CONFIGURATION DE LA MODAL (MESSAGE MODIFIABLE)
// ==============================================
// Vous pouvez modifier ces variables pour changer le message de la modal
$modal_titre = "Bienvenue chez HEURE DU CADEAU !";
$modal_message = "Vous pouvez utiliser ce site dans toute sa puissance. Effectuer des achats sans paiement réel. Des identifiants de paiement, vous seront fournis lorsque vous effectuerez un paiement fictif.";
$modal_afficher = true; // Mettre à false pour désactiver la modal
$modal_icone = "fa-gift"; // Icône Font Awesome à afficher

// Vous pouvez également stocker ces valeurs dans la base de données
// Décommentez le code ci-dessous pour les récupérer depuis la table configuration

/*
try {
    $stmt_modal = $pdo->prepare("SELECT valeur FROM configuration WHERE cle = 'modal_titre'");
    $stmt_modal->execute();
    $modal_titre_db = $stmt_modal->fetchColumn();
    if ($modal_titre_db) $modal_titre = $modal_titre_db;
    
    $stmt_modal = $pdo->prepare("SELECT valeur FROM configuration WHERE cle = 'modal_message'");
    $stmt_modal->execute();
    $modal_message_db = $stmt_modal->fetchColumn();
    if ($modal_message_db) $modal_message = $modal_message_db;
    
    $stmt_modal = $pdo->prepare("SELECT valeur FROM configuration WHERE cle = 'modal_active'");
    $stmt_modal->execute();
    $modal_active_db = $stmt_modal->fetchColumn();
    if ($modal_active_db !== false) $modal_afficher = ($modal_active_db == '1');
    
    $stmt_modal = $pdo->prepare("SELECT valeur FROM configuration WHERE cle = 'modal_icone'");
    $stmt_modal->execute();
    $modal_icone_db = $stmt_modal->fetchColumn();
    if ($modal_icone_db) $modal_icone = $modal_icone_db;
} catch (Exception $e) {
    // Ignorer les erreurs de configuration, utiliser les valeurs par défaut
}
*/

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

// ==============================================
// RÉCUPÉRATION DES PRODUITS - AVEC STOCK COMME CATALOGUE.PHP
// ==============================================

if ($pdo) {
    try {
        // Requête IDENTIQUE à catalogue.php - récupération des produits actifs
        $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
                FROM produits 
                WHERE statut = 'actif' 
                ORDER BY id_produit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Nettoyage : suppression des éventuels doublons par ID
        $produits_temp = [];
        foreach ($resultats as $row) {
            if (!isset($produits_temp[$row['id_produit']])) {
                $produits_temp[$row['id_produit']] = $row;
            }
        }
        $produits = array_values($produits_temp);
        
        $total_produits = count($produits);
        $total_pages = ceil($total_produits / $produits_par_page);
        
        // Pagination manuelle
        $produits = array_slice($produits, $offset, $produits_par_page);
        
        // Récupération des images comme dans catalogue.php
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
        
        // Ajouter les informations nécessaires pour l'affichage (comme catalogue.php)
        $produits_final = [];
        foreach ($produits as $p) {
            // Calcul du prix TTC
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
        
    } catch (Exception $e) {
        error_log("Erreur récupération produits: " . $e->getMessage());
        $erreur_bdd = true;
        $message_erreur = $e->getMessage();
    }
} else {
    $erreur_bdd = true;
    $message_erreur = "Impossible de se connecter à la base de données";
}

$nb_articles = countCartItems();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HEURE DU CADEAU - Boutique de cadeaux uniques</title>
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
        
        /* HERO SECTION */
        .hero {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .hero-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }
        
        .hero-title {
            font-size: 2.8rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 30px;
            max-width: 90%;
        }
        
        .hero-buttons { display: flex; gap: 15px; flex-wrap: wrap; }
        
        .btn {
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #219653, #1e8449);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #2980b9, #2573a7);
        }
        
        .hero-image img {
            width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* SECTIONS */
        section { padding: 60px 0; }
        
        .section-title {
            font-size: 2.2rem;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 40px;
        }
        
        .categories-grid,
        .services-grid,
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        
        .category-card,
        .service-card,
        .testimonial-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            text-align: center;
        }
        
        .category-card:hover,
        .service-card:hover,
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
        }
        
        .category-icon,
        .service-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        
        .category-icon i,
        .service-icon i { font-size: 2.5rem; color: #3498db; }
        
        .category-card h3,
        .service-card h3 { font-size: 1.4rem; color: #2c3e50; margin-bottom: 15px; }
        
        .category-card p,
        .service-card p { color: #7f8c8d; margin-bottom: 20px; }
        
        .category-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s;
        }
        
        .category-link:hover { gap: 10px; color: #2980b9; }
        
        .testimonial-rating { margin-bottom: 15px; }
        .testimonial-rating i { color: #f1c40f; margin: 0 2px; }
        .testimonial-text { font-style: italic; color: #555; margin-bottom: 20px; }
        
        .testimonial-author { display: flex; align-items: center; gap: 15px; justify-content: center; }
        
        .author-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .author-info h4 { color: #2c3e50; margin-bottom: 5px; }
        .author-info p { color: #7f8c8d; font-size: 0.9rem; }
        
        .featured-products { background: #f8f9fa; }
        
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
        
        /* STYLES POUR L'AFFICHAGE DU STOCK (COMME CATALOGUE.PHP) */
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
        
        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 40px 0 20px;
            flex-wrap: wrap;
        }
        
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
        
        /* NEWSLETTER */
        .newsletter {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
        }
        
        .newsletter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 40px;
            flex-wrap: wrap;
        }
        
        .newsletter-content h2 { font-size: 2rem; margin-bottom: 10px; }
        
        .newsletter-form {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 500px;
        }
        
        .newsletter-form input {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            outline: none;
        }
        
        .newsletter-form .btn-primary { padding: 15px 30px; white-space: nowrap; }
        
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
        
        /* MODAL BIENVENUE (MESSAGE MODIFIABLE) */
        .welcome-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 3000;
            justify-content: center;
            align-items: center;
            padding: 20px;
            backdrop-filter: blur(3px);
        }
        
        .welcome-modal.show { display: flex; }
        
        .welcome-modal-content {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
            animation: modalFadeIn 0.4s ease;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        .welcome-modal-header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 25px;
            text-align: center;
            border-radius: 24px 24px 0 0;
        }
        
        .welcome-modal-header i {
            font-size: 3rem;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .welcome-modal-header h2 {
            font-size: 1.8rem;
            margin: 0;
        }
        
        .welcome-modal-body {
            padding: 30px;
            text-align: center;
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
        }
        
        .welcome-modal-footer {
            padding: 20px 30px 30px;
            text-align: center;
            border-top: 1px solid #eee;
        }
        
        .welcome-modal-footer .btn {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .welcome-modal-footer .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39,174,96,0.3);
        }
        
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
        
        .products-loading,
        .products-empty,
        .products-error {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 16px;
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .products-loading i,
        .products-empty i,
        .products-error i { font-size: 3rem; margin-bottom: 20px; }
        .products-loading i { color: #3498db; }
        .products-empty i { color: #7f8c8d; }
        .products-error i { color: #e74c3c; }
        
        .text-center { text-align: center; }
        
        /* RESPONSIVE */
        @media (max-width: 992px) {
            .header-content { flex-wrap: wrap; }
            nav { display: none; }
            .menu-toggle { display: block; }
            .cart-link { margin-left: auto; margin-right: 15px; }
            .hero-container { grid-template-columns: 1fr; text-align: center; }
            .hero-content { order: 2; }
            .hero-image { order: 1; }
            .hero-subtitle { max-width: 100%; margin: 0 auto 30px; }
            .hero-buttons { justify-content: center; }
            .categories-grid,
            .services-grid,
            .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        @media (max-width: 768px) {
            .logo { font-size: 1.5rem; }
            .cart-link span:not(.cart-count) { display: none; }
            .cart-link { padding: 8px 12px; }
            .hero-title { font-size: 2.2rem; }
            .hero-subtitle { font-size: 1rem; }
            .btn { padding: 12px 25px; }
            section { padding: 40px 0; }
            .section-title { font-size: 1.8rem; }
            .categories-grid,
            .services-grid,
            .testimonials-grid,
            .products-grid { grid-template-columns: 1fr; }
            .product-actions { flex-direction: column; }
            .btn-add-to-cart,
            .btn-view { width: 100%; }
            .newsletter-container { flex-direction: column; text-align: center; }
            .newsletter-form { flex-direction: column; width: 100%; }
            .newsletter-form input,
            .newsletter-form button { width: 100%; }
            .toast-notification { min-width: 280px; max-width: 280px; right: 20px; left: 20px; margin: 0 auto; }
            .pagination { gap: 5px; }
            .pagination-btn { padding: 8px 12px; }
            .welcome-modal-header h2 { font-size: 1.4rem; }
            .welcome-modal-body { padding: 20px; font-size: 1rem; }
        }
        
        @media (max-width: 480px) {
            .logo { font-size: 1.2rem; }
            .hero-title { font-size: 1.8rem; }
            .hero-buttons { flex-direction: column; }
            .hero-buttons .btn { width: 100%; }
            .section-title { font-size: 1.6rem; }
            .cart-modal-product { flex-direction: column; text-align: center; }
            .modal-product-image { width: 100%; height: 150px; }
            .cart-modal-footer { flex-direction: column; }
            .pagination-numbers { order: -1; width: 100%; justify-content: center; margin-bottom: 10px; }
            .page-number { min-width: 35px; height: 35px; }
            .welcome-modal-header i { font-size: 2rem; }
            .welcome-modal-header h2 { font-size: 1.2rem; }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo"><i class="fas fa-gift"></i> HEURE DU CADEAU</a>
                <nav>
                    <a href="index.php" class="active"><i class="fas fa-home"></i> Accueil</a>
                    <a href="catalogue.php"><i class="fas fa-box-open"></i> Cadeaux</a>
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
                    <li><a href="index.php" class="nav-mobile-link active"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="catalogue.php" class="nav-mobile-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                    <li><a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                    <li><a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- MODAL BIENVENUE (MESSAGE MODIFIABLE) -->
    <?php if ($modal_afficher): ?>
    <div class="welcome-modal" id="welcomeModal">
        <div class="welcome-modal-content">
            <div class="welcome-modal-header">
                <i class="fas <?= htmlspecialchars($modal_icone) ?>"></i>
                <h2><?= htmlspecialchars($modal_titre) ?></h2>
            </div>
            <div class="welcome-modal-body">
                <p><?= nl2br(htmlspecialchars($modal_message)) ?></p>
            </div>
            <div class="welcome-modal-footer">
                <button class="btn" id="closeWelcomeModal"><i class="fas fa-check-circle"></i> Commencer</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <section class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Des cadeaux qui marquent les esprits</h1>
                <p class="hero-subtitle">Découvrez notre sélection exclusive de cadeaux originaux pour toutes les occasions</p>
                <div class="hero-buttons">
                    <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-gift"></i> Explorer la collection</a>
                    <a href="#categories" class="btn btn-secondary"><i class="fas fa-tags"></i> Voir les catégories</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="img/hero-banner.jpg" alt="Collection de cadeaux élégants" onerror="this.src='https://via.placeholder.com/600x400?text=Cadeaux+élégants'">
            </div>
        </div>
    </section>

    <section class="categories" id="categories">
        <div class="container">
            <h2 class="section-title">Nos catégories de cadeaux</h2>
            <p class="section-subtitle">Trouvez le cadeau parfait selon l'occasion</p>
            <div class="categories-grid">
                <div class="category-card"><div class="category-icon"><i class="fas fa-birthday-cake"></i></div><h3>Anniversaires</h3><p>Cadeaux uniques pour célébrer les anniversaires</p><a href="catalogue.php?categorie=2" class="category-link">Voir les produits →</a></div>
                <div class="category-card"><div class="category-icon"><i class="fas fa-heart"></i></div><h3>Saint-Valentin</h3><p>Romantique et mémorable</p><a href="catalogue.php?categorie=3" class="category-link">Voir les produits →</a></div>
                <div class="category-card"><div class="category-icon"><i class="fas fa-glass-cheers"></i></div><h3>Mariage</h3><p>Cadeaux de mariage élégants</p><a href="catalogue.php?categorie=4" class="category-link">Voir les produits →</a></div>
                <div class="category-card"><div class="category-icon"><i class="fas fa-baby"></i></div><h3>Naissance</h3><p>Pour accueillir bébé</p><a href="catalogue.php?categorie=5" class="category-link">Voir les produits →</a></div>
                <div class="category-card"><div class="category-icon"><i class="fas fa-graduation-cap"></i></div><h3>Diplômés</h3><p>Pour célébrer la réussite</p><a href="catalogue.php?categorie=6" class="category-link">Voir les produits →</a></div>
                <div class="category-card"><div class="category-icon"><i class="fas fa-christmas-tree"></i></div><h3>Noël</h3><p>Magie des fêtes de fin d'année</p><a href="catalogue.php?categorie=7" class="category-link">Voir les produits →</a></div>
            </div>
        </div>
    </section>

    <section class="featured-products">
        <div class="container">
            <h2 class="section-title">Tous nos produits</h2>
            <p class="section-subtitle"><?= $total_produits ?> produits disponibles</p>
            <div class="products-info">
                <span>Page <?= $page ?> sur <?= $total_pages ?></span>
                <span><?= count($produits) ?> produits affichés</span>
            </div>
            <div class="products-grid" id="featuredProducts">
                <?php if ($erreur_bdd): ?>
                    <div class="products-error"><i class="fas fa-exclamation-triangle"></i><h3>Erreur de chargement</h3><p><?= htmlspecialchars($message_erreur) ?></p><button onclick="window.location.reload()" class="btn btn-primary" style="margin-top: 20px;"><i class="fas fa-sync-alt"></i> Réessayer</button></div>
                <?php elseif (empty($produits)): ?>
                    <div class="products-empty"><i class="fas fa-box-open"></i><h3>Aucun produit disponible</h3><p>La boutique est actuellement vide. Revenez bientôt !</p></div>
                <?php else: ?>
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
                    ?>
                    <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                        <?php if ($has_promotion && $reduction_percent > 0): ?><span class="discount-badge">-<?= round($reduction_percent) ?>%</span><?php endif; ?>
                        <div class="product-image">
                            <img src="<?= htmlspecialchars($image_url) ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                            <div class="product-overlay"><i class="fas fa-eye"></i></div>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                            <div class="stock <?= $stock_class ?>">
                                <i class="fas <?= $stock > 10 ? 'fa-check-circle' : ($stock > 0 ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                                <?= $stock_text ?>
                            </div>
                            <?php if ($has_promotion && $prix_promo < $prix_original): ?>
                                <div class="product-price-wrapper"><span class="old-price"><?= $prix_original_affiche ?> €</span><span class="new-price"><?= $prix_affiche ?> €</span></div>
                            <?php else: ?>
                                <div class="product-price"><?= $prix_affiche ?> €</div>
                            <?php endif; ?>
                            <div class="product-rating"><?php for($i = 1; $i <= 5; $i++): ?><i class="fas fa-star"></i><?php endfor; ?><span class="rating-count">(<?= rand(5, 50) ?>)</span></div>
                            <div class="product-actions">
                                <button class="btn-add-to-cart" 
                                        data-id="<?= $produit['id_produit'] ?>"
                                        data-nom="<?= htmlspecialchars($produit['nom']) ?>"
                                        data-prix="<?= $prix_promo ?>"
                                        data-image="<?= htmlspecialchars($image_url) ?>"
                                        <?= $stock <= 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-cart-plus"></i> <?= $stock > 0 ? 'Ajouter au panier' : 'Indisponible' ?>
                                </button>
                                <a href="produit.php?id=<?= $produit['id_produit'] ?>" class="btn-view"><i class="fas fa-eye"></i> Voir</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i> Précédent</a><?php else: ?><button class="pagination-btn" disabled><i class="fas fa-chevron-left"></i> Précédent</button><?php endif; ?>
                <div class="pagination-numbers"><?php
                    if ($page > 3) { echo '<a href="?page=1" class="page-number">1</a>'; if ($page > 4) echo '<span class="page-dots">...</span>'; }
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): ?><a href="?page=<?= $i ?>" class="page-number <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor;
                    if ($page < $total_pages - 2) { if ($page < $total_pages - 3) echo '<span class="page-dots">...</span>'; echo '<a href="?page=' . $total_pages . '" class="page-number">' . $total_pages . '</a>'; }
                ?></div>
                <?php if ($page < $total_pages): ?><a href="?page=<?= $page + 1 ?>" class="pagination-btn">Suivant <i class="fas fa-chevron-right"></i></a><?php else: ?><button class="pagination-btn" disabled>Suivant <i class="fas fa-chevron-right"></i></button><?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center" style="margin-top: 20px;">
                <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Voir tous les produits</a>
            </div>
        </div>
    </section>

    <section class="services">
        <div class="container">
            <h2 class="section-title">Pourquoi choisir HEURE DU CADEAU ?</h2>
            <div class="services-grid">
                <div class="service-card"><div class="service-icon"><i class="fas fa-gift"></i></div><h3>Emballage cadeau offert</h3><p>Chaque cadeau est emballé avec soin dans un papier élégant</p></div>
                <div class="service-card"><div class="service-icon"><i class="fas fa-shipping-fast"></i></div><h3>Livraison rapide</h3><p>Expédition sous 24-48h en France métropolitaine</p></div>
                <div class="service-card"><div class="service-icon"><i class="fas fa-undo-alt"></i></div><h3>Retour facile</h3><p>30 jours pour changer d'avis, retour gratuit</p></div>
                <div class="service-card"><div class="service-icon"><i class="fas fa-headset"></i></div><h3>Service client</h3><p>Une équipe à votre écoute du lundi au vendredi</p></div>
            </div>
        </div>
    </section>

    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">Ce que disent nos clients</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card"><div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div><p class="testimonial-text">"Le cadeau pour l'anniversaire de ma femme était parfait !"</p><div class="testimonial-author"><div class="author-avatar">PD</div><div class="author-info"><h4>Pierre D.</h4><p>Paris</p></div></div></div>
                <div class="testimonial-card"><div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i></div><p class="testimonial-text">"J'ai trouvé exactement ce qu'il me fallait !"</p><div class="testimonial-author"><div class="author-avatar">MS</div><div class="author-info"><h4>Marie S.</h4><p>Lyon</p></div></div></div>
                <div class="testimonial-card"><div class="testimonial-rating"><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i></div><p class="testimonial-text">"La qualité des produits est exceptionnelle."</p><div class="testimonial-author"><div class="author-avatar">TL</div><div class="author-info"><h4>Thomas L.</h4><p>Bordeaux</p></div></div></div>
            </div>
        </div>
    </section>

    <section class="newsletter">
        <div class="container newsletter-container">
            <div class="newsletter-content"><h2>Restez informé</h2><p>Inscrivez-vous à notre newsletter pour recevoir nos nouveautés</p></div>
            <form class="newsletter-form" id="newsletterForm" method="POST" action="newsletter.php" onsubmit="alert('Fonctionnalité à venir !'); return false;">
                <input type="email" name="email" placeholder="Votre adresse email" required>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> S'inscrire</button>
            </form>
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
        const produitsData = <?= json_encode($produits_js ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
        const API_PANIER_URL = "panier.php";

        // Gestionnaire de panier
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

                // Vérifier le stock
                const produitInfo = this.produitsData[id_produit];
                if (produitInfo && produitInfo.quantite_stock <= 0) {
                    this.showNotification("Produit en rupture de stock", "error");
                    return false;
                }

                // Récupérer les infos du produit
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

                // Désactiver le bouton pendant l'ajout
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
                        body: JSON.stringify({ 
                            action: "ajouter", 
                            id_produit: parseInt(id_produit), 
                            quantite: parseInt(quantite) 
                        })
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

        // Gestion de la modal de bienvenue
        function initWelcomeModal() {
            const welcomeModal = document.getElementById("welcomeModal");
            const closeWelcomeModal = document.getElementById("closeWelcomeModal");
            
            if (welcomeModal && closeWelcomeModal) {
                // Vérifier si la modal a déjà été fermée pendant cette session
                const modalClosed = sessionStorage.getItem("welcomeModalClosed");
                
                if (!modalClosed) {
                    setTimeout(() => {
                        welcomeModal.classList.add("show");
                    }, 500);
                }
                
                closeWelcomeModal.addEventListener("click", () => {
                    welcomeModal.classList.remove("show");
                    sessionStorage.setItem("welcomeModalClosed", "true");
                });
                
                welcomeModal.addEventListener("click", (e) => {
                    if (e.target === welcomeModal) {
                        welcomeModal.classList.remove("show");
                        sessionStorage.setItem("welcomeModalClosed", "true");
                    }
                });
            }
        }

        // Initialisation
        document.addEventListener("DOMContentLoaded", function() {
            window.panierManager = new PanierManager();
            initWelcomeModal();
            
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