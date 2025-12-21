<?php
// ============================================
// VÉRIFICATION D'ACCÈS - PROTECTION CONTRE ACCÈS DIRECT
// ============================================

/**
 * Vérifie si l'accès à la page est autorisé
 */
function checkAccess() {
    // DÉMARRER LA SESSION EN PREMIER
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Autoriser les requêtes API
    if (isset($_GET['api']) && $_GET['api'] == '1') {
        return true;
    }
    
    // Autoriser les soumissions POST (formulaire)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return true;
    }
    
    // Vérifier si l'utilisateur a un panier non vide DANS LA SESSION PHP
    if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
        return true;
    }
    
    // Vérifier si le checkout a été autorisé par l'API panier
    if (isset($_SESSION['checkout_authorized']) && $_SESSION['checkout_authorized'] === true) {
        // Vérifier que l'autorisation n'est pas expirée (10 minutes)
        if (isset($_SESSION['checkout_time']) && (time() - $_SESSION['checkout_time']) <= 600) {
            return true;
        } else {
            // Autorisation expirée, nettoyer
            unset($_SESSION['checkout_authorized']);
            unset($_SESSION['checkout_time']);
        }
    }
    
    // Vérifier si l'utilisateur vient d'une page autorisée
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $allowed_referers = ['panier.html', 'panier.php', 'commande.html', 'livraison.php', 'index.php'];
    $has_allowed_referer = false;
    
    foreach ($allowed_referers as $allowed) {
        if (stripos($referer, $allowed) !== false) {
            $has_allowed_referer = true;
            break;
        }
    }
    
    // Autoriser si l'utilisateur vient d'une page autorisée ET a un panier
    if ($has_allowed_referer && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
        return true;
    }
    
    // Vérifier si c'est un rafraîchissement de la page (même URL)
    $current_url = $_SERVER['REQUEST_URI'];
    $is_refresh = $referer && strpos($referer, $current_url) !== false;
    
    if ($is_refresh && isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
        return true;
    }
    
    // Si aucune condition n'est remplie, accès non autorisé
    return false;
}

// Vérifier l'accès avant de continuer
if (!checkAccess() && !isset($_GET['api'])) {
    // Pour les requêtes AJAX/API, retourner une erreur JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé. Veuillez d\'abord remplir votre panier.',
            'redirect' => 'panier.html'
        ]);
        exit();
    }
    
    // Sinon rediriger vers la page du panier
    header('Location: panier.html');
    exit();
}

// ============================================
// DÉBUT DU CODE EXISTANT
// ============================================

// livraison.php - VERSION CORRIGÉE COMPLÈTE
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'db_config.php';

// ============================================
// FONCTIONS DE CONNEXION BDD
// ============================================

/**
 * Vérifie si la table existe
 */
function tableExists($tableName) {
    $db = getDB();
    if (!$db) return false;
    
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Erreur vérification table: " . $e->getMessage());
        return false;
    }
}

// ============================================
// FONCTIONS DE GESTION BDD (CORRIGÉES)
// ============================================

/**
 * Obtient l'ID client (temporaire ou permanent)
 */
