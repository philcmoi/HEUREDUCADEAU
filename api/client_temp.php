<?php
// api/client_temp.php - Gestion des clients temporaires
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

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
            error_log("Erreur BDD client_temp: " . $e->getMessage());
            return false;
        }
    }
    
    return $pdo;
}

// Fonction pour générer un email temporaire unique
function generateTempEmail() {
    $sessionId = session_id();
    $hash = substr(md5($sessionId . time()), 0, 8);
    return "temp_" . $hash . "@heureducadeau.temp";
}

// Fonction pour créer un client temporaire
function createTempClient() {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    $sessionId = session_id();
    
    // Vérifier si un client temporaire existe déjà pour cette session
    $sqlCheck = "SELECT id_client FROM clients 
                WHERE created_from_session = ? AND is_temporary = 1 
                AND statut = 'actif'";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$sessionId]);
    $existing = $stmtCheck->fetch();
    
    if ($existing) {
        return $existing['id_client'];
    }
    
    // Créer un nouveau client temporaire
    $tempEmail = generateTempEmail();
    $tempPassword = bin2hex(random_bytes(8)); // Mot de passe aléatoire
    
    $sqlInsert = "INSERT INTO clients (
        email, mot_de_passe, nom, prenom, telephone,
        date_inscription, statut, is_temporary, created_from_session
    ) VALUES (?, ?, ?, ?, ?, NOW(), 'actif', 1, ?)";
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare($sqlInsert);
        $stmt->execute([
            $tempEmail,
            password_hash($tempPassword, PASSWORD_DEFAULT),
            "Client",
            "Temporaire",
            NULL,
            $sessionId
        ]);
        
        $clientId = $pdo->lastInsertId();
        $pdo->commit();
        
        // Stocker en session
        $_SESSION['temp_client_id'] = $clientId;
        $_SESSION['temp_client_email'] = $tempEmail;
        
        return $clientId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création client temporaire: " . $e->getMessage());
        return false;
    }
}

// Fonction pour convertir un client temporaire en permanent
function convertToPermanentClient($clientId, $clientData) {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    $sqlUpdate = "UPDATE clients SET
        email = ?,
        nom = ?,
        prenom = ?,
        telephone = ?,
        date_naissance = ?,
        genre = ?,
        is_temporary = 0,
        created_from_session = NULL,
        newsletter = ?
    WHERE id_client = ? AND is_temporary = 1";
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare($sqlUpdate);
        $success = $stmt->execute([
            $clientData['email'],
            $clientData['nom'],
            $clientData['prenom'],
            $clientData['telephone'] ?? NULL,
            $clientData['date_naissance'] ?? NULL,
            $clientData['genre'] ?? NULL,
            $clientData['newsletter'] ?? 1,
            $clientId
        ]);
        
        if ($success) {
            // Mettre à jour le mot de passe si fourni
            if (!empty($clientData['password'])) {
                $sqlPass = "UPDATE clients SET mot_de_passe = ? WHERE id_client = ?";
                $stmtPass = $pdo->prepare($sqlPass);
                $stmtPass->execute([
                    password_hash($clientData['password'], PASSWORD_DEFAULT),
                    $clientId
                ]);
            }
            
            $pdo->commit();
            
            // Mettre à jour la session
            unset($_SESSION['temp_client_id']);
            unset($_SESSION['temp_client_email']);
            $_SESSION['client_id'] = $clientId;
            $_SESSION['client_email'] = $clientData['email'];
            
            return true;
        }
        
        $pdo->rollBack();
        return false;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur conversion client: " . $e->getMessage());
        return false;
    }
}

// Fonction pour obtenir ou créer un client temporaire
function getOrCreateTempClient() {
    if (isset($_SESSION['temp_client_id'])) {
        return $_SESSION['temp_client_id'];
    }
    
    return createTempClient();
}

// Gestion des actions via requête
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$action = '';
if (!empty($data) && isset($data['action'])) {
    $action = trim($data['action']);
} elseif (isset($_GET['action'])) {
    $action = trim($_GET['action']);
}

switch ($action) {
    case 'get_or_create':
        $clientId = getOrCreateTempClient();
        if ($clientId) {
            echo json_encode([
                'success' => true,
                'client_id' => $clientId,
                'is_temporary' => true,
                'session_id' => session_id()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Erreur création client temporaire'
            ]);
        }
        break;
        
    case 'convert':
        if (empty($data['client_id']) || empty($data['client_data'])) {
            echo json_encode(['success' => false, 'message' => 'Données manquantes']);
            break;
        }
        
        $success = convertToPermanentClient($data['client_id'], $data['client_data']);
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Client converti en permanent' : 'Erreur conversion'
        ]);
        break;
        
    case 'get_info':
        $clientId = getOrCreateTempClient();
        if ($clientId) {
            $pdo = getPDOConnection();
            $sql = "SELECT id_client, email, nom, prenom, is_temporary FROM clients WHERE id_client = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();
            
            echo json_encode([
                'success' => true,
                'client' => $client
            ]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue',
            'actions_disponibles' => ['get_or_create', 'convert', 'get_info']
        ]);
}
?>