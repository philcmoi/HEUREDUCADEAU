<?php
session_start();

// Inclure la configuration de la base de données
require_once 'db_config.php';

// Configuration
$DEBUG_MODE = true;
$ENABLE_API = true;

// Fonction pour obtenir l'ID client (guest ou enregistré)
function getClientId() {
    if (isset($_SESSION['id_client']) && $_SESSION['id_client'] > 0) {
        return $_SESSION['id_client'];
    }
    
    // Si client guest, utiliser session_id ou créer un client temporaire
    $session_id = session_id();
    
    // Chercher un client temporaire avec cette session
    $db = getDB();
    $stmt = $db->prepare("SELECT id_client FROM clients WHERE session_id = ? AND is_temporary = 1");
    $stmt->execute([$session_id]);
    $client = $stmt->fetch();
    
    if ($client) {
        return $client['id_client'];
    }
    
    return null;
}

// Fonction pour sauvegarder l'adresse en base de données
function saveAddressToDB($addressData) {
    $db = getDB();
    $client_id = getClientId();
    
    if (!$client_id) {
        // Créer un client temporaire
        $stmt = $db->prepare("
            INSERT INTO clients (email, mot_de_passe, nom, prenom, telephone, is_temporary, created_from_session, date_inscription)
            VALUES (?, '', ?, ?, ?, 1, ?, NOW())
        ");
        $session_id = session_id();
        $stmt->execute([
            $addressData['email'],
            $addressData['nom'],
            $addressData['prenom'],
            $addressData['telephone'],
            $session_id
        ]);
        
        $client_id = $db->lastInsertId();
        $_SESSION['id_client_temp'] = $client_id;
    }
    
    // Vérifier si une adresse existe déjà pour ce client
    $stmt = $db->prepare("SELECT id_adresse FROM adresses WHERE id_client = ? AND type_adresse = 'livraison'");
    $stmt->execute([$client_id]);
    $existing_address = $stmt->fetch();
    
    if ($existing_address) {
        // Mettre à jour l'adresse existante
        $stmt = $db->prepare("
            UPDATE adresses SET
                nom = ?,
                prenom = ?,
                societe = ?,
                adresse = ?,
                complement = ?,
                code_postal = ?,
                ville = ?,
                pays = ?,
                telephone = ?,
                principale = 1
            WHERE id_adresse = ?
        ");
        $stmt->execute([
            $addressData['nom'],
            $addressData['prenom'],
            $addressData['societe'] ?? '',
            $addressData['adresse'],
            $addressData['complement'] ?? '',
            $addressData['code_postal'],
            $addressData['ville'],
            $addressData['pays'] ?? 'France',
            $addressData['telephone'],
            $existing_address['id_adresse']
        ]);
        
        return $existing_address['id_adresse'];
    } else {
        // Créer une nouvelle adresse
        $stmt = $db->prepare("
            INSERT INTO adresses (
                id_client, type_adresse, nom, prenom, societe, 
                adresse, complement, code_postal, ville, pays, 
                telephone, principale, date_creation
            ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $client_id,
            $addressData['nom'],
            $addressData['prenom'],
            $addressData['societe'] ?? '',
            $addressData['adresse'],
            $addressData['complement'] ?? '',
            $addressData['code_postal'],
            $addressData['ville'],
            $addressData['pays'] ?? 'France',
            $addressData['telephone']
        ]);
        
        return $db->lastInsertId();
    }
}

// Fonction pour récupérer l'adresse depuis la base de données
function getAddressFromDB() {
    $client_id = getClientId();
    
    if (!$client_id) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM adresses 
        WHERE id_client = ? AND type_adresse = 'livraison' 
        ORDER BY principale DESC, date_creation DESC 
        LIMIT 1
    ");
    $stmt->execute([$client_id]);
    return $stmt->fetch();
}

// API endpoint
if (isset($_GET['api']) && $_GET['api'] == '1' && $ENABLE_API) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'hasAddress' => false,
        'adresse' => null,
        'message' => ''
    ];
    
    // D'abord essayer la session
    if (isset($_SESSION['adresse_livraison'])) {
        $response['success'] = true;
        $response['hasAddress'] = true;
        $response['adresse'] = $_SESSION['adresse_livraison'];
    } else {
        // Sinon essayer la base de données
        $address = getAddressFromDB();
        if ($address) {
            $response['success'] = true;
            $response['hasAddress'] = true;
            $response['adresse'] = [
                'nom' => $address['nom'],
                'prenom' => $address['prenom'],
                'email' => $_SESSION['email'] ?? '', // L'email est dans clients
                'telephone' => $address['telephone'],
                'societe' => $address['societe'],
                'adresse' => $address['adresse'],
                'complement' => $address['complement'],
                'code_postal' => $address['code_postal'],
                'ville' => $address['ville'],
                'pays' => $address['pays']
            ];
        }
    }
    
    echo json_encode($response);
    exit();
}

