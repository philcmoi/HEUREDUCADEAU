<?php
// config_email.php - Configuration pour l'envoi d'emails
// Version corrigée avec gestion d'erreur améliorée

// Définir le chemin de base du projet
define('BASE_PATH', dirname(__FILE__));

// Vérifier si le fichier autoload existe avant de l'inclure
$autoloadPath = BASE_PATH . '/vendor/autoload.php';

if (!file_exists($autoloadPath)) {
    die("Erreur : Les dépendances Composer ne sont pas installées. Veuillez exécuter 'composer install' à la racine du projet.");
}

require_once $autoloadPath;

// Configuration pour PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Fonction pour envoyer un email
function sendEmail($to, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Configuration du serveur
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Mettre à DEBUG_SERVER pour le débogage
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; // Remplacez par votre serveur SMTP
        $mail->SMTPAuth   = true;
        $mail->Username   = 'lhpp.philippe@gmail.com'; // Votre email
        $mail->Password   = 'lvpk zqjt vuon qyrz'; // Votre mot de passe ou mot de passe d'application
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Expéditeur et destinataire
        $mail->setFrom('lhpp.philippe@gmail.com', 'Site Web');
        $mail->addAddress($to);
        
        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Erreur d'envoi d'email : " . $mail->ErrorInfo);
        return false;
    }
}
?>