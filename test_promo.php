<?php
// test_promo.php - Fichier de test indépendant
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session_verification.php';

$pdo = getPDOConnection();

if (!$pdo) {
    die("❌ Connexion BDD échouée");
}

echo "<h2>Test des promotions</h2>";

// Tester la requête directement
$sql = "SELECT p.*, pp.reduction_personnalisee 
        FROM promotions p
        INNER JOIN promotions_produits pp ON p.id_promotion = pp.id_promotion
        WHERE pp.id_produit = 31
          AND p.actif = 1 
          AND p.date_debut <= NOW() 
          AND p.date_fin >= NOW()";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Promotions pour le produit ID 31 :</h3>";
echo "<pre>";
print_r($results);
echo "</pre>";

// Tester les dates
echo "<h3>Date actuelle :</h3>";
echo date('Y-m-d H:i:s') . "<br>";

echo "<h3>Promotions actives :</h3>";
$sql2 = "SELECT * FROM promotions WHERE actif = 1";
$stmt2 = $pdo->query($sql2);
$all_promos = $stmt2->fetchAll();
echo "<pre>";
print_r($all_promos);
echo "</pre>";

// Tester les produits
$sql3 = "SELECT id_produit, nom, prix_ht, tva FROM produits WHERE id_produit IN (31, 42)";
$stmt3 = $pdo->prepare($sql3);
$stmt3->execute();
$produits = $stmt3->fetchAll();

echo "<h3>Produits :</h3>";
foreach($produits as $p) {
    $prix_ttc = $p['prix_ht'] * (1 + $p['tva']/100);
    echo "ID: {$p['id_produit']} - {$p['nom']}<br>";
    echo "Prix HT: {$p['prix_ht']} €, TVA: {$p['tva']}%, Prix TTC: {$prix_ttc} €<br>";
    
    // Chercher la promotion
    $promo = null;
    foreach($results as $r) {
        if($r['id_promotion'] == 1 || $r['id_promotion'] == 8) {
            $promo = $r;
            break;
        }
    }
    
    if($promo) {
        $reduction = $promo['valeur'];
        $prix_promo = $prix_ttc * (1 - $reduction/100);
        echo "✓ Promotion trouvée: {$promo['code_promotion']} - {$reduction}%<br>";
        echo "💰 Prix original: {$prix_ttc} € → Prix promo: " . round($prix_promo, 2) . " €<br>";
    } else {
        echo "✗ Aucune promotion trouvée pour ce produit<br>";
    }
    echo "<br>";
}
?>