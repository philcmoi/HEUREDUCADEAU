<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Configuration PayPal
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox'
];

// Fonction pour obtenir l'access token
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    }
    return false;
}

// Fonction pour capturer le paiement
function capturePayPalPayment($access_token, $order_id, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v2/checkout/orders/' . $order_id . '/capture'
        : 'https://api.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 201) {
        return json_decode($result, true);
    }
    return false;
}

// Traitement du retour PayPal
if (isset($_GET['token']) && isset($_GET['PayerID'])) {
    $order_id = $_GET['token'];
    $payer_id = $_GET['PayerID'];
    
    // Capturer le paiement
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'],
        $paypal_config['environment']
    );
    
    if ($access_token) {
        $capture = capturePayPalPayment($access_token, $order_id, $paypal_config['environment']);
        
        if ($capture && isset($capture['status']) && $capture['status'] === 'COMPLETED') {
            // Paiement réussi
            $montant = $capture['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
            $transaction_id = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? $order_id;
            
            // Récupérer l'ID de commande depuis les données personnalisées
            $custom_id = $capture['purchase_units'][0]['custom_id'] ?? '';
            $idCommande = str_replace('commande_', '', $custom_id);
            
            if ($idCommande) {
                try {
                    $pdo->beginTransaction();
                    
                    // Mettre à jour le statut de la commande
                    $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee' WHERE idCommande = ?");
                    $stmt->execute([$idCommande]);
                    
                    // Enregistrer le paiement
                    $stmt = $pdo->prepare("
                        INSERT INTO Paiement 
                        (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                        VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
                    ");
                    $stmt->execute([$idCommande, $montant, $transaction_id]);
                    
                    // Vider le panier
                    $stmt = $pdo->prepare("
                        DELETE lp FROM LignePanier lp 
                        JOIN Panier p ON lp.idPanier = p.idPanier 
                        JOIN Commande c ON p.idClient = c.idClient 
                        WHERE c.idCommande = ?
                    ");
                    $stmt->execute([$idCommande]);
                    
                    $pdo->commit();
                    
                    // Rediriger vers la page de succès
                    header("Location: paiement_success.php?commande=" . $idCommande . "&reference=" . $transaction_id);
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    error_log("Erreur traitement paiement: " . $e->getMessage());
                    header("Location: paiement_error.php?erreur=processing");
                    exit;
                }
            }
        }
    }
}

// Si échec
header("Location: paiement_error.php?erreur=paypal");
exit;
?>