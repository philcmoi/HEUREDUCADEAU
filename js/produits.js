<?php
// api/produits.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS pour CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Connexion à la base de données
require_once '../config/database.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'featured':
            // Produits phares
            $limit = intval($_GET['limit'] ?? 4);
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id_produit,
                    p.nom,
                    p.reference,
                    p.prix_ttc,
                    p.note_moyenne,
                    p.nombre_avis,
                    p.description_courte,
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
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'products' => $products
            ]);
            break;
            
        case 'get':
            // Récupérer un produit spécifique
            $id_produit = intval($_GET['id'] ?? 0);
            
            if ($id_produit <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
                break;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    p.id_produit,
                    p.nom,
                    p.reference,
                    p.prix_ttc,
                    p.description_courte,
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
                WHERE p.id_produit = ? AND p.statut = 'actif'
            ");
            $stmt->execute([$id_produit]);
            
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
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
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur base de données: ' . $e->getMessage()
    ]);
}
?>