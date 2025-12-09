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
define('DB_USER', 'root');
define('DB_PASS', '');

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
            echo json_encode([
                'success' => false,
                'message' => 'Erreur de connexion BD: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    return $pdo;
}

// Récupérer l'action
$action = $_GET['action'] ?? '';

if (empty($action)) {
    echo json_encode([
        'success' => false,
        'message' => 'Action non spécifiée. Actions disponibles: featured, get, categories, suggestions'
    ]);
    exit;
}

$pdo = getPDOConnection();

switch ($action) {
    
    // Endpoint pour les produits phares (index.html)
    case 'featured':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
        
        try {
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
                    WHERE p.statut = 'actif'
                    ORDER BY p.ventes DESC, p.note_moyenne DESC
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            // Si pas de produits, retourner des produits par défaut
            if (empty($products)) {
                $products = [
                    [
                        'id_produit' => 1,
                        'nom' => 'Bougie parfumée "Élégance"',
                        'categorie_nom' => 'Décoration',
                        'prix_ttc' => 34.90,
                        'image' => 'img/default-product.jpg',
                        'note_moyenne' => 4.5,
                        'nombre_avis' => 12
                    ],
                    [
                        'id_produit' => 2,
                        'nom' => 'Coffret gourmand "Délice"',
                        'categorie_nom' => 'Gastronomie',
                        'prix_ttc' => 49.90,
                        'image' => 'img/default-product.jpg',
                        'note_moyenne' => 4.8,
                        'nombre_avis' => 8
                    ],
                    [
                        'id_produit' => 3,
                        'nom' => 'Montre "Temps Précieux"',
                        'categorie_nom' => 'Bijoux',
                        'prix_ttc' => 89.90,
                        'image' => 'img/default-product.jpg',
                        'note_moyenne' => 4.2,
                        'nombre_avis' => 15
                    ],
                    [
                        'id_produit' => 4,
                        'nom' => 'Set bijoux "Lumière"',
                        'categorie_nom' => 'Bijoux',
                        'prix_ttc' => 74.90,
                        'image' => 'img/default-product.jpg',
                        'note_moyenne' => 4.7,
                        'nombre_avis' => 6
                    ]
                ];
            }
            
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur BD: ' . $e->getMessage()
            ]);
        }
        break;
        
    // Endpoint pour récupérer un produit spécifique
    case 'get':
        if (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            
            try {
                $sql = "SELECT 
                            p.*,
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
                        WHERE p.id_produit = :id AND p.statut = 'actif'";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                
                $product = $stmt->fetch();
                
                if ($product) {
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
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur BD: ' . $e->getMessage()
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
        try {
            $sql = "SELECT 
                        id_categorie,
                        nom,
                        slug,
                        description
                    FROM categories 
                    WHERE active = 1
                    ORDER BY ordre, nom";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            
            $categories = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'categories' => $categories
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur BD: ' . $e->getMessage()
            ]);
        }
        break;
        
    // Endpoint pour les suggestions (utilisé dans panier.html)
    case 'suggestions':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 4;
        
        try {
            $sql = "SELECT 
                        p.id_produit,
                        p.reference,
                        p.nom,
                        p.prix_ttc,
                        p.prix_original,
                        p.promotion,
                        img.url_image as image
                    FROM produits p
                    LEFT JOIN (
                        SELECT id_produit, url_image 
                        FROM images_produits 
                        WHERE principale = 1 
                        ORDER BY ordre LIMIT 1
                    ) img ON p.id_produit = img.id_produit
                    WHERE p.statut = 'actif'
                    ORDER BY RAND()
                    LIMIT :limit";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur BD: ' . $e->getMessage()
            ]);
        }
        break;
        
    // Action par défaut
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue. Actions disponibles: featured, get, categories, suggestions'
        ]);
        break;
}
?>