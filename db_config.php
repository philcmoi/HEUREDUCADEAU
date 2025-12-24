<?php
// db_config.php - Configuration minimale de la base de données

// db_config.php
if (!defined('API_CALL') && !defined('SOME_OTHER_CONSTANT') && !defined('INCLUDED')) {
    die('Accès direct interdit');
}

// Votre configuration de base de données...
// Configuration de base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Version de l'application
define('APP_VERSION', '1.0.0');

// Mode de débogage
define('DEBUG_MODE', true);

// Fonction de connexion à la base de données
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $db = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
        } catch (PDOException $e) {
            error_log("Erreur connexion DB: " . $e->getMessage());
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die("Erreur de connexion à la base de données: " . $e->getMessage());
            } else {
                die("Erreur de connexion à la base de données");
            }
        }
    }
    
    return $db;
}
?>