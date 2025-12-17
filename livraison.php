<?php
session_start();

// Rediriger si panier vide
if (!isset($_SESSION['panier']) || empty($_SESSION['panier']['items'])) {
    header('Location: panier.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .livraison-page {
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

        .livraison-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 40px;
            margin-top: 30px;
        }

        .form-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .summary-section {
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

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .required::after {
            content: " *";
            color: #e53e3e;
        }

        input, select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .livraison-options {
            margin-top: 30px;
        }

        .livraison-option {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .livraison-option:hover {
            border-color: #cbd5e0;
        }

        .livraison-option.selected {
            border-color: #5a67d8;
            background: rgba(90, 103, 216, 0.05);
        }

        .option-icon {
            font-size: 24px;
            color: #718096;
        }

        .option-content {
            flex: 1;
        }

        .option-title {
            font-weight: 600;
            color: #2d3748;
            display: block;
            margin-bottom: 5px;
        }

        .option-desc {
            color: #718096;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        .option-price {
            font-weight: 600;
            color: #38a169;
        }

        input[type="radio"] {
            width: auto;
            margin-right: 10px;
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

        .item-list {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 20px;
        }

        .item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .item-name {
            color: #4a5568;
            max-width: 70%;
        }

        .item-quantity {
            color: #718096;
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

        .error-message {
            color: #e53e3e;
            font-size: 14px;
            margin-top: 5px;
            display: none;
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

        @media (max-width: 992px) {
            .livraison-container {
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
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header (identique aux autres pages) -->
    <?php include 'partials/header.php'; ?>

    <main class="livraison-page">
        <div class="container">
            <!-- Barre de progression -->
            <div class="progress-bar">
                <div class="progress-step completed">
                    <div class="step-circle">1</div>
                    <div class="step-label">Panier</div>
                </div>
                <div class="progress-line completed"></div>
                <div class="progress-step active">
                    <div class="step-circle">2</div>
                    <div class="step-label">Livraison</div>
                </div>
                <div class="progress-line"></div>
                <div class="progress-step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Paiement</div>
                </div>
            </div>

            <div class="livraison-container">
                <!-- Formulaire de livraison -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-truck"></i> Informations de livraison</h2>
                    
                    <form id="livraisonForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="prenom" class="required">Prénom</label>
                                <input type="text" id="prenom" name="prenom" required>
                                <div class="error-message" id="error-prenom"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="nom" class="required">Nom</label>
                                <input type="text" id="nom" name="nom" required>
                                <div class="error-message" id="error-nom"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email" class="required">Email</label>
                            <input type="email" id="email" name="email" required>
                            <div class="error-message" id="error-email"></div>
                        </div>

                        <div class="form-group">
                            <label for="adresse" class="required">Adresse</label>
                            <input type="text" id="adresse" name="adresse" required>
                            <div class="error-message" id="error-adresse"></div>
                        </div>

                        <div class="form-group">
                            <label for="complement">Complément d'adresse</label>
                            <input type="text" id="complement" name="complement">
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label for="code_postal" class="required">Code postal</label>
                                <input type="text" id="code_postal" name="code_postal" required pattern="[0-9]{5}">
                                <div class="error-message" id="error-code_postal"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="ville" class="required">Ville</label>
                                <input type="text" id="ville" name="ville" required>
                                <div class="error-message" id="error-ville"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pays" class="required">Pays</label>
                            <select id="pays" name="pays" required>
                                <option value="France" selected>France</option>
                                <option value="Belgique">Belgique</option>
                                <option value="Suisse">Suisse</option>
                                <option value="Luxembourg">Luxembourg</option>
                                <option value="autre">Autre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone">
                        </div>

                        <div class="form-group">
                            <label for="societe">Société (optionnel)</label>
                            <input type="text" id="societe" name="societe">
                        </div>

                        <!-- Options de livraison -->
                        <div class="livraison-options">
                            <h3 class="section-title"><i class="fas fa-shipping-fast"></i> Mode de livraison</h3>
                            
                            <div class="livraison-option selected" data-mode="standard" data-prix="0">
                                <input type="radio" name="mode_livraison" value="standard" checked hidden>
                                <div class="option-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="option-content">
                                    <span class="option-title">Livraison standard</span>
                                    <span class="option-desc">Livraison en 3-5 jours ouvrés</span>
                                    <span class="option-price">Gratuite</span>
                                </div>
                            </div>
                            
                            <div class="livraison-option" data-mode="express" data-prix="9.90">
                                <input type="radio" name="mode_livraison" value="express" hidden>
                                <div class="option-icon">
                                    <i class="fas fa-bolt"></i>
                                </div>
                                <div class="option-content">
                                    <span class="option-title">Livraison express</span>
                                    <span class="option-desc">Livraison en 24-48h</span>
                                    <span class="option-price">9,90 €</span>
                                </div>
                            </div>
                            
                            <div class="livraison-option" data-mode="relais" data-prix="4.90">
                                <input type="radio" name="mode_livraison" value="relais" hidden>
                                <div class="option-icon">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div class="option-content">
                                    <span class="option-title">Point relais</span>
                                    <span class="option-desc">Retrait en point relais</span>
                                    <span class="option-price">4,90 €</span>
                                </div>
                            </div>
                        </div>

                        <!-- Emballage cadeau -->
                        <div class="form-group" style="margin-top: 30px;">
                            <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                                <input type="checkbox" id="emballage_cadeau" name="emballage_cadeau" value="1">
                                <span>
                                    <i class="fas fa-gift"></i> 
                                    <strong>Emballage cadeau</strong> (+3,90 €)
                                </span>
                            </label>
                            <p style="color: #718096; font-size: 14px; margin-top: 5px; margin-left: 30px;">
                                Votre cadeau sera emballé avec soin dans un joli papier cadeau.
                            </p>
                        </div>

                        <!-- Boutons -->
                        <div style="display: flex; gap: 15px; margin-top: 40px;">
                            <a href="panier.html" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au panier
                            </a>
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-arrow-right"></i> Continuer vers le paiement
                            </button>
                        </div>
                    </form>

                    <!-- Loading -->
                    <div class="loading" id="loading">
                        <i class="fas fa-spinner"></i>
                        <p>Traitement en cours...</p>
                    </div>
                </div>

                <!-- Récapitulatif -->
                <div class="summary-section">
                    <h3 class="section-title"><i class="fas fa-receipt"></i> Récapitulatif</h3>
                    
                    <div class="item-list" id="cartSummary">
                        <!-- Chargé par JavaScript -->
                    </div>
                    
                    <div class="summary-details">
                        <div class="summary-item">
                            <span>Sous-total</span>
                            <span id="subtotal">0,00 €</span>
                        </div>
                        <div class="summary-item">
                            <span>Livraison</span>
                            <span id="shipping">0,00 €</span>
                        </div>
                        <div class="summary-item">
                            <span>Emballage cadeau</span>
                            <span id="giftwrap">0,00 €</span>
                        </div>
                        <div class="summary-item total">
                            <span>Total</span>
                            <span id="total">0,00 €</span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <p style="font-size: 12px; color: #718096; margin: 0;">
                            <i class="fas fa-lock"></i> 
                            Paiement 100% sécurisé par SSL
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <script>
        // Configuration
        const API_BASE_URL = '/api';
        let panierData = null;
        let fraisLivraison = 0;
        let fraisEmballage = 0;

        // Charger le panier au démarrage
        async function chargerPanier() {
            try {
                const response = await fetch(API_BASE_URL + '/panier.php?action=get');
                const data = await response.json();
                
                if (data.success) {
                    panierData = data;
                    afficherRecapitulatif();
                    calculerTotaux();
                } else {
                    console.error('Erreur chargement panier:', data.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        }

        // Afficher le récapitulatif
        function afficherRecapitulatif() {
            const container = document.getElementById('cartSummary');
            if (!container || !panierData) return;
            
            let html = '';
            panierData.items.forEach(item => {
                html += `
                    <div class="item">
                        <span class="item-name">${item.nom} × ${item.quantite}</span>
                        <span>${item.total_item} €</span>
                    </div>
                `;
            });
            
            container.innerHTML = html;
            
            // Mettre à jour les totaux
            document.getElementById('subtotal').textContent = 
                parseFloat(panierData.total_prix).toFixed(2).replace('.', ',') + ' €';
        }

        // Calculer les totaux
        function calculerTotaux() {
            if (!panierData) return;
            
            const sousTotal = parseFloat(panierData.total_prix) || 0;
            const total = sousTotal + fraisLivraison + fraisEmballage;
            
            document.getElementById('shipping').textContent = 
                fraisLivraison.toFixed(2).replace('.', ',') + ' €';
            document.getElementById('giftwrap').textContent = 
                fraisEmballage.toFixed(2).replace('.', ',') + ' €';
            document.getElementById('total').textContent = 
                total.toFixed(2).replace('.', ',') + ' €';
        }

        // Gestion des options de livraison
        document.querySelectorAll('.livraison-option').forEach(option => {
            option.addEventListener('click', function() {
                // Désélectionner toutes les options
                document.querySelectorAll('.livraison-option').forEach(opt => {
                    opt.classList.remove('selected');
                    opt.querySelector('input[type="radio"]').checked = false;
                });
                
                // Sélectionner l'option cliquée
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
                
                // Mettre à jour les frais
                fraisLivraison = parseFloat(this.dataset.prix) || 0;
                calculerTotaux();
            });
        });

        // Gestion de l'emballage cadeau
        document.getElementById('emballage_cadeau').addEventListener('change', function() {
            fraisEmballage = this.checked ? 3.90 : 0;
            document.getElementById('giftwrap').textContent = 
                fraisEmballage.toFixed(2).replace('.', ',') + ' €';
            calculerTotaux();
        });

        // Validation du formulaire
        function validerFormulaire(formData) {
            const errors = {};
            const required = ['prenom', 'nom', 'email', 'adresse', 'code_postal', 'ville'];
            
            required.forEach(field => {
                if (!formData.get(field)?.trim()) {
                    errors[field] = 'Ce champ est obligatoire';
                }
            });
            
            // Validation email
            const email = formData.get('email');
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errors.email = 'Email invalide';
            }
            
            // Validation code postal
            const codePostal = formData.get('code_postal');
            if (codePostal && !/^[0-9]{5}$/.test(codePostal)) {
                errors.code_postal = 'Code postal invalide (5 chiffres)';
            }
            
            return errors;
        }

        // Soumission du formulaire
        document.getElementById('livraisonForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            // Afficher le loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('submitBtn').disabled = true;
            
            // Cacher les erreurs précédentes
            document.querySelectorAll('.error-message').forEach(el => {
                el.style.display = 'none';
                el.textContent = '';
            });
            
            // Récupérer les données du formulaire
            const formData = new FormData(this);
            const data = Object.fromEntries(formData);
            
            // Ajouter les frais
            data.frais_livraison = fraisLivraison;
            data.emballage_cadeau = document.getElementById('emballage_cadeau').checked;
            
            // Validation
            const errors = validerFormulaire(formData);
            
            if (Object.keys(errors).length > 0) {
                // Afficher les erreurs
                Object.keys(errors).forEach(field => {
                    const errorEl = document.getElementById(`error-${field}`);
                    if (errorEl) {
                        errorEl.textContent = errors[field];
                        errorEl.style.display = 'block';
                    }
                });
                
                document.getElementById('loading').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;
                return;
            }
            
            try {
                // Envoyer les données à l'API
                const response = await fetch(API_BASE_URL + '/commande.php?action=save_adresse', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Redirection vers la page de paiement
                    window.location.href = 'paiement.html';
                } else {
                    alert('Erreur: ' + result.message);
                    document.getElementById('loading').style.display = 'none';
                    document.getElementById('submitBtn').disabled = false;
                }
                
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue. Veuillez réessayer.');
                document.getElementById('loading').style.display = 'none';
                document.getElementById('submitBtn').disabled = false;
            }
        });

        // Formatage du téléphone
        document.getElementById('telephone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.substr(0, 10);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 2 || i === 4 || i === 6 || i === 8) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        // Charger au démarrage
        document.addEventListener('DOMContentLoaded', function() {
            chargerPanier();
            
            // Pré-remplir si des données existent
            const savedData = sessionStorage.getItem('livraisonData');
            if (savedData) {
                try {
                    const data = JSON.parse(savedData);
                    Object.keys(data).forEach(key => {
                        const element = document.getElementById(key);
                        if (element) {
                            if (element.type === 'checkbox') {
                                element.checked = data[key];
                            } else {
                                element.value = data[key];
                            }
                        }
                    });
                } catch (e) {
                    console.error('Erreur chargement données:', e);
                }
            }
        });
    </script>
</body>
</html>