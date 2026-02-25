<?php
// ============================================
// PAIEMENT PAR CARTE BANCAIRE VIA PAYPAL - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

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
// FONCTIONS UTILITAIRES (sans getProductDetails qui est déjà dans session_verification.php)
// ============================================

/**
 * Calcule les totaux du panier avec les frais
 */
function calculerTotauxPanierComplet($panier_details, $checkout_data) {
    $sous_total = 0;
    
    foreach ($panier_details as $item) {
        $sous_total += $item['prix_total'] ?? 0;
    }
    
    // Frais de livraison par défaut
    $frais_livraison = 4.90;
    $seuil_gratuit = 50.00;
    
    if ($sous_total >= $seuil_gratuit) {
        $frais_livraison = 0;
    }
    
    // Frais d'emballage cadeau
    $frais_emballage = ($checkout_data['emballage_cadeau'] ?? false) ? 2.90 : 0;
    
    // Mode livraison (express, etc.)
    if (isset($checkout_data['mode_livraison'])) {
        switch ($checkout_data['mode_livraison']) {
            case 'express':
                $frais_livraison = 9.90;
                break;
            case 'point_relais':
                $frais_livraison = 3.90;
                break;
        }
    }
    
    $total_ttc = $sous_total + $frais_livraison + $frais_emballage;
    
    return [
        'sous_total' => $sous_total,
        'frais_livraison' => $frais_livraison,
        'frais_emballage' => $frais_emballage,
        'total_ttc' => $total_ttc
    ];
}

/**
 * Crée une commande à partir du panier en session - VERSION CORRIGÉE
 */
