<?php
require_once 'config/Database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPDO();
    
    // Test 1 : Comptez les produits
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM produits WHERE statut = 'actif'");
    $result = $stmt->fetch();
    echo "Produits actifs dans la base : " . $result['total'] . "<br>";
    
    // Test 2 : Voir les produits
    $stmt = $pdo->query("SELECT id_produit, nom, reference FROM produits LIMIT 10");
    $products = $stmt->fetchAll();
    
    echo "Liste des produits :<br>";
    print_r($products);
    
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>