<?php
require_once 'session_verification.php';

$pdo = getPDOConnection();

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

$sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
        FROM produits 
        WHERE statut = 'actif' 
        AND id_produit IN (31, 42)
        ORDER BY id_produit";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dédoublonnage
$produits_temp = [];
foreach ($resultats as $row) {
    $produits_temp[$row['id_produit']] = $row;
}
$produits = array_values($produits_temp);

// Récupération des images
$images = [];
if (!empty($produits)) {
    $ids = array_column($produits, 'id_produit');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt_img = $pdo->prepare("SELECT id_produit, url_image FROM images_produits WHERE id_produit IN ($placeholders) AND principale = 1");
    $stmt_img->execute($ids);
    while ($img = $stmt_img->fetch(PDO::FETCH_ASSOC)) {
        $images[$img['id_produit']] = $img['url_image'];
    }
}

// Traitement des promotions - SANS utilisation de références (&)
$produits_final = [];
foreach ($produits as $p) {
    $prix_ttc = round($p['prix_ht'] * (1 + $p['tva'] / 100), 2);
    $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
    
    $p['prix_ttc'] = $prix_ttc;
    
    if ($promo) {
        $p['has_promotion'] = true;
        $p['reduction_percent'] = $promo['valeur'];
        $p['prix_promo'] = round($prix_ttc * (1 - $promo['valeur'] / 100), 2);
        $p['prix_original'] = $prix_ttc;
    } else {
        $p['has_promotion'] = false;
        $p['reduction_percent'] = 0;
        $p['prix_promo'] = $prix_ttc;
        $p['prix_original'] = $prix_ttc;
    }
    
    $produits_final[] = $p;
}
$produits = $produits_final;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catalogue - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px}
        .container{max-width:1000px;margin:0 auto}
        .header{background:#2c3e50;color:#fff;padding:15px;margin-bottom:20px;text-align:center}
        .products{display:flex;gap:30px;justify-content:center;flex-wrap:wrap}
        .product{background:#fff;border-radius:10px;padding:20px;width:280px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,0.1);position:relative}
        .product img{width:100%;height:200px;object-fit:cover;border-radius:8px}
        .product h2{margin:15px 0 10px;color:#333}
        .discount-badge{position:absolute;top:15px;right:15px;background:#e74c3c;color:#fff;padding:5px 12px;border-radius:20px;font-size:0.8rem;font-weight:bold}
        .price-wrapper{margin:10px 0}
        .old-price{font-size:1rem;color:#999;text-decoration:line-through;margin-right:10px}
        .new-price{font-size:1.6rem;font-weight:bold;color:#e74c3c}
        .price{font-size:1.6rem;font-weight:bold;color:#2c3e50;margin:10px 0}
        .stock{color:#27ae60;margin:5px 0}
        button{background:#27ae60;color:#fff;border:none;padding:12px;border-radius:5px;cursor:pointer;width:100%}
        button:hover{background:#219653}
        .back-link{display:inline-block;margin-bottom:20px;color:#3498db;text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour</a>
    <div class="header"><h1><i class="fas fa-gift"></i> HEURE DU CADEAU</h1><p>Trouvez le cadeau parfait</p></div>
    <div class="products">
        <?php foreach ($produits as $p): 
            $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/280x200/3498db/ffffff?text=' . urlencode($p['nom']);
        ?>
        <div class="product">
            <?php if ($p['has_promotion']): ?>
                <div class="discount-badge">-<?= round($p['reduction_percent']) ?>%</div>
            <?php endif; ?>
            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
            <h2><?= htmlspecialchars($p['nom']) ?></h2>
            <?php if ($p['has_promotion']): ?>
                <div class="price-wrapper">
                    <span class="old-price"><?= number_format($p['prix_original'], 2) ?> €</span>
                    <span class="new-price"><?= number_format($p['prix_promo'], 2) ?> €</span>
                </div>
            <?php else: ?>
                <div class="price"><?= number_format($p['prix_original'], 2) ?> €</div>
            <?php endif; ?>
            <div class="stock">Stock : <?= $p['quantite_stock'] ?></div>
            <button onclick="alert('Fonctionnalité à venir')"><i class="fas fa-cart-plus"></i> Ajouter</button>
        </div>
        <?php endforeach; ?>
    </div>
</div>
</body>
</html>