<?php
// test-panier-direct.php - Test direct de l'API panier
session_start();

echo "<h1>Test Direct Panier API</h1>";
echo "<pre>";

// 1. Info session
echo "=== SESSION INFO ===\n";
echo "Session ID: " . session_id() . "\n";
echo "Cookie PHPSESSID: " . ($_COOKIE['PHPSESSID'] ?? 'NON DÉFINI') . "\n";

// 2. Initialiser/voir panier
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [];
    echo "Panier initialisé (vide)\n";
} else {
    echo "Panier existant: " . count($_SESSION['panier']) . " article(s)\n";
    print_r($_SESSION['panier']);
}

// 3. Ajouter un produit test
echo "\n=== AJOUT PRODUIT TEST ===\n";
$_SESSION['panier'][] = [
    'id_produit' => 999,
    'nom' => 'PRODUIT TEST',
    'prix' => 19.99,
    'quantite' => 2,
    'date_ajout' => date('Y-m-d H:i:s')
];

echo "Produit ajouté !\n";
echo "Nouveau panier: " . count($_SESSION['panier']) . " article(s)\n";
print_r($_SESSION['panier']);

// 4. Calculer total
$total = 0;
foreach ($_SESSION['panier'] as $item) {
    if (isset($item['quantite'])) {
        $total += $item['quantite'];
    }
}
echo "\nTotal articles: $total\n";

echo "</pre>";

// 5. Lien pour tester dans panier.html
echo "<h2>Test avec panier.html</h2>";
echo "<p>Session ID: <code>" . session_id() . "</code></p>";
echo "<p>Ouvrez la console (F12) et tapez :</p>";
echo "<pre>fetch('panier.php?action=get&session_id=" . session_id() . "')
  .then(r => r.json())
  .then(console.log)</pre>";
?>