// Traitement de la soumission du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si c'est une requête API
    $is_api_request = false;
    
    if (isset($_SERVER['HTTP_X_API_MODE']) && $_SERVER['HTTP_X_API_MODE'] == '1') {
        $is_api_request = true;
    } elseif (isset($_POST['api_mode']) && $_POST['api_mode'] == '1') {
        $is_api_request = true;
    }
    
    // Vérifier si c'est JSON
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);
    if ($json_data) {
        $_POST = array_merge($_POST, $json_data);
        $is_api_request = true;
    }
    
    // Validation et traitement des données
    $errors = [];
    $donnees_saisies = [];
    
    // Champs requis
    $required_fields = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'code_postal', 'ville', 'pays'];
    
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[] = "Le champ '" . $field . "' est requis";
        } else {
            $donnees_saisies[$field] = htmlspecialchars(trim($_POST[$field]));
        }
    }
    
    // Validation email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide";
    }
    
    // Validation téléphone (format français)
    if (!empty($_POST['telephone'])) {
        $phone_cleaned = preg_replace('/\s+/', '', $_POST['telephone']);
        if (!preg_match('/^[0-9]{10}$/', $phone_cleaned)) {
            $errors[] = "Le numéro de téléphone doit contenir 10 chiffres";
        }
    }
    
    // Validation code postal (format français)
    if (!empty($_POST['code_postal'])) {
        $cp_cleaned = preg_replace('/\s+/', '', $_POST['code_postal']);
        if (!preg_match('/^[0-9]{5}$/', $cp_cleaned)) {
            $errors[] = "Le code postal doit contenir 5 chiffres";
        }
    }
    
    // Réponse API
    if ($is_api_request) {
        header('Content-Type: application/json');
        
        if (!empty($errors)) {
            $missing_fields = [];
            foreach ($required_fields as $field) {
                if (empty(trim($_POST[$field] ?? ''))) {
                    $missing_fields[] = $field;
                }
            }
            
            echo json_encode([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $errors,
                'missing' => $missing_fields
            ]);
            exit();
        }
        
        // Préparer les données pour la base de données
        $addressData = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'telephone' => $_POST['telephone'],
            'societe' => $_POST['societe'] ?? '',
            'adresse' => $_POST['adresse'],
            'complement' => $_POST['complement'] ?? '',
            'code_postal' => $_POST['code_postal'],
            'ville' => $_POST['ville'],
            'pays' => $_POST['pays'] ?? 'France'
        ];
        
        // Sauvegarder dans la base de données
        try {
            $address_id = saveAddressToDB($addressData);
            
            // Sauvegarder aussi dans la session pour compatibilité
            $adresse_livraison = [
                'nom' => $_POST['nom'],
                'prenom' => $_POST['prenom'],
                'email' => $_POST['email'],
                'telephone' => $_POST['telephone'],
                'societe' => $_POST['societe'] ?? '',
                'adresse' => $_POST['adresse'],
                'complement' => $_POST['complement'] ?? '',
                'code_postal' => $_POST['code_postal'],
                'ville' => $_POST['ville'],
                'pays' => $_POST['pays'] ?? 'France',
                'mode_livraison' => $_POST['mode_livraison'] ?? 'standard',
                'emballage_cadeau' => isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1',
                'instructions' => $_POST['instructions'] ?? ''
            ];
            
            $_SESSION['adresse_livraison'] = $adresse_livraison;
            $_SESSION['adresse_livraison_id'] = $address_id;
            $_SESSION['mode_livraison'] = $_POST['mode_livraison'] ?? 'standard';
            $_SESSION['emballage_cadeau'] = isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1';
            
            // Sauvegarder l'email dans la session
            $_SESSION['email'] = $_POST['email'];
            
            // Calculer les frais de livraison
            $frais_livraison = 0;
            if (($_POST['mode_livraison'] ?? 'standard') === 'express') {
                $frais_livraison = 9.90;
            } elseif (($_POST['mode_livraison'] ?? 'standard') === 'relais') {
                $frais_livraison = 4.90;
            }
            $_SESSION['frais_livraison'] = $frais_livraison;
            
            // Déterminer la redirection
            $redirect = null;
            $compat_redirect = null;
            
            // Vérifier les pages disponibles
            if (file_exists('paiement.php')) {
                $redirect = 'paiement.php';
            } elseif (file_exists('paiement.html')) {
                $redirect = 'paiement.html';
            }
            
            // Compatibilité avec l'ancien système
            if (file_exists('checkout.php')) {
                $compat_redirect = 'checkout.php';
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Adresse enregistrée avec succès',
                'redirect' => $redirect,
                'compat_redirect' => $compat_redirect
            ]);
            exit();
            
        } catch (Exception $e) {
            error_log('Erreur base de données: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement en base de données'
            ]);
            exit();
        }
    }
    
    // Mode formulaire traditionnel
    if (!empty($errors)) {
        $_SESSION['erreurs_livraison'] = $errors;
        $_SESSION['donnees_saisies'] = $_POST;
        header('Location: livraison.php');
        exit();
    }
    
    // Sauvegarder dans la base de données
    try {
        $addressData = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'telephone' => $_POST['telephone'],
            'societe' => $_POST['societe'] ?? '',
            'adresse' => $_POST['adresse'],
            'complement' => $_POST['complement'] ?? '',
            'code_postal' => $_POST['code_postal'],
            'ville' => $_POST['ville'],
            'pays' => $_POST['pays'] ?? 'France'
        ];
        
        $address_id = saveAddressToDB($addressData);
        
        // Sauvegarder aussi dans la session pour compatibilité
        $_SESSION['adresse_livraison'] = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'telephone' => $_POST['telephone'],
            'societe' => $_POST['societe'] ?? '',
            'adresse' => $_POST['adresse'],
            'complement' => $_POST['complement'] ?? '',
            'code_postal' => $_POST['code_postal'],
            'ville' => $_POST['ville'],
            'pays' => $_POST['pays'] ?? 'France',
            'mode_livraison' => $_POST['mode_livraison'] ?? 'standard',
            'emballage_cadeau' => isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1',
            'instructions' => $_POST['instructions'] ?? ''
        ];
        
        $_SESSION['adresse_livraison_id'] = $address_id;
        $_SESSION['mode_livraison'] = $_POST['mode_livraison'] ?? 'standard';
        $_SESSION['emballage_cadeau'] = isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1';
        $_SESSION['email'] = $_POST['email'];
        
        // Calculer les frais de livraison
        $frais_livraison = 0;
        if (($_POST['mode_livraison'] ?? 'standard') === 'express') {
            $frais_livraison = 9.90;
        } elseif (($_POST['mode_livraison'] ?? 'standard') === 'relais') {
            $frais_livraison = 4.90;
        }
        $_SESSION['frais_livraison'] = $frais_livraison;
        
        // Redirection vers la page de paiement
        if (file_exists('paiement.php')) {
            header('Location: paiement.php');
        } elseif (file_exists('paiement.html')) {
            header('Location: paiement.html');
        } else {
            // Fallback
            header('Location: checkout.php');
        }
        exit();
        
    } catch (Exception $e) {
        error_log('Erreur base de données: ' . $e->getMessage());
        $_SESSION['erreurs_livraison'] = ['Erreur lors de l\'enregistrement en base de données'];
        header('Location: livraison.php');
        exit();
    }
}

