<?php
// ============================================
// FICHIER DE TRAITEMENT DU FORMULAIRE LIVRAISON - VERSION CORRIGÉE
// ============================================

// Activer le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Configuration de la base de données
if (!file_exists('config/database.php')) {
    // En mode API, retourner JSON d'erreur
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Fichier de configuration database introuvable',
        'errors' => ['Configuration manquante']
    ]);
    exit();
}

require_once 'config/database.php';

// ============================================
// DÉTECTION DU TYPE DE REQUÊTE
// ============================================

// Déterminer si c'est une requête API/JSON
$is_api_request = false;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

// Vérifier plusieurs indicateurs d'API
if (strpos($contentType, 'application/json') !== false) {
    $is_api_request = true;
} elseif (isset($_SERVER['HTTP_X_API_MODE']) && $_SERVER['HTTP_X_API_MODE'] == '1') {
    $is_api_request = true;
} elseif (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    $is_api_request = true;
} elseif (isset($_POST['api_mode']) && $_POST['api_mode'] == '1') {
    $is_api_request = true;
} elseif (!empty($_POST) && isset($_POST['api_mode'])) {
    $is_api_request = true;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES SELON LE TYPE DE REQUÊTE
// ============================================

$input = [];

if ($is_api_request) {
    // Mode API (JSON)
    $jsonInput = file_get_contents('php://input');
    
    if (!empty($jsonInput)) {
        $input = json_decode($jsonInput, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback aux données POST si le JSON est invalide
            error_log("Erreur parsing JSON: " . json_last_error_msg());
            $input = $_POST;
        }
    } else {
        // Aucune donnée JSON, utiliser POST
        $input = $_POST;
    }
} else {
    // Mode formulaire traditionnel
    $input = $_POST;
}

// ============================================
// VÉRIFICATION SI C'EST UNE SOUMISSION DE FORMULAIRE
// ============================================

$is_form_submission = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($input));

// Si accès direct (GET) sans données valides, rediriger
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['api']) && !isset($_GET['debug'])) {
    // Vérifier s'il y a un panier valide
    $has_valid_cart = false;
    
    try {
        $pdo = getPDOConnection();
        $session_id = session_id();
        
        if ($pdo) {
            $stmt = $pdo->prepare("
                SELECT COUNT(pi.id_item) as nb_items 
                FROM panier p 
                LEFT JOIN panier_items pi ON p.id_panier = pi.id_panier 
                WHERE p.session_id = ? AND p.statut = 'actif'
                GROUP BY p.id_panier
            ");
            $stmt->execute([$session_id]);
            $result = $stmt->fetch();
            
            if ($result && $result['nb_items'] > 0) {
                $has_valid_cart = true;
            }
        }
    } catch (Exception $e) {
        // Fallback à la session
        error_log("Erreur vérification panier: " . $e->getMessage());
        $has_valid_cart = isset($_SESSION['panier']) && !empty($_SESSION['panier']);
    }
    
    if (!$has_valid_cart) {
        // Mode API : retourner JSON
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Accès direct interdit. Veuillez d\'abord remplir votre panier.',
                'redirect' => 'panier.html'
            ]);
            exit();
        }
        
        // Mode normal : rediriger
        $_SESSION['erreur_message'] = 'Accès direct interdit. Veuillez d\'abord remplir votre panier.';
        header('Location: panier.html');
        exit();
    } else {
        // Afficher le formulaire si panier valide
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Accès GET non autorisé pour API. Utilisez POST.',
                'redirect' => 'livraison_form.php'
            ]);
            exit();
        }
        header('Location: livraison_form.php');
        exit();
    }
}

// ============================================
// TRAITEMENT DE LA SOUMISSION DU FORMULAIRE
// ============================================

// Initialiser la réponse
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'redirect' => 'paiement.php', // TOUJOURS définir une redirection par défaut
    'missing' => []
];

