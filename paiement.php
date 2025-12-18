<?php
session_start();

// Vérifier si l'utilisateur vient de la livraison
if (!isset($_SESSION['adresse_livraison'])) {
    header('Location: livraison.php');
    exit();
}

// Vérifier si le panier existe et n'est pas vide
if (!isset($_SESSION['panier']) || empty($_SESSION['panier']['items'])) {
    header('Location: panier.php');
    exit();
}

// Calculer les totaux réels
$sous_total = isset($_SESSION['panier']['total_prix']) ? floatval($_SESSION['panier']['total_prix']) : 0;
$frais_livraison = isset($_SESSION['frais_livraison']) ? floatval($_SESSION['frais_livraison']) : 0;
$frais_emballage = isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau'] ? 3.90 : 0;
$total = $sous_total + $frais_livraison + $frais_emballage;

// ID client PayPal - À REMPLACER PAR VOTRE VRAI CLIENT ID
$paypal_client_id = "AThDdpC7nCErB8D7uM5K-pjO0qZsyepoQIZ5Qg6H9JqC9gWjs7-WTrXrwqKqbYCLh7v4L4vSGs1sNrKk"; // Sandbox ID
// En production : "VOTRE_CLIENT_ID_LIVE_ICI"

// Traitement après retour de PayPal
if (isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
    // Ici vous devriez valider le paiement avec l'API PayPal
    // Pour l'exemple, on simule le succès
    $payment_id = $_GET['paymentId'];
    $payer_id = $_GET['PayerID'];
    
    // Créer la commande
    $numero_commande = 'CMD-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    $commande = [
        'numero' => $numero_commande,
        'date' => date('Y-m-d H:i:s'),
        'adresse_livraison' => $_SESSION['adresse_livraison'],
        'panier' => $_SESSION['panier'],
        'methode_paiement' => 'paypal',
        'reference_paiement' => $payment_id,
        'payer_id' => $payer_id,
        'sous_total' => $sous_total,
        'frais_livraison' => $frais_livraison,
        'frais_emballage' => $frais_emballage,
        'total' => $total,
        'statut' => 'paye'
    ];
    
    // Sauvegarder la commande en session
    $_SESSION['commande_en_cours'] = $commande;
    
    // Rediriger vers la confirmation
    header('Location: confirmation.php?cmd=' . $numero_commande . '&ref=' . $payment_id);
    exit();
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
            background: rgba(90, 103, 216, 0.05);
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

        .paypal-options {
            display: flex;
            gap: 20px;
            margin-top: 15px;
        }

        .paypal-option {
            flex: 1;
            text-align: center;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .paypal-option:hover {
            border-color: #cbd5e0;
            background: #f7fafc;
        }

        .paypal-option.selected {
            border-color: #5a67d8;
            background: rgba(90, 103, 216, 0.05);
        }

        .paypal-option img {
            height: 24px;
            margin-bottom: 10px;
        }

        .paypal-option span {
            display: block;
            font-size: 14px;
            color: #4a5568;
        }

        .info-box {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #0050b3;
        }

        .info-box i {
            margin-right: 10px;
            color: #1890ff;
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
            
            .paypal-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'partials/header.php'; ?>

    <main class="paiement-page">
        <div class="container">
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
                <div class="progress-line active"></div>
                <div class="progress-step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Paiement</div>
                </div>
            </div>

            <div class="paiement-container">
                <!-- Section paiement -->
                <div class="paiement-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Paiement sécurisé
                    </h2>

                    <!-- Adresse de livraison -->
                    <div class="adresse-info">
                        <?php if (isset($_SESSION['adresse_livraison'])): ?>
                            <?php $adresse = $_SESSION['adresse_livraison']; ?>
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <p class="adresse-line"><strong><?php echo htmlspecialchars(($adresse['prenom'] ?? '') . ' ' . ($adresse['nom'] ?? '')); ?></strong></p>
                                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['adresse'] ?? ''); ?></p>
                                    <?php if (!empty($adresse['complement'])): ?>
                                        <p class="adresse-line"><?php echo htmlspecialchars($adresse['complement']); ?></p>
                                    <?php endif; ?>
                                    <p class="adresse-line"><?php echo htmlspecialchars(($adresse['code_postal'] ?? '') . ' ' . ($adresse['ville'] ?? '')); ?></p>
                                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['pays'] ?? 'France'); ?></p>
                                    <p class="adresse-line"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adresse['email'] ?? ''); ?></p>
                                    <?php if (!empty($adresse['telephone'])): ?>
                                        <p class="adresse-line"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adresse['telephone']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="livraison.php" style="color: #5a67d8; text-decoration: none;">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Paiement 100% sécurisé</strong> - Tous les paiements sont traités par PayPal. 
                        Vous pouvez payer avec votre compte PayPal ou directement par carte bancaire.
                    </div>

                    <!-- Options PayPal -->
                    <div class="paypal-options">
                        <div class="paypal-option selected" id="paypal-express">
                            <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal Express">
                            <span>Payer avec PayPal</span>
                            <small>Compte PayPal requis</small>
                        </div>
                        <div class="paypal-option" id="paypal-cards">
                            <i class="fas fa-credit-card" style="font-size: 24px; color: #635bff; margin-bottom: 10px;"></i>
                            <span>Payer par carte</span>
                            <small>Sans compte PayPal</small>
                        </div>
                    </div>

                    <!-- Container PayPal -->
                    <div id="paypal-button-container"></div>

                    <!-- Informations carte via PayPal (optionnel) -->
                    <div id="card-info" style="display: none; margin-top: 20px; padding: 20px; background: #f7fafc; border-radius: 8px;">
                        <p><i class="fas fa-credit-card"></i> <strong>Payer par carte bancaire</strong></p>
                        <p style="font-size: 14px; color: #718096; margin-top: 10px;">
                            Vous serez redirigé vers PayPal pour saisir vos informations de carte.
                            Aucun compte PayPal n'est nécessaire.
                        </p>
                        <div style="display: flex; gap: 15px; margin: 15px 0;">
                            <i class="fab fa-cc-visa" style="font-size: 32px; color: #1434cb"></i>
                            <i class="fab fa-cc-mastercard" style="font-size: 32px; color: #eb001b"></i>
                            <i class="fab fa-cc-amex" style="font-size: 32px; color: #2e77bc"></i>
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

                    <!-- Note de sécurité -->
                    <p class="securite-note">
                        <i class="fas fa-shield-alt"></i>
                        Transaction cryptée SSL 256-bit - Vos données bancaires sont protégées
                    </p>
                </div>

                <!-- Récapitulatif -->
                <div class="recap-section">
                    <h3 class="section-title">
                        <i class="fas fa-receipt"></i> Récapitulatif
                    </h3>

                    <div id="commandeDetails">
                        <div style="margin-bottom: 20px;">
                            <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                                <i class="fas fa-box"></i> 
                                <?php 
                                    $nb_articles = isset($_SESSION['panier']['items_count']) ? $_SESSION['panier']['items_count'] : 
                                                  (isset($_SESSION['panier']['items']) ? count($_SESSION['panier']['items']) : 0);
                                    echo $nb_articles . ' article(s)';
                                ?>
                            </p>
                            <p style="color: #718096; font-size: 14px;">
                                <i class="fas fa-truck"></i> 
                                Livraison <?php echo isset($_SESSION['mode_livraison']) ? htmlspecialchars($_SESSION['mode_livraison']) : 'standard'; ?>
                            </p>
                            <?php if (isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau']): ?>
                                <p style="color: #718096; font-size: 14px;">
                                    <i class="fas fa-gift"></i> Emballage cadeau
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="summary-details">
                        <div class="summary-item">
                            <span>Sous-total</span>
                            <span><?php echo number_format($sous_total, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="summary-item">
                            <span>Livraison</span>
                            <span>
                                <?php if ($frais_livraison == 0): ?>
                                    Gratuite
                                <?php else: ?>
                                    <?php echo number_format($frais_livraison, 2, ',', ' '); ?> €
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($frais_emballage > 0): ?>
                        <div class="summary-item">
                            <span>Emballage cadeau</span>
                            <span><?php echo number_format($frais_emballage, 2, ',', ' '); ?> €</span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-item total">
                            <span>Total</span>
                            <span><?php echo number_format($total, 2, ',', ' '); ?> €</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <p style="font-size: 12px; color: #718096; margin: 0">
                            <i class="fas fa-info-circle"></i>
                            Après paiement, vous serez redirigé vers la page de confirmation.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypal_client_id; ?>&currency=EUR&intent=capture&enable-funding=card"></script>
    
    <script>
        // Variables globales
        const totalAmount = <?php echo $total; ?>;
        let paypalMode = 'paypal'; // 'paypal' ou 'card'
        
        // Configuration PayPal
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: paypalMode === 'card' ? 'blue' : 'gold',
                shape: 'rect',
                label: paypalMode === 'card' ? 'pay' : 'paypal',
                fundingSource: paypalMode === 'card' ? paypal.FUNDING.CARD : undefined
            },
            
            // Étape 1 : Créer la transaction
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: totalAmount.toFixed(2),
                            currency_code: 'EUR',
                            breakdown: {
                                item_total: {
                                    value: <?php echo $sous_total; ?>.toFixed(2),
                                    currency_code: 'EUR'
                                },
                                shipping: {
                                    value: <?php echo $frais_livraison; ?>.toFixed(2),
                                    currency_code: 'EUR'
                                },
                                handling: {
                                    value: <?php echo $frais_emballage; ?>.toFixed(2),
                                    currency_code: 'EUR'
                                }
                            }
                        },
                        description: 'Commande HEURE DU CADEAU',
                        items: [
                            <?php if (isset($_SESSION['panier']['items'])): ?>
                                <?php foreach ($_SESSION['panier']['items'] as $item): ?>
                                {
                                    name: '<?php echo addslashes($item['nom'] ?? 'Produit'); ?>',
                                    unit_amount: {
                                        value: <?php echo $item['prix'] ?? 0; ?>.toFixed(2),
                                        currency_code: 'EUR'
                                    },
                                    quantity: <?php echo $item['quantite'] ?? 1; ?>,
                                    category: 'PHYSICAL_GOODS'
                                },
                                <?php endforeach; ?>
                            <?php endif; ?>
                            {
                                name: 'Frais de livraison',
                                unit_amount: {
                                    value: <?php echo $frais_livraison; ?>.toFixed(2),
                                    currency_code: 'EUR'
                                },
                                quantity: 1,
                                category: 'SHIPPING'
                            }
                            <?php if ($frais_emballage > 0): ?>
                            ,{
                                name: 'Emballage cadeau',
                                unit_amount: {
                                    value: <?php echo $frais_emballage; ?>.toFixed(2),
                                    currency_code: 'EUR'
                                },
                                quantity: 1,
                                category: 'DIGITAL_GOODS'
                            }
                            <?php endif; ?>
                        ],
                        shipping: {
                            name: {
                                full_name: '<?php echo addslashes(($_SESSION['adresse_livraison']['prenom'] ?? '') . ' ' . ($_SESSION['adresse_livraison']['nom'] ?? '')); ?>'
                            },
                            address: {
                                address_line_1: '<?php echo addslashes($_SESSION['adresse_livraison']['adresse'] ?? ''); ?>',
                                admin_area_2: '<?php echo addslashes($_SESSION['adresse_livraison']['ville'] ?? ''); ?>',
                                postal_code: '<?php echo addslashes($_SESSION['adresse_livraison']['code_postal'] ?? ''); ?>',
                                country_code: 'FR'
                            }
                        }
                    }]
                });
            },
            
            // Étape 2 : Approuver la transaction
            onApprove: function(data, actions) {
                // Afficher le loading
                document.getElementById('loading').style.display = 'block';
                
                // Capturer le paiement
                return actions.order.capture().then(function(details) {
                    console.log('Paiement réussi:', details);
                    
                    // Rediriger vers la page de traitement
                    window.location.href = 'paiement.php?paymentId=' + details.id + '&PayerID=' + details.payer.payer_id;
                }).catch(function(err) {
                    console.error('Erreur capture:', err);
                    alert('Erreur lors du traitement du paiement: ' + err.message);
                    document.getElementById('loading').style.display = 'none';
                });
            },
            
            // En cas d'erreur
            onError: function(err) {
                console.error('Erreur PayPal:', err);
                alert('Erreur PayPal: ' + err.message);
                document.getElementById('loading').style.display = 'none';
            },
            
            // Avant de quitter la page
            onCancel: function(data) {
                console.log('Paiement annulé par l\'utilisateur');
                // Optionnel : afficher un message
            }
            
        }).render('#paypal-button-container');
        
        // Gestion des options PayPal/Carte
        document.getElementById('paypal-express').addEventListener('click', function() {
            document.querySelectorAll('.paypal-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            paypalMode = 'paypal';
            
            // Re-render PayPal avec le bon style
            document.getElementById('paypal-button-container').innerHTML = '';
            document.getElementById('card-info').style.display = 'none';
            
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
                                value: totalAmount.toFixed(2),
                                currency_code: 'EUR'
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    document.getElementById('loading').style.display = 'block';
                    return actions.order.capture().then(function(details) {
                        window.location.href = 'paiement.php?paymentId=' + details.id + '&PayerID=' + details.payer.payer_id;
                    });
                }
            }).render('#paypal-button-container');
        });
        
        document.getElementById('paypal-cards').addEventListener('click', function() {
            document.querySelectorAll('.paypal-option').forEach(opt => opt.classList.remove('selected'));
            this.classList.add('selected');
            paypalMode = 'card';
            
            // Re-render PayPal pour les cartes
            document.getElementById('paypal-button-container').innerHTML = '';
            document.getElementById('card-info').style.display = 'block';
            
            paypal.Buttons({
                style: {
                    layout: 'vertical',
                    color: 'blue',
                    shape: 'rect',
                    label: 'pay',
                    fundingSource: paypal.FUNDING.CARD
                },
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: totalAmount.toFixed(2),
                                currency_code: 'EUR'
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    document.getElementById('loading').style.display = 'block';
                    return actions.order.capture().then(function(details) {
                        window.location.href = 'paiement.php?paymentId=' + details.id + '&PayerID=' + details.payer.payer_id;
                    });
                }
            }).render('#paypal-button-container');
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Par défaut, on affiche PayPal Express
            document.getElementById('paypal-express').click();
        });
    </script>
</body>
</html>