// Récupérer les données de session pour pré-remplissage
$donnees_saisies = $_SESSION['donnees_saisies'] ?? [];

// Si pas dans la session, essayer la base de données
if (empty($donnees_saisies)) {
    if (isset($_SESSION['adresse_livraison'])) {
        $donnees_saisies = $_SESSION['adresse_livraison'];
    } else {
        $address = getAddressFromDB();
        if ($address) {
            $donnees_saisies = [
                'nom' => $address['nom'],
                'prenom' => $address['prenom'],
                'societe' => $address['societe'],
                'adresse' => $address['adresse'],
                'complement' => $address['complement'],
                'code_postal' => $address['code_postal'],
                'ville' => $address['ville'],
                'pays' => $address['pays'],
                'telephone' => $address['telephone']
            ];
            
            // Récupérer l'email depuis la table clients si disponible
            if (isset($_SESSION['email'])) {
                $donnees_saisies['email'] = $_SESSION['email'];
            }
        }
    }
}

// Nettoyer les données de session utilisées
if (isset($_SESSION['donnees_saisies'])) {
    unset($_SESSION['donnees_saisies']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informations de livraison</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        h1 {
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3498db;
            text-align: center;
        }
        
        .error-message {
            background-color: #fee;
            border: 1px solid #f99;
            color: #c00;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .error-message ul {
            margin-left: 20px;
            margin-top: 10px;
        }
        
        .success-message {
            background-color: #dfd;
            border: 1px solid #9d9;
            color: #090;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .required::after {
            content: " *";
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        select:focus,
        textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .shipping-options {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .shipping-option {
            flex: 1;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .shipping-option:hover {
            border-color: #3498db;
        }
        
        .shipping-option.selected {
            border-color: #3498db;
            background-color: #f0f8ff;
        }
        
        .shipping-option input[type="radio"] {
            margin-right: 10px;
        }
        
        .price {
            font-weight: bold;
            color: #2c3e50;
            margin-top: 10px;
        }
        
        .button-group {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            gap: 15px;
        }
        
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-back {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #7f8c8d;
        }
        
        .btn-submit {
            background-color: #3498db;
            color: white;
            flex-grow: 1;
        }
        
        .btn-submit:hover {
            background-color: #2980b9;
        }
        
        .form-note {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .form-row,
            .shipping-options {
                flex-direction: column;
            }
            
            .container {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
        
        .loading {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
        
        .loading-spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        // Mode API pour compatibilité avec les systèmes existants
        const API_MODE = <?php echo $ENABLE_API ? 'true' : 'false'; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Pré-sélectionner le mode de livraison
            const modeLivraison = '<?php echo $_SESSION['mode_livraison'] ?? 'standard'; ?>';
            if (modeLivraison) {
                const radio = document.querySelector(`input[name="mode_livraison"][value="${modeLivraison}"]`);
                if (radio) {
                    radio.checked = true;
                    radio.closest('.shipping-option').classList.add('selected');
                }
            }
            
            // Gestion de la sélection des options de livraison
            document.querySelectorAll('.shipping-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.shipping-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    this.classList.add('selected');
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
            
            // Formatage automatique du téléphone
            const phoneInput = document.querySelector('input[name="telephone"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 10) value = value.substring(0, 10);
                    
                    if (value.length > 6) {
                        value = value.replace(/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/, '$1 $2 $3 $4 $5');
                    } else if (value.length > 4) {
                        value = value.replace(/(\d{2})(\d{2})(\d{2})/, '$1 $2 $3');
                    } else if (value.length > 2) {
                        value = value.replace(/(\d{2})(\d{2})/, '$1 $2');
                    }
                    
                    e.target.value = value;
                });
            }
            
            // Formatage automatique du code postal
            const cpInput = document.querySelector('input[name="code_postal"]');
            if (cpInput) {
                cpInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 5) value = value.substring(0, 5);
                    e.target.value = value;
                });
            }
            
            // Récupération automatique de l'adresse via API si disponible
            if (API_MODE) {
                fetch('livraison.php?api=1')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.hasAddress && data.adresse) {
                            // Pré-remplir les champs avec les données de l'API
                            const addr = data.adresse;
                            const fields = {
                                'nom': addr.nom,
                                'prenom': addr.prenom,
                                'email': addr.email,
                                'telephone': addr.telephone,
                                'societe': addr.societe,
                                'adresse': addr.adresse,
                                'complement': addr.complement,
                                'code_postal': addr.code_postal,
                                'ville': addr.ville,
                                'pays': addr.pays
                            };
                            
                            Object.keys(fields).forEach(field => {
                                const input = document.querySelector(`[name="${field}"]`);
                                if (input && fields[field]) {
                                    input.value = fields[field];
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Erreur lors de la récupération de l\'adresse:', error);
                    });
            }
            
            // Gestion de la soumission du formulaire
            const form = document.getElementById('livraison-form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Validation côté client
                    const requiredFields = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'code_postal', 'ville'];
                    let isValid = true;
                    const errors = [];
                    
                    requiredFields.forEach(field => {
                        const input = document.querySelector(`[name="${field}"]`);
                        if (input && !input.value.trim()) {
                            isValid = false;
                            input.style.borderColor = '#e74c3c';
                            errors.push(`Le champ ${field} est requis`);
                        } else if (input) {
                            input.style.borderColor = '#ddd';
                        }
                    });
                    
                    // Validation email
                    const emailInput = document.querySelector('[name="email"]');
                    if (emailInput && emailInput.value && !isValidEmail(emailInput.value)) {
                        isValid = false;
                        emailInput.style.borderColor = '#e74c3c';
                        errors.push('L\'adresse email n\'est pas valide');
                    }
                    
                    if (!isValid) {
                        showErrors(errors);
                        return;
                    }
                    
                    // Afficher le loader
                    const loadingDiv = document.getElementById('loading');
                    if (loadingDiv) loadingDiv.style.display = 'block';
                    
                    // Préparer les données du formulaire
                    const formData = new FormData(form);
                    
                    // Ajouter le mode API si activé
                    if (API_MODE) {
                        formData.append('api_mode', '1');
                    }
                    
                    // Envoyer les données
                    fetch('livraison.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.headers.get('content-type')?.includes('application/json')) {
                            return response.json();
                        }
                        // Redirection automatique pour les réponses non-JSON
                        window.location.href = response.url;
                        return null;
                    })
                    .then(data => {
                        if (data) {
                            if (data.success) {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                } else if (data.compat_redirect) {
                                    window.location.href = data.compat_redirect;
                                } else {
                                    // Fallback vers paiement.php
                                    window.location.href = 'paiement.php';
                                }
                            } else {
                                showErrors(data.errors || [data.message]);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        showErrors(['Une erreur est survenue lors de l\'envoi du formulaire']);
                    })
                    .finally(() => {
                        if (loadingDiv) loadingDiv.style.display = 'none';
                    });
                });
            }
            
            function isValidEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
            
            function showErrors(errors) {
                const errorDiv = document.getElementById('error-messages');
                if (errorDiv && errors.length > 0) {
                    errorDiv.innerHTML = `
                        <div class="error-message">
                            <strong>Des erreurs sont présentes dans le formulaire :</strong>
                            <ul>
                                ${errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        </div>
                    `;
                    errorDiv.scrollIntoView({ behavior: 'smooth' });
                }
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Informations de livraison</h1>
        
        <div id="error-messages">
            <?php if (isset($_SESSION['erreurs_livraison'])): ?>
                <div class="error-message">
                    <strong>Des erreurs sont présentes dans le formulaire :</strong>
                    <ul>
                        <?php foreach ($_SESSION['erreurs_livraison'] as $erreur): ?>
                            <li><?php echo htmlspecialchars($erreur); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php unset($_SESSION['erreurs_livraison']); ?>
            <?php endif; ?>
        </div>
        
        <form id="livraison-form" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="nom" class="required">Nom</label>
                    <input type="text" id="nom" name="nom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="prenom" class="required">Prénom</label>
                    <input type="text" id="prenom" name="prenom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? ''); ?>" 
                           required>
                    <div class="form-note">Vous recevrez la confirmation de commande à cette adresse</div>
                </div>
                
                <div class="form-group">
                    <label for="telephone" class="required">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" 
                           value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? ''); ?>" 
                           placeholder="01 23 45 67 89" 
                           required>
                    <div class="form-note">Format: 10 chiffres</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="societe">Société (optionnel)</label>
                <input type="text" id="societe" name="societe" 
                       value="<?php echo htmlspecialchars($donnees_saisies['societe'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="adresse" class="required">Adresse</label>
                <input type="text" id="adresse" name="adresse" 
                       value="<?php echo htmlspecialchars($donnees_saisies['adresse'] ?? ''); ?>" 
                       placeholder="123 rue de l'exemple" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="complement">Complément d'adresse (optionnel)</label>
                <input type="text" id="complement" name="complement" 
                       value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? ''); ?>" 
                       placeholder="Appartement, étage, etc.">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="code_postal" class="required">Code postal</label>
                    <input type="text" id="code_postal" name="code_postal" 
                           value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? ''); ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="ville" class="required">Ville</label>
                    <input type="text" id="ville" name="ville" 
                           value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? ''); ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="pays" class="required">Pays</label>
                <select id="pays" name="pays" required>
                    <option value="France" <?php echo (($donnees_saisies['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                    <option value="Belgique" <?php echo (($donnees_saisies['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                    <option value="Suisse" <?php echo (($donnees_saisies['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                    <option value="Luxembourg" <?php echo (($donnees_saisies['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                    <option value="Autre" <?php echo (($donnees_saisies['pays'] ?? '') === 'Autre') ? 'selected' : ''; ?>>Autre pays</option>
                </select>
            </div>
            
            <h2 style="margin-top: 30px; margin-bottom: 20px; color: #2c3e50;">Mode de livraison</h2>
            
            <div class="shipping-options">
                <div class="shipping-option">
                    <label>
                        <input type="radio" name="mode_livraison" value="standard" checked>
                        <strong>Livraison Standard</strong><br>
                        <span>Livré en 3-5 jours ouvrables</span>
                        <div class="price">Gratuit</div>
                    </label>
                </div>
                
                <div class="shipping-option">
                    <label>
                        <input type="radio" name="mode_livraison" value="express">
                        <strong>Livraison Express</strong><br>
                        <span>Livré en 24h (jours ouvrés)</span>
                        <div class="price">9,90 €</div>
                    </label>
                </div>
                
                <div class="shipping-option">
                    <label>
                        <input type="radio" name="mode_livraison" value="relais">
                        <strong>Point Relais</strong><br>
                        <span>Retrait en 48h</span>
                        <div class="price">4,90 €</div>
                    </label>
                </div>
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="emballage_cadeau" name="emballage_cadeau" value="1" 
                       <?php echo (isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau']) ? 'checked' : ''; ?>>
                <label for="emballage_cadeau">Emballage cadeau (+2,00 €)</label>
            </div>
            
            <div class="form-group">
                <label for="instructions">Instructions de livraison (optionnel)</label>
                <textarea id="instructions" name="instructions" rows="3" 
                          placeholder="Porte bleue, sonnette à gauche, absence prévue..."><?php echo htmlspecialchars($_SESSION['adresse_livraison']['instructions'] ?? ''); ?></textarea>
            </div>
            
            <div id="loading" class="loading">
                <div class="loading-spinner"></div>
                <p>Traitement en cours...</p>
            </div>
            
            <div class="button-group">
                <a href="panier.php" class="btn btn-back">← Retour au panier</a>
                <button type="submit" class="btn btn-submit">Continuer vers le paiement →</button>
            </div>
        </form>
    </div>
</body>
</html>