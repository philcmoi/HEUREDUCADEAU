<?php
// test_email.php - Script de test d'envoi d'email
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config_email.php';
require_once __DIR__ . '/fonctions_email.php';

echo "<h1>Test d'envoi d'email</h1>";

// Test 1 : Vérifier la configuration
echo "<h2>1. Vérification de la configuration</h2>";
echo "PHPMailer disponible : " . (PHPMailer_AVAILABLE ? '✅ OUI' : '❌ NON') . "<br>";
echo "Dossier logs : " . (is_dir(EMAIL_LOG_PATH) ? '✅ OK' : '❌ Manquant') . "<br>";

// Test 2 : Tester la création de l'instance PHPMailer
echo "<h2>2. Test de création PHPMailer</h2>";
$mail = getPHPMailerInstance();
if ($mail) {
    echo "✅ Instance PHPMailer créée avec succès<br>";
} else {
    echo "❌ Échec de création PHPMailer<br>";
}

// Test 3 : Envoyer un email de test
echo "<h2>3. Envoi d'un email de test</h2>";

$email_test = 'lhpp.philippe@gmail.com'; // Votre email pour recevoir le test

$mail = getPHPMailerInstance();
if ($mail) {
    try {
        $mail->addAddress($email_test);
        $mail->Subject = 'Test de configuration email - HEURE DU CADEAU';
        $mail->isHTML(true);
        $mail->Body = '
            <h1>Test réussi !</h1>
            <p>Votre configuration email fonctionne correctement.</p>
            <p><strong>Date du test :</strong> ' . date('d/m/Y H:i:s') . '</p>
            <p><strong>Serveur :</strong> ' . $_SERVER['SERVER_NAME'] . '</p>
        ';
        $mail->AltBody = 'Test réussi ! Votre configuration email fonctionne correctement. Date : ' . date('d/m/Y H:i:s');
        
        if ($mail->send()) {
            echo "✅ Email de test envoyé avec succès à <strong>$email_test</strong><br>";
            
            // Journaliser le succès
            $log_message = date('Y-m-d H:i:s') . " - Email de test envoyé à $email_test\n";
            file_put_contents(EMAIL_LOG_PATH . 'test_success.log', $log_message, FILE_APPEND);
        } else {
            echo "❌ Échec de l'envoi : " . $mail->ErrorInfo . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Exception : " . $e->getMessage() . "<br>";
    }
}

// Test 4 : Tester la fonction envoyerFactureEmail si une commande existe
echo "<h2>4. Test avec une vraie commande (optionnel)</h2>";
echo "Pour tester avec une vraie commande, ajoutez ?commande_id=XXX à l'URL<br>";

if (isset($_GET['commande_id'])) {
    $commande_id = intval($_GET['commande_id']);
    $pdo = getPDOConnection();
    
    if ($pdo) {
        echo "Tentative d'envoi de facture pour la commande #$commande_id...<br>";
        $resultat = envoyerFactureEmail($pdo, $commande_id);
        echo $resultat ? "✅ Envoi réussi" : "❌ Échec de l'envoi (voir logs)";
    } else {
        echo "❌ Connexion BDD échouée";
    }
}

// Afficher les logs récents
echo "<h2>Logs récents</h2>";
$log_files = [
    'envois.log' => EMAIL_LOG_PATH . 'envois.log',
    'erreurs.log' => EMAIL_LOG_PATH . 'erreurs.log',
    'test_success.log' => EMAIL_LOG_PATH . 'test_success.log'
];

foreach ($log_files as $name => $path) {
    echo "<h3>$name</h3>";
    if (file_exists($path)) {
        $logs = file($path);
        $logs = array_slice($logs, -5); // Dernières 5 lignes
        echo "<pre style='background:#f4f4f4; padding:10px;'>";
        foreach ($logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    } else {
        echo "<p>Aucun log trouvé</p>";
    }
}
?>