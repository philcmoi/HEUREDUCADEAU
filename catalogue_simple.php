<?php
require_once 'session_verification.php';

$pdo = getPDOConnection();

// Requête TRÈS SIMPLE
$stmt = $pdo->query("SELECT id_produit, nom, prix_ht, tva, quantite_stock FROM produits WHERE id_produit IN (31, 42) ORDER BY id_produit");
$produits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Affichage debug
echo "<pre>";
echo "Nombre de produits: " . count($produits) . "\n";
print_r($produits);
echo "</pre>";
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Simple Catalogue</title>
</head>
<body>
    <h1>Test Simple</h1>
    <div style="display:flex; gap:30px;">
        <?php foreach ($produits as $p): ?>
            <div style="border:1px solid #ccc; padding:20px;">
                <h2><?= htmlspecialchars($p['nom']) ?></h2>
                <p>ID: <?= $p['id_produit'] ?></p>
                <p>Prix HT: <?= $p['prix_ht'] ?> €</p>
                <p>TVA: <?= $p['tva'] ?>%</p>
                <p>Stock: <?= $p['quantite_stock'] ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>