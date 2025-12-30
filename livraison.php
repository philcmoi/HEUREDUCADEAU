<?php
// ============================================
// FICHIER DE TRAITEMENT DU FORMULAIRE LIVRAISON
// ============================================

session_start();

// Activer l'affichage des erreurs pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fonction de connexion PDO (remplace config/database.php)
function getPDOConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",  // Utilisateur
            "l@99339R"   // Mot de passe
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion DB: " . $e->getMessage());
        // Pour le débogage, afficher l'erreur
        if (isset($_GET['debug'])) {
            die("Erreur DB: " . $e->getMessage());
        }
        return null;
    }
}

// Vérifier si c'est une soumission de formulaire
$is_form_submission = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['api_mode']));
$is_api_request = isset($_SERVER['HTTP_X_API_MODE']) || 
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// Initialiser la réponse AVANT tout output
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'redirect' => null
];

// Si accès direct (GET) sans données valides, rediriger
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['api']) && !isset($_GET['debug'])) {
    // Vérifier s'il y a un panier valide
    $has_valid_cart = false;
    $pdo = getPDOConnection();
    
    if ($pdo) {
        try {
            $session_id = session_id();
            
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
        } catch (Exception $e) {
            // Fallback à la session
            error_log("Erreur vérification panier: " . $e->getMessage());
        }
    }
    
    // Fallback aux sessions
    if (!$has_valid_cart) {
        $has_valid_cart = isset($_SESSION['panier']) && !empty($_SESSION['panier']);
    }
    
    if (!$has_valid_cart) {
        // Mode API : retourner JSON
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Accès direct interdit. Veuillez d\'abord remplir votre panier.',
                'redirect' => 'panier.php'
            ]);
            exit();
        }
        
        // Mode normal : rediriger
        $_SESSION['erreur_message'] = 'Accès direct interdit. Veuillez d\'abord remplir votre panier.';
        header('Location: panier.php');
        exit();
    } else {
        // Afficher le formulaire si panier valide
        header('Location: livraison_form.php');
        exit();
    }
}

// ============================================
// TRAITEMENT DE LA SOUMISSION DU FORMULAIRE
// ============================================

