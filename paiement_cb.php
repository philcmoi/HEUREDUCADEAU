<?php
// ============================================
// PAIEMENT PAR CARTE BANCAIRE VIA PAYPAL - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// Vérification de l'étape
checkPaiementAccess();

// ============================================
// CONNEXION BDD
// ============================================
$pdo = getPDOConnection();
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

// Synchroniser le panier
synchroniserPanierSessionBDD($pdo, session_id());

// ============================================
// CONFIGURATION PAYPAL
// ============================================
define('PAYPAL_CLIENT_ID', 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
define('PAYPAL_CLIENT_SECRET', 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' ou 'live'

// URLs de redirection
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . $host . '/';
$return_url = $base_url . 'paiement-reussi.php';
$cancel_url = $base_url . 'paiement-annule.php';

// ============================================
// FONCTIONS PAYPAL CORRIGÉES
// ============================================

/**
 * Obtient un token d'accès PayPal
 */
function getPayPalAccessToken() {
    $ch = curl_init();
    
    $url = (PAYPAL_MODE === 'sandbox') 
        ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api-m.paypal.com/v1/oauth2/token';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Erreur cURL PayPal token: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    if ($http_code !== 200) {
        error_log("Erreur PayPal token HTTP $http_code: $result");
        return ['error' => "Erreur PayPal: HTTP $http_code"];
    }
    
    $data = json_decode($result, true);
    
    if (!isset($data['access_token'])) {
        return ['error' => 'Token d\'accès non reçu'];
    }
    
    return $data['access_token'];
}

/**
 * Crée une commande PayPal avec paiement par carte
 */
function createPayPalCardOrder($montant, $commande_id, $return_url, $cancel_url) {
    $access_token = getPayPalAccessToken();
    
    if (is_array($access_token) && isset($access_token['error'])) {
        return $access_token;
    }
    
    // Formatage du montant
    $montant_total = number_format(floatval($montant), 2, '.', '');
    
    // Générer un ID de requête unique
    $request_id = uniqid('ORDER_' . $commande_id . '_', true);
    
    // Construction de la requête
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'ORDER_' . $commande_id,
                'description' => 'Commande #' . $commande_id . ' - HEURE DU CADEAU',
                'custom_id' => (string)$commande_id,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => $montant_total
                ]
            ]
        ],
        'application_context' => [
            'brand_name' => 'HEURE DU CADEAU',
            'landing_page' => 'BILLING',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $return_url,
            'cancel_url' => $cancel_url
        ]
    ];
    
    error_log("PayPal Card Order Data: " . json_encode($order_data));
    
    $url = (PAYPAL_MODE === 'sandbox')
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'PayPal-Request-Id: ' . $request_id,
        'Prefer: return=representation'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Erreur cURL création commande PayPal carte: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    error_log("PayPal Card Response (HTTP $http_code): " . $result);
    
    if ($http_code >= 400) {
        $response_data = json_decode($result, true);
        $error_message = $response_data['message'] ?? $response_data['error_description'] ?? 'Erreur inconnue';
        $error_details = $response_data['details'][0]['description'] ?? '';
        
        error_log("Erreur PayPal création commande carte: $error_message - $error_details");
        
        return [
            'error' => "Erreur PayPal: " . $error_message . ($error_details ? " - $error_details" : ""),
            'details' => $response_data,
            'http_code' => $http_code
        ];
    }
    
    return json_decode($result, true);
}

/**
 * Capture un paiement PayPal
 */
function capturePayPalOrder($order_id) {
    $access_token = getPayPalAccessToken();
    
    if (is_array($access_token) && isset($access_token['error'])) {
        return $access_token;
    }
    
    $url = (PAYPAL_MODE === 'sandbox')
        ? "https://api-m.sandbox.paypal.com/v2/checkout/orders/$order_id/capture"
        : "https://api-m.paypal.com/v2/checkout/orders/$order_id/capture";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
        'Prefer: return=representation'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Erreur cURL capture PayPal: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    if ($http_code >= 400) {
        error_log("Erreur capture PayPal HTTP $http_code: " . $result);
        return ['error' => "Erreur capture PayPal: $http_code"];
    }
    
    return json_decode($result, true);
}

