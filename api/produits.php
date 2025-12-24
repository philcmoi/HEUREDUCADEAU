<?php
// api/produits.php - Fichier API séparé POUR HEURE DU CADEAU
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Configuration de la base de données HEURE DU CADEAU
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe'); // À MODIFIER SELON VOTRE CONFIGURATION
define('DB_PASS', 'l@99339R'); // À MODIFIER SELON VOTRE CONFIGURATION

// Fonction de connexion PDO avec meilleure gestion d'erreurs
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BD produits.php: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// Gérer les requêtes OPTIONS (CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Récupérer l'action
$action = $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action non spécifiée',
        'suggestions' => 'Utilisez ?action=featured, ?action=get, ?action=categories, ?action=by_category, ?action=search'
    ]);
    exit;
}

$pdo = getPDOConnection();

if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Connexion à la base de données impossible',
        'debug' => 'Vérifiez les paramètres de connexion dans produits.php'
    ]);
    exit;
}

switch ($action) {
    
    // Endpoint pour les produits phares (index.html)
    case 'featured':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
        
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
                        p.date_creation,
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
                
                // Convertir les types
                $product['prix_ttc'] = floatval($product['prix_ttc']);
                $product['note_moyenne'] = floatval($product['note_moyenne']);
                $product['quantite_stock'] = intval($product['quantite_stock']);
                $product['ventes'] = intval($product['ventes']);
                $product['nombre_avis'] = intval($product['nombre_avis']);
                
                // Formater la date
                $product['date_creation_formatted'] = date('d/m/Y', strtotime($product['date_creation']));
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
        
    // Récupérer un produit spécifique par ID
    case 'get':
        $id = $_GET['id'] ?? 0;
        
        if (!$id) {
            echo json_encode(['success' => false, 'message' => 'ID produit requis']);
            exit;
        }
        
        try {
            $sql = "SELECT p.*, c.nom as categorie_nom, c.slug as categorie_slug 
                    FROM produits p 
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
                    WHERE p.id_produit = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $product = $stmt->fetch();
            
            if ($product) {
                // Récupérer les images
                $sql_images = "SELECT * FROM images_produits WHERE id_produit = :id ORDER BY ordre";
                $stmt_images = $pdo->prepare($sql_images);
                $stmt_images->execute([':id' => $id]);
                $images = $stmt_images->fetchAll();
                
                // Récupérer les variants si existants
                $sql_variants = "SELECT * FROM variants WHERE id_produit = :id AND actif = 1";
                $stmt_variants = $pdo->prepare($sql_variants);
                $stmt_variants->execute([':id' => $id]);
                $variants = $stmt_variants->fetchAll();
                
                // Récupérer les avis approuvés
                $sql_avis = "SELECT a.*, cl.nom, cl.prenom 
                            FROM avis a 
                            LEFT JOIN clients cl ON a.id_client = cl.id_client 
                            WHERE a.id_produit = :id AND a.statut = 'approuve' 
                            ORDER BY a.date_creation DESC 
                            LIMIT 10";
                $stmt_avis = $pdo->prepare($sql_avis);
                $stmt_avis->execute([':id' => $id]);
                $avis = $stmt_avis->fetchAll();
                
                $product['images'] = $images;
                $product['variants'] = $variants;
                $product['avis'] = $avis;
                
                echo json_encode(['success' => true, 'product' => $product]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
            }
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (get): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Récupérer toutes les catégories
    case 'categories':
        try {
            $sql = "SELECT * FROM categories WHERE active = 1 ORDER BY ordre";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $categories = $stmt->fetchAll();
            
            // Pour chaque catégorie, compter les produits actifs
            foreach ($categories as &$category) {
                $sql_count = "SELECT COUNT(*) as count FROM produits WHERE id_categorie = :id AND statut = 'actif'";
                $stmt_count = $pdo->prepare($sql_count);
                $stmt_count->execute([':id' => $category['id_categorie']]);
                $count = $stmt_count->fetch();
                $category['produit_count'] = $count['count'];
            }
            
            echo json_encode(['success' => true, 'categories' => $categories]);
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (categories): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Récupérer les produits par catégorie
    case 'by_category':
        $category_id = $_GET['category_id'] ?? 0;
        $limit = $_GET['limit'] ?? 12;
        $page = $_GET['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        
        if (!$category_id) {
            echo json_encode(['success' => false, 'message' => 'ID catégorie requis']);
            exit;
        }
        
        try {
            // D'abord récupérer le nom de la catégorie
            $sql_cat = "SELECT nom, slug FROM categories WHERE id_categorie = :id";
            $stmt_cat = $pdo->prepare($sql_cat);
            $stmt_cat->execute([':id' => $category_id]);
            $categorie = $stmt_cat->fetch();
            
            if (!$categorie) {
                echo json_encode(['success' => false, 'message' => 'Catégorie non trouvée']);
                exit;
            }
            
            // Récupérer les produits
            $sql = "SELECT p.*, 
                    (SELECT ip.url_image FROM images_produits ip 
                     WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image
                    FROM produits p 
                    WHERE p.statut = 'actif' AND p.id_categorie = :category_id 
                    ORDER BY p.date_creation DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':category_id', $category_id, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            // Compter le total
            $sql_count = "SELECT COUNT(*) as total FROM produits WHERE statut = 'actif' AND id_categorie = :category_id";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute([':category_id' => $category_id]);
            $total = $stmt_count->fetch()['total'];
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'categorie' => $categorie,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'current_page' => $page
            ]);
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (by_category): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Recherche de produits
    case 'search':
        $query = $_GET['q'] ?? '';
        $category_id = $_GET['category_id'] ?? 0;
        $min_price = $_GET['min_price'] ?? 0;
        $max_price = $_GET['max_price'] ?? 10000;
        $limit = $_GET['limit'] ?? 12;
        $page = $_GET['page'] ?? 1;
        $offset = ($page - 1) * $limit;
        
        if (empty($query) && !$category_id) {
            echo json_encode(['success' => false, 'message' => 'Terme de recherche ou catégorie requis']);
            exit;
        }
        
        try {
            // Construire la requête dynamiquement
            $sql = "SELECT p.*, 
                    (SELECT ip.url_image FROM images_produits ip 
                     WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image,
                    c.nom as categorie_nom
                    FROM produits p 
                    LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
                    WHERE p.statut = 'actif' 
                    AND p.prix_ttc BETWEEN :min_price AND :max_price";
            
            $params = [
                ':min_price' => $min_price,
                ':max_price' => $max_price
            ];
            
            // Ajouter filtre catégorie
            if ($category_id) {
                $sql .= " AND p.id_categorie = :category_id";
                $params[':category_id'] = $category_id;
            }
            
            // Ajouter recherche texte
            if (!empty($query)) {
                $sql .= " AND (p.nom LIKE :query OR p.description LIKE :query OR p.description_courte LIKE :query OR c.nom LIKE :query)";
                $params[':query'] = '%' . $query . '%';
            }
            
            // Compter d'abord
            $sql_count = str_replace(
                "SELECT p.*, (SELECT ip.url_image FROM images_produits ip WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image, c.nom as categorie_nom",
                "SELECT COUNT(*) as total",
                $sql
            );
            
            $stmt_count = $pdo->prepare($sql_count);
            foreach ($params as $key => $value) {
                $stmt_count->bindValue($key, $value);
            }
            $stmt_count->execute();
            $total = $stmt_count->fetch()['total'];
            
            // Récupérer les produits avec pagination
            $sql .= " ORDER BY p.date_creation DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            
            // Binder tous les paramètres
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $products = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'query' => $query,
                'total' => $total,
                'pages' => ceil($total / $limit),
                'current_page' => $page
            ]);
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (search): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Récupérer les nouveautés
    case 'new':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
        
        try {
            $sql = "SELECT p.*, 
                    (SELECT ip.url_image FROM images_produits ip 
                     WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image
                    FROM produits p 
                    WHERE p.statut = 'actif' 
                    ORDER BY p.date_creation DESC 
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'count' => count($products)
            ]);
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (new): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Récupérer les meilleures ventes
    case 'best_sellers':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 8;
        
        try {
            $sql = "SELECT p.*, 
                    (SELECT ip.url_image FROM images_produits ip 
                     WHERE ip.id_produit = p.id_produit AND ip.principale = 1 LIMIT 1) as image
                    FROM produits p 
                    WHERE p.statut = 'actif' 
                    ORDER BY p.ventes DESC 
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true, 
                'products' => $products,
                'count' => count($products)
            ]);
        } catch (PDOException $e) {
            error_log("Erreur SQL produits.php (best_sellers): " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
        }
        break;
        
    // Test de connexion
    case 'test':
        echo json_encode([
            'success' => true,
            'message' => 'API produits fonctionnelle',
            'timestamp' => date('Y-m-d H:i:s'),
            'server' => $_SERVER['SERVER_NAME']
        ]);
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue',
            'available_actions' => [
                'featured' => 'Produits phares',
                'get' => 'Produit par ID',
                'categories' => 'Liste catégories',
                'by_category' => 'Produits par catégorie',
                'search' => 'Recherche produits',
                'new' => 'Nouveautés',
                'best_sellers' => 'Meilleures ventes',
                'test' => 'Test API'
            ]
        ]);
        break;
}
?>