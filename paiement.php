<?php
// paiement.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// PROTECTION D'ACCÈS - VÉRIFIER L'ÉTAPE DE LIVRAISON
// ============================================

// Vérifier si l'utilisateur a complété l'étape de livraison
if (!isset($_SESSION['livraison_complete']) || !$_SESSION['livraison_complete']) {
    // Vérifier si on a des données en base de données comme fallback
    $has_livraison_data = false;
    
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $session_id = session_id();
        $panier_id = $_SESSION['panier_id'] ?? null;
        
        if ($panier_id) {
            $stmt = $pdo->prepare("
                SELECT donnees_livraison 
                FROM commande_temporaire 
                WHERE panier_id = ? 
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$panier_id]);
            $temp_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($temp_data && !empty($temp_data['donnees_livraison'])) {
                $livraison_data = json_decode($temp_data['donnees_livraison'], true);
                
                if ($livraison_data) {
                    // Récupérer les données depuis la base
                    $_SESSION['adresse_livraison'] = $livraison_data['livraison'] ?? [];
                    $_SESSION['adresse_facturation'] = $livraison_data['facturation'] ?? [];
                    $_SESSION['options_livraison'] = $livraison_data['options'] ?? [];
                    $_SESSION['livraison_complete'] = true;
                    $has_livraison_data = true;
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur vérification livraison: " . $e->getMessage());
    }
    
    // Si pas de données de livraison, rediriger
    if (!$has_livraison_data) {
        header('Location: livraison_form.php');
        exit();
    }
}

// Vérifier aussi que le panier est valide
if (!isset($_SESSION['checkout_authorized']) || !$_SESSION['checkout_authorized']) {
    header('Location: panier.php');
    exit();
}

// ============================================
// RÉCUPÉRATION DES DONNÉES DE LIVRAISON
// ============================================

// Données depuis la session
$adresse_livraison = $_SESSION['adresse_livraison'] ?? [];
$adresse_facturation = $_SESSION['adresse_facturation'] ?? [];
$options_livraison = $_SESSION['options_livraison'] ?? [];
$frais_livraison = $_SESSION['frais_livraison'] ?? 0;

// Récupérer les articles du panier avec détails
$articles_panier = [];
$total_panier = 0;

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
        "Philippe",
        "l@99339R"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $panier_id = $_SESSION['panier_id'] ?? null;
    
    if ($panier_id) {
        // Récupérer les articles du panier avec les détails des produits
        $stmt = $pdo->prepare("
            SELECT pi.*, p.nom, p.prix, p.image, p.description_courte 
            FROM panier_items pi 
            JOIN produits p ON pi.id_produit = p.id_produit 
            WHERE pi.id_panier = ?
        ");
        $stmt->execute([$panier_id]);
        $articles_panier = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le total
        foreach ($articles_panier as $item) {
            $total_panier += ($item['prix'] * $item['quantite']);
        }
    }
} catch (Exception $e) {
    error_log("Erreur récupération panier: " . $e->getMessage());
    
    // Fallback sur la session si la BDD échoue
    if (isset($_SESSION['panier_items'])) {
        $articles_panier = $_SESSION['panier_items'];
        foreach ($_SESSION['panier_items'] as $item) {
            $total_panier += ($item['prix'] * $item['quantite']);
        }
    }
}

$total_commande = $total_panier + $frais_livraison;

// ============================================
// TRAITEMENT DU PAIEMENT
// ============================================

$errors = [];
$success = false;
$payment_method = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Déterminer la méthode de paiement
    if (isset($_POST['process_card'])) {
        $payment_method = 'carte';
        
        // Valider les données de carte
        $required_payment = ['card_number', 'card_expiry', 'card_cvc', 'card_name'];
        
        foreach ($required_payment as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Le champ $field est requis pour le paiement par carte.";
            }
        }
    } elseif (isset($_POST['process_paypal'])) {
        $payment_method = 'paypal';
        // Pour PayPal, on va simplement créer la commande en attente
    } else {
        $errors[] = "Veuillez sélectionner une méthode de paiement.";
    }
    
    if (empty($errors)) {
        // Sauvegarder la commande finale
        try {
            if (!isset($pdo)) {
                $pdo = new PDO(
                    "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
                    "Philippe",
                    "l@99339R"
                );
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            
            // Créer la commande
            $numero_commande = 'CMD-' . date('Ymd') . '-' . strtoupper(uniqid());
            $client_id = $_SESSION['client_id'] ?? null;
            $panier_id = $_SESSION['panier_id'] ?? null;
            $session_id = session_id();
            
            // Déterminer le statut selon la méthode de paiement
            if ($payment_method === 'carte') {
                // Simuler un traitement de carte bancaire réussi
                // EN PRODUCTION: Intégrer avec une vraie API de paiement
                $statut = 'paye';
                $methode_paiement = 'carte_bancaire';
            } else {
                // Pour PayPal, on crée la commande en attente
                $statut = 'en_attente_paiement';
                $methode_paiement = 'paypal';
            }
            
            // Données de livraison complètes
            $donnees_commande = [
                'adresse_livraison' => $adresse_livraison,
                'adresse_facturation' => $adresse_facturation,
                'options_livraison' => $options_livraison,
                'frais_livraison' => $frais_livraison,
                'total_panier' => $total_panier,
                'total_commande' => $total_commande,
                'articles' => $articles_panier,
                'methode_paiement' => $methode_paiement
            ];
            
            // Insérer la commande
            $stmt = $pdo->prepare("
                INSERT INTO commandes 
                (numero_commande, client_id, panier_id, session_id, 
                 donnees_commande, statut, montant_total, frais_livraison, 
                 methode_paiement, date_creation) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $numero_commande,
                $client_id,
                $panier_id,
                $session_id,
                json_encode($donnees_commande, JSON_UNESCAPED_UNICODE),
                $statut,
                $total_commande,
                $frais_livraison,
                $methode_paiement
            ]);
            
            $commande_id = $pdo->lastInsertId();
            
            // Marquer le panier comme terminé
            if ($panier_id) {
                $stmt = $pdo->prepare("UPDATE panier SET statut = 'termine' WHERE id_panier = ?");
                $stmt->execute([$panier_id]);
            }
            
            // Nettoyer la session (sauf données de confirmation)
            $_SESSION['numero_commande'] = $numero_commande;
            $_SESSION['commande_id'] = $commande_id;
            $_SESSION['commande_validee'] = true;
            $_SESSION['methode_paiement'] = $methode_paiement;
            
            unset($_SESSION['panier_items']);
            unset($_SESSION['checkout_authorized']);
            
            // Rediriger selon la méthode de paiement
            if ($payment_method === 'paypal') {
                // Rediriger vers PayPal
                // EN PRODUCTION: Utiliser l'API PayPal pour créer un paiement
                header('Location: traitement_paypal.php?commande=' . $commande_id);
                exit();
            } else {
                $success = true;
            }
            
        } catch (Exception $e) {
            $errors[] = "Erreur lors de l'enregistrement de la commande: " . $e->getMessage();
        }
    }
}

