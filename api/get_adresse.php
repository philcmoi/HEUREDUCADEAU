<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$response = ['success' => false, 'message' => ''];

try {
    $pdo = getPDOConnection();
    
    $panier_id = $_GET['panier_id'] ?? null;
    $session_id = $_GET['session_id'] ?? session_id();
    
    if ($panier_id) {
        // Récupérer l'adresse via le panier
        $stmt = $pdo->prepare("
            SELECT a.* FROM panier p
            LEFT JOIN clients c ON p.id_client = c.id_client
            LEFT JOIN adresses a ON c.id_client = a.id_client AND a.type_adresse = 'livraison' AND a.principale = 1
            WHERE p.id_panier = ?
            ORDER BY a.date_creation DESC LIMIT 1
        ");
        $stmt->execute([$panier_id]);
        $adresse = $stmt->fetch();
        
        if ($adresse) {
            $response = [
                'success' => true,
                'hasAddress' => true,
                'adresse' => $adresse
            ];
        } else {
            $response = [
                'success' => true,
                'hasAddress' => false,
                'message' => 'Aucune adresse trouvée'
            ];
        }
    } else {
        $response['message'] = 'Panier ID manquant';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Erreur serveur';
    error_log("Erreur get_adresse: " . $e->getMessage());
}

echo json_encode($response);
?>