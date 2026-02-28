<?php
// ============================================
// PAIEMENT PAR CARTE BANCAIRE VIA API PAYPAL - VERSION CORRIGÉE FINALE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// ============================================
// CONFIGURATION PAYPAL
// ============================================
define('PAYPAL_CLIENT_ID', 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
define('PAYPAL_CLIENT_SECRET', 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' ou 'live'
define('PAYPAL_BASE_URL', (PAYPAL_MODE === 'sandbox') 
    ? 'https://api-m.sandbox.paypal.com' 
    : 'https://api-m.paypal.com');

// ============================================
// TRAITEMENT DES RETOURS PAYPAL UNIQUEMENT
// ============================================
if (isset($_GET['token']) || isset($_GET['PayerID']) || isset($_GET['success']) || isset($_GET['cancel'])) {
    
    if (isset($_GET['success']) && $_GET['success'] == '1' && isset($_GET['commande'])) {
        $commande_id = intval($_GET['commande']);
        $token = $_GET['token'] ?? '';
        
        header('Location: paiement-reussi.php?commande=' . $commande_id . '&token=' . $token);
        exit;
    }
    
    if (isset($_GET['cancel']) && $_GET['cancel'] == '1') {
        addSessionMessage('Paiement annulé.', 'info');
        header('Location: paiement.php');
        exit;
    }
    
    header('Location: paiement.php');
    exit;
}

// ============================================
// VÉRIFICATIONS D'ACCÈS
// ============================================
if (!hasShippingAddress()) {
    addSessionMessage('Veuillez d\'abord renseigner votre adresse de livraison.', 'error');
    header('Location: livraison_form.php');
    exit;
}

if (!hasValidCart()) {
    addSessionMessage('Votre panier est vide.', 'error');
    header('Location: panier.html');
    exit;
}

// ============================================
// CONNEXION BDD
// ============================================
$pdo = getPDOConnection();
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

synchroniserPanierSessionBDD($pdo, session_id());

// ============================================
// VÉRIFICATION DES COMMANDES EN COURS / DOUBLONS
// ============================================
if (isset($_SESSION[SESSION_KEY_COMMANDE]['id'])) {
    $commande_en_cours = $_SESSION[SESSION_KEY_COMMANDE]['id'];
    
    $stmt_check = $pdo->prepare("SELECT statut_paiement, reference_paypal FROM commandes WHERE id_commande = ?");
    $stmt_check->execute([$commande_en_cours]);
    $commande_existante = $stmt_check->fetch();
    
    if ($commande_existante && $commande_existante['statut_paiement'] === 'paye') {
        // Déjà payée, rediriger vers la confirmation
        header('Location: paiement-reussi.php?commande=' . $commande_en_cours);
        exit;
    }
}

// Nettoyer les anciens flags de session
cleanPayPalFlags();

// ============================================
// FONCTIONS PAYPAL API
// ============================================

/**
 * Obtient un token d'accès PayPal
 */
function getPayPalAccessToken() {
    $ch = curl_init();
    
    $url = PAYPAL_BASE_URL . '/v1/oauth2/token';
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_CLIENT_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
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
 * Valide le format de la date d'expiration
 */
function validateExpiryDate($month, $year) {
    $month = preg_replace('/[^0-9]/', '', $month);
    $year = preg_replace('/[^0-9]/', '', $year);
    
    if (strlen($month) !== 2 || $month < '01' || $month > '12') {
        return ['valid' => false, 'error' => 'Mois d\'expiration invalide'];
    }
    
    if (strlen($year) === 2) {
        $year = '20' . $year;
    } elseif (strlen($year) !== 4) {
        return ['valid' => false, 'error' => 'Année d\'expiration invalide'];
    }
    
    $current_year = intval(date('Y'));
    $current_month = intval(date('m'));
    $exp_year = intval($year);
    $exp_month = intval($month);
    
    if ($exp_year < $current_year || ($exp_year == $current_year && $exp_month < $current_month)) {
        return ['valid' => false, 'error' => 'Carte expirée'];
    }
    
    return [
        'valid' => true,
        'month' => $month,
        'year' => $year,
        'expiry_formatted' => $year . '-' . $month
    ];
}

/**
 * Validation Luhn pour numéro de carte
 */
function validateLuhn($number) {
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $n = $number[$i];
        if ($alt) {
            $n *= 2;
            if ($n > 9) {
                $n = ($n % 10) + 1;
            }
        }
        $sum += $n;
        $alt = !$alt;
    }
    return ($sum % 10 == 0);
}

/**
 * Vérifie si une commande a déjà été payée
 */
function checkCommandeDejaPayee($pdo, $commande_id) {
    $stmt = $pdo->prepare("SELECT statut_paiement, reference_paypal FROM commandes WHERE id_commande = ?");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch();
    
    if ($commande && $commande['statut_paiement'] === 'paye') {
        return true;
    }
    
    // Vérifier aussi dans les transactions
    $stmt_trans = $pdo->prepare("SELECT id_transaction FROM transactions WHERE id_commande = ? AND statut = 'paye'");
    $stmt_trans->execute([$commande_id]);
    
    return $stmt_trans->fetch() ? true : false;
}

/**
 * Vérifie si un ID PayPal a déjà été utilisé
 */
function checkPayPalIdDejaUtilise($pdo, $paypal_order_id) {
    $stmt = $pdo->prepare("SELECT id_commande FROM commandes WHERE reference_paypal = ? AND statut_paiement = 'paye'");
    $stmt->execute([$paypal_order_id]);
    return $stmt->fetch() ? true : false;
}

/**
 * Crée une commande PayPal avec paiement par carte
 */
function createPayPalOrderWithCard($commande_id, $montant, $card_details, $return_url, $cancel_url) {
    $access_token = getPayPalAccessToken();
    
    if (is_array($access_token) && isset($access_token['error'])) {
        return $access_token;
    }
    
    $montant_total = number_format(floatval($montant), 2, '.', '');
    
    // NETTOYAGE DES DONNÉES DE CARTE
    $card_number = preg_replace('/\s+/', '', $card_details['number']);
    $card_number = preg_replace('/[^0-9]/', '', $card_number);
    
    if (strlen($card_number) < 13 || strlen($card_number) > 19) {
        return ['error' => 'Numéro de carte invalide'];
    }
    
    $cvv = preg_replace('/[^0-9]/', '', $card_details['cvv']);
    if (strlen($cvv) < 3 || strlen($cvv) > 4) {
        return ['error' => 'Cryptogramme (CVV) invalide'];
    }
    
    $cardholder_name = trim($card_details['name']);
    if (empty($cardholder_name)) {
        return ['error' => 'Nom du titulaire requis'];
    }
    
    $billing_address = $card_details['billing_address'];
    $expiry = $card_details['expiry'];
    
    // GÉNÉRER UN INVOICE_ID VRAIMENT UNIQUE
    $invoice_id = 'INV-' . date('Ymd') . '-' . $commande_id . '-' . uniqid() . '-' . rand(1000, 9999);
    
    $payment_source = [
        'card' => [
            'name' => $cardholder_name,
            'number' => $card_number,
            'security_code' => $cvv,
            'expiry' => $expiry,
            'billing_address' => [
                'address_line_1' => $billing_address['line1'],
                'admin_area_2' => $billing_address['city'],
                'postal_code' => $billing_address['postal_code'],
                'country_code' => $billing_address['country_code']
            ]
        ]
    ];
    
    if (!empty($billing_address['line2'])) {
        $payment_source['card']['billing_address']['address_line_2'] = $billing_address['line2'];
    }
    
    $order_data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [
            [
                'reference_id' => 'COMMANDE_' . $commande_id,
                'description' => 'Commande #' . $commande_id . ' - HEURE DU CADEAU',
                'custom_id' => (string)$commande_id,
                'invoice_id' => $invoice_id,
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => $montant_total
                ]
            ]
        ],
        'payment_source' => $payment_source
    ];
    
    $log_data = $order_data;
    $log_data['payment_source']['card']['number'] = substr($card_number, 0, 4) . '********' . substr($card_number, -4);
    $log_data['payment_source']['card']['security_code'] = '***';
    error_log("PayPal Order Data with Card: " . json_encode($log_data));
    
    $url = PAYPAL_BASE_URL . '/v2/checkout/orders';
    
    $ch = curl_init();
    
    $request_id = uniqid('paypal_cb_' . $commande_id . '_', true);
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Erreur cURL création commande PayPal avec carte: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    error_log("PayPal Response with Card (HTTP $http_code): " . $result);
    
    if ($http_code >= 400) {
        $response_data = json_decode($result, true);
        $error_message = $response_data['message'] ?? $response_data['error_description'] ?? 'Erreur inconnue';
        
        $error_details = '';
        if (isset($response_data['details']) && is_array($response_data['details'])) {
            foreach ($response_data['details'] as $detail) {
                $error_details .= ' ' . ($detail['description'] ?? $detail['issue'] ?? '');
            }
        }
        
        error_log("Erreur PayPal création commande avec carte: $error_message - $error_details");
        
        return [
            'error' => "Erreur PayPal: " . $error_message,
            'details' => $error_details,
            'full_response' => $response_data,
            'http_code' => $http_code
        ];
    }
    
    return json_decode($result, true);
}

