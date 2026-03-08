<?php
// test_image.php
$image_path = '/sean/uploads/produits/69ad08dd290d9_20260308_0527...';
echo "Chemin de l'image : " . $image_path . "<br>";
echo "Fichier physique : " . __DIR__ . $image_path . "<br>";
echo "Fichier existe : " . (file_exists(__DIR__ . $image_path) ? 'Oui' : 'Non') . "<br>";
?>