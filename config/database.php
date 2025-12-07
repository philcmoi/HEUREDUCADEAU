<?php
// config/database.php

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        // Configuration pour XAMPP/WAMP/MAMP (selon votre installation)
        $host = 'localhost';
        $dbname = 'heureducadeau';
        
        // Utilisateurs par défaut selon l'environnement :
        // XAMPP : 'root' avec mot de passe vide
        // WAMP : 'root' avec mot de passe vide
        // MAMP : 'root' avec 'root'
        // Production : créer un utilisateur spécifique
        
        $username = 'root';  // Utilisateur par défaut
        $password = '';      // Mot de passe par défaut (vide pour XAMPP/WAMP)
        
        // Essayez d'abord avec le mot de passe vide, sinon essayez 'root'
        if (empty($password)) {
            try {
                $this->connection = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                // Si échec avec mot de passe vide, essayez avec 'root'
                $password = 'root';
            }
        }
        
        // Deuxième tentative avec le mot de passe 'root' (pour MAMP)
        try {
            $this->connection = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            // Afficher un message d'erreur plus clair
            die("<h2>Erreur de connexion à la base de données</h2>
                 <p><strong>Message :</strong> " . $e->getMessage() . "</p>
                 <p><strong>Solution :</strong></p>
                 <ol>
                     <li>Vérifiez que MySQL est démarré</li>
                     <li>Créez la base de données 'cadeaux_elegance'</li>
                     <li>Utilisez phpMyAdmin pour vérifier vos identifiants</li>
                     <li>Essayez avec username: 'root', password: '' (vide) ou 'root'</li>
                 </ol>
                 <p><a href='../setup.php'>Cliquez ici pour exécuter le script d'installation</a></p>");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance->connection;
    }
}

// Fonction utilitaire pour tester la connexion
function testDatabaseConnection() {
    try {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT 1 as test");
        $result = $stmt->fetch();
        return ['success' => true, 'message' => 'Connexion OK'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fonction pour créer la base de données si elle n'existe pas
function createDatabaseIfNotExists() {
    try {
        $host = 'localhost';
        $username = 'root';
        $password = '';
        
        // Connexion sans base de données spécifiée
        $tempConnection = new PDO(
            "mysql:host=$host;charset=utf8mb4",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        // Créer la base de données si elle n'existe pas
        $tempConnection->exec("CREATE DATABASE IF NOT EXISTS heureducadeau CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return ['success' => true, 'message' => 'Base de données créée ou déjà existante'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>