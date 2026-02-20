<?php
// ============================================
// PAIEMENT PAYPAL - VERSION FINALE CORRIGÉE
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Vérification de l'étape
if (!isset($_SESSION['checkout']) || $_SESSION['checkout']['etape'] !== 'paiement') {
    header('Location: livraison_form.php');
    exit;
}

// Vérifier que le panier existe
if (!isset($_SESSION['panier']) || empty($_SESSION['panier'])) {
    header('Location: panier.html');
    exit;
}

// ============================================
// CONNEXION BDD
// ============================================
$host = 'localhost';
$dbname = 'heureducadeau';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur connexion BDD paiement_paypal: " . $e->getMessage());
    die("Erreur de connexion à la base de données");
}

// ============================================
// FONCTION DE SYNCHRONISATION PANIER SESSION/BDD
// ============================================
function synchroniserPanierSessionBDD($pdo, $session_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.id_panier, pi.id_produit, pi.quantite, pi.prix_unitaire
            FROM panier p
            LEFT JOIN panier_items pi ON p.id_panier = pi.id_panier
            WHERE p.session_id = ? AND p.statut = 'actif'
        ");
        $stmt->execute([$session_id]);
        $panier_bdd = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($panier_bdd) && empty($_SESSION['panier'])) {
            $_SESSION['panier'] = [];
            $_SESSION['panier_id'] = $panier_bdd[0]['id_panier'];
            
            foreach ($panier_bdd as $item) {
                if ($item['id_produit']) {
                    $_SESSION['panier'][$item['id_produit']] = [
                        'id_produit' => $item['id_produit'],
                        'quantite' => $item['quantite'],
                        'prix' => $item['prix_unitaire']
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur synchronisation panier: " . $e->getMessage());
    }
}

// Synchroniser le panier
synchroniserPanierSessionBDD($pdo, session_id());

// ============================================
// FONCTION POUR OBTENIR LES DÉTAILS D'UN PRODUIT
// ============================================
function getProductDetails($id_produit, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id_produit, nom, prix_ttc, reference, quantite_stock
            FROM produits 
            WHERE id_produit = ?
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if (!$produit) {
            return [
                'id_produit' => $id_produit,
                'nom' => 'Produit #' . $id_produit,
                'prix_ttc' => 19.99,
                'reference' => 'REF' . $id_produit,
                'quantite_stock' => 100
            ];
        }
        
        return $produit;
    } catch (Exception $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return [
            'id_produit' => $id_produit,
            'nom' => 'Produit #' . $id_produit,
            'prix_ttc' => 19.99,
            'reference' => 'REF' . $id_produit,
            'quantite_stock' => 100
        ];
    }
}

// ============================================
// CRÉATION DE LA COMMANDE SI NÉCESSAIRE
// ============================================
if (!isset($_SESSION['commande_en_cours'])) {
    try {
        $pdo->beginTransaction();
        
        // Récupérer les données du checkout
        $checkout = $_SESSION['checkout'];
        $client_id = $checkout['client_id'];
        $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
        $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_livraison_id;
        
        // Calculer le total avec les vrais prix des produits
        $sous_total = 0;
        $items_data = [];
        $verification_stock = true;
        
        foreach ($_SESSION['panier'] as $item) {
            $produit = getProductDetails($item['id_produit'], $pdo);
            $prix_unitaire = floatval($produit['prix_ttc']);
            $quantite = intval($item['quantite']);
            
            // Vérifier le stock
            if ($produit['quantite_stock'] < $quantite) {
                $verification_stock = false;
                throw new Exception("Stock insuffisant pour le produit: " . $produit['nom']);
            }
            
            $sous_total += $prix_unitaire * $quantite;
            
            $items_data[] = [
                'id_produit' => $item['id_produit'],
                'reference' => $produit['reference'],
                'nom' => $produit['nom'],
                'quantite' => $quantite,
                'prix_unitaire_ttc' => $prix_unitaire,
                'prix_unitaire_ht' => round($prix_unitaire / 1.2, 2)
            ];
        }
        
        // Calculer les frais de livraison
        $frais_livraison = 0;
        if ($checkout['mode_livraison'] === 'express') {
            $frais_livraison = 9.90;
        } elseif ($checkout['mode_livraison'] === 'relais') {
            $frais_livraison = 4.90;
        } elseif ($sous_total < 50.00) {
            $frais_livraison = 4.90;
        }
        
        // Emballage cadeau
        $frais_emballage = $checkout['emballage_cadeau'] ? 3.90 : 0;
        
        // Total final
        $total = $sous_total + $frais_livraison + $frais_emballage;
        
        // Insérer la commande
        $stmt = $pdo->prepare("
            INSERT INTO commandes (
                id_client, id_adresse_livraison, id_adresse_facturation,
                sous_total, frais_livraison, total_ttc, statut, statut_paiement,
                mode_paiement, date_commande, client_type, instructions
            ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', 'paypal', NOW(), 'registered', ?)
        ");
        
        $stmt->execute([
            $client_id,
            $adresse_livraison_id,
            $adresse_facturation_id,
            $sous_total,
            $frais_livraison + $frais_emballage,
            $total,
            $checkout['instructions']
        ]);
        
        $id_commande = $pdo->lastInsertId();
        
        // Récupérer le numéro de commande généré par le trigger
        $stmt = $pdo->prepare("SELECT numero_commande FROM commandes WHERE id_commande = ?");
        $stmt->execute([$id_commande]);
        $commande_numero = $stmt->fetchColumn();
        
        // Insérer les articles de la commande
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
                20.00
            ]);
        }
        
        // Mettre à jour le statut du panier en BDD
        if (isset($_SESSION['panier_id']) && strpos($_SESSION['panier_id'], 'session_') === false) {
            $stmt = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
            $stmt->execute([$_SESSION['panier_id']]);
        }
        
        $pdo->commit();
        
        $_SESSION['commande_en_cours'] = [
            'id' => $id_commande,
            'numero' => $commande_numero,
            'montant' => $total
        ];
        
        // Logger la création de commande
        $stmt = $pdo->prepare("
            INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, metadata)
            VALUES ('info', 'info', ?, ?, ?, ?)
        ");
        $stmt->execute([
            'Commande créée avec succès (en attente de paiement PayPal)',
            $client_id,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            json_encode(['commande_id' => $id_commande, 'montant' => $total])
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création commande PayPal: " . $e->getMessage());
        
        // Logger l'erreur
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
                VALUES ('erreur', 'error', ?, ?, ?)
            ");
            $stmt->execute([
                'Erreur création commande PayPal: ' . $e->getMessage(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                json_encode(['error' => $e->getMessage()])
            ]);
        } catch (Exception $logError) {}
        
        die("Erreur lors de la création de la commande. Veuillez réessayer.");
    }
} else {
    $id_commande = $_SESSION['commande_en_cours']['id'];
    $total = $_SESSION['commande_en_cours']['montant'];
}

// Récupérer les informations de la commande
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_commande,
            c.numero_commande,
            c.total_ttc as montant_total,
            c.statut_paiement,
            cl.email,
            cl.prenom,
            cl.nom
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        throw new Exception("Commande non trouvée");
    }
} catch (Exception $e) {
    error_log("Erreur récupération commande: " . $e->getMessage());
    die("Erreur lors de la récupération de la commande");
}

