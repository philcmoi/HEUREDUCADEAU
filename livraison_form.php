<?php
// ============================================
// PROTECTION D'ACCÈS - VERSION BASÉE SUR BD
// ============================================

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// ACCEPTER LES REDIRECTIONS DIRECTES DEPUIS LE PANIER
// ============================================

// Si accès direct depuis le panier, autoriser temporairement
if (!isset($_SESSION['checkout_authorized']) && !empty($_SESSION)) {
    // Vérifier si nous avons des items dans la session
    if (isset($_SESSION['panier_items']) && !empty($_SESSION['panier_items'])) {
        $_SESSION['checkout_authorized'] = true;
        $_SESSION['checkout_time'] = time();
    }
}

// Initialiser les variables
$access_granted = false;
$client_id = null;
$panier_id = null;
$pdo = null;

// Méthode 1: Vérifier via l'autorisation de session
if (isset($_SESSION['checkout_authorized']) && $_SESSION['checkout_authorized'] === true) {
    // Vérifier si l'autorisation n'a pas expiré (10 minutes)
    if (isset($_SESSION['checkout_time']) && (time() - $_SESSION['checkout_time']) <= 600) {
        $access_granted = true;
        
        // Récupérer les IDs depuis la session si disponibles
        $panier_id = $_SESSION['panier_id'] ?? null;
        $client_id = $_SESSION['client_id'] ?? null;
    } else {
        // L'autorisation a expiré, la supprimer
        unset($_SESSION['checkout_authorized']);
        unset($_SESSION['checkout_time']);
    }
}

// Méthode 2: Vérifier via la base de données
if (!$access_granted && session_id()) {
    $session_id = session_id();
    
    try {
        // Connexion à la base de données
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 1. Vérifier si le panier existe dans la base de données
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
            $access_granted = true;
            $panier_id = $panier['id_panier'];
            $client_id = $panier['id_client'];
            
            // Sauvegarder dans la session pour usage ultérieur
            $_SESSION['panier_id'] = $panier_id;
            $_SESSION['client_id'] = $client_id;
            $_SESSION['checkout_authorized'] = true;
            $_SESSION['checkout_time'] = time();
        }
        
    } catch (PDOException $e) {
        // En cas d'erreur BD, utiliser un fallback basique
        error_log("Erreur base de données: " . $e->getMessage());
        
        // Fallback aux sessions
        if (isset($_SESSION['panier_items']) && !empty($_SESSION['panier_items'])) {
            $access_granted = true;
            $_SESSION['checkout_authorized'] = true;
            $_SESSION['checkout_time'] = time();
        }
    }
}

// ============================================
// ACCÈS TEMPORAIRE POUR TEST
// ============================================

// Si aucun accès n'a été accordé mais que nous avons une session
if (!$access_granted && session_id()) {
    // Autoriser temporairement pour le développement
    $access_granted = true;
    $_SESSION['checkout_authorized'] = true;
    $_SESSION['checkout_time'] = time();
    
    // Créer un panier fictif pour le test
    if (!isset($_SESSION['panier_id'])) {
        $_SESSION['panier_id'] = 'temp_' . time();
    }
}

// Si accès non autorisé, rediriger vers le panier
if (!$access_granted) {
    // Vérifier si c'est une requête AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé. Veuillez d\'abord remplir votre panier.',
            'redirect' => 'panier.php'
        ]);
        exit();
    }
    
    // Redirection normale
    header('Location: panier.php');
    exit();
}

// ============================================
// RÉCUPÉRATION DES DONNÉES DEPUIS LA BASE
// ============================================

