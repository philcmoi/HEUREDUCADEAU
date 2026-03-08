<?php
// check_upload.php - Script de diagnostic des permissions
require_once 'admin_protection.php';

echo "<pre>";
echo "=== DIAGNOSTIC DES PERMISSIONS D'UPLOAD ===\n\n";

// Chemins à vérifier
$base_dir = dirname(__DIR__) . '/';
$uploads_dir = $base_dir . 'uploads/';
$produits_dir = $uploads_dir . 'produits/';

echo "Base directory: " . $base_dir . "\n";
echo "Uploads directory: " . $uploads_dir . "\n";
echo "Produits directory: " . $produits_dir . "\n\n";

// Vérifier le dossier uploads
echo "=== DOSSIER UPLOADS ===\n";
if (file_exists($uploads_dir)) {
    echo "✓ Le dossier uploads existe\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($uploads_dir)), -4) . "\n";
    echo "Ecriture: " . (is_writable($uploads_dir) ? "✓ Oui" : "✗ Non") . "\n";
    echo "Propriétaire: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($uploads_dir))['name'] : 'N/A') . "\n";
} else {
    echo "✗ Le dossier uploads n'existe pas\n";
    echo "Tentative de création...\n";
    if (mkdir($uploads_dir, 0755, true)) {
        echo "✓ Dossier uploads créé avec succès\n";
    } else {
        echo "✗ Échec de la création: " . error_get_last()['message'] . "\n";
    }
}

echo "\n=== DOSSIER PRODUITS ===\n";
if (file_exists($produits_dir)) {
    echo "✓ Le dossier produits existe\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($produits_dir)), -4) . "\n";
    echo "Ecriture: " . (is_writable($produits_dir) ? "✓ Oui" : "✗ Non") . "\n";
    
    // Test d'écriture
    $test_file = $produits_dir . 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'test')) {
        echo "✓ Test d'écriture réussi\n";
        unlink($test_file);
    } else {
        echo "✗ Test d'écriture échoué\n";
    }
} else {
    echo "✗ Le dossier produits n'existe pas\n";
    echo "Tentative de création...\n";
    if (mkdir($produits_dir, 0755, true)) {
        echo "✓ Dossier produits créé avec succès\n";
    } else {
        echo "✗ Échec de la création: " . error_get_last()['message'] . "\n";
    }
}

echo "\n=== UTILISATEUR PHP ===\n";
echo "Utilisateur PHP: " . (function_exists('get_current_user') ? get_current_user() : 'N/A') . "\n";
if (function_exists('posix_geteuid')) {
    echo "UID PHP: " . posix_geteuid() . "\n";
}

echo "\n=== CONFIGURATION PHP ===\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "\n";
echo "open_basedir: " . (ini_get('open_basedir') ?: 'Désactivé') . "\n";

echo "\n=== SOLUTION ===\n";
echo "Si des problèmes persistent, exécutez ces commandes en SSH :\n";
echo "cd " . $base_dir . "\n";
echo "sudo mkdir -p uploads/produits\n";
echo "sudo chown -R www-data:www-data uploads/\n";
echo "sudo chmod -R 755 uploads/\n";

echo "\n=== FIN DU DIAGNOSTIC ===\n";
echo "</pre>";
?>