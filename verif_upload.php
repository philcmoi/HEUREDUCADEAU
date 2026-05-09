<?php
$upload_dir = '/var/www/sean/uploads/produits/';

echo "<h2>Vérification du dossier d'upload</h2>";
echo "Dossier: " . $upload_dir . "<br>";
echo "Existe: " . (file_exists($upload_dir) ? '✅ Oui' : '❌ Non') . "<br>";
echo "Accessible en écriture: " . (is_writable($upload_dir) ? '✅ Oui' : '❌ Non') . "<br>";

if (file_exists($upload_dir)) {
    $files = scandir($upload_dir);
    $images = array_filter($files, function($f) {
        return !in_array($f, ['.', '..']) && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f);
    });
    
    echo "<h3>Images dans le dossier (" . count($images) . "):</h3>";
    foreach ($images as $img) {
        echo "- $img <br>";
        echo "  URL: /uploads/produits/$img <br>";
        echo "  Chemin complet: $upload_dir$img <br><br>";
    }
}

// Test d'écriture
$test_file = $upload_dir . 'test_write_' . time() . '.txt';
if (file_put_contents($test_file, 'test')) {
    echo "✅ Test d'écriture réussi<br>";
    unlink($test_file);
} else {
    echo "❌ Test d'écriture échoué<br>";
}
?>