<?php
// ============================================
// REDIRECTION VERS LE PRESTATAIRE DE PAIEMENT
// VERSION CORRIGÉE AVEC GESTION D'ERREUR PAYPAL
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/config.php';

// Vérifier l'accès
checkPaiementAccess();

// Récupérer les paramètres
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$montant = isset($_GET['montant']) ? floatval($_GET['montant']) : 0;
$method = isset($_GET['method']) ? $_GET['method'] : '';

if ($commande_id <= 0 || $montant <= 0 || !in_array($method, ['paypal', 'carte'])) {
    addSessionMessage('Paramètres de commande invalides', 'error');
    header('Location: paiement.php');
    exit;
}

// Connexion BDD
$pdo = getPDOConnection();
if (!$pdo) {
    addSessionMessage('Erreur de connexion', 'error');
    header('Location: paiement.php');
    exit;
}

// Vérifier que la commande existe et est en attente
$stmt = $pdo->prepare("SELECT id_commande, statut_paiement FROM commandes WHERE id_commande = ?");
$stmt->execute([$commande_id]);
$commande = $stmt->fetch();

if (!$commande) {
    addSessionMessage('Commande introuvable', 'error');
    header('Location: paiement.php');
    exit;
}

if ($commande['statut_paiement'] === 'paye') {
    // Déjà payée, rediriger vers la page de succès
    header("Location: paiement-reussi.php?commande=$commande_id");
    exit;
}

// Sauvegarder en session pour le retour
$_SESSION['paypal_commande_id'] = $commande_id;

