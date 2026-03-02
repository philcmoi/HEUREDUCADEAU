<?php
// index.php - Page d'accueil avec gestion panier
require_once 'session_verification.php';

// Récupérer les produits phares depuis la BDD
$pdo = getPDOConnection();
$produits_phares = [];

if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT p.*, 
                   c.nom as categorie_nom,
                   (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.statut = 'actif'
            ORDER BY p.ventes DESC, p.note_moyenne DESC
            LIMIT 4
        ");
        $produits_phares = $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Erreur récupération produits: " . $e->getMessage());
    }
}

// Fallback si aucun produit
if (empty($produits_phares)) {
    $produits_phares = [
        ['id_produit' => 1, 'nom' => 'Bougie parfumée "Élégance"', 'prix_ttc' => 29.08, 'categorie_nom' => 'Cadeau', 'image' => 'img/default-product.jpg'],
        ['id_produit' => 2, 'nom' => 'Coffret gourmand "Délice"', 'prix_ttc' => 41.58, 'categorie_nom' => 'Cadeau', 'image' => 'img/default-product.jpg'],
        ['id_produit' => 3, 'nom' => 'Montre "Temps Précieux"', 'prix_ttc' => 74.92, 'categorie_nom' => 'Cadeau', 'image' => 'img/default-product.jpg'],
        ['id_produit' => 4, 'nom' => 'Set bijoux "Lumière"', 'prix_ttc' => 1000.00, 'categorie_nom' => 'Cadeau', 'image' => 'img/default-product.jpg']
    ];
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
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <style>
        /* ==============================================
           STYLES GLOBAUX ET RESPONSIVE - VERSION BLANC PUR
           ============================================== */

        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Inter", sans-serif;
            background-color: #ffffff;
            color: #1e1e1e;
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            width: 100%;
        }

        /* ==============================================
           HEADER RESPONSIVE - VERSION BLANC
           ============================================== */

        .header {
            background: #ffffff;
            color: #1e1e1e;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.03);
            width: 100%;
            border-bottom: 1px solid #f0f0f0;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        /* Logo responsive */
        .logo {
            color: #1e1e1e;
            text-decoration: none;
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .logo-icon {
            color: #c7a97c;
            font-size: 2rem;
        }

        .logo-text {
            font-family: "Playfair Display", serif;
            letter-spacing: -0.5px;
        }

        .logo-highlight {
            color: #c7a97c;
            font-weight: 600;
        }

        /* Navigation desktop */
        .nav-main {
            flex: 1;
            display: flex;
            justify-content: center;
        }

        .nav-list {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-link {
            color: #1e1e1e;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 15px;
            border-radius: 30px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }

        .nav-link i {
            font-size: 1.1rem;
            color: #c7a97c;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: #f8f8f8;
            color: #1e1e1e;
            transform: translateY(-2px);
        }

        .nav-link:hover i {
            color: #1e1e1e;
        }

        /* Lien panier - TOUJOURS VISIBLE */
        .cart-link {
            position: relative;
            background: #f8f8f8;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
            color: #1e1e1e;
            border: 1px solid #f0f0f0;
        }

        .cart-link:hover {
            background: #1e1e1e;
            color: #ffffff !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border-color: #1e1e1e;
        }

        .cart-link:hover .cart-count {
            background: #ffffff;
            color: #1e1e1e;
        }

        .cart-link:hover i {
            color: #ffffff;
        }

        .cart-count {
            background: #c7a97c;
            color: #ffffff;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            transition: all 0.3s ease;
            margin-left: 5px;
        }

        /* Menu mobile toggle */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            color: #1e1e1e;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: #f8f8f8;
            transform: scale(1.1);
        }

        /* Navigation mobile - cachée par défaut */
        .nav-mobile {
            display: none;
            background: #ffffff;
            padding: 20px;
            border-radius: 0 0 20px 20px;
            margin-top: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.02);
            animation: slideDown 0.3s ease;
            border: 1px solid #f0f0f0;
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
            gap: 15px;
        }

        .nav-mobile-link {
            color: #1e1e1e;
            text-decoration: none;
            font-weight: 500;
            padding: 15px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            background: #f8f8f8;
            font-size: 1.1rem;
        }

        .nav-mobile-link i {
            width: 25px;
            font-size: 1.2rem;
            color: #c7a97c;
        }

        .nav-mobile-link:hover {
            background: #1e1e1e;
            color: #ffffff;
            transform: translateX(5px);
        }

        .nav-mobile-link:hover i {
            color: #ffffff;
        }

        /* ==============================================
           SECTIONS RESPONSIVES - VERSION BLANC
           ============================================== */

        /* Hero Section */
        .hero {
            padding: 80px 0;
            background: #ffffff;
            border-bottom: 1px solid #f5f5f5;
        }

        .hero-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            align-items: center;
        }

        .hero-content {
            animation: fadeInLeft 1s ease;
        }

        @keyframes fadeInLeft {
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
            font-family: "Playfair Display", serif;
            font-size: 3.2rem;
            font-weight: 600;
            color: #1e1e1e;
            line-height: 1.2;
            margin-bottom: 25px;
            letter-spacing: -0.5px;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #6c6c6c;
            margin-bottom: 35px;
            max-width: 90%;
            font-weight: 300;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 35px;
            border-radius: 40px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            font-size: 1rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #1e1e1e;
            color: #ffffff;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .btn-primary:hover {
            background: #c7a97c;
            color: #ffffff;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(199, 169, 124, 0.15);
        }

        .btn-secondary {
            background: #ffffff;
            color: #1e1e1e;
            border: 1px solid #e0e0e0;
        }

        .btn-secondary:hover {
            background: #f8f8f8;
            transform: translateY(-3px);
            border-color: #1e1e1e;
        }

        .hero-image {
            animation: fadeInRight 1s ease;
        }

        @keyframes fadeInRight {
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
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.02);
            border: 1px solid #f0f0f0;
        }

        /* Sections communes */
        section {
            padding: 80px 0;
        }

        .section-title {
            font-family: "Playfair Display", serif;
            font-size: 2.5rem;
            color: #1e1e1e;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .section-subtitle {
            text-align: center;
            color: #6c6c6c;
            font-size: 1.1rem;
            margin-bottom: 50px;
            font-weight: 300;
        }

        /* Grilles responsives */
        .categories-grid,
        .services-grid,
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .category-card,
        .service-card,
        .testimonial-card {
            background: #ffffff;
            padding: 40px 30px;
            border-radius: 24px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
            text-align: center;
            border: 1px solid #f0f0f0;
        }

        .category-card:hover,
        .service-card:hover,
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.03);
            border-color: #c7a97c;
        }

        .category-icon,
        .service-icon {
            width: 80px;
            height: 80px;
            background: #f8f8f8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .category-icon i,
        .service-icon i {
            font-size: 2.5rem;
            color: #c7a97c;
        }

        .category-card h3,
        .service-card h3 {
            font-size: 1.5rem;
            color: #1e1e1e;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .category-card p,
        .service-card p {
            color: #6c6c6c;
            margin-bottom: 20px;
            line-height: 1.6;
            font-weight: 300;
        }

        .category-link {
            color: #1e1e1e;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
            border-bottom: 1px solid transparent;
        }

        .category-link:hover {
            gap: 10px;
            color: #c7a97c;
            border-bottom-color: #c7a97c;
        }

        /* Testimonials */
        .testimonial-rating {
            margin-bottom: 15px;
        }

        .testimonial-rating i {
            color: #c7a97c;
            margin: 0 2px;
        }

        .testimonial-text {
            font-style: italic;
            color: #1e1e1e;
            margin-bottom: 20px;
            line-height: 1.8;
            font-weight: 300;
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
            background: #f8f8f8;
            color: #1e1e1e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.2rem;
            border: 1px solid #e0e0e0;
        }

        .author-info h4 {
            color: #1e1e1e;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .author-info p {
            color: #6c6c6c;
            font-size: 0.9rem;
            font-weight: 300;
        }

        /* Produits phares */
        .featured-products {
            background: #fafafa;
        }

        .featured-products .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }

        .product-card {
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid #f0f0f0;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.03);
            border-color: #c7a97c;
        }

        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background: #fafafa;
            display: flex;
            align-items: center;
            justify-content: center;
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
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(2px);
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
            color: #1e1e1e;
            font-size: 2rem;
            background: #ffffff;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: #1e1e1e;
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            z-index: 1;
        }

        .stock-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
            z-index: 1;
        }

        .stock-faible {
            background: #c7a97c;
            color: #ffffff;
        }

        .stock-rupture {
            background: #e0e0e0;
            color: #1e1e1e;
        }

        .product-info {
            padding: 20px;
        }

        .product-category {
            display: inline-block;
            color: #6c6c6c;
            font-size: 0.85rem;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 400;
        }

        .product-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.2rem;
            color: #1e1e1e;
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
            font-weight: 600;
            color: #1e1e1e;
            margin: 10px 0;
            display: flex;
            align-items: center;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 10px 0;
        }

        .product-rating i {
            color: #c7a97c;
            font-size: 0.9rem;
        }

        .rating-count {
            color: #6c6c6c;
            font-size: 0.85rem;
        }

        .product-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-add-to-cart {
            flex: 1;
            background: #1e1e1e;
            color: #ffffff;
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .btn-add-to-cart:hover:not(:disabled) {
            background: #c7a97c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(199, 169, 124, 0.15);
        }

        .btn-add-to-cart:disabled {
            background: #f0f0f0;
            color: #6c6c6c;
            cursor: not-allowed;
            transform: none;
        }

        .btn-add-to-cart.loading {
            background: #e0e0e0;
            color: #1e1e1e;
            position: relative;
        }

        .btn-add-to-cart.loading i {
            animation: spin 1s linear infinite;
            color: #c7a97c;
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
            background: #f8f8f8;
            color: #1e1e1e;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .btn-view:hover {
            background: #1e1e1e;
            color: #ffffff;
            transform: translateY(-2px);
            border-color: #1e1e1e;
        }

        .btn-view:hover i {
            color: #ffffff;
        }

        /* Newsletter */
        .newsletter {
            background: #fafafa;
            color: #1e1e1e;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .newsletter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 50px;
            flex-wrap: wrap;
        }

        .newsletter-content h2 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
            letter-spacing: -0.5px;
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
            border: 1px solid #e0e0e0;
            border-radius: 40px;
            font-size: 1rem;
            outline: none;
            min-width: 250px;
            background: #ffffff;
            font-family: "Inter", sans-serif;
        }

        .newsletter-form input:focus {
            border-color: #c7a97c;
            outline: none;
        }

        .newsletter-form .btn-primary {
            padding: 15px 30px;
            white-space: nowrap;
            background: #1e1e1e;
        }

        .newsletter-form .btn-primary:hover {
            background: #c7a97c;
        }

        /* Footer */
        .footer {
            background: #ffffff;
            color: #1e1e1e;
            padding: 80px 0 30px;
            border-top: 1px solid #f0f0f0;
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 50px;
        }

        .footer-col h3 {
            font-size: 1.3rem;
            margin-bottom: 25px;
            color: #1e1e1e;
            font-weight: 600;
            letter-spacing: -0.3px;
        }

        .footer-col p {
            color: #6c6c6c;
            line-height: 1.8;
            margin-bottom: 20px;
            font-weight: 300;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: #f8f8f8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e1e1e;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
        }

        .social-links a:hover {
            background: #c7a97c;
            color: #ffffff;
            transform: translateY(-3px);
            border-color: #c7a97c;
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #6c6c6c;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 300;
        }

        .footer-links a:hover {
            color: #c7a97c;
            transform: translateX(5px);
        }

        .footer-contact li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #6c6c6c;
            margin-bottom: 15px;
            font-weight: 300;
        }

        .footer-contact i {
            color: #c7a97c;
            width: 20px;
        }

        .footer-bottom {
            border-top: 1px solid #f0f0f0;
            padding-top: 30px;
            text-align: center;
            color: #6c6c6c;
        }

        .footer-bottom p {
            margin-bottom: 10px;
            font-weight: 300;
        }

        .footer-bottom i {
            font-size: 1.5rem;
            margin: 0 5px;
            color: #6c6c6c;
        }

        /* ==============================================
           STYLES MODAL ET NOTIFICATIONS - VERSION BLANC
           ============================================== */

        .cart-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(5px);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .cart-modal.show {
            display: flex;
        }

        .cart-modal-content {
            background: #ffffff;
            border-radius: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            border: 1px solid #f0f0f0;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .cart-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f0f0;
            position: sticky;
            top: 0;
            background: #ffffff;
            border-radius: 24px 24px 0 0;
            z-index: 1;
        }

        .cart-modal-header h3 {
            margin: 0;
            color: #1e1e1e;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .cart-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6c6c6c;
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
            background: #f8f8f8;
            color: #1e1e1e;
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
            border-radius: 16px;
            overflow: hidden;
            flex-shrink: 0;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.02);
            background: #f8f8f8;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #f0f0f0;
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
            color: #1e1e1e;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .modal-product-ref {
            color: #6c6c6c;
            font-size: 0.85rem;
            margin: 5px 0;
            font-weight: 300;
        }

        .modal-product-price {
            font-weight: 600;
            color: #1e1e1e;
            font-size: 1.2rem;
            margin: 10px 0;
        }

        .modal-success-message {
            color: #c7a97c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }

        .cart-modal-footer {
            padding: 20px;
            background: #fafafa;
            border-top: 1px solid #f0f0f0;
            display: flex;
            gap: 12px;
            position: sticky;
            bottom: 0;
        }

        .cart-modal-footer .btn {
            flex: 1;
            padding: 12px;
            font-weight: 500;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cart-modal-footer .btn-primary {
            background: #1e1e1e;
            color: #ffffff;
        }

        .cart-modal-footer .btn-primary:hover {
            background: #c7a97c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(199, 169, 124, 0.15);
        }

        .cart-modal-footer .btn-secondary {
            background: #f8f8f8;
            color: #1e1e1e;
            border: 1px solid #e0e0e0;
        }

        .cart-modal-footer .btn-secondary:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ffffff;
            border-radius: 16px;
            padding: 15px 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2100;
            animation: toastSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 400px;
            border-left: 4px solid #c7a97c;
            border: 1px solid #f0f0f0;
        }

        .toast-error {
            border-left-color: #e0e0e0;
        }

        .toast-warning {
            border-left-color: #f0f0f0;
        }

        @keyframes toastSlideIn {
            from {
                opacity: 0;
                transform: translateX(100%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0) translateY(0);
            }
        }

        .toast-icon {
            font-size: 1.8rem;
        }

        .toast-success .toast-icon {
            color: #c7a97c;
        }

        .toast-error .toast-icon {
            color: #e0e0e0;
        }

        .toast-warning .toast-icon {
            color: #f0f0f0;
        }

        .toast-message {
            flex: 1;
            color: #1e1e1e;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .toast-close {
            background: none;
            border: none;
            color: #b0b0b0;
            cursor: pointer;
            padding: 5px;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .toast-close:hover {
            color: #1e1e1e;
        }

        /* Loading states */
        .products-loading,
        .products-empty,
        .products-api-error,
        .products-network-error {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid #f0f0f0;
        }

        .products-loading i,
        .products-empty i,
        .products-api-error i,
        .products-network-error i {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .products-loading i {
            color: #c7a97c;
        }

        .products-empty i {
            color: #b0b0b0;
        }

        .products-api-error i,
        .products-network-error i {
            color: #e0e0e0;
        }

        .text-center {
            text-align: center;
        }

        /* Animation du compteur */
        .cart-count.pulse {
            animation: pulse-animation 0.6s ease-in-out;
        }

        @keyframes pulse-animation {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.3);
            }
            100% {
                transform: scale(1);
            }
        }

        /* ==============================================
           MEDIA QUERIES - RESPONSIVE DESIGN
           ============================================== */

        /* Grands écrans (desktop) */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 2.8rem;
            }

            .container {
                padding: 0 30px;
            }
        }

        /* Écrans moyens (tablettes) */
        @media (max-width: 992px) {
            .header-container {
                flex-wrap: wrap;
            }

            .nav-main {
                display: none;
            }

            .menu-toggle {
                display: block;
            }

            .cart-link {
                margin-left: auto;
                margin-right: 15px;
                padding: 8px 15px;
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

            .section-title {
                font-size: 2rem;
            }

            .categories-grid,
            .services-grid,
            .testimonials-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .newsletter-container {
                flex-direction: column;
                text-align: center;
            }

            .newsletter-form {
                max-width: 100%;
                width: 100%;
            }
        }

        /* Petits écrans (mobiles) */
        @media (max-width: 768px) {
            .header {
                padding: 10px 0;
            }

            .logo {
                font-size: 1.4rem;
            }

            .logo-icon {
                font-size: 1.6rem;
            }

            .cart-link span:not(.cart-count) {
                display: none;
            }

            .cart-link {
                padding: 8px 12px;
                margin-right: 5px;
            }

            .cart-link i {
                font-size: 1.2rem;
            }

            .cart-count {
                width: 20px;
                height: 20px;
                font-size: 0.7rem;
                position: static;
                margin-left: 0;
            }

            .hero {
                padding: 50px 0;
            }

            .hero-title {
                font-size: 2rem;
            }

            .hero-subtitle {
                font-size: 1rem;
            }

            .btn {
                padding: 12px 25px;
                font-size: 0.9rem;
            }

            section {
                padding: 50px 0;
            }

            .categories-grid,
            .services-grid,
            .testimonials-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .featured-products .products-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .product-image {
                height: 200px;
            }

            .product-actions {
                flex-direction: column;
            }

            .btn-add-to-cart,
            .btn-view {
                width: 100%;
            }

            .newsletter-form {
                flex-direction: column;
            }

            .newsletter-form input,
            .newsletter-form button {
                width: 100%;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 30px;
            }

            .footer-col {
                text-align: center;
            }

            .social-links {
                justify-content: center;
            }

            .footer-links a {
                justify-content: center;
            }

            .footer-contact li {
                justify-content: center;
            }
        }

        /* Très petits écrans */
        @media (max-width: 480px) {
            .container {
                padding: 0 15px;
            }

            .logo {
                font-size: 1.2rem;
            }

            .logo-icon {
                font-size: 1.4rem;
            }

            .cart-link {
                padding: 6px 10px;
            }

            .hero-title {
                font-size: 1.8rem;
            }

            .hero-buttons {
                flex-direction: column;
                gap: 10px;
            }

            .hero-buttons .btn {
                width: 100%;
                justify-content: center;
            }

            .section-title {
                font-size: 1.6rem;
            }

            .modal-product {
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
        }
    </style>
</head>
<body>
    <!-- Header avec panier toujours visible -->
    <header class="header">
        <div class="container header-container">
            <!-- Logo -->
            <a href="index.php" class="logo">
                <i class="fas fa-gift logo-icon"></i>
                <span class="logo-text">HEURE<span class="logo-highlight"> DU CADEAU</span></span>
            </a>

            <!-- Navigation principale (desktop) -->
            <nav class="nav-main">
                <ul class="nav-list">
                    <li><a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="catalogue.php" class="nav-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                    <li><a href="apropos.html" class="nav-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                    <li><a href="contact.html" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </nav>

            <!-- Panier toujours visible (desktop et mobile) -->
            <a href="panier.html" class="nav-link cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Panier</span>
                <span class="cart-count" id="cartCount"><?= $nb_articles ?></span>
            </a>

            <!-- Menu mobile toggle -->
            <button class="menu-toggle" id="menuToggle" aria-label="Menu">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Navigation mobile (cachée par défaut) -->
        <nav class="nav-mobile" id="navMobile">
            <ul class="nav-mobile-list">
                <li><a href="index.php" class="nav-mobile-link active"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="catalogue.php" class="nav-mobile-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                <li><a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a></li>
            </ul>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container hero-container">
            <div class="hero-content">
                <h1 class="hero-title">Des cadeaux qui marquent les esprits</h1>
                <p class="hero-subtitle">Découvrez notre sélection exclusive de cadeaux originaux pour toutes les occasions</p>
                <div class="hero-buttons">
                    <a href="catalogue.php" class="btn btn-primary">Explorer la collection</a>
                    <a href="#categories" class="btn btn-secondary">Voir les catégories</a>
                </div>
            </div>
            <div class="hero-image">
                <img src="img/hero-banner.jpg" alt="Collection de cadeaux élégants" />
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
                    <a href="catalogue.php?categorie=2" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-heart"></i></div>
                    <h3>Saint-Valentin</h3>
                    <p>Romantique et mémorable</p>
                    <a href="catalogue.php?categorie=3" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-glass-cheers"></i></div>
                    <h3>Mariage</h3>
                    <p>Cadeaux de mariage élégants</p>
                    <a href="catalogue.php?categorie=4" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-baby"></i></div>
                    <h3>Naissance</h3>
                    <p>Pour accueillir bébé</p>
                    <a href="catalogue.php?categorie=5" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>Diplômés</h3>
                    <p>Pour célébrer la réussite</p>
                    <a href="catalogue.php?categorie=6" class="category-link">Voir les produits →</a>
                </div>
                <div class="category-card">
                    <div class="category-icon"><i class="fas fa-christmas-tree"></i></div>
                    <h3>Noël</h3>
                    <p>Magie des fêtes de fin d'année</p>
                    <a href="catalogue.php?categorie=7" class="category-link">Voir les produits →</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Produits phares -->
    <section class="featured-products">
        <div class="container">
            <h2 class="section-title">Nos meilleures ventes</h2>
            <p class="section-subtitle">Découvrez les cadeaux les plus appréciés</p>

            <div class="products-grid" id="featuredProducts">
                <?php foreach ($produits_phares as $produit): 
                    $prix = number_format($produit['prix_ttc'], 2, ',', ' ');
                    $image = !empty($produit['image']) ? htmlspecialchars($produit['image']) : 'img/default-product.jpg';
                ?>
                <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                    <div class="product-image">
                        <img src="<?= $image ?>" alt="<?= htmlspecialchars($produit['nom']) ?>" onerror="this.src='img/default-product.jpg'">
                        <div class="product-overlay">
                            <i class="fas fa-eye"></i>
                        </div>
                    </div>
                    <div class="product-info">
                        <span class="product-category"><?= htmlspecialchars($produit['categorie_nom'] ?? 'Cadeau') ?></span>
                        <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                        <p class="product-price"><?= $prix ?> €</p>
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
            </div>

            <div class="text-center">
                <a href="catalogue.php" class="btn btn-primary">Voir tous les produits</a>
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
                    <p class="testimonial-text">"Le cadeau pour l'anniversaire de ma femme était parfait ! L'emballage était magnifique et la livraison ultra-rapide."</p>
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
                    <p class="testimonial-text">"J'ai trouvé exactement ce qu'il me fallait pour le mariage de mes amis. Service client très réactif !"</p>
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
                    <p class="testimonial-text">"La qualité des produits est exceptionnelle. Je recommande vivement pour tous vos cadeaux !"</p>
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
                <p>Inscrivez-vous à notre newsletter pour recevoir nos nouveautés et offres spéciales</p>
            </div>
            <form class="newsletter-form" id="newsletterForm" method="POST" action="newsletter.php">
                <input type="email" name="email" placeholder="Votre adresse email" required />
                <button type="submit" class="btn btn-primary">S'inscrire</button>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container footer-container">
            <div class="footer-col">
                <h3>HEURE DU CADEAU</h3>
                <p>Votre boutique de cadeaux en ligne pour toutes les occasions. Qualité, élégance et originalité.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-pinterest-p"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                </div>
            </div>
            <div class="footer-col">
                <h3>Liens rapides</h3>
                <ul class="footer-links">
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="catalogue.php">Tous les cadeaux</a></li>
                    <li><a href="apropos.html">À propos</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="panier.html">Mon panier</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Informations</h3>
                <ul class="footer-links">
                    <li><a href="#">Livraison</a></li>
                    <li><a href="#">Retours</a></li>
                    <li><a href="#">Conditions générales</a></li>
                    <li><a href="#">Politique de confidentialité</a></li>
                    <li><a href="#">Mentions légales</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Contact</h3>
                <ul class="footer-contact">
                    <li><i class="fas fa-map-marker-alt"></i> 123 Rue des Cadeaux, 75000 Paris</li>
                    <li><i class="fas fa-phone"></i> 01 23 45 67 89</li>
                    <li><i class="fas fa-envelope"></i> contact@heureducadeau.fr</li>
                    <li><i class="fas fa-clock"></i> Lun-Ven: 9h-18h</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; 2025 HEURE DU CADEAU. Tous droits réservés.</p>
                <p>Paiements sécurisés: <i class="fab fa-cc-visa"></i> <i class="fab fa-cc-mastercard"></i> <i class="fab fa-cc-paypal"></i></p>
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
        // GESTIONNAIRE DE PANIER - VERSION BLANC
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
                    
                    // Animation de l'icône
                    const icon = menuToggle.querySelector("i");
                    if (navMobile.classList.contains("show")) {
                        icon.classList.remove("fa-bars");
                        icon.classList.add("fa-times");
                    } else {
                        icon.classList.remove("fa-times");
                        icon.classList.add("fa-bars");
                    }
                });

                // Fermer le menu en cliquant en dehors
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
                this.initEvents();
                this.updateCartCount();
                console.log("PanierManager initialisé avec URL:", this.apiUrl);
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

                // Gérer les clics sur les boutons d'ajout au panier
                document.addEventListener("click", async (e) => {
                    const addToCartBtn = e.target.closest(".btn-add-to-cart");
                    if (addToCartBtn && !addToCartBtn.disabled) {
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

                // Sauvegarder l'état du bouton
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

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();
                    console.log("Réponse API:", data);

                    if (data.success) {
                        await this.updateCartCount();

                        // Récupérer les infos du produit depuis la réponse
                        const productInfo = {
                            nom: data.produit_nom || `Produit #${id_produit}`,
                            prix_ttc: data.produit?.prix || 19.99,
                            image: "img/default-product.jpg",
                            reference: `REF${id_produit}`,
                            id_produit: id_produit
                        };

                        this.showCartModal(productInfo);
                        this.showNotification(`"${productInfo.nom}" ajouté au panier !`);

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
                                 onerror="this.src='img/default-product.jpg'">
                        </div>
                        <div class="modal-product-info">
                            <h4>${product.nom}</h4>
                            <p class="modal-product-ref">Réf: ${product.reference || product.id_produit}</p>
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
                try {
                    const response = await fetch(`${this.apiUrl}?action=compter&_=${Date.now()}`);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const data = await response.json();

                    if (data.success) {
                        const count = data.total || 0;
                        this.updateCartCountDisplay(count);
                        return count;
                    } else {
                        this.updateCartCountDisplay(0);
                        return 0;
                    }
                } catch (error) {
                    console.error("Erreur mise à jour compteur:", error);
                    this.updateCartCountDisplay(0);
                    return 0;
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
                document.querySelectorAll(".toast-notification").forEach((toast) => {
                    toast.remove();
                });

                const toast = document.createElement("div");
                toast.className = `toast-notification toast-${type}`;
                toast.innerHTML = `
                    <div class="toast-icon"><i class="fas fa-${type === "success" ? "check-circle" : "exclamation-triangle"}"></i></div>
                    <div class="toast-message">${message}</div>
                    <button class="toast-close"><i class="fas fa-times"></i></button>
                `;
                document.body.appendChild(toast);

                toast.querySelector(".toast-close").addEventListener("click", () => toast.remove());

                setTimeout(() => {
                    if (toast.parentElement) toast.remove();
                }, 5000);
            }
        }

        // Initialisation
        document.addEventListener("DOMContentLoaded", () => {
            window.panierManager = new PanierManager();
        });
    </script>
</body>
</html>