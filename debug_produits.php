<?php
require_once 'session_verification.php';

$pdo = getPDOConnection();

// Récupérer tous les produits actifs
$sql = "SELECT id_produit, reference, nom, prix_ttc, quantite_stock, statut 
        FROM produits 
        WHERE statut = 'actif' 
        ORDER BY id_produit";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$produits = $stmt->fetchAll();

// Récupérer les images
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
    <title>Debug Produits</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .product { background: white; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 20px; max-width: 400px; }
        .product img { max-width: 100%; height: auto; }
        .id { color: blue; font-weight: bold; }
        .name { font-size: 18px; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic des produits</h1>
    
    <h2>📦 Produits en base de données (<?= count($produits) ?>)</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Référence</th>
            <th>Nom</th>
            <th>Prix TTC</th>
            <th>Stock</th>
            <th>Statut</th>
            <th>Image</th>
        </tr>
        <?php foreach ($produits as $p): ?>
        <tr>
            <td><strong>#<?= $p['id_produit'] ?></strong></td>
            <td><?= htmlspecialchars($p['reference']) ?></td>
            <td><?= htmlspecialchars($p['nom']) ?></td>
            <td><?= number_format($p['prix_ttc'], 2) ?> €</td>
            <td><?= $p['quantite_stock'] ?></td>
            <td><?= $p['statut'] ?></td>
            <td>
                <?php if (isset($images[$p['id_produit']])): ?>
                    ✅ Image trouvée
                <?php else: ?>
                    ❌ Aucune image
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>🖼️ Affichage des produits</h2>
    <div style="display: flex; gap: 20px; flex-wrap: wrap;">
        <?php foreach ($produits as $p): 
            $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/300x200?text=Produit+' . $p['id_produit'];
        ?>
        <div class="product">
            <div class="id">ID: <?= $p['id_produit'] ?></div>
            <div class="name"><?= htmlspecialchars($p['nom']) ?></div>
            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($p['nom']) ?>" 
                 onerror="this.src='https://via.placeholder.com/300x200?text=Erreur+Image'">
            <div>Prix: <?= number_format($p['prix_ttc'], 2) ?> €</div>
            <div>Réf: <?= htmlspecialchars($p['reference']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <h2>📝 Session PHP</h2>
    <pre><?php print_r($_SESSION); ?></pre>

    <h2>🌐 Serveur</h2>
    <pre>
    PHP Version: <?= phpversion() ?>
    Document Root: <?= $_SERVER['DOCUMENT_ROOT'] ?>
    Script Name: <?= $_SERVER['SCRIPT_NAME'] ?>
    </pre>
</body>
</html>