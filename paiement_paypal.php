<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'C:/wamp64/logs/paypal_errors.log');
session_start();

require_once 'smtp_config.php';
// Configuration de la base de données
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Configuration PayPal
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox',
    'currency' => 'EUR'
];

// Vérifier si une commande est spécifiée
$idCommande = $_GET['commande'] ?? $_POST['id_commande'] ?? null;

if (!$idCommande) {
    header('Location: index.html');
    exit;
}

// Récupérer les informations de la commande
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.idCommande,
            c.montantTotal,
            c.statut,
            cl.email,
            cl.prenom,
            cl.nom,
            a_liv.adresse as adresse_livraison,
            a_liv.codePostal as cp_livraison,
            a_liv.ville as ville_livraison
        FROM Commande c
        JOIN Client cl ON c.idClient = cl.idClient
        JOIN Adresse a_liv ON c.idAdresseLivraison = a_liv.idAdresse
        WHERE c.idCommande = ? AND c.statut = 'en_attente_paiement'
    ");
    $stmt->execute([$idCommande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        die("Commande non trouvée ou déjà traitée");
    }
} catch (Exception $e) {
    die("Erreur lors de la récupération de la commande: " . $e->getMessage());
}

// Fonction pour obtenir l'access token PayPal
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($result === false) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    } else {
        error_log("PAYPAL ACCESS TOKEN HTTP ERROR: $http_code - Response: " . $result);
        return false;
    }
}

// Fonction pour créer un paiement PayPal
function creerPaiementPayPal($commande, $paypal_config) {
    try {
        // Obtenir l'access token
        $access_token = getPayPalAccessToken(
            $paypal_config['client_id'],
            $paypal_config['client_secret'],
            $paypal_config['environment']
        );
        
        if (!$access_token) {
            return [
                'success' => false,
                'error' => 'Erreur de connexion à PayPal',
                'approval_url' => null
            ];
        }
        
        // URL de l'API PayPal
        $url = $paypal_config['environment'] === 'live' 
            ? 'https://api.paypal.com/v1/payments/payment'
            : 'https://api.sandbox.paypal.com/v1/payments/payment';
        
        // Données du paiement
        $data = [
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'paypal'
            ],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($commande['montantTotal'], 2, '.', ''),
                    'currency' => $paypal_config['currency'],
                    'details' => [
                        'subtotal' => number_format($commande['montantTotal'], 2, '.', ''),
                        'shipping' => '0.00',
                        'tax' => '0.00'
                    ]
                ],
                'description' => 'Commande #' . $commande['idCommande'] . ' - Youki and Go',
                'custom' => $commande['idCommande'],
                'invoice_number' => 'CMD-' . $commande['idCommande'] . '-' . time()
            ]],
            'redirect_urls' => [
                'return_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/paiement_paypal.php?commande=' . $commande['idCommande'] . '&status=success',
                'cancel_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/paiement_paypal.php?commande=' . $commande['idCommande'] . '&status=cancel'
            ]
        ];
        
        // Exécuter la requête PayPal
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($result === false) {
            error_log("cURL Error paiement: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($http_code == 201) {
            $response = json_decode($result, true);
            
            // Trouver l'URL d'approbation
            foreach ($response['links'] as $link) {
                if ($link['rel'] === 'approval_url') {
                    return [
                        'success' => true,
                        'approval_url' => $link['href'],
                        'payment_id' => $response['id'],
                        'response' => $response
                    ];
                }
            }
            
            return [
                'success' => false,
                'error' => 'URL d\'approbation non trouvée',
                'approval_url' => null
            ];
            
        } else {
            $error_response = json_decode($result, true);
            $error_message = 'Erreur PayPal: ';
            
            if (isset($error_response['details'][0]['description'])) {
                $error_message .= $error_response['details'][0]['description'];
            } else if (isset($error_response['message'])) {
                $error_message .= $error_response['message'];
            } else {
                $error_message .= 'Erreur inconnue - HTTP: ' . $http_code;
            }
            
            error_log("Erreur création paiement PayPal: " . $result);
            
            return [
                'success' => false,
                'error' => $error_message,
                'approval_url' => null
            ];
        }
        
    } catch (Exception $e) {
        error_log("Exception création paiement PayPal: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur technique: ' . $e->getMessage(),
            'approval_url' => null
        ];
    }
}

