<?php
echo "Document root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
echo "Script filename: " . __FILE__ . "<br>";
echo "Current dir: " . __DIR__ . "<br>";

// Testez différents chemins
$chemins_a_tester = [
    '/var/www/sean/uploads/produits/',
    '/var/www/html/sean/uploads/produits/',
    $_SERVER['DOCUMENT_ROOT'] . '/sean/uploads/produits/',
    __DIR__ . '/uploads/produits/',
    dirname(__DIR__) . '/uploads/produits/'
];

echo "<h3>Test des chemins :</h3>";
foreach ($chemins_a_tester as $chemin) {
    echo "$chemin : ";
    if (file_exists($chemin)) {
        echo "✅ Existe<br>";
    } else {
        echo "❌ N'existe pas<br>";
    }
}
?>