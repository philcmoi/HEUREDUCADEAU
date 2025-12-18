<?php
// api/clients.php - API DÉDIÉE À LA GESTION DES CLIENTS
session_start();

// Headers CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

// Gérer OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Connexion BDD
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=heureducadeau;charset=utf8",
                "Philippe",
                "l@99339R",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur BDD clients: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur de connexion à la base de données',
                'code' => 'DB_CONNECTION_ERROR'
            ]);
            exit();
        }
    }
    
    return $pdo;
}

// DÉTECTION DU TYPE DE DONNÉES REÇUES
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = strpos($contentType, 'application/json') !== false;

// Initialiser $data
$data = [];

// Lire les données selon le type
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isJson) {
        // Données JSON
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $data = [];
        }
    } else {
        // Données FormData (x-www-form-urlencoded ou multipart/form-data)
        $data = $_POST;
        
        // Si pas de données POST, essayer de lire le contenu brut
        if (empty($data)) {
            $input = file_get_contents('php://input');
            parse_str($input, $parsedData);
            if (!empty($parsedData)) {
                $data = $parsedData;
            }
        }
        
        // Si on reçoit des données JSON malgré tout (cas mixte)
        if (empty($data) && !empty($input = file_get_contents('php://input'))) {
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $data = [];
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Données GET
    $data = $_GET;
}

// Déterminer l'action
$action = $_GET['action'] ?? $data['action'] ?? '';

// ====================================================
// FONCTION CORRIGÉE POUR GÉRER LES DONNÉES FORMDATA
// ====================================================

/**
 * Trouve ou crée un client temporaire (version corrigée pour FormData)
 */
function getOrCreateTempClient($data) {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    try {
        // Extraire les données (format FormData ou JSON)
        $email = trim($data['email'] ?? '');
        $nom = trim($data['nom'] ?? '');
        $prenom = trim($data['prenom'] ?? '');
        $telephone = trim($data['telephone'] ?? '');
        
        // DEBUG: Log des données reçues
        error_log("DEBUG getOrCreateTempClient - Données reçues: " . print_r($data, true));
        error_log("DEBUG - Email: $email, Nom: $nom, Prénom: $prenom, Téléphone: $telephone");
        
        // Validation de l'email
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("DEBUG - Email invalide ou vide: $email");
            return false;
        }
        
        // Vérifier si le client existe déjà
        $sql = "SELECT id_client, is_temporary, statut, nom, prenom, email, telephone 
                FROM clients 
                WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client) {
            // Client existant - mettre à jour si nécessaire
            $needsUpdate = false;
            $updateFields = [];
            $updateValues = [];
            
            if (!empty($nom) && (empty($client['nom']) || $client['nom'] !== $nom)) {
                $updateFields[] = 'nom = ?';
                $updateValues[] = $nom;
                $needsUpdate = true;
            }
            
            if (!empty($prenom) && (empty($client['prenom']) || $client['prenom'] !== $prenom)) {
                $updateFields[] = 'prenom = ?';
                $updateValues[] = $prenom;
                $needsUpdate = true;
            }
            
            if (!empty($telephone) && (empty($client['telephone']) || $client['telephone'] !== $telephone)) {
                $updateFields[] = 'telephone = ?';
                $updateValues[] = $telephone;
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $updateFields[] = 'updated_at = NOW()';
                $sqlUpdate = "UPDATE clients SET " . implode(', ', $updateFields) . " WHERE id_client = ?";
                $updateValues[] = $client['id_client'];
                
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute($updateValues);
                
                error_log("DEBUG - Client mis à jour: " . $client['id_client']);
            }
            
            return [
                'id' => $client['id_client'],
                'is_temporary' => $client['is_temporary'],
                'action' => 'existing',
                'nom' => !empty($nom) ? $nom : ($client['nom'] ?? ''),
                'prenom' => !empty($prenom) ? $prenom : ($client['prenom'] ?? ''),
                'email' => $email,
                'telephone' => !empty($telephone) ? $telephone : ($client['telephone'] ?? '')
            ];
        } else {
            // Créer un nouveau client temporaire
            $sqlInsert = "INSERT INTO clients (
                email, nom, prenom, telephone, 
                is_temporary, created_from_session, 
                statut, date_inscription, newsletter, created_at
            ) VALUES (?, ?, ?, ?, 1, ?, 'actif', NOW(), 0, NOW())";
            
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $email,
                $nom,
                $prenom,
                $telephone,
                session_id()
            ]);
            
            $clientId = $pdo->lastInsertId();
            
            // Créer une entrée dans le log (si la table existe)
            try {
                $sqlLog = "INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, created_at)
                          VALUES ('info', 'info', 'Client temporaire créé', ?, ?, NOW())";
                $stmtLog = $pdo->prepare($sqlLog);
                $stmtLog->execute([$clientId, $_SERVER['REMOTE_ADDR'] ?? '']);
            } catch (Exception $e) {
                // Ignorer les erreurs de log (table logs peut ne pas exister)
                error_log("NOTE: Impossible d'écrire dans logs: " . $e->getMessage());
            }
            
            error_log("DEBUG - Nouveau client créé: ID $clientId");
            
            return [
                'id' => $clientId,
                'is_temporary' => 1,
                'action' => 'created',
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone
            ];
        }
    } catch (PDOException $e) {
        error_log("ERREUR getOrCreateTempClient: " . $e->getMessage());
        error_log("ERREUR SQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Convertir un client temporaire en permanent
 */
function convertTempToPermanent($tempClientId, $permanentData) {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // 1. Vérifier si le mot de passe est valide
        if (strlen($permanentData['password']) < 6) {
            throw new Exception("Mot de passe trop court (minimum 6 caractères)");
        }
        
        // 2. Mettre à jour le client
        $sql = "UPDATE clients SET
                mot_de_passe = ?,
                is_temporary = 0,
                statut = 'actif',
                newsletter = ?,
                date_naissance = ?,
                genre = ?,
                updated_at = NOW()
                WHERE id_client = ? AND is_temporary = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            password_hash($permanentData['password'], PASSWORD_DEFAULT),
            $permanentData['newsletter'] ?? 0,
            $permanentData['date_naissance'] ?? null,
            $permanentData['genre'] ?? null,
            $tempClientId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Client non trouvé ou déjà permanent");
        }
        
        // 3. Enregistrer la conversion
        try {
            $sqlConversion = "INSERT INTO conversions_temp (
                id_client_temp, id_client_permanent, 
                methode_conversion, source_page, session_id, created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmtConv = $pdo->prepare($sqlConversion);
            $stmtConv->execute([
                $tempClientId,
                $tempClientId,
                $permanentData['methode'] ?? 'post_commande',
                $permanentData['source'] ?? 'confirmation',
                session_id()
            ]);
        } catch (Exception $e) {
            // Table conversions_temp peut ne pas exister
            error_log("NOTE: Table conversions_temp non trouvée: " . $e->getMessage());
        }
        
        // 4. Log
        try {
            $sqlLog = "INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, created_at)
                      VALUES ('info', 'info', 'Client converti en permanent', ?, ?, NOW())";
            $stmtLog = $pdo->prepare($sqlLog);
            $stmtLog->execute([$tempClientId, $_SERVER['REMOTE_ADDR'] ?? '']);
        } catch (Exception $e) {
            // Ignorer erreur de log
            error_log("NOTE: Erreur log conversion: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur convertTempToPermanent: " . $e->getMessage());
        return false;
    }
}

// ====================================================
// ENDPOINTS API - VERSION CORRIGÉE
// ====================================================

// ACTION: TROUVER OU CRÉER CLIENT TEMPORAIRE
if ($action === 'find_or_create') {
    // DEBUG: Log des données reçues
    error_log("=== ACTION find_or_create ===");
    error_log("Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("Content-Type: $contentType");
    error_log("Données reçues: " . print_r($data, true));
    error_log("Session ID: " . session_id());
    
    // Vérifier si des données ont été reçues
    if (empty($data)) {
        error_log("ERREUR: Aucune donnée reçue");
        echo json_encode([
            'success' => false,
            'message' => 'Aucune donnée reçue',
            'code' => 'NO_DATA',
            'debug' => [
                'method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $contentType,
                'session_id' => session_id()
            ]
        ]);
        exit;
    }
    
    // Vérifier les champs requis - version plus flexible
    $email = trim($data['email'] ?? '');
    
    if (empty($email)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email manquant',
            'code' => 'MISSING_EMAIL',
            'debug' => ['data_received' => $data]
        ]);
        exit;
    }
    
    // Validation email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email invalide',
            'code' => 'INVALID_EMAIL',
            'debug' => ['email' => $email]
        ]);
        exit;
    }
    
    // Protection anti-abus (avec gestion d'erreur)
    try {
        $pdo = getPDOConnection();
        $sqlCheck = "SELECT COUNT(*) as count FROM clients 
                     WHERE created_from_session = ? 
                     AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = $pdo->prepare($sqlCheck);
        $stmt->execute([session_id()]);
        $result = $stmt->fetch();
        
        if ($result && $result['count'] > 10) {
            echo json_encode([
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer plus tard.',
                'code' => 'RATE_LIMIT'
            ]);
            exit;
        }
    } catch (Exception $e) {
        // Continuer même si la vérification échoue
        error_log("NOTE: Rate limit check failed: " . $e->getMessage());
    }
    
    // Trouver ou créer le client
    $clientResult = getOrCreateTempClient($data);
    
    if (!$clientResult) {
        // Essayer une version simplifiée si la première échoue
        $simpleData = ['email' => $email];
        if (!empty($data['nom'])) $simpleData['nom'] = $data['nom'];
        if (!empty($data['prenom'])) $simpleData['prenom'] = $data['prenom'];
        if (!empty($data['telephone'])) $simpleData['telephone'] = $data['telephone'];
        
        $clientResult = getOrCreateTempClient($simpleData);
        
        if (!$clientResult) {
            error_log("ERREUR FINALE: Impossible de créer ou trouver le client");
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la gestion du client',
                'code' => 'SERVER_ERROR',
                'debug' => [
                    'email' => $email,
                    'session_id' => session_id(),
                    'data_format' => $isJson ? 'JSON' : 'FormData'
                ]
            ]);
            exit;
        }
    }
    
    // Sauvegarder dans la session
    $_SESSION['client_temp'] = [
        'id' => $clientResult['id'],
        'email' => $email,
        'is_temporary' => $clientResult['is_temporary'],
        'nom' => $clientResult['nom'],
        'prenom' => $clientResult['prenom'],
        'telephone' => $clientResult['telephone'] ?? '',
        'created_at' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'action' => $clientResult['action']
    ];
    
    error_log("SUCCÈS: Client géré - ID: " . $clientResult['id'] . ", Action: " . $clientResult['action']);
    
    echo json_encode([
        'success' => true,
        'client' => $clientResult,
        'session_id' => session_id(),
        'debug' => [
            'action' => $clientResult['action'],
            'is_temporary' => $clientResult['is_temporary'],
            'data_format' => $isJson ? 'JSON' : 'FormData'
        ]
    ]);
    exit;
}