// ============================================
// AFFICHAGE
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - HEURE DU CADEAU</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            border-bottom: 2px solid #5a67d8;
            padding-bottom: 10px;
            margin-bottom: 30px;
        }

        h2 {
            color: #555;
            font-size: 18px;
            margin: 25px 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .order-summary {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e2e8f0;
        }

        .items-list {
            margin-bottom: 20px;
        }

        .item-row {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            margin-right: 15px;
        }

        .item-details {
            flex-grow: 1;
        }

        .item-name {
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }

        .item-price {
            color: #5a67d8;
            font-weight: bold;
        }

        .item-quantity {
            color: #666;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 18px;
            color: #2d3748;
        }

        .address-box {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }

        .address-card {
            flex: 1;
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .address-card h3 {
            margin-top: 0;
            color: #4a5568;
            font-size: 16px;
            border-bottom: 1px solid #cbd5e0;
            padding-bottom: 10px;
        }

        .payment-methods {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .payment-method {
            flex: 1;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method-content {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }

        .payment-method input[type="radio"]:checked + .payment-method-content {
            border-color: #5a67d8;
            background-color: #f0f5ff;
        }

        .payment-method:hover .payment-method-content {
            border-color: #c3dafe;
        }

        .payment-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .paypal-icon {
            color: #003087;
        }

        .card-icon {
            color: #5a67d8;
        }

        .payment-form {
            display: none;
        }

        .payment-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        button {
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-card {
            background-color: #5a67d8;
            color: white;
        }

        .btn-card:hover {
            background-color: #4c51bf;
            transform: translateY(-2px);
        }

        .btn-paypal {
            background-color: #FFC439;
            color: #003087;
            border: 1px solid #003087;
        }

        .btn-paypal:hover {
            background-color: #F2BA30;
            transform: translateY(-2px);
        }

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

        .info-box {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .address-box, .form-row, .payment-methods {
                flex-direction: column;
                gap: 15px;
            }
            
            .payment-methods {
                gap: 10px;
            }
            
            body {
                padding: 20px;
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-credit-card"></i> Paiement Sécurisé</h1>

        <?php if ($success): ?>
            <div class="message success">
                <strong><i class="fas fa-check-circle"></i> Paiement réussi !</strong><br>
                Votre commande <strong><?php echo htmlspecialchars($_SESSION['numero_commande']); ?></strong> a été validée.<br>
                Un email de confirmation vous a été envoyé à <?php echo htmlspecialchars($adresse_livraison['email']); ?>.<br><br>
                <a href="confirmation.php?commande=<?php echo $_SESSION['commande_id']; ?>" style="color: #155724; font-weight: bold;">
                    Voir le détail de votre commande →
                </a>
            </div>
        <?php else: ?>
            <!-- Récapitulatif de la commande -->
            <div class="order-summary">
                <h2>Détail de votre commande</h2>
                
                <div class="items-list">
                    <?php foreach ($articles_panier as $item): ?>
                        <div class="item-row">
                            <?php if (!empty($item['image'])): ?>
                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['nom']); ?>" 
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-image" style="background:#eee; display:flex; align-items:center; justify-content:center;">
                                    <i class="fas fa-gift"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="item-details">
                                <div class="item-name"><?php echo htmlspecialchars($item['nom']); ?></div>
                                <div class="item-quantity">Quantité: <?php echo $item['quantite']; ?></div>
                                <?php if (!empty($item['description_courte'])): ?>
                                    <div style="color:#666; font-size:14px; margin-top:5px;">
                                        <?php echo htmlspecialchars($item['description_courte']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-price">
                                <?php echo number_format($item['prix'] * $item['quantite'], 2, ',', ' '); ?> €
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="summary-row">
                    <span>Sous-total panier:</span>
                    <span><?php echo number_format($total_panier, 2, ',', ' '); ?> €</span>
                </div>
                
                <div class="summary-row">
                    <span>Frais de livraison (<?php echo htmlspecialchars($options_livraison['mode_livraison'] ?? 'standard'); ?>):</span>
                    <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> €</span>
                </div>
                
                <?php if ($options_livraison['emballage_cadeau'] ?? false): ?>
                <div class="summary-row">
                    <span>Emballage cadeau:</span>
                    <span>+3,90 €</span>
                </div>
                <?php endif; ?>
                
                <div class="summary-row">
                    <span><strong>Total à payer:</strong></span>
                    <span><strong><?php echo number_format($total_commande, 2, ',', ' '); ?> €</strong></span>
                </div>
            </div>

            <!-- Affichage des adresses -->
            <h2>Adresses</h2>
            <div class="address-box">
                <div class="address-card">
                    <h3><i class="fas fa-truck"></i> Livraison</h3>
                    <p>
                        <strong><?php echo htmlspecialchars($adresse_livraison['prenom'] . ' ' . $adresse_livraison['nom']); ?></strong><br>
                        <?php if (!empty($adresse_livraison['societe'])): ?>
                            <?php echo htmlspecialchars($adresse_livraison['societe']); ?><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($adresse_livraison['adresse']); ?><br>
                        <?php if (!empty($adresse_livraison['complement'])): ?>
                            <?php echo htmlspecialchars($adresse_livraison['complement']); ?><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($adresse_livraison['code_postal'] . ' ' . $adresse_livraison['ville']); ?><br>
                        <?php echo htmlspecialchars($adresse_livraison['pays']); ?><br>
                        <?php if (!empty($adresse_livraison['telephone'])): ?>
                            Tél: <?php echo htmlspecialchars($adresse_livraison['telephone']); ?><br>
                        <?php endif; ?>
                        Email: <?php echo htmlspecialchars($adresse_livraison['email']); ?>
                    </p>
                    <?php if (!empty($adresse_livraison['instructions'])): ?>
                        <p><strong>Instructions:</strong> <?php echo htmlspecialchars($adresse_livraison['instructions']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="address-card">
                    <h3><i class="fas fa-file-invoice"></i> Facturation</h3>
                    <?php if ($adresse_facturation == $adresse_livraison): ?>
                        <p><em>Même adresse que la livraison</em></p>
                    <?php else: ?>
                        <p>
                            <strong><?php echo htmlspecialchars($adresse_facturation['prenom'] . ' ' . $adresse_facturation['nom']); ?></strong><br>
                            <?php if (!empty($adresse_facturation['societe'])): ?>
                                <?php echo htmlspecialchars($adresse_facturation['societe']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($adresse_facturation['adresse']); ?><br>
                            <?php if (!empty($adresse_facturation['complement'])): ?>
                                <?php echo htmlspecialchars($adresse_facturation['complement']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($adresse_facturation['code_postal'] . ' ' . $adresse_facturation['ville']); ?><br>
                            <?php echo htmlspecialchars($adresse_facturation['pays']); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Options de livraison -->
            <div class="info-box">
                <strong><i class="fas fa-shipping-fast"></i> Mode de livraison:</strong> 
                <?php 
                $mode_livraison = $options_livraison['mode_livraison'] ?? 'standard';
                $modes = [
                    'standard' => 'Livraison Standard (3-5 jours ouvrés)',
                    'express' => 'Livraison Express (24h)',
                    'relais' => 'Point Relais'
                ];
                echo htmlspecialchars($modes[$mode_livraison] ?? $mode_livraison);
                ?>
            </div>

            <!-- Messages d'erreur -->
            <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>Erreurs :</strong>
                <ul>
                    <?php foreach ($errors as $erreur): ?>
                    <li><?php echo htmlspecialchars($erreur); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Choix de la méthode de paiement -->
            <h2>Choisissez votre moyen de paiement</h2>
            <form action="paiement.php" method="POST" id="payment-form">
                <div class="payment-methods">
                    <div class="payment-method">
                        <input type="radio" id="paypal" name="payment_method" value="paypal">
                        <label for="paypal" class="payment-method-content">
                            <div class="payment-icon paypal-icon">
                                <i class="fab fa-cc-paypal"></i>
                            </div>
                            <strong>Payer avec PayPal</strong>
                            <div style="font-size: 14px; color: #666; margin-top: 5px;">
                                Paiement sécurisé via votre compte PayPal
                            </div>
                        </label>
                    </div>
                    
                    <div class="payment-method">
                        <input type="radio" id="carte" name="payment_method" value="carte">
                        <label for="carte" class="payment-method-content">
                            <div class="payment-icon card-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <strong>Carte bancaire</strong>
                            <div style="font-size: 14px; color: #666; margin-top: 5px;">
                                Visa, Mastercard, etc.
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Formulaire pour carte bancaire -->
                <div id="card-form" class="payment-form">
                    <h3>Informations de carte bancaire</h3>
                    <div class="form-group">
                        <label for="card_name">Nom sur la carte</label>
                        <input type="text" id="card_name" name="card_name" placeholder="JEAN DUPONT">
                    </div>

                    <div class="form-group">
                        <label for="card_number">Numéro de carte</label>
                        <input type="text" id="card_number" name="card_number" 
                               placeholder="1234 5678 9012 3456" pattern="[0-9\s]{13,19}">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="card_expiry">Date d'expiration (MM/AA)</label>
                            <input type="text" id="card_expiry" name="card_expiry" 
                                   placeholder="12/25" pattern="(0[1-9]|1[0-2])\/[0-9]{2}">
                        </div>
                        <div class="form-group">
                            <label for="card_cvc">Code CVC</label>
                            <input type="text" id="card_cvc" name="card_cvc" 
                                   placeholder="123" pattern="[0-9]{3,4}">
                        </div>
                    </div>
                </div>

                <!-- Boutons de paiement -->
                <div id="paypal-button" class="payment-form">
                    <button type="submit" name="process_paypal" class="btn-paypal">
                        <i class="fab fa-paypal"></i> Payer avec PayPal
                    </button>
                </div>

                <div id="card-button" class="payment-form">
                    <button type="submit" name="process_card" class="btn-card">
                        <i class="fas fa-lock"></i> Payer <?php echo number_format($total_commande, 2, ',', ' '); ?> € par carte
                    </button>
                </div>

                <div style="text-align: center; margin-top: 20px; color: #718096; font-size: 14px;">
                    <i class="fas fa-shield-alt"></i> Paiement sécurisé SSL 256-bit
                </div>
            </form>

            <!-- Lien pour modifier l'adresse -->
            <div style="text-align: center; margin-top: 30px;">
                <a href="livraison_form.php" style="color: #5a67d8;">
                    <i class="fas fa-edit"></i> Modifier l'adresse de livraison
                </a>
            </div>
        <?php endif; ?>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            const cardForm = document.getElementById('card-form');
            const paypalButton = document.getElementById('paypal-button');
            const cardButton = document.getElementById('card-button');
            
            // Masquer les formulaires et boutons au départ
            cardForm.style.display = 'none';
            paypalButton.style.display = 'none';
            cardButton.style.display = 'none';
            
            // Gérer le changement de méthode de paiement
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    if (this.value === 'paypal') {
                        cardForm.style.display = 'none';
                        paypalButton.style.display = 'block';
                        cardButton.style.display = 'none';
                    } else if (this.value === 'carte') {
                        cardForm.style.display = 'block';
                        paypalButton.style.display = 'none';
                        cardButton.style.display = 'block';
                    } else {
                        cardForm.style.display = 'none';
                        paypalButton.style.display = 'none';
                        cardButton.style.display = 'none';
                    }
                });
            });
            
            // Validation du formulaire carte bancaire
            const form = document.getElementById('payment-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
                    
                    if (!selectedMethod) {
                        e.preventDefault();
                        alert('Veuillez sélectionner une méthode de paiement.');
                        return;
                    }
                    
                    if (selectedMethod.value === 'carte') {
                        let isValid = true;
                        
                        // Validation du numéro de carte
                        const cardNumber = document.getElementById('card_number');
                        if (cardNumber) {
                            const cleaned = cardNumber.value.replace(/\s/g, '');
                            if (!/^[0-9]{13,19}$/.test(cleaned)) {
                                cardNumber.style.borderColor = '#e53e3e';
                                isValid = false;
                            } else {
                                cardNumber.style.borderColor = '#ddd';
                            }
                        }
                        
                        // Validation de la date d'expiration
                        const expiry = document.getElementById('card_expiry');
                        if (expiry) {
                            if (!/^(0[1-9]|1[0-2])\/[0-9]{2}$/.test(expiry.value)) {
                                expiry.style.borderColor = '#e53e3e';
                                isValid = false;
                            } else {
                                expiry.style.borderColor = '#ddd';
                            }
                        }
                        
                        // Validation du CVC
                        const cvc = document.getElementById('card_cvc');
                        if (cvc) {
                            if (!/^[0-9]{3,4}$/.test(cvc.value)) {
                                cvc.style.borderColor = '#e53e3e';
                                isValid = false;
                            } else {
                                cvc.style.borderColor = '#ddd';
                            }
                        }
                        
                        if (!isValid) {
                            e.preventDefault();
                            alert('Veuillez vérifier les informations de carte bancaire.');
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>