// ============================================
// RÉCUPÉRATION DE L'ID COMMANDE
// ============================================

// Récupérer l'ID commande depuis l'URL d'abord
$id_commande = isset($_GET['commande']) && is_numeric($_GET['commande']) ? intval($_GET['commande']) : null;

// Si pas dans l'URL, essayer la session
if (!$id_commande && isset($_SESSION[SESSION_KEY_COMMANDE]['id'])) {
    $id_commande = $_SESSION[SESSION_KEY_COMMANDE]['id'];
}

// Si toujours pas d'ID, essayer de récupérer la dernière commande en attente
if (!$id_commande && isset($_SESSION['client_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_commande 
            FROM commandes 
            WHERE id_client = ? AND statut_paiement = 'en_attente' 
            ORDER BY date_commande DESC 
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['client_id']]);
        $id_commande = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Erreur récupération dernière commande: " . $e->getMessage());
    }
}

// Si toujours pas d'ID, afficher une erreur
if (!$id_commande) {
    error_log("ID commande non trouvé - URL: " . $_SERVER['REQUEST_URI'] . ", Session: " . print_r($_SESSION, true));
    
    // Rediriger vers le panier avec un message
    $_SESSION['messages'][] = [
        'type' => 'error',
        'message' => 'Aucune commande en cours. Veuillez recommencer votre achat.'
    ];
    header('Location: panier.html');
    exit;
}

