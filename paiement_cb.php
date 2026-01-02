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

// Configuration PayPal Production
$paypal_config = [
    'client_id' => 'Aac1-P0VrxBQ_5REVeo4f557_-p6BDeXA_hyiuVZfi21sILMWccBFfTidQ6nnhQathCbWaCSQaDmxJw5',
    'client_secret' => 'EJxech0i1faRYlo0-ln2sU09ecx5rP3XEOGUTeTduI2t-I0j4xoSPqRRFQTxQsJoSBbSL8aD1b1GPPG1',
    'environment' => 'sandbox', // ← 'sandbox' pour les tests locaux
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

// FONCTION DE DIAGNOSTIC PAYPAL
function diagnostiquerPayPal($paypal_config) {
    error_log("=== DIAGNOSTIC PAYPAL ===");
    error_log("Environment: " . $paypal_config['environment']);
    error_log("Client ID: " . substr($paypal_config['client_id'], 0, 10) . "...");
    error_log("Client Secret: " . substr($paypal_config['client_secret'], 0, 10) . "...");
    
    // Test de connexion
    $access_token = getPayPalAccessToken(
        $paypal_config['client_id'],
        $paypal_config['client_secret'], 
        $paypal_config['environment']
    );
    
    if ($access_token) {
        error_log("✅ Connexion PayPal OK - Token obtenu");
        return true;
    } else {
        error_log("❌ Échec connexion PayPal");
        return false;
    }
}

// Exécuter le diagnostic
diagnostiquerPayPal($paypal_config);

// Fonction pour obtenir l'access token PayPal - VERSION CORRIGÉE
function getPayPalAccessToken($client_id, $client_secret, $environment) {
    $url = $environment === 'live' 
        ? 'https://api.paypal.com/v1/oauth2/token'
        : 'https://api.sandbox.paypal.com/v1/oauth2/token';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    
    // Désactiver la vérification SSL en local
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ":" . $client_secret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Debug
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($result === false) {
        $error_msg = "cURL Error: " . curl_error($ch);
        error_log("PAYPAL ACCESS TOKEN ERROR: " . $error_msg);
        
        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);
        error_log("cURL Verbose: " . $verboseLog);
        
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($http_code == 200) {
        $json = json_decode($result);
        return $json->access_token;
    } else {
        error_log("PAYPAL ACCESS TOKEN HTTP ERROR: $http_code - Response: " . $result);
        
        $error_response = json_decode($result, true);
        if (isset($error_response['error_description'])) {
            error_log("PayPal Error Description: " . $error_response['error_description']);
        }
        if (isset($error_response['error'])) {
            error_log("PayPal Error: " . $error_response['error']);
        }
        
        return false;
    }
}

// Fonction pour traiter le paiement par carte via PayPal - AVEC SIMULATION WAMP
function traiterPaiementPayPalCB($donnees, $paypal_config) {
    // SIMULATION POUR WAMP - DÉCOMMENTER POUR TESTER
    sleep(2);
    return [
        'success' => true,
        'reference' => 'SIMU_WAMP_' . time() . '_' . $donnees['commande']['idCommande'],
        'response' => ['state' => 'approved', 'simulated' => true]
    ];
    
    
}

// Fonction pour détecter le type de carte
function detecterTypeCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    // Visa
    if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $numero)) {
        return 'visa';
    }
    // MasterCard
    if (preg_match('/^5[1-5][0-9]{14}$/', $numero)) {
        return 'mastercard';
    }
    // American Express
    if (preg_match('/^3[47][0-9]{13}$/', $numero)) {
        return 'amex';
    }
    // Discover
    if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $numero)) {
        return 'discover';
    }
    
    return 'visa'; // Par défaut
}

// Fonction pour valider le numéro de carte avec l'algorithme de Luhn
function validerNumeroCarte($numero) {
    $numero = str_replace(' ', '', $numero);
    
    // Vérifier la longueur
    if (strlen($numero) < 13 || strlen($numero) > 19) {
        return false;
    }
    
    // Vérifier que ce sont des chiffres
    if (!ctype_digit($numero)) {
        return false;
    }
    
    // Algorithme de Luhn
    $somme = 0;
    $inverse = strrev($numero);
    
    for ($i = 0; $i < strlen($inverse); $i++) {
        $chiffre = (int)$inverse[$i];
        
        if ($i % 2 == 1) {
            $chiffre *= 2;
            if ($chiffre > 9) {
                $chiffre -= 9;
            }
        }
        
        $somme += $chiffre;
    }
    
    return ($somme % 10 == 0);
}

