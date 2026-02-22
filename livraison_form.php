<?php
// ============================================
// PAGE DU FORMULAIRE DE LIVRAISON - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// ============================================
// VÉRIFICATION D'ACCÈS STANDARDISÉE
// ============================================
checkLivraisonAccess();

// ============================================
// CONNEXION BDD ET SYNCHRONISATION
// ============================================
$pdo = getPDOConnection();
if ($pdo) {
    synchroniserPanierSessionBDD($pdo, session_id());
}

// ============================================
// INITIALISATION DES VALEURS PAR DÉFAUT
// ============================================
// Initialiser le checkout s'il n'existe pas
if (!isset($_SESSION[SESSION_KEY_CHECKOUT])) {
    $_SESSION[SESSION_KEY_CHECKOUT] = [];
}

// Valeurs par défaut pour les options de livraison
if (!isset($_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'])) {
    $_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'] = 'standard';
}

if (!isset($_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'])) {
    $_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] = false;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
$errors = getCheckoutErrors();
$messages = getSessionMessages();

$donnees_saisies = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];
$meme_adresse_checked = !isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse']) || 
                        empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse']);

// Récupérer l'email du client si existant
if (empty($donnees_saisies['email']) && isset($_SESSION[SESSION_KEY_CHECKOUT]['client_email'])) {
    $donnees_saisies['email'] = $_SESSION[SESSION_KEY_CHECKOUT]['client_email'];
}

// Récupérer les données depuis commande_temporaire si disponibles
if (empty($donnees_saisies) && isset($_SESSION[SESSION_KEY_PANIER_ID]) && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT donnees_livraison, mode_livraison, emballage_cadeau, instructions
            FROM commande_temporaire 
            WHERE panier_id = ? 
            ORDER BY date_creation DESC LIMIT 1
        ");
        $stmt->execute([$_SESSION[SESSION_KEY_PANIER_ID]]);
        $temp_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($temp_data && !empty($temp_data['donnees_livraison'])) {
            $temp_array = json_decode($temp_data['donnees_livraison'], true);
            if (is_array($temp_array)) {
                $donnees_saisies = array_merge($donnees_saisies, $temp_array);
                $_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'] = $temp_data['mode_livraison'] ?? 'standard';
                $_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] = (bool)($temp_data['emballage_cadeau'] ?? false);
                $_SESSION[SESSION_KEY_CHECKOUT]['instructions'] = $temp_data['instructions'] ?? null;
            }
        }
    } catch (Exception $e) {
        error_log("Erreur récupération commande_temporaire: " . $e->getMessage());
    }
}