// ACTION: CONVERTIR EN COMPTE PERMANENT
if ($action === 'convert_to_permanent') {
    // Vérifier si un client temporaire existe dans la session
    if (!isset($_SESSION['client_temp']['id']) || $_SESSION['client_temp']['is_temporary'] == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Aucun client temporaire à convertir',
            'code' => 'NO_TEMP_CLIENT'
        ]);
        exit;
    }
    
    $required = ['password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode([
                'success' => false,
                'message' => "Champ $field manquant",
                'code' => 'MISSING_FIELD'
            ]);
            exit;
        }
    }
    
    // Vérifier la correspondance des mots de passe
    if ($data['password'] !== $data['confirm_password']) {
        echo json_encode([
            'success' => false,
            'message' => 'Les mots de passe ne correspondent pas',
            'code' => 'PASSWORD_MISMATCH'
        ]);
        exit;
    }
    
    // Vérifier la force du mot de passe
    if (strlen($data['password']) < 6) {
        echo json_encode([
            'success' => false,
            'message' => 'Le mot de passe doit contenir au moins 6 caractères',
            'code' => 'WEAK_PASSWORD'
        ]);
        exit;
    }
    
    $tempClientId = $_SESSION['client_temp']['id'];
    
    // Préparer les données de conversion
    $conversionData = [
        'password' => $data['password'],
        'newsletter' => $data['newsletter'] ?? 1,
        'date_naissance' => $data['date_naissance'] ?? null,
        'genre' => $data['genre'] ?? null,
        'methode' => 'post_commande',
        'source' => $_SERVER['HTTP_REFERER'] ?? 'direct'
    ];
    
    // Convertir le client
    $result = convertTempToPermanent($tempClientId, $conversionData);
    
    if ($result) {
        // Mettre à jour la session
        $_SESSION['client_temp']['is_temporary'] = 0;
        $_SESSION['client_connecté'] = true;
        $_SESSION['client_id'] = $tempClientId;
        
        echo json_encode([
            'success' => true,
            'message' => 'Compte permanent créé avec succès',
            'client' => [
                'id' => $tempClientId,
                'email' => $_SESSION['client_temp']['email'],
                'nom' => $_SESSION['client_temp']['nom'],
                'prenom' => $_SESSION['client_temp']['prenom']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la conversion du compte',
            'code' => 'CONVERSION_ERROR'
        ]);
    }
    exit;
}

