<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'root');
define('DB_PASS', '');

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
            return null;
        }
    }
    
    return $pdo;
}

$id_produit = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_produit < 1) {
    echo json_encode([
        'success' => false,
        'message' => 'ID produit invalide'
    ]);
    exit;
}

$pdo = getPDOConnection();
if (!$pdo) {
    echo json_encode([
        'success' => false,
        'message' => 'Connexion BD impossible'
    ]);
    exit;
}

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
                (
                    SELECT ip.url_image 
                    FROM images_produits ip 
                    WHERE ip.id_produit = p.id_produit 
                    AND ip.principale = 1 
                    LIMIT 1
                ) as image
            FROM produits p
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE p.id_produit = :id AND p.statut = 'actif'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id_produit, PDO::PARAM_INT);
    $stmt->execute();
    
    $produit = $stmt->fetch();
    
    if ($produit) {
        if (empty($produit['image'])) {
            $produit['image'] = 'img/default-product.jpg';
        }
        
        echo json_encode([
            'success' => true,
            'produit' => $produit
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé dans la base'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur BD: ' . $e->getMessage()
    ]);
}
?>