<?php
// ============================================
// CONFIRMATION PAIEMENT - VERSION CORRIGÉE
// AVEC TÉLÉCHARGEMENT AUTO DIRECT (SANS REDIRECTION)
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
    header('Location: index.html');
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
// FONCTIONS DE MESSAGE (définies avant utilisation)
// ============================================

/**
 * Génère la version HTML du message
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
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['nom_produit']) . '</td>
                        <td>' . htmlspecialchars($item['reference_produit']) . '</td>
                        <td>' . $item['quantite'] . '</td>
                        <td>' . number_format($item['prix_unitaire_ttc'], 2, ',', ' ') . ' €</td>
                        <td>' . number_format($prix_total, 2, ',', ' ') . ' €</td>
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
 * Génère la version texte du message
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
        $text .= "- " . $item['nom_produit'] . " x" . $item['quantite'] . ": " . number_format($prix_total, 2) . " €\n";
    }
    
    $text .= "\nTotal: " . number_format($commande['total_ttc'], 2) . " €\n\n";
    $text .= "Votre facture au format PDF est jointe à cet email.\n\n";
    $text .= "Livraison estimée: 3-5 jours\n\n";
    $text .= "HEURE DU CADEAU\n";
    $text .= "contact@heureducadeau.fr";
    
    return $text;
}

/**
 * Envoie un email avec la facture en pièce jointe PDF - VERSION CORRIGÉE
 * Garantit que la pièce jointe PDF est correctement incluse
 */