// ACTION: RÉCUPÉRER INFOS CLIENT
if ($action === 'get_info') {
    $clientId = $_SESSION['client_temp']['id'] ?? $data['client_id'] ?? null;
    
    if (!$clientId) {
        echo json_encode([
            'success' => false,
            'message' => 'ID client manquant',
            'code' => 'MISSING_ID'
        ]);
        exit;
    }
    
    try {
        $pdo = getPDOConnection();
        $sql = "SELECT id_client, email, nom, prenom, telephone, 
                       is_temporary, date_inscription, statut, created_at
                FROM clients 
                WHERE id_client = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        
        if (!$client) {
            echo json_encode([
                'success' => false,
                'message' => 'Client non trouvé',
                'code' => 'CLIENT_NOT_FOUND'
            ]);
            exit;
        }
        
        // Récupérer les commandes du client (si la table existe)
        $commandes = [];
        try {
            $sqlCommandes = "SELECT numero_commande, date_commande, total_ttc, statut
                             FROM commandes 
                             WHERE id_client = ? 
                             ORDER BY date_commande DESC 
                             LIMIT 5";
            
            $stmtCmd = $pdo->prepare($sqlCommandes);
            $stmtCmd->execute([$clientId]);
            $commandes = $stmtCmd->fetchAll();
        } catch (Exception $e) {
            // Table commandes peut ne pas exister encore
            error_log("NOTE: Table commandes non accessible: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'client' => $client,
            'commandes' => $commandes,
            'commandes_count' => count($commandes)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de base de données: ' . $e->getMessage(),
            'code' => 'DB_ERROR'
        ]);
    }
    exit;
}

