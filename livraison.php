<?php
// ============================================
// TRAITEMENT DU FORMULAIRE LIVRAISON - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// ============================================
// DÉTECTION DU TYPE DE REQUÊTE
// ============================================
$is_api_request = false;
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ||
    isset($_SERVER['HTTP_X_API_MODE']) ||
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') ||
    isset($_POST['api_mode'])) {
    $is_api_request = true;
}

// ============================================
// RÉCUPÉRATION DES DONNÉES
// ============================================
$input = [];
if ($is_api_request) {
    $jsonInput = file_get_contents('php://input');
    if (!empty($jsonInput)) {
        $input = json_decode($jsonInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur parsing JSON: " . json_last_error_msg());
            $input = $_POST;
        }
    } else {
        $input = $_POST;
    }
} else {
    $input = $_POST;
}

// ============================================
// VÉRIFICATION SI SOUMISSION DE FORMULAIRE
// ============================================
$is_form_submission = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($input));

// ============================================
// GESTION DES ACCÈS GET
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['api']) || isset($_GET['debug'])) {
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Méthode GET non supportée pour l\'API. Utilisez POST.',
                'redirect' => 'livraison_form.php'
            ]);
            exit();
        }
    } else {
        // Utiliser la fonction standardisée
        checkLivraisonAccess();
        header('Location: livraison_form.php');
        exit();
    }
}

// ============================================
// INITIALISATION DE LA RÉPONSE
// ============================================
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'redirect' => 'paiement.php',
    'missing' => []
];

// Si ce n'est pas une soumission de formulaire, on arrête ici
if (!$is_form_submission) {
    if ($is_api_request) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    exit();
}

