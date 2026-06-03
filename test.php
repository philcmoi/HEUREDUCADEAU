<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de diagnostic</h1>";

require_once 'session_verification.php';

echo "1. Session démarrée: " . session_id() . "<br>";

$pdo = getPDOConnection();

if (!$pdo) {
    die("ERREUR: Connexion BDD échouée");
}

echo "2. Connexion BDD OK<br>";

// Test simple
$stmt = $pdo->query("SELECT id_produit, nom FROM produits WHERE id_produit IN (31, 42)");
$resultats = $stmt->fetchAll();

echo "3. Nombre de produits trouvés: " . count($resultats) . "<br>";

foreach ($resultats as $row) {
    echo " - ID: {$row['id_produit']}, Nom: {$row['nom']}<br>";
}

echo "<br>Le fichier catalogue.php devrait fonctionner correctement.";
?>