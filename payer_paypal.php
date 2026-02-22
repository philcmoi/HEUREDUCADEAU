<?php
// ============================================
// payer_paypal.php - TRAITEMENT DU RETOUR PAYPAL
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

if (!isset($_GET['token']) || !isset($_GET['PayerID'])) {
    die("Paramètres PayPal manquants");
}

$paypal_order_id = $_GET['token'];
$payer_id = $_GET['PayerID'];

$pdo = getPDOConnection();
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

try {
    $pdo->beginTransaction();
    
    // Récupérer l'ID commande depuis la session
    $commande_id = $_SESSION[SESSION_KEY_COMMANDE]['id'] ?? 0;
    
    if (!$commande_id) {
        throw new Exception("ID commande non trouvé");
    }
    
    // Vérifier que la commande existe
    $stmt_check = $pdo->prepare("SELECT id_commande, id_client, total_ttc FROM commandes WHERE id_commande = ?");
    $stmt_check->execute([$commande_id]);
    $commande = $stmt_check->fetch();
    
    if (!$commande) {
        throw new Exception("Commande non trouvée: $commande_id");
    }
    
    // Générer une référence de paiement
    $reference = 'PP_' . time() . '_' . uniqid();
    
    // Mettre à jour la commande
    $stmt = $pdo->prepare("
        UPDATE commandes 
        SET statut = 'confirmee',
            statut_paiement = 'paye',
            reference_paiement = ?,
            reference_paypal = ?,
            payer_id = ?,
            date_paiement = NOW()
        WHERE id_commande = ?
    ");
    $stmt->execute([$reference, $paypal_order_id, $payer_id, $commande_id]);
    
    // Créer la transaction
    $numero_transaction = 'PP_' . date('Ymd') . '_' . uniqid();
    $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    
    $stmt_trans = $pdo->prepare("
        INSERT INTO transactions 
        (numero_transaction, id_commande, id_client, montant, methode_paiement,
         reference_paiement, statut, date_creation, ip_client) 
        VALUES (?, ?, ?, ?, 'paypal', ?, 'paye', NOW(), ?)
    ");
    
    $stmt_trans->execute([
        $numero_transaction,
        $commande_id,
        $commande['id_client'],
        $commande['total_ttc'],
        $reference,
        $ip_client
    ]);
    
    // Mettre à jour les stocks
    $stmt_stock = $pdo->prepare("
        UPDATE produits p
        JOIN commande_items ci ON p.id_produit = ci.id_produit
        SET p.ventes = p.ventes + ci.quantite,
            p.quantite_stock = p.quantite_stock - ci.quantite
        WHERE ci.id_commande = ?
    ");
    $stmt_stock->execute([$commande_id]);
    
    // Logger le succès
    $stmt_log = $pdo->prepare("
        INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
        VALUES ('paiement', 'info', ?, ?, ?)
    ");
    $stmt_log->execute([
        'Paiement PayPal réussi pour commande #' . $commande_id,
        $commande['id_client'],
        $ip_client
    ]);
    
    $pdo->commit();
    
    // Vider le panier
    cleanUserSession();
    
    // Rediriger vers confirmation
    header('Location: confirmation.php?commande=' . $commande_id);
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erreur paiement PayPal: " . $e->getMessage());
    
    // Logger l'erreur
    try {
        $stmt_log = $pdo->prepare("
            INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
            VALUES ('paiement', 'error', ?, ?, ?)
        ");
        $stmt_log->execute([
            'Erreur paiement PayPal: ' . $e->getMessage(),
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            json_encode(['commande_id' => $commande_id])
        ]);
    } catch (Exception $logError) {}
    
    die("Erreur lors du paiement : " . $e->getMessage());
}