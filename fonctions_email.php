<?php
// ============================================
// FONCTIONS D'ENVOI D'EMAILS AVEC PHPMailer
// ============================================

require_once __DIR__ . '/config_email.php';
require_once __DIR__ . '/generer_pdf_facture.php';

/**
 * Envoie un email de confirmation de commande/facture au client
 * avec pièce jointe PDF et tracking
 */
function envoyerFactureEmail($pdo, $commande_id) {
    try {
        // ============================================
        // VÉRIFICATION DE LA DISPONIBILITÉ DE PHPMailer
        // ============================================
        
        if (!defined('PHPMailer_AVAILABLE') || !PHPMailer_AVAILABLE) {
            error_log("Impossible d'envoyer l'email pour la commande $commande_id : PHPMailer non installé");
            return false;
        }
        
        // ============================================
        // RÉCUPÉRATION DES DONNÉES
        // ============================================
        
        // Récupérer toutes les informations de la commande
        $stmt = $pdo->prepare("
            SELECT 
                c.*,
                cl.email,
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.id_client,
                a.nom as livraison_nom,
                a.prenom as livraison_prenom,
                a.adresse as livraison_adresse,
                a.complement as livraison_complement,
                a.code_postal as livraison_code_postal,
                a.ville as livraison_ville,
                a.pays as livraison_pays,
                a.telephone as livraison_telephone,
                af.nom as facturation_nom,
                af.prenom as facturation_prenom,
                af.adresse as facturation_adresse,
                af.complement as facturation_complement,
                af.code_postal as facturation_code_postal,
                af.ville as facturation_ville,
                af.pays as facturation_pays,
                af.telephone as facturation_telephone
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            LEFT JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
            LEFT JOIN adresses af ON c.id_adresse_facturation = af.id_adresse
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$commande) {
            error_log("Commande introuvable pour envoi email: $commande_id");
            return false;
        }

        // Récupérer les articles de la commande
        $stmt_items = $pdo->prepare("
            SELECT * FROM commande_items 
            WHERE id_commande = ?
        ");
        $stmt_items->execute([$commande_id]);
        $items = $stmt_items->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            error_log("Aucun article trouvé pour la commande $commande_id");
            return false;
        }

        // Récupérer la transaction associée
        $stmt_trans = $pdo->prepare("
            SELECT * FROM transactions 
            WHERE id_commande = ? 
            ORDER BY date_creation DESC LIMIT 1
        ");
        $stmt_trans->execute([$commande_id]);
        $transaction = $stmt_trans->fetch(\PDO::FETCH_ASSOC);

        // Destinataire
        $destinataire = $commande['email'];
        if (empty($destinataire) || !filter_var($destinataire, FILTER_VALIDATE_EMAIL)) {
            error_log("Email client invalide pour commande $commande_id: $destinataire");
            return false;
        }

        // ============================================
        // GÉNÉRATION DU PDF
        // ============================================
        
        $pdf_content = null;
        $pdf_path = null;
        
        try {
            if (defined('TCPDF_AVAILABLE') && TCPDF_AVAILABLE) {
                $pdf_content = genererPDFFacture($commande, $items, $transaction);
                
                // Sauvegarder le PDF pour archivage
                $archive_dir = __DIR__ . '/factures/';
                if (!is_dir($archive_dir)) {
                    mkdir($archive_dir, 0755, true);
                }
                
                $pdf_filename = 'facture_' . $commande['numero_commande'] . '_' . date('Ymd') . '.pdf';
                $pdf_path = $archive_dir . $pdf_filename;
                file_put_contents($pdf_path, $pdf_content);
                
                error_log("PDF généré et sauvegardé: $pdf_path");
            } else {
                error_log("TCPDF non disponible - envoi sans PDF");
            }
            
        } catch (\Exception $e) {
            error_log("Erreur génération PDF: " . $e->getMessage());
            // Continuer sans PDF si erreur
        }

        // ============================================
        // CONSTRUCTION DE L'EMAIL AVEC PHPMailer
        // ============================================
        
        $mail = getPHPMailerInstance();
        if (!$mail) {
            throw new \Exception("Impossible d'initialiser PHPMailer");
        }

        // Destinataire
        $mail->addAddress($destinataire, $commande['client_prenom'] . ' ' . $commande['client_nom']);
        
        // Copie cachée pour l'administrateur
        if (defined('SMTP_FROM_EMAIL')) {
            $mail->addBCC(SMTP_FROM_EMAIL, 'Administration');
        }

        // Sujet
        $sujet = "Votre commande " . $commande['numero_commande'] . " a été confirmée";
        $mail->Subject = $sujet;

        // ============================================
        // GÉNÉRATION DU CONTENU HTML
        // ============================================
        
        $message_html = genererFactureHTML($commande, $items, $transaction);
        $message_texte = genererFactureTexte($commande, $items, $transaction);
        
        // Contenu HTML
        $mail->isHTML(true);
        $mail->Body = $message_html;
        $mail->AltBody = $message_texte;

        // ============================================
        // AJOUT DE LA PIÈCE JOINTE PDF
        // ============================================
        
        if ($pdf_content) {
            $mail->addStringAttachment(
                $pdf_content, 
                'facture_' . $commande['numero_commande'] . '.pdf', 
                'base64', 
                'application/pdf'
            );
        }

        // ============================================
        // ENVOI DE L'EMAIL
        // ============================================
        
        $envoi_reussi = $mail->send();

        if ($envoi_reussi) {
            // Journaliser l'envoi
            $log_message = date('Y-m-d H:i:s') . " - Email envoyé à $destinataire pour commande {$commande['numero_commande']}\n";
            file_put_contents(EMAIL_LOG_PATH . 'envois.log', $log_message, FILE_APPEND);
            
            // Journaliser dans la base de données si la table existe
            try {
                $stmt_log = $pdo->prepare("
                    INSERT INTO logs (type_log, niveau, message, utilisateur_id, metadata, date_log)
                    VALUES ('info', 'info', 'Facture envoyée par email', ?, ?, NOW())
                ");
                $stmt_log->execute([
                    $commande['id_client'],
                    json_encode([
                        'commande_id' => $commande_id,
                        'email' => $destinataire,
                        'numero_commande' => $commande['numero_commande'],
                        'pdf_genere' => !is_null($pdf_content),
                        'date_envoi' => date('Y-m-d H:i:s')
                    ])
                ]);
            } catch (\Exception $e) {
                // Table logs peut ne pas exister - ignorer
            }
            
            error_log("Email envoyé avec succès pour commande $commande_id");
            return true;
            
        } else {
            error_log("Échec envoi email facture pour commande $commande_id: " . $mail->ErrorInfo);
            
            // Journaliser l'échec
            $log_message = date('Y-m-d H:i:s') . " - ÉCHEC envoi à $destinataire: " . $mail->ErrorInfo . "\n";
            file_put_contents(EMAIL_LOG_PATH . 'erreurs.log', $log_message, FILE_APPEND);
            
            return false;
        }

    } catch (\Exception $e) {
        error_log("Erreur critique envoi email facture: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère la version HTML de la facture (optimisée pour email)
 */
function genererFactureHTML($commande, $items, $transaction = null) {
    $site_url = "https://" . ($_SERVER['HTTP_HOST'] ?? 'heureducadeau.fr');
    $logo_url = $site_url . "/img/logo-email.png";
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Facture ' . htmlspecialchars($commande['numero_commande']) . '</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 800px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 28px; }
            .header p { margin: 10px 0 0; opacity: 0.9; }
            .content { padding: 30px; background: #fff; }
            .facture-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
            .facture-info table { width: 100%; }
            .facture-info td { padding: 5px 0; }
            .facture-info .label { font-weight: 600; color: #2c3e50; width: 150px; }
            .section-title { color: #2c3e50; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; margin: 30px 0 20px; font-size: 20px; }
            table.items { width: 100%; border-collapse: collapse; margin: 20px 0; }
            table.items th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
            table.items td { padding: 12px; border-bottom: 1px solid #dee2e6; }
            table.items tr:last-child td { border-bottom: none; }
            .total-row { font-weight: bold; background: #f8f9fa; }
            .total-row td { border-top: 2px solid #dee2e6; }
            .grand-total { font-size: 18px; color: #e74c3c; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #7f8c8d; border-top: 1px solid #dee2e6; }
            .badge { background: #27ae60; color: white; padding: 5px 10px; border-radius: 5px; display: inline-block; font-size: 12px; }
            .address-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
            .address-box h4 { margin: 0 0 10px; color: #2c3e50; }
            .pdf-note { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 5px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HEURE DU CADEAU</h1>
            <p>Merci pour votre commande !</p>
        </div>
        
        <div class="content">
            
            <!-- Note PDF -->
            <div class="pdf-note">
                <strong>📎 Une copie PDF de cette facture</strong> est jointe à cet email pour vos archives.
            </div>
            
            <div style="text-align: center; margin-bottom: 30px;">
                <span class="badge">Commande confirmée</span>
            </div>
            
            <div class="facture-info">
                <table>
                    <tr>
                        <td class="label">N° de commande :</td>
                        <td><strong>' . htmlspecialchars($commande['numero_commande']) . '</strong></td>
                    </tr>
                    <tr>
                        <td class="label">Date :</td>
                        <td>' . date('d/m/Y H:i', strtotime($commande['date_commande'])) . '</td>
                    </tr>
                    <tr>
                        <td class="label">Statut :</td>
                        <td><span style="color: #27ae60;">Payée</span></td>
                    </tr>';
    
    if ($transaction && !empty($transaction['reference_paiement'])) {
        $html .= '
                    <tr>
                        <td class="label">Transaction :</td>
                        <td>' . htmlspecialchars($transaction['reference_paiement']) . '</td>
                    </tr>';
    }
    
    $html .= '
                </table>
            </div>
            
            <div style="display: flex; gap: 30px; margin-bottom: 30px;">
                <div class="address-box" style="flex: 1;">
                    <h4>📦 Adresse de livraison</h4>
                    <p>
                        <strong>' . htmlspecialchars($commande['livraison_prenom'] . ' ' . $commande['livraison_nom']) . '</strong><br>
                        ' . htmlspecialchars($commande['livraison_adresse']) . '<br>';
    
    if (!empty($commande['livraison_complement'])) {
        $html .= '        ' . htmlspecialchars($commande['livraison_complement']) . '<br>';
    }
    
    $html .= '        ' . htmlspecialchars($commande['livraison_code_postal']) . ' ' . htmlspecialchars($commande['livraison_ville']) . '<br>
                        ' . htmlspecialchars($commande['livraison_pays']) . '<br>';
    
    if (!empty($commande['livraison_telephone'])) {
        $html .= '        Tél: ' . htmlspecialchars($commande['livraison_telephone']);
    }
    
    $html .= '
                    </p>
                </div>
                
                <div class="address-box" style="flex: 1;">
                    <h4>📄 Adresse de facturation</h4>
                    <p>
                        <strong>' . htmlspecialchars($commande['facturation_prenom'] . ' ' . $commande['facturation_nom']) . '</strong><br>
                        ' . htmlspecialchars($commande['facturation_adresse']) . '<br>';
    
    if (!empty($commande['facturation_complement'])) {
        $html .= '        ' . htmlspecialchars($commande['facturation_complement']) . '<br>';
    }
    
    $html .= '        ' . htmlspecialchars($commande['facturation_code_postal']) . ' ' . htmlspecialchars($commande['facturation_ville']) . '<br>
                        ' . htmlspecialchars($commande['facturation_pays']) . '
                    </p>
                </div>
            </div>
            
            <h3 class="section-title">Détail des articles</h3>
            
            <table class="items">
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Référence</th>
                        <th style="text-align: center;">Quantité</th>
                        <th style="text-align: right;">Prix unitaire</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    $sous_total = 0;
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $sous_total += $prix_total;
        
        $html .= '
                    <tr>
                        <td><strong>' . htmlspecialchars($item['nom_produit']) . '</strong></td>
                        <td>' . htmlspecialchars($item['reference_produit']) . '</td>
                        <td style="text-align: center;">' . $item['quantite'] . '</td>
                        <td style="text-align: right;">' . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €</td>
                        <td style="text-align: right;">' . number_format($prix_total, 2, ',', ' ') . ' €</td>
                    </tr>';
    }
    
    $html .= '
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;"><strong>Sous-total</strong></td>
                        <td style="text-align: right;">' . number_format($sous_total, 2, ',', ' ') . ' €</td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;">Frais de livraison</td>
                        <td style="text-align: right;">' . number_format($commande['frais_livraison'], 2, ',', ' ') . ' €</td>
                    </tr>';
    
    $html .= '
                    <tr class="grand-total">
                        <td colspan="4" style="text-align: right;"><strong>Total TTC</strong></td>
                        <td style="text-align: right;"><strong>' . number_format($commande['total_ttc'], 2, ',', ' ') . ' €</strong></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #ebf8ff; border-left: 5px solid #003087; padding: 15px; margin: 30px 0; border-radius: 5px;">
                <p style="margin: 0;"><strong>Mode de paiement :</strong> ' . strtoupper($commande['mode_paiement']) . '</p>
                <p style="margin: 10px 0 0;"><strong>Statut :</strong> Paiement validé le ' . date('d/m/Y H:i', strtotime($commande['date_paiement'] ?? $commande['date_commande'])) . '</p>
            </div>
            
            <div style="text-align: center; margin: 40px 0;">
                <p><strong>Livraison estimée :</strong> 3-5 jours ouvrés</p>
                <p>Vous recevrez un email avec le numéro de suivi dès l\'expédition de votre commande.</p>
            </div>
            
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-top: 30px;">
                <p style="margin: 0;"><strong>Besoin d\'aide ?</strong></p>
                <p style="margin: 10px 0 0;">Email : contact@heureducadeau.fr | Tél : 01 23 45 67 89</p>
            </div>
        </div>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>Ceci est un email automatique, merci de ne pas y répondre.</p>
            <p>&copy; ' . date('Y') . ' HEURE DU CADEAU - Tous droits réservés</p>
        </div>
    </body>
    </html>';
    
    return $html;
}

/**
 * Génère la version texte de la facture
 */
function genererFactureTexte($commande, $items, $transaction = null) {
    $texte = "HEURE DU CADEAU\n";
    $texte .= str_repeat("=", 50) . "\n\n";
    $texte .= "MERCI POUR VOTRE COMMANDE !\n\n";
    $texte .= "Une copie PDF de cette facture est jointe à cet email.\n\n";
    
    $texte .= "COMMANDE N° " . $commande['numero_commande'] . "\n";
    $texte .= "Date : " . date('d/m/Y H:i', strtotime($commande['date_commande'])) . "\n";
    $texte .= "Statut : Payée\n";
    if ($transaction && !empty($transaction['reference_paiement'])) {
        $texte .= "Transaction : " . $transaction['reference_paiement'] . "\n";
    }
    $texte .= "\n";
    
    $texte .= "ADRESSE DE LIVRAISON\n";
    $texte .= str_repeat("-", 30) . "\n";
    $texte .= $commande['livraison_prenom'] . " " . $commande['livraison_nom'] . "\n";
    $texte .= $commande['livraison_adresse'] . "\n";
    if (!empty($commande['livraison_complement'])) {
        $texte .= $commande['livraison_complement'] . "\n";
    }
    $texte .= $commande['livraison_code_postal'] . " " . $commande['livraison_ville'] . "\n";
    $texte .= $commande['livraison_pays'] . "\n";
    if (!empty($commande['livraison_telephone'])) {
        $texte .= "Tél : " . $commande['livraison_telephone'] . "\n";
    }
    $texte .= "\n";
    
    $texte .= "ADRESSE DE FACTURATION\n";
    $texte .= str_repeat("-", 30) . "\n";
    $texte .= $commande['facturation_prenom'] . " " . $commande['facturation_nom'] . "\n";
    $texte .= $commande['facturation_adresse'] . "\n";
    if (!empty($commande['facturation_complement'])) {
        $texte .= $commande['facturation_complement'] . "\n";
    }
    $texte .= $commande['facturation_code_postal'] . " " . $commande['facturation_ville'] . "\n";
    $texte .= $commande['facturation_pays'] . "\n\n";
    
    $texte .= "DÉTAIL DES ARTICLES\n";
    $texte .= str_repeat("=", 50) . "\n";
    
    $sous_total = 0;
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $sous_total += $prix_total;
        
        $texte .= $item['nom_produit'] . "\n";
        $texte .= "  Réf: " . $item['reference_produit'] . "\n";
        $texte .= "  Qté: " . $item['quantite'] . " x " . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . " €";
        $texte .= " = " . number_format($prix_total, 2, ',', ' ') . " €\n\n";
    }
    
    $texte .= str_repeat("-", 50) . "\n";
    $texte .= "Sous-total : " . number_format($sous_total, 2, ',', ' ') . " €\n";
    $texte .= "Frais de livraison : " . number_format($commande['frais_livraison'], 2, ',', ' ') . " €\n";
    $texte .= str_repeat("=", 50) . "\n";
    $texte .= "TOTAL TTC : " . number_format($commande['total_ttc'], 2, ',', ' ') . " €\n\n";
    
    $texte .= "Mode de paiement : " . strtoupper($commande['mode_paiement']) . "\n";
    $texte .= "Paiement validé le : " . date('d/m/Y H:i', strtotime($commande['date_paiement'] ?? $commande['date_commande'])) . "\n\n";
    
    $texte .= "Livraison estimée : 3-5 jours ouvrés\n";
    $texte .= "Vous recevrez un email avec le numéro de suivi dès l'expédition.\n\n";
    
    $texte .= "BESOIN D'AIDE ?\n";
    $texte .= str_repeat("-", 30) . "\n";
    $texte .= "Email : contact@heureducadeau.fr\n";
    $texte .= "Tél : 01 23 45 67 89\n\n";
    
    $texte .= str_repeat("=", 50) . "\n";
    $texte .= "HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris\n";
    $texte .= "Ceci est un email automatique, merci de ne pas y répondre.\n";
    $texte .= "© " . date('Y') . " HEURE DU CADEAU - Tous droits réservés\n";
    
    return $texte;
}

/**
 * Fonction de test pour vérifier la configuration email
 */
function testerEnvoiEmail($email_test = null) {
    if (!$email_test) {
        $email_test = SMTP_FROM_EMAIL;
    }
    
    try {
        $mail = getPHPMailerInstance();
        if (!$mail) {
            return ['success' => false, 'message' => 'PHPMailer non disponible'];
        }
        
        $mail->addAddress($email_test);
        $mail->Subject = 'Test configuration email - HEURE DU CADEAU';
        $mail->Body = '<h1>Test réussi !</h1><p>Votre configuration email fonctionne correctement.</p>';
        $mail->AltBody = 'Test réussi ! Votre configuration email fonctionne correctement.';
        
        if ($mail->send()) {
            return ['success' => true, 'message' => 'Email de test envoyé avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur: ' . $mail->ErrorInfo];
        }
    } catch (\Exception $e) {
        return ['success' => false, 'message' => 'Exception: ' . $e->getMessage()];
    }
}
?>