<?php
// catalogue.php - Version corrigée avec accès au panier fonctionnel
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

if ($pdo) {
    try {
        // Si une catégorie spécifique est demandée
        if (!$is_all_categories) {
            // 1. Récupérer les informations de la catégorie
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
            // 2. Compter le nombre total de produits actifs
            if ($is_all_categories) {
                $stmt_count = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 'actif'");
            } else {
                $stmt_count = $pdo->prepare("
                    SELECT COUNT(*) FROM produits 
                    WHERE statut = 'actif' AND id_categorie = ?
                ");
                $stmt_count->execute([$categorie_id]);
            }
            $total_produits = $stmt_count->fetchColumn();
            $total_pages = ceil($total_produits / $produits_par_page);
            
            // CORRECTION CRITIQUE: S'assurer que la page demandée n'est pas supérieure au total
            if ($total_pages > 0 && $page > $total_pages) {
                $page = $total_pages;
                $offset = ($page - 1) * $produits_par_page;
            }
            
            // 3. Récupérer les produits avec pagination
            if ($is_all_categories) {
                $sql = "
                    SELECT p.*, 
                           c.nom as categorie_nom,
                           c.id_categorie as categorie_id
                    FROM produits p
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                    WHERE p.statut = 'actif'
                    ORDER BY p.date_creation DESC, p.id_produit DESC
                    LIMIT ? OFFSET ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$produits_par_page, $offset]);
            } else {
                $sql = "
                    SELECT p.*, 
                           c.nom as categorie_nom,
                           c.id_categorie as categorie_id
                    FROM produits p
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                    WHERE p.statut = 'actif' AND p.id_categorie = ?
                    ORDER BY p.date_creation DESC, p.id_produit DESC
                    LIMIT ? OFFSET ?
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$categorie_id, $produits_par_page, $offset]);
            }
            
            $produits = $stmt->fetchAll();
            
            // ==============================================
            // RÉCUPÉRATION DES IMAGES
            // ==============================================
            $produits_js = [];
            $images = [];
            
            if (!empty($produits)) {
                $ids = array_column($produits, 'id_produit');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                
                $stmt_imgs = $pdo->prepare("
                    SELECT id_produit, url_image, principale 
                    FROM images_produits 
                    WHERE id_produit IN ($placeholders)
                    ORDER BY principale DESC, ordre ASC, id_image ASC
                ");
                $stmt_imgs->execute($ids);
                
                while ($img = $stmt_imgs->fetch()) {
                    $clean_url = $img['url_image'];
                    if (strpos($clean_url, '/sean/') !== false) {
                        $clean_url = preg_replace('#/sean/+#', '/', $clean_url);
                    }
                    
                    if (!isset($images[$img['id_produit']])) {
                        $images[$img['id_produit']] = $clean_url;
                    }
                }
            }
            
            // Construire le tableau des produits pour JavaScript
            foreach ($produits as $p) {
                // Récupérer l'image
                if (isset($images[$p['id_produit']]) && !empty($images[$p['id_produit']])) {
                    $image_url = $images[$p['id_produit']];
                } else {
                    // Images par défaut selon l'ID ou générique
                    $default_images = [
                        1 => 'https://via.placeholder.com/300x300/2c3e50/ffffff?text=Bougie',
                        2 => 'https://via.placeholder.com/300x300/27ae60/ffffff?text=Coffret',
                        3 => 'https://via.placeholder.com/300x300/3498db/ffffff?text=Montre',
                        4 => 'https://via.placeholder.com/300x300/e74c3c/ffffff?text=Bijoux'
                    ];
                    $image_url = $default_images[$p['id_produit']] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                }
                
                $produits_js[$p['id_produit']] = [
                    'id' => (int)$p['id_produit'],
                    'nom' => $p['nom'],
                    'reference' => $p['reference'],
                    'prix_ttc' => floatval($p['prix_ttc']),
                    'description_courte' => $p['description_courte'],
                    'quantite_stock' => (int)$p['quantite_stock'],
                    'image' => $image_url
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

// ==============================================
// RÉCUPÉRATION DU PANIER DEPUIS LA BDD
// ==============================================
$panier_data = getCartItemsFromBDD($pdo);
$panier_items = $panier_data['panier'] ?? [];
$panier_count = $panier_data['total_items'] ?? 0;
$panier_total = $panier_data['sous_total'] ?? 0;

// Fallback sur la session si la BDD ne retourne rien (pour compatibilité)
if ($panier_count === 0 && isset($_SESSION[SESSION_KEY_PANIER]) && !empty($_SESSION[SESSION_KEY_PANIER])) {
    $panier_count = countCartItemsSession();
    // Note: on ne peut pas afficher les détails du panier depuis la session ici,
    // mais au moins le compteur sera correct
}

// Titre de la page
if ($is_all_categories) {
    $page_title = "Tous nos cadeaux";
} else {
    $page_title = $categorie ? htmlspecialchars($categorie['nom']) : "Catégorie introuvable";
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $page_title ?> - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* ==============================================
           STYLES GLOBAUX - CHARTE GRAPHIQUE
           ============================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* ==============================================
           HEADER - STYLE AVEC PANIER TOUJOURS VISIBLE
           ============================================== */

        header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .logo {
            color: white;
            text-decoration: none;
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .logo i {
            color: #e74c3c;
        }

        nav {
            display: flex;
            gap: 25px;
            align-items: center;
            flex: 1;
            justify-content: center;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        nav a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        nav a.active {
            background-color: rgba(255, 255, 255, 0.15);
        }

        .cart-wrapper {
            position: relative;
        }

        .cart-link {
            position: relative;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.15);
            padding: 8px 15px;
            border-radius: 30px;
            margin-left: 10px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cart-link:hover {
            background: #e74c3c;
            transform: translateY(-2px);
        }

        .cart-count {
            background-color: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 5px;
        }

        .cart-link:hover .cart-count {
            background-color: white;
            color: #e74c3c;
        }

        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s ease;
            order: 3;
        }

        .menu-toggle:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-mobile {
            display: none;
            background: #34495e;
            padding: 20px;
            border-radius: 0 0 12px 12px;
            margin-top: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideDown 0.3s ease;
            width: 100%;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .nav-mobile.show {
            display: block;
        }

        .nav-mobile-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .nav-mobile-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 15px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            font-size: 1.1rem;
        }

        .nav-mobile-link i {
            width: 25px;
            color: #e74c3c;
        }

        .nav-mobile-link:hover {
            background: #e74c3c;
            transform: translateX(5px);
        }

        .nav-mobile-link:hover i {
            color: white;
        }

        /* ==============================================
           MINI PANIER DÉROULANT AMÉLIORÉ
           ============================================== */

        .cart-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 380px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            margin-top: 10px;
            z-index: 1001;
            display: none;
            animation: slideDown 0.3s ease;
            max-height: 500px;
            overflow-y: auto;
        }

        .cart-dropdown.show {
            display: block;
        }

        .cart-dropdown::before {
            content: '';
            position: absolute;
            top: -8px;
            right: 30px;
            width: 16px;
            height: 16px;
            background: white;
            transform: rotate(45deg);
            box-shadow: -2px -2px 5px rgba(0, 0, 0, 0.04);
        }

        .cart-dropdown-header {
            padding: 15px 20px;
            border-bottom: 2px solid #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-dropdown-header span {
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
        }

        .cart-dropdown-items {
            max-height: 300px;
            overflow-y: auto;
        }

        .cart-dropdown-item {
            display: flex;
            gap: 15px;
            padding: 15px 20px;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
            position: relative;
        }

        .cart-dropdown-item:hover {
            background-color: #f8f9fa;
        }

        .cart-dropdown-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .cart-dropdown-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cart-dropdown-item-info {
            flex: 1;
        }

        .cart-dropdown-item-info h4 {
            font-size: 0.95rem;
            color: #2c3e50;
            margin-bottom: 5px;
            font-weight: 600;
            padding-right: 25px;
        }

        .cart-dropdown-item-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .cart-dropdown-item-price {
            color: #e74c3c;
            font-weight: 700;
        }

        .cart-dropdown-item-quantity {
            color: #7f8c8d;
        }

        .cart-dropdown-item-remove {
            position: absolute;
            top: 15px;
            right: 15px;
            color: #95a5a6;
            cursor: pointer;
            transition: color 0.2s ease;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .cart-dropdown-item-remove:hover {
            color: #e74c3c;
            background-color: rgba(231, 76, 60, 0.1);
        }

        .cart-dropdown-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            border-radius: 0 0 12px 12px;
        }

        .cart-dropdown-total {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .cart-dropdown-total span:last-child {
            color: #e74c3c;
            font-size: 1.2rem;
        }

        .cart-dropdown-buttons {
            display: flex;
            gap: 10px;
        }

        .cart-dropdown-buttons .btn {
            flex: 1;
            padding: 12px;
            font-size: 0.95rem;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            font-weight: 600;
        }

        .cart-dropdown-buttons .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
        }

        .cart-dropdown-buttons .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(39, 174, 96, 0.3);
        }

        .cart-dropdown-buttons .btn-secondary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .cart-dropdown-buttons .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }

        .cart-dropdown-empty {
            padding: 40px 20px;
            text-align: center;
            color: #7f8c8d;
        }

        .cart-dropdown-empty i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 10px;
        }

        /* ==============================================
           STYLES SPÉCIFIQUES AU CATALOGUE
           ============================================== */

        .catalogue-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 40px 0;
            margin-bottom: 40px;
            border-bottom: 1px solid #dee2e6;
            animation: fadeIn 1s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .catalogue-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .catalogue-header p {
            color: #7f8c8d;
            font-size: 1.1rem;
            max-width: 800px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: #7f8c8d;
        }

        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #e74c3c;
            text-decoration: underline;
        }

        .breadcrumb i {
            margin: 0 10px;
            font-size: 0.8rem;
            color: #95a5a6;
        }

        .products-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: #7f8c8d;
            font-size: 0.95rem;
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            animation: fadeInProduct 0.5s ease forwards;
            opacity: 0;
        }

        @keyframes fadeInProduct {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .product-card:nth-child(1) { animation-delay: 0.1s; }
        .product-card:nth-child(2) { animation-delay: 0.15s; }
        .product-card:nth-child(3) { animation-delay: 0.2s; }
        .product-card:nth-child(4) { animation-delay: 0.25s; }
        .product-card:nth-child(5) { animation-delay: 0.3s; }
        .product-card:nth-child(6) { animation-delay: 0.35s; }
        .product-card:nth-child(7) { animation-delay: 0.4s; }
        .product-card:nth-child(8) { animation-delay: 0.45s; }
        .product-card:nth-child(9) { animation-delay: 0.5s; }
        .product-card:nth-child(10) { animation-delay: 0.55s; }
        .product-card:nth-child(11) { animation-delay: 0.6s; }
        .product-card:nth-child(12) { animation-delay: 0.65s; }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(52, 152, 219, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .product-card:hover .product-overlay {
            opacity: 1;
        }

        .product-overlay i {
            color: white;
            font-size: 2rem;
            background: rgba(255, 255, 255, 0.2);
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
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1;
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
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

        .stock-faible {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }

        .stock-rupture {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            display: inline-block;
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: #2c3e50;
            line-height: 1.4;
            height: 50px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-weight: 600;
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: #e74c3c;
            margin: 10px 0;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 10px 0;
        }

        .product-rating i {
            color: #f1c40f;
            font-size: 0.9rem;
        }

        .rating-count {
            color: #7f8c8d;
            font-size: 0.85rem;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

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
            transition: all 0.3s ease;
            font-size: 0.95rem;
            box-shadow: 0 4px 10px rgba(39, 174, 96, 0.2);
        }

        .btn-add-to-cart:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(39, 174, 96, 0.3);
            background: linear-gradient(135deg, #219653, #1e8449);
        }

        .btn-add-to-cart:disabled {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-add-to-cart.loading {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }

        .btn-add-to-cart.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.2);
        }

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
            background: linear-gradient(135deg, #2980b9, #2573a7);
        }

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
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pagination-btn:hover:not(:disabled) {
            background: #3498db;
            color: white;
            border-color: #3498db;
            transform: translateY(-2px);
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-numbers {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

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
            transition: all 0.3s ease;
        }

        .page-number:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
            transform: translateY(-2px);
        }

        .page-number.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
            font-weight: 600;
        }

        .page-dots {
            display: flex;
            align-items: center;
            padding: 0 5px;
            color: #7f8c8d;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            animation: fadeIn 0.8s ease;
        }

        .empty-state i {
            font-size: 4rem;
            color: #95a5a6;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .empty-state .btn {
            display: inline-block;
            padding: 12px 30px;
            font-size: 1rem;
        }

        footer {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            padding: 50px 0 30px;
            margin-top: 60px;
        }

        .footer-content {
            text-align: center;
        }

        .footer-content p {
            margin-bottom: 10px;
            color: #bdc3c7;
            font-size: 0.9rem;
        }

        .footer-content i {
            margin: 0 5px;
            font-size: 1.5rem;
            color: #bdc3c7;
            transition: color 0.3s ease;
        }

        .footer-content i:hover {
            color: #e74c3c;
        }

        /* ==============================================
           STYLES MODAL ET NOTIFICATIONS
           ============================================== */

        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .cart-modal.show {
            display: flex;
        }

        .cart-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: modalSlideIn 0.4s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            border-radius: 16px 16px 0 0;
            z-index: 1;
        }

        .cart-modal-header h3 {
            margin: 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .cart-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .cart-modal-close:hover {
            background: #f8f9fa;
            color: #e74c3c;
            transform: rotate(90deg);
        }

        .cart-modal-body {
            padding: 20px;
        }

        .cart-modal-product {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
        }

        .modal-product-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        .modal-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-product-info {
            flex: 1;
        }

        .modal-product-info h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-product-ref {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin: 5px 0;
        }

        .modal-product-price {
            font-weight: 700;
            color: #e74c3c;
            font-size: 1.2rem;
            margin: 10px 0;
        }

        .modal-success-message {
            color: #27ae60;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .cart-modal-footer {
            padding: 20px;
            background: #f8f9fa;
            border-top: 2px solid #e9ecef;
            display: flex;
            gap: 12px;
            position: sticky;
            bottom: 0;
        }

        .cart-modal-footer .btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            padding: 18px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            display: flex;
            align-items: center;
            gap: 15px;
            animation: slideInRight 0.5s ease, fadeOut 0.5s ease 2.5s forwards;
            min-width: 300px;
            max-width: 400px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }

        .notification.error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .notification i {
            font-size: 1.5rem;
        }

        .cart-count.pulse {
            animation: pulse 0.6s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        @media (max-width: 992px) {
            .header-content {
                flex-wrap: wrap;
            }

            nav {
                display: none;
            }

            .menu-toggle {
                display: block;
            }

            .cart-link {
                margin-left: auto;
                margin-right: 15px;
            }

            .catalogue-header h1 {
                font-size: 2.2rem;
            }
            
            .cart-dropdown {
                width: 350px;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 0;
            }

            .logo {
                font-size: 1.5rem;
            }

            .cart-link span:not(.cart-count) {
                display: none;
            }

            .cart-link {
                padding: 8px 12px;
            }

            .catalogue-header {
                padding: 30px 0;
            }

            .catalogue-header h1 {
                font-size: 2rem;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }

            .product-actions {
                flex-direction: column;
            }

            .btn-add-to-cart,
            .btn-view {
                width: 100%;
            }

            .notification {
                min-width: 280px;
                max-width: 280px;
                right: 20px;
                left: 20px;
                margin: 0 auto;
            }

            .pagination {
                gap: 5px;
            }

            .pagination-btn {
                padding: 8px 12px;
            }
            
            .cart-dropdown {
                width: 300px;
                right: 10px;
            }
        }

        @media (max-width: 576px) {
            .products-grid {
                grid-template-columns: 1fr;
            }

            .catalogue-header h1 {
                font-size: 1.8rem;
            }

            .pagination-numbers {
                order: -1;
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }

            .page-number {
                min-width: 35px;
                height: 35px;
            }

            .cart-modal-product {
                flex-direction: column;
                text-align: center;
            }

            .modal-product-image {
                width: 100%;
                height: 150px;
            }

            .cart-modal-footer {
                flex-direction: column;
            }
            
            .cart-dropdown {
                width: 280px;
                right: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-gift"></i> HEURE DU CADEAU
                </a>

                <nav>
                    <a href="index.php"><i class="fas fa-home"></i> Accueil</a>
                    <a href="catalogue.php" class="active"><i class="fas fa-box-open"></i> Cadeaux</a>
                    <a href="apropos.html"><i class="fas fa-info-circle"></i> À propos</a>
                    <a href="contact.html"><i class="fas fa-envelope"></i> Contact</a>
                </nav>

                <!-- Panier avec dropdown -->
                <div class="cart-wrapper">
                    <a href="panier.html" class="cart-link" id="cartToggle">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Panier</span>
                        <span class="cart-count" id="cartCount"><?= $panier_count ?></span>
                    </a>
                    
                    <!-- Mini panier déroulant -->
                    <div class="cart-dropdown" id="cartDropdown">
                        <div class="cart-dropdown-header">
                            Mon panier
                            <span id="cartDropdownCount"><?= $panier_count ?> article(s)</span>
                        </div>
                        <div class="cart-dropdown-items" id="cartDropdownItems">
                            <?php if (empty($panier_items)): ?>
                                <div class="cart-dropdown-empty">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p>Votre panier est vide</p>
                                </div>
                            <?php else: 
                                $total_panier = 0;
                                foreach ($panier_items as $item): 
                                    $nom = $item['nom'] ?? 'Produit #' . $item['id_produit'];
                                    $prix = floatval($item['prix_unitaire'] ?? 0);
                                    $quantite = intval($item['quantite'] ?? 1);
                                    $sous_total = $prix * $quantite;
                                    $total_panier += $sous_total;
                                    
                                    // Image
                                    $image = $item['image'] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                            ?>
                                <div class="cart-dropdown-item" data-id="<?= $item['id_produit'] ?>" data-item-id="<?= $item['id_item'] ?? '' ?>">
                                    <div class="cart-dropdown-item-image">
                                        <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($nom) ?>" 
                                             onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                                    </div>
                                    <div class="cart-dropdown-item-info">
                                        <h4><?= htmlspecialchars(mb_substr($nom, 0, 30)) ?><?= mb_strlen($nom) > 30 ? '...' : '' ?></h4>
                                        <div class="cart-dropdown-item-details">
                                            <span class="cart-dropdown-item-price"><?= number_format($prix, 2, ',', ' ') ?> €</span>
                                            <span class="cart-dropdown-item-quantity">x<?= $quantite ?></span>
                                        </div>
                                    </div>
                                    <div class="cart-dropdown-item-remove" onclick="panierManager.supprimerDuPanier(<?= $item['id_produit'] ?>, <?= $item['id_item'] ?? 0 ?>, event)">
                                        <i class="fas fa-times"></i>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="cart-dropdown-footer">
                            <div class="cart-dropdown-total">
                                <span>Total TTC</span>
                                <span id="cartDropdownTotal"><?= number_format($total_panier ?? 0, 2, ',', ' ') ?> €</span>
                            </div>
                            <div class="cart-dropdown-buttons">
                                <a href="panier.html" class="btn btn-primary">
                                    <i class="fas fa-shopping-cart"></i> Voir le panier
                                </a>
                                <?php if (!empty($panier_items)): ?>
                                <a href="livraison_form.php" class="btn btn-secondary">
                                    <i class="fas fa-credit-card"></i> Commander
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                    <i class="fas fa-bars"></i>
                </button>
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

    <!-- En-tête du catalogue -->
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
        <!-- Informations pagination -->
        <div class="products-info">
            <span>Page <?= $page ?> sur <?= $total_pages ?></span>
            <span><?= count($produits) ?> produits sur <?= $total_produits ?></span>
        </div>

        <!-- Grille des produits -->
        <div class="products-grid" id="featuredProducts">
            <?php if ($erreur_bdd): ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Erreur de chargement</h3>
                    <p><?= htmlspecialchars($message_erreur) ?></p>
                    <button onclick="window.location.reload()" class="btn-add-to-cart" style="display: inline-block;">
                        <i class="fas fa-sync-alt"></i> Réessayer
                    </button>
                </div>
            <?php elseif (empty($produits)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Aucun produit <?= !$is_all_categories ? 'dans cette catégorie' : '' ?></h3>
                    <p>Découvrez nos autres catégories de cadeaux</p>
                    <a href="catalogue.php" class="btn-add-to-cart" style="display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Voir toutes les catégories
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($produits as $produit): 
                    $prix = number_format($produit['prix_ttc'] ?? 0, 2, ',', ' ');
                    $image_url = $images[$produit['id_produit']] ?? 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                    $stock = $produit['quantite_stock'] ?? 0;
                    $en_stock = $stock > 0;
                    $stock_faible = $stock > 0 && $stock <= 5;
                ?>
                <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                    <?php if ($produit['id_produit'] == 4): ?>
                        <span class="discount-badge">-20%</span>
                    <?php endif; ?>
                    
                    <?php if (!$en_stock): ?>
                        <span class="stock-badge stock-rupture">Rupture</span>
                    <?php elseif ($stock_faible): ?>
                        <span class="stock-badge stock-faible">Plus que <?= $stock ?></span>
                    <?php endif; ?>
                    
                    <div class="product-image">
                        <img src="<?= $image_url ?>" 
                             alt="<?= htmlspecialchars($produit['nom'] ?? 'Produit') ?>" 
                             loading="lazy"
                             onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        <div class="product-overlay">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <span class="product-category">
                            <?= htmlspecialchars($produit['categorie_nom'] ?? ($is_all_categories ? 'Cadeau' : $categorie['nom'])) ?>
                        </span>
                        <h3><?= htmlspecialchars($produit['nom'] ?? 'Produit sans nom') ?></h3>
                        <p class="product-price"><?= $prix ?> €</p>
                        <div class="product-rating">
                            <?php 
                            $note = $produit['note_moyenne'] ?? 4.5;
                            $note_entier = floor($note);
                            for($i = 1; $i <= 5; $i++): 
                                if ($i <= $note_entier):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i == $note_entier + 1 && $note - $note_entier >= 0.5): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; endfor; ?>
                            <span class="rating-count">(<?= $produit['nombre_avis'] ?? rand(5, 50) ?>)</span>
                        </div>
                        <div class="product-actions">
                            <button class="btn-add-to-cart" data-id="<?= $produit['id_produit'] ?>" 
                                    <?= !$en_stock ? 'disabled' : '' ?>
                                    data-nom="<?= htmlspecialchars($produit['nom']) ?>"
                                    data-prix="<?= $produit['prix_ttc'] ?>"
                                    data-image="<?= $image_url ?>">
                                <i class="fas fa-cart-plus"></i> 
                                <?= $en_stock ? 'Ajouter' : 'Rupture' ?>
                            </button>
                            <a href="produit.php?id=<?= $produit['id_produit'] ?>" class="btn-view">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1 && !empty($produits)): ?>
        <div class="pagination">
            <?php 
            $base_url = $is_all_categories ? 'catalogue.php' : 'catalogue.php?categorie=' . $categorie_id;
            ?>
            
            <?php if ($page > 1): ?>
                <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="pagination-btn">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
            <?php else: ?>
                <button class="pagination-btn" disabled>
                    <i class="fas fa-chevron-left"></i> Précédent
                </button>
            <?php endif; ?>

            <div class="pagination-numbers">
                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                
                // Toujours montrer la première page
                if ($start > 1) {
                    echo '<a href="' . $base_url . '&page=1" class="page-number">1</a>';
                    if ($start > 2) echo '<span class="page-dots">...</span>';
                }
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="<?= $base_url ?>&page=<?= $i ?>" 
                       class="page-number <?= $i == $page ? 'active' : '' ?>">
                       <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end < $total_pages): ?>
                    <?php if ($end < $total_pages - 1) echo '<span class="page-dots">...</span>'; ?>
                    <a href="<?= $base_url ?>&page=<?= $total_pages ?>" 
                       class="page-number"><?= $total_pages ?></a>
                <?php endif; ?>
            </div>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="pagination-btn">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="pagination-btn" disabled>
                    Suivant <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center" style="margin-top: 20px; margin-bottom: 40px;">
            <a href="index.php" class="btn-add-to-cart" style="display: inline-block; padding: 12px 30px;">
                <i class="fas fa-home"></i> Retour à l'accueil
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <p>&copy; 2025 HEURE DU CADEAU - Tous droits réservés</p>
                <p>Votre boutique de cadeaux élégants en ligne</p>
                <p style="margin-top: 15px">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-paypal"></i>
                </p>
            </div>
        </div>
    </footer>

    <!-- Modal Panier -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h3>Article ajouté au panier</h3>
                <button class="cart-modal-close" id="closeCartModal">&times;</button>
            </div>
            <div class="cart-modal-body" id="cartModalBody"></div>
            <div class="cart-modal-footer">
                <a href="panier.html" class="btn-add-to-cart">Voir le panier</a>
                <button class="btn-view" id="continueShopping">Continuer mes achats</button>
            </div>
        </div>
    </div>

    <script>
        // ==============================================
        // DONNÉES DES PRODUITS
        // ==============================================
        const produitsData = <?= json_encode($produits_js ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;

        // ==============================================
        // GESTIONNAIRE DE PANIER AMÉLIORÉ
        // ==============================================
        const API_PANIER_URL = "panier.php";

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
            }

            initEvents() {
                // Fermeture modale
                document.getElementById("closeCartModal")?.addEventListener("click", () => {
                    this.cartModal.classList.remove("show");
                });

                document.getElementById("continueShopping")?.addEventListener("click", () => {
                    this.cartModal.classList.remove("show");
                });

                this.cartModal?.addEventListener("click", (e) => {
                    if (e.target === this.cartModal) {
                        this.cartModal.classList.remove("show");
                    }
                });

                // Toggle du dropdown panier
                const cartToggle = document.getElementById("cartToggle");
                if (cartToggle && this.cartDropdown) {
                    cartToggle.addEventListener("click", (e) => {
                        e.preventDefault();
                        // Ne pas empêcher la redirection vers panier.html
                        // Juste gérer l'affichage du dropdown
                        this.cartDropdown.classList.toggle("show");
                    });

                    document.addEventListener("click", (e) => {
                        if (!this.cartDropdown.contains(e.target) && !cartToggle.contains(e.target)) {
                            this.cartDropdown.classList.remove("show");
                        }
                    });
                }

                // Délégation d'événement pour les boutons d'ajout au panier
                document.addEventListener("click", async (e) => {
                    const addToCartBtn = e.target.closest(".btn-add-to-cart");
                    if (addToCartBtn && !addToCartBtn.disabled && !addToCartBtn.closest('.pagination') && !addToCartBtn.closest('.empty-state') && !addToCartBtn.closest('.cart-modal-footer')) {
                        e.preventDefault();
                        e.stopPropagation();

                        const id_produit = addToCartBtn.dataset.id ? parseInt(addToCartBtn.dataset.id) : null;

                        if (id_produit) {
                            await this.ajouterAuPanier(id_produit, 1, addToCartBtn);
                        }
                    }
                });
            }

            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                if (!id_produit || id_produit <= 0) {
                    this.showNotification("Erreur: Produit invalide", "error");
                    return false;
                }

                // Chercher d'abord dans produitsData (données PHP)
                let produitInfo = this.produitsData[id_produit];
                
                // Si pas trouvé, essayer de récupérer depuis les attributs data du bouton
                if (!produitInfo && button) {
                    produitInfo = {
                        id: id_produit,
                        nom: button.dataset.nom || 'Produit',
                        reference: 'REF' + id_produit,
                        prix_ttc: parseFloat(button.dataset.prix) || 0,
                        image: button.dataset.image || 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'
                    };
                }

                if (!produitInfo) {
                    this.showNotification("Erreur: Produit non trouvé", "error");
                    return false;
                }

                let originalHTML = "";
                let originalDisabled = false;

                if (button) {
                    originalHTML = button.innerHTML;
                    originalDisabled = button.disabled;
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
                    button.classList.add("loading");
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
                        await this.refreshCartDropdown();
                        this.showCartModal(produitInfo);
                        this.showNotification(`"${produitInfo.nom}" ajouté au panier !`);
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
                        }, 1000);
                    }
                }
            }

            async supprimerDuPanier(id_produit, id_item, event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({
                            action: "supprimer",
                            id_produit: parseInt(id_produit),
                            id_item: parseInt(id_item)
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        await this.updateCartCount();
                        await this.refreshCartDropdown();
                        this.showNotification("Article retiré du panier");
                    } else {
                        this.showNotification(data.message || "Erreur de suppression", "error");
                    }
                } catch (error) {
                    console.error("Erreur suppression:", error);
                    this.showNotification("Erreur de connexion", "error");
                }
            }

            async refreshCartDropdown() {
                try {
                    const response = await fetch(`${this.apiUrl}?action=get&_=${Date.now()}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        // Mettre à jour le compteur dans l'en-tête du dropdown
                        if (this.cartDropdownCount) {
                            this.cartDropdownCount.textContent = data.total_items + ' article(s)';
                        }
                        
                        // Mettre à jour le total
                        if (this.cartDropdownTotal) {
                            const total = (data.sous_total || 0).toFixed(2).replace('.', ',');
                            this.cartDropdownTotal.textContent = total + ' €';
                        }
                    }
                } catch (error) {
                    console.error("Erreur refresh dropdown:", error);
                }
            }

            showCartModal(product) {
                if (!product || !this.cartModalBody) return;

                const prix = product.prix_ttc ? parseFloat(product.prix_ttc).toFixed(2).replace(".", ",") : "0,00";

                this.cartModalBody.innerHTML = `
                    <div class="cart-modal-product">
                        <div class="modal-product-image">
                            <img src="${product.image}" 
                                 alt="${product.nom}"
                                 onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        </div>
                        <div class="modal-product-info">
                            <h4>${product.nom}</h4>
                            <p class="modal-product-ref">Réf: ${product.reference || 'REF' + product.id}</p>
                            <p class="modal-product-price">${prix} €</p>
                            <p class="modal-success-message">
                                <i class="fas fa-check-circle"></i>
                                Article ajouté avec succès !
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
                            const count = data.total || 0;
                            this.updateCartCountDisplay(count);
                            return count;
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
                document.querySelectorAll(".notification").forEach((toast) => {
                    toast.remove();
                });

                const notification = document.createElement("div");
                notification.className = `notification ${type}`;
                const icon = type === "success" ? "check-circle" : "exclamation-triangle";
                notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
                document.body.appendChild(notification);

                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 3000);
            }
        }

        // ==============================================
        // GESTION DU MENU MOBILE
        // ==============================================
        document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.getElementById("menuToggle");
            const navMobile = document.getElementById("navMobile");

            if (menuToggle && navMobile) {
                menuToggle.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopPropagation();
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

            // Initialisation du gestionnaire de panier
            window.panierManager = new PanierManager();
        });
    </script>
</body>
</html>