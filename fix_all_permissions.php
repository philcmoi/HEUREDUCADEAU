<?php
// fix_all_permissions.php - Script complet de correction des permissions
// À placer dans /var/www/sean/ et exécuter une fois

echo "<pre>";
echo "🔧 SCRIPT DE CORRECTION DES PERMISSIONS\n";
echo "========================================\n\n";

// Chemins
$base_dir = __DIR__ . '/';
$admin_dir = $base_dir . 'admin/';
$uploads_dir = $base_dir . 'uploads/';
$produits_dir = $uploads_dir . 'produits/';

echo "📁 Dossier racine: " . $base_dir . "\n";
echo "📁 Dossier admin: " . $admin_dir . "\n";
echo "📁 Dossier uploads: " . $uploads_dir . "\n";
echo "📁 Dossier produits: " . $produits_dir . "\n\n";

// ============================================
// ÉTAPE 1: Vérifier l'utilisateur PHP
// ============================================
echo "=== ÉTAPE 1: IDENTIFICATION DE L'UTILISATEUR PHP ===\n";
if (function_exists('posix_geteuid')) {
    $uid = posix_geteuid();
    $user = posix_getpwuid($uid);
    echo "✓ Utilisateur PHP: " . ($user['name'] ?? 'Inconnu') . " (UID: $uid)\n";
    
    $gid = posix_getegid();
    $group = posix_getgrgid($gid);
    echo "✓ Groupe PHP: " . ($group['name'] ?? 'Inconnu') . " (GID: $gid)\n";
} else {
    echo "✓ Fonctions POSIX non disponibles\n";
    echo "✓ Utilisateur PHP: " . (function_exists('get_current_user') ? get_current_user() : 'Inconnu') . "\n";
}
echo "\n";

// ============================================
// ÉTAPE 2: Vérifier les permissions actuelles
// ============================================
echo "=== ÉTAPE 2: VÉRIFICATION DES PERMISSIONS ACTUELLES ===\n";

$paths_to_check = [
    $base_dir => "Dossier racine",
    $admin_dir => "Dossier admin",
    $uploads_dir => "Dossier uploads",
    $produits_dir => "Dossier produits"
];

foreach ($paths_to_check as $path => $label) {
    if (file_exists($path)) {
        $perms = fileperms($path);
        $perms_octal = substr(sprintf('%o', $perms), -4);
        $writable = is_writable($path) ? "OUI" : "NON";
        $readable = is_readable($path) ? "OUI" : "NON";
        
        // Obtenir le propriétaire
        if (function_exists('posix_getpwuid')) {
            $owner = posix_getpwuid(fileowner($path));
            $owner_name = $owner['name'] ?? 'Inconnu';
            $group = posix_getgrgid(filegroup($path));
            $group_name = $group['name'] ?? 'Inconnu';
        } else {
            $owner_name = 'N/A';
            $group_name = 'N/A';
        }
        
        echo "$label: $path\n";
        echo "  - Existe: OUI\n";
        echo "  - Permissions: $perms_octal\n";
        echo "  - Propriétaire: $owner_name\n";
        echo "  - Groupe: $group_name\n";
        echo "  - Lecture: $readable\n";
        echo "  - Écriture: $writable\n\n";
    } else {
        echo "$label: $path\n";
        echo "  - Existe: NON\n\n";
    }
}

// ============================================
// ÉTAPE 3: TENTATIVE DE CORRECTION
// ============================================
echo "=== ÉTAPE 3: TENTATIVE DE CORRECTION ===\n";

// Méthode 1: Utiliser mkdir avec @ pour supprimer les warnings
echo "Méthode 1: Création des dossiers avec @mkdir...\n";

if (!file_exists($uploads_dir)) {
    if (@mkdir($uploads_dir, 0777, true)) {
        echo "✓ Dossier uploads créé avec succès\n";
        @chmod($uploads_dir, 0755);
    } else {
        echo "✗ Échec création uploads: " . error_get_last()['message'] . "\n";
    }
}