if ($method === 'paypal') {
    // --- REDIRECTION VERS PAYPAL ---
    // Configuration PayPal
    $paypal_client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_';
    $paypal_secret = defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S';
    
    // URLs
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . $host . '/';
    $return_url = $base_url . 'paiement_paypal.php';
    $cancel_url = $base_url . 'paiement-annule.php';
    
    // ============================================
    // OBTENTION DU TOKEN D'ACCÈS PAYPAL AVEC GESTION D'ERREUR
    // ============================================
    
    error_log("=== TENTATIVE D'OBTENTION TOKEN PAYPAL ===");
    error_log("Client ID: " . substr($paypal_client_id, 0, 10) . "...");
    error_log("Secret: " . substr($paypal_secret, 0, 5) . "...");
    
    // Initialisation cURL
    $ch = curl_init();
    
    // Configuration cURL avec options de débogage
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api-m.sandbox.paypal.com/v1/oauth2/token",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false, // Désactiver en production (utiliser true avec certificats valides)
        CURLOPT_SSL_VERIFYHOST => false, // Désactiver en production
        CURLOPT_POST => true,
        CURLOPT_USERPWD => $paypal_client_id . ":" . $paypal_secret,
        CURLOPT_POSTFIELDS => "grant_type=client_credentials",
        CURLOPT_TIMEOUT => 30,
        CURLOPT_VERBOSE => true,
        CURLOPT_HEADER => true // Pour capturer les en-têtes de réponse
    ]);
    
    // Capture des logs verbose
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Exécution de la requête
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    
    // Récupération de la taille de l'en-tête pour séparer corps et en-têtes
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    // Récupération des logs verbose
    rewind($verbose);
    $verbose_log = stream_get_contents($verbose);
    
    curl_close($ch);
    
    // Journalisation des résultats
    error_log("cURL Error No: " . $curl_errno);
    error_log("cURL Error: " . ($curl_error ?: 'Aucune erreur cURL'));
    error_log("HTTP Code: " . $http_code);
    error_log("En-têtes réponse: " . $headers);
    error_log("Logs verbose: " . $verbose_log);
    
    // Vérification des erreurs cURL
    if ($curl_errno) {
        $error_message = "Erreur de connexion PayPal (Code $curl_errno): $curl_error";
        error_log($error_message);
        
        // Afficher une page d'erreur conviviale
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur de connexion PayPal</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                .error-container { max-width: 600px; width: 100%; }
                .error-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; }
                .error-icon { font-size: 80px; color: #e74c3c; margin-bottom: 20px; }
                h1 { color: #2c3e50; margin-bottom: 20px; }
                p { color: #7f8c8d; margin-bottom: 30px; line-height: 1.6; }
                .btn { display: inline-block; padding: 15px 30px; background: #003087; color: white; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 10px; transition: all 0.3s; }
                .btn:hover { background: #002060; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,48,135,0.3); }
                .btn-secondary { background: #95a5a6; }
                .btn-secondary:hover { background: #7f8c8d; }
                .details { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 20px; text-align: left; font-size: 14px; border-left: 4px solid #e74c3c; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-card">
                    <div class="error-icon">⚠️</div>
                    <h1>Problème de connexion PayPal</h1>
                    <p>Nous n'avons pas pu établir de connexion sécurisée avec PayPal.<br>Veuillez réessayer ou choisir un autre mode de paiement.</p>
                    <div>
                        <a href="paiement.php" class="btn btn-secondary">← Retour au paiement</a>
                        <a href="paiement_cb.php?commande=<?= $commande_id ?>&montant=<?= $montant ?>" class="btn">Payer par carte</a>
                    </div>
                    <div class="details">
                        <strong>Détail technique :</strong> <?= htmlspecialchars($error_message) ?><br>
                        <small>Si l'erreur persiste, contactez le support.</small>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Vérification du code HTTP
    if ($http_code !== 200) {
        $error_message = "PayPal a répondu avec le code HTTP $http_code";
        error_log($error_message);
        error_log("Corps de la réponse: " . $body);
        
        $response_data = json_decode($body, true);
        $paypal_error = $response_data['error_description'] ?? $response_data['error'] ?? 'Erreur inconnue';
        
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Erreur PayPal</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
                .error-container { max-width: 600px; width: 100%; }
                .error-card { background: white; border-radius: 20px; padding: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); text-align: center; }
                .error-icon { font-size: 80px; color: #e74c3c; margin-bottom: 20px; }
                h1 { color: #2c3e50; margin-bottom: 20px; }
                p { color: #7f8c8d; margin-bottom: 30px; line-height: 1.6; }
                .btn { display: inline-block; padding: 15px 30px; background: #003087; color: white; text-decoration: none; border-radius: 50px; font-weight: 600; margin: 10px; transition: all 0.3s; }
                .btn:hover { background: #002060; transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,48,135,0.3); }
                .btn-secondary { background: #95a5a6; }
                .btn-secondary:hover { background: #7f8c8d; }
                .details { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 20px; text-align: left; font-size: 14px; border-left: 4px solid #e74c3c; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-card">
                    <div class="error-icon">❌</div>
                    <h1>Erreur de réponse PayPal</h1>
                    <p>PayPal a retourné une erreur lors de la création de la session de paiement.</p>
                    <div>
                        <a href="paiement.php" class="btn btn-secondary">← Retour au paiement</a>
                        <a href="paiement_cb.php?commande=<?= $commande_id ?>&montant=<?= $montant ?>" class="btn">Payer par carte</a>
                    </div>
                    <div class="details">
                        <strong>Message PayPal :</strong> <?= htmlspecialchars($paypal_error) ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Décodage de la réponse
    $token_data = json_decode($body, true);
    
    if (!$token_data || !isset($token_data['access_token'])) {
        error_log("Réponse PayPal invalide: " . $body);
        die("Réponse PayPal invalide. Veuillez réessayer.");
    }
    
    $access_token = $token_data['access_token'];
    error_log("✅ Token obtenu avec succès: " . substr($access_token, 0, 20) . "...");
    
    // ============================================
    // CRÉATION DE LA COMMANDE PAYPAL
    // ============================================
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api-m.sandbox.paypal.com/v2/checkout/orders",
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
            'PayPal-Request-Id: ' . uniqid('order_' . $commande_id . '_'),
            'Prefer: return=representation'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => 'commande_' . $commande_id,
                'description' => 'Commande #' . $commande_id . ' - HEURE DU CADEAU',
                'custom_id' => (string)$commande_id,
                'invoice_id' => 'INV-' . date('Ymd') . '-' . $commande_id,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format($montant, 2, '.', '')
                ]
            ]],
            'application_context' => [
                'brand_name' => 'HEURE DU CADEAU',
                'landing_page' => 'LOGIN',
                'user_action' => 'PAY_NOW',
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
                'shipping_preference' => 'SET_PROVIDED_ADDRESS'
            ]
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    // Séparer en-têtes et corps
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    curl_close($ch);
    
    error_log("Création commande PayPal - HTTP $http_code");
    error_log("Corps réponse: " . $body);
    
    if ($curl_error) {
        error_log("Erreur cURL création commande: " . $curl_error);
        die("Erreur de communication avec PayPal lors de la création de la commande.");
    }
    
    if ($http_code >= 400) {
        error_log("Erreur création commande PayPal: HTTP $http_code");
        $error_data = json_decode($body, true);
        $error_message = $error_data['message'] ?? $error_data['error_description'] ?? 'Erreur inconnue';
        die("Erreur PayPal: " . htmlspecialchars($error_message));
    }
    
    $paypal_order = json_decode($body, true);
    
    if (!$paypal_order || !isset($paypal_order['id'])) {
        error_log("Réponse commande PayPal invalide: " . $body);
        die("Réponse PayPal invalide lors de la création de la commande.");
    }
    
    // Sauvegarder l'ID PayPal en session
    $_SESSION['paypal_order_id'] = $paypal_order['id'];
    
    // Rediriger vers PayPal
    $base_paypal_url = 'https://www.sandbox.paypal.com/checkoutnow';
    $redirect_url = $base_paypal_url . '?token=' . $paypal_order['id'];
    
    // Redirection avec message de chargement
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Redirection vers PayPal</title>
        <style>
            body {
                font-family: 'Segoe UI', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 500px;
                width: 100%;
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            .loader {
                border: 5px solid #f3f3f3;
                border-top: 5px solid #003087;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            h1 { color: #2c3e50; margin-bottom: 20px; }
            .montant {
                font-size: 28px;
                font-weight: bold;
                color: #003087;
                margin: 20px 0;
                padding: 15px;
                background: #f0f7ff;
                border-radius: 10px;
            }
            p { color: #7f8c8d; margin: 10px 0; }
        </style>
        <meta http-equiv="refresh" content="3;url=<?= $redirect_url ?>">
    </head>
    <body>
        <div class="container">
            <h1>🔒 Redirection vers PayPal</h1>
            <div class="loader"></div>
            <div class="montant">
                <?= number_format($montant, 2, ',', ' ') ?> €
            </div>
            <p>Commande #<?= $commande_id ?></p>
            <p>Vous allez être redirigé vers PayPal dans quelques secondes...</p>
            <p><small>Si la redirection ne fonctionne pas, <a href="<?= $redirect_url ?>">cliquez ici</a>.</small></p>
        </div>
    </body>
    </html>
    <?php
    exit;
    
} elseif ($method === 'carte') {
    // --- REDIRECTION VERS LE FORMULAIRE DE CARTE ---
    header('Location: paiement_cb.php?commande=' . $commande_id . '&montant=' . $montant);
    exit;
}

?>