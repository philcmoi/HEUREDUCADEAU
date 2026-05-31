<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'session_verification.php';

$pdo = getPDOConnection();

echo "<h2>DEBUG COMPLET</h2>";

// 1. Récupération brute des données
$sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
        FROM produits 
        WHERE statut = 'actif' 
        AND id_produit IN (31, 42)
        ORDER BY id_produit";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Étape 1: Résultats bruts de la requête</h3>";
echo "<pre>";
print_r($resultats);
echo "</pre>";

// 2. Dédoublonnage
$produits_temp = [];
foreach ($resultats as $row) {
    $produits_temp[$row['id_produit']] = $row;
}
$produits = array_values($produits_temp);

echo "<h3>Étape 2: Après dédoublonnage (par id_produit)</h3>";
echo "<pre>";
print_r($produits);
echo "</pre>";

// 3. Récupération des images
$images = [];
if (!empty($produits)) {
    $ids = array_column($produits, 'id_produit');
    echo "<h3>IDs des produits: " . implode(', ', $ids) . "</h3>";
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_img = $pdo->prepare("SELECT id_produit, url_image FROM images_produits WHERE id_produit IN ($placeholders) AND principale = 1");
    $stmt_img->execute($ids);
    while ($img = $stmt_img->fetch(PDO::FETCH_ASSOC)) {
        $images[$img['id_produit']] = $img['url_image'];
    }
}

echo "<h3>Étape 3: Images trouvées</h3>";
echo "<pre>";
print_r($images);
echo "</pre>";

// 4. Vérification de la fonction promotion
function getBestActivePromotionForProduct($pdo, $product_id) {
    $sql = "SELECT p.*, pp.reduction_personnalisee 
            FROM promotions p
            INNER JOIN promotions_produits pp ON p.id_promotion = pp.id_promotion
            WHERE pp.id_produit = :product_id
              AND p.actif = 1 
              AND p.date_debut <= NOW() 
              AND p.date_fin >= NOW()
            ORDER BY p.valeur DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $product_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// 5. Traitement final
echo "<h3>Étape 4: Traitement des promotions</h3>";
foreach ($produits as &$p) {
    echo "Traitement produit ID {$p['id_produit']}: {$p['nom']}<br>";
    $prix_ttc = round($p['prix_ht'] * (1 + $p['tva'] / 100), 2);
    $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
    
    if ($promo) {
        $p['has_promotion'] = true;
        $p['reduction_percent'] = $promo['valeur'];
        $p['prix_promo'] = round($prix_ttc * (1 - $promo['valeur'] / 100), 2);
        $p['prix_original'] = $prix_ttc;
        echo "  -> Promotion appliquée: {$promo['valeur']}%<br>";
    } else {
        $p['has_promotion'] = false;
        $p['reduction_percent'] = 0;
        $p['prix_promo'] = $prix_ttc;
        $p['prix_original'] = $prix_ttc;
        echo "  -> Pas de promotion<br>";
    }
}

echo "<h3>Étape 5: Produits finaux avant affichage HTML</h3>";
echo "<pre>";
print_r($produits);
echo "</pre>";

// Affichage HTML simplifié
?>
<!DOCTYPE html>
<html>
<head>
    <title>DEBUG Catalogue</title>
</head>
<body>
    <h2>AFFICHAGE DES PRODUITS</h2>
    
    <?php if (count($produits) === 2): ?>
        <p style="color:green">✓ 2 produits trouvés, tout est normal.</p>
    <?php else: ?>
        <p style="color:red">✗ Problème: <?= count($produits) ?> produit(s) trouvé(s) au lieu de 2</p>
    <?php endif; ?>
    
    <div style="display:flex; gap:30px; margin-top:30px;">
        <?php foreach ($produits as $index => $p): ?>
            <div style="border:1px solid #ccc; padding:20px; width:250px;">
                <h3>Produit #<?= $index + 1 ?></h3>
                <p><strong>ID:</strong> <?= $p['id_produit'] ?></p>
                <p><strong>Nom:</strong> <?= htmlspecialchars($p['nom']) ?></p>
                <p><strong>Prix original:</strong> <?= $p['prix_original'] ?> €</p>
                <p><strong>Prix promo:</strong> <?= $p['prix_promo'] ?> €</p>
                <p><strong>Stock:</strong> <?= $p['quantite_stock'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>