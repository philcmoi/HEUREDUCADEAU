<?php
// fix_upload_permissions.php - À exécuter une seule fois

// Protection admin
require_once 'admin_protection.php';

echo "<pre>";
echo "=== Correction des permissions d'upload ===\n\n";

// Définir le chemin
$upload_dir = "uploads/produits/";

// 1. Créer le dossier s'il n'existe pas
if (!file_exists($upload_dir)) {
    echo "Création du dossier : $upload_dir\n";
    if (mkdir($upload_dir, 0755, true)) {
        echo "✓ Dossier créé avec succès\n";
    } else {
        echo "✗ Erreur : Impossible de créer le dossier\n";
        exit;
    }
} else {
    echo "Le dossier existe déjà : $upload_dir\n";
}

// 2. Vérifier et corriger les permissions
$current_perms = substr(sprintf('%o', fileperms($upload_dir)), -4);
echo "Permissions actuelles : $current_perms\n";

if ($current_perms != '0755' && $current_perms != '0777') {
    echo "Correction des permissions...\n";
    if (chmod($upload_dir, 0755)) {
        echo "✓ Permissions corrigées à 0755\n";
    } else {
        echo "✗ Erreur : Impossible de modifier les permissions\n";
    }
}

// 3. Vérifier le propriétaire
if (function_exists('posix_getpwuid')) {
    $owner = posix_getpwuid(fileowner($upload_dir));
    $group = posix_getgrgid(filegroup($upload_dir));
    echo "Propriétaire : " . ($owner['name'] ?? 'Inconnu') . "\n";
    echo "Groupe : " . ($group['name'] ?? 'Inconnu') . "\n";
}

// 4. Vérifier l'écriture
if (is_writable($upload_dir)) {
    echo "✓ Le dossier est accessible en écriture\n";
    
    // Test d'écriture
    $test_file = $upload_dir . 'test_permission.txt';
    if (file_put_contents($test_file, 'test')) {
        echo "✓ Test d'écriture réussi\n";
        unlink($test_file);
        echo "✓ Fichier de test supprimé\n";
    } else {
        echo "✗ Erreur : Impossible d'écrire dans le dossier\n";
    }
} else {
    echo "✗ Erreur : Le dossier n'est PAS accessible en écriture\n";
}

// 5. Créer un .htaccess pour la sécurité
$htaccess_content = "# Protéger le dossier d'upload\n";
$htaccess_content .= "Options -Indexes\n";
$htaccess_content .= "Order Deny,Allow\n";
$htaccess_content .= "Deny from all\n";
$htaccess_content .= "<FilesMatch \"\.(jpg|jpeg|png|gif|webp)$\">\n";
$htaccess_content .= "    Order Allow,Deny\n";
$htaccess_content .= "    Allow from all\n";
$htaccess_content .= "</FilesMatch>\n";

if (file_put_contents($upload_dir . '.htaccess', $htaccess_content)) {
    echo "✓ Fichier .htaccess créé pour la sécurité\n";
} else {
    echo "✗ Erreur : Impossible de créer le .htaccess\n";
}

// 6. Informations système
echo "\n=== Informations système ===\n";
echo "Utilisateur PHP : " . (function_exists('get_current_user') ? get_current_user() : 'N/A') . "\n";
echo "Chemin absolu : " . realpath($upload_dir) . "\n";
echo "Document root : " . $_SERVER['DOCUMENT_ROOT'] . "\n";

// 7. Vérification de la configuration PHP
echo "\n=== Configuration PHP ===\n";
echo "upload_max_filesize : " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size : " . ini_get('post_max_size') . "\n";
echo "open_basedir : " . (ini_get('open_basedir') ?: 'Désactivé') . "\n";

echo "\n=== Solution manuelle si nécessaire ===\n";
echo "Si les problèmes persistent, exécutez ces commandes en SSH :\n";
echo "sudo chown -R www-data:www-data " . realpath($upload_dir) . "\n";
echo "sudo chmod -R 755 " . realpath($upload_dir) . "\n";

echo "\n=== FIN DE LA VÉRIFICATION ===\n";
echo "</pre>";
?>