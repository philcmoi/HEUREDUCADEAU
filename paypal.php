<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$action = $_GET['action'] ?? '';

// Authentification PayPal
function getPayPalAccessToken() {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => getPayPalBaseUrl() . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: en_US']
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

// Créer un paiement PayPal
if ($action === 'create_payment') {
    $token = getPayPalAccessToken();
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Erreur authentification PayPal']);
        exit;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $montant = $data['montant'] ?? 0;
    
    $paymentData = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => [
                'currency_code' => CURRENCY,
                'value' => number_format($montant, 2, '.', '')
            ],
            'description' => 'Commande HEURE DU CADEAU'
        ]],
        'application_context' => [
            'return_url' => RETURN_URL,
            'cancel_url' => CANCEL_URL,
            'brand_name' => 'HEURE DU CADEAU',
            'locale' => 'fr-FR',
            'landing_page' => 'BILLING',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
            'user_action' => 'PAY_NOW'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getPayPalBaseUrl() . '/v2/checkout/orders',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($paymentData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'PayPal-Request-Id: ' . uniqid()
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
    exit;
}

// Capturer un paiement
if ($action === 'capture_payment') {
    $token = getPayPalAccessToken();
    $orderId = $_GET['order_id'] ?? '';
    
    if (!$token || !$orderId) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => getPayPalBaseUrl() . '/v2/checkout/orders/' . $orderId . '/capture',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'PayPal-Request-Id: ' . uniqid()
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 201 && $responseData['status'] === 'COMPLETED') {
        // Paiement réussi - Créer la commande en BDD
        $pdo = getPDOConnection();
        
        try {
            // Récupérer le panier de la session
            $panier = $_SESSION['panier'] ?? [];
            
            // Créer la commande
            $stmt = $pdo->prepare("
                INSERT INTO commandes (
                    id_client, statut, sous_total, frais_livraison, 
                    total_ttc, mode_paiement, statut_paiement, 
                    reference_paiement, date_commande
                ) VALUES (?, 'en_attente', ?, 0, ?, 'paypal', 'paye', ?, NOW())
            ");
            
            $stmt->execute([
                $_SESSION['id_client'] ?? null,
                $panier['total'] ?? 0,
                $panier['total'] ?? 0,
                $orderId
            ]);
            
            $commandeId = $pdo->lastInsertId();
            
            // Ajouter les articles de la commande
            foreach ($panier['items'] ?? [] as $item) {
                $stmtItems = $pdo->prepare("
                    INSERT INTO commande_items (
                        id_commande, id_produit, reference_produit, 
                        nom_produit, quantite, prix_unitaire_ttc
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                // Générer une référence
                $ref = 'PROD' . str_pad($item['id_produit'], 6, '0', STR_PAD_LEFT);
                
                $stmtItems->execute([
                    $commandeId,
                    $item['id_produit'],
                    $ref,
                    $item['nom'],
                    $item['quantite'],
                    $item['prix_unitaire']
                ]);
                
                // Mettre à jour le stock
                $stmtStock = $pdo->prepare("
                    UPDATE produits 
                    SET quantite_stock = quantite_stock - ? 
                    WHERE id_produit = ?
                ");
                $stmtStock->execute([$item['quantite'], $item['id_produit']]);
            }
            
            // Vider le panier après commande
            $_SESSION['panier'] = [
                'items' => [],
                'count' => 0,
                'total' => 0.00
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Paiement capturé et commande créée',
                'commande_id' => $commandeId,
                'order_id' => $orderId,
                'details' => $responseData
            ]);
            
        } catch (PDOException $e) {
            error_log("Erreur création commande: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Erreur création commande: ' . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Échec capture paiement',
            'details' => $responseData
        ]);
    }
    exit;
}

// Créer un paiement par email
if ($action === 'create_email_payment') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $amount = $input['amount'] ?? 0;
    
    if (!$email || $amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }
    
    // Ici, vous implémenteriez la logique pour envoyer une demande de paiement par email
    // Cette fonctionnalité nécessite un compte PayPal Business
    
    echo json_encode([
        'success' => true,
        'message' => 'Fonctionnalité à implémenter avec PayPal Business',
        'email' => $email,
        'amount' => $amount
    ]);
    exit;
}

// Vérifier le statut d'une commande
if ($action === 'check_order') {
    $orderId = $_GET['order_id'] ?? '';
    
    if (!$orderId) {
        echo json_encode(['success' => false, 'message' => 'Order ID manquant']);
        exit;
    }
    
    $token = getPayPalAccessToken();
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => getPayPalBaseUrl() . '/v2/checkout/orders/' . $orderId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    echo $response;
    exit;
}

echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
?>