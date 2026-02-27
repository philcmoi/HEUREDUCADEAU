<?php
// ============================================
// GÉNÉRATION PDF DE FACTURE AVEC TCPDF
// ============================================

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Génère un PDF de facture à partir des données de commande
 * 
 * @param array $commande Données de la commande
 * @param array $items Articles de la commande
 * @param array|null $transaction Données de transaction
 * @return string Contenu du PDF (binaire)
 */
function genererPDFFacture($commande, $items, $transaction = null) {
    
    // Créer une nouvelle instance TCPDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // ============================================
    // CONFIGURATION DU DOCUMENT
    // ============================================
    
    // Supprimer l'en-tête et pied de page par défaut
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Informations du document
    $pdf->SetCreator('HEURE DU CADEAU');
    $pdf->SetAuthor('HEURE DU CADEAU');
    $pdf->SetTitle('Facture ' . $commande['numero_commande']);
    $pdf->SetSubject('Facture de commande');
    $pdf->SetKeywords('facture, commande, cadeau');
    
    // Marges
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 25);
    
    // Police par défaut
    $pdf->SetFont('helvetica', '', 10);
    
    // ============================================
    // AJOUTER UNE PAGE
    // ============================================
    $pdf->AddPage();
    
    // ============================================
    // EN-TÊTE AVEC LOGO
    // ============================================
    
    // Logo (si le fichier existe)
    $logo_path = __DIR__ . '/img/logo-facture.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 15, 50, 0, 'PNG');
    } else {
        // Texte du logo
        $pdf->SetFont('helvetica', 'B', 24);
        $pdf->SetTextColor(44, 62, 80); // #2c3e50
        $pdf->Cell(0, 20, 'HEURE DU CADEAU', 0, 1, 'L');
    }
    
    // Informations de la société
    $pdf->SetY(30);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, '123 Rue des Cadeaux', 0, 1, 'R');
    $pdf->Cell(0, 5, '75001 Paris, France', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Tél: 01 23 45 67 89', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Email: contact@heureducadeau.fr', 0, 1, 'R');
    $pdf->Cell(0, 5, 'SIRET: 123 456 789 00012', 0, 1, 'R');
    
    // Ligne de séparation
    $pdf->Line(15, 50, 195, 50);
    
    // ============================================
    // TITRE FACTURE
    // ============================================
    $pdf->SetY(55);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetTextColor(41, 128, 185); // #2980b9
    $pdf->Cell(0, 10, 'FACTURE', 0, 1, 'C');
    
    // Numéro et date
    $pdf->SetFont('helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'N° ' . $commande['numero_commande'], 0, 1, 'C');
    $pdf->Cell(0, 6, 'Date: ' . date('d/m/Y', strtotime($commande['date_commande'])), 0, 1, 'C');
    
    // ============================================
    // INFORMATIONS CLIENT ET ADRESSES
    // ============================================
    $pdf->SetY(80);
    
    // Coordonnées client
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(95, 8, 'CLIENT', 0, 0, 'L');
    $pdf->Cell(95, 8, 'FACTURATION', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    // Client
    $pdf->Cell(95, 5, $commande['client_prenom'] . ' ' . $commande['client_nom'], 0, 0, 'L');
    $pdf->Cell(95, 5, $commande['facturation_prenom'] . ' ' . $commande['facturation_nom'], 0, 1, 'L');
    
    $pdf->Cell(95, 5, $commande['facturation_adresse'], 0, 0, 'L');
    $pdf->Cell(95, 5, $commande['facturation_adresse'], 0, 1, 'L');
    
    if (!empty($commande['facturation_complement'])) {
        $pdf->Cell(95, 5, '', 0, 0, 'L');
        $pdf->Cell(95, 5, $commande['facturation_complement'], 0, 1, 'L');
    }
    
    $pdf->Cell(95, 5, $commande['facturation_code_postal'] . ' ' . $commande['facturation_ville'], 0, 0, 'L');
    $pdf->Cell(95, 5, $commande['facturation_code_postal'] . ' ' . $commande['facturation_ville'], 0, 1, 'L');
    
    $pdf->Cell(95, 5, $commande['facturation_pays'], 0, 0, 'L');
    $pdf->Cell(95, 5, $commande['facturation_pays'], 0, 1, 'L');
    
    $pdf->Cell(95, 5, 'Email: ' . $commande['email'], 0, 0, 'L');
    $pdf->Cell(95, 5, '', 0, 1, 'L');
    
    // Ligne de séparation
    $pdf->Line(15, $pdf->GetY() + 5, 195, $pdf->GetY() + 5);
    
    // ============================================
    // TABLEAU DES ARTICLES
    // ============================================
    $pdf->SetY($pdf->GetY() + 10);
    
    // En-tête du tableau
    $pdf->SetFillColor(44, 62, 80); // #2c3e50
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(80, 10, 'PRODUIT', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'RÉFÉRENCE', 1, 0, 'C', true);
    $pdf->Cell(20, 10, 'QTÉ', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'PRIX UNIT.', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'TOTAL', 1, 1, 'C', true);
    
    // Contenu du tableau
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    
    $sous_total = 0;
    $fill = false;
    
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $sous_total += $prix_total;
        
        $pdf->Cell(80, 8, ' ' . $item['nom_produit'], 'LR', 0, 'L', $fill);
        $pdf->Cell(30, 8, $item['reference_produit'], 'LR', 0, 'C', $fill);
        $pdf->Cell(20, 8, $item['quantite'], 'LR', 0, 'C', $fill);
        $pdf->Cell(30, 8, number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €', 'LR', 0, 'R', $fill);
        $pdf->Cell(30, 8, number_format($prix_total, 2, ',', ' ') . ' €', 'LR', 1, 'R', $fill);
        
        $fill = !$fill;
    }
    
    // Ligne de fermeture du tableau
    $pdf->Cell(190, 0, '', 'T', 1);
    
    // ============================================
    // TOTAUX
    // ============================================
    $pdf->SetY($pdf->GetY() + 5);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Sous-total
    $pdf->Cell(130, 7, '', 0, 0, 'L');
    $pdf->Cell(30, 7, 'Sous-total:', 0, 0, 'R');
    $pdf->Cell(30, 7, number_format($sous_total, 2, ',', ' ') . ' €', 0, 1, 'R');
    
    // Frais de livraison
    $pdf->Cell(130, 7, '', 0, 0, 'L');
    $pdf->Cell(30, 7, 'Livraison:', 0, 0, 'R');
    $pdf->Cell(30, 7, number_format($commande['frais_livraison'], 2, ',', ' ') . ' €', 0, 1, 'R');
    
    // Ligne de séparation
    $pdf->Cell(130, 5, '', 0, 0, 'L');
    $pdf->Cell(30, 5, '---', 0, 0, 'R');
    $pdf->Cell(30, 5, '---', 0, 1, 'R');
    
    // Total TTC
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(231, 76, 60); // #e74c3c
    $pdf->Cell(130, 10, '', 0, 0, 'L');
    $pdf->Cell(30, 10, 'TOTAL TTC:', 0, 0, 'R');
    $pdf->Cell(30, 10, number_format($commande['total_ttc'], 2, ',', ' ') . ' €', 0, 1, 'R');
    
    // ============================================
    // INFORMATIONS DE PAIEMENT
    // ============================================
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColor(44, 62, 80);
    $pdf->Cell(0, 8, 'INFORMATIONS DE PAIEMENT', 0, 1, 'L');
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $pdf->Cell(40, 6, 'Mode de paiement:', 0, 0, 'L');
    $pdf->Cell(0, 6, strtoupper($commande['mode_paiement']), 0, 1, 'L');
    
    $pdf->Cell(40, 6, 'Date de paiement:', 0, 0, 'L');
    $pdf->Cell(0, 6, date('d/m/Y H:i', strtotime($commande['date_paiement'] ?? $commande['date_commande'])), 0, 1, 'L');
    
    if ($transaction && !empty($transaction['reference_paiement'])) {
        $pdf->Cell(40, 6, 'Référence:', 0, 0, 'L');
        $pdf->Cell(0, 6, $transaction['reference_paiement'], 0, 1, 'L');
    }
    
    // ============================================
    // MENTIONS LÉGALES
    // ============================================
    $pdf->SetY(-40);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Cell(0, 4, 'TVA non applicable - article 293 B du CGI', 0, 1, 'C');
    $pdf->Cell(0, 4, 'HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris', 0, 1, 'C');
    $pdf->Cell(0, 4, 'contact@heureducadeau.fr - 01 23 45 67 89', 0, 1, 'C');
    $pdf->Cell(0, 4, 'SIRET 123 456 789 00012 - RCS Paris', 0, 1, 'C');
    
    // ============================================
    // RETOURNER LE PDF
    // ============================================
    return $pdf->Output('facture_' . $commande['numero_commande'] . '.pdf', 'S'); // 'S' pour retourner comme string
}