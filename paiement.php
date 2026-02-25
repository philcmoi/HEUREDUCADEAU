<?php
// ============================================
// PAGE DE PAIEMENT - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// ============================================
// DÉTECTION DU TYPE DE REQUÊTE (API ou HTML)
// ============================================
$is_api_request = false;
if (isset($_GET['action']) || 
    ($_SERVER['REQUEST_METHOD'] === 'POST' && 
     strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)) {
    $is_api_request = true;
}

// ============================================
// CONNEXION BDD
// ============================================
$pdo = getPDOConnection();

// Synchroniser le panier
if ($pdo) {
    synchroniserPanierSessionBDD($pdo, session_id());
}

// ============================================
// TRAITEMENT DES ACTIONS API
// ============================================
if ($is_api_request) {
    ob_clean();
    header("Content-Type: application/json; charset=UTF-8");
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'redirect_paypal':
            $montant = $_GET['montant'] ?? 0;
            $commande_id = $_GET['commande'] ?? 0;
            
            // Nettoyer tout ancien flag PayPal
            unset($_SESSION['paypal_processing']);
            
            echo json_encode([
                'success' => true,
                'redirect_url' => "paiement_paypal.php?commande=$commande_id&montant=$montant&from=paiement&t=" . time()
            ]);
            break;
            
        case 'redirect_cb':
            $montant = $_GET['montant'] ?? 0;
            $commande_id = $_GET['commande'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'redirect_url' => "paiement_cb.php?commande=$commande_id&montant=$montant&from=paiement&t=" . time()
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Action API non reconnue']);
    }
    exit;
}

// ============================================
// VÉRIFICATION D'ACCÈS STANDARDISÉE
// ============================================
checkPaiementAccess();

// Nettoyer tout ancien flag PayPal avant d'afficher la page
unset($_SESSION['paypal_processing']);

// ============================================
// RÉCUPÉRATION DES DONNÉES POUR AFFICHAGE
// ============================================
$messages = getSessionMessages();
$nb_articles = countCartItems();

// Récupérer les détails du panier
$panier_details = [];
foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
    $produit = getProductDetails($item['id_produit'], $pdo);
    $prix_unitaire = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
    $quantite = intval($item['quantite']);
    $panier_details[] = [
        'id_produit' => $item['id_produit'],
        'quantite' => $quantite,
        'nom' => $produit['nom'] ?? 'Produit',
        'prix_unitaire' => $prix_unitaire,
        'prix_total' => $quantite * $prix_unitaire,
        'reference' => $produit['reference'] ?? '',
        'image' => $produit['image'] ?? 'img/default-product.jpg'
    ];
}

$totaux = calculerTotauxPanier($panier_details, $_SESSION[SESSION_KEY_CHECKOUT] ?? []);
$adresse = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];

