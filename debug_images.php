<?php
require_once 'session_verification.php';

$pdo = getPDOConnection();

echo "<h1>Diagnostic des images produits</h1>";

// 1. Vérifier la structure de la table
$structure = $pdo->query("DESCRIBE images_produits")->fetchAll();
echo "<h2>Structure de la table</h2>";
echo "<pre>";
print_r($structure);
echo "</pre>";

// 2. Compter les images
$count = $pdo->query("SELECT COUNT(*) FROM images_produits")->fetchColumn();
echo "<p><strong>Total images :</strong> $count</p>";

// 3. Lister toutes les images avec leurs produits
$images = $pdo->query("
    SELECT i.*, p.nom as produit_nom, p.reference 
    FROM images_produits i
    LEFT JOIN produits p ON i.id_produit = p.id_produit
    ORDER BY i.id_produit, i.principale DESC
")->fetchAll();

echo "<h2>Détail des images</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID Image</th><th>ID Produit</th><th>Produit</th><th>URL</th><th>Principale</th><th>Test URL</th></tr>";

foreach ($images as $img) {
    $test_url = "<span style='color:red'>❌ Non accessible</span>";
    
    // Construire le chemin complet sur le serveur
    $full_path = $_SERVER['DOCUMENT_ROOT'] . $img['url_image'];
    if (file_exists($full_path)) {
        $test_url = "<span style='color:green'>✅ Fichier présent</span>";
    } else {
        $test_url = "<span style='color:red'>❌ Fichier manquant: $full_path</span>";
    }
    
    echo "<tr>";
    echo "<td>{$img['id_image']}</td>";
    echo "<td>{$img['id_produit']}</td>";
    echo "<td>" . htmlspecialchars($img['produit_nom'] ?? 'N/A') . "</td>";
    echo "<td>" . htmlspecialchars($img['url_image']) . "</td>";
    echo "<td>" . ($img['principale'] ? '✅ Oui' : '❌ Non') . "</td>";
    echo "<td>$test_url</td>";
    echo "</tr>";
}
echo "</table>";

// 4. Vérifier les produits sans image
$produits_sans_image = $pdo->query("
    SELECT p.id_produit, p.nom, p.reference 
    FROM produits p
    LEFT JOIN images_produits i ON p.id_produit = i.id_produit
    WHERE i.id_image IS NULL
")->fetchAll();

echo "<h2>Produits sans aucune image</h2>";
if (count($produits_sans_image) > 0) {
    echo "<ul>";
    foreach ($produits_sans_image as $p) {
        echo "<li>#{$p['id_produit']} - {$p['nom']} (Réf: {$p['reference']})</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Tous les produits ont au moins une image !</p>";
}
?>