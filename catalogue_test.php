<?php
// catalogue_test.php - Version ultra simple pour diagnostiquer
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'session_verification.php';

$pdo = getPDOConnection();

function getBestActivePromotionForProduct($pdo, $product_id) {
    try {
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
    } catch (Exception $e) {
        echo "Erreur dans getBestActivePromotionForProduct: " . $e->getMessage() . "<br>";
        return null;
    }
}

try {
    echo "=== DÉBUT DU TRAITEMENT ===<br>";
    
    $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif' 
            AND id_produit IN (31, 42)
            ORDER BY id_produit";
    
    echo "1. Exécution de la requête...<br>";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   Résultats trouvés: " . count($resultats) . "<br>";
    
    echo "2. Dédoublonnage...<br>";
    $produits_temp = [];
    foreach ($resultats as $row) {
        $produits_temp[$row['id_produit']] = $row;
    }
    $produits = array_values($produits_temp);
    echo "   Produits après dédoublonnage: " . count($produits) . "<br>";
    
    echo "3. Récupération des images...<br>";
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
    echo "   Images trouvées: " . count($images) . "<br>";
    
    echo "4. Traitement des promotions...<br>";
    foreach ($produits as &$p) {
        $prix_ttc = round($p['prix_ht'] * (1 + $p['tva'] / 100), 2);
        $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
        
        if ($promo) {
            $p['has_promotion'] = true;
            $p['reduction_percent'] = $promo['valeur'];
            $p['prix_promo'] = round($prix_ttc * (1 - $promo['valeur'] / 100), 2);
            $p['prix_original'] = $prix_ttc;
            echo "   - Produit {$p['nom']}: promotion de {$promo['valeur']}%<br>";
        } else {
            $p['has_promotion'] = false;
            $p['reduction_percent'] = 0;
            $p['prix_promo'] = $prix_ttc;
            $p['prix_original'] = $prix_ttc;
            echo "   - Produit {$p['nom']}: pas de promotion<br>";
        }
    }
    
    echo "5. Affichage du HTML...<br>";
    echo "=== FIN DU TRAITEMENT PHP ===<br><br>";
    
} catch (Exception $e) {
    die("<span style='color:red'>ERREUR: " . $e->getMessage() . "</span>");
}

// Maintenant afficher le HTML
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catalogue - TEST</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <h1>CATALOGUE - VERSION TEST</h1>
    <div style="background:#e8f4f8; padding:15px; margin:20px 0;">
        <strong>Debug:</strong> <?= count($produits) ?> produit(s) trouvé(s)
    </div>
    
    <?php if (empty($produits)): ?>
        <p style="color:red">Aucun produit trouvé !</p>
    <?php else: ?>
        <div style="display:flex; gap:30px;">
            <?php foreach ($produits as $p): 
                $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/280x200/3498db/ffffff?text=' . urlencode($p['nom']);
            ?>
            <div style="border:1px solid #ddd; border-radius:10px; padding:20px; width:280px;">
                <?php if ($p['has_promotion']): ?>
                    <div style="background:#e74c3c; color:white; padding:5px; border-radius:5px;">-<?= round($p['reduction_percent']) ?>%</div>
                <?php endif; ?>
                <img src="<?= htmlspecialchars($image) ?>" style="width:100%; height:200px; object-fit:cover;">
                <h2><?= htmlspecialchars($p['nom']) ?></h2>
                <?php if ($p['has_promotion']): ?>
                    <div>
                        <span style="text-decoration:line-through;"><?= number_format($p['prix_original'], 2) ?> €</span>
                        <span style="color:#e74c3c; font-size:1.5rem;"><?= number_format($p['prix_promo'], 2) ?> €</span>
                    </div>
                <?php else: ?>
                    <div style="font-size:1.5rem;"><?= number_format($p['prix_original'], 2) ?> €</div>
                <?php endif; ?>
                <div>Stock: <?= $p['quantite_stock'] ?></div>
                <button style="background:#27ae60; color:white; padding:10px; width:100%;">Ajouter</button>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</body>
</html>