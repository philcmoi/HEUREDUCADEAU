<?php
// generer_pdf_facture.php - Génération de facture PDF avec TCPDF
// VERSION CORRIGÉE - PREND EN COMPTE LES PRIX PROMOTIONNELS

define('TCPDF_AVAILABLE', false);

// Vérifier si TCPDF est disponible
$tcpdf_paths = [
    __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    __DIR__ . '/tcpdf/tcpdf.php',
    __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php'
];

foreach ($tcpdf_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        define('TCPDF_AVAILABLE', true);
        break;
    }
}

/**
 * Génère le HTML de la facture (utilisé pour l'email et le fallback PDF)
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 * @return string HTML de la facture
 */
function genererFactureHTML($commande, $items, $transaction = null) {
    $total = 0;
    $total_ht = 0;
    $total_tva = 0;
    
    // Calculer les totaux avec les prix promotionnels déjà stockés
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $total += $prix_total;
        
        // Calculer HT et TVA à partir du prix TTC (TVA 20% par défaut)
        $prix_ht = $item['prix_unitaire_ttc'] / 1.2;
        $total_ht += $item['quantite'] * $prix_ht;
        $total_tva += $item['quantite'] * ($item['prix_unitaire_ttc'] - $prix_ht);
    }
    
    $frais_livraison = floatval($commande['frais_livraison'] ?? 0);
    $total_ttc = floatval($commande['total_ttc'] ?? $total);
    
    $mode_paiement = $commande['mode_paiement'] ?? 'carte';
    $mode_paiement_label = '';
    switch ($mode_paiement) {
        case 'paypal': $mode_paiement_label = 'PayPal'; break;
        case 'carte': $mode_paiement_label = 'Carte bancaire'; break;
        default: $mode_paiement_label = ucfirst($mode_paiement);
    }
    
    $statut_paiement = $commande['statut_paiement'] ?? 'paye';
    $statut_label = $statut_paiement === 'paye' ? 'Payé' : 'En attente';
    $statut_class = $statut_paiement === 'paye' ? '#27ae60' : '#f39c12';
    
    // Récupérer l'adresse de livraison
    $livraison_nom = $commande['livraison_nom'] ?? ($commande['nom'] ?? '');
    $livraison_prenom = $commande['livraison_prenom'] ?? ($commande['prenom'] ?? '');
    $livraison_adresse = $commande['livraison_adresse'] ?? ($commande['adresse'] ?? '');
    $livraison_complement = $commande['livraison_complement'] ?? ($commande['complement'] ?? '');
    $livraison_code_postal = $commande['livraison_code_postal'] ?? ($commande['code_postal'] ?? '');
    $livraison_ville = $commande['livraison_ville'] ?? ($commande['ville'] ?? '');
    $livraison_pays = $commande['livraison_pays'] ?? ($commande['pays'] ?? 'France');
    $livraison_telephone = $commande['livraison_telephone'] ?? ($commande['telephone'] ?? '');
    
    // Récupérer l'adresse de facturation (ou utiliser livraison par défaut)
    $facturation_nom = $commande['facturation_nom'] ?? $livraison_nom;
    $facturation_prenom = $commande['facturation_prenom'] ?? $livraison_prenom;
    $facturation_adresse = $commande['facturation_adresse'] ?? $livraison_adresse;
    $facturation_complement = $commande['facturation_complement'] ?? $livraison_complement;
    $facturation_code_postal = $commande['facturation_code_postal'] ?? $livraison_code_postal;
    $facturation_ville = $commande['facturation_ville'] ?? $livraison_ville;
    $facturation_pays = $commande['facturation_pays'] ?? $livraison_pays;
    
    // Date de la commande
    $date_commande = isset($commande['date_commande']) ? date('d/m/Y H:i', strtotime($commande['date_commande'])) : date('d/m/Y H:i');
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture ' . htmlspecialchars($commande['numero_commande'] ?? 'N/A') . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; 
            font-size: 11pt;
            line-height: 1.4;
            color: #333;
            margin: 20px;
        }
        .facture {
            max-width: 800px;
            margin: 0 auto;
            background: white;
        }
        .header {
            border-bottom: 3px solid #2c3e50;
            padding: 15px 0;
            margin-bottom: 25px;
            overflow: hidden;
        }
        .logo {
            float: left;
            font-size: 22pt;
            font-weight: bold;
            color: #2c3e50;
        }
        .logo span { color: #e74c3c; }
        .facture-title {
            float: right;
            text-align: right;
        }
        .facture-title h1 {
            font-size: 20pt;
            color: #2c3e50;
            margin: 0;
        }
        .facture-number {
            font-size: 9pt;
            color: #7f8c8d;
        }
        .clear { clear: both; }
        
        .info-section {
            margin-bottom: 25px;
            overflow: hidden;
        }
        .info-box {
            width: 48%;
            float: left;
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 10pt;
        }
        .info-box.right { float: right; }
        .info-box h3 {
            font-size: 11pt;
            color: #2c3e50;
            margin-bottom: 8px;
            padding-bottom: 4px;
            border-bottom: 1px solid #ddd;
        }
        .info-box p { margin: 4px 0; color: #555; }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9pt;
            font-weight: bold;
            background: ' . $statut_class . '20;
            color: ' . $statut_class . ';
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th {
            background: #2c3e50;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 10pt;
        }
        td {
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 10pt;
        }
        .text-right { text-align: right; }
        
        .totals {
            width: 300px;
            margin-left: auto;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        .grand-total {
            font-size: 13pt;
            font-weight: bold;
            color: #e74c3c;
            border-top: 2px solid #ddd;
            margin-top: 8px;
            padding-top: 8px;
        }
        
        .footer {
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
        
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="facture">
        <div class="header">
            <div class="logo">HEURE DU <span>CADEAU</span></div>
            <div class="facture-title">
                <h1>FACTURE</h1>
                <div class="facture-number">N° ' . htmlspecialchars($commande['numero_commande'] ?? 'N/A') . '</div>
            </div>
            <div class="clear"></div>
        </div>
        
        <div class="info-section">
            <div class="info-box">
                <h3>📦 LIVRAISON</h3>
                <p><strong>' . htmlspecialchars($livraison_prenom . ' ' . $livraison_nom) . '</strong></p>
                <p>' . nl2br(htmlspecialchars($livraison_adresse)) . '</p>';
    
    if (!empty($livraison_complement)) {
        $html .= '<p>' . htmlspecialchars($livraison_complement) . '</p>';
    }
    
    $html .= '<p>' . htmlspecialchars($livraison_code_postal . ' ' . $livraison_ville) . '</p>
                <p>' . htmlspecialchars($livraison_pays) . '</p>';
    
    if (!empty($livraison_telephone)) {
        $html .= '<p>📞 ' . htmlspecialchars($livraison_telephone) . '</p>';
    }
    
    $html .= '</div>
            
            <div class="info-box right">
                <h3>🧾 FACTURE</h3>
                <p><strong>Date :</strong> ' . $date_commande . '</p>
                <p><strong>Mode de paiement :</strong> ' . $mode_paiement_label . '</p>
                <p><strong>Statut :</strong> <span class="status-badge">' . $statut_label . '</span></p>
                <p><strong>Email :</strong> ' . htmlspecialchars($commande['email'] ?? '') . '</p>
                <p><strong>Référence transaction :</strong> ' . htmlspecialchars(substr($commande['reference_paiement'] ?? ($transaction['reference_paiement'] ?? ''), 0, 20)) . '...</p>
            </div>
            <div class="clear"></div>
        </div>';
    
    // S'il y a une adresse de facturation différente
    if ($facturation_adresse != $livraison_adresse && !empty($facturation_adresse)) {
        $html .= '<div class="info-section">
            <div class="info-box">
                <h3>🏢 FACTURATION</h3>
                <p><strong>' . htmlspecialchars($facturation_prenom . ' ' . $facturation_nom) . '</strong></p>
                <p>' . nl2br(htmlspecialchars($facturation_adresse)) . '</p>';
        
        if (!empty($facturation_complement)) {
            $html .= '<p>' . htmlspecialchars($facturation_complement) . '</p>';
        }
        
        $html .= '<p>' . htmlspecialchars($facturation_code_postal . ' ' . $facturation_ville) . '</p>
                <p>' . htmlspecialchars($facturation_pays) . '</p>
            </div>
            <div class="clear"></div>
        </div>';
    }
    
    $html .= '<table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Référence</th>
                    <th class="text-right">Qté</th>
                    <th class="text-right">Prix unitaire</th>
                    <th class="text-right">Total TTC</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($items as $item) {
        $prix_unitaire = floatval($item['prix_unitaire_ttc']);
        $quantite = intval($item['quantite']);
        $prix_total = $quantite * $prix_unitaire;
        
        // Vérifier si une promotion a été appliquée (stockée dans options)
        $has_promotion = false;
        $prix_original = $prix_unitaire;
        $reduction_percent = 0;
        
        if (isset($item['options']) && !empty($item['options'])) {
            $options = json_decode($item['options'], true);
            if (isset($options['prix_original']) && $options['prix_original'] > $prix_unitaire) {
                $has_promotion = true;
                $prix_original = $options['prix_original'];
                $reduction_percent = $options['reduction_percent'] ?? round((1 - $prix_unitaire / $prix_original) * 100);
            }
        }
        
        $html .= '<tr>
                <td>' . htmlspecialchars($item['nom_produit']);
        
        if ($has_promotion) {
            $html .= '<br><small style="color: #e74c3c;">✓ Promotion -' . $reduction_percent . '% appliquée</small>';
        }
        
        $html .= '</td>
                <td>' . htmlspecialchars($item['reference_produit']) . '</td>
                <td class="text-right">' . $quantite . '</td>
                <td class="text-right">';
        
        if ($has_promotion) {
            $html .= '<span style="text-decoration: line-through; color: #999; font-size: 9pt;">' . number_format($prix_original, 2, ',', ' ') . ' €</span><br>';
            $html .= '<span style="color: #e74c3c; font-weight: bold;">' . number_format($prix_unitaire, 2, ',', ' ') . ' €</span>';
        } else {
            $html .= number_format($prix_unitaire, 2, ',', ' ') . ' €';
        }
        
        $html .= '</td>
                <td class="text-right"><strong>' . number_format($prix_total, 2, ',', ' ') . ' €</strong></td>
            </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="totals">
            <div class="total-row">
                <span>Sous-total HT</span>
                <span>' . number_format($total_ht, 2, ',', ' ') . ' €</span>
            </div>
            <div class="total-row">
                <span>TVA (20%)</span>
                <span>' . number_format($total_tva, 2, ',', ' ') . ' €</span>
            </div>
            <div class="total-row">
                <span>Frais de livraison</span>
                <span>' . number_format($frais_livraison, 2, ',', ' ') . ' €</span>
            </div>
            <div class="total-row grand-total">
                <span><strong>TOTAL TTC</strong></span>
                <span><strong>' . number_format($total_ttc, 2, ',', ' ') . ' €</strong></span>
            </div>
        </div>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
            <p>SIRET : 123 456 789 00010 - TVA : FR12345678901</p>
            <p>© ' . date('Y') . ' HEURE DU CADEAU - Tous droits réservés</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Génère un PDF de facture
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 * @return string Contenu du PDF ou HTML fallback
 */
function genererPDFFacture($commande, $items, $transaction = null) {
    $html = genererFactureHTML($commande, $items, $transaction);
    
    // Si TCPDF n'est pas disponible, retourner le HTML pour fallback
    if (!defined('TCPDF_AVAILABLE') || !TCPDF_AVAILABLE) {
        error_log("TCPDF non disponible, retour HTML pour fallback");
        return $html;
    }
    
    try {
        // Créer le PDF avec TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Configuration du document
        $pdf->SetCreator('HEURE DU CADEAU');
        $pdf->SetAuthor('HEURE DU CADEAU');
        $pdf->SetTitle('Facture ' . ($commande['numero_commande'] ?? 'N/A'));
        $pdf->SetSubject('Facture de commande');
        $pdf->SetKeywords('Facture, Commande, Cadeau');
        
        // Supprimer les en-têtes/pieds de page par défaut
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Ajouter une page
        $pdf->AddPage();
        
        // Définir la police
        $pdf->SetFont('helvetica', '', 10);
        
        // Convertir le HTML en PDF
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Retourner le contenu du PDF
        return $pdf->Output('', 'S');
        
    } catch (Exception $e) {
        error_log("Erreur génération PDF TCPDF: " . $e->getMessage());
        return $html;
    }
}
?>