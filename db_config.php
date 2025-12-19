<?php
// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe'); // À ajuster selon votre configuration
define('DB_PASS', 'l@99339R'); // À ajuster selon votre configuration

// Connexion à la base de données
function getDB() {
    static $db = null;
    
    if ($db === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $db = new PDO($dsn, DB_USER, DB_PASS);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            error_log('Erreur de connexion à la base de données: ' . $e->getMessage());
            die('Erreur de connexion à la base de données');
        }
    }
    
    return $db;
}
?>