function envoyerEmailAvecPDF($smtp_config, $commande, $items, $pdf_content, $pdf_filename) {
    $to = $commande['email'];
    $to_name = $commande['client_prenom'] . ' ' . $commande['client_nom'];
    $subject = "Votre commande " . $commande['numero_commande'] . " a été confirmée";
    
    // ============================================
    // CONSTRUCTION DU MESSAGE MULTIPART/MIXED
    // ============================================
    
    $boundary_mixed = md5(uniqid(mt_rand(), true));
    $boundary_alt = md5(uniqid(mt_rand(), true));
    
    // ============================================
    // VERSION HTML DU MESSAGE
    // ============================================
    
    $html_message = genererMessageHTML($commande, $items);
    
    // ============================================
    // VERSION TEXTE DU MESSAGE
    // ============================================
    
    $text_message = genererMessageTexte($commande, $items);
    
    // ============================================
    // PARTIE ALTERNATIVE (HTML + TEXTE)
    // ============================================
    
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
    
    // ============================================
    // CONSTRUCTION DU MESSAGE COMPLET (MIXED)
    // ============================================
    
    $message = "Ceci est un message au format MIME multipart/mixed contenant votre facture en pièce jointe.\r\n\r\n";
    $message .= "--$boundary_mixed\r\n";
    
    // On ajoute la partie alternative (le corps de l'email)
    $message .= $alt_part . "\r\n\r\n";
    
    // ============================================
    // AJOUT DE LA PIÈCE JOINTE PDF
    // ============================================
    if ($pdf_content) {
        $pdf_attachment = chunk_split(base64_encode($pdf_content));
        
        $message .= "--$boundary_mixed\r\n";
        $message .= "Content-Type: application/pdf; name=\"$pdf_filename\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"$pdf_filename\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= $pdf_attachment . "\r\n\r\n";
    }
    
    // Fermeture du mixed boundary
    $message .= "--$boundary_mixed--";
    
    // ============================================
    // HEADERS
    // ============================================
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary_mixed . '"',
        'From: =?UTF-8?B?' . base64_encode($smtp_config['from_name']) . '?= <' . $smtp_config['from_email'] . '>',
        'Reply-To: ' . $smtp_config['from_email'],
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // ============================================
    // SUJET ENCODÉ
    // ============================================
    $encoded_subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    
    // ============================================
    // ENVOI VIA SMTP - VERSION CORRIGÉE AVEC GESTION DES ERREURS
    // ============================================
    
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($smtp_config['host'], $smtp_config['port'], $errno, $errstr, 30);
    
    if (!$socket) {
        error_log("Erreur connexion SMTP: $errstr ($errno)");
        return false;
    }
    
    // Fonction interne pour fermer le socket et retourner false
    $closeAndReturnFalse = function($error_message) use ($socket) {
        error_log($error_message);
        fclose($socket);
        return false;
    };
    
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        return $closeAndReturnFalse("Réponse SMTP inattendue: $response");
    }
    
    // EHLO
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($socket, 515);
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
    }
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("EHLO échoué: $response");
    }
    
    // STARTTLS
    fputs($socket, "STARTTLS\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '220') {
        return $closeAndReturnFalse("STARTTLS échoué: $response");
    }
    
    // Activer TLS
    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        return $closeAndReturnFalse("Échec de l'activation TLS");
    }
    
    // EHLO à nouveau après TLS
    fputs($socket, "EHLO " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\n");
    $response = fgets($socket, 515);
    while (substr($response, 3, 1) == '-') {
        $response = fgets($socket, 515);
    }
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("EHLO après TLS échoué: $response");
    }
    
    // Authentification
    fputs($socket, "AUTH LOGIN\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        return $closeAndReturnFalse("AUTH LOGIN échoué: $response");
    }
    
    // Envoyer username en base64
    fputs($socket, base64_encode($smtp_config['username']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '334') {
        return $closeAndReturnFalse("Username échoué: $response");
    }
    
    // Envoyer password en base64
    fputs($socket, base64_encode($smtp_config['password']) . "\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '235') {
        return $closeAndReturnFalse("Authentification échouée: $response");
    }
    
    // MAIL FROM
    fputs($socket, "MAIL FROM: <" . $smtp_config['from_email'] . ">\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("MAIL FROM échoué: $response");
    }
    
    // RCPT TO
    fputs($socket, "RCPT TO: <$to>\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '250') {
        return $closeAndReturnFalse("RCPT TO échoué: $response");
    }
    
    // DATA
    fputs($socket, "DATA\r\n");
    $response = fgets($socket, 515);
    if (substr($response, 0, 3) != '354') {
        return $closeAndReturnFalse("DATA échoué: $response");
    }
    
    // Envoyer les headers, le sujet et le message
    fputs($socket, "Subject: $encoded_subject\r\n");
    foreach ($headers as $header) {
        fputs($socket, $header . "\r\n");
    }
    fputs($socket, "\r\n");
    fputs($socket, $message . "\r\n");
    
    // Fin du message
    fputs($socket, "\r\n.\r\n");
    $response = fgets($socket, 515);
    
    // QUIT
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
    
    // Récupérer les articles
    $stmt_items = $pdo->prepare("SELECT * FROM commande_items WHERE id_commande = ?");
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
    // ENVOI DE L'EMAIL EN ARRIÈRE-PLAN (NON BLOQUANT)
    // ============================================
    
    $email_envoye = false;
    $email_message = '';
    
    if ($pdf_genere) {
        try {
            // Configuration SMTP Gmail
            $smtp_config = [
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'secure' => 'tls',
                'username' => 'lhpp.philippe@gmail.com',
                'password' => 'lvpk zqjt vuon qyrz',
                'from_email' => 'lhpp.philippe@gmail.com',
                'from_name' => 'HEURE DU CADEAU'
            ];
            
            // Envoyer l'email avec PDF en pièce jointe
            $email_envoye = envoyerEmailAvecPDF(
                $smtp_config, 
                $commande, 
                $items, 
                $pdf_content,
                $pdf_filename
            );
            
            if ($email_envoye) {
                $email_message = "✅ Un email de confirmation avec votre facture en PDF a été envoyé à : " . htmlspecialchars($commande['email']);
                
                // Journaliser dans la base de données
                try {
                    $stmt_log = $pdo->prepare("
                        INSERT INTO logs (type_log, niveau, message, utilisateur_id, metadata, date_log)
                        VALUES ('info', 'info', 'Email avec facture PDF envoyé', ?, ?, NOW())
                    ");
                    $stmt_log->execute([
                        $commande['id_client'],
                        json_encode([
                            'commande_id' => $commande_id,
                            'email' => $commande['email'],
                            'numero_commande' => $commande['numero_commande'],
                            'pdf_genere' => true,
                            'pdf_taille' => strlen($pdf_content)
                        ])
                    ]);
                } catch (Exception $e) {
                    // Ignorer les erreurs de log
                }
            } else {
                $email_message = "⚠️ L'email de confirmation n'a pas pu être envoyé, mais votre commande est bien confirmée.";
            }
        } catch (Exception $e) {
            $email_message = "❌ Erreur lors de l'envoi de l'email: " . $e->getMessage();
            error_log("Erreur envoi email commande #$commande_id: " . $e->getMessage());
        }
    } else {
        $email_message = "⚠️ La facture n'a pas pu être générée, mais votre commande est confirmée.";
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
    header('Location: index.html');
    exit;
}

// ============================================
// GÉNÉRATION D'UN PDF SIMPLE EN CAS D'ÉCHEC
// ============================================
if (!$pdf_genere) {
    // Générer un PDF simple en HTML pour le téléchargement
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
            .total { font-weight: bold; font-size: 1.3em; text-align: right; margin-top: 30px; padding-top: 20px; border-top: 2px solid #333; }
            .footer { margin-top: 50px; text-align: center; font-size: 0.9em; color: #7f8c8d; border-top: 1px solid #ddd; padding-top: 20px; }
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
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['nom_produit']) ?></td>
                    <td><?= htmlspecialchars($item['reference_produit']) ?></td>
                    <td><?= $item['quantite'] ?></td>
                    <td><?= number_format($item['prix_unitaire_ttc'], 2, ',', ' ') ?> €</td>
                    <td><?= number_format($prix_total, 2, ',', ' ') ?> €</td>
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
    
    // Convertir le HTML en PDF via une librairie ou utiliser Dompdf si disponible
    // Pour simplifier, on va juste forcer le téléchargement du HTML avec une extension .pdf
    // Le navigateur l'ouvrira quand même
    $pdf_content = $html_pdf;
    $pdf_genere = true;
    $pdf_filename = 'facture_' . $commande['numero_commande'] . '.html';
}

// ============================================
// AFFICHAGE DE LA PAGE HTML (avec téléchargement JS)
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
        .btn-download {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border: none;
            display: inline-block;
        }
        .btn-download:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(52,152,219,0.3);
        }
        .pdf-badge {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-size: 1rem;
        }
        .auto-download-message {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
        .manual-download-link {
            display: inline-block;
            margin-top: 20px;
            font-size: 0.9rem;
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
            
            <!-- Affichage du message email sans htmlspecialchars car il contient du HTML sécurisé -->
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
                        <i class="fas fa-box"></i> Détail des articles
                    </h3>
                    <?php 
                    $total = 0;
                    foreach ($items as $item): 
                        $itemTotal = $item['quantite'] * $item['prix_unitaire_ttc'];
                        $total += $itemTotal;
                    ?>
                    <div class="item-row">
                        <span>
                            <?= htmlspecialchars($item['nom_produit']) ?> 
                            <small style="color: #7f8c8d;">x<?= $item['quantite'] ?></small>
                        </span>
                        <span><?= number_format($itemTotal, 2, ',', ' ') ?> €</span>
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
            
            <div style="margin: 20px 0;">
                <a href="telecharger-facture.php?commande_id=<?= $commande_id ?>" class="btn btn-download" target="_blank" id="downloadLink">
                    <i class="fas fa-file-pdf"></i> Télécharger ma facture (PDF)
                </a>
            </div>
            
            <div style="margin-top: 30px;">
                <a href="index.html" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        'use strict';
        
        // Fonction pour lancer le téléchargement du PDF
        function downloadPDF() {
            <?php if ($pdf_genere && $pdf_content): ?>
            try {
                // Créer un blob à partir du contenu PDF
                const byteCharacters = atob('<?= base64_encode($pdf_content) ?>');
                const byteNumbers = new Array(byteCharacters.length);
                for (let i = 0; i < byteCharacters.length; i++) {
                    byteNumbers[i] = byteCharacters.charCodeAt(i);
                }
                const byteArray = new Uint8Array(byteNumbers);
                const blob = new Blob([byteArray], { type: 'application/pdf' });
                
                // Créer un lien de téléchargement
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = '<?= $pdf_filename ?>';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                window.URL.revokeObjectURL(url);
                
                // Mettre à jour le message
                const msg = document.getElementById('autoDownloadMessage');
                if (msg) {
                    msg.innerHTML = '<i class="fas fa-check-circle"></i> Téléchargement terminé !';
                    msg.style.backgroundColor = '#d4edda';
                    msg.style.color = '#155724';
                    msg.style.borderColor = '#c3e6cb';
                }
            } catch (e) {
                console.error('Erreur téléchargement:', e);
                // Fallback vers le lien traditionnel
                const link = document.getElementById('downloadLink');
                if (link) {
                    link.click();
                }
            }
            <?php endif; ?>
        }
        
        // Lancer le téléchargement après le chargement de la page
        window.addEventListener('load', function() {
            // Petit délai pour que la page s'affiche d'abord
            setTimeout(downloadPDF, 500);
        });
        
        // Si le téléchargement automatique ne fonctionne pas,
        // l'utilisateur peut cliquer sur le lien manuellement
    })();
    </script>
</body>
</html>