function creerCommandeDepuisPanier($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Vérifier qu'il y a des articles dans le panier
        if (empty($_SESSION[SESSION_KEY_PANIER])) {
            throw new Exception("Panier vide");
        }
        
        // Récupérer l'ID client
        $id_client = $_SESSION['client_id'] ?? null;
        if (!$id_client) {
            // Créer un client temporaire si nécessaire
            $email = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']['email'] ?? 'temp_' . uniqid() . '@temp.com';
            
            $stmt = $pdo->prepare("
                INSERT INTO clients (email, nom, prenom, is_temporary, date_inscription)
                VALUES (?, 'Client', 'Temporaire', 1, NOW())
            ");
            $stmt->execute([$email]);
            $id_client = $pdo->lastInsertId();
            $_SESSION['client_id'] = $id_client;
        }
        
        // Récupérer l'adresse de livraison depuis la session
        $adresse_livraison = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? null;
        
        // Si pas d'adresse en session, en créer une temporaire
        if (!$adresse_livraison || empty($adresse_livraison['id'])) {
            // Créer une adresse temporaire
            $stmt = $pdo->prepare("
                INSERT INTO adresses (id_client, nom, prenom, adresse, code_postal, ville, pays)
                VALUES (?, 'Client', 'Temporaire', 'Adresse temporaire', '75000', 'Paris', 'France')
            ");
            $stmt->execute([$id_client]);
            $id_adresse = $pdo->lastInsertId();
        } else {
            $id_adresse = $adresse_livraison['id'];
        }
        
        // Préparer les détails du panier
        $panier_details = [];
        $sous_total = 0;
        
        foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
            // Utiliser la fonction getProductDetails du fichier session_verification.php
            $produit = getProductDetails($item['id_produit'], $pdo);
            
            $prix_ttc = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
            if ($prix_ttc == 0) {
                // Essayer de récupérer depuis la BDD
                $stmt = $pdo->prepare("SELECT prix_ttc FROM produits WHERE id_produit = ?");
                $stmt->execute([$item['id_produit']]);
                $prod = $stmt->fetch(PDO::FETCH_ASSOC);
                $prix_ttc = floatval($prod['prix_ttc'] ?? 0);
            }
            
            $prix_ht = $prix_ttc / 1.20; // TVA 20%
            $quantite = intval($item['quantite']);
            $total_ligne = $quantite * $prix_ttc;
            $sous_total += $total_ligne;
            
            $panier_details[] = [
                'id_produit' => $item['id_produit'],
                'quantite' => $quantite,
                'prix_ht' => $prix_ht,
                'prix_ttc' => $prix_ttc,
                'prix_total' => $total_ligne,
                'nom' => $produit['nom'] ?? 'Produit',
                'reference' => $produit['reference'] ?? 'REF' . $item['id_produit'],
                'tva' => 20.00
            ];
        }
        
        // Calculer les frais
        $totaux = calculerTotauxPanierComplet($panier_details, $_SESSION[SESSION_KEY_CHECKOUT] ?? []);
        
        // INSÉRER LA COMMANDE
        $stmt = $pdo->prepare("
            INSERT INTO commandes (
                id_client, 
                id_adresse_livraison, 
                statut, 
                sous_total, 
                frais_livraison, 
                total_ttc, 
                mode_paiement,
                statut_paiement,
                client_type,
                date_commande
            ) VALUES (
                ?, ?, 'en_attente', ?, ?, ?, 'carte', 'en_attente', 'guest', NOW()
            )
        ");
        
        $result = $stmt->execute([
            $id_client,
            $id_adresse,
            $totaux['sous_total'],
            $totaux['frais_livraison'],
            $totaux['total_ttc']
        ]);
        
        if (!$result) {
            throw new Exception("Erreur lors de la création de la commande");
        }
        
        $id_commande = $pdo->lastInsertId();
        
        // Insérer les articles de la commande
        $stmt_item = $pdo->prepare("
            INSERT INTO commande_items (
                id_commande, 
                id_produit, 
                reference_produit, 
                nom_produit, 
                quantite, 
                prix_unitaire_ht,
                prix_unitaire_ttc,
                tva
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        foreach ($panier_details as $item) {
            $result = $stmt_item->execute([
                $id_commande,
                $item['id_produit'],
                $item['reference'],
                $item['nom'],
                $item['quantite'],
                $item['prix_ht'],
                $item['prix_ttc'],
                $item['tva']
            ]);
            
            if (!$result) {
                throw new Exception("Erreur lors de l'ajout des articles");
            }
            
            // Mettre à jour le stock du produit
            $stmt_update = $pdo->prepare("
                UPDATE produits 
                SET quantite_stock = quantite_stock - ?,
                    ventes = ventes + ?
                WHERE id_produit = ?
            ");
            $stmt_update->execute([
                $item['quantite'],
                $item['quantite'],
                $item['id_produit']
            ]);
        }
        
        $pdo->commit();
        
        // Sauvegarder l'ID en session
        $_SESSION[SESSION_KEY_COMMANDE]['id'] = $id_commande;
        
        // Récupérer le numéro de commande généré par le trigger
        $stmt = $pdo->prepare("SELECT numero_commande FROM commandes WHERE id_commande = ?");
        $stmt->execute([$id_commande]);
        $num = $stmt->fetchColumn();
        $_SESSION[SESSION_KEY_COMMANDE]['numero'] = $num;
        
        return $id_commande;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création commande: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        throw $e;
    }
}

/**
 * Récupère les détails d'une commande
 */
function getCommandeDetails($pdo, $id_commande) {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_commande,
            c.numero_commande,
            c.total_ttc as montant_total,
            c.sous_total,
            c.frais_livraison,
            c.statut,
            c.statut_paiement,
            c.mode_paiement,
            cl.id_client,
            cl.email,
            cl.prenom,
            cl.nom,
            a.id_adresse,
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
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

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
// FONCTIONS PAYPAL
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
 * Crée une commande PayPal
 */
function createPayPalOrder($montant, $commande_id, $return_url, $cancel_url) {
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
                'reference_id' => 'COMMANDE_' . $commande_id,
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
            'return_url' => $return_url . '?commande=' . $commande_id,
            'cancel_url' => $cancel_url . '?commande=' . $commande_id
        ]
    ];
    
    error_log("PayPal Order Data: " . json_encode($order_data));
    
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
        error_log("Erreur cURL création commande PayPal: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    error_log("PayPal Response (HTTP $http_code): " . $result);
    
    if ($http_code >= 400) {
        $response_data = json_decode($result, true);
        $error_message = $response_data['message'] ?? $response_data['error_description'] ?? 'Erreur inconnue';
        $error_details = $response_data['details'][0]['description'] ?? '';
        
        error_log("Erreur PayPal création commande: $error_message - $error_details");
        
        return [
            'error' => "Erreur PayPal: " . $error_message . ($error_details ? " - $error_details" : ""),
            'details' => $response_data,
            'http_code' => $http_code
        ];
    }
    
    return json_decode($result, true);
}

// ============================================
// RÉCUPÉRATION OU CRÉATION DE LA COMMANDE
// ============================================

// Récupérer l'ID commande depuis l'URL
$id_commande = isset($_GET['commande']) && is_numeric($_GET['commande']) ? intval($_GET['commande']) : null;
$montant_param = isset($_GET['montant']) ? floatval($_GET['montant']) : 0;

try {
    // Vérifier si la commande existe en base
    if ($id_commande) {
        $commande = getCommandeDetails($pdo, $id_commande);
        
        if (!$commande) {
            error_log("Commande #$id_commande non trouvée - tentative de création");
            $id_commande = null; // Forcer la création
        }
    }
    
    // Si pas d'ID commande valide, créer la commande
    if (!$id_commande) {
        $id_commande = creerCommandeDepuisPanier($pdo);
        error_log("Nouvelle commande créée avec ID: $id_commande");
        
        // Récupérer les détails de la nouvelle commande
        $commande = getCommandeDetails($pdo, $id_commande);
        
        if (!$commande) {
            throw new Exception("Impossible de récupérer la commande #$id_commande après création");
        }
    }
    
    $total = floatval($commande['montant_total']);
    
    // Vérifier que le montant correspond (tolérance de 0.01€)
    if ($montant_param > 0 && abs($total - $montant_param) > 0.01) {
        error_log("Attention: Montant incohérent - URL: $montant_param, BDD: $total");
        // On utilise le montant de la BDD qui est la source de vérité
    }
    
} catch (Exception $e) {
    error_log("Erreur fatale paiement CB: " . $e->getMessage());
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
            pre { 
                text-align: left; 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 20px 0;
                max-height: 300px;
                overflow: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-icon">❌</div>
            <h1>Erreur lors de la préparation de la commande</h1>
            <p><?= htmlspecialchars($e->getMessage()) ?></p>
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
        $paypal_order = createPayPalOrder(
            $total,
            $id_commande,
            $return_url,
            $cancel_url
        );
        
        if (isset($paypal_order['error'])) {
            $erreurs[] = "Erreur PayPal: " . $paypal_order['error'];
            error_log("Erreur PayPal: " . json_encode($paypal_order));
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
                
                // Mettre à jour la commande avec la référence PayPal
                $stmt = $pdo->prepare("UPDATE commandes SET reference_paiement = ? WHERE id_commande = ?");
                $stmt->execute([$paypal_order['id'], $id_commande]);
                
                // Rediriger vers PayPal
                header('Location: ' . $approval_url);
                exit;
            } else {
                $erreurs[] = "URL d'approbation PayPal non trouvée";
            }
        }
    }
}

// ============================================
// AFFICHAGE HTML
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