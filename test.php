<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test de configuration</h1>";

// Test 1: Version PHP
echo "<p>Version PHP: " . phpversion() . "</p>";

// Test 2: Extensions chargées
$extensions = ['pdo', 'pdo_mysql', 'session', 'json', 'curl'];
foreach ($extensions as $ext) {
    echo "<p>Extension $ext: " . (extension_loaded($ext) ? '✅ OK' : '❌ MANQUANTE') . "</p>";
}

// Test 3: Session
session_start();
$_SESSION['test'] = time();
echo "<p>Session: " . (isset($_SESSION['test']) ? '✅ OK' : '❌ ÉCHEC') . "</p>";

// Test 4: Connexion BDD
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
        "Philippe",
        "l@99339R"
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p>Connexion BDD: ✅ OK</p>";
    
    // Test 5: Requête simple
    $stmt = $pdo->query("SELECT COUNT(*) FROM produits");
    $count = $stmt->fetchColumn();
    echo "<p>Nombre de produits: $count</p>";
    
} catch (Exception $e) {
    echo "<p>Connexion BDD: ❌ ÉCHEC - " . $e->getMessage() . "</p>";
}

// Test 6: Fichiers requis
$fichiers = [
    'session_verification.php',
    'livraison.php'
];

foreach ($fichiers as $fichier) {
    if (file_exists(__DIR__ . '/' . $fichier)) {
        echo "<p>Fichier $fichier: ✅ Présent</p>";
    } else {
        echo "<p>Fichier $fichier: ❌ ABSENT</p>";
    }
}

echo "<h2>Test terminé</h2>";