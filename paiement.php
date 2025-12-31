<?php
session_start();

// Définir une constante pour autoriser l'accès à db_config.php
define('API_CALL', true);

// Vérifier si db_config.php existe
$dbConfigPath = __DIR__ . '/db_config.php';
if (!file_exists($dbConfigPath)) {
    die("Fichier de configuration DB introuvable");
}

// Inclure db_config.php
require_once $dbConfigPath;

// Vérifier si le panier existe et n'est pas vide
if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    header('Location: panier.php');
    exit();
}

// Vérifier si les infos de livraison sont disponibles
/*if (!isset($_SESSION['livraison_form'])) {
    header('Location: livraison.php');
    exit();
}*/

// Configuration PayPal (à remplacer par vos vraies clés)
define('PAYPAL_CLIENT_ID', 'test'); // Mode test
define('PAYPAL_SECRET', 'test'); // Mode test
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' ou 'live'

// Fonction pour récupérer les détails du panier
function getCartDetails() {
    $db = getDB();
    if (!$db) return [];
    
    $items = [];
    $sous_total = 0;
    
    if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $item) {
            $id_produit = $item['id_produit'] ?? 0;
            $quantite = $item['quantite'] ?? 0;
            
            if ($id_produit > 0 && $quantite > 0) {
                // Récupérer les infos produit depuis BDD
                $stmt = $db->prepare("
                    SELECT p.id_produit, p.nom, p.prix_ttc, p.reference,
                           COALESCE(
                               (SELECT url_image FROM images_produits 
                                WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1),
                               'img/default-product.jpg'
                           ) as image
                    FROM produits p
                    WHERE p.id_produit = ? AND p.statut = 'actif'
                ");
                $stmt->execute([$id_produit]);
                $produit = $stmt->fetch();
                
                if ($produit) {
                    $prix_unitaire = floatval($produit['prix_ttc']);
                    $prix_total = $prix_unitaire * $quantite;
                    $sous_total += $prix_total;
                    
                    $items[] = [
                        'id_produit' => $produit['id_produit'],
                        'nom' => $produit['nom'],
                        'reference' => $produit['reference'],
                        'prix_unitaire' => $prix_unitaire,
                        'quantite' => $quantite,
                        'prix_total' => $prix_total,
                        'image' => $produit['image']
                    ];
                }
            }
        }
    }
    
    return [
        'items' => $items,
        'sous_total' => $sous_total
    ];
}

// Récupérer les données
$livraison = $_SESSION['livraison_form'];
$cart_details = getCartDetails();
$items = $cart_details['items'];
$sous_total = $cart_details['sous_total'];

// Calculer les frais de livraison
$frais_livraison = 0;
if (isset($livraison['mode_livraison'])) {
    switch ($livraison['mode_livraison']) {
        case 'standard':
            $frais_livraison = 5.99;
            break;
        case 'express':
            $frais_livraison = 12.99;
            break;
        case 'point_relais':
            $frais_livraison = 3.99;
            break;
        default:
            $frais_livraison = 5.99;
    }
}

$total = $sous_total + $frais_livraison;

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer la méthode de paiement
    $methode_paiement = $_POST['methode_paiement'] ?? '';
    
    if ($methode_paiement === 'carte') {
        // Validation des données de carte
        $errors = [];
        
        if (empty($_POST['numero_carte'])) {
            $errors[] = "Le numéro de carte est requis";
        } elseif (!preg_match('/^[0-9]{16}$/', str_replace(' ', '', $_POST['numero_carte']))) {
            $errors[] = "Le numéro de carte n'est pas valide";
        }
        
        if (empty($_POST['nom_carte'])) {
            $errors[] = "Le nom sur la carte est requis";
        }
        
        if (empty($_POST['date_expiration'])) {
            $errors[] = "La date d'expiration est requise";
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/[0-9]{2}$/', $_POST['date_expiration'])) {
            $errors[] = "Format de date invalide (MM/AA)";
        }
        
        if (empty($_POST['cvv'])) {
            $errors[] = "Le code CVV est requis";
        } elseif (!preg_match('/^[0-9]{3,4}$/', $_POST['cvv'])) {
            $errors[] = "Le code CVV n'est pas valide";
        }
        
        if (empty($errors)) {
            // Simuler un paiement carte réussi
            $reference_paiement = 'CARD-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            // Créer la commande
            if (createOrder($methode_paiement, $reference_paiement)) {
                // Rediriger vers confirmation
                header('Location: confirmation.php?ref=' . urlencode($reference_paiement));
                exit();
            } else {
                $errors[] = "Erreur lors de la création de la commande";
            }
        }
    } elseif ($methode_paiement === 'paypal') {
        // Pour PayPal, l'API s'occupe de la validation
        // Stocker les infos pour le callback PayPal
        $_SESSION['pending_payment'] = [
            'methode' => 'paypal',
            'total' => $total,
            'items' => $items,
            'livraison' => $livraison
        ];
        
        // En production, vous redirigeriez vers PayPal
        // Pour la démo, on simule un paiement PayPal réussi
        if (isset($_POST['simuler_paypal'])) {
            $reference_paiement = 'PAYPAL-' . date('YmdHis') . '-' . rand(1000, 9999);
            
            if (createOrder('paypal', $reference_paiement)) {
                header('Location: confirmation.php?ref=' . urlencode($reference_paiement));
                exit();
            }
        }
    }
}

