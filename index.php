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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <style>
        /* ==============================================
           STYLES GLOBAUX ET RESPONSIVE - VERSION FINALE
           ============================================== */

        /* Reset et base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: #333;
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
           HEADER RESPONSIVE - PANIER TOUJOURS VISIBLE
           ============================================== */

        .header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            width: 100%;
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        /* Logo responsive */
        .logo {
            color: white;
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
            color: #e74c3c;
            font-size: 2rem;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
        }

        .logo-highlight {
            color: #e74c3c;
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
            color: white;
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
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
            transform: translateY(-2px);
        }

        /* Lien panier - TOUJOURS VISIBLE */
        .cart-link {
            position: relative;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }

        .cart-link:hover {
            background: #e74c3c;
            color: white !important;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .cart-link:hover .cart-count {
            background: white;
            color: #e74c3c;
        }

        .cart-count {
            background: #e74c3c;
            color: white;
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
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        /* Navigation mobile - cachée par défaut */
        .nav-mobile {
            display: none;
            background: #34495e;
            padding: 20px;
            border-radius: 0 0 20px 20px;
            margin-top: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            animation: slideDown 0.3s ease;
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
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 15px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            font-size: 1.1rem;
        }

        .nav-mobile-link i {
            width: 25px;
            font-size: 1.2rem;
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
           SECTIONS RESPONSIVES
           ============================================== */

        /* Hero Section */
        .hero {
            padding: 80px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
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
            font-family: 'Playfair Display', serif;
            font-size: 3.2rem;
            font-weight: 700;
            color: #2c3e50;
            line-height: 1.2;
            margin-bottom: 25px;
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 35px;
            max-width: 90%;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 35px;
            border-radius: 50px;
            font-weight: 600;
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
            background: #e74c3c;
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            background: #c0392b;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: white;
            color: #2c3e50;
            border: 2px solid #e74c3c;
        }

        .btn-secondary:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-3px);
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
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        /* Sections communes */
        section {
            padding: 80px 0;
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 15px;
        }

        .section-subtitle {
            text-align: center;
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 50px;
        }

        /* Grilles responsives */
        .categories-grid,
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .category-card,
        .service-card {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            text-align: center;
        }

        .category-card:hover,
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .category-icon,
        .service-icon {
            width: 80px;
            height: 80px;
            background: rgba(231, 76, 60, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .category-icon i,
        .service-icon i {
            font-size: 2.5rem;
            color: #e74c3c;
        }

        .category-card h3,
        .service-card h3 {
            font-size: 1.5rem;
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
            color: #e74c3c;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .category-link:hover {
            gap: 10px;
        }

        /* Produits phares */
        .featured-products .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }

        .product-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        .product-image {
            position: relative;
            height: 250px;
            overflow: hidden;
            background-color: #f8f9fa;
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
            background: rgba(0, 0, 0, 0.4);
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
            background: rgba(231, 76, 60, 0.9);
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
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            z-index: 1;
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
        }

        .product-price {
            font-size: 1.4rem;
            font-weight: 700;
            color: #e74c3c;
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
            background: #e74c3c;
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
        }

        .btn-add-to-cart:hover:not(:disabled) {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .btn-add-to-cart:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-add-to-cart.loading {
            background: #3498db;
        }

        .btn-add-to-cart.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .btn-view {
            background: #3498db;
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
        }

        .btn-view:hover {
            background: #2980b9;
        }

        /* Newsletter */
        .newsletter {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            margin-top: 40px;
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
            border-radius: 50px;
            font-size: 1rem;
            outline: none;
            min-width: 250px;
        }

        .newsletter-form .btn-primary {
            padding: 15px 30px;
            white-space: nowrap;
        }

        /* Footer */
        .footer {
            background: #1a252f;
            color: white;
            padding: 80px 0 30px;
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
            color: #e74c3c;
        }

        .footer-col p {
            color: #bdc3c7;
            line-height: 1.8;
            margin-bottom: 20px;
        }

        .social-links {
            display: flex;
            gap: 15px;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: #e74c3c;
            transform: translateY(-3px);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-links a:hover {
            color: #e74c3c;
            transform: translateX(5px);
        }

        .footer-contact li {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #bdc3c7;
            margin-bottom: 15px;
        }

        .footer-contact i {
            color: #e74c3c;
            width: 20px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            text-align: center;
            color: #bdc3c7;
        }

        .footer-bottom p {
            margin-bottom: 10px;
        }

        .footer-bottom i {
            font-size: 1.5rem;
            margin: 0 5px;
            color: white;
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
            animation: modalSlideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            background: white;
            border-radius: 16px 16px 0 0;
            z-index: 1;
        }

        .cart-modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }

        .cart-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
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
            background: #f5f5f5;
            color: #333;
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
            background-color: #f8f9fa;
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
            border-top: 1px solid #eee;
            display: flex;
            gap: 12px;
            position: sticky;
            bottom: 0;
        }

        .cart-modal-footer .btn {
            flex: 1;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .cart-modal-footer .btn-primary {
            background: #e74c3c;
            color: white;
        }

        .cart-modal-footer .btn-primary:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        .cart-modal-footer .btn-secondary {
            background: #ecf0f1;
            color: #2c3e50;
            border: 1px solid #ddd;
        }

        .cart-modal-footer .btn-secondary:hover {
            background: #d5dbdb;
            transform: translateY(-2px);
        }

        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2100;
            animation: toastSlideIn 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            max-width: 400px;
            border-left: 4px solid #27ae60;
        }

        .toast-error {
            border-left-color: #e74c3c;
        }

        .toast-warning {
            border-left-color: #f39c12;
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
            color: #27ae60;
        }

        .toast-error .toast-icon {
            color: #e74c3c;
        }

        .toast-warning .toast-icon {
            color: #f39c12;
        }

        .toast-message {
            flex: 1;
            color: #2c3e50;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .toast-close {
            background: none;
            border: none;
            color: #bdc3c7;
            cursor: pointer;
            padding: 5px;
            font-size: 1.1rem;
            transition: color 0.3s ease;
        }

        .toast-close:hover {
            color: #7f8c8d;
        }

        /* Animation du compteur */
        .cart-count.pulse {
            animation: pulse-animation 0.6s ease-in-out;
        }

        @keyframes pulse-animation {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .text-center {
            text-align: center;
        }

        /* Loading states */
        .products-loading {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        .products-loading i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 20px;
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
            .services-grid {
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
            .services-grid {
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
                <li>
                    <a href="index.php" class="nav-mobile-link active"><i class="fas fa-home"></i> Accueil</a>
                </li>
                <li>
                    <a href="catalogue.php" class="nav-mobile-link"><i class="fas fa-box-open"></i> Cadeaux</a>
                </li>
                <li>
                    <a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a>
                </li>
                <li>
                    <a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a>
                </li>
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
            <h2 class="section-title">Pourquoi choisir Cadeaux Élégance ?</h2>
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