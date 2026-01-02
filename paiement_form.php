<?php
// ============================================
// PROTECTION D'ACCÈS - VÉRIFIER L'ÉTAPE DE LIVRAISON
// ============================================

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier si l'utilisateur a complété l'étape de livraison
$livraison_complete = false;
$donnees_commande = [];
$frais_livraison = 0;
$total_panier = 0;
$total_commande = 0;
$panier_id = null;
$client_id = null;
$pdo = null;

// Méthode 1: Vérifier via la session
if (isset($_SESSION['livraison_complete']) && $_SESSION['livraison_complete']) {
    $livraison_complete = true;
    $adresse_livraison = $_SESSION['adresse_livraison'] ?? [];
    $adresse_facturation = $_SESSION['adresse_facturation'] ?? [];
    $options_livraison = $_SESSION['options_livraison'] ?? [];
    $frais_livraison = $_SESSION['frais_livraison'] ?? 0;
    $panier_id = $_SESSION['panier_id'] ?? null;
    $client_id = $_SESSION['client_id'] ?? null;
}

// Méthode 2: Vérifier via la base de données (fallback)
if (!$livraison_complete) {
    try {
        // Connexion à la base de données
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Vérifier si on a un panier en session
        $session_id = session_id();
        $panier_id = $_SESSION['panier_id'] ?? null;
        
        if ($panier_id) {
            // 1. Vérifier si les données de livraison existent
            $stmt = $pdo->prepare("
                SELECT donnees_livraison, mode_livraison, emballage_cadeau, instructions
                FROM commande_temporaire 
                WHERE panier_id = ? 
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$panier_id]);
            $temp_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($temp_data && !empty($temp_data['donnees_livraison'])) {
                $livraison_data = json_decode($temp_data['donnees_livraison'], true);
                
                if ($livraison_data && !empty($livraison_data['email'])) {
                    $livraison_complete = true;
                    
                    // Récupérer les données depuis la base
                    $_SESSION['adresse_livraison'] = $livraison_data;
                    $_SESSION['adresse_facturation'] = $livraison_data; // Par défaut, même adresse
                    $_SESSION['options_livraison'] = [
                        'mode_livraison' => $temp_data['mode_livraison'] ?? 'standard',
                        'emballage_cadeau' => $temp_data['emballage_cadeau'] ?? 0,
                        'instructions' => $temp_data['instructions'] ?? ''
                    ];
                    
                    // Calculer les frais de livraison
                    $frais_livraison = 0;
                    $mode_livraison = $temp_data['mode_livraison'] ?? 'standard';
                    
                    switch ($mode_livraison) {
                        case 'express':
                            $frais_livraison = 9.90;
                            break;
                        case 'relais':
                            $frais_livraison = 4.90;
                            break;
                        default:
                            $frais_livraison = 0;
                    }
                    
                    // Ajouter frais d'emballage cadeau
                    if ($temp_data['emballage_cadeau']) {
                        $frais_livraison += 3.90;
                    }
                    
                    $_SESSION['frais_livraison'] = $frais_livraison;
                    $_SESSION['livraison_complete'] = true;
                    $_SESSION['panier_id'] = $panier_id;
                    
                    $adresse_livraison = $livraison_data;
                    $adresse_facturation = $livraison_data;
                    $options_livraison = $_SESSION['options_livraison'];
                }
            }
        }
        
        // Si pas de données de livraison, vérifier le panier directement
        if (!$livraison_complete) {
            // Vérifier si le panier existe et a des articles
            if ($session_id) {
                $stmt = $pdo->prepare("
                    SELECT p.id_panier, p.id_client, COUNT(pi.id_item) as nb_items 
                    FROM panier p 
                    LEFT JOIN panier_items pi ON p.id_panier = pi.id_panier 
                    WHERE p.session_id = ? AND p.statut = 'actif'
                    GROUP BY p.id_panier
                ");
                $stmt->execute([$session_id]);
                $panier = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($panier && $panier['nb_items'] > 0) {
                    // Stocker les IDs pour usage ultérieur
                    $_SESSION['panier_id'] = $panier['id_panier'];
                    $_SESSION['client_id'] = $panier['id_client'];
                    $panier_id = $panier['id_panier'];
                    $client_id = $panier['id_client'];
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erreur base de données: " . $e->getMessage());
    }
}

// Si livraison non complétée, rediriger vers le formulaire de livraison
if (!$livraison_complete) {
    header('Location: livraison_form.php');
    exit();
}

// ============================================
// RÉCUPÉRATION DU PANIER ET CALCUL DES TOTAUX
// ============================================

try {
    // Si pas de connexion PDO, en créer une nouvelle
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    // Récupérer les articles du panier
    if ($panier_id) {
        $stmt = $pdo->prepare("
            SELECT pi.*, p.nom, p.reference, p.prix, p.prix_ttc, 
                   c.nom as categorie_nom, p.image_url
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie
            WHERE pi.id_panier = ?
            ORDER BY pi.date_ajout DESC
        ");
        $stmt->execute([$panier_id]);
        $panier_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculer le total du panier
        $total_panier = 0;
        foreach ($panier_items as $item) {
            $total_panier += ($item['prix'] * $item['quantite']);
        }
        
        // Calculer le total de la commande
        $total_commande = $total_panier + $frais_livraison;
        
        // Préparer les données pour l'affichage
        $donnees_commande = [
            'panier_items' => $panier_items,
            'adresse_livraison' => $adresse_livraison ?? [],
            'adresse_facturation' => $adresse_facturation ?? [],
            'options_livraison' => $options_livraison ?? [],
            'frais_livraison' => $frais_livraison,
            'total_panier' => $total_panier,
            'total_commande' => $total_commande,
            'panier_id' => $panier_id,
            'client_id' => $client_id
        ];
        
    } else {
        // Fallback aux données de session
        if (isset($_SESSION['panier_items'])) {
            $panier_items = $_SESSION['panier_items'];
            $total_panier = 0;
            foreach ($panier_items as $item) {
                $total_panier += ($item['prix'] * $item['quantite']);
            }
            $total_commande = $total_panier + $frais_livraison;
            
            $donnees_commande = [
                'panier_items' => $panier_items,
                'adresse_livraison' => $adresse_livraison ?? [],
                'adresse_facturation' => $adresse_facturation ?? [],
                'options_livraison' => $options_livraison ?? [],
                'frais_livraison' => $frais_livraison,
                'total_panier' => $total_panier,
                'total_commande' => $total_commande
            ];
        } else {
            // Rediriger si panier vide
            header('Location: panier.php');
            exit();
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur récupération panier: " . $e->getMessage());
    // Fallback aux sessions
    if (isset($_SESSION['panier_items'])) {
        $panier_items = $_SESSION['panier_items'];
        $total_panier = 0;
        foreach ($panier_items as $item) {
            $total_panier += ($item['prix'] * $item['quantite']);
        }
        $total_commande = $total_panier + $frais_livraison;
    } else {
        header('Location: panier.php');
        exit();
    }
}

// ============================================
// CRÉATION DU FLAG POUR PAIEMENT.PHP
// ============================================
$_SESSION['from_paiement_form'] = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paiement - HEURE DU CADEAU</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
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

        .progress-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            padding: 0 30px;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .step-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .progress-step.active .step-circle {
            background: #5a67d8;
            color: white;
            box-shadow: 0 4px 12px rgba(90,103,216,0.3);
        }

        .progress-step.active .step-label {
            color: #5a67d8;
            font-weight: 600;
        }

        .progress-step.completed .step-circle {
            background: #38a169;
            color: white;
        }

        .progress-step.completed .step-label {
            color: #38a169;
        }

        .progress-line {
            flex: 1;
            height: 3px;
            background: #e0e0e0;
            margin: 0 -20px;
            position: relative;
            top: -20px;
            z-index: 1;
        }

        .progress-line.completed {
            background: #38a169;
        }

        .paiement-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
            margin-top: 30px;
        }

        .paiement-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .recap-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .section-title {
            color: #2d3748;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #5a67d8;
        }

        .adresse-info {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .adresse-line {
            margin-bottom: 5px;
            color: #4a5568;
        }

        .paiement-options {
            margin-bottom: 30px;
        }

        .paiement-option {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .paiement-option:hover {
            border-color: #cbd5e0;
        }

        .paiement-option.selected {
            border-color: #5a67d8;
            background: rgba(90,103,216,0.05);
        }

        .option-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .option-header img {
            height: 24px;
        }

        .option-body {
            padding-left: 40px;
        }

        .option-body p {
            margin-bottom: 10px;
            color: #718096;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .option-body p i {
            color: #38a169;
        }

        #paypal-button-container {
            margin-top: 20px;
            min-height: 45px;
        }

        .card-form {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-item.total {
            border-bottom: none;
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            width: 100%;
            margin-top: 20px;
        }

        .btn-primary {
            background: #5a67d8;
            color: white;
        }

        .btn-primary:hover {
            background: #4c51bf;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90,103,216,0.3);
        }

        .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading i {
            font-size: 24px;
            color: #5a67d8;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .securite-note {
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .securite-note i {
            color: #38a169;
        }

        .panier-items {
            margin-bottom: 20px;
        }

        .panier-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .item-info {
            flex: 1;
        }

        .item-quantity {
            color: #718096;
            font-size: 14px;
        }

        .item-price {
            font-weight: 600;
            color: #2d3748;
        }

        @media (max-width: 992px) {
            .paiement-container {
                grid-template-columns: 1fr;
            }

            .progress-bar {
                flex-wrap: wrap;
            }

            .progress-step {
                padding: 0 15px;
                margin-bottom: 20px;
            }

            .progress-line {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-credit-card"></i> Paiement Sécurisé</h1>

        <!-- Barre de progression -->
        <div class="progress-bar">
            <div class="progress-step completed">
                <div class="step-circle">1</div>
                <div class="step-label">Panier</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step completed">
                <div class="step-circle">2</div>
                <div class="step-label">Livraison</div>
            </div>
            <div class="progress-line completed"></div>
            <div class="progress-step active">
                <div class="step-circle">3</div>
                <div class="step-label">Paiement</div>
            </div>
        </div>

        <div class="paiement-container">
            <!-- Section paiement -->
            <div class="paiement-section">
                <h2 class="section-title">
                    <i class="fas fa-credit-card"></i> Mode de paiement
                </h2>

                <!-- Adresse de livraison -->
                <div class="adresse-info">
                    <?php if (!empty($donnees_commande['adresse_livraison'])): 
                        $adresse = $donnees_commande['adresse_livraison']; ?>
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <p class="adresse-line"><strong><?php echo htmlspecialchars($adresse['prenom'] . ' ' . $adresse['nom']); ?></strong></p>
                                <p class="adresse-line"><?php echo htmlspecialchars($adresse['adresse']); ?></p>
                                <?php if (!empty($adresse['complement'])): ?>
                                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['complement']); ?></p>
                                <?php endif; ?>
                                <p class="adresse-line"><?php echo htmlspecialchars($adresse['code_postal'] . ' ' . $adresse['ville']); ?></p>
                                <p class="adresse-line"><?php echo htmlspecialchars($adresse['pays']); ?></p>
                                <?php if (!empty($adresse['telephone'])): ?>
                                    <p class="adresse-line"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adresse['telephone']); ?></p>
                                <?php endif; ?>
                                <p class="adresse-line"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adresse['email']); ?></p>
                            </div>
                            <a href="livraison_form.php" style="color: #5a67d8; text-decoration: none;">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Options de paiement -->
                <div class="paiement-options">
                    <!-- PayPal -->
                    <div class="paiement-option selected" id="optionPaypal">
                        <div class="option-header">
                            <input type="radio" name="paiement" id="paypal" value="paypal" checked hidden />
                            <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal" />
                            <span style="font-weight: 600; color: #2d3748">PayPal</span>
                        </div>
                        <div class="option-body">
                            <p><i class="fas fa-check-circle"></i> Paiement sécurisé par carte bancaire</p>
                            <p><i class="fas fa-check-circle"></i> Pas besoin de compte PayPal</p>
                            <p><i class="fas fa-check-circle"></i> Protection de l'acheteur incluse</p>
                            <div id="paypal-button-container"></div>
                        </div>
                    </div>

                    <!-- Carte bancaire -->
                    <div class="paiement-option" id="optionCarte">
                        <div class="option-header">
                            <input type="radio" name="paiement" id="carte" value="carte" hidden />
                            <i class="fas fa-credit-card" style="font-size: 24px; color: #718096"></i>
                            <span style="font-weight: 600; color: #2d3748">Carte bancaire</span>
                        </div>
                        <div class="option-body">
                            <p>Paiement sécurisé via notre système</p>
                            <div style="display: flex; gap: 15px; margin: 15px 0">
                                <i class="fab fa-cc-visa" style="font-size: 32px; color: #1434cb"></i>
                                <i class="fab fa-cc-mastercard" style="font-size: 32px; color: #eb001b"></i>
                                <i class="fab fa-cc-amex" style="font-size: 32px; color: #2e77bc"></i>
                            </div>
                            <div id="cardForm" style="display: none">
                                <div class="form-group">
                                    <label for="cardNumber">Numéro de carte</label>
                                    <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" />
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="cardExpiry">Date d'expiration</label>
                                        <input type="text" id="cardExpiry" placeholder="MM/AA" maxlength="5" />
                                    </div>
                                    <div class="form-group">
                                        <label for="cardCVC">CVC</label>
                                        <input type="text" id="cardCVC" placeholder="123" maxlength="3" />
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="cardName">Nom sur la carte</label>
                                    <input type="text" id="cardName" placeholder="JEAN DUPONT" />
                                </div>
                                <button type="button" class="btn btn-primary" id="submitCard">
                                    <i class="fas fa-lock"></i> Payer avec ma carte
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boutons de navigation -->
                <div style="display: flex; gap: 15px; margin-top: 40px">
                    <a href="livraison_form.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour à la livraison
                    </a>
                </div>

                <!-- Loading -->
                <div class="loading" id="loading">
                    <i class="fas fa-spinner"></i>
                    <p>Traitement en cours...</p>
                </div>

                <!-- Note de sécurité -->
                <p class="securite-note">
                    <i class="fas fa-shield-alt"></i>
                    Paiement 100% sécurisé - Vos données sont cryptées
                </p>
            </div>

            <!-- Récapitulatif -->
            <div class="recap-section">
                <h3 class="section-title">
                    <i class="fas fa-receipt"></i> Récapitulatif
                </h3>

                <!-- Articles du panier -->
                <div class="panier-items">
                    <?php foreach ($donnees_commande['panier_items'] as $item): ?>
                        <div class="panier-item">
                            <div class="item-info">
                                <strong><?php echo htmlspecialchars($item['nom']); ?></strong>
                                <div class="item-quantity">Quantité: <?php echo $item['quantite']; ?></div>
                            </div>
                            <div class="item-price">
                                <?php echo number_format($item['prix'] * $item['quantite'], 2, ',', ' '); ?> €
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totaux -->
                <div class="summary-details">
                    <div class="summary-item">
                        <span>Sous-total</span>
                        <span><?php echo number_format($donnees_commande['total_panier'], 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="summary-item">
                        <span>Livraison</span>
                        <span><?php echo number_format($donnees_commande['frais_livraison'], 2, ',', ' '); ?> €</span>
                    </div>
                    <div class="summary-item total">
                        <span>Total</span>
                        <span><?php echo number_format($donnees_commande['total_commande'], 2, ',', ' '); ?> €</span>
                    </div>
                </div>

                <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                    <p style="font-size: 12px; color: #718096; margin: 0">
                        <i class="fas fa-info-circle"></i>
                        Vous recevrez un email de confirmation après le paiement.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <!-- PayPal SDK (Version de test) -->
    <script src="https://www.paypal.com/sdk/js?client-id=test&currency=EUR"></script>

    <script>
        // Variables globales
        const commandeData = <?php echo json_encode($donnees_commande); ?>;
        let paiementMethod = "paypal";

        // Gestion des options de paiement
        document.querySelectorAll('.paiement-option').forEach(option => {
            option.addEventListener('click', function() {
                // Désélectionner toutes les options
                document.querySelectorAll('.paiement-option').forEach(opt => {
                    opt.classList.remove('selected');
                });

                // Sélectionner l'option cliquée
                this.classList.add('selected');

                // Mettre à jour la méthode de paiement
                const input = this.querySelector('input[type="radio"]');
                if (input) {
                    input.checked = true;
                    paiementMethod = input.value;

                    // Afficher/masquer le formulaire carte
                    if (paiementMethod === 'carte') {
                        document.getElementById('cardForm').style.display = 'block';
                        document.getElementById('paypal-button-container').style.display = 'none';
                    } else {
                        document.getElementById('cardForm').style.display = 'none';
                        document.getElementById('paypal-button-container').style.display = 'block';
                    }
                }
            });
        });

        // Initialiser PayPal
        function initialiserPayPal(total) {
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'gold',
                    shape: 'rect',
                    label: 'paypal'
                },
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: total.toFixed(2),
                                currency_code: 'EUR'
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Paiement réussi
                        finaliserCommande('paypal', details.id);
                    });
                },
                onError: function(err) {
                    console.error('Erreur PayPal:', err);
                    alert('Une erreur est survenue avec PayPal. Veuillez réessayer ou choisir un autre mode de paiement.');
                }
            }).render('#paypal-button-container');
        }

        // Paiement par carte
        document.getElementById('submitCard')?.addEventListener('click', function() {
            const cardNumber = document.getElementById('cardNumber').value.replace(/\s/g, '');
            const cardExpiry = document.getElementById('cardExpiry').value;
            const cardCVC = document.getElementById('cardCVC').value;
            const cardName = document.getElementById('cardName').value.trim();

            // Validation simple
            if (!cardNumber || cardNumber.length < 16) {
                alert('Numéro de carte invalide');
                return;
            }

            if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
                alert('Date d\'expiration invalide (format MM/AA)');
                return;
            }

            if (!cardCVC || cardCVC.length < 3) {
                alert('CVC invalide');
                return;
            }

            if (!cardName) {
                alert('Nom sur la carte requis');
                return;
            }

            // Finaliser la commande avec paiement carte
            const reference = 'CARD-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9).toUpperCase();
            finaliserCommande('carte', reference);
        });

        // Finaliser la commande
        async function finaliserCommande(methode, reference) {
            try {
                // Afficher le loading
                document.getElementById('loading').style.display = 'block';

                // Envoyer les données au serveur
                const response = await fetch('paiement.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        process_payment: true,
                        methode_paiement: methode,
                        reference_paiement: reference,
                        panier_id: commandeData.panier_id,
                        client_id: commandeData.client_id,
                        montant_total: commandeData.total_commande
                    })
                });

                const result = await response.json();

                if (result.success) {
                    // Redirection vers la confirmation
                    window.location.href = result.redirect || 'confirmation.php';
                } else {
                    alert('Erreur: ' + (result.message || 'Une erreur est survenue'));
                    document.getElementById('loading').style.display = 'none';
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue. Veuillez réessayer.');
                document.getElementById('loading').style.display = 'none';
            }
        }

        // Formatage des champs carte
        document.getElementById('cardNumber')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);

            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);

            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2);
            }
            e.target.value = value;
        });

        document.getElementById('cardCVC')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substr(0, 3);
        });

        // Initialiser PayPal au chargement
        document.addEventListener('DOMContentLoaded', function() {
            if (commandeData.total_commande > 0) {
                initialiserPayPal(commandeData.total_commande);
            }
        });
    </script>
</body>
</html>