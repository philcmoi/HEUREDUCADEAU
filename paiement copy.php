<?php
session_start();
require_once 'config.php';
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
    <!-- Header (identique aux autres pages) -->
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
                    <li><a href="panier.html" class="nav-link cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <span class="cart-count">0</span>
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
                <li><a href="panier.html" class="nav-mobile-link"><i class="fas fa-shopping-cart"></i> Panier</a></li>
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

            <div class="paiement-layout">
                <!-- Section récapitulative -->
                <div class="recap-section">
                    <h2><i class="fas fa-receipt"></i> Récapitulatif</h2>
                    <div class="recap-card">
                        <div class="recap-row">
                            <span>Articles</span>
                            <span id="recapArticles">0</span>
                        </div>
                        <div class="recap-row">
                            <span>Sous-total</span>
                            <span id="recapSousTotal">0,00 €</span>
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
                            <span id="recapTotal">0,00 €</span>
                        </div>
                    </div>

                    <div class="adresse-livraison">
                        <h3><i class="fas fa-truck"></i> Adresse de livraison</h3>
                        <div id="adresseDisplay">
                            <!-- Adresse chargée dynamiquement -->
                        </div>
                        <button class="btn-modifier" onclick="modifierAdresse()">Modifier</button>
                    </div>
                </div>

                <!-- Section choix paiement -->
                <div class="paiement-section">
                    <h2><i class="fas fa-credit-card"></i> Choisissez votre mode de paiement</h2>
                    
                    <!-- Option PayPal -->
                    <div class="paiement-option active" id="optionPayPal">
                        <div class="option-header">
                            <input type="radio" name="paiement" id="paypalRadio" checked>
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
                            
                            <div class="alternative-paypal">
                                <p>Ou utilisez votre adresse email :</p>
                                <div class="email-paiement">
                                    <input type="email" id="paypalEmail" placeholder="votre@email.com">
                                    <button class="btn btn-secondary" onclick="payerParEmail()">
                                        <i class="fas fa-envelope"></i> Payer par email
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Option Carte bancaire directe (via PayPal) -->
                    <div class="paiement-option" id="optionCarte">
                        <div class="option-header">
                            <input type="radio" name="paiement" id="carteRadio">
                            <label for="carteRadio">
                                <i class="fas fa-credit-card"></i>
                                <span>Carte bancaire</span>
                            </label>
                        </div>
                        <div class="option-body">
                            <p>Paiement sécurisé via PayPal</p>
                            <div class="card-icons">
                                <i class="fab fa-cc-visa"></i>
                                <i class="fab fa-cc-mastercard"></i>
                                <i class="fab fa-cc-amex"></i>
                            </div>
                            <p class="small-text">Vous serez redirigé vers PayPal pour saisir vos coordonnées</p>
                        </div>
                    </div>

                    <!-- Bouton de confirmation -->
                    <div class="paiement-confirmation">
                        <button class="btn btn-primary btn-large" id="confirmerPaiement">
                            <i class="fas fa-lock"></i> Confirmer le paiement
                        </button>
                        <p class="securite-note">
                            <i class="fas fa-shield-alt"></i>
                            Paiement 100% sécurisé - Vos données sont cryptées
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <!-- Footer identique aux autres pages -->
    </footer>

    <!-- Scripts -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo PAYPAL_CLIENT_ID; ?>&currency=EUR"></script>
    <script src="js/main.js"></script>
    <script src="js/paiement.js"></script>
    <script>
        // Variables globales
        let panierTotal = 0;
        let adresseLivraison = null;
        let emballageCadeau = false;

        // Charger les données du panier
        async function chargerDonneesPanier() {
            try {
                const response = await fetch('/api/panier.php?action=get');
                const data = await response.json();
                
                if (data.success) {
                    panierTotal = parseFloat(data.total_prix);
                    document.getElementById('recapArticles').textContent = data.total_articles;
                    document.getElementById('recapSousTotal').textContent = data.total_prix + ' €';
                    document.getElementById('recapTotal').textContent = data.total_prix + ' €';
                    
                    // Charger l'adresse de livraison
                    chargerAdresse();
                }
            } catch (error) {
                console.error('Erreur chargement panier:', error);
            }
        }

        // Charger l'adresse de livraison
        async function chargerAdresse() {
            try {
                const response = await fetch('/api/adresse.php?action=get');
                const data = await response.json();
                
                if (data.success && data.adresse) {
                    adresseLivraison = data.adresse;
                    afficherAdresse();
                } else {
                    // Rediriger vers la page livraison si pas d'adresse
                    window.location.href = 'livraison.html';
                }
            } catch (error) {
                console.error('Erreur chargement adresse:', error);
            }
        }

        function afficherAdresse() {
            const container = document.getElementById('adresseDisplay');
            if (adresseLivraison) {
                container.innerHTML = `
                    <p><strong>${adresseLivraison.prenom} ${adresseLivraison.nom}</strong></p>
                    <p>${adresseLivraison.adresse}</p>
                    <p>${adresseLivraison.code_postal} ${adresseLivraison.ville}</p>
                    <p>${adresseLivraison.pays}</p>
                    <p><i class="fas fa-phone"></i> ${adresseLivraison.telephone}</p>
                `;
            }
        }

        function modifierAdresse() {
            window.location.href = 'livraison.html';
        }

        // Initialisation PayPal
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
                                value: panierTotal.toString(),
                                currency_code: 'EUR'
                            },
                            description: 'Commande HEURE DU CADEAU'
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Traitement du paiement réussi
                        traiterPaiementReussi(details);
                    });
                },
                onError: function(err) {
                    console.error('Erreur PayPal:', err);
                    alert('Une erreur est survenue lors du paiement.');
                }
            }).render('#paypal-button-container');
        }

        // Fonction pour payer par email
        async function payerParEmail() {
            const email = document.getElementById('paypalEmail').value.trim();
            
            if (!email || !validateEmail(email)) {
                alert('Veuillez saisir une adresse email valide.');
                return;
            }

            // Créer un paiement avec l'email
            try {
                const response = await fetch('/api/paypal.php?action=create_email_payment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: email,
                        amount: panierTotal
                    })
                });
                
                const data = await response.json();
                
                if (data.success && data.approval_url) {
                    // Rediriger vers PayPal
                    window.location.href = data.approval_url;
                } else {
                    alert('Erreur lors de la création du paiement.');
                }
            } catch (error) {
                console.error('Erreur paiement email:', error);
                alert('Erreur de connexion au serveur.');
            }
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            chargerDonneesPanier();
            initialiserPayPal();
            
            // Gestion du bouton de confirmation
            document.getElementById('confirmerPaiement').addEventListener('click', function() {
                const paypalChecked = document.getElementById('paypalRadio').checked;
                const carteChecked = document.getElementById('carteRadio').checked;
                
                if (paypalChecked || carteChecked) {
                    // Déclencher le bouton PayPal
                    document.querySelector('#paypal-button-container button').click();
                }
            });
        });
    </script>
</body>
</html>