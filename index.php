<?php
// index.php - Page d'accueil avec gestion panier et pagination
// VERSION CORRIGÉE - Affichage correct des produits avec leurs vraies images
// CORRECTION: Ajout du préfixe /sean/ pour les URLs d'images
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

if ($pdo) {
    try {
        // 1. Compter le nombre total de produits actifs
        $stmt_count = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 'actif'");
        $total_produits = $stmt_count->fetchColumn();
        $total_pages = ceil($total_produits / $produits_par_page);
        
        // 2. Récupérer les produits avec pagination
        $sql = "
            SELECT p.*, 
                   c.nom as categorie_nom,
                   NULL as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.statut = 'actif'
            ORDER BY p.date_creation DESC, p.id_produit DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $produits_par_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $produits = $stmt->fetchAll();
        
        // ==============================================
        // VERSION OPTIMISÉE - Récupération des images en une seule requête
        // ==============================================
        $produits_js = [];
        $images = [];
        
        if (!empty($produits)) {
            // Récupérer tous les IDs des produits
            $ids = array_column($produits, 'id_produit');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            // Récupérer toutes les images principales en une seule requête
            $stmt_imgs = $pdo->prepare("
                SELECT id_produit, url_image 
                FROM images_produits 
                WHERE id_produit IN ($placeholders) AND principale = 1
            ");
            $stmt_imgs->execute($ids);
            
            // Créer un tableau associatif [id_produit => url_image]
            while ($img = $stmt_imgs->fetch()) {
                $images[$img['id_produit']] = $img['url_image'];
            }
        }
        
        // Construire le tableau des produits pour JavaScript
        foreach ($produits as $p) {
            // CORRECTION: Ajouter le préfixe /sean/ devant l'URL de l'image
            // Les images sont stockées dans /var/www/sean/uploads/produits/
            // et accessibles via /sean/uploads/produits/
            if (isset($images[$p['id_produit']])) {
                // Vérifier si l'URL commence déjà par /sean/
                if (strpos($images[$p['id_produit']], '/sean/') === 0) {
                    $image_url = $images[$p['id_produit']];
                } else {
                    $image_url = '/sean' . $images[$p['id_produit']];
                }
            } else {
                $image_url = 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
            }
            
            $produits_js[$p['id_produit']] = [
                'id' => $p['id_produit'],
                'nom' => $p['nom'],
                'reference' => $p['reference'],
                'prix_ttc' => floatval($p['prix_ttc']),
                'description_courte' => $p['description_courte'],
                'image' => $image_url
            ];
        }
        
        error_log("Nombre de produits trouvés : " . count($produits));
        
    } catch (Exception $e) {
        error_log("Erreur récupération produits: " . $e->getMessage());
        $erreur_bdd = true;
        $message_erreur = $e->getMessage();
    }
} else {
    $erreur_bdd = true;
    $message_erreur = "Impossible de se connecter à la base de données";
}

// Récupérer le nombre d'articles dans le panier
$nb_articles = countCartItems();
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HEURE DU CADEAU - Boutique de cadeaux uniques</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* TOUT VOTRE CSS EXISTANT RESTE ICI - IDENTIQUE */
        /* ==============================================
           STYLES GLOBAUX - CHARTE GRAPHIQUE INDEX.HTML
           ============================================== */

        /* Reset et base */
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
           HEADER - STYLE INDEX.HTML AVEC PANIER TOUJOURS VISIBLE
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

        /* Navigation desktop */
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

        /* Lien panier - TOUJOURS VISIBLE */
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

        /* Menu mobile toggle */
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

        /* Navigation mobile */
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
           SECTIONS - STYLE INDEX.HTML
           ============================================== */

        /* Hero Section */
        .hero {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            animation: fadeIn 1s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        .hero-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero-content {
            animation: slideInLeft 1s ease;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
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

        .hero-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(39, 174, 96, 0.3);
            background: linear-gradient(135deg, #219653, #1e8449);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
            background: linear-gradient(135deg, #2980b9, #2573a7);
        }

        .hero-image {
            animation: slideInRight 1s ease;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-image img {
            width: 100%;
            height: auto;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .hero-image img:hover {
            transform: scale(1.02);
        }

        /* Sections communes */
        section {
            padding: 60px 0;
        }

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

        /* Grilles responsives */
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
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-align: center;
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .category-card:hover,
        .service-card:hover,
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
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
        .service-icon i {
            font-size: 2.5rem;
            color: #3498db;
        }

        .category-card h3,
        .service-card h3 {
            font-size: 1.4rem;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .category-card p,
        .service-card p {
            color: #7f8c8d;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .category-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .category-link:hover {
            gap: 10px;
            color: #2980b9;
        }

        /* Testimonials */
        .testimonial-rating {
            margin-bottom: 15px;
        }

        .testimonial-rating i {
            color: #f1c40f;
            margin: 0 2px;
        }

        .testimonial-text {
            font-style: italic;
            color: #555;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
            justify-content: center;
        }

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

        .author-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .author-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Produits */
        .featured-products {
            background: #f8f9fa;
        }

        .featured-products .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
            min-height: 500px;
            opacity: 1;
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
            will-change: transform, opacity;
            backface-visibility: hidden;
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
            background-color: #f8f9fa;
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
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            display: flex;
            align-items: center;
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
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
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

        /* Pagination */
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

        /* Newsletter */
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

        .newsletter-content h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

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
            min-width: 250px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .newsletter-form input:focus {
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .newsletter-form .btn-primary {
            padding: 15px 30px;
            white-space: nowrap;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            color: white;
            padding: 50px 0 30px;
            margin-top: 60px;
        }

        .footer-content {
            text-align: center;
            padding: 20px 0;
        }

        .footer-content p {
            margin-bottom: 10px;
            color: #bdc3c7;
            font-size: 0.9rem;
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
            display: flex;
            align-items: center;
            justify-content: center;
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

        .cart-modal-footer .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
        }

        .cart-modal-footer .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .cart-modal-footer .btn-secondary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }

        .cart-modal-footer .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        /* Toast Notifications */
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

        .notification.warning {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .notification i {
            font-size: 1.5rem;
        }

        /* Loading states */
        .products-loading,
        .products-empty,
        .products-error {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            min-height: 400px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .products-loading i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
        }

        .products-empty i {
            font-size: 3rem;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .products-error i {
            font-size: 3rem;
            color: #e74c3c;
            margin-bottom: 20px;
        }

        .loading-spinner {
            animation: spin 1s linear infinite;
        }

        .text-center {
            text-align: center;
        }

        /* Animation du compteur */
        .cart-count.pulse {
            animation: pulse 0.6s ease;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Info pagination */
        .products-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            color: #7f8c8d;
            font-size: 0.95rem;
        }

        /* Styles supplémentaires pour les produits */
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .debug-info h4 {
            margin-top: 0;
            color: #e74c3c;
        }

        /* ==============================================
           MEDIA QUERIES - RESPONSIVE
           ============================================== */

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

            .hero-container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content {
                order: 2;
            }

            .hero-image {
                order: 1;
            }

            .hero-subtitle {
                max-width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .categories-grid,
            .services-grid,
            .testimonials-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .hero-title {
                font-size: 2.2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .btn {
                padding: 12px 25px;
            }

            section {
                padding: 40px 0;
            }

            .section-title {
                font-size: 1.8rem;
            }

            .categories-grid,
            .services-grid,
            .testimonials-grid,
            .featured-products .products-grid {
                grid-template-columns: 1fr;
            }

            .product-actions {
                flex-direction: column;
            }

            .btn-add-to-cart,
            .btn-view {
                width: 100%;
            }

            .newsletter-container {
                flex-direction: column;
                text-align: center;
            }

            .newsletter-form {
                flex-direction: column;
                width: 100%;
            }

            .newsletter-form input,
            .newsletter-form button {
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
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1.2rem;
            }

            .hero-title {
                font-size: 1.8rem;
            }

            .hero-buttons {
                flex-direction: column;
            }

            .hero-buttons .btn {
                width: 100%;
            }

            .section-title {
                font-size: 1.6rem;
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

                <!-- Navigation desktop -->
                <nav>
                    <a href="index.php" class="active"><i class="fas fa-home"></i> Accueil</a>
                    <a href="catalogue.php"><i class="fas fa-box-open"></i> Cadeaux</a>
                    <a href="apropos.html"><i class="fas fa-info-circle"></i> À propos</a>
                    <a href="contact.html"><i class="fas fa-envelope"></i> Contact</a>
                </nav>

                <!-- Panier toujours visible -->
                <a href="panier.html" class="cart-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Panier</span>
                    <span class="cart-count" id="cartCount"><?= $nb_articles ?></span>
                </a>

                <!-- Menu mobile toggle -->
                <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                    <i class="fas fa-bars"></i>
                </button>
            </div>

            <!-- Navigation mobile -->
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

    <!-- Hero Section -->
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
                <img src="img/hero-banner.jpg" alt="Collection de cadeaux élégants" onerror="this.src='https://via.placeholder.com/600x400?text=Cadeaux+élégants'" />
            </div>
        </div>
    </section>

    <!-- Catégories -->
    <section class="categories" id="categories">
        <div class="container">
            <h2 class="section-title">Nos catégories de cadeaux</h2>
            <p class="section-subtitle">Trouvez le cadeau parfait selon l'occasion</p>

            <div class="categories-grid">
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-birthday-cake"></i></div>
                    <h3>Anniversaires</h3>
                    <p>Cadeaux uniques pour célébrer les anniversaires</p>
                    <a href="catalogue.php?categorie=1" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-heart"></i></div>
                    <h3>Saint-Valentin</h3>
                    <p>Romantique et mémorable</p>
                    <a href="catalogue.php?categorie=2" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-glass-cheers"></i></div>
                    <h3>Mariage</h3>
                    <p>Cadeaux de mariage élégants</p>
                    <a href="catalogue.php?categorie=3" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-baby"></i></div>
                    <h3>Naissance</h3>
                    <p>Pour accueillir bébé</p>
                    <a href="catalogue.php?categorie=4" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>Diplômés</h3>
                    <p>Pour célébrer la réussite</p>
                    <a href="catalogue.php?categorie=5" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-christmas-tree"></i></div>
                    <h3>Noël</h3>
                    <p>Magie des fêtes de fin d'année</p>
                    <a href="catalogue.php?categorie=6" class="category-link">Voir les produits →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Tous les produits avec pagination -->
    <section class="featured-products">
        <div class="container">
            <h2 class="section-title">Tous nos produits</h2>
            <p class="section-subtitle"><?= $total_produits ?> produits disponibles</p>

            <!-- Informations pagination -->
            <div class="products-info">
                <span>Page <?= $page ?> sur <?= $total_pages ?></span>
                <span><?= count($produits) ?> produits affichés</span>
            </div>

            <div class="products-grid" id="featuredProducts">
                <?php if ($erreur_bdd): ?>
                    <div class="products-error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h3>Erreur de chargement</h3>
                        <p><?= htmlspecialchars($message_erreur) ?></p>
                        <button onclick="window.location.reload()" class="btn btn-primary" style="margin-top: 20px;">
                            <i class="fas fa-sync-alt"></i> Réessayer
                        </button>
                    </div>
                <?php elseif (empty($produits)): ?>
                    <div class="products-empty">
                        <i class="fas fa-box-open"></i>
                        <h3>Aucun produit disponible</h3>
                        <p>La boutique est actuellement vide. Revenez bientôt !</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($produits as $produit): 
                        $prix = number_format($produit['prix_ttc'] ?? 0, 2, ',', ' ');
                        
                        // CORRECTION: Ajouter le préfixe /sean/ devant l'URL de l'image
                        if (isset($images[$produit['id_produit']])) {
                            // Vérifier si l'URL commence déjà par /sean/
                            if (strpos($images[$produit['id_produit']], '/sean/') === 0) {
                                $image_url = $images[$produit['id_produit']];
                            } else {
                                $image_url = '/sean' . $images[$produit['id_produit']];
                            }
                        } else {
                            $image_url = 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit';
                        }
                        
                        // Référence courte
                        $ref_short = substr($produit['reference'] ?? 'REF' . $produit['id_produit'], 0, 8);
                    ?>
                    <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                        <?php if (($produit['id_produit'] ?? 0) == 4): ?>
                        <span class="discount-badge">-20%</span>
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
                            <span class="product-category"><?= htmlspecialchars($produit['categorie_nom'] ?? 'Cadeau') ?></span>
                            <h3><?= htmlspecialchars($produit['nom'] ?? 'Produit sans nom') ?></h3>
                            <p class="product-price"><?= $prix ?> €</p>
                            <div class="product-rating">
                                <?php for($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                                <span class="rating-count">(<?= rand(5, 50) ?>)</span>
                            </div>
                            <div class="product-actions">
                                <button class="btn-add-to-cart" data-id="<?= $produit['id_produit'] ?>" title="Ajouter au panier">
                                    <i class="fas fa-cart-plus"></i> Ajouter
                                </button>
                                <a href="catalogue.php?id=<?= $produit['id_produit'] ?>" class="btn-view">
                                    <i class="fas fa-eye"></i> Voir
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>
                        <i class="fas fa-chevron-left"></i> Précédent
                    </button>
                <?php endif; ?>

                <div class="pagination-numbers">
                    <?php
                    $start = max(1, min($page - 2, $total_pages - 4));
                    $end = min($total_pages, max(5, $page + 2));
                    
                    if ($start > 1) {
                        echo '<a href="?page=1" class="page-number">1</a>';
                        if ($start > 2) echo '<span class="page-dots">...</span>';
                    }
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?= $i ?>" class="page-number <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($end < $total_pages): ?>
                        <?php if ($end < $total_pages - 1) echo '<span class="page-dots">...</span>'; ?>
                        <a href="?page=<?= $total_pages ?>" class="page-number"><?= $total_pages ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pagination-btn">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <button class="pagination-btn" disabled>
                        Suivant <i class="fas fa-chevron-right"></i>
                    </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="text-center" style="margin-top: 20px;">
                <a href="catalogue.php" class="btn btn-primary"><i class="fas fa-arrow-right"></i> Voir tous les produits</a>
            </div>
        </div>
    </section>

    <!-- Services -->
    <section class="services">
        <div class="container">
            <h2 class="section-title">Pourquoi choisir HEURE DU CADEAU ?</h2>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-gift"></i></div>
                    <h3>Emballage cadeau offert</h3>
                    <p>Chaque cadeau est emballé avec soin dans un papier élégant</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-shipping-fast"></i></div>
                    <h3>Livraison rapide</h3>
                    <p>Expédition sous 24-48h en France métropolitaine</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-undo-alt"></i></div>
                    <h3>Retour facile</h3>
                    <p>30 jours pour changer d'avis, retour gratuit</p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-headset"></i></div>
                    <h3>Service client</h3>
                    <p>Une équipe à votre écoute du lundi au vendredi</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Témoignages -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">Ce que disent nos clients</h2>
            <div class="testimonials-grid">
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"Le cadeau pour l'anniversaire de ma femme était parfait !"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">PD</div>
                        <div class="author-info">
                            <h4>Pierre D.</h4>
                            <p>Paris</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="testimonial-text">"J'ai trouvé exactement ce qu'il me fallait !"</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">MS</div>
                        <div class="author-info">
                            <h4>Marie S.</h4>
                            <p>Lyon</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-rating">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="testimonial-text">"La qualité des produits est exceptionnelle."</p>
                    <div class="testimonial-author">
                        <div class="author-avatar">TL</div>
                        <div class="author-info">
                            <h4>Thomas L.</h4>
                            <p>Bordeaux</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Newsletter -->
    <section class="newsletter">
        <div class="container newsletter-container">
            <div class="newsletter-content">
                <h2>Restez informé</h2>
                <p>Inscrivez-vous à notre newsletter pour recevoir nos nouveautés</p>
            </div>
            <form class="newsletter-form" id="newsletterForm" method="POST" action="newsletter.php" onsubmit="alert('Fonctionnalité à venir !'); return false;">
                <input type="email" name="email" placeholder="Votre adresse email" required />
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> S'inscrire</button>
            </form>
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
                <a href="panier.html" class="btn btn-primary">Voir le panier</a>
                <button class="btn btn-secondary" id="continueShopping">Continuer mes achats</button>
            </div>
        </div>
    </div>

    <script>
        // ==============================================
        // DONNÉES DES PRODUITS - EXTRAITES DE LA BDD
        // ==============================================
        const produitsData = <?= json_encode($produits_js, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;

        // ==============================================
        // GESTIONNAIRE DE PANIER - VERSION CORRIGÉE
        // ==============================================

        const API_PANIER_URL = "panier.php";

        // Gestion du menu mobile
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
        });

        // Classe PanierManager
        class PanierManager {
            constructor() {
                this.apiUrl = API_PANIER_URL;
                this.cartModal = document.getElementById("cartModal");
                this.cartModalBody = document.getElementById("cartModalBody");
                this.cartCountElements = document.querySelectorAll(".cart-count");
                this.updateInProgress = false;
                this.produitsData = produitsData; // Stocker les données des produits
                this.initEvents();
                this.updateCartCount();
            }

            initEvents() {
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
            }

            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                if (!id_produit || id_produit <= 0) {
                    this.showNotification("Erreur: Produit invalide", "error");
                    return false;
                }

                // Récupérer les infos du produit depuis les données PHP
                const produitInfo = this.produitsData[id_produit];
                
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

                        // Utiliser les données du produit récupérées depuis la BDD
                        this.showCartModal({
                            id: produitInfo.id,
                            nom: produitInfo.nom,
                            reference: produitInfo.reference,
                            prix_ttc: produitInfo.prix_ttc,
                            image: produitInfo.image
                        });
                        
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
                const icon = type === "success" ? "check-circle" : type === "error" ? "exclamation-triangle" : "info-circle";
                notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
                document.body.appendChild(notification);

                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 3000);
            }
        }

        // Initialisation
        document.addEventListener("DOMContentLoaded", async () => {
            window.panierManager = new PanierManager();

            // Délégation d'événement pour les boutons d'ajout au panier
            document.addEventListener("click", async (e) => {
                const addToCartBtn = e.target.closest(".btn-add-to-cart");
                if (addToCartBtn && !addToCartBtn.disabled) {
                    e.preventDefault();
                    e.stopPropagation();

                    const id_produit = addToCartBtn.dataset.id ? parseInt(addToCartBtn.dataset.id) : null;

                    if (id_produit) {
                        await window.panierManager.ajouterAuPanier(id_produit, 1, addToCartBtn);
                    }
                }
            });
        });

        window.ajouterAuPanier = function(id_produit, quantite = 1, button = null) {
            if (!window.panierManager) {
                window.panierManager = new PanierManager();
            }
            return window.panierManager.ajouterAuPanier(id_produit, quantite, button);
        };
    </script>
</body>
</html>