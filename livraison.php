<?php
// livraison.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// ============================================
// DÉBOGAGE - Activer temporairement pour diagnostiquer
// ============================================
$debug_mode = false;
if ($debug_mode) {
    error_log("=== DÉBOGAGE livraison.php ===");
    error_log("Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("POST data: " . print_r($_POST, true));
    error_log("Headers: " . print_r(getallheaders(), true));
    error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'non défini'));
    error_log("Session ID: " . session_id());
}

// ============================================
// VÉRIFICATION DE L'ACCÈS
// ============================================

// Vérifier si on vient du formulaire
$from_form = false;
$is_api_mode = false;

// Méthode 1: Via le champ POST
if (isset($_POST['from_livraison_form']) && $_POST['from_livraison_form'] == '1') {
    $from_form = true;
}

// Méthode 2: Via header API
if (isset($_SERVER['HTTP_X_API_MODE']) && $_SERVER['HTTP_X_API_MODE'] == '1') {
    $from_form = true;
    $is_api_mode = true;
}

// Méthode 3: Via champ POST api_mode
if (isset($_POST['api_mode']) && $_POST['api_mode'] == '1') {
    $from_form = true;
    $is_api_mode = true;
}

// Méthode 4: Si données JSON sont envoyées
if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $json_data = json_decode($input, true);
        if ($json_data !== null) {
            // Fusionner les données JSON avec $_POST
            $_POST = array_merge($_POST, $json_data);
            $from_form = true;
            $is_api_mode = true;
        }
    }
}

// ============================================
// RÉCUPÉRATION DU PANIER_ID AVANT TOUTE CHOSE
// ============================================

// Essayer d'abord depuis la session
$panier_id = $_SESSION['panier_id'] ?? null;

// Si pas dans la session, essayer depuis POST
if (!$panier_id && isset($_POST['panier_id']) && !empty($_POST['panier_id'])) {
    $panier_id = $_POST['panier_id'];
}

// Si toujours pas de panier_id, essayer de le récupérer depuis la BDD basé sur la session
if (!$panier_id && session_id()) {
    try {
        // Connexion temporaire pour récupérer le panier
        $temp_pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $temp_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $session_id = session_id();
        $stmt = $temp_pdo->prepare("
            SELECT id_panier 
            FROM panier 
            WHERE session_id = ? AND statut = 'actif'
            ORDER BY date_modification DESC LIMIT 1
        ");
        $stmt->execute([$session_id]);
        $panier_db = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($panier_db && isset($panier_db['id_panier'])) {
            $panier_id = $panier_db['id_panier'];
            $_SESSION['panier_id'] = $panier_id;
        }
    } catch (Exception $e) {
        error_log("Erreur récupération panier: " . $e->getMessage());
    }
}

if (!$from_form && !$panier_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé. Veuillez remplir le formulaire de livraison d\'abord.',
        'debug_info' => [
            'method' => $_SERVER['REQUEST_METHOD'],
            'has_post' => !empty($_POST),
            'has_panier_id' => !empty($panier_id),
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'non défini'
        ]
    ]);
    exit();
}

// Vérifier si nous avons un panier actif
if (!$panier_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Panier introuvable. Veuillez retourner au panier.',
        'redirect' => 'panier.php'
    ]);
    exit();
}

// ============================================
// CONNEXION À LA BASE DE DONNÉES
// ============================================

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
        "Philippe",
        "l@99339R"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ]);
    exit();
}

// ============================================
// CORRECTION : RÉCUPÉRATION ET VALIDATION DES DONNÉES
// ============================================

// Définir les champs requis AVEC leurs noms exacts
$required_fields = [
    'nom' => 'Nom',
    'prenom' => 'Prénom', 
    'email' => 'Email',
    'adresse' => 'Adresse',
    'code_postal' => 'Code postal',
    'ville' => 'Ville'
];

$errors = [];
$missing = [];
$donnees_validees = [];

// Valider chaque champ requis
foreach ($required_fields as $field => $label) {
    // Vérifier si le champ existe et n'est pas vide
    if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
        $missing[] = $field;
        $errors[] = "Le champ '$label' est requis.";
    } else {
        // Nettoyer et stocker la valeur
        $donnees_validees[$field] = trim($_POST[$field]);
    }
}

// Si des champs manquent, retourner les erreurs
if (!empty($missing)) {
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs obligatoires.',
        'missing' => $missing,
        'errors' => $errors,
        'debug_data' => $debug_mode ? $_POST : null
    ]);
    exit();
}

// ============================================
// VALIDATIONS SPÉCIFIQUES
// ============================================

// Validation de l'email
if (!filter_var($donnees_validees['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Adresse email invalide.',
        'missing' => ['email'],
        'errors' => ['Veuillez entrer une adresse email valide.']
    ]);
    exit();
}

