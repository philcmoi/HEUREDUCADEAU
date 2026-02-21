<?php
// ============================================
// PAIEMENT PAYPAL - VERSION CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

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
// TRAITEMENT RETOUR PAYPAL
// ============================================
if (isset($_GET['paymentId']) && isset($_GET['PayerID']) && isset($_GET['status']) && $_GET['status'] === 'success') {
    
    $payment_id = $_GET['paymentId'];
    $payer_id = $_GET['PayerID'];
    $id_commande = $_GET['commande'] ?? 0;
    
    try {
        $pdo->beginTransaction();
        
        // Vérifier que la commande existe
        $stmt_check = $pdo->prepare("SELECT id_commande, id_client, total_ttc FROM commandes WHERE id_commande = ?");
        $stmt_check->execute([$id_commande]);
        $commande = $stmt_check->fetch();
        
        if (!$commande) {
            throw new Exception("Commande non trouvée: $id_commande");
        }
        
        // Mettre à jour la commande
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'confirmee',
                statut_paiement = 'paye',
                reference_paiement = ?,
                reference_paypal = ?,
                payer_id = ?,
                date_paiement = NOW()
            WHERE id_commande = ?
        ");
        $stmt->execute([$payment_id, $payment_id, $payer_id, $id_commande]);
        
        // Créer la transaction
        $numero_transaction = 'PP_' . date('Ymd') . '_' . uniqid();
        $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt_trans = $pdo->prepare("
            INSERT INTO transactions 
            (numero_transaction, id_commande, id_client, montant, methode_paiement,
             reference_paiement, statut, date_creation, ip_client) 
            VALUES (?, ?, ?, ?, 'paypal', ?, 'paye', NOW(), ?)
        ");
        
        $stmt_trans->execute([
            $numero_transaction,
            $id_commande,
            $commande['id_client'],
            $commande['total_ttc'],
            $payment_id,
            $ip_client
        ]);
        
        // Mettre à jour les stocks
        $stmt_stock = $pdo->prepare("
            UPDATE produits p
            JOIN commande_items ci ON p.id_produit = ci.id_produit
            SET p.ventes = p.ventes + ci.quantite,
                p.quantite_stock = p.quantite_stock - ci.quantite
            WHERE ci.id_commande = ?
        ");
        $stmt_stock->execute([$id_commande]);
        
        $pdo->commit();
        
        // Vider le panier
        cleanUserSession();
        
        header('Location: confirmation.php?commande=' . $id_commande);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur paiement PayPal: " . $e->getMessage());
        die("Erreur lors du paiement : " . $e->getMessage());
    }
}

// ============================================
// CRÉATION DE LA COMMANDE - CORRIGÉE
// ============================================
$id_commande = null;
$total = 0;

if (!isset($_SESSION[SESSION_KEY_COMMANDE])) {
    try {
        $pdo->beginTransaction();
        
        $checkout = $_SESSION[SESSION_KEY_CHECKOUT] ?? [];
        $client_id = $checkout['client_id'] ?? null;
        $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
        
        // Si pas de client_id, créer un client temporaire
        if (!$client_id) {
            $adresse = $checkout['adresse_livraison'] ?? [];
            $email = $adresse['email'] ?? 'temp_' . uniqid() . '@temp.com';
            $nom = $adresse['nom'] ?? 'Client';
            $prenom = $adresse['prenom'] ?? 'Temporaire';
            
            $stmt_client = $pdo->prepare("
                INSERT INTO clients (email, nom, prenom, is_temporary, date_inscription)
                VALUES (?, ?, ?, 1, NOW())
            ");
            $stmt_client->execute([$email, $nom, $prenom]);
            $client_id = $pdo->lastInsertId();
            
            // Créer l'adresse
            if ($client_id && !empty($adresse)) {
                $stmt_addr = $pdo->prepare("
                    INSERT INTO adresses 
                    (id_client, nom, prenom, adresse, code_postal, ville, pays, telephone, principale)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt_addr->execute([
                    $client_id,
                    $adresse['nom'] ?? '',
                    $adresse['prenom'] ?? '',
                    $adresse['adresse'] ?? '',
                    $adresse['code_postal'] ?? '',
                    $adresse['ville'] ?? '',
                    $adresse['pays'] ?? 'France',
                    $adresse['telephone'] ?? null
                ]);
                $adresse_livraison_id = $pdo->lastInsertId();
            }
        }
        
        if (!$client_id || !$adresse_livraison_id) {
            throw new Exception("Client ou adresse manquante");
        }
        
        $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_livraison_id;
        
        // Calculer le total
        $sous_total = 0;
        $items_data = [];
        
        foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
            $produit = getProductDetails($item['id_produit'], $pdo);
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
                'prix_unitaire_ht' => round($prix_unitaire / 1.2, 2)
            ];
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
        
        // Insérer la commande
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
                client_type
            ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', 'paypal', NOW(), ?)
        ");
        
        $client_type = ($checkout['is_guest'] ?? false) ? 'guest' : 'registered';
        
        $result = $stmt->execute([
            $client_id,
            $adresse_livraison_id,
            $adresse_facturation_id,
            round($sous_total, 2),
            round($frais_livraison + $frais_emballage, 2),
            $total,
            $client_type
        ]);
        
        if (!$result) {
            throw new Exception("Échec de l'insertion de la commande");
        }
        
        $id_commande = $pdo->lastInsertId();
        
        if (!$id_commande || $id_commande == 0) {
            throw new Exception("ID commande non généré");
        }
        
        // Insérer les articles
        $stmt_item = $pdo->prepare("
            INSERT INTO commande_items (
                id_commande, id_produit, reference_produit, nom_produit,
                quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 20.00)
        ");
        
        foreach ($items_data as $item) {
            $result_item = $stmt_item->execute([
                $id_commande,
                $item['id_produit'],
                $item['reference'],
                $item['nom'],
                $item['quantite'],
                $item['prix_unitaire_ht'],
                $item['prix_unitaire_ttc']
            ]);
            
            if (!$result_item) {
                throw new Exception("Échec insertion article: " . $item['nom']);
            }
        }
        
        // Récupérer le numéro de commande
        $stmt_num = $pdo->prepare("SELECT numero_commande FROM commandes WHERE id_commande = ?");
        $stmt_num->execute([$id_commande]);
        $numero_commande = $stmt_num->fetchColumn();
        
        $pdo->commit();
        
        $_SESSION[SESSION_KEY_COMMANDE] = [
            'id' => $id_commande,
            'numero' => $numero_commande,
            'montant' => $total
        ];
        
        error_log("Commande PayPal créée: ID $id_commande, Montant: $total €");
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erreur création commande PayPal: " . $e->getMessage());
        die("Erreur lors de la création de la commande : " . $e->getMessage());
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
        'numero_commande' => 'TEMP-' . date('Ymd') . '-' . uniqid(),
        'prenom' => $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']['prenom'] ?? '',
        'nom' => $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']['nom'] ?? '',
        'total_ttc' => $total
    ];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Paiement PayPal - HEURE DU CADEAU</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔵 Paiement PayPal</h1>
        
        <div class="commande-info">
            <p style="font-size: 16px; margin-bottom: 5px;">Commande</p>
            <p style="font-size: 20px; font-weight: bold;">#<?= htmlspecialchars($commande['numero_commande'] ?? $id_commande) ?></p>
        </div>
        
        <div class="montant">
            <?= number_format(floatval($commande['total_ttc'] ?? $total), 2, ',', ' ') ?> €
        </div>
        
        <p style="color: #495057; margin: 20px 0;">
            <i class="fas fa-user"></i> 
            <?= htmlspecialchars(($commande['prenon'] ?? $commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) ?>
        </p>
        
        <button class="btn" onclick="simulerPaiement(<?= $id_commande ?>, <?= $commande['total_ttc'] ?? $total ?>)">
            <i class="fas fa-paypal"></i> Payer avec PayPal
        </button>
        
        <a href="paiement.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        
        <div class="secure-badge">
            <i class="fas fa-lock"></i> Paiement 100% sécurisé
        </div>
        
        <div class="details">
            <p>Vous allez être redirigé vers PayPal</p>
            <p>Aucun prélèvement ne sera effectué sans votre confirmation</p>
        </div>
    </div>

    <script src="https://kit.fontawesome.com/your-code.js" crossorigin="anonymous"></script>
    <script>
        function simulerPaiement(commandeId, montant) {
            const btn = document.querySelector('.btn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirection...';
            btn.disabled = true;
            
            // Générer des IDs de test
            const paymentId = 'PAY-' + Date.now() + '-' + Math.random().toString(36).substring(2, 10);
            const payerId = 'PAYER-' + Math.random().toString(36).substring(2, 15);
            
            // Redirection avec l'ID commande
            window.location.href = 'paiement_paypal.php?paymentId=' + paymentId + 
                                   '&PayerID=' + payerId + 
                                   '&status=success' + 
                                   '&commande=' + commandeId;
        }
    </script>
</body>
</html>