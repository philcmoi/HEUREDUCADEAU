<?php
// api/produits.php - Fichier API séparé
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Fonction de connexion PDO avec meilleure gestion d'erreurs
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
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BD: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// Récupérer l'action
$action = $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action non spécifiée',
        'suggestions' => 'Utilisez ?action=featured, ?action=get, ?action=categories'
    ]);
    exit;
}

$pdo = getPDOConnection();

switch ($action) {
    
    // Endpoint pour les produits phares (index.html)
    case 'featured':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
        
        if (!$pdo) {
            echo json_encode([
                'success' => false,
                'message' => 'Connexion à la base de données impossible',
                'debug' => 'Vérifiez les paramètres de connexion dans produits.php'
            ]);
            exit;
        }
        
        try {
            // Récupérer les produits avec leurs images principales
            $sql = "SELECT 
                        p.id_produit,
                        p.reference,
                        p.nom,
                        p.slug,
                        p.description_courte,
                        p.prix_ttc,
                        p.quantite_stock,
                        p.note_moyenne,
                        p.nombre_avis,
                        p.ventes,
                        p.statut,
                        c.nom as categorie_nom,
                        c.slug as categorie_slug,
                        (
                            SELECT ip.url_image 
                            FROM images_produits ip 
                            WHERE ip.id_produit = p.id_produit 
                            AND ip.principale = 1 
                            LIMIT 1
                        ) as image_principale
                    FROM produits p
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                    WHERE p.statut = 'actif'
                    ORDER BY p.ventes DESC, p.note_moyenne DESC
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            // Ajouter une image par défaut si aucune image n'est disponible
            foreach ($products as &$product) {
                if (empty($product['image_principale'])) {
                    $product['image'] = 'img/default-product.jpg';
                } else {
                    $product['image'] = $product['image_principale'];
                }
                unset($product['image_principale']); // Nettoyer le champ temporaire
            }
            
            echo json_encode([
                'success' => true,
                'products' => $products,
                'count' => count($products),
                'debug' => $limit . ' produits chargés depuis la base'
            ]);
            
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (featured): " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des produits',
                'debug' => $e->getMessage()
            ]);
        }
        break;
        
    // Endpoint pour récupérer un produit spécifique
    case 'get':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            
            if (!$pdo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Connexion à la base de données impossible'
                ]);
                exit;
            }
            
            try {
                // Récupérer le produit avec ses détails
                $sql = "SELECT 
                            p.*,
                            c.nom as categorie_nom,
                            c.slug as categorie_slug,
                            (
                                SELECT GROUP_CONCAT(url_image ORDER BY ordre SEPARATOR '|')
                                FROM images_produits ip 
                                WHERE ip.id_produit = p.id_produit
                            ) as images
                        FROM produits p
                        LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                        WHERE p.id_produit = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                $product = $stmt->fetch();
                
                if ($product) {
                    // Traiter les images
                    if (!empty($product['images'])) {
                        $product['image_list'] = explode('|', $product['images']);
                        $product['image'] = $product['image_list'][0] ?? 'img/default-product.jpg';
                    } else {
                        $product['image'] = 'img/default-product.jpg';
                        $product['image_list'] = ['img/default-product.jpg'];
                    }
                    unset($product['images']);
                    
                    // Récupérer les variantes si elles existent
                    $sqlVariants = "SELECT * FROM variants WHERE id_produit = :id AND actif = 1";
                    $stmtVariants = $pdo->prepare($sqlVariants);
                    $stmtVariants->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtVariants->execute();
                    $product['variants'] = $stmtVariants->fetchAll();
                    
                    // Récupérer les avis approuvés
                    $sqlAvis = "SELECT * FROM avis WHERE id_produit = :id AND statut = 'approuve' ORDER BY date_creation DESC";
                    $stmtAvis = $pdo->prepare($sqlAvis);
                    $stmtAvis->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtAvis->execute();
                    $product['avis'] = $stmtAvis->fetchAll();
                    
                    echo json_encode([
                        'success' => true,
                        'product' => $product
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Produit non trouvé'
                    ]);
                }
                
            } catch (PDOException $e) {
                error_log("Erreur SQL produits.php (get): " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la récupération du produit'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ID produit manquant'
            ]);
        }
        break;
        
    // Endpoint pour toutes les catégories
    case 'categories':
        if (!$pdo) {
            echo json_encode([
                'success' => false,
                'message' => 'Connexion à la base de données impossible'
            ]);
            exit;
        }
        
        try {
            $sql = "SELECT 
                        id_categorie,
                        nom,
                        slug,
                        description,
                        image,
                        ordre
                    FROM categories 
                    WHERE active = 1
                    ORDER BY ordre, nom";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            $categories = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'categories' => $categories,
                'count' => count($categories)
            ]);
            
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (categories): " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la récupération des catégories'
            ]);
        }
        break;
        
    // Endpoint pour les produits par catégorie
    case 'by_category':
        if (isset($_GET['category_id'])) {
            $categoryId = (int)$_GET['category_id'];
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $offset = ($page - 1) * $limit;
            
            if (!$pdo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Connexion à la base de données impossible'
                ]);
                exit;
            }
            
            try {
                // Compter le total pour la pagination
                $sqlCount = "SELECT COUNT(*) as total 
                            FROM produits p
                            WHERE p.id_categorie = :category_id 
                            AND p.statut = 'actif'";
                
                $stmtCount = $pdo->prepare($sqlCount);
                $stmtCount->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $stmtCount->execute();
                $totalResult = $stmtCount->fetch();
                $totalProducts = $totalResult['total'];
                
                // Récupérer les produits
                $sql = "SELECT 
                            p.id_produit,
                            p.nom,
                            p.slug,
                            p.description_courte,
                            p.prix_ttc,
                            p.note_moyenne,
                            p.nombre_avis,
                            p.ventes,
                            (
                                SELECT ip.url_image 
                                FROM images_produits ip 
                                WHERE ip.id_produit = p.id_produit 
                                AND ip.principale = 1 
                                LIMIT 1
                            ) as image
                        FROM produits p
                        WHERE p.id_categorie = :category_id 
                        AND p.statut = 'actif'
                        ORDER BY p.ventes DESC, p.date_creation DESC
                        LIMIT :limit OFFSET :offset";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                
                $products = $stmt->fetchAll();
                
                // Ajouter une image par défaut si nécessaire
                foreach ($products as &$product) {
                    if (empty($product['image'])) {
                        $product['image'] = 'img/default-product.jpg';
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'products' => $products,
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $limit,
                        'total' => $totalProducts,
                        'total_pages' => ceil($totalProducts / $limit)
                    ]
                ]);
                
            } catch (PDOException $e) {
                error_log("Erreur SQL produits.php (by_category): " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la récupération des produits'
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'ID catégorie manquant'
            ]);
        }
        break;
        
    // Endpoint pour la recherche
    case 'search':
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        
        if (empty($query)) {
            echo json_encode([
                'success' => false,
                'message' => 'Terme de recherche manquant'
            ]);
            exit;
        }
        
        if (!$pdo) {
            echo json_encode([
                'success' => false,
                'message' => 'Connexion à la base de données impossible'
            ]);
            exit;
        }
        
        try {
            // Enregistrer la recherche dans la table recherches
            $sessionId = session_id();
            $clientId = isset($_SESSION['id_client']) ? $_SESSION['id_client'] : null;
            
            $sqlLog = "INSERT INTO recherches (id_client, session_id, terme_recherche, date_recherche) 
                      VALUES (:client_id, :session_id, :terme, NOW())";
            $stmtLog = $pdo->prepare($sqlLog);
            $stmtLog->bindValue(':client_id', $clientId, PDO::PARAM_INT);
            $stmtLog->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
            $stmtLog->bindValue(':terme', $query, PDO::PARAM_STR);
            $stmtLog->execute();
            
            // Rechercher les produits
            $sql = "SELECT 
                        p.id_produit,
                        p.nom,
                        p.slug,
                        p.description_courte,
                        p.prix_ttc,
                        p.note_moyenne,
                        c.nom as categorie_nom,
                        (
                            SELECT ip.url_image 
                            FROM images_produits ip 
                            WHERE ip.id_produit = p.id_produit 
                            AND ip.principale = 1 
                            LIMIT 1
                        ) as image
                    FROM produits p
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                    WHERE (p.nom LIKE :query 
                           OR p.description LIKE :query 
                           OR p.description_courte LIKE :query 
                           OR p.marque LIKE :query)
                    AND p.statut = 'actif'
                    ORDER BY p.ventes DESC
                    LIMIT 20";
            
            $stmt = $pdo->prepare($sql);
            $searchTerm = "%" . $query . "%";
            $stmt->bindValue(':query', $searchTerm, PDO::PARAM_STR);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            // Ajouter une image par défaut si nécessaire
            foreach ($products as &$product) {
                if (empty($product['image'])) {
                    $product['image'] = 'img/default-product.jpg';
                }
            }
            
            echo json_encode([
                'success' => true,
                'query' => $query,
                'products' => $products,
                'count' => count($products)
            ]);
            
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (search): " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la recherche'
            ]);
        }
        break;
        
    // Action par défaut
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue',
            'available_actions' => ['featured', 'get', 'categories', 'by_category', 'search']
        ]);
        break;
}
?>