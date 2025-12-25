<?php
// index.php - Page principale d'affichage des produits
session_start();

// Configuration de la base de données DIRECTE (sans classe)
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Fonction de connexion PDO
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die("<div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
                    <h2 style='color: #e74c3c;'><i class='fas fa-database'></i> Erreur de connexion à la base de données</h2>
                    <p style='margin: 20px 0;'>Veuillez vérifier la configuration de la base de données.</p>
                    <p style='background: #f8f9fa; padding: 15px; border-radius: 8px; font-family: monospace;'>
                        " . htmlspecialchars($e->getMessage()) . "
                    </p>
                    <p style='margin-top: 20px;'>
                        <a href='install.php' style='display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px;'>
                            <i class='fas fa-cogs'></i> Installer la base de données
                        </a>
                    </p>
                </div>");
        }
    }
    
    return $pdo;
}

// Fonction pour récupérer les produits filtrés
function getProduitsFiltres($filtres) {
    $pdo = getPDOConnection();
    
    $sql = "SELECT 
                p.id_produit,
                p.reference,
                p.nom,
                p.description_courte,
                p.prix_ttc,
                p.quantite_stock,
                p.note_moyenne,
                p.nombre_avis,
                p.ventes,
                c.nom as categorie_nom,
                img.url_image as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            LEFT JOIN (
                SELECT id_produit, url_image 
                FROM images_produits 
                WHERE principale = 1 
                ORDER BY ordre LIMIT 1
            ) img ON p.id_produit = img.id_produit
            WHERE p.statut = 'actif'";
    
    $params = [];
    $conditions = [];
    
    // Recherche texte
    if (!empty($filtres['recherche'])) {
        $conditions[] = "(p.nom LIKE ? OR p.description_courte LIKE ? OR p.description LIKE ?)";
        $searchTerm = '%' . $filtres['recherche'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Filtre par catégorie
    if (!empty($filtres['categorie'])) {
        $conditions[] = "p.id_categorie = ?";
        $params[] = $filtres['categorie'];
    }
    
    // Filtre par prix minimum
    if (!empty($filtres['prix_min']) && is_numeric($filtres['prix_min'])) {
        $conditions[] = "p.prix_ttc >= ?";
        $params[] = $filtres['prix_min'];
    }
    
    // Filtre par prix maximum
    if (!empty($filtres['prix_max']) && is_numeric($filtres['prix_max'])) {
        $conditions[] = "p.prix_ttc <= ?";
        $params[] = $filtres['prix_max'];
    }
    
    // Ajouter les conditions
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
    
    // Tri
    switch ($filtres['tri']) {
        case 'nouveaute':
            $sql .= " ORDER BY p.date_creation DESC";
            break;
        case 'prix-croissant':
            $sql .= " ORDER BY p.prix_ttc ASC";
            break;
        case 'prix-decriossant':
            $sql .= " ORDER BY p.prix_ttc DESC";
            break;
        case 'meilleurs-avis':
            $sql .= " ORDER BY p.note_moyenne DESC, p.nombre_avis DESC";
            break;
        case 'plus-vendus':
            $sql .= " ORDER BY p.ventes DESC";
            break;
        default:
            $sql .= " ORDER BY p.ventes DESC, p.note_moyenne DESC";
            break;
    }
    
    // Pagination
    $sql .= " LIMIT :limit OFFSET :offset";
    
    try {
        $stmt = $pdo->prepare($sql);
        
        // Bind des paramètres
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i, $param, is_numeric($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $i++;
        }
        
        // Bind des paramètres de pagination
        $offset = ($filtres['page'] - 1) * $filtres['limit'];
        $stmt->bindValue(':limit', $filtres['limit'], PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erreur getProduitsFiltres: " . $e->getMessage());
        return [];
    }
}

// Fonction pour récupérer les catégories avec compteur
function getCategoriesAvecCompteur() {
    $pdo = getPDOConnection();
    
    $sql = "SELECT 
                c.id_categorie,
                c.nom,
                c.slug,
                COUNT(p.id_produit) as nb_produits
            FROM categories c
            LEFT JOIN produits p ON c.id_categorie = p.id_categorie AND p.statut = 'actif'
            WHERE c.active = 1
            GROUP BY c.id_categorie, c.nom, c.slug
            ORDER BY c.ordre, c.nom";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur getCategoriesAvecCompteur: " . $e->getMessage());
        return [];
    }
}

// Récupérer les paramètres de recherche avec sanitisation
$recherche = htmlspecialchars(trim($_GET['q'] ?? ''));
$categorie = intval($_GET['categorie'] ?? 0);
$prix_min = filter_var($_GET['prix_min'] ?? '', FILTER_VALIDATE_FLOAT);
$prix_max = filter_var($_GET['prix_max'] ?? '', FILTER_VALIDATE_FLOAT);
$tri = htmlspecialchars($_GET['tri'] ?? 'pertinence');
$page = max(1, intval($_GET['page'] ?? 1));

// Construire les filtres
$filtres = [
    'recherche' => $recherche,
    'categorie' => $categorie > 0 ? $categorie : '',
    'prix_min' => $prix_min !== false ? $prix_min : '',
    'prix_max' => $prix_max !== false ? $prix_max : '',
    'tri' => $tri,
    'page' => $page,
    'limit' => 12
];

// Récupérer les produits
$produits = getProduitsFiltres($filtres);

// Récupérer les catégories pour les filtres
$categories = getCategoriesAvecCompteur();

// Compter le total des produits pour la pagination
$pdo = getPDOConnection();
$sqlCount = "SELECT COUNT(*) as total 
             FROM produits p 
             LEFT JOIN categories c ON p.id_categorie = c.id_categorie
             WHERE p.statut = 'actif'";
             
$paramsCount = [];
$conditionsCount = [];

if (!empty($recherche)) {
    $conditionsCount[] = "(p.nom LIKE ? OR p.description_courte LIKE ? OR p.description LIKE ?)";
    $searchTerm = '%' . $recherche . '%';
    $paramsCount[] = $searchTerm;
    $paramsCount[] = $searchTerm;
    $paramsCount[] = $searchTerm;
}

if (!empty($categorie) && $categorie > 0) {
    $conditionsCount[] = "p.id_categorie = ?";
    $paramsCount[] = $categorie;
}

if (!empty($prix_min) && is_numeric($prix_min)) {
    $conditionsCount[] = "p.prix_ttc >= ?";
    $paramsCount[] = $prix_min;
}

if (!empty($prix_max) && is_numeric($prix_max)) {
    $conditionsCount[] = "p.prix_ttc <= ?";
    $paramsCount[] = $prix_max;
}

if (!empty($conditionsCount)) {
    $sqlCount .= " AND " . implode(" AND ", $conditionsCount);
}

try {
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($paramsCount);
    $resultCount = $stmtCount->fetch();
    $totalProduits = $resultCount['total'] ?? 0;
    $totalPages = ceil($totalProduits / $filtres['limit']);
} catch (PDOException $e) {
    $totalProduits = 0;
    $totalPages = 1;
    error_log("Erreur comptage produits: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recherche de Cadeaux - Cadeaux Élégance</title>
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <style>
        /* Styles de base pour la page produits */
        .products-page {
            padding: 40px 0;
            background: #f8f9fa;
            min-height: 70vh;
        }
        
        .products-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 30px;
        }
        
        @media (max-width: 992px) {
            .products-layout {
                grid-template-columns: 1fr;
            }
        }
        
        .products-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .products-main {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }
        
        .product-image {
            height: 200px;
            overflow: hidden;
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
        
        .product-info {
            padding: 15px;
        }
        
        .product-info h3 {
            margin: 0 0 10px 0;
            font-size: 1.1rem;
            color: #2c3e50;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: #e74c3c;
            margin: 10px 0;
        }
        
        .btn-add-cart {
            width: 100%;
            background: #e74c3c;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-add-cart:hover {
            background: #c0392b;
        }
        
        .btn-add-cart.loading {
            background: #3498db;
            cursor: wait;
        }
        
        .btn-add-cart.loading i {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        
        .cart-count {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            position: absolute;
            top: -8px;
            right: -8px;
        }
        
        /* Notification toast */
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
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        .toast-error {
            background: #e74c3c;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        /* Animation du compteur */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        
        .pulse {
            animation: pulse 0.3s ease;
        }
        
        /* Style pour le message d'erreur de base de données */
        .db-error {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .db-error h3 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Header simple -->
    <header style="background: #2c3e50; color: white; padding: 20px 0;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <a href="index.html" style="color: white; text-decoration: none; font-size: 1.5rem; font-weight: bold;">
                <i class="fas fa-gift"></i> Cadeaux Élégance
            </a>
            <nav>
                <a href="index.html" style="color: white; text-decoration: none; margin-left: 20px;">Accueil</a>
                <a href="index.php" style="color: white; text-decoration: none; margin-left: 20px;">Produits</a>
                <a href="panier.php" style="color: white; text-decoration: none; margin-left: 20px; position: relative;">
                    <i class="fas fa-shopping-cart"></i> Panier
                    <span id="cartCount" class="cart-count" style="display: none;">0</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Section principale -->
    <main class="products-page">
        <div class="container">
            <h1 style="margin-bottom: 30px;">Recherche de produits</h1>
            
            <div class="products-layout">
                <!-- Sidebar des filtres -->
                <aside class="products-sidebar">
                    <h3>Filtres</h3>
                    
                    <form method="GET" style="margin-top: 20px;">
                        <div style="margin-bottom: 20px;">
                            <label>Recherche:</label>
                            <input type="text" name="q" value="<?= htmlspecialchars($recherche) ?>" 
                                   style="width: 100%; padding: 10px; margin-top: 5px;"
                                   placeholder="Nom du produit...">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label>Catégorie:</label>
                            <select name="categorie" style="width: 100%; padding: 10px; margin-top: 5px;">
                                <option value="">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id_categorie'] ?>" 
                                        <?= $categorie == $cat['id_categorie'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nom']) ?> (<?= $cat['nb_produits'] ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label>Prix min:</label>
                            <input type="number" name="prix_min" value="<?= $prix_min !== false ? $prix_min : '' ?>" 
                                   style="width: 100%; padding: 10px; margin-top: 5px;"
                                   placeholder="0" min="0" step="0.01">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label>Prix max:</label>
                            <input type="number" name="prix_max" value="<?= $prix_max !== false ? $prix_max : '' ?>" 
                                   style="width: 100%; padding: 10px; margin-top: 5px;"
                                   placeholder="1000" min="0" step="0.01">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label>Trier par:</label>
                            <select name="tri" style="width: 100%; padding: 10px; margin-top: 5px;">
                                <option value="pertinence" <?= $tri == 'pertinence' ? 'selected' : '' ?>>Pertinence</option>
                                <option value="nouveaute" <?= $tri == 'nouveaute' ? 'selected' : '' ?>>Nouveautés</option>
                                <option value="prix-croissant" <?= $tri == 'prix-croissant' ? 'selected' : '' ?>>Prix croissant</option>
                                <option value="prix-decriossant" <?= $tri == 'prix-decriossant' ? 'selected' : '' ?>>Prix décroissant</option>
                                <option value="meilleurs-avis" <?= $tri == 'meilleurs-avis' ? 'selected' : '' ?>>Meilleurs avis</option>
                                <option value="plus-vendus" <?= $tri == 'plus-vendus' ? 'selected' : '' ?>>Plus vendus</option>
                            </select>
                        </div>
                        
                        <button type="submit" style="width: 100%; padding: 12px; background: #3498db; color: white; border: none; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-filter"></i> Appliquer les filtres
                        </button>
                    </form>
                </aside>

                <!-- Contenu principal -->
                <div class="products-main">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <p><strong><?= $totalProduits ?></strong> produit(s) trouvé(s)</p>
                        <div>
                            <a href="index.php" style="padding: 8px 15px; background: #f8f9fa; color: #2c3e50; text-decoration: none; border-radius: 6px; margin-right: 10px;">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($produits)): ?>
                        <!-- Message si aucun produit trouvé -->
                        <?php if (!empty($recherche) || !empty($categorie) || !empty($prix_min) || !empty($prix_max)): ?>
                            <!-- Recherche avec filtres mais pas de résultats -->
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-search fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                                <h3>Aucun résultat trouvé</h3>
                                <p>Essayez de modifier vos critères de recherche</p>
                                <a href="index.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px;">
                                    <i class="fas fa-redo"></i> Réinitialiser la recherche
                                </a>
                            </div>
                        <?php else: ?>
                            <!-- Pas de produits dans la base de données -->
                            <div style="text-align: center; padding: 40px;">
                                <i class="fas fa-box-open fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                                <h3>Aucun produit disponible</h3>
                                <p>La boutique est actuellement vide.</p>
                                <a href="install.php" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; margin-right: 10px;">
                                    <i class="fas fa-cogs"></i> Installer des produits
                                </a>
                                <a href="index.html" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background: #2c3e50; color: white; text-decoration: none; border-radius: 6px;">
                                    <i class="fas fa-home"></i> Retour à l'accueil
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Affichage des produits trouvés -->
                        <div class="products-grid">
                            <?php foreach ($produits as $produit): 
                                $note = $produit['note_moyenne'] ?? 0;
                                $nombreAvis = $produit['nombre_avis'] ?? 0;
                                $imageUrl = !empty($produit['image']) ? htmlspecialchars($produit['image']) : 'img/default-product.jpg';
                            ?>
                            <div class="product-card" data-id="<?= $produit['id_produit'] ?>">
                                <div class="product-image">
                                    <img src="<?= $imageUrl ?>" 
                                         alt="<?= htmlspecialchars($produit['nom']) ?>"
                                         onerror="this.src='img/default-product.jpg'">
                                </div>
                                
                                <div class="product-info">
                                    <span style="font-size: 0.8rem; color: #7f8c8d;">
                                        <?= htmlspecialchars($produit['categorie_nom'] ?? 'Cadeau') ?>
                                    </span>
                                    <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                                    
                                    <?php if (!empty($produit['description_courte'])): ?>
                                    <p style="color: #666; font-size: 0.9rem; margin: 10px 0;">
                                        <?= htmlspecialchars(substr($produit['description_courte'], 0, 80)) ?>...
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($note > 0): ?>
                                    <div class="product-rating">
                                        <?php
                                        $fullStars = floor($note);
                                        $hasHalfStar = ($note - $fullStars) >= 0.5;
                                        $emptyStars = 5 - $fullStars - ($hasHalfStar ? 1 : 0);
                                        
                                        // Étoiles pleines
                                        for ($i = 0; $i < $fullStars; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                        
                                        <?php if ($hasHalfStar): ?>
                                            <i class="fas fa-star-half-alt"></i>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 0; $i < $emptyStars; $i++): ?>
                                            <i class="far fa-star"></i>
                                        <?php endfor; ?>
                                        
                                        <?php if ($nombreAvis > 0): ?>
                                            <span class="rating-count">(<?= $nombreAvis ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="product-price">
                                        <?= number_format($produit['prix_ttc'], 2, ',', ' ') ?> €
                                    </div>
                                    
                                    <?php if ($produit['quantite_stock'] > 0): ?>
                                        <button class="btn-add-cart" 
                                                data-id="<?= $produit['id_produit'] ?>"
                                                title="Ajouter au panier">
                                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-add-cart" 
                                                style="background: #95a5a6; cursor: not-allowed;"
                                                disabled
                                                title="Produit épuisé">
                                            <i class="fas fa-times-circle"></i> Épuisé
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination simple -->
                        <?php if ($totalPages > 1): ?>
                        <div style="display: flex; justify-content: center; gap: 10px; margin-top: 30px;">
                            <?php if ($page > 1): ?>
                            <a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                               style="padding: 10px 15px; background: #f8f9fa; color: #2c3e50; text-decoration: none; border-radius: 6px;">
                                ← Précédent
                            </a>
                            <?php endif; ?>
                            
                            <span style="padding: 10px 15px;">Page <?= $page ?> sur <?= $totalPages ?></span>
                            
                            <?php if ($page < $totalPages): ?>
                            <a href="index.php?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                               style="padding: 10px 15px; background: #f8f9fa; color: #2c3e50; text-decoration: none; border-radius: 6px;">
                                Suivant →
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer simple -->
    <footer style="background: #2c3e50; color: white; padding: 40px 0; margin-top: 40px;">
        <div class="container">
            <p style="text-align: center;">&copy; 2025 Cadeaux Élégance</p>
        </div>
    </footer>

    <script>
        // Gestionnaire de panier pour la page index.php
        class PanierManager {
            constructor() {
                this.apiUrl = 'api/panier.php';
                this.cartCountElement = document.getElementById('cartCount');
                this.initCartCount();
                this.initEvents();
            }
            
            async initCartCount() {
                try {
                    const response = await fetch(`${this.apiUrl}?action=compter`);
                    const data = await response.json();
                    
                    if (data.success) {
                        this.updateCartCountDisplay(data.total);
                    }
                } catch (error) {
                    console.error('Erreur chargement compteur:', error);
                }
            }
            
            updateCartCountDisplay(count) {
                if (this.cartCountElement) {
                    this.cartCountElement.textContent = count;
                    this.cartCountElement.style.display = count > 0 ? 'inline-flex' : 'none';
                }
            }
            
            initEvents() {
                // Gérer les clics sur les boutons "Ajouter au panier"
                document.addEventListener('click', async (e) => {
                    if (e.target.closest('.btn-add-cart')) {
                        e.preventDefault();
                        const button = e.target.closest('.btn-add-cart');
                        const id_produit = button.dataset.id;
                        
                        if (id_produit) {
                            await this.ajouterAuPanier(id_produit, 1, button);
                        }
                    }
                });
            }
            
            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                // Mettre le bouton en état de chargement
                if (button) {
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
                    button.classList.add('loading');
                    button.disabled = true;
                }
                
                try {
                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'ajouter',
                            id_produit: id_produit,
                            quantite: quantite
                        })
                    });
                    
                    const data = await response.json();
                    
                    // Réinitialiser le bouton
                    if (button) {
                        if (data.success) {
                            button.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
                            setTimeout(() => {
                                button.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter';
                                button.classList.remove('loading');
                                button.disabled = false;
                            }, 1500);
                        } else {
                            button.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter';
                            button.classList.remove('loading');
                            button.disabled = false;
                            this.showNotification(data.message || 'Erreur', 'error');
                        }
                    }
                    
                    if (data.success) {
                        // Mettre à jour le compteur
                        this.updateCartCountDisplay(data.total_articles || data.total);
                        this.showNotification(data.produit_nom + ' ajouté au panier !', 'success');
                        this.animateCartCounter();
                    }
                    
                    return data;
                    
                } catch (error) {
                    console.error('Erreur:', error);
                    
                    if (button) {
                        button.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter';
                        button.classList.remove('loading');
                        button.disabled = false;
                    }
                    
                    this.showNotification('Erreur de connexion', 'error');
                    return { success: false, message: 'Erreur réseau' };
                }
            }
            
            showNotification(message, type = 'success') {
                // Supprimer les notifications existantes
                document.querySelectorAll('.toast-notification').forEach(toast => {
                    toast.remove();
                });
                
                // Créer la notification
                const toast = document.createElement('div');
                toast.className = `toast-notification ${type === 'error' ? 'toast-error' : ''}`;
                toast.innerHTML = `
                    <div class="toast-icon">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    </div>
                    <div class="toast-message">${message}</div>
                `;
                
                document.body.appendChild(toast);
                
                // Supprimer après 3 secondes
                setTimeout(() => {
                    toast.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 300);
                }, 3000);
            }
            
            animateCartCounter() {
                if (this.cartCountElement) {
                    this.cartCountElement.classList.add('pulse');
                    setTimeout(() => {
                        this.cartCountElement.classList.remove('pulse');
                    }, 300);
                }
            }
        }
        
        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            window.panierManager = new PanierManager();
        });
    </script>
</body>
</html>