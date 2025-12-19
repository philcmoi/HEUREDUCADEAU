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
        'suggestions' => 'Utilisez ?action=featured, ?action=get, ?action=categories'
    ]);
    exit;
}

$pdo = getPDOConnection();

if (!$pdo && $action !== 'test') {
    echo json_encode([
        'success' => false,
        'message' => 'Connexion à la base de données impossible',
        'debug' => 'Vérifiez les paramètres de connexion'
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
        
    // ... (le reste du code produits.php reste identique à votre version)
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue',
            'available_actions' => ['featured', 'get', 'categories', 'by_category', 'search']
        ]);
        break;
}
?>