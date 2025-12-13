<?php
session_start();
require_once 'config.php';

// Vérifier si l'utilisateur a une session panier
if (!isset($_SESSION['panier_id'])) {
    header('Location: panier.php');
    exit;
}

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les informations du panier
$panier_id = $_SESSION['panier_id'];
$panierTotal = 0;
$totalArticles = 0;

// Récupérer les items du panier
$sqlPanier = "SELECT pi.*, p.nom, p.reference, p.prix_ttc 
              FROM panier_items pi 
              JOIN produits p ON pi.id_produit = p.id_produit 
              WHERE pi.id_panier = ?";
$stmtPanier = $pdo->prepare($sqlPanier);
$stmtPanier->execute([$panier_id]);
$itemsPanier = $stmtPanier->fetchAll(PDO::FETCH_ASSOC);

foreach ($itemsPanier as $item) {
    $panierTotal += $item['prix_unitaire'] * $item['quantite'];
    $totalArticles += $item['quantite'];
}

// Récupérer l'adresse de livraison si elle existe en session
$adresseLivraison = null;
if (isset($_SESSION['adresse_livraison'])) {
    $adresseLivraison = $_SESSION['adresse_livraison'];
}

// Si pas d'adresse en session, vérifier en base de données
if (!$adresseLivraison && isset($_SESSION['client_id'])) {
    $sqlAdresse = "SELECT * FROM adresses 
                   WHERE id_client = ? AND type_adresse = 'livraison' 
                   ORDER BY principale DESC LIMIT 1";
    $stmtAdresse = $pdo->prepare($sqlAdresse);
    $stmtAdresse->execute([$_SESSION['client_id']]);
    $adresseLivraison = $stmtAdresse->fetch(PDO::FETCH_ASSOC);
    
    if ($adresseLivraison) {
        $_SESSION['adresse_livraison'] = $adresseLivraison;
    }
}