function getClientId() {
    // Priorité 1: Client déjà en session
    if (isset($_SESSION['id_client']) && $_SESSION['id_client'] > 0) {
        return $_SESSION['id_client'];
    }
    
    // Priorité 2: Client temporaire en session
    if (isset($_SESSION['client_temp']) && isset($_SESSION['client_temp']['id'])) {
        $_SESSION['id_client'] = $_SESSION['client_temp']['id'];
        return $_SESSION['client_temp']['id'];
    }
    
    $session_id = session_id();
    $email = $_SESSION['email'] ?? '';
    
    $db = getDB();
    if (!$db) return null;
    
    try {
        // Chercher par email d'abord
        if (!empty($email) && tableExists('clients')) {
            $stmt = $db->prepare("SELECT id_client FROM clients WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $client = $stmt->fetch();
            
            if ($client) {
                $_SESSION['id_client'] = $client['id_client'];
                $_SESSION['client_temp'] = [
                    'id' => $client['id_client'],
                    'email' => $email
                ];
                return $client['id_client'];
            }
        }
        
        // Chercher par session (clients temporaires)
        if (tableExists('clients')) {
            $stmt = $db->prepare("
                SELECT id_client FROM clients 
                WHERE created_from_session = ? AND is_temporary = 1 
                LIMIT 1
            ");
            $stmt->execute([$session_id]);
            $client = $stmt->fetch();
            
            if ($client) {
                $_SESSION['id_client'] = $client['id_client'];
                $_SESSION['client_temp'] = [
                    'id' => $client['id_client'],
                    'email' => $email,
                    'is_temporary' => 1
                ];
                return $client['id_client'];
            }
        }
        
        return null;
    } catch (PDOException $e) {
        error_log("Erreur getClientId: " . $e->getMessage());
        return null;
    }
}

/**
 * Crée un client temporaire (corrigé pour la structure BDD)
 */
function createTempClient($email, $nom, $prenom, $telephone) {
    $db = getDB();
    if (!$db) {
        error_log("createTempClient: Pas de connexion BDD");
        return null;
    }
    
    if (!tableExists('clients')) {
        error_log("createTempClient: Table clients n'existe pas");
        return null;
    }
    
    try {
        // Vérifier si l'email existe déjà
        $stmt = $db->prepare("SELECT id_client FROM clients WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Mettre à jour la session et marquer comme non temporaire si déjà existant
            $stmt = $db->prepare("
                UPDATE clients 
                SET created_from_session = ?, is_temporary = 0 
                WHERE id_client = ?
            ");
            $stmt->execute([session_id(), $existing['id_client']]);
            
            error_log("DEBUG: Client existant réutilisé: " . $existing['id_client']);
            
            return $existing['id_client'];
        }
        
        // Créer un nouveau client temporaire selon la structure BDD
        $stmt = $db->prepare("
            INSERT INTO clients (
                email, mot_de_passe, nom, prenom, telephone, 
                is_temporary, newsletter, statut, date_inscription, created_from_session
            ) VALUES (?, '', ?, ?, ?, 1, 0, 'actif', NOW(), ?)
        ");
        
        $result = $stmt->execute([
            $email, 
            $nom, 
            $prenom, 
            $telephone,
            session_id()
        ]);
        
        if (!$result) {
            error_log("createTempClient: Échec insertion");
            return null;
        }
        
        $client_id = $db->lastInsertId();
        
        error_log("DEBUG: Client temporaire créé avec ID: $client_id");
        
        return $client_id;
    } catch (PDOException $e) {
        error_log("Erreur création client: " . $e->getMessage());
        return null;
    }
}

/**
 * Sauvegarde l'adresse en BDD (VERSION SIMPLIFIÉE et CORRIGÉE)
 */
function saveAddressToDB($addressData) {
    $db = getDB();
    if (!$db) {
        error_log("saveAddressToDB: Connexion BDD échouée");
        return false;
    }
    
    if (!tableExists('adresses')) {
        error_log("saveAddressToDB: Table adresses n'existe pas");
        return false;
    }
    
    // DEBUG: Log des données
    error_log("DEBUG saveAddressToDB - Données: " . json_encode($addressData));
    
    // Obtenir ou créer un client
    $client_id = getClientId();
    
    if (!$client_id) {
        error_log("DEBUG: Création client temporaire...");
        $client_id = createTempClient(
            $addressData['email'],
            $addressData['nom'],
            $addressData['prenom'],
            $addressData['telephone']
        );
        
        if (!$client_id) {
            error_log("saveAddressToDB: Échec création client temporaire");
            return false;
        }
        
        $_SESSION['id_client'] = $client_id;
        $_SESSION['client_temp'] = [
            'id' => $client_id,
            'email' => $addressData['email'],
            'is_temporary' => 1,
            'nom' => $addressData['nom'],
            'prenom' => $addressData['prenom']
        ];
        
        error_log("DEBUG: Client temporaire créé avec ID: $client_id");
    }
    
    try {
        // Vérifier la structure de la table
        $stmt_check = $db->query("DESCRIBE adresses");
        $columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        error_log("DEBUG: Colonnes table adresses: " . implode(', ', $columns));
        
        // Vérifier si une adresse de livraison existe déjà pour ce client
        if (in_array('type_adresse', $columns)) {
            $stmt = $db->prepare("
                SELECT id_adresse FROM adresses 
                WHERE id_client = ? AND type_adresse = 'livraison' 
                LIMIT 1
            ");
        } else {
            // Version sans type_adresse
            $stmt = $db->prepare("
                SELECT id_adresse FROM adresses 
                WHERE id_client = ? 
                LIMIT 1
            ");
        }
        
        $stmt->execute([$client_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            error_log("DEBUG: Adresse existante trouvée ID: " . $existing['id_adresse']);
            
            // Mettre à jour l'adresse existante
            $stmt = $db->prepare("
                UPDATE adresses SET
                    nom = ?, prenom = ?, societe = ?, adresse = ?,
                    complement = ?, code_postal = ?, ville = ?, pays = ?,
                    telephone = ?, date_creation = NOW()
                WHERE id_adresse = ?
            ");
            
            $params = [
                $addressData['nom'], 
                $addressData['prenom'], 
                $addressData['societe'] ?? '', 
                $addressData['adresse'],
                $addressData['complement'] ?? '', 
                $addressData['code_postal'],
                $addressData['ville'], 
                $addressData['pays'] ?? 'France',
                $addressData['telephone'], 
                $existing['id_adresse']
            ];
            
            $result = $stmt->execute($params);
            
            if ($result) {
                $address_id = $existing['id_adresse'];
                error_log("DEBUG: Adresse mise à jour avec succès ID: $address_id");
                return $address_id;
            } else {
                error_log("DEBUG: Échec mise à jour adresse");
                return false;
            }
        } else {
            error_log("DEBUG: Création nouvelle adresse pour client: $client_id");
            
            // Déterminer les colonnes disponibles
            $hasTypeAdresse = in_array('type_adresse', $columns);
            $hasPrincipale = in_array('principale', $columns);
            
            // Construire la requête INSERT adaptative
            $sql_columns = ['id_client', 'nom', 'prenom', 'societe', 'adresse', 'complement', 
                          'code_postal', 'ville', 'pays', 'telephone', 'date_creation'];
            $sql_placeholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?', 'NOW()'];
            
            if ($hasTypeAdresse) {
                array_splice($sql_columns, 1, 0, 'type_adresse');
                array_splice($sql_placeholders, 1, 0, "'livraison'");
            }
            
            if ($hasPrincipale) {
                $sql_columns[] = 'principale';
                $sql_placeholders[] = '1';
            }
            
            $columns_str = implode(', ', $sql_columns);
            $placeholders_str = implode(', ', $sql_placeholders);
            
            $sql = "INSERT INTO adresses ($columns_str) VALUES ($placeholders_str)";
            
            // Préparer les paramètres dans l'ordre correct
            $params = [
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
            ];
            
            // Si type_adresse est présent, nous devons réorganiser les paramètres
            if ($hasTypeAdresse) {
                // Pas besoin d'ajouter de paramètre pour 'livraison' car c'est une constante
                // Les paramètres restent les mêmes
            }
            
            error_log("DEBUG: SQL INSERT: $sql");
            error_log("DEBUG: Paramètres INSERT: " . json_encode($params));
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $address_id = $db->lastInsertId();
                error_log("DEBUG: Adresse créée avec succès ID: $address_id");
                return $address_id;
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("DEBUG: Échec insertion adresse - " . json_encode($errorInfo));
                return false;
            }
        }
        
    } catch (PDOException $e) {
        error_log("Erreur saveAddressToDB: " . $e->getMessage());
        error_log("Trace: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * Récupère l'adresse depuis la BDD
 */
function getAddressFromDB() {
    $client_id = getClientId();
    if (!$client_id) {
        error_log("DEBUG getAddressFromDB: Pas de client ID");
        return null;
    }
    
    $db = getDB();
    if (!$db || !tableExists('adresses')) {
        error_log("DEBUG getAddressFromDB: Pas de connexion ou table inexistante");
        return null;
    }
    
    try {
        // Vérifier la structure de la table
        $stmt_check = $db->query("DESCRIBE adresses");
        $columns = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('type_adresse', $columns)) {
            $stmt = $db->prepare("
                SELECT a.*, c.email, c.nom as client_nom, c.prenom as client_prenom
                FROM adresses a
                LEFT JOIN clients c ON a.id_client = c.id_client
                WHERE a.id_client = ? AND a.type_adresse = 'livraison'
                ORDER BY a.date_creation DESC 
                LIMIT 1
            ");
        } else {
            $stmt = $db->prepare("
                SELECT a.*, c.email, c.nom as client_nom, c.prenom as client_prenom
                FROM adresses a
                LEFT JOIN clients c ON a.id_client = c.id_client
                WHERE a.id_client = ?
                ORDER BY a.date_creation DESC 
                LIMIT 1
            ");
        }
        
        $stmt->execute([$client_id]);
        $address = $stmt->fetch();
        
        if ($address) {
            // Formater selon la structure attendue
            $formatted = [
                'nom' => $address['nom'] ?? $address['client_nom'] ?? '',
                'prenom' => $address['prenom'] ?? $address['client_prenom'] ?? '',
                'email' => $address['email'] ?? '',
                'telephone' => $address['telephone'] ?? '',
                'societe' => $address['societe'] ?? '',
                'adresse' => $address['adresse'] ?? '',
                'complement' => $address['complement'] ?? '',
                'code_postal' => $address['code_postal'] ?? '',
                'ville' => $address['ville'] ?? '',
                'pays' => $address['pays'] ?? 'France'
            ];
            
            error_log("DEBUG getAddressFromDB: Adresse trouvée: " . json_encode($formatted));
            return $formatted;
        }
        
        error_log("DEBUG getAddressFromDB: Aucune adresse trouvée pour client $client_id");
        return null;
        
    } catch (PDOException $e) {
        error_log("Erreur getAddressFromDB: " . $e->getMessage());
        return null;
    }
}

/**
 * Calcule les frais de livraison
 */
function calculateFraisLivraison($mode_livraison, $sous_total = 0) {
    // Récupérer la configuration du site
    $seuil_livraison_gratuite = 50.00; // Valeur par défaut
    
    $db = getDB();
    if ($db && tableExists('configuration')) {
        try {
            $stmt = $db->prepare("
                SELECT valeur FROM configuration 
                WHERE cle = 'seuil_livraison_gratuite'
            ");
            $stmt->execute();
            $config = $stmt->fetch();
            if ($config) {
                $seuil_livraison_gratuite = floatval($config['valeur']);
            }
        } catch (PDOException $e) {
            error_log("Erreur récupération configuration livraison: " . $e->getMessage());
        }
    }
    
    // Livraison gratuite si sous-total >= seuil et mode standard
    if ($mode_livraison === 'standard' && $sous_total >= $seuil_livraison_gratuite) {
        return 0.00;
    }
    
    switch ($mode_livraison) {
        case 'express':
            return 9.90;
        case 'relais':
            return 4.90;
        case 'standard':
        default:
            // Gratuit par défaut pour standard (seuil géré au-dessus)
            return $sous_total < $seuil_livraison_gratuite ? 4.90 : 0.00;
    }
}

// ============================================
// TRAITEMENT DES REQUÊTES
// ============================================

// API endpoint pour récupérer l'adresse existante
if (isset($_GET['api']) && $_GET['api'] == '1') {
    header('Content-Type: application/json');
    
    $response = [
        'success' => false, 
        'hasAddress' => false, 
        'adresse' => null,
        'debug' => []
    ];
    
    // D'abord essayer la session
    if (isset($_SESSION['adresse_livraison'])) {
        $response['success'] = true;
        $response['hasAddress'] = true;
        $response['adresse'] = $_SESSION['adresse_livraison'];
        $response['debug']['source'] = 'session';
    } else {
        // Sinon essayer la BDD
        $address = getAddressFromDB();
        if ($address) {
            $response['success'] = true;
            $response['hasAddress'] = true;
            $response['adresse'] = $address;
            $response['debug']['source'] = 'database';
        } else {
            $response['debug']['source'] = 'none';
        }
    }
    
    // Ajouter les frais de livraison configurés
    $sous_total = $_SESSION['panier']['total'] ?? 0;
    $response['frais_livraison'] = [
        'standard' => calculateFraisLivraison('standard', $sous_total),
        'express' => calculateFraisLivraison('express', $sous_total),
        'relais' => calculateFraisLivraison('relais', $sous_total)
    ];
    
    // Debug info
    $response['debug']['session_id'] = session_id();
    $response['debug']['client_id'] = getClientId();
    $response['debug']['panier'] = $_SESSION['panier'] ?? [];
    
    echo json_encode($response);
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier si c'est une requête API
    $is_api_request = false;
    if (isset($_SERVER['HTTP_X_API_MODE']) || isset($_POST['api_mode'])) {
        $is_api_request = true;
    }
    
    // Vérifier si c'est JSON
    $input = file_get_contents('php://input');
    $json_data = json_decode($input, true);
    if ($json_data) {
        $_POST = array_merge($_POST, $json_data);
        $is_api_request = true;
    }
    
    error_log("DEBUG: Méthode POST, API mode: " . ($is_api_request ? 'oui' : 'non'));
    error_log("DEBUG: Données POST: " . json_encode($_POST));
    
    // Validation
    $errors = [];
    $donnees_saisies = [];
    
    $required_fields = ['nom', 'prenom', 'email', 'telephone', 'adresse', 'code_postal', 'ville'];
    foreach ($required_fields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[] = "Le champ '$field' est requis";
        } else {
            $donnees_saisies[$field] = htmlspecialchars(trim($_POST[$field]));
        }
    }
    
    // Validation email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide";
    }
    
    // Validation téléphone
    if (!empty($_POST['telephone'])) {
        $phone_cleaned = preg_replace('/\s+/', '', $_POST['telephone']);
        if (!preg_match('/^[0-9]{10}$/', $phone_cleaned)) {
            $errors[] = "Téléphone doit contenir 10 chiffres";
        }
        $donnees_saisies['telephone'] = $phone_cleaned;
    }
    
    // Validation code postal
    if (!empty($_POST['code_postal'])) {
        $cp_cleaned = preg_replace('/\s+/', '', $_POST['code_postal']);
        if (!preg_match('/^[0-9]{5}$/', $cp_cleaned)) {
            $errors[] = "Code postal doit contenir 5 chiffres";
        }
        $donnees_saisies['code_postal'] = $cp_cleaned;
    }
    
    // Champs optionnels
    $optional_fields = ['societe', 'complement', 'pays', 'instructions'];
    foreach ($optional_fields as $field) {
        $donnees_saisies[$field] = htmlspecialchars(trim($_POST[$field] ?? ''));
    }
    
    // Mode livraison et emballage
    $mode_livraison = $_POST['mode_livraison'] ?? 'standard';
    $emballage_cadeau = isset($_POST['emballage_cadeau']) && $_POST['emballage_cadeau'] == '1' ? true : false;
    
    error_log("DEBUG: Erreurs validation: " . json_encode($errors));
    
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
        
        // Sauvegarder en BDD
        $addressData = [
            'nom' => $donnees_saisies['nom'],
            'prenom' => $donnees_saisies['prenom'],
            'email' => $donnees_saisies['email'],
            'telephone' => $donnees_saisies['telephone'],
            'societe' => $donnees_saisies['societe'],
            'adresse' => $donnees_saisies['adresse'],
            'complement' => $donnees_saisies['complement'],
            'code_postal' => $donnees_saisies['code_postal'],
            'ville' => $donnees_saisies['ville'],
            'pays' => $donnees_saisies['pays'] ?? 'France'
        ];
        
        error_log("DEBUG: Tentative sauvegarde BDD avec données: " . json_encode($addressData));
        
        $address_id = saveAddressToDB($addressData);
        
        if ($address_id) {
            // Calculer les frais de livraison
            $sous_total = $_SESSION['panier']['total'] ?? 0;
            $frais_livraison = calculateFraisLivraison($mode_livraison, $sous_total);
            
            // Sauvegarder en session
            $adresse_livraison = array_merge($addressData, [
                'mode_livraison' => $mode_livraison,
                'emballage_cadeau' => $emballage_cadeau,
                'instructions' => $donnees_saisies['instructions']
            ]);
            
            $_SESSION['adresse_livraison'] = $adresse_livraison;
            $_SESSION['adresse_livraison_id'] = $address_id;
            $_SESSION['mode_livraison'] = $mode_livraison;
            $_SESSION['emballage_cadeau'] = $emballage_cadeau;
            $_SESSION['email'] = $donnees_saisies['email'];
            
            // Sauvegarder dans la structure commande pour l'API
            $_SESSION['commande'] = [
                'adresse_livraison' => $adresse_livraison,
                'livraison' => [
                    'mode' => $mode_livraison,
                    'frais' => $frais_livraison,
                    'date_estimee' => date('Y-m-d', strtotime('+' . ($mode_livraison === 'express' ? '1' : '3') . ' days'))
                ],
                'emballage_cadeau' => $emballage_cadeau,
                'frais_emballage' => $emballage_cadeau ? 3.90 : 0,
                'instructions' => $donnees_saisies['instructions'],
                'date_sauvegarde' => date('Y-m-d H:i:s')
            ];
            
            // Si client temporaire, l'ajouter à la session commande
            if (isset($_SESSION['client_temp'])) {
                $_SESSION['commande']['client_id'] = $_SESSION['client_temp']['id'];
            }
            
            $_SESSION['frais_livraison'] = $frais_livraison;
            
            error_log("DEBUG: Succès sauvegarde adresse, ID: $address_id");
            
            echo json_encode([
                'success' => true,
                'message' => 'Adresse enregistrée',
                'address_id' => $address_id,
                'frais_livraison' => $frais_livraison,
                'redirect' => 'paiement.php',
                'debug' => [
                    'client_id' => getClientId(),
                    'session_id' => session_id()
                ]
            ]);
        } else {
            error_log("DEBUG: Échec saveAddressToDB, retourne false");
            
            echo json_encode([
                'success' => false, 
                'message' => 'Erreur enregistrement BDD',
                'debug' => [
                    'client_id' => getClientId(),
                    'session_id' => session_id(),
                    'error' => 'saveAddressToDB a retourné false'
                ]
            ]);
        }
        exit();
    }
    
    // Mode formulaire traditionnel
    if (!empty($errors)) {
        $_SESSION['erreurs_livraison'] = $errors;
        $_SESSION['donnees_saisies'] = $_POST;
        header('Location: livraison.php');
        exit();
    }
    
    // Sauvegarder en BDD
    $addressData = [
        'nom' => $donnees_saisies['nom'],
        'prenom' => $donnees_saisies['prenom'],
        'email' => $donnees_saisies['email'],
        'telephone' => $donnees_saisies['telephone'],
        'societe' => $donnees_saisies['societe'],
        'adresse' => $donnees_saisies['adresse'],
        'complement' => $donnees_saisies['complement'],
        'code_postal' => $donnees_saisies['code_postal'],
        'ville' => $donnees_saisies['ville'],
        'pays' => $donnees_saisies['pays'] ?? 'France'
    ];
    
    $address_id = saveAddressToDB($addressData);
    
    if ($address_id) {
        // Calculer les frais de livraison
        $sous_total = $_SESSION['panier']['total'] ?? 0;
        $frais_livraison = calculateFraisLivraison($mode_livraison, $sous_total);
        
        // Sauvegarder en session
        $adresse_livraison = array_merge($addressData, [
            'mode_livraison' => $mode_livraison,
            'emballage_cadeau' => $emballage_cadeau,
            'instructions' => $donnees_saisies['instructions']
        ]);
        
        $_SESSION['adresse_livraison'] = $adresse_livraison;
        $_SESSION['adresse_livraison_id'] = $address_id;
        $_SESSION['mode_livraison'] = $mode_livraison;
        $_SESSION['emballage_cadeau'] = $emballage_cadeau;
        $_SESSION['email'] = $donnees_saisies['email'];
        
        // Sauvegarder dans la structure commande
        $_SESSION['commande'] = [
            'adresse_livraison' => $adresse_livraison,
            'livraison' => [
                'mode' => $mode_livraison,
                'frais' => $frais_livraison,
                'date_estimee' => date('Y-m-d', strtotime('+' . ($mode_livraison === 'express' ? '1' : '3') . ' days'))
            ],
            'emballage_cadeau' => $emballage_cadeau,
            'frais_emballage' => $emballage_cadeau ? 3.90 : 0,
            'instructions' => $donnees_saisies['instructions'],
            'date_sauvegarde' => date('Y-m-d H:i:s')
        ];
        
        $_SESSION['frais_livraison'] = $frais_livraison;
        
        // Redirection
        header('Location: paiement.php');
        exit();
    } else {
        $_SESSION['erreurs_livraison'] = ['Erreur lors de l\'enregistrement dans la base de données'];
        $_SESSION['donnees_saisies'] = $_POST;
        header('Location: livraison.php');
        exit();
    }
}

// ============================================
// AFFICHAGE DU FORMULAIRE
// ============================================

// Récupérer les données pour pré-remplissage
$donnees_saisies = $_SESSION['donnees_saisies'] ?? [];

if (empty($donnees_saisies)) {
    if (isset($_SESSION['adresse_livraison'])) {
        $donnees_saisies = $_SESSION['adresse_livraison'];
    } else {
        $address = getAddressFromDB();
        if ($address) {
            $donnees_saisies = $address;
        }
    }
}

// Nettoyer les données de session utilisées
if (isset($_SESSION['donnees_saisies'])) {
    unset($_SESSION['donnees_saisies']);
}

// Récupérer le mode de livraison par défaut
$mode_livraison_default = $donnees_saisies['mode_livraison'] ?? 'standard';
$emballage_cadeau_default = $donnees_saisies['emballage_cadeau'] ?? false;

// Calculer les frais de livraison par défaut
$sous_total = $_SESSION['panier']['total'] ?? 0;
$frais_livraison_default = calculateFraisLivraison($mode_livraison_default, $sous_total);

// Déterminer le prix affiché pour la livraison standard
$prix_standard_affichage = ($sous_total >= 50) ? 'Gratuite' : '4,90 €';

// HTML du formulaire
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adresse de Livraison - HEURE DU CADEAU</title>
    <style>
        /* VOTRE CSS EXISTANT RESTE IDENTIQUE */
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
            display: flex;
            align-items: center;
            gap: 10px;
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
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
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
            background: rgba(90, 103, 216, 0.05);
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
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            width: auto;
            margin-top: 4px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        button:hover {
            background-color: #4c51bf;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
        }
        
        button:disabled {
            background-color: #cbd5e0;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #5a67d8;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
            
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 24px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
                margin: 10px auto;
            }
            
            .container {
                padding: 15px;
            }
            
            h1 {
                font-size: 20px;
            }
            
            button {
                padding: 12px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-truck"></i> Adresse de Livraison</h1>
        
        <!-- Messages d'information -->
        <div id="info-message"></div>
        
        <!-- Messages d'erreur depuis session PHP -->
        <?php if (isset($_SESSION['erreurs_livraison'])): ?>
        <div class="message error">
            <strong><i class="fas fa-exclamation-triangle"></i> Erreurs :</strong>
            <ul>
                <?php foreach ($_SESSION['erreurs_livraison'] as $erreur): ?>
                <li><?php echo htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php 
        unset($_SESSION['erreurs_livraison']);
        endif; ?>
        
        <form action="livraison.php" method="POST" id="livraison-form">
            <!-- Champ pour détecter le mode API -->
            <input type="hidden" name="api_mode" value="1" />
            
            <h2>Informations personnelles</h2>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="prenom" class="required">Prénom</label>
                    <input type="text" id="prenom" name="prenom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required 
                           autocomplete="given-name" />
                    <div class="error-message" id="error-prenom"></div>
                </div>
                <div class="form-group">
                    <label for="nom" class="required">Nom</label>
                    <input type="text" id="nom" name="nom" 
                           value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required 
                           autocomplete="family-name" />
                    <div class="error-message" id="error-nom"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email" class="required">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       required 
                       autocomplete="email" />
                <div class="error-message" id="error-email"></div>
                <div class="shipping-info">
                    <i class="fas fa-info-circle"></i>
                    Votre confirmation de commande sera envoyée à cette adresse
                </div>
            </div>
            
            <div class="form-group">
                <label for="telephone" class="required">Téléphone</label>
                <input type="tel" id="telephone" name="telephone" 
                       value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       required 
                       autocomplete="tel" 
                       pattern="[0-9]{10}" 
                       placeholder="0123456789" />
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
                       autocomplete="organization" />
            </div>
            
            <h2>Adresse de livraison</h2>
            
            <div class="form-group">
                <label for="adresse" class="required">Adresse</label>
                <textarea id="adresse" name="adresse" rows="3" required autocomplete="street-address"><?php echo htmlspecialchars($donnees_saisies['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="error-message" id="error-adresse"></div>
            </div>
            
            <div class="form-group">
                <label for="complement">Complément d'adresse (appartement, étage, etc.)</label>
                <input type="text" id="complement" name="complement" 
                       value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                       autocomplete="address-line2" />
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="code_postal" class="required">Code postal</label>
                    <input type="text" id="code_postal" name="code_postal" 
                           value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required 
                           autocomplete="postal-code" 
                           pattern="[0-9]{5}" 
                           placeholder="75000" />
                    <div class="error-message" id="error-code_postal"></div>
                </div>
                <div class="form-group">
                    <label for="ville" class="required">Ville</label>
                    <input type="text" id="ville" name="ville" 
                           value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           required 
                           autocomplete="address-level2" />
                    <div class="error-message" id="error-ville"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="pays" class="required">Pays</label>
                <select id="pays" name="pays" required autocomplete="country">
                    <option value="France" <?php echo (($donnees_saisies['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                    <option value="Belgique" <?php echo (($donnees_saisies['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                    <option value="Suisse" <?php echo (($donnees_saisies['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                    <option value="Luxembourg" <?php echo (($donnees_saisies['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                    <option value="autre" <?php echo (($donnees_saisies['pays'] ?? '') === 'autre') ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            
            <h2>Options de livraison</h2>
            
            <div class="radio-group" id="livraisonOptions">
                <div class="radio-option <?php echo $mode_livraison_default === 'standard' ? 'selected' : ''; ?>" data-value="standard">
                    <input type="radio" name="mode_livraison" value="standard" 
                           <?php echo $mode_livraison_default === 'standard' ? 'checked' : ''; ?> hidden />
                    <div class="radio-details">
                        <strong>Livraison Standard</strong>
                        <p>Livraison en 3-5 jours ouvrés</p>
                        <?php if ($sous_total >= 50): ?>
                        <p style="color: #38a169; font-size: 12px; margin-top: 5px;">
                            <i class="fas fa-check-circle"></i> Gratuite (commande ≥ 50€)
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="radio-price">
                        <?php echo $prix_standard_affichage; ?>
                    </div>
                </div>
                
                <div class="radio-option <?php echo $mode_livraison_default === 'express' ? 'selected' : ''; ?>" data-value="express">
                    <input type="radio" name="mode_livraison" value="express" 
                           <?php echo $mode_livraison_default === 'express' ? 'checked' : ''; ?> hidden />
                    <div class="radio-details">
                        <strong>Livraison Express</strong>
                        <p>Livraison en 24h (hors week-end)</p>
                    </div>
                    <div class="radio-price">9,90 €</div>
                </div>
                
                <div class="radio-option <?php echo $mode_livraison_default === 'relais' ? 'selected' : ''; ?>" data-value="relais">
                    <input type="radio" name="mode_livraison" value="relais" 
                           <?php echo $mode_livraison_default === 'relais' ? 'checked' : ''; ?> hidden />
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
                       <?php echo $emballage_cadeau_default ? 'checked' : ''; ?> />
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
                <span id="btn-text"><i class="fas fa-arrow-right"></i> Continuer vers le paiement</span>
                <span id="btn-loading" style="display: none;"><div class="loading"></div> Traitement...</span>
            </button>
            
            <div style="text-align: center; margin-top: 20px; color: #718096; font-size: 14px">
                <i class="fas fa-lock"></i> Vos données sont protégées et ne seront pas partagées avec des tiers
            </div>
        </form>
    </div>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            // Variables d'état
            let isLoading = false;
            let fraisLivraison = <?php echo json_encode($frais_livraison_default); ?>;
            let sousTotal = <?php echo json_encode($sous_total); ?>;
            
            // Charger l'adresse existante
            function loadExistingAddress() {
                fetch("livraison.php?api=1")
                    .then(response => response.json())
                    .then(data => {
                        console.log("API Response:", data);
                        if (data.success && data.hasAddress && data.adresse) {
                            displayExistingAddress(data.adresse);
                        }
                        // Afficher les infos de debug si présentes
                        if (data.debug) {
                            console.log('Debug API:', data.debug);
                        }
                    })
                    .catch(error => {
                        console.log("API non disponible:", error);
                    });
            }
            
            // Afficher l'adresse existante
            function displayExistingAddress(address) {
                const messageDiv = document.getElementById('info-message');
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
                
                // Pré-sélectionner le mode de livraison
                if (address.mode_livraison) {
                    selectDeliveryOption(address.mode_livraison);
                }
                
                // Cocher l'emballage cadeau
                if (address.emballage_cadeau) {
                    document.getElementById('emballage_cadeau').checked = true;
                }
            }
            
            // Sélectionner une option de livraison
            function selectDeliveryOption(value) {
                document.querySelectorAll('.radio-option').forEach(option => {
                    option.classList.remove('selected');
                    const radio = option.querySelector('input[type="radio"]');
                    if (radio) {
                        radio.checked = option.getAttribute('data-value') === value;
                        if (radio.checked) {
                            option.classList.add('selected');
                        }
                    }
                });
            }
            
            // Gestion des options de livraison
            document.querySelectorAll('.radio-option').forEach(option => {
                option.addEventListener('click', function() {
                    selectDeliveryOption(this.getAttribute('data-value'));
                });
            });
            
            // Validation des champs en temps réel
            function validateField(fieldId, errorId) {
                const field = document.getElementById(fieldId);
                const error = document.getElementById(errorId);
                
                if (!field.value.trim()) {
                    field.classList.add('error-field');
                    error.textContent = 'Ce champ est requis';
                    error.classList.add('show');
                    return false;
                } else {
                    field.classList.remove('error-field');
                    error.classList.remove('show');
                    
                    // Validation spécifique
                    if (fieldId === 'email') {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(field.value)) {
                            field.classList.add('error-field');
                            error.textContent = 'Veuillez entrer une adresse email valide';
                            error.classList.add('show');
                            return false;
                        }
                    }
                    
                    if (fieldId === 'telephone') {
                        const phoneRegex = /^[0-9]{10}$/;
                        const cleanedPhone = field.value.replace(/\s/g, '');
                        if (!phoneRegex.test(cleanedPhone)) {
                            field.classList.add('error-field');
                            error.textContent = 'Veuillez entrer un numéro de téléphone valide (10 chiffres)';
                            error.classList.add('show');
                            return false;
                        }
                    }
                    
                    if (fieldId === 'code_postal') {
                        const cpRegex = /^[0-9]{5}$/;
                        if (!cpRegex.test(field.value)) {
                            field.classList.add('error-field');
                            error.textContent = 'Veuillez entrer un code postal valide (5 chiffres)';
                            error.classList.add('show');
                            return false;
                        }
                    }
                    
                    return true;
                }
            }
            
            // Validation complète du formulaire
            function validateForm() {
                const fields = [
                    { id: 'nom', error: 'error-nom' },
                    { id: 'prenom', error: 'error-prenom' },
                    { id: 'adresse', error: 'error-adresse' },
                    { id: 'code_postal', error: 'error-code_postal' },
                    { id: 'ville', error: 'error-ville' },
                    { id: 'email', error: 'error-email' },
                    { id: 'telephone', error: 'error-telephone' }
                ];
                
                let isValid = true;
                
                fields.forEach(field => {
                    if (!validateField(field.id, field.error)) {
                        isValid = false;
                    }
                });
                
                return isValid;
            }
            
            // Vérifier si une page existe
            async function checkPageExists(url) {
                try {
                    const response = await fetch(url, { method: 'HEAD' });
                    return response.ok;
                } catch {
                    return false;
                }
            }
            
            // Soumission du formulaire
            document.getElementById('livraison-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                if (isLoading) return;
                
                if (!validateForm()) {
                    const firstError = document.querySelector('.error-field');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return;
                }
                
                isLoading = true;
                const submitBtn = document.getElementById('submit-btn');
                const btnText = document.getElementById('btn-text');
                const btnLoading = document.getElementById('btn-loading');
                
                btnText.style.display = 'none';
                btnLoading.style.display = 'inline-flex';
                submitBtn.disabled = true;
                
                // Préparer les données
                const formData = new FormData(this);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                
                // Ajouter l'emballage cadeau
                data['emballage_cadeau'] = document.getElementById('emballage_cadeau').checked ? '1' : '0';
                
                console.log("Données envoyées:", data);
                
                try {
                    // Envoyer à l'API
                    const response = await fetch('livraison.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-API-Mode': '1'
                        },
                        body: JSON.stringify(data)
                    });
                    
                    const result = await response.json();
                    console.log("Réponse API:", result);
                    
                    if (result.success) {
                        // Redirection
                        if (result.redirect) {
                            window.location.href = result.redirect;
                        } else if (await checkPageExists('paiement.php')) {
                            window.location.href = 'paiement.php';
                        } else {
                            window.location.href = 'paiement.html';
                        }
                    } else {
                        // Afficher les erreurs
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
                            
                            const firstMissingField = document.getElementById(result.missing[0]);
                            if (firstMissingField) {
                                firstMissingField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                        }
                        
                        const messageDiv = document.getElementById('info-message');
                        messageDiv.className = 'message error';
                        messageDiv.innerHTML = `
                            <strong><i class="fas fa-exclamation-triangle"></i> Erreur :</strong><br>
                            ${result.message || 'Une erreur est survenue'}<br>
                            ${result.debug ? '<small>Debug: ' + JSON.stringify(result.debug) + '</small>' : ''}
                        `;
                        
                        // Réinitialiser le bouton
                        isLoading = false;
                        btnText.style.display = 'inline-flex';
                        btnLoading.style.display = 'none';
                        submitBtn.disabled = false;
                        
                        // Scroll to error
                        messageDiv.scrollIntoView({ behavior: 'smooth' });
                    }
                } catch (error) {
                    console.error('Erreur API:', error);
                    
                    const messageDiv = document.getElementById('info-message');
                    messageDiv.className = 'message error';
                    messageDiv.innerHTML = `
                        <strong><i class="fas fa-exclamation-triangle"></i> Erreur réseau :</strong><br>
                        Impossible de se connecter au serveur. Veuillez réessayer.<br>
                        <small>${error.message}</small>
                    `;
                    
                    // Fallback: soumission traditionnelle après 2 secondes
                    setTimeout(() => {
                        console.log("Fallback: soumission traditionnelle");
                        this.submit();
                    }, 2000);
                }
            });
            
            // Écouteurs pour la validation en temps réel
            const fieldsToValidate = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'email', 'telephone'];
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
                        error.classList.remove('show');
                    });
                }
            });
            
            // Charger l'adresse existante au démarrage
            loadExistingAddress();
        });
    </script>
</body>
</html>