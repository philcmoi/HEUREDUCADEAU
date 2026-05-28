<?php
// ============================================
// FONCTIONS EMAIL - AVEC FACTURE HTML/IMPRESSION
// ============================================

// ... (contenu existant) ...

/**
 * Envoie la facture par email (sans TCPDF)
 * Utilise le HTML avec impression intégrée
 * 
 * @param PDO $pdo Connexion BDD
 * @param int $commande_id ID de la commande
 * @return bool Succès de l'envoi
 */
function envoyerFactureEmail($pdo, $commande_id) {
    if (!defined('PHPMailer_AVAILABLE') || !PHPMailer_AVAILABLE) {
        error_log("PHPMailer non disponible pour l'envoi de la facture");
        return false;
    }
    
    try {
        // Récupérer les données de la commande
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.nom as client_nom, cl.prenom as client_prenom
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$commande) {
            error_log("Commande #$commande_id non trouvée");
            return false;
        }
        
        // Récupérer les adresses
        $stmt_addr = $pdo->prepare("
            SELECT a.nom, a.prenom, a.adresse, a.complement, a.code_postal, a.ville, a.pays, a.telephone
            FROM adresses a            WHERE a.id_adresse = ?
        ");
        $stmt_addr->execute([$commande['id_adresse_livraison']]);
        $adresse = $stmt_addr->fetch(PDO::FETCH_ASSOC);
        
        if ($adresse) {
            foreach ($adresse as $key => $value) {
                if (!isset($commande[$key]) || empty($commande[$key])) {
                    $commande[$key] = $value;
                }
            }
        }
        
        // Récupérer les articles
        $stmt_items = $pdo->prepare("
            SELECT * FROM commande_items WHERE id_commande = ?
        ");
        $stmt_items->execute([$commande_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        // Récupérer la transaction
        $stmt_trans = $pdo->prepare("
            SELECT * FROM transactions 
            WHERE id_commande = ? 
            ORDER BY date_creation DESC LIMIT 1
        ");
        $stmt_trans->execute([$commande_id]);
        $transaction = $stmt_trans->fetch(PDO::FETCH_ASSOC);
        
        // Générer le HTML de la facture (avec style pour email)
        $facture_html = genererFactureEmailHTML($commande, $items, $transaction);
        
        // Envoyer l'email
        $mail = getPHPMailerInstance();
        if (!$mail) {
            error_log("Impossible de créer l'instance PHPMailer");
            return false;
        }
        
        // Destinataire
        $client_email = $commande['email'];
        $client_nom = ($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? 'Client');
        
        $mail->addAddress($client_email, $client_nom);
        $mail->Subject = 'Confirmation de commande #' . $commande['numero_commande'] . ' - HEURE DU CADEAU';
        
        // Corps HTML
        $mail->isHTML(true);
        $mail->Body = $facture_html;
        $mail->AltBody = "Merci pour votre commande #" . $commande['numero_commande'] . "\n\n"
                       . "Total : " . number_format($commande['total_ttc'], 2, ',', ' ') . " €\n\n"
                       . "Vous pouvez consulter votre facture en pièce jointe ou depuis votre espace client.\n\n"
                       . "HEURE DU CADEAU";
        
        // Ajouter le HTML comme pièce jointe (pour téléchargement)
        $facture_attachment = genererFactureAttachmentHTML($commande, $items, $transaction);
        $mail->addStringAttachment($facture_attachment, 'facture_' . $commande['numero_commande'] . '.html', 'base64', 'text/html');
        
        $result = $mail->send();
        
        if ($result) {
            error_log("Facture envoyée par email pour commande #$commande_id à $client_email");
        } else {
            error_log("Échec envoi email facture: " . $mail->ErrorInfo);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Erreur envoi facture email: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère le HTML pour la facture dans l'email
 */
function genererFactureEmailHTML($commande, $items, $transaction = null) {
    $total = 0;
    foreach ($items as $item) {
        $total += $item['quantite'] * $item['prix_unitaire_ttc'];
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
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Facture ' . htmlspecialchars($commande['numero_commande']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.5; color: #333; }
        .container { max-width: 600px; margin: 0 auto; }
        .header { border-bottom: 2px solid #2c3e50; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .logo span { color: #e74c3c; }
        .facture-title { text-align: right; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #2c3e50; color: white; padding: 10px; text-align: left; font-size: 12px; }
        td { padding: 10px; border-bottom: 1px solid #ddd; font-size: 12px; }
        .total-row { text-align: right; margin: 5px 0; }
        .grand-total { font-size: 16px; font-weight: bold; color: #e74c3c; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; text-align: center; font-size: 11px; color: #999; }
        .button { display: inline-block; background: #2c3e50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 15px 0; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 15px; background: #d4edda; color: #155724; font-size: 11px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">HEURE DU <span>CADEAU</span></div>
            <div class="facture-title">Facture N° ' . htmlspecialchars($commande['numero_commande']) . '</div>
        </div>
        
        <p>Bonjour <strong>' . htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) . '</strong>,</p>
        
        <p>Nous vous remercions pour votre commande. Vous trouverez ci-dessous le récapitulatif de votre achat.</p>
        
        <div class="info-box">
            <p><strong>Commande :</strong> #' . htmlspecialchars($commande['numero_commande']) . '</p>
            <p><strong>Date :</strong> ' . date('d/m/Y H:i', strtotime($commande['date_commande'])) . '</p>
            <p><strong>Paiement :</strong> ' . $mode_paiement_label . ' - <span class="status">Payé</span></p>
            <p><strong>Livraison :</strong> ' . htmlspecialchars($commande['adresse'] ?? '') . ' ' . htmlspecialchars($commande['code_postal'] ?? '') . ' ' . htmlspecialchars($commande['ville'] ?? '') . '</p>
        </div>
        
        <table>
            <thead>
                <tr><th>Produit</th><th>Réf.</th><th>Qté</th><th>Prix</th><th>Total</th></tr>
            </thead>
            <tbody>';
    
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $html .= '<tr>
            <td>' . htmlspecialchars($item['nom_produit']) . '</td>
            <td>' . htmlspecialchars($item['reference_produit']) . '</td>
            <td>' . $item['quantite'] . '</td>
            <td>' . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €</td>
            <td><strong>' . number_format($prix_total, 2, ',', ' ') . ' €</strong></td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="total-row">Sous-total : ' . number_format($total, 2, ',', ' ') . ' €</div>
        <div class="total-row">Frais de livraison : ' . number_format($frais_livraison, 2, ',', ' ') . ' €</div>
        <div class="total-row grand-total">TOTAL TTC : ' . number_format($total_ttc, 2, ',', ' ') . ' €</div>
        
        <p style="text-align: center;">
            <a href="' . SITE_URL . '/telecharger-facture.php?commande_id=' . $commande['id_commande'] . '" class="button">📄 Télécharger ma facture</a>
        </p>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
            <p>© ' . date('Y') . ' HEURE DU CADEAU</p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Génère le HTML pour pièce jointe
 */
function genererFactureAttachmentHTML($commande, $items, $transaction = null) {
    // Réutiliser la fonction genererFactureHTML du fichier generer_pdf_facture.php
    if (file_exists(__DIR__ . '/generer_pdf_facture.php')) {
        require_once __DIR__ . '/generer_pdf_facture.php';
        return genererFactureHTML($commande, $items, $transaction);
    }
    
    // Fallback
    return "FACTURE\n" . $commande['numero_commande'] . "\nTotal : " . $commande['total_ttc'] . " €";
}

// ... (reste du fichier inchangé)
?>