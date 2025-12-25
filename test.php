<?php
echo "<h1>Test de connexion MySQL</h1>";

// Configuration
$host = 'localhost';
$dbname = 'heureducadeau';
$user = 'Philippe';
$pass = 'l@99339R';

// Affichage des erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test PDO
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color:green;font-weight:bold;'>✅ CONNEXION RÉUSSIE !</p>";
    echo "<p>Base de données: <strong>$dbname</strong></p>";
    echo "<p>Hôte: <strong>$host</strong></p>";
    echo "<p>Utilisateur: <strong>$user</strong></p>";
    
    // Test supplémentaire
    $stmt = $pdo->query("SELECT DATABASE() as db, USER() as user");
    $result = $stmt->fetch();
    echo "<p>Base connectée: " . $result['db'] . "</p>";
    echo "<p>Utilisateur MySQL: " . $result['user'] . "</p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ ERREUR PDO: " . $e->getMessage() . "</p>";
    
    // Test sans base
    try {
        $pdo2 = new PDO("mysql:host=$host", $user, $pass);
        echo "<p style='color:orange;'>⚠️ Connexion au serveur MySQL OK, mais problème avec la base</p>";
        
        // Vérifier si la base existe
        $stmt = $pdo2->query("SHOW DATABASES LIKE '$dbname'");
        if ($stmt->rowCount() > 0) {
            echo "<p>La base '$dbname' existe</p>";
            echo "<p>Problème probable: droits insuffisants pour l'utilisateur '$user'</p>";
        } else {
            echo "<p>La base '$dbname' n'existe pas</p>";
        }
    } catch (PDOException $e2) {
        echo "<p>❌ Impossible de se connecter au serveur MySQL: " . $e2->getMessage() . "</p>";
    }
}

// Informations serveur
echo "<hr>";
echo "<h2>Informations serveur</h2>";
echo "<p>Répertoire racine: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p>Script exécuté: " . __FILE__ . "</p>";
echo "<p>PHP version: " . phpversion() . "</p>";

// Vérifier les extensions
echo "<p>Extensions MySQL chargées:</p>";
echo "<ul>";
foreach (get_loaded_extensions() as $ext) {
    if (strpos($ext, 'mysql') !== false || $ext === 'pdo_mysql') {
        echo "<li>$ext</li>";
    }
}
echo "</ul>";
?>
