<?php
// ============================================
// PAIEMENT PAYPAL - VERSION AVEC API RÉELLE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// Configuration PayPal
define('PAYPAL_CLIENT_ID', 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
define('PAYPAL_CLIENT_SECRET', 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' pour test, 'live' pour production

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

function getPayPalAccessToken() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_MODE === 'sandbox' 
        ? 'https://api-m.sandbox.paypal.com/v1/oauth2/token'
        : 'https://api-m.paypal.com/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        error_log('Erreur cURL PayPal: ' . curl_error($ch));
        return false;
    }
    
    curl_close($ch);
    
    $data = json_decode($result, true);
    return $data['access_token'] ?? false;
}

function createPayPalOrder($commande_id, $montant, $items_data, $return_url, $cancel_url) {
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        return ['error' => 'Impossible d\'obtenir le token d\'accès PayPal'];
    }
    
    // Construction des items pour PayPal
    $items = [];
    $total_ht = 0;
    foreach ($items_data as $item) {
        $prix_unitaire = floatval($item['prix_unitaire_ttc']);
        $quantite = intval($item['quantite']);
        $items[] = [
            'name' => substr($item['nom'], 0, 127),
            'sku' => $item['reference'],
            'unit_amount' => [
                'currency_code' => 'EUR',
                'value' => number_format($prix_unitaire, 2, '.', '')
            ],
            'quantity' => $quantite,
            'category' => 'PHYSICAL_GOODS'
        ];
        $total_ht += $prix_unitaire * $quantite;
    }
    
    $montant_total = number_format(floatval($montant), 2, '.', '');
    
    // Construction de la requête PayPal
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'COMMANDE_' . $commande_id,
                'description' => 'Commande #' . $commande_id . ' - HEURE DU CADEAU',
                'custom_id' => (string)$commande_id,
                'invoice_id' => 'INV-' . date('Ymd') . '-' . $commande_id,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => $montant_total,
                    'breakdown' => [
                        'item_total' => [
                            'currency_code' => 'EUR',
                            'value' => $montant_total
                        ]
                    ]
                ],
                'items' => $items
            ]
        ],
        'application_context' => [
            'brand_name' => 'HEURE DU CADEAU',
            'landing_page' => 'BILLING',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            'user_action' => 'PAY_NOW',
            'return_url' => $return_url,
            'cancel_url' => $cancel_url
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_MODE === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
        : 'https://api-m.paypal.com/v2/checkout/orders');
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
        error_log('Erreur cURL création commande PayPal: ' . curl_error($ch));
        curl_close($ch);
        return ['error' => 'Erreur de communication avec PayPal'];
    }
    
    curl_close($ch);
    
    if ($http_code >= 400) {
        error_log('Erreur PayPal HTTP ' . $http_code . ': ' . $result);
        return ['error' => 'Erreur PayPal: ' . $http_code];
    }
    
    return json_decode($result, true);
}