/**
 * Capture un paiement PayPal avec gestion d'erreur "already captured"
 */
function capturePayPalOrder($pdo, $commande_id, $order_id) {
    // Vérifier d'abord si la commande a déjà été capturée
    if (checkPayPalIdDejaUtilise($pdo, $order_id)) {
        return [
            'success' => true,
            'already_captured' => true,
            'message' => 'Order already captured'
        ];
    }
    
    $access_token = getPayPalAccessToken();
    
    if (is_array($access_token) && isset($access_token['error'])) {
        return $access_token;
    }
    
    $url = PAYPAL_BASE_URL . "/v2/checkout/orders/$order_id/capture";
    
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("Erreur cURL capture PayPal: " . $error);
        return ['error' => "Erreur de communication avec PayPal: $error"];
    }
    
    curl_close($ch);
    
    error_log("Capture PayPal Response (HTTP $http_code): " . $result);
    
    if ($http_code >= 400) {
        $response_data = json_decode($result, true);
        $error_message = $response_data['message'] ?? $response_data['error_description'] ?? "Erreur capture PayPal: $http_code";
        
        $error_details = '';
        if (isset($response_data['details']) && is_array($response_data['details'])) {
            foreach ($response_data['details'] as $detail) {
                $error_details .= ' ' . ($detail['description'] ?? $detail['issue'] ?? '');
            }
        }
        
        error_log("Erreur capture PayPal: $error_message - $error_details");
        
        // Vérifier spécifiquement l'erreur "already captured"
        if (strpos($error_message, 'already captured') !== false || 
            strpos($error_details, 'already captured') !== false) {
            return [
                'success' => true,
                'already_captured' => true,
                'message' => 'Order already captured'
            ];
        }
        
        return [
            'error' => $error_message,
            'details' => $error_details,
            'full_response' => $response_data,
            'http_code' => $http_code
        ];
    }
    
    return json_decode($result, true);
}