// ============================================
// RÉCUPÉRATION DES INFORMATIONS DE LA COMMANDE
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_commande,
            c.numero_commande,
            c.total_ttc as montant_total,
            c.statut,
            c.statut_paiement,
            cl.id_client,
            cl.email,
            cl.prenom,
            cl.nom,
            a.adresse as adresse_livraison,
            a.complement,
            a.code_postal,
            a.ville,
            a.pays,
            a.telephone
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        LEFT JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$commande) {
        throw new Exception("Commande #$id_commande non trouvée dans la base de données");
    }
    
    // Vérifier que la commande est bien en attente de paiement
    if ($commande['statut_paiement'] !== 'en_attente') {
        // Si déjà payée, rediriger vers la page de succès
        if ($commande['statut_paiement'] === 'paye') {
            header('Location: paiement-reussi.php?commande=' . $id_commande);
            exit;
        }
        
        // Sinon, permettre le paiement quand même
        error_log("Attention: Commande #$id_commande avec statut_paiement = " . $commande['statut_paiement']);
    }
    
    $total = floatval($commande['montant_total']);
    
} catch (Exception $e) {
    error_log("Erreur récupération commande CB: " . $e->getMessage() . " - ID commande: " . $id_commande);
    
    // Afficher une page d'erreur plus informative
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur - HEURE DU CADEAU</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container { 
                max-width: 600px; 
                width: 100%;
                background: white; 
                padding: 40px; 
                border-radius: 20px; 
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
            }
            h1 { color: #e74c3c; margin-bottom: 20px; }
            .error-icon {
                font-size: 64px;
                color: #e74c3c;
                margin-bottom: 20px;
            }
            .btn { 
                background: #5a67d8; 
                color: white; 
                padding: 15px 30px; 
                border: none; 
                border-radius: 12px; 
                cursor: pointer; 
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
            }
            .btn:hover { background: #4c51bf; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">❌</div>
            <h1>Erreur lors de la récupération de la commande</h1>
            <p>Nous n'avons pas pu trouver votre commande. Voici les détails techniques :</p>
            <p style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left;">
                <strong>Message :</strong> <?= htmlspecialchars($e->getMessage()) ?><br>
                <strong>ID commande :</strong> <?= htmlspecialchars($id_commande ?? 'Non défini') ?>
            </p>
            <p>Veuillez réessayer ou contacter notre service client.</p>
            <a href="panier.html" class="btn">Retour au panier</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// TRAITEMENT DU FORMULAIRE DE PAIEMENT
// ============================================
$erreurs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_paiement_cb') {
    
    $numero_carte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $expiration_mois = $_POST['expiration_mois'] ?? '';
    $expiration_annee = $_POST['expiration_annee'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $titulaire = $_POST['titulaire_carte'] ?? '';
    
    $date_expiration = $expiration_mois . '/' . $expiration_annee;
    
    // Validation des données carte
    if (strlen($numero_carte) < 15 || !ctype_digit($numero_carte)) {
        $erreurs[] = "Numéro de carte invalide";
    }
    
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $expiration_mois)) {
        $erreurs[] = "Mois d'expiration invalide";
    }
    
    if (!preg_match('/^[0-9]{2}$/', $expiration_annee)) {
        $erreurs[] = "Année d'expiration invalide";
    }
    
    if (strlen($cryptogramme) < 3 || !ctype_digit($cryptogramme)) {
        $erreurs[] = "Cryptogramme invalide";
    }
    
    if (empty($titulaire)) {
        $erreurs[] = "Nom du titulaire requis";
    }
    
    // Si pas d'erreurs, procéder au paiement via PayPal
    if (empty($erreurs)) {
        
        // Sauvegarder l'ID commande en session pour le retour
        $_SESSION[SESSION_KEY_COMMANDE]['id'] = $id_commande;
        
        // Créer la commande PayPal
        $paypal_order = createPayPalCardOrder(
            $total,
            $id_commande,
            $return_url . '?commande=' . $id_commande,
            $cancel_url . '?commande=' . $id_commande
        );
        
        if (isset($paypal_order['error'])) {
            $erreurs[] = "Erreur PayPal: " . $paypal_order['error'];
            error_log("Erreur PayPal Card: " . json_encode($paypal_order));
        } else {
            // Rediriger vers l'URL d'approbation PayPal
            $approval_url = null;
            foreach ($paypal_order['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    $approval_url = $link['href'];
                    break;
                }
            }
            
            if ($approval_url) {
                // Sauvegarder l'ID PayPal pour le retour
                $_SESSION['paypal_order_id'] = $paypal_order['id'];
                
                // Rediriger vers PayPal pour la saisie de la carte
                header('Location: ' . $approval_url);
                exit;
            } else {
                $erreurs[] = "URL d'approbation PayPal non trouvée";
            }
        }
    }
}

// ============================================
// AFFICHAGE HTML - FORMULAIRE SIMPLIFIÉ
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement par Carte Bancaire - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { 
            max-width: 600px; 
            width: 100%;
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 { 
            color: #2d3748; 
            margin-bottom: 30px; 
            text-align: center;
            font-size: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h1 i { color: #5a67d8; }
        .badge {
            background: #5a67d8;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        .details { 
            background: #f7fafc; 
            padding: 25px; 
            border-radius: 15px; 
            margin-bottom: 30px;
            border-left: 5px solid #5a67d8;
        }
        .details p {
            margin: 8px 0;
            color: #4a5568;
        }
        .montant {
            font-size: 28px;
            color: #5a67d8;
            font-weight: 800;
            margin: 10px 0;
        }
        .form-group { 
            margin-bottom: 25px; 
        }
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 600;
            color: #4a5568;
        }
        input, select { 
            width: 100%; 
            padding: 14px 18px; 
            border: 2px solid #e2e8f0; 
            border-radius: 12px; 
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }
        .form-row { 
            display: flex; 
            gap: 15px; 
            margin-bottom: 0;
        }
        .form-row .form-group { 
            flex: 1; 
            margin-bottom: 0;
        }
        .card-icons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 32px;
            color: #718096;
        }
        .card-icons i {
            transition: all 0.3s ease;
        }
        .btn { 
            background: linear-gradient(135deg, #5a67d8, #4c51bf);
            color: white; 
            padding: 16px 30px; 
            border: none; 
            border-radius: 12px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(90,103,216,0.4);
        }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .error { 
            color: #c53030; 
            margin-bottom: 25px; 
            padding: 15px; 
            background: #fff5f5; 
            border-radius: 12px;
            border-left: 5px solid #c53030;
        }
        .error p {
            margin: 5px 0;
        }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #edf2f7;
            color: #718096;
            font-size: 14px;
        }
        .secure-badge i {
            color: #38a169;
        }
        .paypal-note {
            background: #ebf8ff;
            border-left: 5px solid #3182ce;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 14px;
            color: #2c5282;
        }
        .paypal-note i {
            color: #3182ce;
            margin-right: 10px;
        }
        .expiry-select {
            display: flex;
            gap: 10px;
        }
        .expiry-select select {
            flex: 1;
        }
        .commande-numero {
            font-size: 16px;
            color: #4a5568;
            margin-bottom: 5px;
        }
        @media (max-width: 768px) {
            .container { padding: 25px; }
            .form-row { flex-direction: column; gap: 0; }
            .form-row .form-group { margin-bottom: 25px; }
            .form-row .form-group:last-child { margin-bottom: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-credit-card"></i> 
            Paiement sécurisé
            <span class="badge">via PayPal</span>
        </h1>
        
        <div class="paypal-note">
            <i class="fab fa-paypal"></i>
            <strong>Paiement par carte traité via PayPal</strong> - 
            Après avoir saisi vos informations, vous serez redirigé vers 
            PayPal pour finaliser le paiement en toute sécurité.
        </div>
        
        <div class="details">
            <p class="commande-numero">
                <strong><i class="fas fa-file-invoice"></i> Commande #<?= htmlspecialchars($commande['numero_commande'] ?? $id_commande) ?></strong>
            </p>
            <p><i class="fas fa-user"></i> <?= htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) ?></p>
            <?php if (!empty($commande['adresse_livraison'])): ?>
            <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($commande['adresse_livraison']) ?><?= !empty($commande['complement']) ? ', ' . htmlspecialchars($commande['complement']) : '' ?>, <?= htmlspecialchars($commande['code_postal'] ?? '') ?> <?= htmlspecialchars($commande['ville'] ?? '') ?></p>
            <?php endif; ?>
            <div class="montant">
                <?= number_format($commande['montant_total'] ?? $total, 2, ',', ' ') ?> €
            </div>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="error">
                <?php foreach ($erreurs as $erreur): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="paymentForm" autocomplete="off">
            <input type="hidden" name="action" value="traiter_paiement_cb">
            <input type="hidden" name="id_commande" value="<?= htmlspecialchars($id_commande) ?>">
            
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Numéro de carte</label>
                <input type="text" name="numero_carte" id="numero_carte" 
                       placeholder="1234 5678 9012 3456" maxlength="19" required 
                       autocomplete="off" inputmode="numeric">
                <div class="card-icons">
                    <i class="fab fa-cc-visa" id="icon-visa"></i>
                    <i class="fab fa-cc-mastercard" id="icon-mastercard"></i>
                    <i class="fab fa-cc-amex" id="icon-amex"></i>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date d'expiration</label>
                    <div class="expiry-select">
                        <select name="expiration_mois" id="expiration_mois" required>
                            <option value="">Mois</option>
                            <option value="01">01 - Janvier</option>
                            <option value="02">02 - Février</option>
                            <option value="03">03 - Mars</option>
                            <option value="04">04 - Avril</option>
                            <option value="05">05 - Mai</option>
                            <option value="06">06 - Juin</option>
                            <option value="07">07 - Juillet</option>
                            <option value="08">08 - Août</option>
                            <option value="09">09 - Septembre</option>
                            <option value="10">10 - Octobre</option>
                            <option value="11">11 - Novembre</option>
                            <option value="12">12 - Décembre</option>
                        </select>
                        <select name="expiration_annee" id="expiration_annee" required>
                            <option value="">Année</option>
                            <?php
                            $currentYear = date('Y');
                            for ($i = 0; $i < 10; $i++) {
                                $year = $currentYear + $i;
                                $yearShort = substr($year, -2);
                                echo "<option value=\"$yearShort\">$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Cryptogramme (CVV)</label>
                    <input type="text" name="cryptogramme" id="cryptogramme" 
                           placeholder="123" maxlength="3" required 
                           autocomplete="off" inputmode="numeric">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nom du titulaire</label>
                <input type="text" name="titulaire_carte" id="titulaire_carte" 
                       value="<?= htmlspecialchars(trim(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? ''))) ?>" required 
                       autocomplete="off">
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                <i class="fab fa-paypal"></i>
                Payer <?= number_format($commande['montant_total'] ?? $total, 2, ',', ' ') ?> €
            </button>
        </form>
        
        <div class="secure-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Paiement 100% sécurisé - Traité par PayPal</span>
            <i class="fas fa-lock"></i>
        </div>
    </div>

    <script>
        (function() {
            'use strict';
            
            // Éléments du DOM
            const numeroCarte = document.getElementById('numero_carte');
            const expirationMois = document.getElementById('expiration_mois');
            const expirationAnnee = document.getElementById('expiration_annee');
            const cryptogramme = document.getElementById('cryptogramme');
            const submitBtn = document.getElementById('submitBtn');
            const paymentForm = document.getElementById('paymentForm');
            
            // Icônes des cartes
            const iconVisa = document.getElementById('icon-visa');
            const iconMastercard = document.getElementById('icon-mastercard');
            const iconAmex = document.getElementById('icon-amex');

            // Formatage du numéro de carte
            if (numeroCarte) {
                numeroCarte.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
                    if (value.length > 16) value = value.substr(0, 16);
                    
                    let formatted = '';
                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) formatted += ' ';
                        formatted += value[i];
                    }
                    e.target.value = formatted;
                    
                    // Détection du type de carte
                    detectCardType(value);
                });
            }

            // Détection du type de carte
            function detectCardType(cardNumber) {
                // Reset des couleurs
                if (iconVisa) iconVisa.style.color = '#718096';
                if (iconMastercard) iconMastercard.style.color = '#718096';
                if (iconAmex) iconAmex.style.color = '#718096';
                
                if (cardNumber.startsWith('4')) {
                    if (iconVisa) iconVisa.style.color = '#1434cb';
                } else if (cardNumber.startsWith('5')) {
                    if (iconMastercard) iconMastercard.style.color = '#eb001b';
                } else if (cardNumber.startsWith('3')) {
                    if (iconAmex) iconAmex.style.color = '#2e77bc';
                }
            }

            // Formatage du cryptogramme
            if (cryptogramme) {
                cryptogramme.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').substr(0, 3);
                });
            }

            // Validation du formulaire avant soumission
            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    // Vérifier que tous les champs sont remplis
                    if (!numeroCarte || !numeroCarte.value.trim()) {
                        e.preventDefault();
                        alert('Veuillez saisir le numéro de carte');
                        return false;
                    }
                    
                    if (!expirationMois || !expirationMois.value) {
                        e.preventDefault();
                        alert('Veuillez sélectionner le mois d\'expiration');
                        return false;
                    }
                    
                    if (!expirationAnnee || !expirationAnnee.value) {
                        e.preventDefault();
                        alert('Veuillez sélectionner l\'année d\'expiration');
                        return false;
                    }
                    
                    if (!cryptogramme || !cryptogramme.value.trim()) {
                        e.preventDefault();
                        alert('Veuillez saisir le cryptogramme (CVV)');
                        return false;
                    }
                    
                    if (!document.getElementById('titulaire_carte') || !document.getElementById('titulaire_carte').value.trim()) {
                        e.preventDefault();
                        alert('Veuillez saisir le nom du titulaire');
                        return false;
                    }
                    
                    // Désactiver le bouton pour éviter la double soumission
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirection vers PayPal...';
                    }
                    
                    return true;
                });
            }
        })();
    </script>
</body>
</html>