function capturePayPalOrder($order_id) {
    $access_token = getPayPalAccessToken();
    if (!$access_token) {
        return ['error' => 'Impossible d\'obtenir le token d\'accès PayPal'];
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_MODE === 'sandbox'
        ? 'https://api-m.sandbox.paypal.com/v2/checkout/orders/' . $order_id . '/capture'
        : 'https://api-m.paypal.com/v2/checkout/orders/' . $order_id . '/capture');
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
        error_log('Erreur cURL capture PayPal: ' . curl_error($ch));
        curl_close($ch);
        return ['error' => 'Erreur de communication avec PayPal'];
    }
    
    curl_close($ch);
    
    if ($http_code >= 400) {
        error_log('Erreur capture PayPal HTTP ' . $http_code . ': ' . $result);
        return ['error' => 'Erreur capture PayPal: ' . $http_code];
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
    
    // Capturer le paiement
    $capture_result = capturePayPalOrder($paypal_order_id);
    
    if (isset($capture_result['error'])) {
        die("Erreur lors de la capture du paiement: " . $capture_result['error']);
    }
    
    // Récupérer l'ID de commande depuis les métadonnées
    $commande_id = null;
    if (isset($capture_result['purchase_units'][0]['custom_id'])) {
        $commande_id = $capture_result['purchase_units'][0]['custom_id'];
    }
    
    if (!$commande_id) {
        die("ID commande non trouvé dans la réponse PayPal");
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
        
        // Vider le panier
        cleanUserSession();
        
        header('Location: confirmation.php?commande=' . $commande_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur paiement PayPal: " . $e->getMessage());
        die("Erreur lors du paiement : " . $e->getMessage());
    }
}

// ============================================
// CRÉATION DE LA COMMANDE ET REDIRECTION PAYPAL
// ============================================
$id_commande = null;
$total = 0;
$paypal_order = null;

if (!isset($_SESSION[SESSION_KEY_COMMANDE])) {
    try {
        $pdo->beginTransaction();
        
        $checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
        $client_id = $checkout['client_id'] ?? null;
        $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
        
        // ========== ÉTAPE 1 : CRÉATION DU CLIENT TEMPORAIRE SI NÉCESSAIRE ==========
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
                    (id_client, nom, prenom, adresse, code_postal, ville, pays, telephone, principale, type_adresse)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'livraison')
                ");
                $result_addr = $stmt_addr->execute([
                    $client_id,
                    $adresse['nom'] ?? '',
                    $adresse['prenom'] ?? '',
                    $adresse['adresse'] ?? '',
                    $adresse['code_postal'] ?? '',
                    $adresse['ville'] ?? '',
                    $adresse['pays'] ?? 'France',
                    $adresse['telephone'] ?? null
                ]);
                
                if (!$result_addr) {
                    throw new Exception("Impossible de créer l'adresse de livraison");
                }
                
                $adresse_livraison_id = $pdo->lastInsertId();
            }
        }
        
        // Vérifications critiques avant insertion
        if (!$client_id) {
            throw new Exception("Client ID manquant");
        }
        if (!$adresse_livraison_id) {
            throw new Exception("Adresse de livraison ID manquante");
        }
        
        // ========== ÉTAPE 2 : PRÉPARATION DES DONNÉES DE LA COMMANDE ==========
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
        
        // ========== ÉTAPE 3 : INSERTION DE LA COMMANDE ==========
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
        
        $result = $stmt->execute([
            $client_id,
            $adresse_livraison_id,
            $adresse_facturation_id,
            round($sous_total, 2),
            round($frais_livraison + $frais_emballage, 2),
            $total,
            $client_type,
            $instructions
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Échec de l'insertion de la commande : " . ($errorInfo[2] ?? 'Erreur inconnue'));
        }
        
        $id_commande = $pdo->lastInsertId();
        
        if (!$id_commande || $id_commande == 0) {
            throw new Exception("ID commande non généré (lastInsertId = 0)");
        }
        
        // ========== ÉTAPE 4 : INSERTION DES ARTICLES ==========
        $stmt_item = $pdo->prepare("
            INSERT INTO commande_items (
                id_commande, id_produit, reference_produit, nom_produit,
                quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items_data as $item) {
            $result_item = $stmt_item->execute([
                $id_commande,
                $item['id_produit'],
                $item['reference'],
                $item['nom'],
                $item['quantite'],
                $item['prix_unitaire_ht'],
                $item['prix_unitaire_ttc'],
                $item['tva']
            ]);
            
            if (!$result_item) {
                $errorInfo = $stmt_item->errorInfo();
                throw new Exception("Échec insertion article '{$item['nom']}' : " . ($errorInfo[2] ?? 'Erreur inconnue'));
            }
        }
        
        // ========== ÉTAPE 5 : MISE À JOUR DU STATUT DU PANIER ==========
        if (isset($_SESSION[SESSION_KEY_PANIER_ID]) && is_numeric($_SESSION[SESSION_KEY_PANIER_ID])) {
            $stmt_panier = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
            $stmt_panier->execute([$_SESSION[SESSION_KEY_PANIER_ID]]);
        }
        
        $pdo->commit();
        
        $_SESSION[SESSION_KEY_COMMANDE] = [
            'id' => $id_commande,
            'montant' => $total,
            'items' => $items_data
        ];
        
        error_log("Commande PayPal créée avec succès: ID $id_commande, Montant: $total €");
        
        // ========== ÉTAPE 6 : CRÉATION DE LA COMMANDE PAYPAL ==========
        $return_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/paiement_paypal.php';
        $cancel_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/paiement-annule.php';
        
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
        
        // Rediriger vers PayPal
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
        
        // Sauvegarder l'ID PayPal dans la session
        $_SESSION['paypal_order_id'] = $paypal_order['id'];
        
        header('Location: ' . $approval_url);
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("ERREUR CRITIQUE création commande PayPal: " . $e->getMessage());
        
        if (ini_get('display_errors')) {
            die("Erreur lors de la création de la commande : " . $e->getMessage());
        } else {
            die("Une erreur est survenue lors de la création de votre commande. Veuillez réessayer ou contacter le support.");
        }
    }
} else {
    $id_commande = $_SESSION[SESSION_KEY_COMMANDE]['id'] ?? 0;
    $total = $_SESSION[SESSION_KEY_COMMANDE]['montant'] ?? 0;
}

