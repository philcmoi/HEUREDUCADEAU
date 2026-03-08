<?php
// ============================================
// PAGE DU FORMULAIRE DE LIVRAISON - VERSION CORRIGÉE FINALE
// ============================================

// Activer l'affichage des erreurs (à désactiver en production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/session_verification.php';

// ============================================
// VÉRIFICATION D'ACCÈS STANDARDISÉE
// ============================================
if (!function_exists('checkLivraisonAccess')) {
    /**
     * Vérifie si l'utilisateur peut accéder à la page de livraison
     */
    function checkLivraisonAccess() {
        if (!hasValidCart()) {
            addSessionMessage('Votre panier est vide.', 'error');
            header('Location: panier.html');
            exit;
        }
    }
}

checkLivraisonAccess();

// ============================================
// CONNEXION BDD ET SYNCHRONISATION
// ============================================
$pdo = null;
try {
    $pdo = getPDOConnection();
    if ($pdo) {
        if (!function_exists('synchroniserPanierSessionBDD')) {
            /**
             * Synchronise le panier session avec la BDD
             */
            function synchroniserPanierSessionBDD($pdo, $session_id) {
                try {
                    // Vérifier si un panier BDD existe pour cette session
                    $stmt = $pdo->prepare("SELECT id_panier FROM panier WHERE session_id = ? AND statut = 'actif'");
                    $stmt->execute([$session_id]);
                    $panier_bdd = $stmt->fetch();
                    
                    if (!$panier_bdd && !empty($_SESSION[SESSION_KEY_PANIER])) {
                        // Créer un nouveau panier en BDD
                        $stmt = $pdo->prepare("INSERT INTO panier (session_id, statut, date_creation) VALUES (?, 'actif', NOW())");
                        $stmt->execute([$session_id]);
                        $panier_id = $pdo->lastInsertId();
                        $_SESSION[SESSION_KEY_PANIER_ID] = $panier_id;
                        
                        // Ajouter les items
                        $stmt_item = $pdo->prepare("
                            INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        
                        foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
                            $produit = getProductDetails($item['id_produit'], $pdo);
                            $prix = $produit['prix_ttc'] ?? $item['prix'] ?? 19.99;
                            
                            $stmt_item->execute([
                                $panier_id,
                                $item['id_produit'],
                                $item['quantite'],
                                $prix
                            ]);
                        }
                    } elseif ($panier_bdd) {
                        $_SESSION[SESSION_KEY_PANIER_ID] = $panier_bdd['id_panier'];
                        
                        // Récupérer les items de la BDD vers la session si nécessaire
                        if (empty($_SESSION[SESSION_KEY_PANIER])) {
                            $stmt_items = $pdo->prepare("
                                SELECT id_produit, quantite, prix_unitaire as prix
                                FROM panier_items
                                WHERE id_panier = ?
                            ");
                            $stmt_items->execute([$panier_bdd['id_panier']]);
                            $items = $stmt_items->fetchAll();
                            
                            $_SESSION[SESSION_KEY_PANIER] = [];
                            foreach ($items as $item) {
                                $_SESSION[SESSION_KEY_PANIER][] = [
                                    'id_produit' => $item['id_produit'],
                                    'quantite' => $item['quantite'],
                                    'prix' => $item['prix']
                                ];
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Erreur synchronisation panier: " . $e->getMessage());
                }
            }
        }
        
        synchroniserPanierSessionBDD($pdo, session_id());
    }
} catch (Exception $e) {
    error_log("Erreur connexion BDD: " . $e->getMessage());
    // Continuer sans BDD, utiliser uniquement la session
}

// ============================================
// INITIALISATION DES VALEURS PAR DÉFAUT
// ============================================
// Définir la constante SESSION_KEY_CHECKOUT si elle n'existe pas
if (!defined('SESSION_KEY_CHECKOUT')) {
    define('SESSION_KEY_CHECKOUT', 'checkout');
}

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
// FONCTIONS DE GESTION DES MESSAGES
// ============================================
if (!function_exists('getSessionMessages')) {
    /**
     * Récupère et efface les messages de session
     */
    function getSessionMessages() {
        $messages = [];
        if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
        }
        return $messages;
    }
}

if (!function_exists('addSessionMessage')) {
    /**
     * Ajoute un message en session
     */
    function addSessionMessage($message, $type = 'info') {
        if (!isset($_SESSION['messages'])) {
            $_SESSION['messages'] = [];
        }
        $_SESSION['messages'][] = [
            'message' => $message,
            'type' => $type,
            'time' => time()
        ];
    }
}

if (!function_exists('getCheckoutErrors')) {
    /**
     * Récupère et efface les erreurs de checkout
     */
    function getCheckoutErrors() {
        $errors = [];
        if (isset($_SESSION['checkout_errors']) && is_array($_SESSION['checkout_errors'])) {
            $errors = $_SESSION['checkout_errors'];
            unset($_SESSION['checkout_errors']);
        }
        return $errors;
    }
}

if (!function_exists('addCheckoutError')) {
    /**
     * Ajoute une erreur de checkout
     */
    function addCheckoutError($error) {
        if (!isset($_SESSION['checkout_errors'])) {
            $_SESSION['checkout_errors'] = [];
        }
        $_SESSION['checkout_errors'][] = $error;
    }
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
        /* STYLES CSS COMPLETS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 0 20px;
        }
        
        .content-box {
            background: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }
        
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.8rem;
        }
        
        h1 i {
            color: #3498db;
        }
        
        h2 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
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
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .radio-option:hover {
            border-color: #3498db;
            background: #f0f7ff;
        }
        
        .radio-option.selected {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.05);
        }
        
        .radio-option input[type="radio"] {
            width: auto;
            margin-right: 15px;
        }
        
        .radio-details {
            flex: 1;
        }
        
        .radio-details strong {
            color: #2c3e50;
            font-size: 1.1rem;
        }
        
        .radio-details p {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .radio-price {
            font-weight: 700;
            color: #27ae60;
            font-size: 1.1rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 15px;
            background: #f0fff4;
            border: 2px solid #9ae6b4;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-top: 3px;
        }
        
        .checkbox-group label {
            margin-bottom: 5px;
        }
        
        .checkbox-group p {
            color: #2c3e50;
            font-size: 0.9rem;
        }
        
        .checkbox-group p strong {
            color: #27ae60;
        }
        
        button {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.3);
        }
        
        button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 10px;
            border-left: 5px solid transparent;
        }
        
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border-left-color: #27ae60;
        }
        
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-left-color: #e74c3c;
        }
        
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-left-color: #3498db;
        }
        
        .shipping-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .shipping-info i {
            color: #3498db;
        }
        
        .error-field {
            border-color: #e74c3c !important;
        }
        
        .error-message {
            color: #e74c3c;
            font-size: 0.9rem;
            margin-top: 5px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        #adresse-facturation-different {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            margin-top: 20px;
            margin-bottom: 25px;
            display: none;
        }
        
        #adresse-facturation-different h3 {
            color: #2c3e50;
            font-size: 1.1rem;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        #facturation-same-checkbox {
            background: #edf2f7;
            border-color: #cbd5e0;
        }
        
        .info-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            background: #ebf8ff;
            border-left: 5px solid #3498db;
        }
        
        .info-message strong {
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .container {
                margin: 20px auto;
            }
            
            .content-box {
                padding: 20px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content-box">
            <h1>
                <i class="fas fa-truck"></i> 
                Adresse de livraison
            </h1>

            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo htmlspecialchars($msg['type']); ?>">
                        <?php echo htmlspecialchars($msg['message']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="message error">
                    <strong><i class="fas fa-exclamation-triangle"></i> Erreurs :</strong>
                    <ul style="margin-top: 10px; margin-left: 20px;">
                        <?php foreach ($errors as $erreur): ?>
                            <li><?php echo htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div id="info-message"></div>

            <form action="livraison.php" method="POST" id="livraison-form" novalidate>
                <input type="hidden" name="api_mode" value="1" />
                <input type="hidden" name="panier_id" value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_PANIER_ID] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

                <h2>Informations personnelles</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom" class="required">Prénom</label>
                        <input type="text" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               required 
                               maxlength="100" />
                        <div class="error-message" id="error-prenom"></div>
                    </div>
                    <div class="form-group">
                        <label for="nom" class="required">Nom</label>
                        <input type="text" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               required 
                               maxlength="100" />
                        <div class="error-message" id="error-nom"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? $_SESSION[SESSION_KEY_CHECKOUT]['client_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required 
                           maxlength="255" />
                    <div class="error-message" id="error-email"></div>
                    <div class="shipping-info">
                        <i class="fas fa-info-circle"></i>
                        Votre confirmation de commande sera envoyée à cette adresse
                    </div>
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" 
                           value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           pattern="[0-9]{10}" 
                           maxlength="20" />
                    <div class="error-message" id="error-telephone"></div>
                    <div class="shipping-info">
                        <i class="fas fa-info-circle"></i>
                        Pour vous contacter en cas de problème de livraison
                    </div>
                </div>

                <div class="form-group">
                    <label for="societe">Société (optionnel)</label>
                    <input type="text" id="societe" name="societe" 
                           value="<?php echo htmlspecialchars($donnees_saisies['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           maxlength="255" />
                </div>

                <h2>Adresse de livraison</h2>

                <div class="form-group">
                    <label for="adresse" class="required">Adresse</label>
                    <textarea id="adresse" name="adresse" rows="3" required 
                              maxlength="500"><?php echo htmlspecialchars($donnees_saisies['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <div class="error-message" id="error-adresse"></div>
                </div>

                <div class="form-group">
                    <label for="complement">Complément d'adresse</label>
                    <input type="text" id="complement" name="complement" 
                           value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           maxlength="255" />
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code_postal" class="required">Code postal</label>
                        <input type="text" id="code_postal" name="code_postal" 
                               value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               required 
                               pattern="[0-9]{5}" 
                               maxlength="10" />
                        <div class="error-message" id="error-code_postal"></div>
                    </div>
                    <div class="form-group">
                        <label for="ville" class="required">Ville</label>
                        <input type="text" id="ville" name="ville" 
                               value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               required 
                               maxlength="100" />
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
                    <div style="flex: 1;">
                        <label for="meme_adresse_facturation" style="margin-bottom: 5px;">
                            <i class="fas fa-file-invoice"></i> Utiliser la même adresse pour la facturation
                        </label>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.9rem;">
                            Si décoché, vous pourrez saisir une adresse de facturation différente
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
                                   <?php echo !$meme_adresse_checked ? 'required' : ''; ?>
                                   maxlength="100" />
                        </div>
                        <div class="form-group">
                            <label for="facturation_nom">Nom (facturation)</label>
                            <input type="text" id="facturation_nom" name="facturation_nom" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                   <?php echo !$meme_adresse_checked ? 'required' : ''; ?>
                                   maxlength="100" />
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_societe">Société (facturation, optionnel)</label>
                        <input type="text" id="facturation_societe" name="facturation_societe" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               maxlength="255" />
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_adresse">Adresse (facturation)</label>
                        <textarea id="facturation_adresse" name="facturation_adresse" rows="3" 
                                  <?php echo !$meme_adresse_checked ? 'required' : ''; ?>
                                  maxlength="500"><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_complement">Complément d'adresse (facturation)</label>
                        <input type="text" id="facturation_complement" name="facturation_complement" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               maxlength="255" />
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="facturation_code_postal">Code postal (facturation)</label>
                            <input type="text" id="facturation_code_postal" name="facturation_code_postal" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                   <?php echo !$meme_adresse_checked ? 'required' : ''; ?>
                                   pattern="[0-9]{5}" 
                                   maxlength="10" />
                        </div>
                        <div class="form-group">
                            <label for="facturation_ville">Ville (facturation)</label>
                            <input type="text" id="facturation_ville" name="facturation_ville" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                                   <?php echo !$meme_adresse_checked ? 'required' : ''; ?>
                                   maxlength="100" />
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

                <h2>Options supplémentaires</h2>

                <div class="form-group">
                    <label for="instructions">Instructions de livraison (optionnel)</label>
                    <textarea id="instructions" name="instructions" rows="2"
                        placeholder="Ex: Sonner au portail rouge, livrer au gardien, etc."
                        maxlength="1000"><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <button type="submit" id="submit-btn">
                    <i class="fas fa-arrow-right"></i> Continuer vers le paiement
                </button>

                <div style="text-align: center; margin-top: 20px; color: #7f8c8d; font-size: 0.9rem;">
                    <i class="fas fa-lock"></i> Vos données sont protégées et cryptées
                </div>
            </form>
        </div>
    </div>

    <script>
        // ============================================
        // JAVASCRIPT - GESTIONNAIRE DE FORMULAIRE
        // ============================================
        
        let isLoading = false;
        
        /**
         * Affiche une adresse existante
         */
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

        /**
         * Configure le toggle d'adresse de facturation
         */
        function setupFacturationToggle() {
            const sameAddressCheckbox = document.getElementById('meme_adresse_facturation');
            const facturationDiv = document.getElementById('adresse-facturation-different');
            
            if (!sameAddressCheckbox || !facturationDiv) return;
            
            sameAddressCheckbox.addEventListener('change', function(e) {
                if (this.checked) {
                    facturationDiv.style.display = 'none';
                    // Retirer required des champs de facturation
                    facturationDiv.querySelectorAll('input, textarea, select').forEach(field => {
                        field.removeAttribute('required');
                    });
                } else {
                    facturationDiv.style.display = 'block';
                    // Ajouter required aux champs obligatoires
                    const requiredFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                           'facturation_code_postal', 'facturation_ville'];
                    requiredFields.forEach(fieldId => {
                        const field = document.getElementById(fieldId);
                        if (field) field.setAttribute('required', 'required');
                    });
                }
            });
            
            // Copier les valeurs de l'adresse de livraison
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

        /**
         * Configure les options de livraison
         */
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

        /**
         * Valide un champ individuel
         */
        function validateField(fieldId, errorId) {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(errorId);
            
            if (!field) return true;
            
            // Champs non requis
            if (!field.hasAttribute('required') && !field.value.trim()) {
                field.classList.remove('error-field');
                if (error) error.classList.remove('show');
                return true;
            }
            
            // Champs requis
            if (!field.value.trim()) {
                field.classList.add('error-field');
                if (error) {
                    error.textContent = 'Ce champ est requis';
                    error.classList.add('show');
                }
                return false;
            }
            
            field.classList.remove('error-field');
            if (error) error.classList.remove('show');
            
            // Validations spécifiques
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

        /**
         * Valide un champ de facturation
         */
        function validateFacturationField(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) return true;
            
            // Si le champ est requis et vide
            if (field.hasAttribute('required') && !field.value.trim()) {
                field.classList.add('error-field');
                return false;
            }
            
            field.classList.remove('error-field');
            return true;
        }

        /**
         * Valide tout le formulaire
         */
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
                if (!validateField(field.id, field.error)) {
                    isValid = false;
                }
            });
            
            // Valider la facturation si différente
            if (!document.getElementById('meme_adresse_facturation').checked) {
                const facturationFields = [
                    'facturation_prenom', 
                    'facturation_nom', 
                    'facturation_adresse', 
                    'facturation_code_postal', 
                    'facturation_ville'
                ];
                
                facturationFields.forEach(fieldId => {
                    if (!validateFacturationField(fieldId)) {
                        isValid = false;
                    }
                });
            }
            
            return isValid;
        }

        /**
         * Configure la validation en temps réel
         */
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
            
            const facturationFields = [
                'facturation_prenom', 
                'facturation_nom', 
                'facturation_adresse', 
                'facturation_code_postal', 
                'facturation_ville'
            ];
            facturationFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('blur', () => validateFacturationField(fieldId));
                    field.addEventListener('input', () => field.classList.remove('error-field'));
                }
            });
        }

        /**
         * Configure la soumission du formulaire
         */
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
                
                if (isLoading) return;
                isLoading = true;
                
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
                            console.error('Réponse non-JSON:', text.substring(0, 200));
                            return { 
                                success: false, 
                                message: 'Réponse inattendue du serveur',
                                response_text: text.substring(0, 200)
                            };
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
                        } else {
                            errorMessage = 'Erreur inconnue lors de la soumission';
                        }
                        
                        alert(errorMessage);
                        
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        isLoading = false;
                        
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
                    isLoading = false;
                });
            });
        }

        /**
         * Charge les données initiales
         */
        function loadInitialData() {
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
        }

        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            setupFacturationToggle();
            setupLivraisonOptions();
            setupRealTimeValidation();
            setupFormSubmission();
            loadInitialData();
        });
    </script>
</body>
</html>