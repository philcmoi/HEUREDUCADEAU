<?php
// catalogue.php - Page catalogue avec gestion panier, promotions ET FILTRAGE PAR CATÉGORIE
// VERSION CORRIGÉE - Affichage des produits par catégorie
// Date: 2026-06-01

require_once 'session_verification.php';

// Récupération du filtre catégorie
$categorie_id = isset($_GET['categorie']) ? (int)$_GET['categorie'] : 0;
$categorie_nom = '';

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

// Récupérer le nom de la catégorie si un filtre est actif
if ($categorie_id > 0) {
    $stmt_cat = $pdo->prepare("SELECT nom FROM categories WHERE id_categorie = ? AND active = 1");
    $stmt_cat->execute([$categorie_id]);
    $categorie_nom = $stmt_cat->fetchColumn();
    if (!$categorie_nom) {
        $categorie_id = 0;
    }
}

// Construction de la requête SQL avec ou sans filtre
if ($categorie_id > 0) {
    $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif' 
            AND id_categorie = :categorie_id
            ORDER BY id_produit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['categorie_id' => $categorie_id]);
} else {
    $sql = "SELECT id_produit, nom, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif' 
            ORDER BY id_produit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
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

// Traitement des promotions
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

// Construction du tableau JS pour le panier
$produits_js = [];
foreach ($produits as $p) {
    $image_url = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=' . urlencode($p['nom']);
    
    $produits_js[$p['id_produit']] = [
        'id' => $p['id_produit'],
        'nom' => $p['nom'],
        'reference' => 'REF' . $p['id_produit'],
        'prix_ttc' => floatval($p['prix_promo']),
        'prix_original' => floatval($p['prix_original']),
        'reduction_percent' => $p['reduction_percent'],
        'has_promotion' => $p['has_promotion'],
        'description_courte' => '',
        'image' => $image_url,
        'quantite_stock' => intval($p['quantite_stock'])
    ];
}

$nb_articles = countCartItems();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Catalogue<?= $categorie_nom ? ' - ' . htmlspecialchars($categorie_nom) : '' ?> - HEURE DU CADEAU</title>
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
        .stock{
            display:inline-block;
            font-size:0.85rem;
            padding:5px 12px;
            border-radius:20px;
            margin:10px 0;
        }
        .stock.in-stock{
            background:#d4edda;
            color:#155724;
        }
        .stock.low-stock{
            background:#fff3cd;
            color:#856404;
        }
        .stock.out-of-stock{
            background:#f8d7da;
            color:#721c24;
        }
        button{
            background:#27ae60;
            color:#fff;
            border:none;
            padding:12px;
            border-radius:5px;
            cursor:pointer;
            width:100%;
            font-size:1rem;
            font-weight:600;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            transition:all 0.3s ease;
        }
        button:hover:not(:disabled){background:#219653;transform:translateY(-2px)}
        button:disabled{background:#95a5a6;cursor:not-allowed}
        button.loading{opacity:0.7;cursor:wait}
        button.loading i{animation:spin 1s linear infinite}
        @keyframes spin{
            0%{transform:rotate(0deg)}
            100%{transform:rotate(360deg)}
        }
        .back-link{display:inline-block;margin-bottom:20px;color:#3498db;text-decoration:none}
        .back-link:hover{text-decoration:underline}
        
        .category-filter-bar {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .category-filter-bar .current-category {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .category-filter-bar .current-category i {
            color: #e74c3c;
            margin-right: 5px;
        }
        
        .clear-filter {
            background: #f8f9fa;
            color: #2c3e50;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s;
        }
        
        .clear-filter:hover {
            background: #e74c3c;
            color: white;
        }
        
        /* Notification toast */
        .toast-notification{
            position:fixed;
            top:20px;
            right:20px;
            background:#27ae60;
            color:white;
            padding:15px 20px;
            border-radius:8px;
            box-shadow:0 5px 15px rgba(0,0,0,0.2);
            display:flex;
            align-items:center;
            gap:10px;
            z-index:1000;
            animation:slideInRight 0.3s ease;
            min-width:280px;
            max-width:400px;
        }
        .toast-notification.error{background:#e74c3c}
        .toast-notification.warning{background:#f39c12}
        .toast-notification i{font-size:1.2rem}
        @keyframes slideInRight{
            from{transform:translateX(100%);opacity:0}
            to{transform:translateX(0);opacity:1}
        }
        @keyframes slideOutRight{
            from{transform:translateX(0);opacity:1}
            to{transform:translateX(100%);opacity:0}
        }
        
        /* Animation compteur panier */
        .cart-count.pulse{animation:pulse 0.3s ease}
        @keyframes pulse{
            0%{transform:scale(1)}
            50%{transform:scale(1.2)}
            100%{transform:scale(1)}
        }
        
        /* Header avec panier */
        .main-header{
            background:#2c3e50;
            color:white;
            padding:15px 0;
            margin-bottom:20px;
        }
        .main-header .container{
            display:flex;
            justify-content:space-between;
            align-items:center;
            max-width:1200px;
            margin:0 auto;
            padding:0 20px;
        }
        .main-header .logo{
            color:white;
            text-decoration:none;
            font-size:1.5rem;
            font-weight:bold;
            display:flex;
            align-items:center;
            gap:10px;
        }
        .main-header .logo i{color:#e74c3c}
        .main-header nav{display:flex;gap:20px}
        .main-header nav a{
            color:white;
            text-decoration:none;
            padding:8px 12px;
            border-radius:6px;
            transition:background 0.3s;
        }
        .main-header nav a:hover{background:rgba(255,255,255,0.1)}
        .cart-link{
            display:flex;
            align-items:center;
            gap:8px;
            background:rgba(255,255,255,0.15);
            padding:8px 15px;
            border-radius:30px;
            text-decoration:none;
            color:white;
            position:relative;
        }
        .cart-link:hover{background:#e74c3c}
        .cart-count{
            background:#e74c3c;
            border-radius:50%;
            width:22px;
            height:22px;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            font-size:0.75rem;
            margin-left:5px;
        }
        
        /* Modal panier */
        .cart-modal{
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            z-index:2000;
            justify-content:center;
            align-items:center;
            padding:20px;
        }
        .cart-modal.show{display:flex}
        .cart-modal-content{
            background:white;
            border-radius:16px;
            width:90%;
            max-width:500px;
            max-height:90vh;
            overflow-y:auto;
            box-shadow:0 20px 40px rgba(0,0,0,0.2);
        }
        .cart-modal-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:20px;
            border-bottom:2px solid #f8f9fa;
            position:sticky;
            top:0;
            background:white;
        }
        .cart-modal-close{
            background:none;
            border:none;
            font-size:24px;
            cursor:pointer;
            width:40px;
            height:40px;
            border-radius:50%;
            transition:background 0.3s;
        }
        .cart-modal-close:hover{background:#f8f9fa;color:#e74c3c}
        .cart-modal-body{padding:20px}
        .cart-modal-product{
            display:flex;
            align-items:center;
            gap:20px;
            padding:20px;
        }
        .modal-product-image{
            width:100px;
            height:100px;
            border-radius:12px;
            overflow:hidden;
            background:#e9ecef;
        }
        .modal-product-image img{
            width:100%;
            height:100%;
            object-fit:cover;
        }
        .modal-product-info{flex:1}
        .modal-product-info h4{margin-bottom:10px;color:#2c3e50}
        .modal-product-price{font-weight:700;color:#e74c3c;font-size:1.2rem;margin:10px 0}
        .modal-success-message{color:#27ae60;display:flex;align-items:center;gap:8px}
        .cart-modal-footer{
            padding:20px;
            background:#f8f9fa;
            border-top:2px solid #e9ecef;
            display:flex;
            gap:12px;
        }
        .cart-modal-footer .btn{
            flex:1;
            padding:12px;
            border-radius:8px;
            text-align:center;
            text-decoration:none;
            border:none;
            cursor:pointer;
            font-weight:600;
        }
        .btn-primary{background:#27ae60;color:white}
        .btn-primary:hover{background:#219653}
        .btn-secondary{background:#3498db;color:white}
        .btn-secondary:hover{background:#2980b9}
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
        }
    </style>
</head>
<body>
    <div class="main-header">
        <div class="container">
            <a href="index.php" class="logo"><i class="fas fa-gift"></i> HEURE DU CADEAU</a>
            <nav>
                <a href="index.php">Accueil</a>
                <a href="catalogue.php" style="background:rgba(255,255,255,0.15)">Catalogue</a>
                <a href="apropos.html">À propos</a>
                <a href="contact.html">Contact</a>
            </nav>
            <a href="panier.html" class="cart-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Panier</span>
                <span class="cart-count" id="cartCount"><?= $nb_articles ?></span>
            </a>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Retour à l'accueil</a>
        
        <!-- Barre de filtre catégorie -->
        <div class="category-filter-bar">
            <div>
                <i class="fas fa-filter"></i> Filtrer par catégorie :
                <a href="catalogue.php" style="margin-left: 10px; <?= $categorie_id == 0 ? 'font-weight:bold; color:#e74c3c;' : '' ?>">Tous</a> |
                <a href="?categorie=2" <?= $categorie_id == 2 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Anniversaires</a> |
                <a href="?categorie=3" <?= $categorie_id == 3 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Saint-Valentin</a> |
                <a href="?categorie=4" <?= $categorie_id == 4 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Mariage</a> |
                <a href="?categorie=5" <?= $categorie_id == 5 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Naissance</a> |
                <a href="?categorie=6" <?= $categorie_id == 6 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Diplômés</a> |
                <a href="?categorie=7" <?= $categorie_id == 7 ? 'style="font-weight:bold; color:#e74c3c;"' : '' ?>>Noël</a>
            </div>
            <?php if ($categorie_id > 0 && $categorie_nom): ?>
                <div class="current-category">
                    <i class="fas fa-tag"></i> <?= htmlspecialchars($categorie_nom) ?>
                    <a href="catalogue.php" class="clear-filter"><i class="fas fa-times"></i> Effacer</a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="header">
            <h1><i class="fas fa-gift"></i> HEURE DU CADEAU</h1>
            <p><?= $categorie_nom ? "Cadeaux pour " . htmlspecialchars($categorie_nom) : "Trouvez le cadeau parfait" ?></p>
        </div>
        
        <?php if (empty($produits)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
                <h3>Aucun produit trouvé</h3>
                <p><?= $categorie_nom ? "Aucun produit n'est disponible dans la catégorie " . htmlspecialchars($categorie_nom) . " pour le moment." : "La boutique est actuellement vide." ?></p>
                <a href="catalogue.php" class="btn btn-primary" style="margin-top: 20px; display: inline-block; width: auto;"><i class="fas fa-arrow-left"></i> Voir tous les produits</a>
            </div>
        <?php else: ?>
            <div class="products">
                <?php foreach ($produits as $p): 
                    $image = isset($images[$p['id_produit']]) ? $images[$p['id_produit']] : 'https://via.placeholder.com/280x200/3498db/ffffff?text=' . urlencode($p['nom']);
                    $stock = $p['quantite_stock'];
                    $stock_class = $stock > 10 ? 'in-stock' : ($stock > 0 ? 'low-stock' : 'out-of-stock');
                    $stock_text = $stock > 10 ? 'En stock' : ($stock > 0 ? 'Stock faible : ' . $stock : 'Rupture de stock');
                ?>
                <div class="product" data-id="<?= $p['id_produit'] ?>">
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
                    <div class="stock <?= $stock_class ?>">
                        <i class="fas <?= $stock > 10 ? 'fa-check-circle' : ($stock > 0 ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                        <?= $stock_text ?>
                    </div>
                    <button class="btn-add-to-cart" 
                            data-id="<?= $p['id_produit'] ?>"
                            data-nom="<?= htmlspecialchars($p['nom']) ?>"
                            data-prix="<?= $p['prix_promo'] ?>"
                            data-image="<?= htmlspecialchars($image) ?>"
                            <?= $stock <= 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-cart-plus"></i> <?= $stock > 0 ? 'Ajouter au panier' : 'Indisponible' ?>
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal panier -->
    <div class="cart-modal" id="cartModal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h3><i class="fas fa-check-circle" style="color:#27ae60"></i> Article ajouté</h3>
                <button class="cart-modal-close" id="closeCartModal">&times;</button>
            </div>
            <div class="cart-modal-body" id="cartModalBody"></div>
            <div class="cart-modal-footer">
                <a href="panier.html" class="btn btn-primary"><i class="fas fa-shopping-cart"></i> Voir le panier</a>
                <button class="btn btn-secondary" id="continueShopping"><i class="fas fa-arrow-left"></i> Continuer</button>
            </div>
        </div>
    </div>

    <script>
        // Données des produits pour l'API panier
        const produitsData = <?= json_encode($produits_js ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?>;
        const API_PANIER_URL = "panier.php";

        // Gestionnaire de panier
        class PanierManager {
            constructor() {
                this.apiUrl = API_PANIER_URL;
                this.cartModal = document.getElementById("cartModal");
                this.cartModalBody = document.getElementById("cartModalBody");
                this.cartCountElements = document.querySelectorAll(".cart-count");
                this.updateInProgress = false;
                this.produitsData = produitsData;
                this.initEvents();
                this.updateCartCount();
            }

            initEvents() {
                // Fermeture modal
                document.getElementById("closeCartModal")?.addEventListener("click", () => this.closeModal());
                document.getElementById("continueShopping")?.addEventListener("click", () => this.closeModal());
                this.cartModal?.addEventListener("click", (e) => { if (e.target === this.cartModal) this.closeModal(); });
                
                // Clic sur boutons "Ajouter au panier"
                document.addEventListener("click", async (e) => {
                    const addToCartBtn = e.target.closest(".btn-add-to-cart");
                    if (addToCartBtn && !addToCartBtn.disabled && !addToCartBtn.classList.contains("loading")) {
                        e.preventDefault();
                        e.stopPropagation();
                        const id_produit = addToCartBtn.dataset.id ? parseInt(addToCartBtn.dataset.id) : null;
                        if (id_produit) await this.ajouterAuPanier(id_produit, 1, addToCartBtn);
                    }
                });
            }

            closeModal() {
                this.cartModal?.classList.remove("show");
            }

            async ajouterAuPanier(id_produit, quantite = 1, button = null) {
                if (!id_produit || id_produit <= 0) {
                    this.showNotification("Erreur: Produit invalide", "error");
                    return false;
                }

                // Vérifier le stock
                const produitInfo = this.produitsData[id_produit];
                if (produitInfo && produitInfo.quantite_stock <= 0) {
                    this.showNotification("Produit en rupture de stock", "error");
                    return false;
                }

                // Récupérer les infos du produit
                let finalInfo = produitInfo;
                
                if (!produitInfo && button) {
                    finalInfo = {
                        id: id_produit,
                        nom: button.dataset.nom || 'Produit',
                        reference: 'REF' + id_produit,
                        prix_ttc: parseFloat(button.dataset.prix) || 0,
                        image: button.dataset.image || 'https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'
                    };
                }
                
                if (!finalInfo) {
                    this.showNotification("Erreur: Produit non trouvé", "error");
                    return false;
                }

                // Désactiver le bouton pendant l'ajout
                let originalHTML = "", originalDisabled = false;
                if (button) {
                    originalHTML = button.innerHTML;
                    originalDisabled = button.disabled;
                    button.disabled = true;
                    button.classList.add("loading");
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
                }

                try {
                    const response = await fetch(this.apiUrl, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ 
                            action: "ajouter", 
                            id_produit: parseInt(id_produit), 
                            quantite: parseInt(quantite) 
                        })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await this.updateCartCount();
                        this.showCartModal(finalInfo);
                        this.showNotification(`"${finalInfo.nom}" ajouté au panier !`);
                        return true;
                    } else {
                        this.showNotification(data.message || "Erreur lors de l'ajout", "error");
                        return false;
                    }
                } catch (error) {
                    console.error("Erreur ajout panier:", error);
                    this.showNotification("Erreur de connexion au serveur", "error");
                    return false;
                } finally {
                    if (button) {
                        setTimeout(() => {
                            button.disabled = originalDisabled;
                            button.innerHTML = originalHTML;
                            button.classList.remove("loading");
                        }, 800);
                    }
                }
            }

            showCartModal(product) {
                if (!product || !this.cartModalBody) return;
                
                const prix = product.prix_ttc ? parseFloat(product.prix_ttc).toFixed(2).replace(".", ",") : "0,00";
                
                this.cartModalBody.innerHTML = `
                    <div class="cart-modal-product">
                        <div class="modal-product-image">
                            <img src="${product.image}" alt="${product.nom}" 
                                 onerror="this.src='https://via.placeholder.com/300x300/95a5a6/ffffff?text=Produit'">
                        </div>
                        <div class="modal-product-info">
                            <h4>${this.escapeHtml(product.nom)}</h4>
                            <p class="modal-product-ref">Réf: ${product.reference || 'REF' + product.id}</p>
                            <p class="modal-product-price">${prix} €</p>
                            <p class="modal-success-message">
                                <i class="fas fa-check-circle"></i> Article ajouté avec succès !
                            </p>
                        </div>
                    </div>
                `;
                this.cartModal.classList.add("show");
            }

            async updateCartCount() {
                if (this.updateInProgress) return;
                this.updateInProgress = true;
                
                try {
                    const response = await fetch(`${this.apiUrl}?action=compter&_=${Date.now()}`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            this.updateCartCountDisplay(data.total || 0);
                            return data.total || 0;
                        }
                    }
                    this.updateCartCountDisplay(0);
                    return 0;
                } catch (error) {
                    console.error("Erreur mise à jour compteur:", error);
                    this.updateCartCountDisplay(0);
                    return 0;
                } finally {
                    this.updateInProgress = false;
                }
            }

            updateCartCountDisplay(count) {
                this.cartCountElements.forEach((element) => {
                    if (count > 0) {
                        element.textContent = count > 99 ? "99+" : count;
                        element.style.display = "inline-flex";
                        element.classList.add("pulse");
                        setTimeout(() => element.classList.remove("pulse"), 600);
                    } else {
                        element.textContent = "0";
                        element.style.display = "inline-flex";
                    }
                });
            }

            showNotification(message, type = "success") {
                // Supprimer les notifications existantes
                document.querySelectorAll(".toast-notification").forEach(toast => toast.remove());
                
                const notification = document.createElement("div");
                notification.className = `toast-notification ${type}`;
                
                let icon = "check-circle";
                if (type === "error") icon = "exclamation-triangle";
                else if (type === "warning") icon = "info-circle";
                
                notification.innerHTML = `<i class="fas fa-${icon}"></i><span>${message}</span>`;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = "slideOutRight 0.3s ease";
                    setTimeout(() => notification.remove(), 300);
                }, 3000);
            }

            escapeHtml(text) {
                const div = document.createElement("div");
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialisation
        document.addEventListener("DOMContentLoaded", function() {
            window.panierManager = new PanierManager();
        });
    </script>
</body>
</html>