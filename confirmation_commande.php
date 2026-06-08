<?php
// ============================================
// CONFIRMATION PAIEMENT - VERSION CORRIGÉE
// AVEC AFFICHAGE DES PRIX PROMOTIONNELS
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/session_verification.php';
require_once __DIR__ . '/generer_pdf_facture.php';

// Récupérer les paramètres
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$token = isset($_GET['token']) ? $_GET['token'] : '';

if ($commande_id <= 0) {
    header('Location: index.php');
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
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur connexion BDD: " . $e->getMessage());
    die("Erreur technique. Veuillez réessayer plus tard.");
}

// ============================================
// FONCTIONS DE MESSAGE
// ============================================

/**
 * Génère la version HTML du message avec affichage des promotions
 */
function genererMessageHTML($commande, $items) {
    $to_name = $commande['client_prenom'] . ' ' . $commande['client_nom'];
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Confirmation de commande</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #2c3e50, #34495e); color: white; padding: 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; }
            .content { padding: 30px; background: #fff; }
            .order-info { background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th { background: #f8f9fa; padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6; }
            td { padding: 12px; border-bottom: 1px solid #dee2e6; }
            .total { font-weight: bold; color: #e74c3c; font-size: 1.2em; margin-top: 20px; text-align: right; }
            .address { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .promo-badge { display: inline-block; background: #e74c3c; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 8px; }
            .old-price { text-decoration: line-through; color: #999; font-size: 11px; margin-right: 5px; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #7f8c8d; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HEURE DU CADEAU</h1>
            <p>Merci pour votre commande !</p>
        </div>
        
        <div class="content">
            <p>Bonjour <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
            <p>Nous vous confirmons que votre commande a bien été enregistrée et payée.</p>
            
            <div class="order-info">
                <p><strong>Numéro de commande :</strong> ' . htmlspecialchars($commande['numero_commande']) . '</p>
                <p><strong>Date :</strong> ' . date('d/m/Y H:i', strtotime($commande['date_commande'])) . '</p>
                <p><strong>Mode de paiement :</strong> ' . strtoupper($commande['mode_paiement'] ?? 'Carte') . '</p>';
    
    if (!empty($commande['reference_paiement'])) {
        $html .= '<p><strong>Transaction :</strong> ' . htmlspecialchars($commande['reference_paiement']) . '</p>';
    }
    
    $html .= '
            </div>
            
            <h3>Détail des articles</h3>
            <table>
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
    
    $sous_total = 0;
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        $sous_total += $prix_total;
        
        // Vérifier si une promotion a été appliquée
        $has_promotion = false;
        $prix_original = $item['prix_unitaire_ttc'];
        
        if (isset($item['options']) && !empty($item['options'])) {
            $options = json_decode($item['options'], true);
            if (isset($options['prix_original']) && $options['prix_original'] > $item['prix_unitaire_ttc']) {
                $has_promotion = true;
                $prix_original = $options['prix_original'];
            }
        }
        
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['nom_produit']);
        
        if ($has_promotion) {
            $html .= '<span class="promo-badge">Promo</span>';
        }
        
        $html .= '</td>
                        <td>' . htmlspecialchars($item['reference_produit']) . '</td>
                        <td>' . $item['quantite'] . '</td>
                        <td>';
        
        if ($has_promotion) {
            $html .= '<span class="old-price">' . number_format($prix_original, 2, ',', ' ') . ' €</span><br>';
            $html .= '<span style="color:#e74c3c; font-weight:bold;">' . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €</span>';
        } else {
            $html .= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €';
        }
        
        $html .= '</td>
                        <td><strong>' . number_format($prix_total, 2, ',', ' ') . ' €</strong></td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
            
            <div style="text-align: right;">
                <p><strong>Sous-total :</strong> ' . number_format($sous_total, 2, ',', ' ') . ' €</p>
                <p><strong>Frais de livraison :</strong> ' . number_format($commande['frais_livraison'], 2, ',', ' ') . ' €</p>
                <p style="font-size: 1.3em; color: #e74c3c;"><strong>Total payé :</strong> ' . number_format($commande['total_ttc'], 2, ',', ' ') . ' €</p>
            </div>
            
            <div class="address">
                <h4>Adresse de livraison</h4>
                <p>
                    ' . htmlspecialchars($commande['livraison_prenom'] . ' ' . $commande['livraison_nom']) . '<br>
                    ' . htmlspecialchars($commande['livraison_adresse']) . '<br>';
    
    if (!empty($commande['livraison_complement'])) {
        $html .= '        ' . htmlspecialchars($commande['livraison_complement']) . '<br>';
    }
    
    $html .= '        ' . htmlspecialchars($commande['livraison_code_postal'] . ' ' . $commande['livraison_ville']) . '<br>
                    ' . htmlspecialchars($commande['livraison_pays']) . '
                </p>
            </div>
            
            <p style="text-align: center; margin: 30px 0;">
                <strong>Votre facture au format PDF est jointe à cet email.</strong>
            </p>
            
            <p><strong>Livraison estimée :</strong> 3-5 jours ouvrés</p>
            <p>Une question ? Contactez-nous : <a href="mailto:contact@heureducadeau.fr">contact@heureducadeau.fr</a></p>
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
 * Génère la version texte du message avec affichage des promotions
 */
function genererMessageTexte($commande, $items) {
    $to_name = $commande['client_prenom'] . ' ' . $commande['client_nom'];
    
    $text = "HEURE DU CADEAU\n";
    $text .= "==================\n\n";
    $text .= "Bonjour $to_name,\n\n";
    $text .= "Nous vous confirmons que votre commande a bien été enregistrée et payée.\n\n";
    $text .= "Commande n°: " . $commande['numero_commande'] . "\n";
    $text .= "Date: " . date('d/m/Y H:i', strtotime($commande['date_commande'])) . "\n\n";
    
    $text .= "Articles:\n";
    foreach ($items as $item) {
        $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
        
        // Vérifier si une promotion a été appliquée
        $has_promotion = false;
        $prix_original = $item['prix_unitaire_ttc'];
        
        if (isset($item['options']) && !empty($item['options'])) {
            $options = json_decode($item['options'], true);
            if (isset($options['prix_original']) && $options['prix_original'] > $item['prix_unitaire_ttc']) {
                $has_promotion = true;
                $prix_original = $options['prix_original'];
            }
        }
        
        if ($has_promotion) {
            $text .= "- " . $item['nom_produit'] . " [PROMO] x" . $item['quantite'] . ": ";
            $text .= number_format($prix_original, 2) . "€ -> " . number_format($prix_total, 2) . " €\n";
        } else {
            $text .= "- " . $item['nom_produit'] . " x" . $item['quantite'] . ": " . number_format($prix_total, 2) . " €\n";
        }
    }
    
    $text .= "\nTotal: " . number_format($commande['total_ttc'], 2) . " €\n\n";
    $text .= "Votre facture au format PDF est jointe à cet email.\n\n";
    $text .= "Livraison estimée: 3-5 jours\n\n";
    $text .= "HEURE DU CADEAU\n";
    $text .= "contact@heureducadeau.fr";
    
    return $text;
}

/**
 * Envoie un email avec la facture en pièce jointe PDF
 */
function envoyerEmailAvecPDF($smtp_config, $commande, $items, $pdf_content, $pdf_filename) {
    $to = $commande['email'];
    $to_name = $commande['client_prenom'] . ' ' . $commande['client_nom'];
    $subject = "Votre commande " . $commande['numero_commande'] . " a été confirmée";
    
    $boundary_mixed = md5(uniqid(mt_rand(), true));
    $boundary_alt = md5(uniqid(mt_rand(), true));
    
    $html_message = genererMessageHTML($commande, $items);
    $text_message = genererMessageTexte($commande, $items);
    
    $alt_part = "Content-Type: multipart/alternative; boundary=\"$boundary_alt\"\r\n\r\n";
    
    $alt_part .= "--$boundary_alt\r\n";
    $alt_part .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $alt_part .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $alt_part .= $text_message . "\r\n\r\n";
    
    $alt_part .= "--$boundary_alt\r\n";
    $alt_part .= "Content-Type: text/html; charset=UTF-8\r\n";
    $alt_part .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $alt_part .= $html_message . "\r\n\r\n";
    
    $alt_part .= "--$boundary_alt--";
    
    $message = "Ceci est un message au format MIME multipart/mixed contenant votre facture en pièce jointe.\r\n\r\n";
    $message .= "--$boundary_mixed\r\n";
    $message .= $alt_part . "\r\n\r\n";
    
    if ($pdf_content) {
        $pdf_attachment = chunk_split(base64_encode($pdf_content));
        
        $message .= "--$boundary_mixed\r\n";
        $message .= "Content-Type: application/pdf; name=\"$pdf_filename\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$pdf_filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= $pdf_attachment . "\r\n\r\n";
    }
    
    $message .= "--$boundary_mixed--";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary_mixed . '"',
        'From: =?UTF-8?B?' . base64_encode($smtp_config['from_name']) . '?= <' . $smtp_config['from_email'] . '>',
        'Reply-To: ' . $smtp_config['from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($smtp_config['host'], $smtp_config['port'], $errno, $errstr, 30);
    
    if (!$socket) {
        error_log("Erreur connexion SMTP: $errstr ($errno)");
        return false;
    }
    
    $closeAndReturnFalse = function($error_message) use ($socket) {
        error_log($error_message);
        fclose($socket);
        return false;
    };
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        return $closeAndReturnFalse("Réponse SMTP inattendue: $response");
    }
    
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($socket, 515);
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
    }
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("EHLO échoué: $response");
    }
    
    fputs($socket, "STARTTLS\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        return $closeAndReturnFalse("STARTTLS échoué: $response");
    }
    
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        return $closeAndReturnFalse("Échec de l'activation TLS");
    }
    
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($socket, 515);
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
    }
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("EHLO après TLS échoué: $response");
    }
    
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        return $closeAndReturnFalse("AUTH LOGIN échoué: $response");
    }
    
    fputs($socket, base64_encode($smtp_config['username']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        return $closeAndReturnFalse("Username échoué: $response");
    }
    
    fputs($socket, base64_encode($smtp_config['password']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        return $closeAndReturnFalse("Authentification échouée: $response");
    }
    
    fputs($socket, "MAIL FROM: <" . $smtp_config['from_email'] . ">\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("MAIL FROM échoué: $response");
    }
    
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("RCPT TO échoué: $response");
    }
    
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') {
        return $closeAndReturnFalse("DATA échoué: $response");
    }
    
    fputs($socket, "Subject: $encoded_subject\r\n");
    foreach ($headers as $header) {
        fputs($socket, $header . "\r\n");
    }
    fputs($socket, "\r\n");
    fputs($socket, $message . "\r\n");
    
    fputs($socket, "\r\n.\r\n");
    $response = fgets($socket, 515);
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
    
    if (substr($response, 0, 3) == '250') {
        error_log("Email avec PDF envoyé avec succès via SMTP à $to - Fichier: $pdf_filename");
        return true;
    } else {
        error_log("Échec envoi SMTP (réponse DATA): $response");
        return false;
    }
}

// ============================================
// RÉCUPÉRATION DES DONNÉES DE LA COMMANDE
// ============================================

try {
    // Récupérer la commande avec toutes les informations
    $stmt = $pdo->prepare("
        SELECT c.*, 
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
    $commande = $stmt->fetch();
    
    if (!$commande) {
        throw new Exception("Commande #$commande_id non trouvée");
    }
    
    // Récupérer les articles AVEC la colonne options
    $stmt_items = $pdo->prepare("SELECT *, options FROM commande_items WHERE id_commande = ?");
    $stmt_items->execute([$commande_id]);
    $items = $stmt_items->fetchAll();
    
    // Récupérer la transaction
    $stmt_trans = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE id_commande = ? 
        ORDER BY date_creation DESC LIMIT 1
    ");
    $stmt_trans->execute([$commande_id]);
    $transaction = $stmt_trans->fetch();
    
    // Ajouter la référence de transaction
    if ($transaction) {
        $commande['reference_paiement'] = $transaction['reference_paiement'] ?? $token;
    } else {
        $commande['reference_paiement'] = $token;
    }
    
    // Marquer la commande comme récente dans la session
    $_SESSION['commande_recente'] = $commande_id;
    
    // ============================================
    // NETTOYAGE DE LA SESSION
    // ============================================
    cleanUserSession();
    
    // ============================================
    // GÉNÉRATION DU PDF DE FACTURE
    // ============================================
    
    $pdf_content = null;
    $pdf_genere = false;
    $pdf_filename = 'facture_' . $commande['numero_commande'] . '.pdf';
    
    // Vérifier que TCPDF est disponible
    if (!defined('TCPDF_AVAILABLE') || !TCPDF_AVAILABLE) {
        if (file_exists(__DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php')) {
            require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            define('TCPDF_AVAILABLE', true);
        }
    }
    
    if (defined('TCPDF_AVAILABLE') && TCPDF_AVAILABLE) {
        try {
            $pdf_content = genererPDFFacture($commande, $items, $transaction);
            $pdf_genere = true;
            error_log("PDF généré avec succès pour commande #$commande_id - Taille: " . strlen($pdf_content) . " octets");
        } catch (Exception $e) {
            error_log("Erreur génération PDF: " . $e->getMessage());
        }
    }
    
    // ============================================
    // ENVOI DE L'EMAIL
    // ============================================
    
    $email_envoye = false;
    $email_message = '';
    
    if ($pdf_genere) {
        try {
            $smtp_config = [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'secure' => 'tls',
                'username' => 'lhpp.philippe@gmail.com',
                'password' => 'lvpk zqjt vuon qyrz',
                'from_email' => 'lhpp.philippe@gmail.com',
                'from_name' => 'HEURE DU CADEAU'
            ];
            
            $email_envoye = envoyerEmailAvecPDF(
                $smtp_config, 
                $commande, 
                $items, 
                $pdf_content,
                $pdf_filename
            );
            
            if ($email_envoye) {
                $email_message = "✅ Un email de confirmation avec votre facture en PDF a été envoyé à : " . htmlspecialchars($commande['email']);
            } else {
                $email_message = "⚠️ L'email de confirmation n'a pas pu être envoyé, mais votre commande est bien confirmée.";
            }
        } catch (Exception $e) {
            $email_message = "❌ Erreur lors de l'envoi de l'email: " . $e->getMessage();
            error_log("Erreur envoi email commande #$commande_id: " . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log("Erreur BDD confirmation: " . $e->getMessage());
    $commande = null;
    $items = [];
    $email_message = "Erreur technique lors de la récupération des informations.";
    $pdf_genere = false;
}

// Si commande non trouvée, rediriger
if (!$commande) {
    header('Location: index.php');
    exit;
}

// ============================================
// GÉNÉRATION D'UN PDF SIMPLE EN CAS D'ÉCHEC
// ============================================
if (!$pdf_genere) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Facture <?= htmlspecialchars($commande['numero_commande']) ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
            .header h1 { color: #2c3e50; margin: 0; }
            .facture-info { margin: 30px 0; }
            .info-block { margin: 20px 0; padding: 15px; background: #f9f9f9; border-radius: 8px; }
            table { width: 100%; border-collapse: collapse; margin: 30px 0; }
            th { background: #34495e; color: white; padding: 12px; text-align: left; }
            td { padding: 12px; border-bottom: 1px solid #ddd; }
            .old-price { text-decoration: line-through; color: #999; font-size: 12px; margin-right: 5px; }
            .promo-price { color: #e74c3c; font-weight: bold; }
            .total { font-weight: bold; font-size: 1.3em; text-align: right; margin-top: 30px; padding-top: 20px; border-top: 2px solid #333; }
            .footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #7f8c8d; border-top: 1px solid #ddd; padding-top: 20px; }
            .promo-badge { display: inline-block; background: #e74c3c; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 8px; vertical-align: middle; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HEURE DU CADEAU</h1>
            <h2>FACTURE</h2>
            <p>N° <?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p>Date : <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
        </div>
        
        <div class="info-block">
            <h3>Client</h3>
            <p>
                <?= htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) ?><br>
                <?= htmlspecialchars($commande['email']) ?>
            </p>
        </div>
        
        <div class="info-block">
            <h3>Adresse de livraison</h3>
            <p>
                <?= htmlspecialchars($commande['livraison_prenom'] . ' ' . $commande['livraison_nom']) ?><br>
                <?= htmlspecialchars($commande['livraison_adresse']) ?><br>
                <?php if (!empty($commande['livraison_complement'])): ?>
                    <?= htmlspecialchars($commande['livraison_complement']) ?><br>
                <?php endif; ?>
                <?= htmlspecialchars($commande['livraison_code_postal'] . ' ' . $commande['livraison_ville']) ?><br>
                <?= htmlspecialchars($commande['livraison_pays']) ?>
            </p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Référence</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($items as $item): 
                    $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
                    $total += $prix_total;
                    
                    $has_promotion = false;
                    $prix_original = $item['prix_unitaire_ttc'];
                    
                    if (isset($item['options']) && !empty($item['options'])) {
                        $options = json_decode($item['options'], true);
                        if (isset($options['prix_original']) && $options['prix_original'] > $item['prix_unitaire_ttc']) {
                            $has_promotion = true;
                            $prix_original = $options['prix_original'];
                        }
                    }
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($item['nom_produit']) ?>
                        <?php if ($has_promotion): ?>
                            <span class="promo-badge">-<?= $options['reduction_percent'] ?? round((1 - $item['prix_unitaire_ttc'] / $prix_original) * 100) ?>%</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($item['reference_produit']) ?></td>
                    <td><?= $item['quantite'] ?></td>
                    <td>
                        <?php if ($has_promotion): ?>
                            <span class="old-price"><?= number_format($prix_original, 2, ',', ' ') ?> €</span>
                            <span class="promo-price"><?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €</span>
                        <?php else: ?>
                            <?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €
                        <?php endif; ?>
                    </td>
                    <td><strong><?= number_format($prix_total, 2, ',', ' ') ?> €</strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <p>Sous-total : <?= number_format($total, 2, ',', ' ') ?> €</p>
            <p>Frais de livraison : <?= number_format($commande['frais_livraison'], 2, ',', ' ') ?> €</p>
            <p style="font-size: 1.3em; color: #e74c3c;">TOTAL TTC : <?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €</p>
        </div>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
        </div>
    </body>
    </html>
    <?php
    $html_pdf = ob_get_clean();
    
    $pdf_content = $html_pdf;
    $pdf_genere = true;
    $pdf_filename = 'facture_' . $commande['numero_commande'] . '.html';
}

// ============================================
// AFFICHAGE DE LA PAGE HTML AVEC PRIX PROMOTIONNELS
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande confirmée - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            max-width: 700px;
            width: 100%;
        }
        .card {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .success-icon {
            font-size: 100px;
            color: #27ae60;
            margin-bottom: 30px;
        }
        h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .email-note {
            background: <?= ($email_envoye) ? '#e8f5e9' : '#fff3cd' ?>;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: <?= ($email_envoye) ? '#2e7d32' : '#856404' ?>;
            border: 1px solid <?= ($email_envoye) ? '#c3e6cb' : '#ffeeba' ?>;
        }
        .order-details {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        .detail-value {
            color: #2c3e50;
            font-weight: 700;
        }
        .items-list {
            margin-top: 20px;
            border-top: 2px dashed #e0e0e0;
            padding-top: 20px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }
        .item-row.promotion {
            background: #fff3e0;
            border-radius: 8px;
            margin: 5px 0;
            padding: 8px 12px;
        }
        .old-price {
            text-decoration: line-through;
            color: #999;
            font-size: 0.85rem;
            margin-right: 5px;
        }
        .promo-badge {
            display: inline-block;
            background: #e74c3c;
            color: white;
            font-size: 0.7rem;
            padding: 2px 8px;
            border-radius: 20px;
            margin-left: 8px;
            font-weight: normal;
        }
        .promo-price {
            color: #e74c3c;
            font-weight: bold;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            font-weight: 800;
            font-size: 1.3rem;
            color: #e74c3c;
        }
        .btn {
            display: inline-block;
            padding: 16px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(39,174,96,0.3);
        }
        .auto-download-message {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Merci pour votre commande !</h1>
            
            <?php if ($pdf_genere): ?>
            <div class="auto-download-message" id="autoDownloadMessage">
                <i class="fas fa-download"></i>
                Téléchargement automatique de votre facture...
            </div>
            <?php endif; ?>
            
            <div class="email-note">
                <i class="fas <?= $email_envoye ? 'fa-envelope' : 'fa-exclamation-triangle' ?>"></i>
                <?= $email_message ?>
            </div>
            
            <div class="order-details">
                <div class="detail-row">
                    <span class="detail-label">Numéro de commande</span>
                    <span class="detail-value"><?= htmlspecialchars($commande['numero_commande']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></span>
                </div>
                <?php if (!empty($commande['reference_paiement'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Transaction</span>
                    <span class="detail-value"><?= htmlspecialchars(substr($commande['reference_paiement'], 0, 15)) ?>...</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($items)): ?>
                <div class="items-list">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;">
                        <i class="fas fa-tags"></i> Détail des articles
                    </h3>
                    <?php 
                    $total = 0;
                    foreach ($items as $item): 
                        $itemTotal = $item['quantite'] * $item['prix_unitaire_ttc'];
                        $total += $itemTotal;
                        
                        $has_promotion = false;
                        $prix_original = $item['prix_unitaire_ttc'];
                        $reduction_percent = 0;
                        
                        if (isset($item['options']) && !empty($item['options'])) {
                            $options = json_decode($item['options'], true);
                            if (isset($options['prix_original']) && $options['prix_original'] > $item['prix_unitaire_ttc']) {
                                $has_promotion = true;
                                $prix_original = $options['prix_original'];
                                $reduction_percent = $options['reduction_percent'] ?? round((1 - $item['prix_unitaire_ttc'] / $prix_original) * 100);
                            }
                        }
                    ?>
                    <div class="item-row <?= $has_promotion ? 'promotion' : '' ?>">
                        <span>
                            <?= htmlspecialchars($item['nom_produit']) ?> 
                            <small style="color: #7f8c8d;">x<?= $item['quantite'] ?></small>
                            <?php if ($has_promotion): ?>
                                <span class="promo-badge">-<?= $reduction_percent ?>%</span>
                            <?php endif; ?>
                        </span>
                        <span>
                            <?php if ($has_promotion): ?>
                                <span class="old-price"><?= number_format($prix_original, 2, ',', ' ') ?> €</span>
                                <span class="promo-price"><?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €</span>
                            <?php else: ?>
                                <?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €
                            <?php endif; ?>
                            <strong>→ <?= number_format($itemTotal, 2, ',', ' ') ?> €</strong>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="item-row" style="border-top: 1px solid #e0e0e0; margin-top: 10px; padding-top: 10px;">
                        <span>Frais de livraison</span>
                        <span><?= number_format($commande['frais_livraison'], 2, ',', ' ') ?> €</span>
                    </div>
                    
                    <div class="total-row">
                        <span>Total payé</span>
                        <span><?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        function downloadPDF() {
            <?php if ($pdf_genere && $pdf_content): ?>
            try {
                const byteCharacters = atob('<?= base64_encode($pdf_content) ?>');
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: 'application/pdf' });
                
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = '<?= $pdf_filename ?>';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
                
                const msg = document.getElementById('autoDownloadMessage');
                if (msg) {
                    msg.innerHTML = '<i class="fas fa-check-circle"></i> Téléchargement terminé !';
                    msg.style.backgroundColor = '#d4edda';
                    msg.style.color = '#155724';
                    msg.style.borderColor = '#c3e6cb';
                }
            } catch (e) {
                console.error('Erreur téléchargement:', e);
            }
            <?php endif; ?>
        }
        
        window.addEventListener('load', function() {
            setTimeout(downloadPDF, 500);
        });
    })();
    </script>
</body>
</html>