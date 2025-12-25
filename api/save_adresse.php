<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$response = ['success' => false, 'message' => ''];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $response['message'] = 'Données invalides';
        echo json_encode($response);
        exit;
    }
    
    // Validation des champs obligatoires
    $required = ['nom', 'prenom', 'email', 'adresse', 'code_postal', 'ville', 'pays'];
    $missing = [];
    
    foreach ($required as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        $response['message'] = 'Champs obligatoires manquants';
        $response['missing'] = $missing;
        echo json_encode($response);
        exit;
    }
    
    // Sauvegarder dans la base de données
    $result = sauvegarderAdresse($input);
    
    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => 'Adresse sauvegardée avec succès',
            'adresse_id' => $result['adresse_livraison_id']
        ];
        
        // Sauvegarder dans la table logs
        $pdo = getPDOConnection();
        $stmt = $pdo->prepare("
            INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, user_agent)
            VALUES ('info', 'info', 'Adresse sauvegardée', ?, ?, ?)
        ");
        $stmt->execute([
            $result['client_id'],
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            $_SERVER['HTTP_USER_AGENT'] ?? 'Inconnu'
        ]);
        
    } else {
        $response['message'] = $result['message'];
    }
    
} catch (Exception $e) {
    $response['message'] = 'Erreur serveur';
    error_log("Erreur save_adresse: " . $e->getMessage());
}

echo json_encode($response);