// Mettre à jour la date de modification
if (isset($_SESSION[SESSION_KEY_CHECKOUT])) {
    $_SESSION[SESSION_KEY_CHECKOUT]['date_modification'] = date('Y-m-d H:i:s');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Adresse de Livraison - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* STYLES CSS COMPLETS - Identiques à l'original */
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        .required:after {
            content: " *";
            color: #e53e3e;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        input:focus, textarea:focus, select:focus {
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
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .radio-option {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .radio-option:hover {
            border-color: #cbd5e0;
            background: #edf2f7;
        }
        .radio-option.selected {
            border-color: #5a67d8;
            background: rgba(90,103,216,0.05);
        }
        .radio-option input {
            width: auto;
            margin-right: 10px;
        }
        .radio-details {
            flex: 1;
        }
        .radio-price {
            font-weight: bold;
            color: #2d3748;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .checkbox-group input {
            width: auto;
        }
        button {
            background-color: #5a67d8;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            margin-top: 20px;
        }
        button:hover {
            background-color: #4c51bf;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90,103,216,0.3);
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
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }
        .shipping-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f7fafc;
            border-radius: 6px;
            margin-top: 5px;
            font-size: 14px;
            color: #718096;
        }
        .shipping-info i {
            color: #38a169;
        }
        .error-field {
            border-color: #e53e3e !important;
        }
        .error-message {
            color: #e53e3e;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
        .error-message.show {
            display: block;
        }
        #adresse-facturation-different {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-top: 15px;
            margin-bottom: 25px;
            display: none;
        }
        #adresse-facturation-different h3 {
            color: #4a5568;
            font-size: 16px;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #cbd5e0;
        }
        #facturation-same-checkbox {
            background: #edf2f7;
            border-color: #cbd5e0;
        }
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
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
        <h1><i class="fas fa-truck"></i> Adresse de Livraison</h1>

        <?php if (!empty($messages)): ?>
            <?php foreach ($messages as $msg): ?>
                <div class="message <?php echo $msg['type']; ?>">
                    <?php echo htmlspecialchars($msg['message']); ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="message error">
                <strong>Erreurs :</strong>
                <ul>
                    <?php foreach ($errors as $erreur): ?>
                        <li><?php echo htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div id="info-message"></div>

        <form action="livraison.php" method="POST" id="livraison-form">
            <input type="hidden" name="api_mode" value="1" />
            <input type="hidden" name="panier_id" value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_PANIER_ID] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

            <h2>Informations personnelles</h2>

            <div class="form-row">
                <div class="form-group">
                    <label for="prenom" class="required">Prénom</label>
                    <input type="text" id="prenom" name="prenom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    <div class="error-message" id="error-prenom"></div>
                </div>
                <div class="form-group">
                    <label for="nom" class="required">Nom</label>
                    <input type="text" id="nom" name="nom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    <div class="error-message" id="error-nom"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="email" class="required">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? $_SESSION[SESSION_KEY_CHECKOUT]['client_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                <div class="error-message" id="error-email"></div>
                <div class="shipping-info">
                    <i class="fas fa-info-circle"></i>
                    Votre confirmation de commande sera envoyée à cette adresse
                </div>
            </div>

            <div class="form-group">
                <label for="telephone">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" 
                       value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                <div class="error-message" id="error-telephone"></div>
                <div class="shipping-info">
                    <i class="fas fa-info-circle"></i>
                    Pour vous contacter en cas de problème de livraison
                </div>
            </div>

            <div class="form-group">
                <label for="societe">Société (optionnel)</label>
                <input type="text" id="societe" name="societe" 
                       value="<?php echo htmlspecialchars($donnees_saisies['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <h2>Adresse de livraison</h2>

            <div class="form-group">
                <label for="adresse" class="required">Adresse</label>
                <textarea id="adresse" name="adresse" rows="3" required><?php echo htmlspecialchars($donnees_saisies['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="error-message" id="error-adresse"></div>
            </div>

            <div class="form-group">
                <label for="complement">Complément d'adresse</label>
                <input type="text" id="complement" name="complement" 
                       value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="code_postal" class="required">Code postal</label>
                    <input type="text" id="code_postal" name="code_postal" 
                           value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    <div class="error-message" id="error-code_postal"></div>
                </div>
                <div class="form-group">
                    <label for="ville" class="required">Ville</label>
                    <input type="text" id="ville" name="ville" 
                           value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
                    <div class="error-message" id="error-ville"></div>
                </div>
            </div>

            <div class="form-group">
                <label for="pays" class="required">Pays</label>
                <select id="pays" name="pays" required>
                    <option value="France" <?php echo (($donnees_saisies['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                    <option value="Belgique" <?php echo (($donnees_saisies['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                    <option value="Suisse" <?php echo (($donnees_saisies['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                    <option value="Luxembourg" <?php echo (($donnees_saisies['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                    <option value="autre" <?php echo (($donnees_saisies['pays'] ?? '') === 'autre') ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>

            <h2>Adresse de facturation</h2>

            <div class="checkbox-group" id="facturation-same-checkbox">
                <input type="checkbox" id="meme_adresse_facturation" name="meme_adresse_facturation" value="1" 
                       <?php echo $meme_adresse_checked ? 'checked' : ''; ?> />
                <div>
                    <label for="meme_adresse_facturation" style="font-weight: bold">
                        <i class="fas fa-file-invoice"></i> Utiliser la même adresse pour la facturation
                    </label>
                    <p style="margin: 5px 0 0 0; color: #718096; font-size: 14px">
                        Si décocher, vous pourrez saisir une adresse de facturation différente
                    </p>
                </div>
            </div>

            <div id="adresse-facturation-different" style="display: <?php echo $meme_adresse_checked ? 'none' : 'block'; ?>;">
                <h3>Adresse de facturation différente</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="facturation_prenom">Prénom (facturation)</label>
                        <input type="text" id="facturation_prenom" name="facturation_prenom" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                    <div class="form-group">
                        <label for="facturation_nom">Nom (facturation)</label>
                        <input type="text" id="facturation_nom" name="facturation_nom" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="facturation_societe">Société (facturation, optionnel)</label>
                    <input type="text" id="facturation_societe" name="facturation_societe" 
                           value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                
                <div class="form-group">
                    <label for="facturation_adresse">Adresse (facturation)</label>
                    <textarea id="facturation_adresse" name="facturation_adresse" rows="3" 
                              <?php echo !$meme_adresse_checked ? 'required' : ''; ?>><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="facturation_complement">Complément d'adresse (facturation)</label>
                    <input type="text" id="facturation_complement" name="facturation_complement" 
                           value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="facturation_code_postal">Code postal (facturation)</label>
                        <input type="text" id="facturation_code_postal" name="facturation_code_postal" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                    <div class="form-group">
                        <label for="facturation_ville">Ville (facturation)</label>
                        <input type="text" id="facturation_ville" name="facturation_ville" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="facturation_pays">Pays (facturation)</label>
                    <select id="facturation_pays" name="facturation_pays">
                        <option value="France" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                        <option value="Belgique" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                        <option value="Suisse" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                        <option value="Luxembourg" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                        <option value="autre" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? '') === 'autre') ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
            </div>

            <h2>Options de livraison</h2>

            <div class="radio-group" id="livraisonOptions">
                <?php 
                $mode_livraison = $_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'] ?? 'standard';
                ?>
                <div class="radio-option <?php echo ($mode_livraison == 'standard') ? 'selected' : ''; ?>" data-value="standard">
                    <input type="radio" name="mode_livraison" value="standard" <?php echo ($mode_livraison == 'standard') ? 'checked' : ''; ?> />
                    <div class="radio-details">
                        <strong>Livraison Standard</strong>
                        <p>Livraison en 3-5 jours ouvrés</p>
                    </div>
                    <div class="radio-price">Gratuite</div>
                </div>

                <div class="radio-option <?php echo ($mode_livraison == 'express') ? 'selected' : ''; ?>" data-value="express">
                    <input type="radio" name="mode_livraison" value="express" <?php echo ($mode_livraison == 'express') ? 'checked' : ''; ?> />
                    <div class="radio-details">
                        <strong>Livraison Express</strong>
                        <p>Livraison en 24h (hors week-end)</p>
                    </div>
                    <div class="radio-price">9,90 €</div>
                </div>

                <div class="radio-option <?php echo ($mode_livraison == 'relais') ? 'selected' : ''; ?>" data-value="relais">
                    <input type="radio" name="mode_livraison" value="relais" <?php echo ($mode_livraison == 'relais') ? 'checked' : ''; ?> />
                    <div class="radio-details">
                        <strong>Point Relais</strong>
                        <p>Retrait dans un point relais partenaire</p>
                    </div>
                    <div class="radio-price">4,90 €</div>
                </div>
            </div>

            <h2>Options supplémentaires</h2>

            <div class="checkbox-group">
                <input type="checkbox" id="emballage_cadeau" name="emballage_cadeau" value="1"
                       <?php echo ($_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] ?? false) ? 'checked' : ''; ?> />
                <div>
                    <label for="emballage_cadeau" style="font-weight: bold">
                        <i class="fas fa-gift"></i> Emballage cadeau
                    </label>
                    <p style="margin: 5px 0 0 0; color: #718096; font-size: 14px">
                        Emballage élégant avec carte personnalisée - <strong>+3,90 €</strong>
                    </p>
                </div>
            </div>

            <div class="form-group">
                <label for="instructions">Instructions de livraison (optionnel)</label>
                <textarea id="instructions" name="instructions" rows="2"
                    placeholder="Ex: Sonner au portail rouge, livrer au gardien, etc."><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button type="submit" id="submit-btn">
                <i class="fas fa-arrow-right"></i> Continuer vers le paiement
            </button>

            <div style="text-align: center; margin-top: 20px; color: #718096; font-size: 14px;">
                <i class="fas fa-lock"></i> Vos données sont protégées
            </div>
        </form>
    </div>

    <script>
    // ============================================
    // JAVASCRIPT - Identique à l'original
    // ============================================
    let isLoading = false;
    
    function displayExistingAddress(address) {
        const messageDiv = document.getElementById('info-message');
        if (!messageDiv) return;
        
        messageDiv.className = 'message success';
        messageDiv.innerHTML = `
            <strong><i class="fas fa-check-circle"></i> Adresse déjà enregistrée :</strong><br>
            ${address.prenom || ''} ${address.nom || ''}<br>
            ${address.adresse || ''}<br>
            ${address.complement ? address.complement + '<br>' : ''}
            ${address.code_postal || ''} ${address.ville || ''}<br>
            ${address.pays || 'France'}<br>
            <small>Vous pouvez modifier ces informations ci-dessous si nécessaire.</small>
        `;
    }

    function setupFacturationToggle() {
        const sameAddressCheckbox = document.getElementById('meme_adresse_facturation');
        const facturationDiv = document.getElementById('adresse-facturation-different');
        
        if (!sameAddressCheckbox || !facturationDiv) return;
        
        sameAddressCheckbox.addEventListener('change', function(e) {
            if (this.checked) {
                facturationDiv.style.display = 'none';
                const facturationFields = facturationDiv.querySelectorAll('input, textarea, select');
                facturationFields.forEach(field => field.removeAttribute('required'));
            } else {
                facturationDiv.style.display = 'block';
                const requiredFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                       'facturation_code_postal', 'facturation_ville'];
                requiredFields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) field.setAttribute('required', 'required');
                });
            }
        });
        
        sameAddressCheckbox.addEventListener('click', function() {
            if (!this.checked) return;
            
            const mapping = {
                'prenom': 'facturation_prenom',
                'nom': 'facturation_nom',
                'societe': 'facturation_societe',
                'adresse': 'facturation_adresse',
                'complement': 'facturation_complement',
                'code_postal': 'facturation_code_postal',
                'ville': 'facturation_ville',
                'pays': 'facturation_pays'
            };
            
            for (const [sourceId, targetId] of Object.entries(mapping)) {
                const source = document.getElementById(sourceId);
                const target = document.getElementById(targetId);
                if (source && target) {
                    target.value = source.value;
                }
            }
        });
    }

    function setupLivraisonOptions() {
        document.querySelectorAll('.radio-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.radio-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                const radio = this.querySelector('input[type="radio"]');
                if (radio) radio.checked = true;
            });
        });
    }

    function validateField(fieldId, errorId) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        
        if (!field) return true;
        
        if (!field.value.trim()) {
            field.classList.add('error-field');
            if (error) {
                error.textContent = 'Ce champ est requis';
                error.classList.add('show');
            }
            return false;
        } else {
            field.classList.remove('error-field');
            if (error) error.classList.remove('show');
            
            if (fieldId === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    field.classList.add('error-field');
                    if (error) {
                        error.textContent = 'Veuillez entrer une adresse email valide';
                        error.classList.add('show');
                    }
                    return false;
                }
            }
            
            if (fieldId === 'telephone' && field.value.trim()) {
                const phoneRegex = /^[0-9]{10}$/;
                const cleanedPhone = field.value.replace(/\s/g, '');
                if (!phoneRegex.test(cleanedPhone)) {
                    field.classList.add('error-field');
                    if (error) {
                        error.textContent = 'Veuillez entrer un numéro de téléphone valide (10 chiffres)';
                        error.classList.add('show');
                    }
                    return false;
                }
            }
            
            if (fieldId === 'code_postal') {
                const cpRegex = /^[0-9]{5}$/;
                if (!cpRegex.test(field.value)) {
                    field.classList.add('error-field');
                    if (error) {
                        error.textContent = 'Veuillez entrer un code postal valide (5 chiffres)';
                        error.classList.add('show');
                    }
                    return false;
                }
            }
            
            return true;
        }
    }

    function validateFacturationField(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return true;
        
        if (field.hasAttribute('required') && !field.value.trim()) {
            field.classList.add('error-field');
            return false;
        }
        
        field.classList.remove('error-field');
        return true;
    }

    function validateForm() {
        const fields = [
            { id: 'nom', error: 'error-nom' },
            { id: 'prenom', error: 'error-prenom' },
            { id: 'adresse', error: 'error-adresse' },
            { id: 'code_postal', error: 'error-code_postal' },
            { id: 'ville', error: 'error-ville' },
            { id: 'email', error: 'error-email' }
        ];
        
        let isValid = true;
        
        fields.forEach(field => {
            if (!validateField(field.id, field.error)) isValid = false;
        });
        
        if (!document.getElementById('meme_adresse_facturation').checked) {
            const facturationFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                     'facturation_code_postal', 'facturation_ville'];
            
            facturationFields.forEach(fieldId => {
                if (!validateFacturationField(fieldId)) isValid = false;
            });
        }
        
        return isValid;
    }

    function setupRealTimeValidation() {
        const fieldsToValidate = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'email'];
        fieldsToValidate.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', () => {
                    const errorId = 'error-' + fieldId;
                    validateField(fieldId, errorId);
                });
                
                field.addEventListener('input', () => {
                    const errorId = 'error-' + fieldId;
                    const error = document.getElementById(errorId);
                    field.classList.remove('error-field');
                    if (error) error.classList.remove('show');
                });
            }
        });
        
        const facturationFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                 'facturation_code_postal', 'facturation_ville'];
        facturationFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', () => validateFacturationField(fieldId));
                field.addEventListener('input', () => field.classList.remove('error-field'));
            }
        });
    }

    function setupFormSubmission() {
        const form = document.getElementById('livraison-form');
        const submitBtn = document.getElementById('submit-btn');
        
        if (!form || !submitBtn) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!validateForm()) {
                alert('Veuillez corriger les erreurs dans le formulaire avant de continuer.');
                return false;
            }
            
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
            submitBtn.disabled = true;
            
            const formData = new FormData(form);
            const headers = new Headers();
            headers.append('X-Requested-With', 'XMLHttpRequest');
            headers.append('X-API-Mode', '1');
            
            fetch('livraison.php', {
                method: 'POST',
                body: formData,
                headers: headers
            })
            .then(response => {
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        try { return JSON.parse(text); } 
                        catch { return { success: false, message: 'Réponse inattendue du serveur' }; }
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    window.location.href = 'paiement.php';
                } else {
                    let errorMessage = 'Des erreurs sont survenues :\n';
                    if (data.errors && Array.isArray(data.errors)) {
                        errorMessage += data.errors.join('\n');
                    } else if (data.message) {
                        errorMessage = data.message;
                    }
                    
                    alert(errorMessage);
                    
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    
                    if (data.missing && Array.isArray(data.missing)) {
                        data.missing.forEach(fieldName => {
                            const field = document.getElementById(fieldName);
                            if (field) field.classList.add('error-field');
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Erreur lors de la soumission:', error);
                alert('Une erreur est survenue lors de la soumission. Veuillez réessayer.');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupFacturationToggle();
        setupLivraisonOptions();
        setupRealTimeValidation();
        setupFormSubmission();
        
        try {
            const addressData = <?php 
                if (isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']) && !empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'])) {
                    echo json_encode($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
                } else {
                    echo 'null';
                }
            ?>;
            
            if (addressData) {
                displayExistingAddress(addressData);
            }
        } catch (e) {
            console.log("Aucune adresse en session");
        }
        
        console.log('Session ID:', '<?php echo session_id(); ?>');
        console.log('Panier ID:', '<?php echo $_SESSION[SESSION_KEY_PANIER_ID] ?? "non défini"; ?>');
        console.log('Nombre articles panier:', '<?php echo count($_SESSION[SESSION_KEY_PANIER] ?? []); ?>');
    });
    </script>
</body>
</html>