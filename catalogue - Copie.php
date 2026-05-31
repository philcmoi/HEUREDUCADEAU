<?php
// catalogue.php - Version avec promotions intégrées (comme index.php)
// CORRIGÉ - Calcule explicitement le prix TTC
// Date: 2026-05-31

require_once 'session_verification.php';

$pdo = getPDOConnection();

// ==============================================
// FONCTIONS DE GESTION DES PROMOTIONS
// ==============================================

function getBestActivePromotionForProduct($pdo, $product_id) {
    $sql = "SELECT p.*, pp.reduction_personnalisee 
            FROM promotions p
            INNER JOIN promotions_produits pp ON p.id_promotion = pp.id_promotion
            WHERE pp.id_produit = :product_id
              AND p.actif = 1 
              AND p.date_debut <= NOW() 
              AND p.date_fin >= NOW()
            ORDER BY 
                CASE 
                    WHEN p.type_promotion = 'pourcentage' AND pp.reduction_personnalisee IS NOT NULL 
                        THEN pp.reduction_personnalisee
                    WHEN p.type_promotion = 'pourcentage' THEN p.valeur
                    WHEN p.type_promotion = 'montant_fixe' THEN 100
                    ELSE 0
                END DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $product_id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($promo) {
        return $promo;
    }
    
    $sql = "SELECT p.* 
            FROM promotions p
            INNER JOIN promotions_categories pc ON p.id_promotion = pc.id_promotion
            INNER JOIN produits pr ON pr.id_categorie = pc.id_categorie
            WHERE pr.id_produit = :product_id
              AND p.actif = 1 
              AND p.date_debut <= NOW() 
              AND p.date_fin >= NOW()
              AND p.type_promotion != 'livraison_gratuite'
            ORDER BY 
                CASE 
                    WHEN p.type_promotion = 'pourcentage' THEN p.valeur
                    WHEN p.type_promotion = 'montant_fixe' THEN 100
                    ELSE 0
                END DESC
            LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['product_id' => $product_id]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $promo ?: null;
}

function calculateDiscountedPrice($original_price, $promotion) {
    if (!$promotion) {
        return [
            'price' => $original_price,
            'original_price' => $original_price,
            'reduction_amount' => 0,
            'reduction_percent' => 0,
            'has_promotion' => false
        ];
    }
    
    $discounted_price = $original_price;
    $reduction_percent = 0;
    $reduction_amount = 0;
    
    $reduction_value = $promotion['reduction_personnalisee'] ?? $promotion['valeur'];
    
    if ($promotion['type_promotion'] == 'pourcentage') {
        $reduction_percent = floatval($reduction_value);
        $reduction_amount = $original_price * ($reduction_percent / 100);
        $discounted_price = $original_price - $reduction_amount;
        $reduction_amount = round($reduction_amount, 2);
        $discounted_price = round($discounted_price, 2);
    } elseif ($promotion['type_promotion'] == 'montant_fixe') {
        $reduction_amount = floatval($reduction_value);
        $discounted_price = max(0, $original_price - $reduction_amount);
        $reduction_percent = ($reduction_amount / $original_price) * 100;
        $discounted_price = round($discounted_price, 2);
    }
    
    return [
        'price' => $discounted_price,
        'original_price' => $original_price,
        'reduction_amount' => $reduction_amount,
        'reduction_percent' => round($reduction_percent),
        'has_promotion' => true,
        'type' => $promotion['type_promotion'],
        'code' => $promotion['code_promotion']
    ];
}

// ==============================================
// RÉCUPÉRATION DES PRODUITS AVEC PROMOTIONS
// ==============================================

// Requête pour récupérer les produits 31 et 42 avec prix_ht et tva
$sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
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

