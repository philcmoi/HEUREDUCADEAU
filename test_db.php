<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_config.php';

echo "<h2>Test connexion BDD</h2>";

$db = getDB();

if ($db) {
    echo "✓ Connexion réussie<br>";
    
    // Tester les tables
    $tables = ['clients', 'adresses', 'produits'];
    
    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table");
            $data = $result->fetch();
            echo "✓ Table $table : " . $data['count'] . " enregistrements<br>";
        } catch (Exception $e) {
            echo "✗ Table $table : Erreur - " . $e->getMessage() . "<br>";
        }
    }
    
    // Tester l'insertion dans adresses
    try {
        $stmt = $db->prepare("
            INSERT INTO adresses (id_client, type_adresse, nom, prenom, adresse, 
                                 code_postal, ville, pays, telephone, principale)
            VALUES (1, 'livraison', 'TEST', 'TEST', 'Test adresse', 
                    '75000', 'Paris', 'France', '0123456789', 1)
        ");
        
        if ($stmt->execute()) {
            echo "✓ Insertion test dans adresses réussie<br>";
            $id = $db->lastInsertId();
            echo "✓ Dernier ID inséré : $id<br>";
            
            // Nettoyer
            $db->query("DELETE FROM adresses WHERE id_adresse = $id");
        }
        
    } catch (Exception $e) {
        echo "✗ Erreur insertion test : " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "✗ Échec de la connexion BDD<br>";
}

echo "<h2>Structure table adresses</h2>";
try {
    $result = $db->query("DESCRIBE adresses");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
}
?>