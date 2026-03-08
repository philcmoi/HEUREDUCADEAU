<?php
// error_log.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Test d'erreur</h1>";

// Tester la connexion DB
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
        'Philippe',
        'l@99339R'
    );
    echo "<p style='color:green'>✓ Connexion DB réussie</p>";
} catch(Exception $e) {
    echo "<p style='color:red'>✗ Erreur DB: " . $e->getMessage() . "</p>";
}

// Tester l'inclusion de fichiers
echo "<h2>Test d'inclusion:</h2>";
if (file_exists('admin_protection.php')) {
    echo "<p>✓ admin_protection.php existe</p>";
    try {
        require_once 'admin_protection.php';
        echo "<p>✓ admin_protection.php inclus</p>";
    } catch(Exception $e) {
        echo "<p>✗ Erreur inclusion: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>✗ admin_protection.php n'existe pas</p>";
}

// Afficher les variables de session
session_start();
echo "<h2>Session:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";