// ============================================
// TRAITEMENT DU FORMULAIRE (POST)
// ============================================
try {
    $pdo = getPDOConnection();
    if (!$pdo) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    $session_id = session_id();
    
    // ============================================
    // SYNCHRONISATION DU PANIER AVANT TRAITEMENT
    // ============================================
    synchroniserPanierSessionBDD($pdo, $session_id);
    
    // ============================================
    // VALIDATION DES DONNÉES
    // ============================================
    $errors = [];
    $donnees_valides = [];
    
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
    
    if (!empty($errors)) {
        $response['message'] = 'Des erreurs ont été trouvées dans le formulaire';
        $response['errors'] = $errors;
        
        if (!$is_api_request) {
            addCheckoutErrors($errors);
            $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] = $input;
            $_SESSION[SESSION_KEY_CHECKOUT]['meme_adresse_facturation'] = $meme_adresse;
            
            if (!$meme_adresse) {
                $_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation'] = [
                    'nom' => $input['facturation_nom'] ?? '',
                    'prenom' => $input['facturation_prenom'] ?? '',
                    'societe' => $input['facturation_societe'] ?? '',
                    'adresse' => $input['facturation_adresse'] ?? '',
                    'complement' => $input['facturation_complement'] ?? '',
                    'code_postal' => $input['facturation_code_postal'] ?? '',
                    'ville' => $input['facturation_ville'] ?? '',
                    'pays' => $input['facturation_pays'] ?? 'France'
                ];
            }
        }
        
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode($response);
            exit();
        } else {
            header('Location: livraison_form.php');
            exit();
        }
    }
    
    // ============================================
    // VÉRIFICATION DU PANIER
    // ============================================
    $panier_id = $input['panier_id'] ?? $_SESSION[SESSION_KEY_PANIER_ID] ?? null;
    
    if (!$panier_id && hasValidCart()) {
        // Créer un panier en BDD pour cette session
        $stmt = $pdo->prepare("
            INSERT INTO panier (session_id, statut, date_creation)
            VALUES (?, 'actif', NOW())
        ");
        $stmt->execute([$session_id]);
        $panier_id = $pdo->lastInsertId();
        $_SESSION[SESSION_KEY_PANIER_ID] = $panier_id;
        
        // Ajouter les items du panier session
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
    }
    
    if (!$panier_id) {
        throw new Exception('Aucun panier actif trouvé.');
    }
    
    // ============================================
    // GESTION DU CLIENT
    // ============================================
    $email = trim($input['email']);
    $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ?");
    $stmt->execute([$email]);
    $client_existant = $stmt->fetch();
    
    if ($client_existant) {
        $client_id = $client_existant['id_client'];
        $_SESSION[SESSION_KEY_CLIENT_ID] = $client_id;
        
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
        $_SESSION[SESSION_KEY_CLIENT_ID] = $client_id;
    }
    
    // Mettre à jour le panier avec les infos client
    $stmt = $pdo->prepare("
        UPDATE panier 
        SET id_client = ?, email_client = ?, telephone_client = ?, date_modification = NOW()
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
    $stmt = $pdo->prepare("
        UPDATE adresses 
        SET principale = 0 
        WHERE id_client = ? AND type_adresse = 'livraison'
    ");
    $stmt->execute([$client_id]);
    
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
        $adresse_facturation_id = $adresse_livraison_id;
    } else {
        $stmt = $pdo->prepare("
            UPDATE adresses 
            SET principale = 0 
            WHERE id_client = ? AND type_adresse = 'facturation'
        ");
        $stmt->execute([$client_id]);
        
        $stmt = $pdo->prepare("
            INSERT INTO adresses (
                id_client, type_adresse, nom, prenom, societe, adresse, complement,
                code_postal, ville, pays, telephone, principale, date_creation
            ) VALUES (?, 'facturation', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $client_id,
            trim($input['facturation_nom'] ?? $input['nom']),
            trim($input['facturation_prenom'] ?? $input['prenom']),
            !empty($input['facturation_societe']) ? trim($input['facturation_societe']) : null,
            trim($input['facturation_adresse'] ?? $input['adresse']),
            !empty($input['facturation_complement']) ? trim($input['facturation_complement']) : null,
            trim($input['facturation_code_postal'] ?? $input['code_postal']),
            trim($input['facturation_ville'] ?? $input['ville']),
            trim($input['facturation_pays'] ?? $input['pays']),
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
    
    // ============================================
    // MISE À JOUR DE LA SESSION CENTRALISÉE
    // ============================================
    $_SESSION[SESSION_KEY_CHECKOUT] = [
        'panier_id' => $panier_id,
        'client_id' => $client_id,
        'client_email' => $email,
        'adresse_livraison' => [
            'id' => $adresse_livraison_id,
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
        ],
        'adresse_facturation' => $meme_adresse ? null : [
            'id' => $adresse_facturation_id,
            'nom' => trim($input['facturation_nom'] ?? $input['nom']),
            'prenom' => trim($input['facturation_prenom'] ?? $input['prenom']),
            'societe' => !empty($input['facturation_societe']) ? trim($input['facturation_societe']) : null,
            'adresse' => trim($input['facturation_adresse'] ?? $input['adresse']),
            'complement' => !empty($input['facturation_complement']) ? trim($input['facturation_complement']) : null,
            'code_postal' => trim($input['facturation_code_postal'] ?? $input['code_postal']),
            'ville' => trim($input['facturation_ville'] ?? $input['ville']),
            'pays' => trim($input['facturation_pays'] ?? $input['pays'])
        ],
        'mode_livraison' => $mode_livraison,
        'emballage_cadeau' => (bool)$emballage_cadeau,
        'instructions' => $instructions,
        'etape' => 'paiement',
        'date_creation' => $_SESSION[SESSION_KEY_CHECKOUT]['date_creation'] ?? date('Y-m-d H:i:s'),
        'date_modification' => date('Y-m-d H:i:s'),
        'validation' => [
            'panier_valide' => true,
            'adresse_valide' => true,
            'paiement_autorise' => false
        ]
    ];
    
    // Sauvegarde dans commande_temporaire
    $donnees_livraison = json_encode($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
    
    try {
        // Vérifier si une entrée existe déjà
        $stmt = $pdo->prepare("SELECT id FROM commande_temporaire WHERE panier_id = ?");
        $stmt->execute([$panier_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            $stmt = $pdo->prepare("
                UPDATE commande_temporaire 
                SET donnees_livraison = ?, mode_livraison = ?, emballage_cadeau = ?, instructions = ?, date_creation = NOW()
                WHERE panier_id = ?
            ");
            $stmt->execute([$donnees_livraison, $mode_livraison, $emballage_cadeau, $instructions, $panier_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO commande_temporaire (
                    panier_id, donnees_livraison, mode_livraison, emballage_cadeau, instructions, date_creation
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$panier_id, $donnees_livraison, $mode_livraison, $emballage_cadeau, $instructions]);
        }
    } catch (PDOException $e) {
        error_log("Erreur commande_temporaire: " . $e->getMessage());
    }
    
    // Logger la réussite
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, metadata)
            VALUES ('info', 'info', ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Formulaire livraison traité avec succès - Redirection vers paiement.php',
            $client_id,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            json_encode(['panier_id' => $panier_id, 'mode_livraison' => $mode_livraison])
        ]);
    } catch (Exception $e) {
        error_log("Erreur log: " . $e->getMessage());
    }
    
    $response['success'] = true;
    $response['message'] = 'Adresse enregistrée avec succès';
    $response['redirect'] = 'paiement.php';
    $response['data'] = [
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'adresse_livraison_id' => $adresse_livraison_id
    ];
    
} catch (Exception $e) {
    $response['message'] = 'Une erreur est survenue: ' . $e->getMessage();
    $response['errors'] = [$e->getMessage()];
    
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
            // Ignorer les erreurs de log
        }
    }
}

if ($is_api_request) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    if ($response['success']) {
        header('Location: paiement.php');
        exit();
    } else {
        addCheckoutErrors($response['errors']);
        if (isset($input)) {
            $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] = $input;
        }
        header('Location: livraison_form.php');
        exit();
    }
}
?>