// Fonction pour exécuter un paiement PayPal
function executerPaiementPayPal($paymentId, $payerId, $paypal_config) {
    try {
        // Obtenir l'access token
        $access_token = getPayPalAccessToken(
            $paypal_config['client_id'],
            $paypal_config['client_secret'],
            $paypal_config['environment']
        );
        
        if (!$access_token) {
            return [
                'success' => false,
                'error' => 'Erreur de connexion à PayPal'
            ];
        }
        
        // URL de l'API PayPal
        $url = $paypal_config['environment'] === 'live' 
            ? 'https://api.paypal.com/v1/payments/payment/' . $paymentId . '/execute'
            : 'https://api.sandbox.paypal.com/v1/payments/payment/' . $paymentId . '/execute';
        
        // Données d'exécution
        $data = [
            'payer_id' => $payerId
        ];
        
        // Exécuter la requête PayPal
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($http_code == 200) {
            $response = json_decode($result, true);
            
            if ($response['state'] === 'approved') {
                $transaction_id = $response['transactions'][0]['related_resources'][0]['sale']['id'];
                
                return [
                    'success' => true,
                    'reference' => $transaction_id,
                    'response' => $response
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Paiement non approuvé par PayPal'
                ];
            }
            
        } else {
            $error_response = json_decode($result, true);
            $error_message = 'Erreur PayPal: ';
            
            if (isset($error_response['details'][0]['description'])) {
                $error_message .= $error_response['details'][0]['description'];
            } else if (isset($error_response['message'])) {
                $error_message .= $error_response['message'];
            } else {
                $error_message .= 'Erreur inconnue - HTTP: ' . $http_code;
            }
            
            error_log("Erreur exécution paiement PayPal: " . $result);
            
            return [
                'success' => false,
                'error' => $error_message
            ];
        }
        
    } catch (Exception $e) {
        error_log("Exception exécution paiement PayPal: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur technique: ' . $e->getMessage()
        ];
    }
}