// ============================================
// TRAITEMENT DU RETOUR PAYPAL
// ============================================
if (isset($_GET['paymentId']) && isset($_GET['PayerID']) && isset($_GET['status']) && $_GET['status'] === 'success') {
    
    $payment_id = $_GET['paymentId'];
    $payer_id = $_GET['PayerID'];
    
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour la commande
        $stmt = $pdo->prepare("
            UPDATE commandes 
            SET statut = 'confirmee',
                statut_paiement = 'paye',
                mode_paiement = 'paypal',
                reference_paiement = ?,
                reference_paypal = ?,
                payer_id = ?,
                date_paiement = NOW()
            WHERE id_commande = ?
        ");
        $stmt->execute([$payment_id, $payment_id, $payer_id, $id_commande]);
        
        // Créer la transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions 
            (numero_transaction, id_commande, id_client, montant, methode_paiement,
             reference_paiement, statut, date_creation, ip_client) 
            VALUES (?, ?, ?, ?, 'paypal', ?, 'paye', NOW(), ?)
        ");
        
        $numero_transaction = 'PP_' . date('Ymd') . '_' . uniqid();
        $client_id = $_SESSION['checkout']['client_id'] ?? $commande['id_client'] ?? null;
        $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        $stmt->execute([
            $numero_transaction,
            $id_commande,
            $client_id,
            $commande['montant_total'],
            $payment_id,
            $ip_client
        ]);
        
        // Mettre à jour les ventes et le stock des produits
        $stmt = $pdo->prepare("
            UPDATE produits p
            JOIN commande_items ci ON p.id_produit = ci.id_produit
            SET p.ventes = p.ventes + ci.quantite,
                p.quantite_stock = p.quantite_stock - ci.quantite
            WHERE ci.id_commande = ?
        ");
        $stmt->execute([$id_commande]);
        
        // Mettre à jour le statut du panier en BDD
        if (isset($_SESSION['panier_id']) && strpos($_SESSION['panier_id'], 'session_') === false) {
            $stmt = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
            $stmt->execute([$_SESSION['panier_id']]);
        }
        
        // Logger le succès
        $stmt = $pdo->prepare("
            INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
            VALUES ('paiement', 'info', ?, ?, ?)
        ");
        $stmt->execute([
            'Paiement PayPal réussi pour commande #' . $id_commande,
            $client_id,
            $ip_client
        ]);
        
        $pdo->commit();
        
        // ============================================
        // VIDER LE PANIER - ÉTAPE CRITIQUE
        // ============================================
        unset($_SESSION['panier']);
        unset($_SESSION['checkout']);
        unset($_SESSION['panier_id']);
        unset($_SESSION['commande_en_cours']);
        
        // Rediriger vers confirmation
        header('Location: confirmation.php?commande=' . $id_commande);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur paiement PayPal: " . $e->getMessage());
        
        // Logger l'erreur
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
                VALUES ('paiement', 'error', ?, ?, ?)
            ");
            $stmt->execute([
                'Erreur paiement PayPal: ' . $e->getMessage(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                json_encode(['error' => $e->getMessage(), 'commande' => $id_commande])
            ]);
        } catch (Exception $logError) {}
        
        die("Erreur lors de l'enregistrement du paiement");
    }
}

// ============================================
// PAGE DE SIMULATION PAYPAL
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement PayPal - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 450px;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .paypal-logo {
            margin: 30px 0;
            font-size: 48px;
            color: #003087;
        }
        .paypal-logo i {
            background: #003087;
            color: white;
            padding: 15px;
            border-radius: 50%;
        }
        .details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: left;
        }
        .details p {
            margin: 10px 0;
        }
        .btn-paypal {
            background: #003087;
            color: white;
            padding: 18px 30px;
            border: none;
            border-radius: 50px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-paypal:hover {
            background: #00276c;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0,48,135,0.3);
        }
        .btn-paypal:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .btn-paypal i {
            font-size: 24px;
        }
        .secure {
            margin-top: 25px;
            color: #7f8c8d;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Paiement PayPal</h1>
        
        <div class="paypal-logo">
            <i class="fab fa-paypal"></i>
        </div>
        
        <div class="details">
            <p><strong>Commande #<?= htmlspecialchars($commande['numero_commande'] ?? $id_commande) ?></strong></p>
            <p style="font-size: 24px; color: #003087; font-weight: bold;">
                Total : <?= number_format($commande['montant_total'] ?? $total, 2, ',', ' ') ?> €
            </p>
            <p><i class="fas fa-user"></i> <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></p>
        </div>

        <p>Vous allez être redirigé vers PayPal pour finaliser votre paiement en toute sécurité.</p>

        <!-- SIMULATION : Bouton de redirection vers le retour simulé -->
        <button class="btn-paypal" id="paypalBtn">
            <i class="fab fa-paypal"></i>
            Payer avec PayPal
        </button>
        
        <div class="secure">
            <i class="fas fa-lock"></i> Paiement sécurisé par PayPal
        </div>
    </div>

    <script>
        document.getElementById('paypalBtn').addEventListener('click', function(e) {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirection...';
            
            // SIMULATION : Redirection vers le retour simulé
            const paymentId = 'PAY-' + Date.now() + '-' + Math.random().toString(36).substring(7);
            const payerId = 'PAYER-' + Math.random().toString(36).substring(7);
            
            setTimeout(() => {
                window.location.href = 'paiement_paypal.php?paymentId=' + paymentId + 
                                       '&PayerID=' + payerId + 
                                       '&status=success&commande=<?= $id_commande ?>';
            }, 1500);
        });
    </script>
</body>
</html>