if (!file_exists($produits_dir)) {
    if (@mkdir($produits_dir, 0777, true)) {
        echo "✓ Dossier produits créé avec succès\n";
        @chmod($produits_dir, 0755);
    } else {
        echo "✗ Échec création produits: " . error_get_last()['message'] . "\n";
    }
}

// ============================================
// ÉTAPE 4: SOLUTION ALTERNATIVE - UTILISER UN AUTRE DOSSIER
// ============================================
echo "\n=== ÉTAPE 4: SOLUTION ALTERNATIVE ===\n";
echo "Si les méthodes ci-dessus échouent, voici une alternative:\n\n";

// Proposer un dossier temporaire dans /tmp
$temp_upload_dir = '/tmp/heureducadeau_uploads/produits/';
echo "Option A: Utiliser un dossier temporaire: $temp_upload_dir\n";
echo "Pour utiliser cette option, modifiez la fonction uploadImage():\n";
echo "\$target_dir = '/tmp/heureducadeau_uploads/produits/';\n\n";

// Proposer un dossier dans le home de l'utilisateur
$home_upload_dir = $_SERVER['HOME'] ?? '/home/' . get_current_user() . '/heureducadeau_uploads/produits/';
echo "Option B: Utiliser votre dossier personnel: $home_upload_dir\n\n";

// ============================================
// ÉTAPE 5: COMMANDES SSH À EXÉCUTER
// ============================================
echo "=== ÉTAPE 5: COMMANDES SSH À EXÉCUTER ===\n";
echo "Si vous avez accès SSH, exécutez ces commandes:\n\n";
echo "cd " . $base_dir . "\n";
echo "sudo mkdir -p uploads/produits\n";
echo "sudo chown -R www-data:www-data uploads/\n";
echo "sudo chmod -R 755 uploads/\n";
echo "sudo usermod -a -G www-data " . (get_current_user() ?? 'votre_utilisateur') . "\n\n";

// ============================================
// ÉTAPE 6: SOLUTION ULTIME - MODIFIER LE CODE
// ============================================
echo "=== ÉTAPE 6: SOLUTION ULTIME - MODIFICATION DU CODE ===\n";
echo "Si rien ne fonctionne, modifiez la fonction uploadImage() pour utiliser:\n\n";

echo "function uploadImage(\$file) {
    // Utiliser sys_get_temp_dir() pour un dossier temporaire
    \$target_dir = sys_get_temp_dir() . '/heureducadeau_uploads/produits/';
    
    if (!file_exists(\$target_dir)) {
        mkdir(\$target_dir, 0777, true);
    }
    
    // ... reste du code ...
    
    // Au lieu de retourner le chemin relatif, retourner le chemin absolu
    return ['success' => \$target_dir . \$new_filename];
}\n";

// ============================================
// ÉTAPE 7: TEST D'ÉCRITURE
// ============================================
echo "\n=== ÉTAPE 7: TEST D'ÉCRITURE ===\n";

$test_dirs = [
    $base_dir,
    $uploads_dir,
    $produits_dir,
    sys_get_temp_dir(),
    '/tmp/'
];

foreach ($test_dirs as $dir) {
    if (file_exists($dir) || $dir === sys_get_temp_dir() || $dir === '/tmp/') {
        if (!file_exists($dir) && $dir !== sys_get_temp_dir() && $dir !== '/tmp/') {
            continue;
        }
        
        $test_file = rtrim($dir, '/') . '/test_' . time() . '.txt';
        $result = @file_put_contents($test_file, 'test');
        
        if ($result !== false) {
            echo "✓ Test réussi dans: $dir\n";
            @unlink($test_file);
        } else {
            echo "✗ Test échoué dans: $dir - " . error_get_last()['message'] . "\n";
        }
    }
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
echo "</pre>";
?>