// Si toujours pas d'adresse, rediriger vers livraison.php
if (!$adresseLivraison) {
    header('Location: livraison.php');
    exit;
}

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'process_payment') {
        // Générer un numéro de commande
        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        try {
            $pdo->beginTransaction();
            
            // 1. Créer la commande
            $sqlCommande = "INSERT INTO commandes (
                numero_commande, id_client, id_adresse_livraison, id_adresse_facturation,
                statut, sous_total, frais_livraison, total_ttc, mode_paiement,
                statut_paiement, reference_paypal
            ) VALUES (?, ?, ?, ?, 'en_attente', ?, 0, ?, ?, 'en_attente', ?)";
            
            $stmtCommande = $pdo->prepare($sqlCommande);
            $stmtCommande->execute([
                $numeroCommande,
                $_SESSION['client_id'] ?? null,
                $adresseLivraison['id_adresse'],
                $adresseLivraison['id_adresse'],
                $panierTotal,
                $panierTotal,
                $_POST['payment_method'] ?? 'paypal',
                $_POST['paypal_reference'] ?? null
            ]);
            
            $commande_id = $pdo->lastInsertId();
            
            // 2. Ajouter les items de la commande
            $sqlItem = "INSERT INTO commande_items (
                id_commande, id_produit, reference_produit, nom_produit,
                quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
            ) SELECT 
                ?, pi.id_produit, p.reference, p.nom,
                pi.quantite, p.prix_ht, p.prix_ttc, p.tva
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            WHERE pi.id_panier = ?";
            
            $stmtItem = $pdo->prepare($sqlItem);
            $stmtItem->execute([$commande_id, $panier_id]);
            
            // 3. Vider le panier
            $sqlDeletePanier = "DELETE FROM panier_items WHERE id_panier = ?";
            $stmtDelete = $pdo->prepare($sqlDeletePanier);
            $stmtDelete->execute([$panier_id]);
            
            $sqlDeletePanier2 = "DELETE FROM panier WHERE id_panier = ?";
            $stmtDelete2 = $pdo->prepare($sqlDeletePanier2);
            $stmtDelete2->execute([$panier_id]);
            
            $pdo->commit();
            
            // Nettoyer la session
            unset($_SESSION['panier_id']);
            
            // Rediriger vers la confirmation
            header('Location: confirmation.php?commande=' . $numeroCommande);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Erreur lors du traitement de la commande: " . $e->getMessage();
        }
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
    <link rel="stylesheet" href="css/paiement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-container">
            <a href="index.html" class="logo">
                <i class="fas fa-gift logo-icon"></i>
                <span class="logo-text">HEURE<span class="logo-highlight">DU CADEAU</span></span>
            </a>
            <nav class="nav-main">
                <ul class="nav-list">
                    <li><a href="index.html" class="nav-link"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="produits.php" class="nav-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                    <li><a href="apropos.html" class="nav-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                    <li><a href="contact.html" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
                    <li><a href="panier.php" class="nav-link cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <span class="cart-count"><?php echo $totalArticles; ?></span>
                    </a></li>
                </ul>
            </nav>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>
        <nav class="nav-mobile" id="navMobile">
            <ul class="nav-mobile-list">
                <li><a href="index.html" class="nav-mobile-link"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="produits.php" class="nav-mobile-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                <li><a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a></li>
                <li><a href="panier.php" class="nav-mobile-link"><i class="fas fa-shopping-cart"></i> Panier</a></li>
            </ul>
        </nav>
    </header>

    <main class="paiement-page">
        <div class="container">
            <div class="paiement-progress">
                <div class="progress-step completed">
                    <span class="step-number">1</span>
                    <span class="step-text">Panier</span>
                </div>
                <div class="progress-line completed"></div>
                <div class="progress-step completed">
                    <span class="step-number">2</span>
                    <span class="step-text">Livraison</span>
                </div>
                <div class="progress-line active"></div>
                <div class="progress-step active">
                    <span class="step-number">3</span>
                    <span class="step-text">Paiement</span>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="paymentForm">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="payment_method" id="paymentMethod" value="paypal">
                <input type="hidden" name="paypal_reference" id="paypalReference" value="">
                
                <div class="paiement-layout">
                    <!-- Section récapitulative -->
                    <div class="recap-section">
                        <h2><i class="fas fa-receipt"></i> Récapitulatif</h2>
                        <div class="recap-card">
                            <div class="recap-row">
                                <span>Articles</span>
                                <span id="recapArticles"><?php echo $totalArticles; ?></span>
                            </div>
                            <div class="recap-row">
                                <span>Sous-total</span>
                                <span id="recapSousTotal"><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                            </div>
                            <div class="recap-row">
                                <span>Livraison</span>
                                <span class="free-shipping">Gratuite</span>
                            </div>
                            <div class="recap-row">
                                <span>Emballage cadeau</span>
                                <span id="recapEmballage">0,00 €</span>
                            </div>
                            <div class="recap-divider"></div>
                            <div class="recap-row total">
                                <span>Total TTC</span>
                                <span id="recapTotal"><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                            </div>
                        </div>

                        <div class="adresse-livraison">
                            <h3><i class="fas fa-truck"></i> Adresse de livraison</h3>
                            <div id="adresseDisplay">
                                <?php if ($adresseLivraison): ?>
                                <p><strong><?php echo htmlspecialchars($adresseLivraison['prenom'] . ' ' . $adresseLivraison['nom']); ?></strong></p>
                                <p><?php echo htmlspecialchars($adresseLivraison['adresse']); ?></p>
                                <p><?php echo htmlspecialchars($adresseLivraison['code_postal'] . ' ' . $adresseLivraison['ville']); ?></p>
                                <p><?php echo htmlspecialchars($adresseLivraison['pays']); ?></p>
                                <?php if (!empty($adresseLivraison['telephone'])): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adresseLivraison['telephone']); ?></p>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="btn-modifier" onclick="window.location.href='livraison.php'">Modifier</button>
                        </div>
                    </div>

                    <!-- Section choix paiement -->
                    <div class="paiement-section">
                        <h2><i class="fas fa-credit-card"></i> Choisissez votre mode de paiement</h2>
                        
                        <!-- Option PayPal -->
                        <div class="paiement-option active" id="optionPayPal">
                            <div class="option-header">
                                <input type="radio" name="paiement" id="paypalRadio" value="paypal" checked 
                                       onchange="document.getElementById('paymentMethod').value='paypal'">
                                <label for="paypalRadio">
                                    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal">
                                    <span>PayPal</span>
                                </label>
                            </div>
                            <div class="option-body">
                                <p><i class="fas fa-check-circle"></i> Paiement sécurisé par carte bancaire</p>
                                <p><i class="fas fa-check-circle"></i> Pas besoin de compte PayPal</p>
                                <p><i class="fas fa-check-circle"></i> Protection de l'acheteur incluse</p>
                                
                                <div class="paypal-buttons" id="paypal-button-container">
                                    <!-- Boutons PayPal seront injectés ici -->
                                </div>
                            </div>
                        </div>

                        <!-- Option Carte bancaire -->
                        <div class="paiement-option" id="optionCarte">
                            <div class="option-header">
                                <input type="radio" name="paiement" id="carteRadio" value="carte"
                                       onchange="document.getElementById('paymentMethod').value='carte'">
                                <label for="carteRadio">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Carte bancaire</span>
                                </label>
                            </div>
                            <div class="option-body">
                                <p>Paiement sécurisé via notre système</p>
                                <div class="card-icons">
                                    <i class="fab fa-cc-visa"></i>
                                    <i class="fab fa-cc-mastercard"></i>
                                    <i class="fab fa-cc-amex"></i>
                                </div>
                                
                                <div class="card-form">
                                    <div class="form-group">
                                        <label for="cardNumber">Numéro de carte</label>
                                        <input type="text" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19">
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="cardExpiry">Date d'expiration</label>
                                            <input type="text" id="cardExpiry" placeholder="MM/AA" maxlength="5">
                                        </div>
                                        <div class="form-group">
                                            <label for="cardCVC">CVC</label>
                                            <input type="text" id="cardCVC" placeholder="123" maxlength="3">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="cardName">Nom sur la carte</label>
                                        <input type="text" id="cardName" placeholder="JEAN DUPONT">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Bouton de confirmation -->
                        <div class="paiement-confirmation">
                            <button type="button" class="btn btn-primary btn-large" id="confirmerPaiement">
                                <i class="fas fa-lock"></i> Confirmer le paiement
                            </button>
                            <p class="securite-note">
                                <i class="fas fa-shield-alt"></i>
                                Paiement 100% sécurisé - Vos données sont cryptées
                            </p>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <!-- Footer identique aux autres pages -->
    </footer>

    <!-- Scripts -->
    <?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=EUR"></script>
    <?php endif; ?>
    <script src="js/main.js"></script>
    <script>
        // Variables globales
        let panierTotal = <?php echo $panierTotal; ?>;
        let paymentMethod = 'paypal';

        // Gestion du changement de méthode de paiement
        document.querySelectorAll('input[name="paiement"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const value = this.value;
                paymentMethod = value;
                
                // Mettre à jour l'interface
                document.querySelectorAll('.paiement-option').forEach(option => {
                    option.classList.remove('active');
                });
                document.getElementById('option' + value.charAt(0).toUpperCase() + value.slice(1)).classList.add('active');
                
                // Mettre à jour le champ hidden
                document.getElementById('paymentMethod').value = value;
            });
        });

        // Initialisation PayPal
        <?php if (defined('PAYPAL_CLIENT_ID') && PAYPAL_CLIENT_ID && $panierTotal > 0): ?>
        function initialiserPayPal() {
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
                                value: panierTotal.toFixed(2),
                                currency_code: 'EUR'
                            }
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Enregistrer la référence PayPal
                        document.getElementById('paypalReference').value = details.id;
                        // Soumettre le formulaire
                        document.getElementById('paymentForm').submit();
                    });
                },
                onError: function(err) {
                    console.error('Erreur PayPal:', err);
                    alert('Une erreur est survenue lors du paiement PayPal.');
                }
            }).render('#paypal-button-container');
        }

        // Initialiser PayPal si l'option est sélectionnée
        if (document.getElementById('paypalRadio').checked) {
            initialiserPayPal();
        }
        <?php endif; ?>

        // Gérer la confirmation de paiement
        document.getElementById('confirmerPaiement').addEventListener('click', function() {
            if (paymentMethod === 'paypal') {
                // Déclencher PayPal
                const paypalButton = document.querySelector('#paypal-button-container button');
                if (paypalButton) {
                    paypalButton.click();
                } else {
                    alert('Le système PayPal n\'est pas disponible. Veuillez réessayer.');
                }
            } else if (paymentMethod === 'carte') {
                // Validation du formulaire carte
                const cardNumber = document.getElementById('cardNumber').value.trim();
                const cardExpiry = document.getElementById('cardExpiry').value.trim();
                const cardCVC = document.getElementById('cardCVC').value.trim();
                const cardName = document.getElementById('cardName').value.trim();
                
                // Validation basique
                if (!cardNumber || !cardExpiry || !cardCVC || !cardName) {
                    alert('Veuillez remplir tous les champs de la carte bancaire.');
                    return;
                }
                
                // Validation du numéro de carte (simple)
                if (cardNumber.replace(/\s/g, '').length < 16) {
                    alert('Numéro de carte invalide.');
                    return;
                }
                
                // Soumettre le formulaire
                document.getElementById('paymentForm').submit();
            }
        });

        // Formatage des champs carte
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '');
            value = value.replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        document.getElementById('cardExpiry').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);
            
            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2);
            }
            e.target.value = value;
        });

        document.getElementById('cardCVC').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substr(0, 3);
        });
    </script>
</body>
</html>