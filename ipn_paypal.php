<?php
// ============================================
// IPN PAYPAL - NOTIFICATIONS INSTANTANÉES
// ============================================

require_once 'config.php';

// Lire et décoder la notification
$raw_post_data = file_get_contents('php://input');
$raw_post_array = explode('&', $raw_post_data);
$myPost = array();

foreach ($raw_post_array as $keyval) {
    $keyval = explode('=', $keyval);
    if (count($keyval) == 2) {
        $myPost[$keyval[0]] = urldecode($keyval[1]);
    }
}

// Construire la requête de vérification
$req = 'cmd=_notify-validate';
foreach ($myPost as $key => $value) {
    $value = urlencode($value);
    $req .= "&$key=$value";
}

// Définir l'URL PayPal selon le mode
$paypal_mode = 'sandbox'; // À modifier en production
$paypal_url = ($paypal_mode === 'sandbox') 
    ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' 
    : 'https://www.paypal.com/cgi-bin/webscr';

// Vérifier la notification
$ch = curl_init($paypal_url);
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: Close'));

$res = curl_exec($ch);
curl_close($ch);

// Journalisation
$log_file = 'ipn_paypal.log';
$log_data = date('Y-m-d H:i:s') . " - IPN reçu:\n" . print_r($myPost, true) . "\n\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

if (strcmp($res, "VERIFIED") == 0) {
    // IPN vérifié - traiter la commande
    $pdo = getPDOConnection();
    
    if ($pdo && isset($myPost['custom']) && isset($myPost['payment_status'])) {
        $id_commande = intval($myPost['custom']);
        $payment_status = $myPost['payment_status'];
        $txn_id = $myPost['txn_id'] ?? '';
        $mc_gross = $myPost['mc_gross'] ?? 0;
        $payer_email = $myPost['payer_email'] ?? '';
        
        if ($payment_status == 'Completed') {
            try {
                $pdo->beginTransaction();
                
                // Mettre à jour la commande
                $stmt = $pdo->prepare("
                    UPDATE commandes 
                    SET statut = 'confirmee',
                        statut_paiement = 'paye',
                        reference_paiement = ?,
                        email_paypal = ?,
                        date_paiement = NOW()
                    WHERE id_commande = ?
                ");
                $stmt->execute([$txn_id, $payer_email, $id_commande]);
                
                // Créer la transaction
                $stmt_trans = $pdo->prepare("
                    INSERT INTO transactions (
                        numero_transaction,
                        id_commande,
                        montant,
                        methode_paiement,
                        reference_paiement,
                        statut,
                        details,
                        date_creation
                    ) VALUES (?, ?, ?, 'paypal', ?, 'paye', ?, NOW())
                ");
                
                $details = json_encode($myPost);
                $stmt_trans->execute([
                    'PP_' . date('Ymd') . '_' . uniqid(),
                    $id_commande,
                    $mc_gross,
                    $txn_id,
                    $details
                ]);
                
                $pdo->commit();
                
                // Journaliser le succès
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - Commande #$id_commande marquée comme payée\n\n", FILE_APPEND);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                file_put_contents($log_file, date('Y-m-d H:i:s') . " - ERREUR: " . $e->getMessage() . "\n\n", FILE_APPEND);
            }
        }
    }
    
} else if (strcmp($res, "INVALID") == 0) {
    // IPN invalide
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - IPN INVALIDE\n\n", FILE_APPEND);
}

// Toujours répondre 200 OK
http_response_code(200);
?>