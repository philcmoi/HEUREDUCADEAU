<?php
// ============================================
// PAIEMENT PAYPAL - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// Configuration PayPal
define('PAYPAL_CLIENT_ID', 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
define('PAYPAL_CLIENT_SECRET', 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
define('PAYPAL_MODE', 'sandbox');

// Vérifications
if (!hasShippingAddress()) {
    $_SESSION['messages'][] = ['type' => 'error', 'message' => 'Veuillez d\'abord renseigner votre adresse de livraison.'];
    header('Location: livraison_form.php');
    exit;
}

if (!hasValidCart()) {
    $_SESSION['messages'][] = ['type' => 'error', 'message' => 'Votre panier est vide.'];
    header('Location: panier.html');
    exit;
}

// Connexion BDD
$pdo = getPDOConnection();
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

synchroniserPanierSessionBDD($pdo, session_id());

// ============================================
// FONCTIONS PAYPAL API
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
        return ['error' => "Erreur PayPal: $http_code"];
    }
    
    $data = json_decode($result, true);
    
    if (!isset($data['access_token'])) {
        return ['error' => 'Token d\'accès non reçu'];
    }
    
    return $data['access_token'];
}

/**
 * Crée une commande PayPal
 */
function createPayPalOrder($commande_id, $montant, $items_data, $return_url, $cancel_url) {
    $access_token = getPayPalAccessToken();
    
    if (is_array($access_token) && isset($access_token['error'])) {
        return $access_token;
    }
    
    $montant_total = number_format(floatval($montant), 2, '.', '');
    
    // Construire les items
    $items = [];
    $total_items = 0;
    
    foreach ($items_data as $item) {
        $prix_unitaire = number_format(floatval($item['prix_unitaire_ttc']), 2, '.', '');
        $quantite = intval($item['quantite']);
        $total_items += $prix_unitaire * $quantite;
        
        $items[] = [
            'name' => mb_substr($item['nom'], 0, 120),
            'sku' => $item['reference'] ?? ('SKU' . $item['id_produit']),
            'unit_amount' => [
                'currency_code' => 'EUR',
                'value' => $prix_unitaire
            ],
            'quantity' => $quantite,
            'category' => 'PHYSICAL_GOODS'
        ];
    }
    
    $total_items = number_format($total_items, 2, '.', '');
    
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'ORDER_' . $commande_id,
                'description' => 'Commande #' . $commande_id . ' - HEURE DU CADEAU',
                'custom_id' => (string)$commande_id,
                'invoice_id' => 'INV-' . date('Ymd') . '-' . $commande_id,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => $montant_total,
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => 'EUR',
                            'value' => $total_items
                        ]
                    ]
                ],
                'items' => $items
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
    
    $url = (PAYPAL_MODE === 'sandbox')
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token,
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
        error_log("Erreur cURL création commande PayPal: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    if ($http_code >= 400) {
        error_log("Erreur PayPal création commande HTTP $http_code: " . $result);
        return ['error' => "Erreur PayPal: $http_code", 'details' => json_decode($result, true)];
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
        return ['error' => "Erreur capture PayPal: $http_code", 'details' => json_decode($result, true)];
    }
    
    return json_decode($result, true);
}

