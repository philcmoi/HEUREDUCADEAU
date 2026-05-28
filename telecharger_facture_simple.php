<?php
// telecharger-facture-simple.php - Version simplifiée avec impression PDF

session_start();

$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if ($commande_id <= 0) {
    die('ID commande invalide');
}

// Connexion directe BDD
$host = 'localhost';
$dbname = 'heureducadeau';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Récupérer la commande
    $stmt = $pdo->prepare("
        SELECT c.*, cl.email, cl.nom as client_nom, cl.prenom as client_prenom
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        die('Commande non trouvée');
    }
    
    // Vérification d'autorisation simplifiée
    $autorise = false;
    if (isset($_SESSION['commande_recente']) && $_SESSION['commande_recente'] == $commande_id) {
        $autorise = true;
    }
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == $commande['id_client']) {
        $autorise = true;
    }
    if (isset($_SESSION['admin_id'])) {
        $autorise = true;
    }
    
    // TEMPORAIRE : à supprimer en production
    $autorise = true;
    
    if (!$autorise) {
        die('Accès non autorisé');
    }
    
    // Récupérer les articles
    $stmt_items = $pdo->prepare("SELECT * FROM commande_items WHERE id_commande = ?");
    $stmt_items->execute([$commande_id]);
    $items = $stmt_items->fetchAll();
    
    // Récupérer les adresses
    $stmt_addr = $pdo->prepare("
        SELECT a.nom, a.prenom, a.adresse, a.complement, a.code_postal, a.ville, a.pays, a.telephone
        FROM adresses a
        WHERE a.id_adresse = ?
    ");
    $stmt_addr->execute([$commande['id_adresse_livraison']]);
    $adresse = $stmt_addr->fetch();
    
    if ($adresse) {
        $commande = array_merge($commande, $adresse);
    }
    
    // Calculer le total
    $total = 0;
    foreach ($items as $item) {
        $total += $item['quantite'] * $item['prix_unitaire_ttc'];
    }
    
    $frais_livraison = floatval($commande['frais_livraison'] ?? 0);
    $total_ttc = floatval($commande['total_ttc'] ?? $total);
    
    // Déterminer le mode de paiement
    $mode_paiement = $commande['mode_paiement'] ?? 'carte';
    $mode_paiement_label = '';
    switch ($mode_paiement) {
        case 'paypal': $mode_paiement_label = 'PayPal'; break;
        case 'carte': $mode_paiement_label = 'Carte bancaire'; break;
        default: $mode_paiement_label = ucfirst($mode_paiement);
    }
    
    // ============================================
    // GÉNÉRATION DE LA FACTURE HTML
    // ============================================
    
    header('Content-Type: text/html; charset=utf-8');
    
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Facture <?= htmlspecialchars($commande['numero_commande']) ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            
            body {
                font-family: 'Helvetica Neue', Arial, sans-serif;
                background: #f5f5f5;
                padding: 30px;
            }
            
            .facture {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                border-radius: 10px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                overflow: hidden;
            }
            
            .facture-content {
                padding: 40px;
            }
            
            /* En-tête */
            .header {
                border-bottom: 3px solid #2c3e50;
                padding-bottom: 20px;
                margin-bottom: 30px;
                position: relative;
            }
            
            .logo {
                font-size: 28px;
                font-weight: bold;
                color: #2c3e50;
            }
            
            .logo span { color: #e74c3c; }
            
            .facture-title {
                position: absolute;
                top: 0;
                right: 0;
                text-align: right;
            }
            
            .facture-title h1 {
                font-size: 24px;
                color: #2c3e50;
            }
            
            .facture-number {
                color: #7f8c8d;
                font-size: 12px;
            }
            
            /* Sections info */
            .info-section {
                display: flex;
                gap: 30px;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }
            
            .info-box {
                flex: 1;
                background: #f8f9fa;
                padding: 15px;
                border-radius: 8px;
            }
            
            .info-box h3 {
                font-size: 14px;
                color: #2c3e50;
                margin-bottom: 10px;
                padding-bottom: 5px;
                border-bottom: 2px solid #e0e0e0;
            }
            
            .info-box p {
                margin: 5px 0;
                font-size: 12px;
                color: #555;
            }
            
            /* Tableau */
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }
            
            th {
                background: #2c3e50;
                color: white;
                padding: 12px;
                text-align: left;
                font-size: 13px;
            }
            
            td {
                padding: 12px;
                border-bottom: 1px solid #e0e0e0;
                font-size: 13px;
            }
            
            .total-row {
                display: flex;
                justify-content: flex-end;
                gap: 30px;
                padding: 5px 0;
            }
            
            .grand-total {
                font-size: 18px;
                font-weight: bold;
                color: #e74c3c;
                border-top: 2px solid #e0e0e0;
                margin-top: 10px;
                padding-top: 10px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 15px;
                font-size: 11px;
                font-weight: bold;
                background: #d4edda;
                color: #155724;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #e0e0e0;
                text-align: center;
                font-size: 10px;
                color: #999;
            }
            
            .print-btn {
                text-align: center;
                padding: 15px;
                background: #f8f9fa;
                border-bottom: 1px solid #e0e0e0;
            }
            
            .print-btn button {
                background: #2c3e50;
                color: white;
                border: none;
                padding: 10px 30px;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            
            .print-btn button:hover {
                background: #1a252f;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .print-btn {
                    display: none;
                }
                
                .facture {
                    box-shadow: none;
                    border-radius: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="facture">
            <div class="print-btn">
                <button onclick="window.print();">
                    🖨️ Imprimer / Télécharger en PDF
                </button>
            </div>
            
            <div class="facture-content">
                <div class="header">
                    <div class="logo">HEURE DU <span>CADEAU</span></div>
                    <div class="facture-title">
                        <h1>FACTURE</h1>
                        <div class="facture-number">N° <?= htmlspecialchars($commande['numero_commande']) ?></div>
                    </div>
                </div>
                
                <div class="info-section">
                    <div class="info-box">
                        <h3>📦 LIVRAISON</h3>
                        <p><strong><?= htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) ?></strong></p>
                        <p><?= nl2br(htmlspecialchars($commande['adresse'] ?? '')) ?></p>
                        <?php if (!empty($commande['complement'])): ?>
                        <p><?= htmlspecialchars($commande['complement']) ?></p>
                        <?php endif; ?>
                        <p><?= htmlspecialchars(($commande['code_postal'] ?? '') . ' ' . ($commande['ville'] ?? '')) ?></p>
                        <p><?= htmlspecialchars($commande['pays'] ?? 'France') ?></p>
                        <?php if (!empty($commande['telephone'])): ?>
                        <p>📞 <?= htmlspecialchars($commande['telephone']) ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="info-box">
                        <h3>🧾 FACTURE</h3>
                        <p><strong>Date :</strong> <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
                        <p><strong>Mode :</strong> <?= $mode_paiement_label ?></p>
                        <p><strong>Statut :</strong> <span class="status-badge">Payé</span></p>
                        <p><strong>Email :</strong> <?= htmlspecialchars($commande['email']) ?></p>
                        <p><strong>Référence :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Produit</th>
                            <th>Référence</th>
                            <th>Qté</th>
                            <th>Prix unitaire</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['nom_produit']) ?></td>
                            <td><?= htmlspecialchars($item['reference_produit']) ?></td>
                            <td><?= $item['quantite'] ?></td>
                            <td><?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €</td>
                            <td><strong><?= number_format($prix_total, 2, ',', ' ') ?> €</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: right;">
                    <div class="total-row">
                        <span>Sous-total</span>
                        <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="total-row">
                        <span>Frais de livraison</span>
                        <span><?= number_format($frais_livraison, 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>TOTAL TTC</span>
                        <span><?= number_format($total_ttc, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                
                <div class="footer">
                    <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
                    <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
                    <p>SIRET : 123 456 789 00010 - TVA : FR12345678901</p>
                </div>
            </div>
        </div>
        
        <script>
            // Décommenter pour imprimer automatiquement
            // setTimeout(function() { window.print(); }, 500);
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>