// Fonction pour créer une commande en BDD
function createOrder($methode_paiement, $reference_paiement) {
    $db = getDB();
    if (!$db) return false;
    
    try {
        $db->beginTransaction();
        
        // Créer la commande
        $stmt = $db->prepare("
            INSERT INTO commandes (
                id_client, numero_commande, total_ht, total_ttc, 
                frais_livraison, methode_paiement, reference_paiement,
                statut_paiement, statut_commande, date_commande,
                nom_livraison, prenom_livraison, adresse_livraison,
                complement_livraison, code_postal_livraison, ville_livraison,
                pays_livraison, telephone_livraison, email_livraison,
                mode_livraison
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'payé', 'en_traitement', NOW(),
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $id_client = $_SESSION['id_client'] ?? null;
        $numero_commande = 'CMD' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        
        // Calculer les totaux
        $cart_details = getCartDetails();
        $total_ht = $cart_details['sous_total'] / 1.2; // Exemple TVA 20%
        $total_ttc = $cart_details['sous_total'];
        $frais_livraison = $_SESSION['livraison_form']['frais_livraison'] ?? 0;
        
        $livraison = $_SESSION['livraison_form'];
        
        $stmt->execute([
            $id_client,
            $numero_commande,
            $total_ht,
            $total_ttc,
            $frais_livraison,
            $methode_paiement,
            $reference_paiement,
            $livraison['nom'] ?? '',
            $livraison['prenom'] ?? '',
            $livraison['adresse'] ?? '',
            $livraison['complement'] ?? '',
            $livraison['code_postal'] ?? '',
            $livraison['ville'] ?? '',
            $livraison['pays'] ?? '',
            $livraison['telephone'] ?? '',
            $livraison['email'] ?? '',
            $livraison['mode_livraison'] ?? 'standard'
        ]);
        
        $id_commande = $db->lastInsertId();
        
        // Ajouter les articles de la commande
        foreach ($_SESSION['panier'] as $item) {
            $id_produit = $item['id_produit'];
            $quantite = $item['quantite'];
            
            // Récupérer le prix du produit
            $stmt_prod = $db->prepare("SELECT prix_ttc FROM produits WHERE id_produit = ?");
            $stmt_prod->execute([$id_produit]);
            $produit = $stmt_prod->fetch();
            
            if ($produit) {
                $stmt_item = $db->prepare("
                    INSERT INTO commande_items (
                        id_commande, id_produit, quantite, prix_unitaire_ttc
                    ) VALUES (?, ?, ?, ?)
                ");
                
                $stmt_item->execute([
                    $id_commande,
                    $id_produit,
                    $quantite,
                    $produit['prix_ttc']
                ]);
                
                // Mettre à jour le stock
                $stmt_stock = $db->prepare("
                    UPDATE produits 
                    SET quantite_stock = quantite_stock - ? 
                    WHERE id_produit = ?
                ");
                $stmt_stock->execute([$quantite, $id_produit]);
            }
        }
        
        $db->commit();
        
        // Vider le panier
        $_SESSION['panier'] = [];
        
        // Stocker la commande pour la confirmation
        $_SESSION['last_commande'] = [
            'numero' => $numero_commande,
            'total' => $total_ttc + $frais_livraison,
            'date' => date('Y-m-d H:i:s')
        ];
        
        return true;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Erreur création commande: " . $e->getMessage());
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .paiement-page {
            padding: 40px 0;
            background: #f8f9fa;
            min-height: calc(100vh - 200px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
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
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .recap-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            background: rgba(90, 103, 216, 0.05);
        }

        .option-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
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
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
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
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
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

        .error {
            color: #e53e3e;
            background: #fed7d7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .error p {
            margin: 5px 0;
        }

        @media (max-width: 992px) {
            .paiement-container {
                grid-template-columns: 1fr;
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
    <?php include 'header.php'; ?>

    <main class="paiement-page">
        <div class="container">
            <!-- Barre de progression -->
            <div class="progress-bar">
                <div class="progress-step completed">
                    <div class="step-circle">1</div>
                    <div class="step-label">Panier</div>
                </div>
                <div class="progress-step completed">
                    <div class="step-circle">2</div>
                    <div class="step-label">Livraison</div>
                </div>
                <div class="progress-step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Paiement</div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="paiement-container">
                <!-- Section paiement -->
                <div class="paiement-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Mode de paiement
                    </h2>

                    <!-- Adresse de livraison -->
                    <div class="adresse-info">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <p style="margin: 5px 0;"><strong><?php echo htmlspecialchars($livraison['prenom'] . ' ' . $livraison['nom']); ?></strong></p>
                                <p style="margin: 5px 0;"><?php echo htmlspecialchars($livraison['adresse']); ?></p>
                                <?php if (!empty($livraison['complement'])): ?>
                                    <p style="margin: 5px 0;"><?php echo htmlspecialchars($livraison['complement']); ?></p>
                                <?php endif; ?>
                                <p style="margin: 5px 0;"><?php echo htmlspecialchars($livraison['code_postal'] . ' ' . $livraison['ville']); ?></p>
                                <p style="margin: 5px 0;"><?php echo htmlspecialchars($livraison['pays']); ?></p>
                                <?php if (!empty($livraison['telephone'])): ?>
                                    <p style="margin: 5px 0;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($livraison['telephone']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($livraison['email'])): ?>
                                    <p style="margin: 5px 0;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($livraison['email']); ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="livraison.php" style="color: #5a67d8; text-decoration: none;">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        </div>
                    </div>

                    <form method="POST" action="" id="paymentForm">
                        <!-- Options de paiement -->
                        <div class="paiement-options">
                            <!-- PayPal -->
                            <div class="paiement-option selected" id="optionPaypal">
                                <div class="option-header">
                                    <input type="radio" name="methode_paiement" id="paypal" value="paypal" checked hidden />
                                    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal" />
                                    <span style="font-weight: 600; color: #2d3748">PayPal</span>
                                </div>
                                <div class="option-body">
                                    <p><i class="fas fa-check-circle"></i> Paiement sécurisé par carte bancaire</p>
                                    <p><i class="fas fa-check-circle"></i> Pas besoin de compte PayPal</p>
                                    <p><i class="fas fa-check-circle"></i> Protection de l'acheteur incluse</p>

                                    <!-- Bouton PayPal (mode démo) -->
                                    <div style="margin-top: 20px;">
                                        <button type="button" class="btn btn-primary" id="simulerPaypal">
                                            <i class="fab fa-paypal"></i> Payer avec PayPal (Démo)
                                        </button>
                                        <input type="hidden" name="simuler_paypal" id="simuler_paypal" value="0">
                                    </div>
                                </div>
                            </div>

                            <!-- Carte bancaire -->
                            <div class="paiement-option" id="optionCarte">
                                <div class="option-header">
                                    <input type="radio" name="methode_paiement" id="carte" value="carte" hidden />
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
                                            <label for="numero_carte">Numéro de carte</label>
                                            <input type="text" id="numero_carte" name="numero_carte" placeholder="1234 5678 9012 3456" maxlength="19" />
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="date_expiration">Date d'expiration</label>
                                                <input type="text" id="date_expiration" name="date_expiration" placeholder="MM/AA" maxlength="5" />
                                            </div>
                                            <div class="form-group">
                                                <label for="cvv">CVV</label>
                                                <input type="text" id="cvv" name="cvv" placeholder="123" maxlength="4" />
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="nom_carte">Nom sur la carte</label>
                                            <input type="text" id="nom_carte" name="nom_carte" placeholder="JEAN DUPONT" />
                                        </div>

                                        <button type="submit" class="btn btn-primary" name="submit_card">
                                            <i class="fas fa-lock"></i> Payer <?php echo number_format($total, 2, ',', ' '); ?> €
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons de navigation -->
                        <div style="display: flex; gap: 15px; margin-top: 40px">
                            <a href="livraison.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour à la livraison
                            </a>
                        </div>

                        <!-- Loading -->
                        <div class="loading" id="loading">
                            <i class="fas fa-spinner"></i>
                            <p>Traitement en cours...</p>
                        </div>
                    </form>

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

                    <div style="margin-bottom: 20px;">
                        <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                            <i class="fas fa-box"></i> <?php echo count($items); ?> article(s)
                        </p>
                        <p style="color: #718096; font-size: 14px;">
                            <i class="fas fa-truck"></i> Livraison <?php echo htmlspecialchars($livraison['mode_livraison'] ?? 'standard'); ?>
                        </p>
                    </div>

                    <div class="summary-details">
                        <div class="summary-item">
                            <span>Sous-total</span>
                            <span><?php echo number_format($sous_total, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="summary-item">
                            <span>Livraison</span>
                            <span><?php echo number_format($frais_livraison, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="summary-item total">
                            <span>Total</span>
                            <span><?php echo number_format($total, 2, ',', ' '); ?> €</span>
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
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Gestion des options de paiement
        document.querySelectorAll('.paiement-option').forEach((option) => {
            option.addEventListener('click', function() {
                // Désélectionner toutes les options
                document.querySelectorAll('.paiement-option').forEach((opt) => {
                    opt.classList.remove('selected');
                });

                // Sélectionner l'option cliquée
                this.classList.add('selected');

                // Mettre à jour le radio button
                const input = this.querySelector('input[type="radio"]');
                if (input) {
                    input.checked = true;
                    
                    // Afficher/masquer le formulaire carte
                    if (input.value === 'carte') {
                        document.getElementById('cardForm').style.display = 'block';
                    } else {
                        document.getElementById('cardForm').style.display = 'none';
                    }
                }
            });
        });

        // Simuler PayPal
        document.getElementById('simulerPaypal')?.addEventListener('click', function() {
            if (confirm('En mode démo, nous allons simuler un paiement PayPal réussi. Continuer ?')) {
                document.getElementById('simuler_paypal').value = '1';
                document.getElementById('paypal').checked = true;
                
                // Afficher le loading
                document.getElementById('loading').style.display = 'block';
                
                // Soumettre le formulaire
                setTimeout(() => {
                    document.getElementById('paymentForm').submit();
                }, 1000);
            }
        });

        // Formatage des champs carte
        document.getElementById('numero_carte')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        document.getElementById('date_expiration')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);
            
            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2);
            }
            e.target.value = value;
        });

        document.getElementById('cvv')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substr(0, 4);
        });

        // Validation avant soumission carte
        document.querySelector('button[name="submit_card"]')?.addEventListener('click', function(e) {
            const cardNumber = document.getElementById('numero_carte').value.replace(/\s/g, '');
            const cardExpiry = document.getElementById('date_expiration').value;
            const cardCVC = document.getElementById('cvv').value;
            const cardName = document.getElementById('nom_carte').value.trim();
            
            // Validation
            if (!cardNumber || cardNumber.length < 16) {
                alert('Numéro de carte invalide');
                e.preventDefault();
                return false;
            }
            
            if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
                alert('Date d\'expiration invalide (format MM/AA)');
                e.preventDefault();
                return false;
            }
            
            if (!cardCVC || cardCVC.length < 3) {
                alert('CVC invalide');
                e.preventDefault();
                return false;
            }
            
            if (!cardName) {
                alert('Nom sur la carte requis');
                e.preventDefault();
                return false;
            }
            
            // Afficher le loading
            document.getElementById('loading').style.display = 'block';
            return true;
        });

        // En production, intégrer le vrai SDK PayPal
        // paypal.Buttons({...}).render('#paypal-button-container');
    </script>
</body>
</html>