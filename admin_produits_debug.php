<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Logger toutes les erreurs dans un fichier
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

echo "Début du script...<br>";

require_once 'admin_protection.php';
echo "admin_protection.php inclus<br>";

// Test PDO disponible
if (class_exists('PDO')) {
    echo "PDO est disponible<br>";
} else {
    die("PDO n'est pas disponible");
}

// Test connexion
try {
    $host = 'localhost';
    $dbname = 'heureducadeau';
    $username_db = 'Philippe';
    $password_db = 'l@99339R';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion DB réussie<br>";
    
    // Test requête simple
    $test = $pdo->query("SELECT 1");
    echo "Requête test réussie<br>";
    
    // Vérifier les tables nécessaires
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables disponibles : " . implode(', ', $tables) . "<br>";
    
    if (!in_array('images_produits', $tables)) {
        echo "<span style='color:orange'>⚠️ Table 'images_produits' manquante</span><br>";
    }
    
} catch(Exception $e) {
    echo "<span style='color:red'>ERREUR DB: " . $e->getMessage() . "</span><br>";
}

echo "Fin du script - tout semble OK !";