// Validation du code postal (format français)
if (!preg_match('/^[0-9]{5}$/', $donnees_validees['code_postal'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Code postal invalide.',
        'missing' => ['code_postal'],
        'errors' => ['Le code postal doit contenir 5 chiffres.']
    ]);
    exit();
}

// ============================================
// RÉCUPÉRATION DES DONNÉES OPTIONNELLES
// ============================================

$donnees_optional = [
    'societe' => $_POST['societe'] ?? '',
    'complement' => $_POST['complement'] ?? '',
    'telephone' => $_POST['telephone'] ?? '',
    'pays' => $_POST['pays'] ?? 'France',
    'instructions' => $_POST['instructions'] ?? '',
    'mode_livraison' => $_POST['mode_livraison'] ?? 'standard',
    'emballage_cadeau' => isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1' ? 1 : 0
];

// Fusionner les données validées et optionnelles
$donnees_livraison_completes = array_merge($donnees_validees, $donnees_optional);

// ============================================
// GESTION DE L'ADRESSE DE FACTURATION
// ============================================

$meme_adresse = isset($_POST['meme_adresse_facturation']) && $_POST['meme_adresse_facturation'] == '1';

if ($meme_adresse) {
    // Utiliser la même adresse pour la facturation
    $donnees_facturation = $donnees_livraison_completes;
} else {
    // Récupérer l'adresse de facturation séparée
    $donnees_facturation = [
        'prenom' => $_POST['facturation_prenom'] ?? '',
        'nom' => $_POST['facturation_nom'] ?? '',
        'societe' => $_POST['facturation_societe'] ?? '',
        'adresse' => $_POST['facturation_adresse'] ?? '',
        'complement' => $_POST['facturation_complement'] ?? '',
        'code_postal' => $_POST['facturation_code_postal'] ?? '',
        'ville' => $_POST['facturation_ville'] ?? '',
        'pays' => $_POST['facturation_pays'] ?? 'France'
    ];
    
    // Valider les champs de facturation si adresse différente
    $required_facturation = [
        'facturation_prenom' => 'Prénom (facturation)',
        'facturation_nom' => 'Nom (facturation)',
        'facturation_adresse' => 'Adresse (facturation)',
        'facturation_code_postal' => 'Code postal (facturation)',
        'facturation_ville' => 'Ville (facturation)'
    ];
    
    $missing_facturation = [];
    $errors_facturation = [];
    
    foreach ($required_facturation as $field => $label) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            $field_name = str_replace('facturation_', '', $field);
            $missing_facturation[] = $field_name;
            $errors_facturation[] = "Le champ '$label' est requis lorsque l'adresse de facturation est différente.";
        }
    }
    
    if (!empty($missing_facturation)) {
        echo json_encode([
            'success' => false,
            'message' => 'Veuillez remplir tous les champs de facturation.',
            'missing' => $missing_facturation,
            'errors' => $errors_facturation
        ]);
        exit();
    }
    
    // Validation du code postal de facturation
    if (!empty($donnees_facturation['code_postal']) && !preg_match('/^[0-9]{5}$/', $donnees_facturation['code_postal'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Code postal de facturation invalide.',
            'missing' => ['facturation_code_postal'],
            'errors' => ['Le code postal de facturation doit contenir 5 chiffres.']
        ]);
        exit();
    }
}

// ============================================
// PRÉPARATION DES DONNÉES POUR SAUVEGARDE
// ============================================

$donnees_pour_sauvegarde = [
    'livraison' => $donnees_livraison_completes,
    'facturation' => $donnees_facturation,
    'options' => [
        'mode_livraison' => $donnees_optional['mode_livraison'],
        'emballage_cadeau' => $donnees_optional['emballage_cadeau'],
        'instructions' => $donnees_optional['instructions']
    ]
];

// ============================================
// CALCUL DES FRAIS DE LIVRAISON
// ============================================

$frais_livraison = 0;
switch ($donnees_optional['mode_livraison']) {
    case 'express':
        $frais_livraison = 9.90;
        break;
    case 'relais':
        $frais_livraison = 4.90;
        break;
    case 'standard':
    default:
        $frais_livraison = 0;
}

// Ajouter frais d'emballage cadeau
if ($donnees_optional['emballage_cadeau']) {
    $frais_livraison += 3.90;
}

// ============================================
// SAUVEGARDE DANS LA SESSION ET LA BASE DE DONNÉES
// ============================================

