<?php
// ============================================
// CONFIGURATION EMAIL CORRIGÉE
// ============================================

// Charger l'autoloader de Composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    define('PHPMailer_AVAILABLE', true);
    define('TCPDF_AVAILABLE', true);
    error_log("✓ Autoloader chargé avec succès");
} else {
    define('PHPMailer_AVAILABLE', false);
    define('TCPDF_AVAILABLE', false);
    error_log("✗ vendor/autoload.php non trouvé - Exécutez 'composer install'");
}

// Chemin des logs
define('EMAIL_LOG_PATH', __DIR__ . '/logs/emails/');

// Créer le dossier de logs s'il n'existe pas
if (!is_dir(EMAIL_LOG_PATH)) {
    mkdir(EMAIL_LOG_PATH, 0755, true);
}

// Inclure la configuration SMTP
if (file_exists(__DIR__ . '/smtp_config.php')) {
    require_once __DIR__ . '/smtp_config.php';
} else {
    error_log("✗ smtp_config.php non trouvé");
}

/**
 * Retourne une instance de PHPMailer configurée
 */
function getPHPMailerInstance() {
    if (!defined('PHPMailer_AVAILABLE') || !PHPMailer_AVAILABLE) {
        error_log("PHPMailer non disponible");
        return null;
    }
    
    if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        error_log("Classe PHPMailer non trouvée");
        return null;
    }
    
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        
        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Options de sécurité
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Expéditeur
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Encodage
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
        error_log("✓ Instance PHPMailer créée avec succès");
        return $mail;
        
    } catch (Exception $e) {
        error_log("✗ Erreur création PHPMailer: " . $e->getMessage());
        return null;
    }
}
?>