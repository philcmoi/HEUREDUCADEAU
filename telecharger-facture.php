<?php
// telecharger-facture.php - Téléchargement sécurisé des factures

session_start();
require_once 'config.php';
require_once 'generer_pdf_facture.php';

/**
 * Obtient une connexion PDO (fallback si config.php ne définit pas la fonction)
 */
if (!function_exists('getPDOConnection')) {
    function getPDOConnection() {
        $host = 'localhost';
        $dbname = 'heureducadeau';
        $username = 'Philippe';
        $password = 'l@99339R';
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $pdo;
        } catch (PDOException $e) {
            error_log("Erreur connexion BDD: " . $e->getMessage());
            return null;
        }
    }
}

$pdo = getPDOConnection();

// Récupérer et valider l'ID commande
$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if ($commande_id <= 0) {
    die('ID commande invalide');
}

if (!$pdo) {
    die('Erreur de connexion à la base de données');
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
        die('Commande non trouvee');
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
        die('Acces non autorise a cette facture. Veuillez vous connecter a votre compte.');
    }
    
    // ============================================
    // RÉCUPÉRATION DES DONNÉES COMPLÈTES
    // ============================================
    
    // Récupérer les adresses
    $stmt_addr = $pdo->prepare("
        SELECT 
            a.nom, a.prenom, a.adresse, a.complement, a.code_postal, a.ville, a.pays, a.telephone
        FROM commandes c
        LEFT JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
        WHERE c.id_commande = ?
    ");
    $stmt_addr->execute([$commande_id]);
    $adresse = $stmt_addr->fetch(PDO::FETCH_ASSOC);
    
    // Fusionner les données d'adresse avec la commande
    if ($adresse) {
        foreach ($adresse as $key => $value) {
            if (!isset($commande[$key]) || empty($commande[$key])) {
                $commande[$key] = $value;
            }
        }
    }
    
    // Récupérer les articles
    $stmt_items = $pdo->prepare("
        SELECT * FROM commande_items 
        WHERE id_commande = ?
    ");
    $stmt_items->execute([$commande_id]);
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        die('Aucun article trouve pour cette commande');
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
    error_log("Erreur telechargement facture: " . $e->getMessage());
    die('Erreur lors de la generation de la facture : ' . $e->getMessage());
}
?>