try {
    // Sauvegarder dans la session
    $_SESSION['adresse_livraison'] = $donnees_livraison_completes;
    $_SESSION['adresse_facturation'] = $donnees_facturation;
    $_SESSION['options_livraison'] = $donnees_pour_sauvegarde['options'];
    $_SESSION['livraison_complete'] = true;
    $_SESSION['frais_livraison'] = $frais_livraison;
    $_SESSION['panier_id'] = $panier_id;
    
    // CORRECTION : Définir explicitement l'autorisation de checkout
    $_SESSION['checkout_authorized'] = true;
    $_SESSION['checkout_time'] = time();
    
    // ============================================
    // SAUVEGARDE DANS commande_temporaire
    // ============================================
    
    // Vérifier si l'ID du panier existe, sinon utiliser un ID temporaire basé sur la session
    if (empty($panier_id) || $panier_id === 'temp') {
        $panier_id = 'temp_' . session_id() . '_' . time();
        $_SESSION['panier_id'] = $panier_id;
    }
    
    // Préparer les données JSON pour la colonne donnees_livraison
    $donnees_json = json_encode([
        'prenom' => $donnees_livraison_completes['prenom'],
        'nom' => $donnees_livraison_completes['nom'],
        'societe' => $donnees_livraison_completes['societe'],
        'adresse' => $donnees_livraison_completes['adresse'],
        'complement' => $donnees_livraison_completes['complement'],
        'code_postal' => $donnees_livraison_completes['code_postal'],
        'ville' => $donnees_livraison_completes['ville'],
        'pays' => $donnees_livraison_completes['pays'],
        'telephone' => $donnees_livraison_completes['telephone'],
        'email' => $donnees_livraison_completes['email'],
        'mode_livraison' => $donnees_optional['mode_livraison'],
        'emballage_cadeau' => $donnees_optional['emballage_cadeau'],
        'instructions' => $donnees_optional['instructions']
    ]);
    
    // Vérifier si une entrée existe déjà pour ce panier
    $stmt = $pdo->prepare("SELECT id FROM commande_temporaire WHERE panier_id = ?");
    $stmt->execute([$panier_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Mettre à jour l'entrée existante
        $stmt = $pdo->prepare("
            UPDATE commande_temporaire 
            SET donnees_livraison = ?, 
                mode_livraison = ?, 
                emballage_cadeau = ?, 
                instructions = ?,
                date_creation = NOW()
            WHERE panier_id = ?
        ");
        $success = $stmt->execute([
            $donnees_json, 
            $donnees_optional['mode_livraison'], 
            $donnees_optional['emballage_cadeau'], 
            $donnees_optional['instructions'], 
            $panier_id
        ]);
    } else {
        // Insérer une nouvelle entrée
        $stmt = $pdo->prepare("
            INSERT INTO commande_temporaire 
            (panier_id, donnees_livraison, mode_livraison, emballage_cadeau, instructions, date_creation) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $success = $stmt->execute([
            $panier_id, 
            $donnees_json, 
            $donnees_optional['mode_livraison'], 
            $donnees_optional['emballage_cadeau'], 
            $donnees_optional['instructions']
        ]);
    }
    
    if (!$success) {
        throw new Exception("Erreur lors de l'enregistrement dans commande_temporaire");
    }
    
    // ============================================
    // MISE À JOUR OU CRÉATION DU CLIENT
    // ============================================
    
    $client_id = $_POST['client_id'] ?? $_SESSION['client_id'] ?? null;
    
    if (!$client_id) {
        // Chercher si le client existe déjà par email
        $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ?");
        $stmt->execute([$donnees_livraison_completes['email']]);
        $existing_client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_client) {
            $client_id = $existing_client['id_client'];
        } else {
            // Créer un nouveau client temporaire
            $stmt = $pdo->prepare("
                INSERT INTO clients 
                (email, nom, prenom, telephone, date_inscription, is_temporary) 
                VALUES (?, ?, ?, ?, NOW(), 1)
            ");
            
            // Gérer le champ mot_de_passe qui ne peut pas être NULL
            $stmt->execute([
                $donnees_livraison_completes['email'],
                $donnees_livraison_completes['nom'],
                $donnees_livraison_completes['prenom'],
                $donnees_livraison_completes['telephone'] ?? ''
            ]);
            
            $client_id = $pdo->lastInsertId();
        }
        
        $_SESSION['client_id'] = $client_id;
        
        // Mettre à jour le panier avec l'ID client
        if ($panier_id && is_numeric($panier_id)) {
            $stmt = $pdo->prepare("UPDATE panier SET id_client = ? WHERE id_panier = ?");
            $stmt->execute([$client_id, $panier_id]);
        }
    } else {
        // Mettre à jour l'email du client si différent
        $client_id = (int)$client_id;
        
        $stmt = $pdo->prepare("UPDATE clients SET email = ?, telephone = ? WHERE id_client = ?");
        $stmt->execute([
            $donnees_livraison_completes['email'],
            $donnees_livraison_completes['telephone'] ?? '',
            $client_id
        ]);
    }
    
    // ============================================
    // ENREGISTREMENT DANS LA TABLE ADRESSES
    // ============================================
    
    if ($client_id) {
        // Vérifier si une adresse principale existe déjà
        $stmt = $pdo->prepare("
            SELECT id_adresse FROM adresses 
            WHERE id_client = ? AND type_adresse = 'livraison' AND principale = 1
        ");
        $stmt->execute([$client_id]);
        $existing_address = $stmt->fetch();
        
        if ($existing_address) {
            // Mettre à jour l'adresse existante
            $stmt = $pdo->prepare("
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
                    date_creation = NOW()
                WHERE id_adresse = ?
            ");
            $stmt->execute([
                $donnees_livraison_completes['nom'],
                $donnees_livraison_completes['prenom'],
                $donnees_livraison_completes['societe'],
                $donnees_livraison_completes['adresse'],
                $donnees_livraison_completes['complement'],
                $donnees_livraison_completes['code_postal'],
                $donnees_livraison_completes['ville'],
                $donnees_livraison_completes['pays'],
                $donnees_livraison_completes['telephone'] ?? '',
                $existing_address['id_adresse']
            ]);
        } else {
            // Insérer une nouvelle adresse
            $stmt = $pdo->prepare("
                INSERT INTO adresses 
                (id_client, type_adresse, nom, prenom, societe, adresse, complement, 
                 code_postal, ville, pays, telephone, principale, date_creation)
                VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            $stmt->execute([
                $client_id,
                $donnees_livraison_completes['nom'],
                $donnees_livraison_completes['prenom'],
                $donnees_livraison_completes['societe'],
                $donnees_livraison_completes['adresse'],
                $donnees_livraison_completes['complement'],
                $donnees_livraison_completes['code_postal'],
                $donnees_livraison_completes['ville'],
                $donnees_livraison_completes['pays'],
                $donnees_livraison_completes['telephone'] ?? ''
            ]);
        }
    }
    
    // ============================================
    // ENREGISTRER UN LOG DE SUCCÈS
    // ============================================
    
    $log_user_id = $client_id ? (int)$client_id : null;
    
    $stmt = $pdo->prepare("
        INSERT INTO logs 
        (type_log, niveau, message, utilisateur_id, ip_address, date_log) 
        VALUES ('info', 'info', 'Formulaire livraison traité avec succès', ?, ?, NOW())
    ");
    $stmt->execute([$log_user_id, $_SERVER['REMOTE_ADDR'] ?? '']);
    
    // ============================================
    // PRÉPARER LA RÉPONSE DE SUCCÈS
    // ============================================
    
    // CORRECTION : Utiliser 'paiement.php' au lieu de 'paiement_form.php'
    $response = [
        'success' => true,
        'message' => 'Adresse de livraison enregistrée avec succès.',
        'redirect' => 'paiement.php', // CORRECTION ICI
        'data' => [
            'adresse_livraison' => $donnees_livraison_completes,
            'adresse_facturation' => $donnees_facturation,
            'options' => $donnees_pour_sauvegarde['options'],
            'frais_livraison' => $frais_livraison,
            'panier_id' => $panier_id,
            'client_id' => $client_id
        ]
    ];
    
    // Ajouter des informations de débogage si activé
    if ($debug_mode) {
        $response['debug'] = [
            'post_data' => $_POST,
            'session_data' => [
                'checkout_authorized' => $_SESSION['checkout_authorized'] ?? null,
                'panier_id' => $_SESSION['panier_id'] ?? null,
                'client_id' => $_SESSION['client_id'] ?? null
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Erreur lors de l'enregistrement: " . $e->getMessage());
    
    // Enregistrer l'erreur dans les logs
    try {
        $log_user_id = $client_id ?? null;
        
        $stmt = $pdo->prepare("
            INSERT INTO logs 
            (type_log, niveau, message, utilisateur_id, ip_address, metadata, date_log) 
            VALUES ('erreur', 'error', 'Erreur lors du traitement du formulaire livraison', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $log_user_id, 
            $_SERVER['REMOTE_ADDR'] ?? '',
            json_encode([
                'error' => $e->getMessage(), 
                'trace' => $e->getTraceAsString(),
                'post_data' => $_POST
            ])
        ]);
    } catch (Exception $logError) {
        error_log("Erreur lors de l'enregistrement du log: " . $logError->getMessage());
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'enregistrement.',
        'errors' => ['Erreur technique: ' . $e->getMessage()],
        'debug' => $debug_mode ? ['exception' => $e->getMessage(), 'trace' => $e->getTraceAsString()] : null
    ]);
}
?>