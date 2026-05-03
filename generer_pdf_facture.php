<?php
// ============================================
// GENERATEUR DE FACTURE PDF (Sans TCPDF)
// Utilise l'impression HTML/CSS pour générer le PDF
// ============================================

/**
 * Génère une facture au format HTML pour impression/PDF
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 * @return string HTML de la facture
 */
function genererFactureHTML($commande, $items, $transaction = null) {
    // Calcul du total
    $total = 0;
    foreach ($items as $item) {
        $total += $item['quantite'] * $item['prix_unitaire_ttc'];
    }
    
    // Déterminer le mode de paiement
    $mode_paiement = $commande['mode_paiement'] ?? 'carte';
    $mode_paiement_label = '';
    switch ($mode_paiement) {
        case 'paypal':
            $mode_paiement_label = 'PayPal';
            break;
        case 'carte':
            $mode_paiement_label = 'Carte bancaire';
            break;
        case 'virement':
            $mode_paiement_label = 'Virement bancaire';
            break;
        default:
            $mode_paiement_label = ucfirst($mode_paiement);
    }
    
    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture ' . htmlspecialchars($commande['numero_commande'] ?? 'N/A') . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Helvetica Neue", Arial, sans-serif;
            background: #fff;
            color: #333;
            line-height: 1.4;
        }
        
        .facture-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* En-tête */
        .header {
            border-bottom: 3px solid #2c3e50;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .logo span {
            color: #e74c3c;
        }
        
        .facture-title {
            text-align: right;
            margin-top: -45px;
        }
        
        .facture-title h1 {
            font-size: 24px;
            color: #2c3e50;
        }
        
        .facture-number {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        /* Informations */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 20px;
        }
        
        .info-box {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .info-box h3 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .info-box p {
            margin: 5px 0;
            font-size: 13px;
            color: #555;
        }
        
        /* Tableau des articles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .items-table th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
        }
        
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }
        
        .items-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Totaux */
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        
        .total-row {
            padding: 8px 0;
            display: flex;
            justify-content: flex-end;
            gap: 30px;
        }
        
        .total-row span:first-child {
            font-weight: normal;
            color: #666;
        }
        
        .total-row span:last-child {
            min-width: 100px;
            text-align: right;
        }
        
        .grand-total {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
            border-top: 2px solid #e0e0e0;
            margin-top: 10px;
            padding-top: 10px;
        }
        
        /* Pied de page */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        
        /* Badge de statut */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }
        
        .status-paye {
            background: #d4edda;
            color: #155724;
        }
        
        /* Responsive */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .facture-container {
                padding: 20px;
            }
            
            .no-print {
                display: none;
            }
        }
        
        /* Bouton impression */
        .print-btn {
            text-align: center;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="facture-container">
        <div class="print-btn no-print">
            <button onclick="window.print();">
                <i>🖨️</i> Imprimer / Télécharger en PDF
            </button>
        </div>
        
        <div class="header">
            <div class="logo">
                HEURE DU <span>CADEAU</span>
            </div>
            <div class="facture-title">
                <h1>FACTURE</h1>
                <div class="facture-number">N° ' . htmlspecialchars($commande['numero_commande'] ?? 'N/A') . '</div>
            </div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>📦 EXPÉDITION</h3>
                <p><strong>' . htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) . '</strong></p>
                <p>' . nl2br(htmlspecialchars($commande['adresse'] ?? '')) . '</p>
                ' . (!empty($commande['complement']) ? '<p>' . htmlspecialchars($commande['complement']) . '</p>' : '') . '
                <p>' . htmlspecialchars(($commande['code_postal'] ?? '') . ' ' . ($commande['ville'] ?? '')) . '</p>
                <p>' . htmlspecialchars($commande['pays'] ?? 'France') . '</p>
                ' . (!empty($commande['telephone']) ? '<p>📞 ' . htmlspecialchars($commande['telephone']) . '</p>' : '') . '
            </div>
            
            <div class="info-box">
                <h3>🧾 FACTURE</h3>
                <p><strong>Date :</strong> ' . date('d/m/Y', strtotime($commande['date_commande'] ?? 'now')) . '</p>
                <p><strong>Mode de paiement :</strong> ' . $mode_paiement_label . '</p>
                <p><strong>Statut :</strong> <span class="status-badge status-paye">Payé</span></p>
                ' . (!empty($transaction['reference_paiement']) ? '<p><strong>Transaction :</strong> ' . htmlspecialchars(substr($transaction['reference_paiement'], 0, 20)) . '...</p>' : '') . '
                ' . (!empty($commande['email']) ? '<p><strong>Email :</strong> ' . htmlspecialchars($commande['email']) . '</p>' : '') . '
            </div>
        </div>
        
        <table class="items-table">
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Référence</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['nom_produit']) . '</td>
                    <td>' . htmlspecialchars($item['reference_produit'] ?? '-') . '</td>
                    <td>' . $item['quantite'] . '</td>
                    <td>' . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €</td>
                    <td><strong>' . number_format($prix_total, 2, ',', ' ') . ' €</strong></td>
                </tr>';
    }
    
    $frais_livraison = floatval($commande['frais_livraison'] ?? 0);
    $total_ttc = floatval($commande['total_ttc'] ?? $total);
    
    $html .= '
            </tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Sous-total</span>
                <span>' . number_format($total, 2, ',', ' ') . ' €</span>
            </div>
            <div class="total-row">
                <span>Frais de livraison</span>
                <span>' . number_format($frais_livraison, 2, ',', ' ') . ' €</span>
            </div>
            <div class="total-row grand-total">
                <span>TOTAL TTC</span>
                <span>' . number_format($total_ttc, 2, ',', ' ') . ' €</span>
            </div>
        </div>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
            <p>SIRET : 123 456 789 00010 - TVA : FR12345678901</p>
            <p>© ' . date('Y') . ' HEURE DU CADEAU - Tous droits réservés</p>
        </div>
    </div>
    
    <script>
        // Auto-impression au chargement (optionnel)
        // setTimeout(function() { window.print(); }, 500);
    </script>
</body>
</html>';
    
    return $html;
}

/**
 * Génère et force le téléchargement de la facture
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 */
function telechargerFactureHTML($commande, $items, $transaction = null) {
    $html = genererFactureHTML($commande, $items, $transaction);
    
    // Définir les headers pour le téléchargement
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="facture_' . htmlspecialchars($commande['numero_commande'] ?? 'N/A') . '.html"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    
    echo $html;
    exit;
}

/**
 * Affiche la facture dans le navigateur (pour visualisation/impression)
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 */
function afficherFactureHTML($commande, $items, $transaction = null) {
    $html = genererFactureHTML($commande, $items, $transaction);
    
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

// Si le fichier est appelé directement
if (basename($_SERVER['PHP_SELF']) == 'generer_pdf_facture.php') {
    // Rediriger vers la page d'accueil si accès direct
    header('Location: index.html');
    exit;
}
?>