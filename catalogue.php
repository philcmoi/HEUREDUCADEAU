<?php
require_once 'session_verification.php';

$pdo = getPDOConnection();

// Requête SIMPLE sans GROUP BY ni DISTINCT qui pourrait causer des problèmes
$sql = "SELECT id_produit, nom, prix_ttc, quantite_stock 
        FROM produits 
        WHERE statut = 'actif' 
        AND id_produit IN (31, 42)
        ORDER BY id_produit";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$produits = $stmt->fetchAll();

// Récupération des images
$images = [];
if (!empty($produits)) {
    $ids = array_column($produits, 'id_produit');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_img = $pdo->prepare("SELECT id_produit, url_image FROM images_produits WHERE id_produit IN ($placeholders) AND principale = 1");
    $stmt_img->execute($ids);
    while ($img = $stmt_img->fetch()) {
        $images[$img['id_produit']] = $img['url_image'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catalogue - HEURE DU CADEAU</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: #2c3e50; color: white; padding: 15px; margin-bottom: 20px; text-align: center; }
        .products { display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; }
        .product { background: white; border-radius: 10px; padding: 20px; width: 280px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .product img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; background: #f0f0f0; }
        .product h2 { margin: 15px 0 10px; color: #333; }
        .price { font-size: 24px; color: #e74c3c; font-weight: bold; margin: 10px 0; }
        .stock { color: #27ae60; margin: 5px 0; }
        button { background: #27ae60; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
        button:hover { background: #219653; }
        footer { text-align: center; margin-top: 40px; padding: 20px; color: #777; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-gift"></i> HEURE DU CADEAU</h1>
            <p>Trouvez le cadeau parfait</p>
        </div>

        <div class="products">
            <?php foreach ($produits as $p): 
                $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/280x200/3498db/ffffff?text=' . urlencode($p['nom']);
            ?>
            <div class="product">
                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                <h2><?= htmlspecialchars($p['nom']) ?></h2>
                <div class="price"><?= number_format($p['prix_ttc'], 2) ?> €</div>
                <div class="stock">Stock : <?= $p['quantite_stock'] ?></div>
                <button onclick="alert('Fonctionnalité à venir')"><i class="fas fa-cart-plus"></i> Ajouter au panier</button>
            </div>
            <?php endforeach; ?>
        </div>

        <footer>
            <p>&copy; 2025 HEURE DU CADEAU - Tous droits réservés</p>
        </footer>
    </div>
</body>
</html>