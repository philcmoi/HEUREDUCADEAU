<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Simuler une base de données avec un fichier JSON
$file_path = 'commandes.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action'])) {
        echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
        exit;
    }
    
    $action = $input['action'];
    
    switch ($action) {
        case 'creer_commande':
            // Récupérer les données de la commande
            $commande = [
                'id' => uniqid('CMD_'),
                'date' => date('Y-m-d H:i:s'),
                'client' => $input['client'] ?? [],
                'articles' => $input['articles'] ?? [],
                'total' => $input['total'] ?? 0,
                'statut' => 'en_attente'
            ];
            
            // Lire les commandes existantes
            $commandes = file_exists($file_path) ? json_decode(file_get_contents($file_path), true) : [];
            $commandes[] = $commande;
            
            // Sauvegarder
            if (file_put_contents($file_path, json_encode($commandes, JSON_PRETTY_PRINT))) {
                echo json_encode([
                    'success' => true,
                    'commande_id' => $commande['id'],
                    'message' => 'Commande créée avec succès'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur de sauvegarde']);
            }
            break;
            
        case 'verifier_stock':
            // Simuler la vérification de stock
            echo json_encode(['success' => true, 'stock_ok' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}
?>