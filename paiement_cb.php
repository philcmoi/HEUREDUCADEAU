<?php
// ============================================
// PAIEMENT PAR CARTE BANCAIRE - VERSION CORRIGÉE
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
// CRÉATION DE LA COMMANDE SI NÉCESSAIRE
// ============================================
if (!isset($_SESSION[SESSION_KEY_COMMANDE])) {
    try {
        $pdo->beginTransaction();
        
        // Récupérer les données du checkout
        $checkout = $_SESSION[SESSION_KEY_CHECKOUT];
        $client_id = $checkout['client_id'];
        $adresse_livraison_id = $checkout['adresse_livraison']['id'] ?? null;
        $adresse_facturation_id = $checkout['adresse_facturation']['id'] ?? $adresse_livraison_id;
        
        // Calculer le total avec les vrais prix des produits
        $sous_total = 0;
        $items_data = [];
        $verification_stock = true;
        
        foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
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
            ) VALUES (?, ?, ?, ?, ?, ?, 'en_attente', 'en_attente', 'carte', NOW(), 'registered', ?)
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
        
        if (!$id_commande) {
            throw new Exception("Échec de la création de la commande - ID non généré");
        }
        
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
            $result = $stmt_item->execute([
                $id_commande,
                $item['id_produit'],
                $item['reference'],
                $item['nom'],
                $item['quantite'],
                $item['prix_unitaire_ht'],
                $item['prix_unitaire_ttc'],
                20.00
            ]);
            
            if (!$result) {
                throw new Exception("Échec insertion article: " . $item['nom']);
            }
        }
        
        // Mettre à jour le statut du panier en BDD
        if (isset($_SESSION[SESSION_KEY_PANIER_ID]) && strpos($_SESSION[SESSION_KEY_PANIER_ID], 'session_') === false) {
            $stmt = $pdo->prepare("UPDATE panier SET statut = 'valide' WHERE id_panier = ?");
            $stmt->execute([$_SESSION[SESSION_KEY_PANIER_ID]]);
        }
        
        $pdo->commit();
        
        $_SESSION[SESSION_KEY_COMMANDE] = [
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
            'Commande créée avec succès (en attente de paiement)',
            $client_id,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            json_encode(['commande_id' => $id_commande, 'montant' => $total])
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création commande CB: " . $e->getMessage());
        
        // Logger l'erreur
        try {
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
                VALUES ('erreur', 'error', ?, ?, ?)
            ");
            $stmt->execute([
                'Erreur création commande CB: ' . $e->getMessage(),
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                json_encode(['error' => $e->getMessage()])
            ]);
        } catch (Exception $logError) {}
        
        die("Erreur lors de la création de la commande. Veuillez réessayer.");
    }
} else {
    $id_commande = $_SESSION[SESSION_KEY_COMMANDE]['id'];
    $total = $_SESSION[SESSION_KEY_COMMANDE]['montant'];
}

// Récupérer les informations de la commande
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id_commande,
            c.numero_commande,
            c.total_ttc as montant_total,
            c.statut,
            cl.id_client,
            cl.email,
            cl.prenom,
            cl.nom,
            a.adresse as adresse_livraison,
            a.code_postal,
            a.ville
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        throw new Exception("Commande non trouvée");
    }
} catch (Exception $e) {
    error_log("Erreur récupération commande CB: " . $e->getMessage());
    die("Erreur lors de la récupération de la commande");
}

