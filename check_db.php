<?php
// check_db.php - Script de diagnostic BDD
// À placer à la racine et à exécuter une fois

echo "<h1>Diagnostic Base de Données</h1>";

// Tester les différentes combinaisons d'identifiants
$tests = [
    ['user' => 'Philippe', 'pass' => 'l@99339R', 'name' => 'Compte Philippe'],
    ['user' => 'root', 'pass' => '', 'name' => 'Root sans mot de passe'],
    ['user' => 'root', 'pass' => 'root', 'name' => 'Root avec root'],
    ['user' => 'root', 'pass' => 'password', 'name' => 'Root avec password'],
    ['user' => 'admin', 'pass' => '', 'name' => 'Admin sans mot de passe'],
    ['user' => 'admin', 'pass' => 'admin', 'name' => 'Admin avec admin'],
];

foreach ($tests as $test) {
    echo "<h3>Test: " . $test['name'] . "</h3>";
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4", $test['user'], $test['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "<span style='color:green'>✓ CONNEXION RÉUSSIE !</span><br>";
        
        // Tester la présence des tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll();
        echo "Tables trouvées : " . count($tables) . "<br>";
        
        echo "<strong>Utilisez ces identifiants :</strong><br>";
        echo "User: <code>" . $test['user'] . "</code><br>";
        echo "Password: <code>" . ($test['pass'] ?: '(vide)') . "</code><br>";
        
        break;
    } catch (PDOException $e) {
        echo "<span style='color:red'>✗ ÉCHEC: " . $e->getMessage() . "</span><br>";
    }
    echo "<br>";
}

// Afficher la solution recommandée
echo "<hr>";
echo "<h2>Solution recommandée</h2>";
echo "<p>Ajoutez ces lignes à votre fichier <code>config/database.php</code> :</p>";
echo "<pre>
// Configuration BDD
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'root');  // À modifier selon le test réussi
define('DB_PASS', '');      // À modifier selon le test réussi
</pre>";