// Le reste du HTML reste identique...
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tous les styles identiques à l'original */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo {
            color: white;
            text-decoration: none;
            font-size: 1.8rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo i { color: #e74c3c; }
        nav { display: flex; gap: 25px; align-items: center; }
        nav a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 12px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        nav a:hover { background: rgba(255,255,255,0.1); }
        .cart-link { position: relative; }
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .paiement-page { padding: 40px 0; min-height: 70vh; }
        .paiement-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .paiement-header h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .paiement-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        @media (max-width: 992px) {
            .paiement-content { grid-template-columns: 1fr; }
        }
        .paiement-form {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .section-title {
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .adresse-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border-left: 5px solid #3498db;
        }
        .paiement-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px;
        }
        .paiement-option {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .paiement-option:hover { border-color: #3498db; }
        .paiement-option.selected {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.05);
        }
        .option-header {
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
        }
        .btn-payer {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn-payer:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(39,174,96,0.3);
        }
        .btn-payer:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .cart-summary {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: sticky;
            top: 100px;
        }
        .cart-item-mini {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .cart-item-mini:last-child { border-bottom: none; }
        .mini-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
        }
        .mini-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .mini-details {
            flex: 1;
        }
        .mini-details h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        .mini-price {
            font-weight: 700;
            color: #e74c3c;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .summary-total {
            font-size: 1.3rem;
            font-weight: 800;
            color: #e74c3c;
            border-top: 2px solid #f0f0f0;
            padding-top: 20px;
            margin-top: 10px;
        }
        .btn-continue {
            display: block;
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            margin-top: 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52,152,219,0.3);
        }
        .loading {
            text-align: center;
            padding: 40px;
        }
        .loading i { font-size: 3rem; color: #3498db; }
        .notification {
            position: fixed;
            top: 30px;
            right: 30px;
            background: #27ae60;
            color: white;
            padding: 18px 25px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideInRight 0.5s, fadeOut 0.5s 2.5s forwards;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            to { opacity: 0; transform: translateX(100%); }
        }
        .notification.error { background: #e74c3c; }
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 5px solid #3498db;
        }
        .info-box i { color: #3498db; margin-right: 10px; }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="index.html" class="logo"><i class="fas fa-gift"></i> HEURE DU CADEAU</a>
                <nav>
                    <a href="index.html">Accueil</a>
                    <a href="index.php">Produits</a>
                    <a href="panier.html" class="cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <span id="cartCount" class="cart-count"><?= $nb_articles ?></span>
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <main class="paiement-page">
        <div class="container">
            <div class="paiement-header">
                <h1>Paiement sécurisé</h1>
                <p id="pageMessage">Finalisez votre commande</p>
            </div>

            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo $msg['type']; ?>">
                        <?php echo htmlspecialchars($msg['message']); ?>
                    </div>
                <?php endforeach; ?>
                <?php clearSessionMessages(); ?>
            <?php endif; ?>

            <div id="paiementContent">
                <div class="loading">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Chargement de votre panier...</p>
                </div>
            </div>
        </div>
    </main>

    <footer style="background: #2c3e50; color: white; padding: 30px 0; margin-top: 60px;">
        <div class="container">
            <p style="text-align: center;">&copy; 2026 HEURE DU CADEAU - Paiement 100% sécurisé</p>
        </div>
    </footer>

    <script>
    // ============================================
    // JAVASCRIPT COMPLET - GESTIONNAIRE DE PAIEMENT
    // ============================================
    const API_BASE_URL = window.location.href.split('?')[0];
    let paiementManager = null;

    class PaiementManager {
        constructor() {
            this.apiUrl = API_BASE_URL;
            this.panierData = null;
            this.totaux = <?= json_encode($totaux) ?>;
            this.adresse = <?= json_encode($adresse) ?>;
            this.panierDetails = <?= json_encode($panier_details) ?>;
            this.paiementMethod = "paypal";
            this.init();
        }

        init() {
            console.log("Initialisation page paiement...");
            this.afficherPaiement();
            this.initEvents();
        }

        afficherPaiement() {
            let html = `<div class="paiement-content">`;
            
            html += `<div class="paiement-form">`;
            html += `<h2 class="section-title"><i class="fas fa-credit-card"></i> Mode de paiement</h2>`;

            if (this.adresse && Object.keys(this.adresse).length > 0) {
                html += `<div class="adresse-card">`;
                html += `<h3 style="margin-bottom: 10px;"><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h3>`;
                html += `<p><strong>${this.adresse.prenom || ''} ${this.adresse.nom || ''}</strong></p>`;
                html += `<p>${this.adresse.adresse || ''}</p>`;
                if (this.adresse.complement) html += `<p>${this.adresse.complement}</p>`;
                html += `<p>${this.adresse.code_postal || ''} ${this.adresse.ville || ''}</p>`;
                html += `<p>${this.adresse.pays || 'France'}</p>`;
                html += `<p style="margin-top: 10px;"><i class="fas fa-envelope"></i> ${this.adresse.email || ''}</p>`;
                if (this.adresse.telephone) html += `<p><i class="fas fa-phone"></i> ${this.adresse.telephone}</p>`;
                
                html += `<a href="livraison_form.php" style="color: #3498db; margin-top: 10px; display: inline-block;"><i class="fas fa-edit"></i> Modifier</a>`;
                html += `</div>`;
            } else {
                html += `<div class="adresse-card" style="border-left-color: #e74c3c;">`;
                html += `<p><i class="fas fa-exclamation-triangle"></i> <strong>Aucune adresse de livraison</strong></p>`;
                html += `<a href="livraison_form.php" class="btn-continue" style="margin-top: 10px;">Ajouter une adresse</a>`;
                html += `</div>`;
            }

            html += `<div class="paiement-options">`;
            html += `<div class="paiement-option selected" id="optionPaypal">`;
            html += `<div class="option-header">`;
            html += `<input type="radio" name="paiement" id="paypal" value="paypal" checked hidden>`;
            html += `<img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal" style="height: 24px;">`;
            html += `<span>PayPal</span>`;
            html += `</div>`;
            html += `</div>`;

            html += `<div class="paiement-option" id="optionCarte">`;
            html += `<div class="option-header">`;
            html += `<input type="radio" name="paiement" id="carte" value="carte" hidden>`;
            html += `<i class="fas fa-credit-card" style="font-size: 24px; color: #718096;"></i>`;
            html += `<span>Carte bancaire (Visa, Mastercard, Amex)</span>`;
            html += `</div>`;
            html += `</div>`;
            html += `</div>`;

            html += `<button id="btnPayer" class="btn-payer">`;
            html += `<i class="fas fa-lock"></i> Payer ${(this.totaux?.total || 0).toFixed(2).replace('.', ',')} €`;
            html += `</button>`;

            html += `<p style="text-align: center; margin-top: 20px; color: #7f8c8d; font-size: 0.9rem;">`;
            html += `<i class="fas fa-shield-alt"></i> Paiement 100% sécurisé - Cryptage SSL`;
            html += `</p>`;
            html += `</div>`;

            html += `<div class="cart-summary">`;
            html += `<h2 style="color: #2c3e50; margin-bottom: 25px; font-size: 1.8rem;">Votre panier</h2>`;

            this.panierDetails.forEach(item => {
                const prixTotal = (item.prix_total || 0).toFixed(2).replace('.', ',');
                html += `<div class="cart-item-mini">`;
                html += `<div class="mini-image">`;
                html += `<img src="${item.image || 'img/default-product.jpg'}" alt="${item.nom}" onerror="this.src='img/default-product.jpg'">`;
                html += `</div>`;
                html += `<div class="mini-details">`;
                html += `<h4>${item.nom}</h4>`;
                html += `<div style="font-size: 0.9rem; color: #7f8c8d;">Réf: ${item.reference || ''}</div>`;
                html += `<div style="display: flex; justify-content: space-between; margin-top: 5px;">`;
                html += `<span>${item.quantite} x ${(item.prix_unitaire || 0).toFixed(2).replace('.', ',')} €</span>`;
                html += `<span class="mini-price">${prixTotal} €</span>`;
                html += `</div>`;
                html += `</div>`;
                html += `</div>`;
            });

            html += `<div style="margin-top: 25px;">`;
            html += `<div class="summary-row">`;
            html += `<span>Sous-total (${this.totaux?.total_items || 0} article${this.totaux?.total_items > 1 ? 's' : ''})</span>`;
            html += `<span>${(this.totaux?.sous_total || 0).toFixed(2).replace('.', ',')} €</span>`;
            html += `</div>`;

            html += `<div class="summary-row">`;
            html += `<span>Frais de livraison</span>`;
            html += `<span>${(this.totaux?.frais_livraison || 0).toFixed(2).replace('.', ',')} €</span>`;
            html += `</div>`;

            if (this.totaux?.frais_emballage > 0) {
                html += `<div class="summary-row">`;
                html += `<span>Emballage cadeau</span>`;
                html += `<span>${(this.totaux?.frais_emballage || 0).toFixed(2).replace('.', ',')} €</span>`;
                html += `</div>`;
            }

            html += `<div class="summary-row summary-total">`;
            html += `<span>Total TTC</span>`;
            html += `<span>${(this.totaux?.total || 0).toFixed(2).replace('.', ',')} €</span>`;
            html += `</div>`;
            html += `</div>`;

            if (this.totaux?.sous_total < this.totaux?.seuil_livraison_gratuite) {
                const reste = (this.totaux.seuil_livraison_gratuite - this.totaux.sous_total).toFixed(2).replace('.', ',');
                html += `<div class="info-box">`;
                html += `<i class="fas fa-truck"></i> Plus que <strong>${reste} €</strong> pour la livraison gratuite !`;
                html += `</div>`;
            } else if (this.totaux?.frais_livraison == 0) {
                html += `<div class="info-box" style="border-left-color: #27ae60; background: #d4edda;">`;
                html += `<i class="fas fa-check-circle" style="color: #27ae60;"></i> <strong>Livraison gratuite</strong> !`;
                html += `</div>`;
            }

            html += `<a href="panier.html" class="btn-continue"><i class="fas fa-arrow-left"></i> Modifier le panier</a>`;
            html += `</div>`;
            html += `</div>`;

            document.getElementById("paiementContent").innerHTML = html;
            this.initPaiementEvents();
        }

        initPaiementEvents() {
            document.querySelectorAll('.paiement-option').forEach(opt => {
                opt.addEventListener('click', (e) => {
                    document.querySelectorAll('.paiement-option').forEach(o => o.classList.remove('selected'));
                    opt.classList.add('selected');
                    const radio = opt.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                        this.paiementMethod = radio.value;
                    }
                });
            });

            const btnPayer = document.getElementById('btnPayer');
            if (btnPayer) {
                btnPayer.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.procederPaiement();
                });
            }
        }

        procederPaiement() {
            if (!this.adresse || Object.keys(this.adresse).length === 0) {
                this.showNotification("Veuillez renseigner une adresse de livraison", "error");
                return;
            }
            
            const btn = document.getElementById('btnPayer');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirection...';
            btn.disabled = true;

            if (this.paiementMethod === 'paypal') {
                // Ajouter un timestamp pour éviter le cache
                const url = `${this.apiUrl}?action=redirect_paypal&commande=1&montant=${this.totaux.total}&_=${Date.now()}`;
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.redirect_url) {
                            // Redirection vers PayPal
                            window.location.href = data.redirect_url;
                        } else {
                            // Fallback
                            window.location.href = `paiement_paypal.php?commande=1&montant=${this.totaux.total}&from=paiement&t=${Date.now()}`;
                        }
                    })
                    .catch(error => {
                        console.error("Erreur redirection:", error);
                        // Fallback en cas d'erreur
                        window.location.href = `paiement_paypal.php?commande=1&montant=${this.totaux.total}&from=paiement&t=${Date.now()}`;
                    });
            } else {
                // Paiement par carte
                fetch(`${this.apiUrl}?action=redirect_cb&commande=1&montant=${this.totaux.total}&_=${Date.now()}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            window.location.href = `paiement_cb.php?commande=1&montant=${this.totaux.total}&from=paiement&t=${Date.now()}`;
                        }
                    })
                    .catch(error => {
                        console.error("Erreur redirection:", error);
                        window.location.href = `paiement_cb.php?commande=1&montant=${this.totaux.total}&from=paiement&t=${Date.now()}`;
                    });
            }
        }

        initEvents() {
            setInterval(() => this.updateCartCounter(), 30000);
        }

        async updateCartCounter() {
            try {
                const response = await fetch('panier.php?action=compter&_=' + Date.now());
                const data = await response.json();
                if (data.success) {
                    const counter = document.getElementById('cartCount');
                    if (counter) {
                        counter.textContent = data.total > 99 ? '99+' : data.total;
                    }
                }
            } catch (e) { }
        }

        showNotification(message, type = 'success') {
            document.querySelectorAll('.notification').forEach(n => n.remove());
            const notif = document.createElement('div');
            notif.className = `notification ${type}`;
            notif.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> <span>${message}</span>`;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        paiementManager = new PaiementManager();
    });
    </script>
</body>
</html>