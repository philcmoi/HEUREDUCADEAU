<?php
// config/database.php
function getPDOConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=heureducadeau;charset=utf8mb4",
            "Philippe",
            "l@99339R"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erreur de connexion DB: " . $e->getMessage());
        return null;
    }
}
?>