// Calculer le prix TTC et appliquer les promotions
foreach ($produits as &$p) {
    // Calcul du prix TTC à partir de HT et TVA
    $prix_ht = floatval($p['prix_ht']);
    $tva = floatval($p['tva']);
    $prix_ttc = round($prix_ht * (1 + $tva / 100), 2);
    $p['prix_ttc'] = $prix_ttc;
    
    // Récupérer la promotion
    $promo = getBestActivePromotionForProduct($pdo, $p['id_produit']);
    
    if ($promo) {
        $price_info = calculateDiscountedPrice($prix_ttc, $promo);
        $p['has_promotion'] = $price_info['has_promotion'];
        $p['reduction_percent'] = $price_info['reduction_percent'];
        $p['prix_promo'] = $price_info['price'];
        $p['prix_original'] = $price_info['original_price'];
        $p['reduction_amount'] = $price_info['reduction_amount'];
        $p['promo_code'] = $price_info['code'];
    } else {
        $p['has_promotion'] = false;
        $p['reduction_percent'] = 0;
        $p['prix_promo'] = $prix_ttc;
        $p['prix_original'] = $prix_ttc;
        $p['reduction_amount'] = 0;
        $p['promo_code'] = null;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catalogue - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        .header { background: #2c3e50; color: white; padding: 15px; margin-bottom: 20px; text-align: center; }
        .header h1 i { margin-right: 10px; }
        .products { display: flex; gap: 30px; justify-content: center; flex-wrap: wrap; }
        .product {
            background: white;
            border-radius: 10px;
            padding: 20px;
            width: 280px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            transition: transform 0.3s;
        }
        .product:hover { transform: translateY(-5px); }
        .product img { width: 100%; height: 200px; object-fit: cover; border-radius: 8px; background: #f0f0f0; }
        .product h2 { margin: 15px 0 10px; color: #333; }
        
        /* Styles pour les promotions */
        .discount-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 10;
        }
        .price-wrapper { display: flex; align-items: baseline; justify-content: center; gap: 12px; margin: 10px 0; flex-wrap: wrap; }
        .old-price { font-size: 1rem; color: #95a5a6; text-decoration: line-through; }
        .new-price { font-size: 1.6rem; font-weight: 800; color: #e74c3c; }
        .price { font-size: 1.6rem; font-weight: 800; color: #2c3e50; margin: 10px 0; }
        
        .stock { color: #27ae60; margin: 5px 0; }
        button {
            background: #27ae60;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.3s;
        }
        button:hover { background: #219653; }
        footer { text-align: center; margin-top: 40px; padding: 20px; color: #777; }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>
        
        <div class="header">
            <h1><i class="fas fa-gift"></i> HEURE DU CADEAU</h1>
            <p>Trouvez le cadeau parfait</p>
        </div>

        <div class="products">
            <?php foreach ($produits as $p): 
                $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/280x200/3498db/ffffff?text=' . urlencode($p['nom']);
                $has_promotion = $p['has_promotion'] ?? false;
                $reduction_percent = $p['reduction_percent'] ?? 0;
                $prix_original = $p['prix_original'] ?? $p['prix_ttc'];
                $prix_promo = $p['prix_promo'] ?? $prix_original;
                $prix_affiche = number_format($prix_promo, 2, ',', ' ');
                $prix_original_affiche = number_format($prix_original, 2, ',', ' ');
            ?>
            <div class="product">
                <?php if ($has_promotion && $reduction_percent > 0): ?>
                    <div class="discount-badge">-<?= round($reduction_percent) ?>%</div>
                <?php endif; ?>
                
                <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($p['nom']) ?>">
                <h2><?= htmlspecialchars($p['nom']) ?></h2>
                
                <?php if ($has_promotion && $prix_promo < $prix_original): ?>
                    <div class="price-wrapper">
                        <span class="old-price"><?= $prix_original_affiche ?> €</span>
                        <span class="new-price"><?= $prix_affiche ?> €</span>
                    </div>
                <?php else: ?>
                    <div class="price"><?= $prix_affiche ?> €</div>
                <?php endif; ?>
                
                <div class="stock">Stock : <?= $p['quantite_stock'] ?></div>
                <button onclick="alert('Fonctionnalité à venir')">
                    <i class="fas fa-cart-plus"></i> Ajouter au panier
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <footer>
            <p>&copy; 2025 HEURE DU CADEAU - Tous droits réservés</p>
        </footer>
    </div>
</body>
</html>