<?php
// ============================================
// TEST SIMPLE - À EXÉCUTER D'ABORD
// ============================================
echo "<pre>";
echo "=== DIAGNOSTIC SYSTÈME ===\n\n";

// 1. Vérifier le dossier actuel
echo "1. Dossier actuel : " . __DIR__ . "\n";

// 2. Vérifier si Composer est installé
echo "2. Vérification Composer :\n";
if (file_exists(__DIR__ . '/composer.json')) {
    echo "   - composer.json présent\n";
} else {
    echo "   - composer.json manquant\n";
}

if (file_exists(__DIR__ . '/composer.lock')) {
    echo "   - composer.lock présent\n";
} else {
    echo "   - composer.lock manquant\n";
}

// 3. Vérifier le dossier vendor
echo "\n3. Vérification vendor :\n";
if (is_dir(__DIR__ . '/vendor')) {
    echo "   - Dossier vendor présent\n";
    
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "   - autoload.php présent\n";
        
        // Tester le chargement
        require_once __DIR__ . '/vendor/autoload.php';
        echo "   - ✓ autoload.php chargé avec succès\n";
        
        // Vérifier PHPMailer
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "   - ✓ PHPMailer trouvé\n";
        } else {
            echo "   - ✗ PHPMailer non trouvé\n";
        }
        
        // Vérifier TCPDF
        if (class_exists('\\TCPDF')) {
            echo "   - ✓ TCPDF trouvé\n";
        } else {
            echo "   - ✗ TCPDF non trouvé\n";
        }
    } else {
        echo "   - ✗ autoload.php manquant\n";
    }
} else {
    echo "   - ✗ Dossier vendor manquant\n";
}

// 4. Vérifier les permissions
echo "\n4. Permissions :\n";
$logs_dir = __DIR__ . '/logs/emails';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
    echo "   - Dossier logs créé\n";
}
echo "   - Dossier logs : " . (is_writable($logs_dir) ? "accessible en écriture" : "non accessible") . "\n";

// 5. Vérifier smtp_config.php
echo "\n5. Configuration SMTP :\n";
if (file_exists(__DIR__ . '/smtp_config.php')) {
    echo "   - smtp_config.php présent\n";
    require_once __DIR__ . '/smtp_config.php';
    echo "   - SMTP_HOST : " . (defined('SMTP_HOST') ? SMTP_HOST : 'non défini') . "\n";
    echo "   - SMTP_USERNAME : " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'non défini') . "\n";
} else {
    echo "   - ✗ smtp_config.php manquant\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";
echo "</pre>";
?>