// ============================================
// TRAITEMENT RETOUR PAYPAL
// ============================================
if (isset($_GET['token']) && isset($_GET['PayerID']) && isset($_GET['paymentId'])) {
    
    $paypal_order_id = $_GET['token'];
    $payer_id = $_GET['PayerID'];
    $payment_id = $_GET['paymentId'];
    
    error_log("Retour PayPal - OrderID: $paypal_order_id, PayerID: $payer_id, PaymentID: $payment_id");
    
    // Récupérer l'ID commande depuis la session
    $commande_id = $_SESSION[SESSION_KEY_COMMANDE]['id'] ?? null;
    
    if (!$commande_id) {
        error_log("ID commande non trouvé dans la session");
        die("ID commande non trouvé");
    }
    
    // Capturer le paiement
    $capture_result = capturePayPalOrder($paypal_order_id);
    
    if (isset($capture_result['error'])) {
        error_log("Erreur capture: " . json_encode($capture_result));
        die("Erreur lors de la capture du paiement: " . $capture_result['error']);
    }
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier que la commande existe
        $stmt_check = $pdo->prepare("SELECT id_commande, id_client, total_ttc FROM commandes WHERE id_commande = ?");
        $stmt_check->execute([$commande_id]);
        $commande = $stmt_check->fetch();
        
        if (!$commande) {
            throw new Exception("Commande non trouvée: $commande_id");
        }
        
        // Extraire les informations PayPal
        $paypal_email = $capture_result['payer']['email_address'] ?? null;
        $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        $montant_paye = $capture_result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;
        
        // Mettre à jour la commande
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'confirmee',
                statut_paiement = 'paye',
                reference_paiement = ?,
                reference_paypal = ?,
                payer_id = ?,
                email_paypal = ?,
                capture_id = ?,
                date_paiement = NOW()
            WHERE id_commande = ?
        ");
        $stmt->execute([
            $payment_id,
            $paypal_order_id,
            $payer_id,
            $paypal_email,
            $capture_id,
            $commande_id
        ]);
        
        // Créer la transaction
        $numero_transaction = 'PP_' . date('Ymd') . '_' . uniqid();
        $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt_trans = $pdo->prepare("
            INSERT INTO transactions 
            (numero_transaction, id_commande, id_client, montant, methode_paiement,
             reference_paiement, statut, date_creation, ip_client, details) 
            VALUES (?, ?, ?, ?, 'paypal', ?, 'paye', NOW(), ?, ?)
        ");
        
        $details_json = json_encode([
            'paypal_order_id' => $paypal_order_id,
            'payment_id' => $payment_id,
            'payer_id' => $payer_id,
            'capture_id' => $capture_id,
            'payer_email' => $paypal_email,
            'full_response' => $capture_result
        ]);
        
        $stmt_trans->execute([
            $numero_transaction,
            $commande_id,
            $commande['id_client'],
            $montant_paye,
            $payment_id,
            $ip_client,
            $details_json
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
        
        $pdo->commit();
        
        // Vider le panier et la session checkout
        cleanUserSession();
        
        header('Location: paiement-reussi.php?commande=' . $commande_id . '&paypal_order=' . $paypal_order_id);
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur paiement PayPal: " . $e->getMessage());
        die("Erreur lors du paiement : " . $e->getMessage());
    }
}

// ============================================
// CRÉATION DE LA COMMANDE (UNE SEULE FOIS)
// ============================================

// Vérifier si on a déjà une commande PayPal en cours
if (isset($_SESSION['paypal_processing']) && $_SESSION['paypal_processing'] === true) {
    // Afficher la page d'attente
    afficherPageAttente();
    exit;
}

// Marquer qu'on est en train de traiter PayPal
$_SESSION['paypal_processing'] = true;

try {
    $pdo->beginTransaction();
    
    $checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
    $client_id = $checkout['client_id'] ?? null;
    $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
    
    // ========== CRÉATION DU CLIENT TEMPORAIRE SI NÉCESSAIRE ==========
    if (!$client_id) {
        $adresse = $checkout['adresse_livraison'] ?? [];
        $email = $adresse['email'] ?? 'temp_' . uniqid() . '@temp.com';
        $nom = $adresse['nom'] ?? 'Client';
        $prenom = $adresse['prenom'] ?? 'Temporaire';
        
        $stmt_client = $pdo->prepare("
            INSERT INTO clients (email, nom, prenom, is_temporary, date_inscription, statut, newsletter)
            VALUES (?, ?, ?, 1, NOW(), 'actif', 1)
        ");
        $stmt_client->execute([$email, $nom, $prenom]);
        $client_id = $pdo->lastInsertId();
        
        if (!$client_id) {
            throw new Exception("Impossible de créer le client temporaire");
        }
        
        // Créer l'adresse associée
        if (!empty($adresse)) {
            $stmt_addr = $pdo->prepare("
                INSERT INTO adresses 
                (id_client, nom, prenom, adresse, complement, code_postal, ville, pays, telephone, principale, type_adresse)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'livraison')
            ");
            $stmt_addr->execute([
                $client_id,
                $adresse['nom'] ?? '',
                $adresse['prenom'] ?? '',
                $adresse['adresse'] ?? '',
                $adresse['complement'] ?? null,
                $adresse['code_postal'] ?? '',
                $adresse['ville'] ?? '',
                $adresse['pays'] ?? 'France',
                $adresse['telephone'] ?? null
            ]);
            
            $adresse_livraison_id = $pdo->lastInsertId();
        }
    }
    
    // Vérifications
    if (!$client_id) {
        throw new Exception("Client ID manquant");
    }
    if (!$adresse_livraison_id) {
        throw new Exception("Adresse de livraison ID manquante");
    }
    
    // ========== PRÉPARATION DES DONNÉES ==========
    $sous_total = 0;
    $items_data = [];
    
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $produit = getProductDetails($item['id_produit'], $pdo);
        if (!$produit) {
            throw new Exception("Produit ID " . $item['id_produit'] . " introuvable");
        }
        
        $prix_unitaire = floatval($produit['prix_ttc'] ?? 0);
        $quantite = intval($item['quantite'] ?? 1);
        
        if (($produit['quantite_stock'] ?? 0) < $quantite) {
            throw new Exception("Stock insuffisant pour: " . ($produit['nom'] ?? ''));
        }
        
        $sous_total += $prix_unitaire * $quantite;
        
        $items_data[] = [
            'id_produit' => $item['id_produit'],
            'reference' => $produit['reference'] ?? 'REF' . $item['id_produit'],
            'nom' => $produit['nom'] ?? 'Produit',
            'quantite' => $quantite,
            'prix_unitaire_ttc' => $prix_unitaire,
            'prix_unitaire_ht' => round($prix_unitaire / 1.2, 2),
            'tva' => 20.00
        ];
    }
    
    if (empty($items_data)) {
        throw new Exception("Aucun article dans le panier");
    }
    
    // Frais de livraison
    $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
    $frais_livraison = 0;
    
    if ($sous_total < 50) {
        if ($mode_livraison === 'express') {
            $frais_livraison = 9.90;
        } elseif ($mode_livraison === 'relais') {
            $frais_livraison = 4.90;
        } else {
            $frais_livraison = 4.90;
        }
    }
    
    $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
    $total = round($sous_total + $frais_livraison + $frais_emballage, 2);
    
    // ========== INSERTION DE LA COMMANDE ==========
    $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_livraison_id;
    
    $stmt = $pdo->prepare("
        INSERT INTO commandes (
            id_client, 
            id_adresse_livraison, 
            id_adresse_facturation,
            sous_total, 
            frais_livraison, 
            total_ttc, 
            statut, 
            statut_paiement,
            mode_paiement, 
            date_commande, 
            client_type,
            instructions
        ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', 'paypal', NOW(), ?, ?)
    ");
    
    $client_type = ($checkout['is_guest'] ?? false) ? 'guest' : 'registered';
    $instructions = $checkout['instructions'] ?? null;
    
    $stmt->execute([
        $client_id,
        $adresse_livraison_id,
        $adresse_facturation_id,
        round($sous_total, 2),
        round($frais_livraison + $frais_emballage, 2),
        $total,
        $client_type,
        $instructions
    ]);
    
    $id_commande = $pdo->lastInsertId();
    
    if (!$id_commande || $id_commande == 0) {
        throw new Exception("ID commande non généré");
    }
    
    // ========== INSERTION DES ARTICLES ==========
    $stmt_item = $pdo->prepare("
        INSERT INTO commande_items (
            id_commande, id_produit, reference_produit, nom_produit,
            quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($items_data as $item) {
        $stmt_item->execute([
            $id_commande,
            $item['id_produit'],
            $item['reference'],
            $item['nom'],
            $item['quantite'],
            $item['prix_unitaire_ht'],
            $item['prix_unitaire_ttc'],
            $item['tva']
        ]);
    }
    
    // ========== MISE À JOUR DU PANIER ==========
    if (isset($_SESSION[SESSION_KEY_PANIER_ID]) && is_numeric($_SESSION[SESSION_KEY_PANIER_ID])) {
        $stmt_panier = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
        $stmt_panier->execute([$_SESSION[SESSION_KEY_PANIER_ID]]);
    }
    
    // Récupérer le numéro de commande
    $stmt_num = $pdo->prepare("SELECT numero_commande FROM commandes WHERE id_commande = ?");
    $stmt_num->execute([$id_commande]);
    $commande_numero = $stmt_num->fetchColumn();
    
    $pdo->commit();
    
    $_SESSION[SESSION_KEY_COMMANDE] = [
        'id' => $id_commande,
        'numero' => $commande_numero,
        'montant' => $total,
        'items' => $items_data
    ];
    
    error_log("Commande PayPal créée: ID $id_commande, N° $commande_numero, Montant: $total €");
    
    // ========== CRÉATION DE LA COMMANDE PAYPAL ==========
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $return_url = $protocol . $host . '/paiement_paypal.php';
    $cancel_url = $protocol . $host . '/paiement-annule.php';
    
    $paypal_order = createPayPalOrder(
        $id_commande,
        $total,
        $items_data,
        $return_url,
        $cancel_url
    );
    
    if (isset($paypal_order['error'])) {
        throw new Exception("Erreur création commande PayPal: " . $paypal_order['error']);
    }
    
    // Trouver l'URL d'approbation
    $approval_url = null;
    foreach ($paypal_order['links'] as $link) {
        if ($link['rel'] === 'approve') {
            $approval_url = $link['href'];
            break;
        }
    }
    
    if (!$approval_url) {
        throw new Exception("URL d'approbation PayPal non trouvée");
    }
    
    // Sauvegarder l'ID PayPal
    $_SESSION['paypal_order_id'] = $paypal_order['id'];
    
    // Rediriger vers PayPal
    header('Location: ' . $approval_url);
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Nettoyer le flag de traitement
    unset($_SESSION['paypal_processing']);
    
    error_log("ERREUR CRITIQUE création commande PayPal: " . $e->getMessage());
    
    if (ini_get('display_errors')) {
        die("Erreur lors de la création de la commande : " . $e->getMessage());
    } else {
        die("Une erreur est survenue. Veuillez réessayer ou contacter le support.");
    }
}

// Fonction pour afficher la page d'attente
function afficherPageAttente() {
    $commande = $_SESSION[SESSION_KEY_COMMANDE] ?? [];
    $total = $commande['montant'] ?? 0;
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Redirection PayPal - HEURE DU CADEAU</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Segoe UI', sans-serif; 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                padding: 20px;
                display: flex; 
                justify-content: center; 
                align-items: center; 
                min-height: 100vh;
            }
            .container { 
                background: white; 
                padding: 50px 40px; 
                border-radius: 20px; 
                text-align: center; 
                max-width: 500px;
                width: 100%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.5s ease;
            }
            @keyframes slideUp {
                from { opacity: 0; transform: translateY(30px); }
                to { opacity: 1; transform: translateY(0); }
            }
            h1 { color: #003087; margin-bottom: 20px; }
            .paypal-logo { margin: 20px 0; }
            .paypal-logo i { font-size: 60px; color: #003087; }
            .commande-info {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 12px;
                margin: 25px 0;
                border-left: 4px solid #003087;
                text-align: left;
            }
            .montant { 
                font-size: 36px; 
                color: #003087; 
                margin: 15px 0;
                font-weight: bold;
            }
            .btn { 
                background: #003087; 
                color: white; 
                padding: 18px 40px; 
                border: none; 
                border-radius: 50px; 
                font-size: 20px; 
                font-weight: 600;
                cursor: pointer; 
                width: 100%;
                transition: all 0.3s ease;
                margin: 20px 0;
                display: inline-block;
            }
            .btn:hover { 
                background: #002060; 
                transform: translateY(-2px);
            }
            .spinner {
                width: 60px;
                height: 60px;
                margin: 30px auto;
                border: 5px solid #f3f3f3;
                border-top: 5px solid #003087;
                border-radius: 50%;
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    </head>
    <body>
        <div class="container">
            <div class="paypal-logo">
                <i class="fab fa-paypal"></i>
            </div>
            <h1>Redirection vers PayPal</h1>
            
            <div class="spinner"></div>
            <p>Préparation de votre paiement sécurisé...</p>
            
            <div class="commande-info">
                <p><strong>Commande #<?= htmlspecialchars($commande['numero'] ?? '') ?></strong></p>
                <div class="montant">
                    <?= number_format(floatval($total), 2, ',', ' ') ?> €
                </div>
            </div>
            
            <button class="btn" onclick="window.location.reload()">
                <i class="fab fa-paypal"></i> Payer maintenant
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>