// ACTION: TEST CONNEXION SIMPLE
if ($action === 'test') {
    try {
        $pdo = getPDOConnection();
        
        // Tester une requête simple
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        
        // Vérifier si la table clients existe
        $tableExists = false;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'clients'");
            $tableExists = $stmt->rowCount() > 0;
        } catch (Exception $e) {
            // Ignorer l'erreur
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'API clients fonctionnelle',
            'status' => [
                'database' => 'connecté',
                'table_clients_exists' => $tableExists,
                'session_id' => session_id(),
                'php_version' => PHP_VERSION,
                'timestamp' => date('Y-m-d H:i:s'),
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'content_type' => $contentType
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur test: ' . $e->getMessage(),
            'status' => [
                'database' => 'non connecté',
                'error' => $e->getMessage(),
                'session_id' => session_id()
            ]
        ]);
    }
    exit;
}

// ACTION: DEBUG - Afficher les données reçues
if ($action === 'debug') {
    echo json_encode([
        'success' => true,
        'debug_info' => [
            'session_id' => session_id(),
            'session_data' => $_SESSION,
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $contentType,
            'get_params' => $_GET,
            'post_data' => $_POST,
            'raw_input' => file_get_contents('php://input'),
            'parsed_data' => $data,
            'is_json' => $isJson,
            'action_requested' => $action
        ]
    ]);
    exit;
}

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non reconnue',
    'received_action' => $action,
    'available_actions' => [
        'find_or_create' => 'Trouver ou créer client temporaire',
        'convert_to_permanent' => 'Convertir en compte permanent',
        'get_info' => 'Récupérer infos client',
        'test' => 'Test de connexion',
        'debug' => 'Debug des données'
    ],
    'debug_info' => [
        'session_id' => session_id(),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'content_type' => $contentType,
        'has_data' => !empty($data),
        'data_keys' => !empty($data) ? array_keys($data) : 'no data',
        'raw_input' => file_get_contents('php://input')
    ]
]);
?>