<?php
session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Tester la connexion à la base de données
require_once '../config/database.php';
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Connexion DB OK<br>";
    
    // Vérifier si le panier existe
    $stmt = $pdo->prepare("SELECT * FROM panier WHERE session_id = ?");
    $stmt->execute([session_id()]);
    $panier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "Panier pour cette session: <pre>" . print_r($panier, true) . "</pre>";
    
    // Vérifier les items du panier
    if ($panier) {
        $stmt = $pdo->prepare("
            SELECT pi.*, p.nom, p.reference 
            FROM panier_items pi 
            LEFT JOIN produits p ON pi.id_produit = p.id_produit 
            WHERE pi.id_panier = ?
        ");
        $stmt->execute([$panier['id_panier']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Items dans le panier: <pre>" . print_r($items, true) . "</pre>";
    }
    
} catch (PDOException $e) {
    echo "Erreur DB: " . $e->getMessage() . "<br>";
}
?>