// Récupération des infos commande
$commande = null;
if ($id_commande) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.prenom, cl.nom 
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$id_commande]);
        $commande = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Erreur récupération commande: " . $e->getMessage());
    }
}

if (!$commande) {
    $commande = [
        'numero_commande' => $_SESSION[SESSION_KEY_COMMANDE]['numero'] ?? ('TEMP-' . date('Ymd') . '-' . uniqid()),
        'prenom' => $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']['prenom'] ?? '',
        'nom' => $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']['nom'] ?? '',
        'total_ttc' => $total
    ];
}

// Si on arrive ici sans redirection PayPal, afficher la page d'attente
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redirection PayPal - HEURE DU CADEAU</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            margin: 0;
            padding: 20px;
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh;
        }
        .container { 
            background: white; 
            padding: 40px; 
            border-radius: 20px; 
            text-align: center; 
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        h1 {
            color: #003087;
            margin-bottom: 20px;
            font-size: 28px;
        }
        .commande-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #003087;
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
            font-weight: bold;
            cursor: pointer; 
            width: 100%;
            transition: all 0.3s ease;
            margin: 20px 0;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { 
            background: #002060; 
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,48,135,0.3);
        }
        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .secure-badge {
            color: #28a745;
            margin-top: 20px;
            font-size: 14px;
        }
        .details {
            color: #6c757d;
            font-size: 14px;
            margin-top: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #003087;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 2s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔵 Paiement PayPal</h1>
        
        <div class="spinner"></div>
        <p>Préparation de votre paiement sécurisé...</p>
        
        <div class="commande-info">
            <p style="font-size: 16px; margin-bottom: 5px;">Commande</p>
            <p style="font-size: 20px; font-weight: bold;">#<?= htmlspecialchars($commande['numero_commande'] ?? $id_commande) ?></p>
        </div>
        
        <div class="montant">
            <?= number_format(floatval($commande['total_ttc'] ?? $total), 2, ',', ' ') ?> €
        </div>
        
        <p style="color: #495057; margin: 20px 0;">
            <i class="fas fa-user"></i> 
            <?= htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) ?>
        </p>
        
        <button class="btn" onclick="redirectToPayPal()" id="paypalBtn">
            <i class="fas fa-paypal"></i> Payer avec PayPal
        </button>
        
        <a href="paiement.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        
        <div class="secure-badge">
            <i class="fas fa-lock"></i> Paiement 100% sécurisé par PayPal
        </div>
        
        <div class="details">
            <p>Vous allez être redirigé vers PayPal</p>
            <p>Aucun prélèvement ne sera effectué sans votre confirmation</p>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-code.js" crossorigin="anonymous"></script>
    <script>
        function redirectToPayPal() {
            // Cette fonction sera appelée si la redirection automatique ne fonctionne pas
            // Elle recharge la page pour tenter à nouveau la création de commande PayPal
            window.location.reload();
        }
        
        // Tentative de redirection automatique après 2 secondes
        setTimeout(function() {
            window.location.reload();
        }, 2000);
    </script>
</body>
</html>