try {
    $pdo = getPDOConnection();
    if (!$pdo) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    $session_id = session_id();
    
    // ============================================
    // VALIDATION DES DONNÉES
    // ============================================
    
    $errors = [];
    $donnees_valides = [];
    
    // Champs obligatoires
    $required_fields = [
        'nom' => 'Nom',
        'prenom' => 'Prénom',
        'email' => 'Email',
        'adresse' => 'Adresse',
        'code_postal' => 'Code postal',
        'ville' => 'Ville',
        'pays' => 'Pays'
    ];
    
    foreach ($required_fields as $field => $label) {
        if (empty(trim($input[$field] ?? ''))) {
            $errors[] = "Le champ \"$label\" est obligatoire";
            $response['missing'][] = $field;
        } else {
            $donnees_valides[$field] = trim($input[$field]);
        }
    }
    
    // Validation spécifique
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide";
        $response['missing'][] = 'email';
    }
    
    if (!empty($input['code_postal']) && !preg_match('/^\d{5}$/', $input['code_postal'])) {
        $errors[] = "Le code postal doit contenir 5 chiffres";
        $response['missing'][] = 'code_postal';
    }
    
    if (!empty($input['telephone']) && !preg_match('/^[0-9]{10}$/', str_replace(' ', '', $input['telephone']))) {
        $errors[] = "Le numéro de téléphone doit contenir 10 chiffres";
    }
    
    // Vérifier si adresse de facturation différente
    $meme_adresse = isset($input['meme_adresse_facturation']) && $input['meme_adresse_facturation'] == '1';
    
    if (!$meme_adresse) {
        $facturation_fields = [
            'facturation_nom' => 'Nom (facturation)',
            'facturation_prenom' => 'Prénom (facturation)',
            'facturation_adresse' => 'Adresse (facturation)',
            'facturation_code_postal' => 'Code postal (facturation)',
            'facturation_ville' => 'Ville (facturation)'
        ];
        
        foreach ($facturation_fields as $field => $label) {
            if (empty(trim($input[$field] ?? ''))) {
                $errors[] = "Le champ \"$label\" est obligatoire lorsque l'adresse de facturation est différente";
                $response['missing'][] = $field;
            }
        }
    }
    
    // Si il y a des erreurs
    if (!empty($errors)) {
        $response['message'] = 'Des erreurs ont été trouvées dans le formulaire';
        $response['errors'] = $errors;
        
        // Sauvegarder les erreurs pour les afficher
        if (!$is_api_request) {
            $_SESSION['erreurs_livraison'] = $errors;
            $_SESSION['donnees_saisies'] = $input;
            $_SESSION['meme_adresse_facturation'] = $meme_adresse;
            
            if (!$meme_adresse) {
                $adresse_facturation = [
                    'nom' => $input['facturation_nom'] ?? '',
                    'prenom' => $input['facturation_prenom'] ?? '',
                    'societe' => $input['facturation_societe'] ?? '',
                    'adresse' => $input['facturation_adresse'] ?? '',
                    'complement' => $input['facturation_complement'] ?? '',
                    'code_postal' => $input['facturation_code_postal'] ?? '',
                    'ville' => $input['facturation_ville'] ?? '',
                    'pays' => $input['facturation_pays'] ?? 'France'
                ];
                $_SESSION['adresse_facturation'] = $adresse_facturation;
            }
        }
        
        // IMPORTANT: En mode API, retourner JSON immédiatement
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            // Mode formulaire traditionnel : rediriger vers le formulaire
            header('Location: livraison_form.php');
            exit();
        }
    }
    
    // ============================================
    // VÉRIFICATION DU PANIER
    // ============================================
    
    $stmt = $pdo->prepare("
        SELECT p.id_panier, p.id_client 
        FROM panier p 
        WHERE p.session_id = ? AND p.statut = 'actif'
        ORDER BY p.date_creation DESC LIMIT 1
    ");
    $stmt->execute([$session_id]);
    $panier = $stmt->fetch();
    
    if (!$panier) {
        throw new Exception('Aucun panier actif trouvé. Veuillez d\'abord ajouter des articles à votre panier.');
    }
    
    $panier_id = $panier['id_panier'];
    $client_id_existant = $panier['id_client'];
    
    // ============================================
    // GESTION DU CLIENT
    // ============================================
    
    $client_id = $client_id_existant;
    
    // Vérifier si le client existe déjà
    $email = trim($input['email']);
    $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client_existant = $stmt->fetch();
    
    if ($client_existant) {
        // Client existe déjà
        $client_id = $client_existant['id_client'];
        
        // Mettre à jour les informations du client
        $stmt = $pdo->prepare("
            UPDATE clients 
            SET nom = ?, prenom = ?, telephone = ?, dernier_connexion = NOW()
            WHERE id_client = ?
        ");
        $stmt->execute([
            trim($input['nom']),
            trim($input['prenom']),
            !empty($input['telephone']) ? trim($input['telephone']) : null,
            $client_id
        ]);
    } else {
        // CORRECTION: Ajouter mot_de_passe avec NULL
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                email, nom, prenom, telephone, date_inscription, 
                statut, is_temporary, created_from_session, mot_de_passe
            ) VALUES (?, ?, ?, ?, NOW(), 'actif', 1, ?, NULL)
        ");
        
        $stmt->execute([
            $email,
            trim($input['nom']),
            trim($input['prenom']),
            !empty($input['telephone']) ? trim($input['telephone']) : null,
            $session_id
        ]);
        
        $client_id = $pdo->lastInsertId();
    }
    
    // Mettre à jour le panier avec l'ID client
    $stmt = $pdo->prepare("
        UPDATE panier 
        SET id_client = ?, email_client = ?, telephone_client = ?
        WHERE id_panier = ?
    ");
    $stmt->execute([
        $client_id,
        $email,
        !empty($input['telephone']) ? trim($input['telephone']) : null,
        $panier_id
    ]);
    
    // ============================================
    // SAUVEGARDE DE L'ADRESSE DE LIVRAISON
    // ============================================
    
    // Désactiver les anciennes adresses principales
    $stmt = $pdo->prepare("
        UPDATE adresses 
        SET principale = 0 
        WHERE id_client = ? AND type_adresse = 'livraison'
    ");
    $stmt->execute([$client_id]);
    
    // Insérer la nouvelle adresse de livraison
    $stmt = $pdo->prepare("
        INSERT INTO adresses (
            id_client, type_adresse, nom, prenom, societe, adresse, complement,
            code_postal, ville, pays, telephone, principale, date_creation
        ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
    ");
    
    $stmt->execute([
        $client_id,
        trim($input['nom']),
        trim($input['prenom']),
        !empty($input['societe']) ? trim($input['societe']) : null,
        trim($input['adresse']),
        !empty($input['complement']) ? trim($input['complement']) : null,
        trim($input['code_postal']),
        trim($input['ville']),
        trim($input['pays']),
        !empty($input['telephone']) ? trim($input['telephone']) : null
    ]);
    
    $adresse_livraison_id = $pdo->lastInsertId();
    
    // ============================================
    // SAUVEGARDE DE L'ADRESSE DE FACTURATION
    // ============================================
    
    $adresse_facturation_id = null;
    
    if ($meme_adresse) {
        // Utiliser la même adresse
        $adresse_facturation_id = $adresse_livraison_id;
    } else {
        // Désactiver les anciennes adresses de facturation principales
        $stmt = $pdo->prepare("
            UPDATE adresses 
            SET principale = 0 
            WHERE id_client = ? AND type_adresse = 'facturation'
        ");
        $stmt->execute([$client_id]);
        
        // Insérer la nouvelle adresse de facturation
        $stmt = $pdo->prepare("
            INSERT INTO adresses (
                id_client, type_adresse, nom, prenom, societe, adresse, complement,
                code_postal, ville, pays, telephone, principale, date_creation
            ) VALUES (?, 'facturation', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $client_id,
            trim($input['facturation_nom']),
            trim($input['facturation_prenom']),
            !empty($input['facturation_societe']) ? trim($input['facturation_societe']) : null,
            trim($input['facturation_adresse']),
            !empty($input['facturation_complement']) ? trim($input['facturation_complement']) : null,
            trim($input['facturation_code_postal']),
            trim($input['facturation_ville']),
            trim($input['facturation_pays']),
            !empty($input['telephone']) ? trim($input['telephone']) : null
        ]);
        
        $adresse_facturation_id = $pdo->lastInsertId();
    }
    
    // ============================================
    // SAUVEGARDE DES OPTIONS DE LIVRAISON
    // ============================================
    
    $mode_livraison = $input['mode_livraison'] ?? 'standard';
    $emballage_cadeau = isset($input['emballage_cadeau']) && $input['emballage_cadeau'] == '1' ? 1 : 0;
    $instructions = !empty($input['instructions']) ? trim($input['instructions']) : null;
    
    // Sauvegarder dans la session pour la page de paiement
    $_SESSION['livraison_data'] = [
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'adresse_livraison_id' => $adresse_livraison_id,
        'adresse_facturation_id' => $adresse_facturation_id,
        'mode_livraison' => $mode_livraison,
        'emballage_cadeau' => $emballage_cadeau,
        'instructions' => $instructions,
        'email' => $email,
        'nom' => trim($input['nom']),
        'prenom' => trim($input['prenom'])
    ];
    
    // IMPORTANT: Sauvegarder aussi les données dans la session classique pour paiement.php
    $_SESSION['adresse_livraison'] = [
        'nom' => trim($input['nom']),
        'prenom' => trim($input['prenom']),
        'email' => $email,
        'telephone' => !empty($input['telephone']) ? trim($input['telephone']) : null,
        'societe' => !empty($input['societe']) ? trim($input['societe']) : null,
        'adresse' => trim($input['adresse']),
        'complement' => !empty($input['complement']) ? trim($input['complement']) : null,
        'code_postal' => trim($input['code_postal']),
        'ville' => trim($input['ville']),
        'pays' => trim($input['pays'])
    ];
    
    $_SESSION['mode_livraison'] = $mode_livraison;
    $_SESSION['emballage_cadeau'] = $emballage_cadeau;
    
    // Sauvegarder dans commande_temporaire
    $donnees_livraison = json_encode([
        'nom' => trim($input['nom']),
        'prenom' => trim($input['prenom']),
        'email' => $email,
        'telephone' => !empty($input['telephone']) ? trim($input['telephone']) : null,
        'societe' => !empty($input['societe']) ? trim($input['societe']) : null,
        'adresse' => trim($input['adresse']),
        'complement' => !empty($input['complement']) ? trim($input['complement']) : null,
        'code_postal' => trim($input['code_postal']),
        'ville' => trim($input['ville']),
        'pays' => trim($input['pays']),
        'mode_livraison' => $mode_livraison,
        'emballage_cadeau' => $emballage_cadeau,
        'instructions' => $instructions
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO commande_temporaire (
            panier_id, donnees_livraison, mode_livraison, emballage_cadeau, instructions
        ) VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            donnees_livraison = VALUES(donnees_livraison),
            mode_livraison = VALUES(mode_livraison),
            emballage_cadeau = VALUES(emballage_cadeau),
            instructions = VALUES(instructions)
    ");
    
    $stmt->execute([
        $panier_id,
        $donnees_livraison,
        $mode_livraison,
        $emballage_cadeau,
        $instructions
    ]);
    
    // Marquer comme autorisé pour le checkout
    $_SESSION['checkout_authorized'] = true;
    $_SESSION['checkout_time'] = time();
    $_SESSION['panier_id'] = $panier_id;
    $_SESSION['client_id'] = $client_id;
    
    // ============================================
    // PRÉPARER LA RÉPONSE
    // ============================================
    
    $response['success'] = true;
    $response['message'] = 'Adresse enregistrée avec succès';
    $response['data'] = [
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'adresse_livraison_id' => $adresse_livraison_id,
        'adresse_facturation_id' => $adresse_facturation_id
    ];
    
    // Logger la réussite
    $stmt = $pdo->prepare("
        INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
        VALUES ('info', 'info', ?, ?, ?)
    ");
    $stmt->execute([
        'Formulaire livraison traité avec succès - Redirection vers paiement.php',
        $client_id,
        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);
    
} catch (Exception $e) {
    $response['message'] = 'Une erreur est survenue: ' . $e->getMessage();
    $response['errors'] = [$e->getMessage()];
    
    // Logger l'erreur
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
                VALUES ('erreur', 'error', ?, ?, ?)
            ");
            $stmt->execute([
                'Erreur lors du traitement du formulaire livraison',
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                json_encode(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()])
            ]);
        } catch (Exception $logError) {
            // Ne rien faire si le log échoue
        }
    }
}

// ============================================
// ENVOI DE LA RÉPONSE FINALE
// ============================================

// IMPORTANT: TOUJOURS définir Content-Type pour les réponses API
if ($is_api_request) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit(); // ARRÊTER ICI - ne pas continuer vers le HTML
} else {
    // Mode formulaire traditionnel
    if ($response['success']) {
        // Rediriger vers la page de paiement
        header('Location: paiement.php');
        exit();
    } else {
        // Rediriger vers le formulaire avec les erreurs
        if (!isset($_SESSION['erreurs_livraison'])) {
            $_SESSION['erreurs_livraison'] = $response['errors'];
        }
        if (isset($input)) {
            $_SESSION['donnees_saisies'] = $input;
        }
        header('Location: livraison_form.php');
        exit();
    }
}

// ============================================
// SECTION SUIVANTE SUPPRIMÉE CAR INUTILE
// Le code ne doit JAMAIS atteindre cette section dans une requête API
// ============================================

// Si le code atteint ici, c'est une requête GET ou POST sans traitement approprié
// Dans ce cas, afficher une page d'erreur simple

// NE PAS UTILISER $response ICI - elle n'est pas définie dans ce contexte
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur - HEURE DU CADEAU</title>
    <style>
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
            text-align: center;
        }
        .error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background-color: #5a67d8;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 10px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #4c51bf;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-exclamation-triangle"></i> Erreur de traitement</h1>
        
        <div class="error">
            <i class="fas fa-exclamation-circle"></i>
            <h3>Erreur inattendue</h3>
            <p>Une erreur est survenue lors du traitement de votre demande.</p>
            <p>Veuillez réessayer ou contacter le support technique.</p>
        </div>
        
        <a href="livraison_form.php" class="btn">
            <i class="fas fa-arrow-left"></i> Retour au formulaire de livraison
        </a>
        
        <a href="panier.html" class="btn">
            <i class="fas fa-shopping-cart"></i> Retour au panier
        </a>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</body>
</html>