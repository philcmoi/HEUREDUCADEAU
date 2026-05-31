<?php
// diagnostic_produits.php
// Placez ce fichier dans votre dossier racine et exécutez-le

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'session_verification.php';

$pdo = getPDOConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <title>Diagnostic des produits</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; background: white; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #4CAF50; color: white; }
        .success { background: #d4edda; color: #155724; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; margin: 10px 0; border-radius: 5px; }
        h2 { margin-top: 30px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnostic complet des produits</h1>";

if (!$pdo) {
    echo "<div class='error'>❌ Erreur de connexion à la base de données</div>";
    echo "</body></html>";
    exit;
}

echo "<div class='success'>✅ Connexion BDD établie</div>";

// ============================================
// 1. Compter les produits
// ============================================
echo "<h2>📊 1. État général des produits</h2>";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM produits");
$total = $stmt->fetchColumn();
echo "<p><strong>Total des produits dans la table 'produits' :</strong> $total</p>";

$stmt = $pdo->query("SELECT statut, COUNT(*) as count FROM produits GROUP BY statut");
echo "<table>";
echo "<tr><th>Statut</th><th>Nombre</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr><td>{$row['statut']}</td><td>{$row['count']}</td></tr>";
}
echo "</table>";

// ============================================
// 2. Lister TOUS les produits avec leurs IDs
// ============================================
echo "<h2>📋 2. Liste complète des produits</h2>";

$stmt = $pdo->query("SELECT id_produit, reference, nom, statut, date_creation FROM produits ORDER BY id_produit");
echo "<table>";
echo "<tr><th>ID</th><th>Référence</th><th>Nom</th><th>Statut</th><th>Date création</th></tr>";
while ($row = $stmt->fetch()) {
    echo "<tr>
            <td>{$row['id_produit']}</td>
            <td><code>{$row['reference']}</code></td>
            <td><strong>{$row['nom']}</strong></td>
            <td>{$row['statut']}</td>
            <td>{$row['date_creation']}</td>
          </tr>";
}
echo "</table>";

// ============================================
// 3. Vérifier la table images_produits
// ============================================
echo "<h2>🖼️ 3. Images des produits</h2>";

$stmt = $pdo->query("SELECT id_produit, COUNT(*) as nb_images, MAX(principale) as a_principale FROM images_produits GROUP BY id_produit");
if ($stmt->rowCount() > 0) {
    echo "<table>";
    echo "<tr><th>ID Produit</th><th>Nb images</th><th>A une image principale</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr>
                <td>{$row['id_produit']}</td>
                <td>{$row['nb_images']}</td>
                <td>" . ($row['a_principale'] ? "✅ Oui" : "❌ Non") . "</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<div class='warning'>⚠️ Aucune image trouvée dans la table images_produits</div>";
}

// ============================================
// 4. Vérifier les doublons potentiels
// ============================================
echo "<h2>🔄 4. Vérification des doublons</h2>";

$stmt = $pdo->query("SELECT reference, COUNT(*) as count FROM produits GROUP BY reference HAVING COUNT(*) > 1");
if ($stmt->rowCount() > 0) {
    echo "<div class='error'>❌ Références en double :</div>";
    echo "<table>";
    echo "<tr><th>Référence</th><th>Nombre</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['reference']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='success'>✅ Aucune référence en double</div>";
}

$stmt = $pdo->query("SELECT slug, COUNT(*) as count FROM produits GROUP BY slug HAVING COUNT(*) > 1");
if ($stmt->rowCount() > 0) {
    echo "<div class='error'>❌ Slugs en double :</div>";
    echo "<table>";
    echo "<tr><th>Slug</th><th>Nombre</th></tr>";
    while ($row = $stmt->fetch()) {
        echo "<tr><td>{$row['slug']}</td><td>{$row['count']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<div class='success'>✅ Aucun slug en double</div>";
}

// ============================================
// 5. Tester la fonction getAllProducts()
// ============================================
echo "<h2>🧪 5. Test de la fonction getAllProducts()</h2>";

function testGetAllProducts($pdo) {
    $sql = "SELECT DISTINCT p.*, c.nom as categorie_nom, ip.url_image as image_url
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            LEFT JOIN images_produits ip ON p.id_produit = ip.id_produit AND ip.principale = 1
            ORDER BY p.id_produit DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results;
}

$testProducts = testGetAllProducts($pdo);
echo "<p><strong>Nombre de produits retournés :</strong> " . count($testProducts) . "</p>";

if (count($testProducts) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Référence</th><th>Nom</th><th>Catégorie</th><th>Image URL</th></tr>";
    foreach ($testProducts as $p) {
        echo "<tr>
                <td>{$p['id_produit']}</td>
                <td><code>{$p['reference']}</code></td>
                <td><strong>{$p['nom']}</strong></td>
                <td>{$p['categorie_nom']}</td>
                <td>" . (empty($p['image_url']) ? '❌ Aucune' : '✅ ' . substr($p['image_url'], 0, 50) . '...') . "</td>
              </tr>";
    }
    echo "</table>";
}

// ============================================
// 6. Vérifier les IDs des produits qui s'affichent
// ============================================
echo "<h2>🔢 6. IDs des produits (pour vérifier l'affichage)</h2>";

$stmt = $pdo->query("SELECT id_produit, nom FROM produits WHERE statut = 'actif' ORDER BY id_produit");
$ids = [];
while ($row = $stmt->fetch()) {
    $ids[] = $row['id_produit'] . " (" . $row['nom'] . ")";
}
echo "<p><strong>IDs des produits actifs :</strong> " . implode(", ", $ids) . "</p>";

// ============================================
// 7. Vérification des requêtes AJAX (si nécessaire)
// ============================================
echo "<h2>🌐 7. Test de l'API Panier (si accessible)</h2>";

$apiUrl = "panier.php?action=compter";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "<div class='success'>✅ API Panier accessible (HTTP $httpCode)</div>";
    $data = json_decode($response, true);
    echo "<pre>" . print_r($data, true) . "</pre>";
} else {
    echo "<div class='warning'>⚠️ API Panier non accessible (HTTP $httpCode)</div>";
}

// ============================================
// 8. Solution proposée
// ============================================
echo "<h2>🔧 8. Actions à effectuer</h2>";

echo "<div class='warning'>";
echo "<p><strong>Si vous voyez des produits en double ou des produits manquants, exécutez ces requêtes SQL :</strong></p>";
echo "<pre style='background: #333; color: #0f0; padding: 15px; overflow-x: auto;'>
-- 1. Vérifier et supprimer les doublons éventuels
-- (Exécutez d'abord la requête SELECT pour vérifier)
SELECT id_produit, reference, nom FROM produits ORDER BY id_produit;

-- 2. Si des produits sont cachés (statut inactif)
UPDATE produits SET statut = 'actif' WHERE statut != 'actif';

-- 3. Nettoyer le cache du navigateur et vider les cookies
-- 4. Rafraîchir la page avec Ctrl+F5 (vidange cache)
</pre>";
echo "</div>";

echo "<h2>📝 Conclusion</h2>";
echo "<p>Copiez ce que vous voyez dans ce diagnostic et envoyez-le moi. Je pourrai identifier précisément le problème.</p>";

echo "</body></html>";
?>