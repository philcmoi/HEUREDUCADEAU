<?php
// traitement_paiement.php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_payment_method') {
        $mode_paiement = $_POST['mode_paiement'] ?? '';
        $panier_id = $_POST['panier_id'] ?? '';
        $total = $_POST['total'] ?? 0;
        
        if (empty($mode_paiement) || empty($panier_id)) {
            throw new Exception('Données manquantes');
        }
        
        // Sauvegarder dans la session
        $_SESSION['mode_paiement'] = $mode_paiement;
        $_SESSION['panier_id'] = $panier_id;
        $_SESSION['total_commande'] = $total;
        
        echo json_encode([
            'success' => true,
            'message' => 'Mode de paiement sauvegardé',
            'mode_paiement' => $mode_paiement
        ]);
    } else {
        throw new Exception('Action non reconnue');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>