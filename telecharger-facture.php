<?php
// telecharger-facture.php - Téléchargement sécurisé des factures

session_start();
require_once 'config.php';
require_once 'generer_pdf_facture.php';

$pdo = getPDOConnection();

// Récupérer et valider l'ID commande
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if ($commande_id <= 0) {
    die('ID commande invalide');
}

try {
    // ============================================
    // VÉRIFICATION DE LA COMMANDE
    // ============================================
    
    // Récupérer la commande avec les infos client
    $stmt = $pdo->prepare("
        SELECT c.*, cl.email, cl.nom as client_nom, cl.prenom as client_prenom, cl.id_client
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die('Commande non trouvée');
    }
    
    // Vérification d'autorisation
    $autorise = false;
    
    // Cas 1 : Client connecté et c'est sa commande
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == $commande['id_client']) {
        $autorise = true;
    }
    // Cas 2 : Commande récente (juste après paiement)
    elseif (isset($_SESSION['commande_recente']) && $_SESSION['commande_recente'] == $commande_id) {
        $autorise = true;
    }
    // Cas 3 : Administrateur connecté
    elseif (isset($_SESSION['admin_id'])) {
        $autorise = true;
    }
    
    if (!$autorise) {
        die('Accès non autorisé à cette facture. Veuillez vous connecter à votre compte.');
    }
    
    // ============================================
    // RÉCUPÉRATION DES DONNÉES COMPLÈTES
    // ============================================
    
    // Récupérer les adresses
    $stmt_addr = $pdo->prepare("
        SELECT 
            a.nom as livraison_nom,
            a.prenom as livraison_prenom,
            a.adresse as livraison_adresse,
            a.complement as livraison_complement,
            a.code_postal as livraison_code_postal,
            a.ville as livraison_ville,
            a.pays as livraison_pays,
            a.telephone as livraison_telephone,
            af.nom as facturation_nom,
            af.prenom as facturation_prenom,
            af.adresse as facturation_adresse,
            af.complement as facturation_complement,
            af.code_postal as facturation_code_postal,
            af.ville as facturation_ville,
            af.pays as facturation_pays,
            af.telephone as facturation_telephone
        FROM commandes c
        LEFT JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
        LEFT JOIN adresses af ON c.id_adresse_facturation = af.id_adresse
        WHERE c.id_commande = ?
    ");
    $stmt_addr->execute([$commande_id]);
    $adresses = $stmt_addr->fetch(PDO::FETCH_ASSOC);
    
    // Fusionner les données
    $commande = array_merge($commande, $adresses ?: []);
    
    // Récupérer les articles
    $stmt_items = $pdo->prepare("
        SELECT * FROM commande_items 
        WHERE id_commande = ?
    ");
    $stmt_items->execute([$commande_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        die('Aucun article trouvé pour cette commande');
    }
    
    // Récupérer la transaction
    $stmt_trans = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE id_commande = ? 
        ORDER BY date_creation DESC LIMIT 1
    ");
    $stmt_trans->execute([$commande_id]);
    $transaction = $stmt_trans->fetch(PDO::FETCH_ASSOC);
    
    // ============================================
    // GÉNÉRATION ET TÉLÉCHARGEMENT
    // ============================================
    
    $pdf_content = genererPDFFacture($commande, $items, $transaction);
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="facture_' . $commande['numero_commande'] . '.pdf"');
    header('Content-Length: ' . strlen($pdf_content));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    echo $pdf_content;
    
} catch (Exception $e) {
    error_log("Erreur téléchargement facture: " . $e->getMessage());
    die('Erreur lors de la génération de la facture : ' . $e->getMessage());
}
?>