$errors = [];
$donnees_saisies = [];
$meme_adresse_default = true;
$adresse_facturation = [];

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
    
    // Récupérer les données sauvegardées depuis la base
    if (isset($_SESSION['panier_id']) || $panier_id) {
        // Utiliser l'ID du panier depuis la session ou la variable locale
        $current_panier_id = $_SESSION['panier_id'] ?? $panier_id;
        
        // Récupérer les adresses du client si connecté
        if ($client_id) {
            $stmt = $pdo->prepare("
                SELECT * FROM adresses 
                WHERE id_client = ? AND type_adresse = 'livraison' AND principale = 1
                ORDER BY date_creation DESC LIMIT 1
            ");
            $stmt->execute([$client_id]);
            $adresse_livraison = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adresse_livraison) {
                $donnees_saisies = [
                    'prenom' => $adresse_livraison['prenom'] ?? '',
                    'nom' => $adresse_livraison['nom'] ?? '',
                    'societe' => $adresse_livraison['societe'] ?? '',
                    'adresse' => $adresse_livraison['adresse'] ?? '',
                    'complement' => $adresse_livraison['complement'] ?? '',
                    'code_postal' => $adresse_livraison['code_postal'] ?? '',
                    'ville' => $adresse_livraison['ville'] ?? '',
                    'pays' => $adresse_livraison['pays'] ?? '',
                    'telephone' => $adresse_livraison['telephone'] ?? ''
                ];
                
                // Récupérer l'email du client
                $stmt = $pdo->prepare("SELECT email FROM clients WHERE id_client = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client && isset($client['email'])) {
                    $donnees_saisies['email'] = $client['email'];
                }
            }
        }
        
        // ============================================
        // CORRECTION : Récupérer les données depuis commande_temporaire
        // ============================================
        $stmt = $pdo->prepare("
            SELECT donnees_livraison, mode_livraison, emballage_cadeau, instructions
            FROM commande_temporaire 
            WHERE panier_id = ? 
            ORDER BY date_creation DESC LIMIT 1
        ");
        $stmt->execute([$current_panier_id]);
        $temp_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($temp_data) {
            // Données de livraison
            if (!empty($temp_data['donnees_livraison'])) {
                $temp_data_array = json_decode($temp_data['donnees_livraison'], true);
                if ($temp_data_array && is_array($temp_data_array)) {
                    // Fusionner avec les données existantes
                    $donnees_saisies = array_merge($donnees_saisies, $temp_data_array);
                }
            }
            
            // Options de livraison
            if (isset($temp_data['mode_livraison'])) {
                $_SESSION['mode_livraison'] = $temp_data['mode_livraison'];
            }
            
            // Emballage cadeau
            if (isset($temp_data['emballage_cadeau'])) {
                $donnees_saisies['emballage_cadeau'] = $temp_data['emballage_cadeau'];
            }
            
            // Instructions
            if (isset($temp_data['instructions'])) {
                $donnees_saisies['instructions'] = $temp_data['instructions'];
            }
        }
        
        // Récupérer les erreurs depuis la table logs si nécessaire
        $stmt = $pdo->prepare("
            SELECT message FROM logs 
            WHERE type_log = 'erreur' 
            AND (utilisateur_id = ? OR utilisateur_id = 0)
            AND date_log > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY date_log DESC LIMIT 5
        ");
        $stmt->execute([$client_id ?: 0]);
        $logs_erreurs = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if ($logs_erreurs) {
            $errors = $logs_erreurs;
        }
    }
} catch (Exception $e) {
    // En cas d'erreur, utiliser les sessions comme fallback
    error_log("Erreur lors de la récupération des données: " . $e->getMessage());
    
    if (isset($_SESSION['erreurs_livraison'])) {
        $errors = $_SESSION['erreurs_livraison'];
    }
    if (isset($_SESSION['donnees_saisies'])) {
        $donnees_saisies = $_SESSION['donnees_saisies'];
    }
    if (isset($_SESSION['adresse_facturation'])) {
        $adresse_facturation = $_SESSION['adresse_facturation'];
    }
    $meme_adresse_default = isset($_SESSION['meme_adresse_facturation']) ? 
                           (bool)$_SESSION['meme_adresse_facturation'] : true;
}

// Nettoyer les sessions après utilisation
if (isset($_SESSION['erreurs_livraison'])) unset($_SESSION['erreurs_livraison']);
if (isset($_SESSION['donnees_saisies'])) unset($_SESSION['donnees_saisies']);
if (isset($_SESSION['adresse_facturation'])) unset($_SESSION['adresse_facturation']);

// Définir la valeur par défaut pour la case à cocher
$meme_adresse_checked = isset($_SESSION['meme_adresse_facturation']) ? 
                       $_SESSION['meme_adresse_facturation'] : true;

// ============================================
// CRÉATION DU FLAG POUR PAIEMENT.PHP
// ============================================
// Définir un flag pour indiquer que nous venons de livraison_form.php
$_SESSION['from_livraison_form'] = true;

// Nettoyage de la session pour meme_adresse_facturation
if (isset($_SESSION['meme_adresse_facturation'])) {
    unset($_SESSION['meme_adresse_facturation']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Adresse de Livraison - HEURE DU CADEAU</title>
    <style>
        /* [VOTRE CSS EXISTANT - CONSERVER INTACT] */
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

        <!-- Messages d'information -->
        <div id="info-message"></div>

        <!-- Messages d'erreur -->
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

        <!-- CORRECTION : Formulaire pointe vers livraison.php -->
        <form action="livraison.php" method="POST" id="livraison-form">
            <!-- AJOUT : Flag pour indiquer qu'on vient du formulaire -->
            <input type="hidden" name="from_livraison_form" value="1" />
            <input type="hidden" name="api_mode" value="1" />
            <input type="hidden" name="panier_id" value="<?php echo htmlspecialchars($panier_id ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
            <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($client_id ?? '', ENT_QUOTES, 'UTF-8'); ?>" />

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
                       value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required />
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
                <label for="complement">Complément d'adresse (appartement, étage, etc.)</label>
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

            <!-- Section pour adresse de facturation différente -->
            <div id="adresse-facturation-different" style="display: <?php echo $meme_adresse_checked ? 'none' : 'block'; ?>;">
                <h3>Adresse de facturation différente</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="facturation_prenom">Prénom (facturation)</label>
                        <input type="text" id="facturation_prenom" name="facturation_prenom" 
                               value="<?php echo htmlspecialchars($adresse_facturation['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                    <div class="form-group">
                        <label for="facturation_nom">Nom (facturation)</label>
                        <input type="text" id="facturation_nom" name="facturation_nom" 
                               value="<?php echo htmlspecialchars($adresse_facturation['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="facturation_societe">Société (facturation, optionnel)</label>
                    <input type="text" id="facturation_societe" name="facturation_societe" 
                           value="<?php echo htmlspecialchars($adresse_facturation['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                
                <div class="form-group">
                    <label for="facturation_adresse">Adresse (facturation)</label>
                    <textarea id="facturation_adresse" name="facturation_adresse" rows="3" 
                              <?php echo !$meme_adresse_checked ? 'required' : ''; ?>><?php echo htmlspecialchars($adresse_facturation['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="facturation_complement">Complément d'adresse (facturation)</label>
                    <input type="text" id="facturation_complement" name="facturation_complement" 
                           value="<?php echo htmlspecialchars($adresse_facturation['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" />
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="facturation_code_postal">Code postal (facturation)</label>
                        <input type="text" id="facturation_code_postal" name="facturation_code_postal" 
                               value="<?php echo htmlspecialchars($adresse_facturation['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                    <div class="form-group">
                        <label for="facturation_ville">Ville (facturation)</label>
                        <input type="text" id="facturation_ville" name="facturation_ville" 
                               value="<?php echo htmlspecialchars($adresse_facturation['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                               <?php echo !$meme_adresse_checked ? 'required' : ''; ?> />
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="facturation_pays">Pays (facturation)</label>
                    <select id="facturation_pays" name="facturation_pays">
                        <option value="France" <?php echo (($adresse_facturation['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                        <option value="Belgique" <?php echo (($adresse_facturation['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                        <option value="Suisse" <?php echo (($adresse_facturation['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                        <option value="Luxembourg" <?php echo (($adresse_facturation['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                        <option value="autre" <?php echo (($adresse_facturation['pays'] ?? '') === 'autre') ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
            </div>

            <h2>Options de livraison</h2>

            <div class="radio-group" id="livraisonOptions">
                <div class="radio-option <?php echo (($donnees_saisies['mode_livraison'] ?? 'standard') === 'standard') ? 'selected' : ''; ?>" data-value="standard">
                    <input type="radio" name="mode_livraison" value="standard" <?php echo (($donnees_saisies['mode_livraison'] ?? 'standard') === 'standard') ? 'checked' : ''; ?> hidden />
                    <div class="radio-details">
                        <strong>Livraison Standard</strong>
                        <p>Livraison en 3-5 jours ouvrés</p>
                    </div>
                    <div class="radio-price">Gratuite</div>
                </div>

                <div class="radio-option <?php echo (($donnees_saisies['mode_livraison'] ?? '') === 'express') ? 'selected' : ''; ?>" data-value="express">
                    <input type="radio" name="mode_livraison" value="express" <?php echo (($donnees_saisies['mode_livraison'] ?? '') === 'express') ? 'checked' : ''; ?> hidden />
                    <div class="radio-details">
                        <strong>Livraison Express</strong>
                        <p>Livraison en 24h (hors week-end)</p>
                    </div>
                    <div class="radio-price">9,90 €</div>
                </div>

                <div class="radio-option <?php echo (($donnees_saisies['mode_livraison'] ?? '') === 'relais') ? 'selected' : ''; ?>" data-value="relais">
                    <input type="radio" name="mode_livraison" value="relais" <?php echo (($donnees_saisies['mode_livraison'] ?? '') === 'relais') ? 'checked' : ''; ?> hidden />
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
                       <?php echo (isset($donnees_saisies['emballage_cadeau']) && $donnees_saisies['emballage_cadeau']) ? 'checked' : ''; ?> />
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
                    placeholder="Ex: Sonner au portail rouge, livrer au gardien, etc."><?php echo htmlspecialchars($donnees_saisies['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button type="submit" id="submit-btn">
                <i class="fas fa-arrow-right"></i> Continuer vers le paiement
            </button>

            <div style="text-align: center; margin-top: 20px; color: #718096; font-size: 14px;">
                <i class="fas fa-lock"></i> Vos données sont protégées et ne seront pas partagées avec des tiers
            </div>
        </form>
    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

    <script>
        // Variables globales
        let isLoading = false;
        
        // Fonction pour afficher une adresse existante
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

            // Pré-remplir le formulaire
            const fields = ['prenom', 'nom', 'adresse', 'complement', 'code_postal', 'ville', 'pays', 'telephone', 'email', 'societe', 'instructions'];
            fields.forEach(field => {
                const input = document.getElementById(field);
                if (input && address[field]) {
                    input.value = address[field];
                }
            });
        }

        // Gestion de la case à cocher "même adresse pour facturation"
        function setupFacturationToggle() {
            const sameAddressCheckbox = document.getElementById('meme_adresse_facturation');
            const facturationDiv = document.getElementById('adresse-facturation-different');
            
            if (!sameAddressCheckbox || !facturationDiv) return;
            
            sameAddressCheckbox.addEventListener('change', function(e) {
                if (this.checked) {
                    // Caché: même adresse
                    facturationDiv.style.display = 'none';
                    
                    // Enlever l'attribut required des champs
                    const facturationFields = facturationDiv.querySelectorAll('input, textarea, select');
                    facturationFields.forEach(field => {
                        field.removeAttribute('required');
                    });
                } else {
                    // Affiché: adresse différente
                    facturationDiv.style.display = 'block';
                    
                    // Ajouter l'attribut required aux champs obligatoires
                    const requiredFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                           'facturation_code_postal', 'facturation_ville'];
                    requiredFields.forEach(fieldId => {
                        const field = document.getElementById(fieldId);
                        if (field) field.setAttribute('required', 'required');
                    });
                }
            });
            
            // Copier automatiquement l'adresse de livraison
            sameAddressCheckbox.addEventListener('click', function() {
                if (!this.checked) return;
                
                // Copier les valeurs de livraison vers facturation
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

        // Gestion des options de livraison
        function setupLivraisonOptions() {
            document.querySelectorAll('.radio-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Désélectionner toutes les options
                    document.querySelectorAll('.radio-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });

                    // Sélectionner celle cliquée
                    this.classList.add('selected');

                    // Cochez le radio correspondant
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = true;
                    }
                });
            });
        }

        // Fonction de validation des champs
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
                if (error) {
                    error.classList.remove('show');
                }
                
                // Validation spécifique pour email
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
                
                // Validation spécifique pour téléphone (format français)
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
                
                // Validation spécifique pour code postal (format français)
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

        // Validation des champs de facturation
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

        // Fonction de validation globale
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
            
            // Validation des champs de livraison
            fields.forEach(field => {
                if (!validateField(field.id, field.error)) {
                    isValid = false;
                }
            });
            
            // Validation des champs de facturation si nécessaire
            if (!document.getElementById('meme_adresse_facturation').checked) {
                const facturationFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                         'facturation_code_postal', 'facturation_ville'];
                
                facturationFields.forEach(fieldId => {
                    if (!validateFacturationField(fieldId)) {
                        isValid = false;
                    }
                });
            }
            
            return isValid;
        }

        // Validation en temps réel
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
            
            // Validation en temps réel pour les champs de facturation
            const facturationFields = ['facturation_prenom', 'facturation_nom', 'facturation_adresse', 
                                     'facturation_code_postal', 'facturation_ville'];
            facturationFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('blur', () => {
                        validateFacturationField(fieldId);
                    });
                    
                    field.addEventListener('input', () => {
                        field.classList.remove('error-field');
                    });
                }
            });
        }

        // Soumission du formulaire
        function setupFormSubmission() {
            const form = document.getElementById('livraison-form');
            if (!form) return;
            
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (isLoading) return;
                
                // Validation
                if (!validateForm()) {
                    // Trouver le premier champ en erreur
                    const firstErrorField = document.querySelector('.error-field');
                    if (firstErrorField) {
                        firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                
                isLoading = true;
                const submitBtn = document.getElementById('submit-btn');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                submitBtn.disabled = true;
                
                // Préparer les données
                const formData = new FormData(this);
                const data = {};
                formData.forEach((value, key) => {
                    // Gérer les checkbox
                    if (key === 'emballage_cadeau' || key === 'meme_adresse_facturation') {
                        data[key] = value === '1' ? '1' : '0';
                    } else {
                        data[key] = value;
                    }
                });
                
                // Ajouter les IDs de session
                data.session_id = '<?php echo session_id(); ?>';
                data.panier_id = '<?php echo $panier_id ?? ""; ?>';
                data.client_id = '<?php echo $client_id ?? ""; ?>';
                
                try {
                    // Essayer l'API moderne d'abord
                    const response = await fetch('livraison.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-Mode': '1'
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // CORRECTION CRITIQUE : Rediriger vers paiement.php
                        window.location.href = result.redirect || 'paiement.php';
                    } else {
                        // Afficher les erreurs de validation
                        if (result.missing && result.missing.length > 0) {
                            result.missing.forEach(field => {
                                const errorId = 'error-' + field;
                                const errorElement = document.getElementById(errorId);
                                const fieldElement = document.getElementById(field);
                                if (errorElement && fieldElement) {
                                    fieldElement.classList.add('error-field');
                                    errorElement.textContent = 'Ce champ est requis';
                                    errorElement.classList.add('show');
                                }
                            });
                            
                            // Faire défiler jusqu'au premier champ manquant
                            const firstMissingField = document.getElementById(result.missing[0]);
                            if (firstMissingField) {
                                firstMissingField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }
                        
                        // Afficher un message d'erreur général
                        const messageDiv = document.getElementById('info-message');
                        if (messageDiv) {
                            messageDiv.className = 'message error';
                            let errorHtml = `<strong><i class="fas fa-exclamation-triangle"></i> Erreur :</strong><br>`;
                            errorHtml += `${result.message || 'Une erreur est survenue'}`;
                            
                            if (result.errors && result.errors.length > 0) {
                                errorHtml += '<ul>';
                                result.errors.forEach(error => {
                                    errorHtml += `<li>${error}</li>`;
                                });
                                errorHtml += '</ul>';
                            }
                            
                            messageDiv.innerHTML = errorHtml;
                        }
                        
                        isLoading = false;
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                } catch (error) {
                    console.error('Erreur API:', error);
                    // Fallback: soumission traditionnelle du formulaire
                    this.submit();
                }
            });
        }

        // Chargement initial
        document.addEventListener('DOMContentLoaded', function() {
            // Configuration des composants
            setupFacturationToggle();
            setupLivraisonOptions();
            setupRealTimeValidation();
            setupFormSubmission();
            
            // Essayer de charger une adresse existante
            try {
                const addressData = <?php
                    if (isset($_SESSION['adresse_livraison']) && !empty($_SESSION['adresse_livraison'])) {
                        echo json_encode($_SESSION['adresse_livraison']);
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
        });
    </script>
</body>
</html>