// Traitement du formulaire de paiement
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_paiement_cb') {
    
    $numero_carte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $date_expiration = $_POST['date_expiration'] ?? '';
    $cryptogramme = $_POST['cryptogramme'] ?? '';
    $titulaire = $_POST['titulaire_carte'] ?? '';
    
    if (strlen($numero_carte) < 16 || !ctype_digit($numero_carte)) {
        $erreurs[] = "Numéro de carte invalide";
    }
    
    if (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $date_expiration)) {
        $erreurs[] = "Date d'expiration invalide";
    }
    
    if (strlen($cryptogramme) !== 3 || !ctype_digit($cryptogramme)) {
        $erreurs[] = "Cryptogramme invalide";
    }
    
    if (empty($erreurs)) {
        // SIMULATION POUR TEST - En production, appeler l'API bancaire
        sleep(1);
        
        $reference = 'CARD_' . time() . '_' . uniqid();
        
        try {
            $pdo->beginTransaction();
            
            // Mettre à jour la commande
            $stmt = $pdo->prepare("
                UPDATE commandes 
                SET statut = 'confirmee',
                    statut_paiement = 'paye',
                    mode_paiement = 'carte',
                    reference_paiement = ?,
                    date_paiement = NOW()
                WHERE id_commande = ?
            ");
            $stmt->execute([$reference, $id_commande]);
            
            // Créer la transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions 
                (numero_transaction, id_commande, id_client, montant, methode_paiement, 
                 reference_paiement, statut, date_creation, ip_client) 
                VALUES (?, ?, ?, ?, 'carte', ?, 'paye', NOW(), ?)
            ");
            
            $numero_transaction = 'TRX_' . date('Ymd') . '_' . uniqid();
            $client_id = $_SESSION[SESSION_KEY_CLIENT_ID] ?? $commande['id_client'];
            $ip_client = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            
            $stmt->execute([
                $numero_transaction,
                $id_commande,
                $client_id,
                $commande['montant_total'],
                $reference,
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
            
            // Logger le succès
            $stmt = $pdo->prepare("
                INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address)
                VALUES ('paiement', 'info', ?, ?, ?)
            ");
            $stmt->execute([
                'Paiement CB réussi pour commande #' . $id_commande,
                $client_id,
                $ip_client
            ]);
            
            $pdo->commit();
            
            // ============================================
            // VIDER LE PANIER - ÉTAPE CRITIQUE
            // ============================================
            cleanUserSession();
            
            // Rediriger vers confirmation
            header('Location: confirmation.php?commande=' . $id_commande);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Erreur paiement CB: " . $e->getMessage());
            
            // Logger l'erreur
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO logs (type_log, niveau, message, ip_address, metadata)
                    VALUES ('paiement', 'error', ?, ?, ?)
                ");
                $stmt->execute([
                    'Erreur paiement CB: ' . $e->getMessage(),
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    json_encode(['error' => $e->getMessage(), 'commande' => $id_commande])
                ]);
            } catch (Exception $logError) {}
            
            $erreurs[] = "Erreur lors de l'enregistrement du paiement";
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
    <title>Paiement Carte Bancaire - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* STYLES CSS COMPLETS */
        body { 
            font-family: Arial, sans-serif; 
            background: #f9f9f9; 
            margin: 0; 
            padding: 40px 20px; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
        }
        .container { 
            max-width: 500px; 
            background: white; 
            padding: 40px; 
            border-radius: 12px; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.1); 
            width: 100%;
        }
        h1 { 
            color: #5a67d8; 
            margin-bottom: 30px; 
            text-align: center;
            font-size: 28px;
        }
        .details { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 25px;
            border-left: 4px solid #5a67d8;
        }
        .details p {
            margin: 8px 0;
            color: #2d3748;
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
        input { 
            width: 100%; 
            padding: 12px 15px; 
            border: 2px solid #e2e8f0; 
            border-radius: 8px; 
            box-sizing: border-box;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        input:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }
        .form-row { 
            display: flex; 
            gap: 15px; 
        }
        .form-row .form-group { 
            flex: 1; 
        }
        .btn { 
            background: #5a67d8; 
            color: white; 
            padding: 16px 30px; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            width: 100%; 
            font-size: 18px;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 15px;
        }
        .btn:hover { 
            background: #4c51bf; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(90,103,216,0.3);
        }
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .error { 
            color: #c53030; 
            margin-bottom: 20px; 
            padding: 15px; 
            background: #fff5f5; 
            border-radius: 8px;
            border-left: 4px solid #c53030;
        }
        .error p {
            margin: 5px 0;
        }
        .card-icons {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            font-size: 32px;
            color: #718096;
        }
        .secure-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
        .secure-badge i {
            color: #38a169;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-credit-card" style="margin-right: 10px;"></i> Paiement sécurisé</h1>
        
        <div class="details">
            <p><strong><i class="fas fa-file-invoice"></i> Commande #<?= htmlspecialchars($commande['numero_commande'] ?? $id_commande) ?></strong></p>
            <p style="font-size: 20px; color: #5a67d8;">
                <strong>Montant : <?= number_format($commande['montant_total'] ?? $total, 2, ',', ' ') ?> €</strong>
            </p>
            <p><i class="fas fa-user"></i> <?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?></p>
        </div>

        <?php if (!empty($erreurs)): ?>
            <div class="error">
                <?php foreach ($erreurs as $erreur): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($erreur) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="paymentForm">
            <input type="hidden" name="action" value="traiter_paiement_cb">
            <input type="hidden" name="id_commande" value="<?= htmlspecialchars($id_commande) ?>">
            
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Numéro de carte</label>
                <input type="text" name="numero_carte" id="numero_carte" placeholder="1234 5678 9012 3456" maxlength="19" required>
                <div class="card-icons">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-amex"></i>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-calendar"></i> Date d'expiration</label>
                    <input type="text" name="date_expiration" id="date_expiration" placeholder="MM/AA" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Cryptogramme</label>
                    <input type="text" name="cryptogramme" id="cryptogramme" placeholder="123" maxlength="3" required>
                </div>
            </div>
            
            <div class="form-group">
                <label><i class="fas fa-user"></i> Nom du titulaire</label>
                <input type="text" name="titulaire_carte" id="titulaire_carte" value="<?= htmlspecialchars($commande['prenom'] . ' ' . $commande['nom']) ?>" required>
            </div>
            
            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-lock"></i> Payer <?= number_format($commande['montant_total'] ?? $total, 2, ',', ' ') ?> €
            </button>
        </form>
        
        <div class="secure-badge">
            <i class="fas fa-shield-alt"></i>
            <span>Paiement 100% sécurisé - Cryptage SSL 256-bit</span>
        </div>
    </div>

    <script>
        // Formatage numéro de carte
        document.getElementById('numero_carte').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            if (v.length > 16) v = v.substr(0, 16);
            let f = '';
            for (let i = 0; i < v.length; i++) {
                if (i > 0 && i % 4 === 0) f += ' ';
                f += v[i];
            }
            e.target.value = f;
        });

        // Formatage date expiration
        document.getElementById('date_expiration').addEventListener('input', function(e) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length >= 2) {
                let month = v.substr(0, 2);
                if (parseInt(month) > 12) month = '12';
                e.target.value = month + '/' + v.substr(2, 2);
            }
        });

        // Formatage cryptogramme
        document.getElementById('cryptogramme').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substr(0, 3);
        });
        
        // Empêcher la soumission multiple
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
        });
    </script>
</body>
</html>