// ============================================
// FONCTIONS DE CRÉATION DE COMMANDE
// ============================================

/**
 * Crée une commande à partir du panier
 */
function creerCommandeDepuisPanier($pdo, $mode_paiement = 'carte') {
    try {
        $checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
        $client_id = $checkout['client_id'] ?? null;
        $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
        
        $pdo->beginTransaction();
        
        // Création du client temporaire si nécessaire
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
        
        if (!$client_id) {
            throw new Exception("Client ID manquant");
        }
        if (!$adresse_livraison_id) {
            throw new Exception("Adresse de livraison ID manquante");
        }
        
        // Calculer les totaux
        $sous_total = 0;
        $items_data = [];
        
        foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
            $produit = getProductDetails($item['id_produit'], $pdo);
            $prix_ttc = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
            $quantite = intval($item['quantite'] ?? 1);
            $sous_total += $prix_ttc * $quantite;
            
            $items_data[] = [
                'id_produit' => $item['id_produit'],
                'reference' => $produit['reference'] ?? 'REF' . $item['id_produit'],
                'nom' => $produit['nom'] ?? 'Produit',
                'quantite' => $quantite,
                'prix_unitaire_ttc' => $prix_ttc,
                'prix_unitaire_ht' => round($prix_ttc / 1.2, 2),
                'tva' => 20.00
            ];
        }
        
        if (empty($items_data)) {
            throw new Exception("Aucun article dans le panier");
        }
        
        // Frais de livraison
        $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
        $frais_livraison = 0;
        
        if ($mode_livraison === 'express') {
            $frais_livraison = 9.90;
        } elseif ($mode_livraison === 'relais') {
            $frais_livraison = 4.90;
        } elseif ($sous_total < 50) {
            $frais_livraison = 4.90;
        }
        
        $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
        $total = round($sous_total + $frais_livraison + $frais_emballage, 2);
        
        // Insérer la commande
        $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_livraison_id;
        $client_type = ($checkout['is_guest'] ?? false) ? 'guest' : 'registered';
        $instructions = $checkout['instructions'] ?? null;
        
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
            ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', ?, NOW(), ?, ?)
        ");
        
        $result = $stmt->execute([
            $client_id,
            $adresse_livraison_id,
            $adresse_facturation_id,
            round($sous_total, 2),
            round($frais_livraison + $frais_emballage, 2),
            $total,
            $mode_paiement,
            $client_type,
            $instructions
        ]);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            throw new Exception("Échec insertion commande: " . ($errorInfo[2] ?? 'Erreur inconnue'));
        }
        
        $id_commande = $pdo->lastInsertId();
        
        if (!$id_commande || $id_commande == 0) {
            throw new Exception("Échec de la récupération de l'ID de la commande après insertion.");
        }
        
        // Insérer les articles
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
                throw new Exception("Échec de l'insertion de l'article: " . ($errorInfo[2] ?? 'Erreur inconnue'));
            }
        }
        
        $pdo->commit();
        
        if (isset($_SESSION[SESSION_KEY_PANIER_ID]) && is_numeric($_SESSION[SESSION_KEY_PANIER_ID])) {
            $stmt_panier = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
            $stmt_panier->execute([$_SESSION[SESSION_KEY_PANIER_ID]]);
        }
        
        $stmt_num = $pdo->prepare("SELECT numero_commande FROM commandes WHERE id_commande = ?");
        $stmt_num->execute([$id_commande]);
        $commande_numero = $stmt_num->fetchColumn();
        
        $_SESSION[SESSION_KEY_COMMANDE] = [
            'id' => $id_commande,
            'numero' => $commande_numero,
            'montant' => $total
        ];
        
        return [
            'id' => $id_commande,
            'numero' => $commande_numero,
            'total' => $total,
            'client_id' => $client_id,
            'items' => $items_data
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("ERREUR CRÉATION COMMANDE: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Ajoute une transaction après paiement réussi
 */
function ajouterTransaction($pdo, $commande_id, $client_id, $montant, $reference, $details = []) {
    try {
        $numero_transaction = 'CB_' . date('Ymd') . '_' . uniqid();
        $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt_trans = $pdo->prepare("
            INSERT INTO transactions 
            (numero_transaction, id_commande, id_client, montant, methode_paiement,
             reference_paiement, statut, date_creation, ip_client, details) 
            VALUES (?, ?, ?, ?, 'carte', ?, 'paye', NOW(), ?, ?)
        ");
        
        $details_json = json_encode($details);
        
        return $stmt_trans->execute([
            $numero_transaction,
            $commande_id,
            $client_id,
            $montant,
            $reference,
            $ip_client,
            $details_json
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur ajout transaction: " . $e->getMessage());
        return false;
    }
}

/**
 * Met à jour les stocks après paiement
 */
function mettreAJourStocks($pdo, $commande_id) {
    try {
        $stmt = $pdo->prepare("
            UPDATE produits p
            JOIN commande_items ci ON p.id_produit = ci.id_produit
            SET p.ventes = p.ventes + ci.quantite,
                p.quantite_stock = p.quantite_stock - ci.quantite
            WHERE ci.id_commande = ?
        ");
        return $stmt->execute([$commande_id]);
    } catch (Exception $e) {
        error_log("Erreur mise à jour stocks: " . $e->getMessage());
        return false;
    }
}

// ============================================
// TRAITEMENT DU FORMULAIRE DE PAIEMENT
// ============================================
$erreurs = [];
$paiement_reussi = false;
$commande_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $numero_carte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $numero_carte = preg_replace('/[^0-9]/', '', $numero_carte);
    $expiration_mois = $_POST['expiration_mois'] ?? '';
    $expiration_annee = $_POST['expiration_annee'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $cryptogramme = preg_replace('/[^0-9]/', '', $cryptogramme);
    $titulaire = trim($_POST['titulaire_carte'] ?? '');
    
    // Validation des données carte
    if (strlen($numero_carte) < 13 || strlen($numero_carte) > 19 || !ctype_digit($numero_carte)) {
        $erreurs[] = "Numéro de carte invalide (doit contenir entre 13 et 19 chiffres)";
    } elseif (!validateLuhn($numero_carte)) {
        $erreurs[] = "Numéro de carte invalide (échec de la validation)";
    }
    
    if (!preg_match('/^(0[1-9]|1[0-2])$/', $expiration_mois)) {
        $erreurs[] = "Mois d'expiration invalide (doit être entre 01 et 12)";
    }
    
    if (!preg_match('/^[0-9]{2}$/', $expiration_annee) && !preg_match('/^20[0-9]{2}$/', $expiration_annee)) {
        $erreurs[] = "Année d'expiration invalide (format accepté: YY ou YYYY)";
    }
    
    $expiry_validation = validateExpiryDate($expiration_mois, $expiration_annee);
    if (!$expiry_validation['valid']) {
        $erreurs[] = $expiry_validation['error'];
    }
    
    if (strlen($cryptogramme) < 3 || strlen($cryptogramme) > 4 || !ctype_digit($cryptogramme)) {
        $erreurs[] = "Cryptogramme (CVV) invalide (doit contenir 3 ou 4 chiffres)";
    }
    
    if (empty($titulaire)) {
        $erreurs[] = "Nom du titulaire requis";
    }
    
    // Si pas d'erreurs, procéder au paiement
    if (empty($erreurs) && $expiry_validation['valid']) {
        try {
            // Vérifier si une commande existe déjà dans la session
            if (isset($_SESSION[SESSION_KEY_COMMANDE]['id'])) {
                $commande_existante_id = $_SESSION[SESSION_KEY_COMMANDE]['id'];
                
                // Vérifier si cette commande est déjà payée
                if (checkCommandeDejaPayee($pdo, $commande_existante_id)) {
                    // Déjà payée, rediriger
                    header('Location: paiement-reussi.php?commande=' . $commande_existante_id);
                    exit;
                }
            }
            
            // Étape 1: Créer la commande en base de données
            $commande = creerCommandeDepuisPanier($pdo, 'carte');
            $commande_id = $commande['id'];
            
            // Étape 2: Préparer les détails de la carte pour PayPal
            $adresse = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];
            
            $card_details = [
                'name' => $titulaire,
                'number' => $numero_carte,
                'cvv' => $cryptogramme,
                'expiry' => $expiry_validation['expiry_formatted'],
                'billing_address' => [
                    'line1' => $adresse['adresse'] ?? '123 Rue Example',
                    'line2' => $adresse['complement'] ?? '',
                    'city' => $adresse['ville'] ?? 'Paris',
                    'postal_code' => $adresse['code_postal'] ?? '75001',
                    'country_code' => 'FR'
                ]
            ];
            
            // Étape 3: URLs de retour
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . $host . '/';
            $return_url = $base_url . 'paiement_cb.php?success=1&commande=' . $commande_id;
            $cancel_url = $base_url . 'paiement_cb.php?cancel=1&commande=' . $commande_id;
            
            // Étape 4: Créer la commande PayPal avec paiement par carte
            $paypal_order = createPayPalOrderWithCard(
                $commande_id,
                $commande['total'],
                $card_details,
                $return_url,
                $cancel_url
            );
            
            if (isset($paypal_order['error'])) {
                throw new Exception($paypal_order['error'] . ($paypal_order['details'] ?? ''));
            }
            
            // Étape 5: Capturer le paiement avec gestion "already captured"
            $capture_result = capturePayPalOrder($pdo, $commande_id, $paypal_order['id']);
            
            if (isset($capture_result['error'])) {
                throw new Exception("Erreur capture: " . $capture_result['error'] . ($capture_result['details'] ?? ''));
            }
            
            // Si déjà capturé, rediriger vers succès
            if (isset($capture_result['already_captured']) && $capture_result['already_captured']) {
                header('Location: paiement-reussi.php?commande=' . $commande_id);
                exit;
            }
            
            // Vérifier le statut de la capture
            $capture_status = $capture_result['status'] ?? '';
            $capture_completed = false;
            
            if ($capture_status === 'COMPLETED') {
                $capture_completed = true;
            } elseif (isset($capture_result['purchase_units'][0]['payments']['captures'][0]['status'])) {
                $capture_status = $capture_result['purchase_units'][0]['payments']['captures'][0]['status'];
                if ($capture_status === 'COMPLETED') {
                    $capture_completed = true;
                }
            }
            
            if ($capture_completed) {
                
                // Paiement réussi
                $pdo->beginTransaction();
                
                try {
                    $capture_id = $capture_result['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                    
                    // Mettre à jour la commande
                    $stmt = $pdo->prepare("
                        UPDATE commandes 
                        SET statut = 'confirmee',
                            statut_paiement = 'paye',
                            reference_paiement = ?,
                            reference_paypal = ?,
                            date_paiement = NOW()
                        WHERE id_commande = ?
                    ");
                    $stmt->execute([
                        $paypal_order['id'],
                        $paypal_order['id'],
                        $commande_id
                    ]);
                    
                    // Ajouter la transaction
                    $paypal_details = [
                        'order_id' => $paypal_order['id'],
                        'capture_id' => $capture_id,
                        'status' => $capture_status,
                        'full_response' => $capture_result
                    ];
                    
                    ajouterTransaction(
                        $pdo, 
                        $commande_id, 
                        $commande['client_id'], 
                        $commande['total'], 
                        $paypal_order['id'],
                        $paypal_details
                    );
                    
                    // Mettre à jour les stocks
                    mettreAJourStocks($pdo, $commande_id);
                    
                    $pdo->commit();
                    
                    // Vider le panier
                    cleanUserSession();
                    
                    // Rediriger vers la page de succès
                    header('Location: paiement-reussi.php?commande=' . $commande_id . '&token=' . $paypal_order['id']);
                    exit;
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                
            } else {
                throw new Exception("Statut de paiement inattendu: " . $capture_status);
            }
            
        } catch (Exception $e) {
            error_log("Erreur paiement carte via PayPal: " . $e->getMessage());
            $erreurs[] = "Erreur lors du paiement: " . $e->getMessage();
        }
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES POUR AFFICHAGE
// ============================================
$messages = getSessionMessages();
$nb_articles = countCartItems();

// Récupérer les détails du panier
$panier_details = [];
$sous_total = 0;

foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
    $produit = getProductDetails($item['id_produit'], $pdo);
    $prix_unitaire = floatval($produit['prix_ttc'] ?? $item['prix'] ?? 0);
    $quantite = intval($item['quantite'] ?? 1);
    $prix_total = $quantite * $prix_unitaire;
    $sous_total += $prix_total;
    
    $panier_details[] = [
        'id_produit' => $item['id_produit'],
        'quantite' => $quantite,
        'nom' => $produit['nom'] ?? 'Produit',
        'prix_unitaire' => $prix_unitaire,
        'prix_total' => $prix_total,
        'reference' => $produit['reference'] ?? '',
        'image' => $produit['image'] ?? 'img/default-product.jpg'
    ];
}

// Calculer les frais
$mode_livraison = $_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'] ?? 'standard';
$frais_livraison = 0;

if ($mode_livraison === 'express') {
    $frais_livraison = 9.90;
} elseif ($mode_livraison === 'relais') {
    $frais_livraison = 4.90;
} elseif ($sous_total < 50) {
    $frais_livraison = 4.90;
}

$frais_emballage = ($_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] ?? false) ? 3.90 : 0;
$total = $sous_total + $frais_livraison + $frais_emballage;

$adresse = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement par Carte - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 700px;
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
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        h1 i { color: #003087; }
        .badge {
            background: #003087;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 10px;
        }
        .paypal-note {
            background: #ebf8ff;
            border-left: 5px solid #003087;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 14px;
            color: #2c5282;
        }
        .paypal-note i {
            color: #003087;
            margin-right: 10px;
        }
        .details {
            background: #f7fafc;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #003087;
        }
        .details p {
            margin: 8px 0;
            color: #4a5568;
        }
        .montant {
            font-size: 28px;
            color: #003087;
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
            border-color: #003087;
            box-shadow: 0 0 0 3px rgba(0,48,135,0.1);
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
        .card-icons i { transition: all 0.3s ease; }
        .btn {
            background: linear-gradient(135deg, #003087, #002060);
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
            box-shadow: 0 10px 25px rgba(0,48,135,0.4);
        }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .btn-secondary {
            background: #edf2f7;
            color: #4a5568;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
            transform: none;
            box-shadow: none;
        }
        .error {
            color: #c53030;
            margin-bottom: 25px;
            padding: 15px;
            background: #fff5f5;
            border-radius: 12px;
            border-left: 5px solid #c53030;
        }
        .error p { margin: 5px 0; }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 8px;
            border: 1px solid transparent;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
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
        .secure-badge i { color: #003087; }
        .expiry-select {
            display: flex;
            gap: 10px;
        }
        .expiry-select select { flex: 1; }
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
            <i class="fab fa-paypal"></i> 
            Paiement par Carte
            <span class="badge">via PayPal</span>
        </h1>
        
        <div class="paypal-note">
            <i class="fab fa-paypal"></i>
            <strong>Paiement sécurisé par PayPal</strong> - 
            Vos informations de carte sont traitées directement par PayPal.
            Aucune donnée sensible n'est stockée sur notre site.
        </div>
        
        <div class="details">
            <p><strong><i class="fas fa-map-marker-alt"></i> Adresse de livraison</strong></p>
            <p><?= htmlspecialchars(($adresse['prenom'] ?? '') . ' ' . ($adresse['nom'] ?? '')) ?></p>
            <p><?= htmlspecialchars($adresse['adresse'] ?? '') ?></p>
            <?php if (!empty($adresse['complement'])): ?>
                <p><?= htmlspecialchars($adresse['complement']) ?></p>
            <?php endif; ?>
            <p><?= htmlspecialchars($adresse['code_postal'] ?? '') ?> <?= htmlspecialchars($adresse['ville'] ?? '') ?></p>
            
            <div class="montant">
                Total à payer : <?= number_format($total, 2, ',', ' ') ?> €
            </div>
            
            <p style="font-size: 14px; color: #718096; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> 
                <?= count($panier_details) ?> article(s)
            </p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="message error">
                <?php foreach ($erreurs as $erreur): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="paymentForm" autocomplete="off">
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Numéro de carte</label>
                <input type="text" name="numero_carte" id="numero_carte" 
                       placeholder="1234 5678 9012 3456" maxlength="19" required 
                       autocomplete="off" inputmode="numeric"
                       value="<?= htmlspecialchars($_POST['numero_carte'] ?? '') ?>">
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
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $month = str_pad($m, 2, '0', STR_PAD_LEFT);
                                $selected = (isset($_POST['expiration_mois']) && $_POST['expiration_mois'] == $month) ? 'selected' : '';
                            ?>
                            <option value="<?= $month ?>" <?= $selected ?>><?= $month ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="expiration_annee" id="expiration_annee" required>
                            <option value="">Année</option>
                            <?php
                            $currentYear = date('Y');
                            for ($i = 0; $i < 10; $i++):
                                $yearDisplay = $currentYear + $i;
                                $yearFormatted = $yearDisplay;
                                $selected = (isset($_POST['expiration_annee']) && $_POST['expiration_annee'] == $yearFormatted) ? 'selected' : '';
                            ?>
                            <option value="<?= $yearFormatted ?>" <?= $selected ?>><?= $yearDisplay ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Cryptogramme (CVV)</label>
                    <input type="text" name="cryptogramme" id="cryptogramme" 
                           placeholder="123" maxlength="4" required 
                           autocomplete="off" inputmode="numeric"
                           value="<?= htmlspecialchars($_POST['cryptogramme'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nom du titulaire</label>
                <input type="text" name="titulaire_carte" id="titulaire_carte" 
                       value="<?= htmlspecialchars($_POST['titulaire_carte'] ?? ($adresse['prenom'] ?? '') . ' ' . ($adresse['nom'] ?? '')) ?>" required 
                       autocomplete="off">
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <a href="paiement.php" class="btn btn-secondary" style="flex: 1;">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <button type="submit" class="btn" id="submitBtn" style="flex: 2;">
                    <i class="fab fa-paypal"></i> Payer <?= number_format($total, 2, ',', ' ') ?> €
                </button>
            </div>
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
            
            const numeroCarte = document.getElementById('numero_carte');
            const cryptogramme = document.getElementById('cryptogramme');
            const submitBtn = document.getElementById('submitBtn');
            const paymentForm = document.getElementById('paymentForm');
            
            const iconVisa = document.getElementById('icon-visa');
            const iconMastercard = document.getElementById('icon-mastercard');
            const iconAmex = document.getElementById('icon-amex');

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
                    
                    detectCardType(value);
                });
            }

            function detectCardType(cardNumber) {
                if (iconVisa) iconVisa.style.color = '#718096';
                if (iconMastercard) iconMastercard.style.color = '#718096';
                if (iconAmex) iconAmex.style.color = '#718096';
                
                if (cardNumber.startsWith('4')) {
                    if (iconVisa) iconVisa.style.color = '#1434cb';
                } else if (cardNumber.startsWith('5')) {
                    if (iconMastercard) iconMastercard.style.color = '#eb001b';
                } else if (['34', '37'].some(prefix => cardNumber.startsWith(prefix))) {
                    if (iconAmex) iconAmex.style.color = '#2e77bc';
                }
            }

            if (cryptogramme) {
                cryptogramme.addEventListener('input', function(e) {
                    e.target.value = e.target.value.replace(/\D/g, '').substr(0, 4);
                });
            }

            if (paymentForm) {
                paymentForm.addEventListener('submit', function(e) {
                    if (!numeroCarte || !numeroCarte.value.trim()) {
                        e.preventDefault();
                        alert('Veuillez saisir le numéro de carte');
                        return false;
                    }
                    
                    const cardNumber = numeroCarte.value.replace(/\s/g, '');
                    if (cardNumber.length < 13 || cardNumber.length > 16) {
                        e.preventDefault();
                        alert('Numéro de carte invalide (doit contenir entre 13 et 16 chiffres)');
                        return false;
                    }
                    
                    const mois = document.getElementById('expiration_mois').value;
                    const annee = document.getElementById('expiration_annee').value;
                    
                    if (!mois) {
                        e.preventDefault();
                        alert('Veuillez sélectionner le mois d\'expiration');
                        return false;
                    }
                    
                    if (!annee) {
                        e.preventDefault();
                        alert('Veuillez sélectionner l\'année d\'expiration');
                        return false;
                    }
                    
                    const currentDate = new Date();
                    const currentYear = currentDate.getFullYear();
                    const currentMonth = currentDate.getMonth() + 1;
                    
                    if (parseInt(annee) < currentYear || 
                        (parseInt(annee) === currentYear && parseInt(mois) < currentMonth)) {
                        e.preventDefault();
                        alert('Cette carte est expirée');
                        return false;
                    }
                    
                    if (!cryptogramme || !cryptogramme.value.trim() || cryptogramme.value.length < 3) {
                        e.preventDefault();
                        alert('Veuillez saisir le cryptogramme (CVV)');
                        return false;
                    }
                    
                    if (!document.getElementById('titulaire_carte').value.trim()) {
                        e.preventDefault();
                        alert('Veuillez saisir le nom du titulaire');
                        return false;
                    }
                    
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement PayPal...';
                    }
                    
                    return true;
                });
            }
        })();
    </script>
</body>
</html>