// Fonction pour valider la date d'expiration
function validerDateExpiration($date) {
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $date, $matches)) {
        return false;
    }
    
    $mois = (int)$matches[1];
    $annee = (int)$matches[2];
    $anneeComplete = 2000 + $annee;
    
    // Vérifier si la carte n'est pas expirée
    $aujourdhui = new DateTime();
    $dateExpiration = new DateTime("$anneeComplete-$mois-01");
    $dateExpiration->modify('last day of this month');
    
    return $dateExpiration > $aujourdhui;
}

// NOUVELLE FONCTION : Générer et envoyer la facture par email
function envoyerFactureAvecEmail($pdo, $commande, $reference) {
    error_log("📧 Début envoi facture avec email pour commande: " . $commande['idCommande']);
    
    try {
        // Inclure la fonction de génération PDF
        require_once 'genererFacturePDF.php';
        
        // Générer la facture PDF
        $cheminFacture = genererFacturePDF($pdo, $commande['idCommande']);
        
        if (!$cheminFacture || !file_exists($cheminFacture)) {
            error_log("❌ Erreur génération PDF pour commande: " . $commande['idCommande']);
            return false;
        }
        
        error_log("✅ PDF généré: " . $cheminFacture);
        
        // Inclure PHPMailer
        require_once 'PHPMailer/src/Exception.php';
        require_once 'PHPMailer/src/PHPMailer.php';
        require_once 'PHPMailer/src/SMTP.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'ssl' ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
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
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($commande['email']);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Pièce jointe - la facture PDF
        $mail->addAttachment($cheminFacture, 'facture_' . $commande['idCommande'] . '.pdf');
        
        // Sujet et contenu de l'email
        $mail->isHTML(true);
        $mail->Subject = "Votre facture Youki and Co - Commande #" . $commande['idCommande'];
        
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
                .facture-info { background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 4px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Youki and Co</h1>
                    <p>Créations artisanales japonaises</p>
                </div>
                
                <h2 class='success'>✅ Paiement Confirmé</h2>
                
                <p>Bonjour <strong>" . htmlspecialchars($commande['prenom']) . " " . htmlspecialchars($commande['nom']) . "</strong>,</p>
                
                <p>Votre paiement par carte bancaire pour la commande <strong>#" . $commande['idCommande'] . "</strong> a été traité avec succès via PayPal.</p>
                
                <div class='details'>
                    <p><strong>Référence de transaction :</strong> " . $reference . "</p>
                    <p><strong>Montant :</strong> " . number_format($commande['montantTotal'], 2, ',', ' ') . " €</p>
                    <p><strong>Mode de paiement :</strong> Carte Bancaire (PayPal)</p>
                </div>

                <div class='facture-info'>
                    <h3>📄 Votre facture</h3>
                    <p>Votre facture détaillée est jointe à cet email au format PDF.</p>
                    <p>Vous pouvez également la télécharger depuis votre espace client.</p>
                </div>
                
                <p>Votre commande est en cours de préparation et vous sera livrée à l'adresse :</p>
                <p><strong>" . htmlspecialchars($commande['adresse_livraison']) . "<br>
                " . $commande['cp_livraison'] . " " . htmlspecialchars($commande['ville_livraison']) . "</strong></p>
                
                <p>Nous vous remercions pour votre confiance et espérons vous revoir très bientôt !</p>
                
                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 14px;'>
                    <p><strong>Youki and Co - Créations artisanales japonaises</strong></p>
                    <p>📧 " . SMTP_FROM_EMAIL . " | 📞 +33 1 23 45 67 89</p>
                    <p>123 Rue du Papier, 75000 Paris, France</p>
                    <p><em>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</em></p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);
        
        if ($mail->send()) {
            error_log("✅ Email avec facture envoyé avec succès à: " . $commande['email']);
            return true;
        } else {
            error_log("❌ Erreur envoi email avec facture: " . $mail->ErrorInfo);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("❌ ERREUR envoi facture par email: " . $e->getMessage());
        return false;
    }
}

// Fonction pour afficher la confirmation
function afficherConfirmationCB($commande, $reference) {
    // Récupérer l'ID de commande depuis les données de la commande
    $idCommande = $commande['idCommande'];
    
    // Générer l'URL de la facture HTML
    $urlFactureHTML = 'http://' . $_SERVER['HTTP_HOST'] . '/admin_factures.php?action=generer&id=' . $idCommande;
    
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Paiement Confirmé - Youki and Co</title>
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
                background-color: #d40000; 
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
                background-color: #b30000;
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
            .email-notice {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
                color: #155724;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">✅</div>
            <h1>Paiement Confirmé !</h1>
            
            <p>Votre paiement par carte bancaire a été traité avec succès via PayPal.</p>
            
            <div class="details">
                <p><strong>Commande :</strong> #<?= $commande['idCommande'] ?></p>
                <p><strong>Référence PayPal :</strong> <?= $reference ?></p>
                <p><strong>Montant :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
                <p><strong>Mode de paiement :</strong> Carte Bancaire (PayPal)</p>
            </div>

            <div class="email-notice">
                <p><strong>📧 Email envoyé !</strong></p>
                <p>Un email de confirmation avec votre facture PDF a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong>.</p>
            </div>
            
            <p>Votre commande est en cours de préparation.</p>

            <!-- Section Options de Facture -->
            <!--<div class="facture-options">
                <h3>📄 Options de facture</h3>
                <p>Vous pouvez également :</p>
                <a href="<?//= $urlFactureHTML ?>" target="_blank" class="btn-facture">👁️ Voir la facture HTML</a>
                <button onclick="telechargerFacturePDF(<?//= $idCommande ?>)" class="btn-facture">📥 Télécharger PDF</button>
            </div>-->
            
            <a href="index.html" class="btn">Retour à l'accueil</a>
        </div>

        <script>
            function telechargerFacturePDF(idCommande) {
                window.open('generer_facture.php?id=' + idCommande, '_blank');
            }
        </script>
    </body>
    </html>
    <?php
}

// Traitement du formulaire de paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_paiement_cb') {
    
    // Récupération des données du formulaire
    $numeroCarte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $dateExpiration = $_POST['date_expiration'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $titulaire = $_POST['titulaire_carte'] ?? '';
    
    // Validation des données
    $erreurs = [];
    
    if (!validerNumeroCarte($numeroCarte)) {
        $erreurs[] = "Numéro de carte invalide";
    }
    
    if (!validerDateExpiration($dateExpiration)) {
        $erreurs[] = "Date d'expiration invalide ou carte expirée";
    }
    
    if (strlen($cryptogramme) !== 3 || !is_numeric($cryptogramme)) {
        $erreurs[] = "Cryptogramme invalide";
    }
    
    if (empty($titulaire) || strlen($titulaire) < 2) {
        $erreurs[] = "Nom du titulaire invalide";
    }
    
    if (empty($erreurs)) {
        // Traitement réel du paiement avec PayPal
        $resultatPaiement = traiterPaiementPayPalCB([
            'numero_carte' => $numeroCarte,
            'date_expiration' => $dateExpiration,
            'cryptogramme' => $cryptogramme,
            'titulaire' => $titulaire,
            'montant' => $commande['montantTotal'],
            'commande' => $commande
        ], $paypal_config);
        
        if ($resultatPaiement['success']) {
            // Paiement réussi
            try {
                // Démarrer une transaction
                $pdo->beginTransaction();
                
                // Mettre à jour le statut de la commande
                $stmt = $pdo->prepare("UPDATE Commande SET statut = 'payee', modeReglement = 'Carte Bancaire (PayPal)' WHERE idCommande = ?");
                $stmt->execute([$idCommande]);
                
                // CORRECTION : Enregistrer le paiement avec les bons noms de colonnes
                $stmt = $pdo->prepare("
                    INSERT INTO Paiement 
                    (idCommande, montant, currency, statut, methode_paiement, reference, date_creation) 
                    VALUES (?, ?, 'EUR', 'payee', 'Carte Bancaire (PayPal)', ?, NOW())
                ");
                $reference = $resultatPaiement['reference'];
                $stmt->execute([$idCommande, $commande['montantTotal'], $reference]);
                
                // Valider la transaction
                $pdo->commit();
                
                // NOUVEAU : Envoyer l'email avec la facture PDF
                $resultatEmail = envoyerFactureAvecEmail($pdo, $commande, $reference);
                
                if (!$resultatEmail) {
                    error_log("⚠️ Paiement réussi mais échec envoi email avec facture");
                }
                
                // Afficher la page de confirmation
                afficherConfirmationCB($commande, $reference);
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $erreurs[] = "Erreur lors de l'enregistrement du paiement: " . $e->getMessage();
            }
        } else {
            $erreurs[] = $resultatPaiement['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Carte Bancaire - Youki and Co</title>
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
            max-width: 500px; 
            background: white; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .header { 
            text-align: center; 
            color: #d40000; 
            margin-bottom: 30px; 
        }
        .form-group { 
            margin-bottom: 20px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: bold; 
            font-size: 14px;
        }
        input, select { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            box-sizing: border-box;
            font-size: 16px;
        }
        .form-row {
            display: flex;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn { 
            background-color: #d40000; 
            color: white; 
            padding: 15px 30px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 16px;
            margin-top: 10px;
        }
        .btn:hover {
            background-color: #b30000;
        }
        .btn:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        .details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .card-icons {
            text-align: center;
            margin: 15px 0;
            font-size: 24px;
        }
        .security-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
            font-size: 14px;
        }
        .paypal-badge {
            text-align: center;
            margin: 10px 0;
        }
        .test-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #155724;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Paiement Sécurisé</h1>
            <p>Finalisez votre commande #<?= $commande['idCommande'] ?></p>
        </div>
        
        <div class="details">
            <p><strong>Montant à payer :</strong> <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> €</p>
        </div>

        <div class="paypal-badge">
            <img src="https://www.paypalobjects.com/webstatic/en_US/i/buttons/cc-badges-ppmcvdam.png" alt="PayPal" style="height: 30px;">
            <p style="font-size: 12px; color: #666; margin: 5px 0 0 0;">Paiement sécurisé par PayPal</p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>Erreurs :</strong>
                <ul style="margin: 10px 0 0 0; padding-left: 20px;">
                    <?php foreach ($erreurs as $erreur): ?>
                        <li><?= htmlspecialchars($erreur) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="security-notice">
            <strong>🔒 Paiement 100% sécurisé par PayPal</strong><br>
            Vos données bancaires sont cryptées et traitées directement par PayPal. Aucune information n'est stockée sur nos serveurs.
        </div>

        <div class="test-info">
            <strong>💡 Mode test activé</strong><br>
            Le paiement est simulé pour les tests. Après validation, vous recevrez un email avec votre facture PDF.
        </div>

        <form id="formPaiementCB" method="POST">
            <input type="hidden" name="action" value="traiter_paiement_cb">
            <input type="hidden" name="id_commande" value="<?= $idCommande ?>">
            
            <div class="form-group">
                <label for="numero_carte">Numéro de carte <span style="color: #d40000;">*</span></label>
                <input type="text" id="numero_carte" name="numero_carte" 
                       placeholder="1234 5678 9012 3456" 
                       maxlength="19"
                       pattern="[0-9\s]{16,19}"
                       required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_expiration">Date d'expiration <span style="color: #d40000;">*</span></label>
                    <input type="text" id="date_expiration" name="date_expiration" 
                           placeholder="MM/AA" 
                           maxlength="5"
                           pattern="(0[1-9]|1[0-2])\/[0-9]{2}"
                           required>
                </div>
                
                <div class="form-group">
                    <label for="cryptogramme">Cryptogramme <span style="color: #d40000;">*</span></label>
                    <input type="text" id="cryptogramme" name="cryptogramme" 
                           placeholder="123" 
                           maxlength="3"
                           pattern="[0-9]{3}"
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="titulaire_carte">Nom du titulaire <span style="color: #d40000;">*</span></label>
                <input type="text" id="titulaire_carte" name="titulaire_carte" 
                       placeholder="M. DUPONT Jean" 
                       value="<?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?>" required>
            </div>
            
            <div class="card-icons">
                💳 • • • • • • • • • • • • • • • •
            </div>
            
            <button type="submit" class="btn" id="btnPayer">
                Payer <?= number_format($commande['montantTotal'], 2, ',', ' ') ?> € avec PayPal
            </button>
            
            <p style="text-align: center; margin-top: 15px; font-size: 12px; color: #666;">
                En cliquant sur "Payer", vous serez redirigé vers le système sécurisé PayPal.
            </p>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="acheter.php?action=paypal_success&commande=<?= $idCommande ?>" style="color: #666; text-decoration: none;">
                ← Retour aux options de paiement
            </a>
        </div>
    </div>

    <script>
        // Formatage automatique du numéro de carte
        document.getElementById('numero_carte').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            let formattedValue = '';
            
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formattedValue += ' ';
                }
                formattedValue += value[i];
            }
            
            e.target.value = formattedValue.substring(0, 19);
        });

        // Formatage automatique de la date d'expiration
        document.getElementById('date_expiration').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                e.target.value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
        });

        // Validation en temps réel
        document.getElementById('formPaiementCB').addEventListener('submit', function(e) {
            const btn = document.getElementById('btnPayer');
            btn.disabled = true;
            btn.innerHTML = 'Traitement en cours avec PayPal...';
        });

        // Empêcher la soumission avec la touche Entrée
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const target = e.target;
                if (target.form && target.type !== 'textarea') {
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>