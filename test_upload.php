<?php
echo "<h2>Test d'upload</h2>";

// Configuration
$upload_dir = '/var/www/sean/uploads/produits/';
echo "Dossier d'upload : " . $upload_dir . "<br>";

// 1. Vérifier que le dossier existe
if (!file_exists($upload_dir)) {
    echo "<span style='color:red'>✗ Le dossier n'existe pas</span><br>";
    echo "Créez-le avec : sudo mkdir -p " . $upload_dir . "<br>";
} else {
    echo "<span style='color:green'>✓ Le dossier existe</span><br>";
    
    // 2. Vérifier les permissions
    $perms = fileperms($upload_dir);
    $perms_str = substr(sprintf('%o', $perms), -4);
    echo "Permissions : " . $perms_str . "<br>";
    
    if (is_writable($upload_dir)) {
        echo "<span style='color:green'>✓ Le dossier est accessible en écriture</span><br>";
    } else {
        echo "<span style='color:red'>✗ Le dossier n'est PAS accessible en écriture</span><br>";
        echo "Corrigez avec : sudo chown www-data:www-data " . $upload_dir . "<br>";
        echo "sudo chmod 755 " . $upload_dir . "<br>";
    }
    
    // 3. Tester l'écriture
    $test_file = $upload_dir . 'test_' . time() . '.txt';
    if (file_put_contents($test_file, 'Test')) {
        echo "<span style='color:green'>✓ Écriture réussie !</span><br>";
        unlink($test_file); // Supprimer le fichier test
    } else {
        echo "<span style='color:red'>✗ Impossible d'écrire dans le dossier</span><br>";
    }
}

// 4. Vérifier la configuration PHP
echo "<h3>Configuration PHP :</h3>";
echo "upload_max_filesize : " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size : " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads : " . ini_get('max_file_uploads') . "<br>";

// 5. Formulaire de test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_image'])) {
    echo "<h3>Résultat de l'upload test :</h3>";
    
    $file = $_FILES['test_image'];
    echo "Nom original : " . $file['name'] . "<br>";
    echo "Taille : " . $file['size'] . " bytes<br>";
    echo "Type : " . $file['type'] . "<br>";
    echo "Erreur : " . $file['error'] . "<br>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $destination = $upload_dir . 'test_' . time() . '_' . $file['name'];
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            echo "<span style='color:green'>✓ Upload réussi !</span><br>";
            echo "Fichier sauvegardé : " . $destination . "<br>";
            
            // Afficher l'image
            $url = '/sean/uploads/produits/' . basename($destination);
            echo "<img src='" . $url . "' style='max-width:200px;'><br>";
            echo "URL : " . $url;
        } else {
            echo "<span style='color:red'>✗ Échec du déplacement du fichier</span><br>";
            echo "Erreur : " . error_get_last()['message'];
        }
    } else {
        $errors = [
            UPLOAD_ERR_INI_SIZE => "Le fichier dépasse upload_max_filesize",
            UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse MAX_FILE_SIZE",
            UPLOAD_ERR_PARTIAL => "Fichier partiellement uploadé",
            UPLOAD_ERR_NO_FILE => "Aucun fichier uploadé",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
            UPLOAD_ERR_CANT_WRITE => "Échec d'écriture",
            UPLOAD_ERR_EXTENSION => "Extension PHP a bloqué l'upload"
        ];
        echo "<span style='color:red'>✗ Erreur : " . ($errors[$file['error']] ?? "Erreur inconnue") . "</span><br>";
    }
}
?>

<h3>Formulaire de test d'upload</h3>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_image" accept="image/*" required>
    <button type="submit">Tester l'upload</button>
</form>