// Fonction pour envoyer l'email de confirmation PayPal
function envoyerEmailConfirmationPayPal($commande, $reference) {
    require_once 'PHPMailer/src/Exception.php';
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->SMTPDebug = 0;
        $mail->CharSet = 'UTF-8';
        
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Destinataires
        $mail->setFrom(SMTP_FROM_EMAIL, 'Youki and Go');
        $mail->addAddress($commande['email']);
        $mail->addReplyTo(SMTP_USERNAME, 'Youki and Go');
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = "Confirmation de paiement PayPal - Commande #" . $commande['idCommande'];
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
                .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
                .header { text-align: center; color: #d40000; margin-bottom: 20px; }
                .success { color: #28a745; font-size: 24px; }
                .details { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Youki and Go</h1>
                </div>
                
                <h2 class='success'>✅ Paiement PayPal Confirmé</h2>
                
                <p>Bonjour " . htmlspecialchars($commande['prenom']) . ",</p>
                
                <p>Votre paiement PayPal pour la commande <strong>#" . $commande['idCommande'] . "</strong> a été traité avec succès.</p>
                
                <div class='details'>
                    <p><strong>Référence PayPal :</strong> " . $reference . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Mode de paiement :</strong> PayPal</p>
                </div>
                
                <p>Votre commande est en cours de préparation et vous sera livrée à l'adresse :</p>
                <p><strong>" . htmlspecialchars($commande['adresse_livraison']) . "<br>
                " . $commande['cp_livraison'] . " " . htmlspecialchars($commande['ville_livraison']) . "</strong></p>
                
                <p>Merci pour votre confiance !</p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
                    <p><strong>Youki and Go</strong><br>
                    📧 contact@YoukiandGo.fr | 📞 +33 1 23 45 67 89<br>
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        $mail->send();
        
    } catch (Exception $e) {
        error_log("Erreur envoi email PayPal: " . $e->getMessage());
    }
}

// Fonction pour afficher la confirmation PayPal
function afficherConfirmationPayPal($commande, $reference) {
    $idCommande = $commande['idCommande'];
    $urlFactureHTML = 'http://' . $_SERVER['HTTP_HOST'] . '/facture.php?id=' . $idCommande;
    
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement PayPal Confirmé - Youki and Go</title>
        <style>
            body { 
                font-family: 'Helvetica Neue', Arial, sans-serif; 
                background-color: #f9f9f9; 
                margin: 0; 
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .container { 
                max-width: 600px; 
                background: white; 
                padding: 40px; 
                border-radius: 8px; 
                box-shadow: 0 5px 15px rgba(0,0,0,0.1);
                text-align: center;
            }
            .success { 
                color: #28a745; 
                font-size: 48px;
                margin-bottom: 20px;
            }
            .details {
                text-align: left;
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .btn { 
                display: inline-block;
                background-color: #0070ba; 
                color: white; 
                padding: 12px 30px; 
                text-decoration: none; 
                border-radius: 4px; 
                margin-top: 20px;
                border: none;
                cursor: pointer;
                font-size: 16px;
            }
            .btn:hover {
                background-color: #005ea6;
            }
            .btn-facture { 
                background-color: #28a745; 
                color: white; 
                padding: 10px 20px; 
                text-decoration: none; 
                border-radius: 4px; 
                margin: 5px;
                display: inline-block;
                border: none;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-facture:hover {
                background-color: #218838;
            }
            .facture-options {
                background: #e7f3ff;
                border: 1px solid #b3d9ff;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">✅</div>
            <h1>Paiement PayPal Confirmé !</h1>
            
            <p>Votre paiement via PayPal a été traité avec succès.</p>
            
            <div class="details">
                <p><strong>Commande :</strong> #<?= $commande['idCommande'] ?></p>
                <p><strong>Référence PayPal :</strong> <?= $reference ?></p>
                <p><strong>Montant :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
                <p><strong>Mode de paiement :</strong> PayPal</p>
            </div>
            
            <p>Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong>.</p>
            <p>Votre commande est en cours de préparation.</p>

            <!-- Section Options de Facture -->
            <!--<div class="facture-options">
                <h3>📄 Options de facture</h3>
                <p>Vous pouvez déjà télécharger votre facture :</p>
                <a href="<?//= $urlFactureHTML ?>" target="_blank" class="btn-facture">👁️ Voir la facture HTML</a>
                <button onclick="telechargerFacturePDF(<?= $idCommande ?>)" class="btn-facture">📥 Télécharger PDF</button>
            </div>-->
            
            <a href="index.html" class="btn">Retour à l'accueil</a>
        </div>

        <script>
            function telechargerFacturePDF(idCommande) {
                window.open('acheter.php?action=telecharger_facture&id_commande=' + idCommande, '_blank');
            }
        </script>
    </body>
    </html>
    <?php
}

// Traitement des retours PayPal
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success' && isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
        // Paiement approuvé par l'utilisateur
        $paymentId = $_GET['paymentId'];
        $payerId = $_GET['PayerID'];
        
        // Exécuter le paiement
        $resultat = executerPaiementPayPal($paymentId, $payerId, $paypal_config);
        
        if ($resultat['success']) {
            // Paiement réussi
            try {
                // Démarrer une transaction
                $pdo->beginTransaction();
                
                // Mettre à jour le statut de la commande
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'PayPal' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                
                // Enregistrer le paiement
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'PayPal', ?, NOW())
                ");
                $reference = $resultat['reference'];
                $stmt->execute([$idCommande, $commande['montantTotal'], $reference]);
                
                // Valider la transaction
                $pdo->commit();
                
                // Envoyer un email de confirmation
                envoyerEmailConfirmationPayPal($commande, $reference);
                
                // Afficher la page de confirmation
                afficherConfirmationPayPal($commande, $reference);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                die("Erreur lors de l'enregistrement du paiement: " . $e->getMessage());
            }
        } else {
            die("Erreur lors de l'exécution du paiement PayPal: " . $resultat['error']);
        }
        
    } elseif ($_GET['status'] === 'cancel') {
        // Paiement annulé par l'utilisateur
        echo "<h2>Paiement annulé</h2>";
        echo "<p>Vous avez annulé le paiement. Votre commande reste en attente.</p>";
        echo '<a href="acheter.php?action=paypal_success&commande=' . $idCommande . '">Retour aux options de paiement</a>';
        exit;
    }
}

// Créer un nouveau paiement PayPal
$resultatCreation = creerPaiementPayPal($commande, $paypal_config);

if ($resultatCreation['success']) {
    // Rediriger vers PayPal
    header('Location: ' . $resultatCreation['approval_url']);
    exit;
} else {
    die("Erreur lors de la création du paiement PayPal: " . $resultatCreation['error']);
}
?>