try {
    $pdo = getPDOConnection();
    if (!$pdo) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    $session_id = session_id();
    
    // Récupérer les données selon le mode
    if ($is_api_request && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // Mode API (JSON)
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Données JSON invalides');
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Mode formulaire traditionnel
        $input = $_POST;
    } else {
        throw new Exception('Méthode non autorisée');
    }
    
    // ============================================
    // VALIDATION DES DONNÉES
    // ============================================
    
    $errors = [];
    $donnees_valides = [];
    $missing_fields = [];
    
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
            $missing_fields[] = $field;
        } else {
            $donnees_valides[$field] = trim($input[$field]);
        }
    }
    
    // Validation spécifique
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide";
        if (!in_array('email', $missing_fields)) $missing_fields[] = 'email';
    }
    
    if (!empty($input['code_postal']) && !preg_match('/^\d{5}$/', $input['code_postal'])) {
        $errors[] = "Le code postal doit contenir 5 chiffres";
        if (!in_array('code_postal', $missing_fields)) $missing_fields[] = 'code_postal';
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
                $missing_fields[] = $field;
            }
        }
    }
    
    // Si il y a des erreurs
    if (!empty($errors)) {
        $response['message'] = 'Des erreurs ont été trouvées dans le formulaire';
        $response['errors'] = $errors;
        $response['missing'] = $missing_fields;
        
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
        
        if ($is_api_request) {
            header('Content-Type: application/json');
            echo json_encode($response);
        } else {
            header('Location: livraison_form.php');
        }
        exit();
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
        // Créer un nouveau client (temporaire pour l'instant)
        $stmt = $pdo->prepare("
            INSERT INTO clients (
                email, nom, prenom, telephone, date_inscription, 
                statut, is_temporary, created_from_session
            ) VALUES (?, ?, ?, ?, NOW(), 'actif', 1, ?)
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
    
    // ============================================
    // CRÉATION DES DONNÉES DE SESSION POUR PAIEMENT.PHP
    // ============================================
    
    // 1. Récupérer les items du panier pour les mettre en session
    $stmt = $pdo->prepare("
        SELECT 
            pi.id_item,
            pi.id_produit,
            pi.quantite,
            pi.prix_unitaire,
            pi.options,
            p.nom as produit_nom,
            p.reference as produit_reference,
            p.prix_ttc as produit_prix,
            p.id_categorie
        FROM panier_items pi
        JOIN produits p ON pi.id_produit = p.id_produit
        WHERE pi.id_panier = ?
    ");
    $stmt->execute([$panier_id]);
    $panier_items_db = $stmt->fetchAll();
    
    // Formater les items pour la session
    $panier_items = [];
    foreach ($panier_items_db as $item) {
        $panier_items[] = [
            'id' => $item['id_item'],
            'produit_id' => $item['id_produit'],
            'nom' => $item['produit_nom'],
            'reference' => $item['produit_reference'],
            'prix' => floatval($item['prix_unitaire']),
            'prix_ttc' => floatval($item['produit_prix']),
            'quantite' => intval($item['quantite']),
            'total' => floatval($item['prix_unitaire']) * intval($item['quantite']),
            'categorie_id' => $item['id_categorie'],
            'options' => json_decode($item['options'] ?? '{}', true)
        ];
    }
    
    // 2. Calculer le total du panier
    $stmt = $pdo->prepare("
        SELECT 
            SUM(pi.quantite * pi.prix_unitaire) as sous_total,
            COUNT(pi.id_item) as items_count
        FROM panier_items pi
        WHERE pi.id_panier = ?
    ");
    $stmt->execute([$panier_id]);
    $panier_totals = $stmt->fetch();
    
    $sous_total = floatval($panier_totals['sous_total'] ?? 0);
    $items_count = intval($panier_totals['items_count'] ?? 0);
    
    // 3. Calculer les frais de livraison
    $frais_livraison = 0;
    switch ($mode_livraison) {
        case 'express':
            $frais_livraison = 9.90;
            break;
        case 'relais':
            $frais_livraison = 4.90;
            break;
        case 'standard':
        default:
            $frais_livraison = 0;
            break;
    }
    
    // 4. Frais d'emballage
    $frais_emballage = $emballage_cadeau ? 3.90 : 0;
    
    // 5. Récupérer l'adresse complète pour la session
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            c.email as client_email
        FROM adresses a
        LEFT JOIN clients c ON a.id_client = c.id_client
        WHERE a.id_adresse = ?
    ");
    $stmt->execute([$adresse_livraison_id]);
    $adresse_livraison_complete = $stmt->fetch();
    
    // Ajouter l'email si pas déjà présent
    if ($adresse_livraison_complete && !isset($adresse_livraison_complete['email'])) {
        $adresse_livraison_complete['email'] = $email;
    }
    
    // ============================================
    // SAUVEGARDE DANS LA SESSION
    // ============================================
    
    // Structure principale pour livraison_data
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
    
    // Structure pour paiement.php
    $_SESSION['panier'] = [
        'items' => $panier_items,
        'sous_total' => $sous_total,
        'items_count' => $items_count,
        'total' => $sous_total
    ];
    
    $_SESSION['commande'] = [
        'adresse_livraison' => $adresse_livraison_complete ?: [
            'nom' => trim($input['nom']),
            'prenom' => trim($input['prenom']),
            'email' => $email,
            'adresse' => trim($input['adresse']),
            'complement' => trim($input['complement'] ?? ''),
            'code_postal' => trim($input['code_postal']),
            'ville' => trim($input['ville']),
            'pays' => trim($input['pays']),
            'telephone' => trim($input['telephone'] ?? '')
        ],
        'livraison' => [
            'mode' => $mode_livraison,
            'frais' => $frais_livraison
        ],
        'emballage_cadeau' => $emballage_cadeau,
        'frais_emballage' => $frais_emballage,
        'instructions' => $instructions,
        'mode_livraison' => $mode_livraison,
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'total' => $sous_total + $frais_livraison + $frais_emballage
    ];
    
    // Marquer comme autorisé pour le checkout
    $_SESSION['checkout_authorized'] = true;
    $_SESSION['checkout_time'] = time();
    
    // ============================================
    // SAUVEGARDE DANS COMMANDE_TEMPORAIRE
    // ============================================
    
    // Préparer les données pour commande_temporaire
    $donnees_livraison = [
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
    ];
    
    // Sauvegarder ou mettre à jour dans commande_temporaire
    $stmt = $pdo->prepare("
        SELECT id FROM commande_temporaire 
        WHERE panier_id = ? 
        ORDER BY date_creation DESC LIMIT 1
    ");
    $stmt->execute([$panier_id]);
    $commande_temp_existante = $stmt->fetch();
    
    if ($commande_temp_existante) {
        // Mettre à jour
        $stmt = $pdo->prepare("
            UPDATE commande_temporaire 
            SET donnees_livraison = ?, 
                mode_livraison = ?, 
                emballage_cadeau = ?, 
                instructions = ?,
                date_creation = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            json_encode($donnees_livraison),
            $mode_livraison,
            $emballage_cadeau,
            $instructions,
            $commande_temp_existante['id']
        ]);
    } else {
        // Insérer
        $stmt = $pdo->prepare("
            INSERT INTO commande_temporaire (
                panier_id, donnees_livraison, mode_livraison, 
                emballage_cadeau, instructions, date_creation
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $panier_id,
            json_encode($donnees_livraison),
            $mode_livraison,
            $emballage_cadeau,
            $instructions
        ]);
    }
    
    // ============================================
    // PRÉPARER LA RÉPONSE
    // ============================================
    
    $response['success'] = true;
    $response['message'] = 'Adresse enregistrée avec succès';
    $response['redirect'] = 'paiement.php';
    $response['data'] = [
        'client_id' => $client_id,
        'panier_id' => $panier_id,
        'adresse_livraison_id' => $adresse_livraison_id,
        'adresse_facturation_id' => $adresse_facturation_id,
        'sous_total' => $sous_total,
        'frais_livraison' => $frais_livraison,
        'frais_emballage' => $frais_emballage,
        'total' => $sous_total + $frais_livraison + $frais_emballage
    ];
    
    // Logger la réussite
    $stmt = $pdo->prepare("
        INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
        VALUES ('info', 'info', ?, ?, ?)
    ");
    $stmt->execute([
        'Formulaire livraison traité avec succès',
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
// ENVOI DE LA RÉPONSE
// ============================================

if ($is_api_request) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
} else {
    // Mode formulaire traditionnel
    if ($response['success']) {
        // Rediriger vers la page de paiement
        header('Location: ' . $response['redirect']);
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
?>