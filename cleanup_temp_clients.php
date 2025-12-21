<?php
// cleanup_temp_clients.php - Script de nettoyage des clients temporaires
// À exécuter quotidiennement via cron : 0 2 * * * php /chemin/vers/cleanup_temp_clients.php

// Configuration
$config = [
    'db_host' => 'localhost',
    'db_name' => 'heureducadeau',
    'db_user' => 'Philippe',
    'db_pass' => 'l@99339R',
    'days_temp_no_order' => 30,    // Supprimer clients temporaires sans commande après X jours
    'days_temp_with_order' => 90,  // Supprimer clients temporaires avec commandes après X jours
    'log_file' => '/var/log/cleanup_temp_clients.log'
];

// Connexion BDD
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    logMessage("ERREUR: Connexion BDD impossible - " . $e->getMessage());
    exit(1);
}

// Fonction de log
function logMessage($message) {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    
    // Log dans fichier
    if (is_writable(dirname($config['log_file']))) {
        file_put_contents($config['log_file'], $logEntry, FILE_APPEND);
    }
    
    // Output console
    echo $logEntry;
}

logMessage("=== Début du nettoyage des clients temporaires ===");

try {
    $pdo->beginTransaction();
    
    $stats = [
        'temp_no_order_deleted' => 0,
        'temp_with_order_deleted' => 0,
        'addresses_deleted' => 0,
        'total_deleted' => 0
    ];
    
    // 1. Clients temporaires SANS commande depuis X jours
    logMessage("Suppression des clients temporaires sans commande...");
    
    $sql = "DELETE c FROM clients c
            LEFT JOIN commandes cmd ON c.id_client = cmd.id_client
            WHERE c.is_temporary = 1
            AND c.date_inscription < DATE_SUB(NOW(), INTERVAL :days DAY)
            AND cmd.id_commande IS NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':days' => $config['days_temp_no_order']]);
    $stats['temp_no_order_deleted'] = $stmt->rowCount();
    
    logMessage("Clients temporaires sans commande supprimés: " . $stats['temp_no_order_deleted']);
    
    // 2. Clients temporaires AVEC commandes terminées depuis X jours
    logMessage("Suppression des clients temporaires avec commandes terminées...");
    
    $sql2 = "DELETE c FROM clients c
             WHERE c.is_temporary = 1
             AND c.id_client IN (
                 SELECT DISTINCT cmd.id_client
                 FROM commandes cmd
                 WHERE cmd.statut IN ('livree', 'annulee', 'remboursee')
                 AND cmd.date_commande < DATE_SUB(NOW(), INTERVAL :days DAY)
             )";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute([':days' => $config['days_temp_with_order']]);
    $stats['temp_with_order_deleted'] = $stmt2->rowCount();
    
    logMessage("Clients temporaires avec commandes supprimés: " . $stats['temp_with_order_deleted']);
    
    // 3. Supprimer les adresses orphelines
    logMessage("Nettoyage des adresses orphelines...");
    
    $sql3 = "DELETE a FROM adresses a
             LEFT JOIN clients c ON a.id_client = c.id_client
             WHERE c.id_client IS NULL";
    
    $stmt3 = $pdo->prepare($sql3);
    $stmt3->execute();
    $stats['addresses_deleted'] = $stmt3->rowCount();
    
    logMessage("Adresses orphelines supprimées: " . $stats['addresses_deleted']);
    
    // 4. Statistiques globales
    $stats['total_deleted'] = $stats['temp_no_order_deleted'] + $stats['temp_with_order_deleted'];
    
    // 5. Enregistrer les statistiques dans la table logs
    $sqlLog = "INSERT INTO logs (type_log, niveau, message, metadata, date_log)
              VALUES ('info', 'info', 'Nettoyage clients temporaires', ?, NOW())";
    
    $stmtLog = $pdo->prepare($sqlLog);
    $stmtLog->execute([json_encode($stats)]);
    
    $pdo->commit();
    
    logMessage("=== Nettoyage terminé avec succès ===");
    logMessage("Résumé:");
    logMessage("  - Clients temporaires sans commande: " . $stats['temp_no_order_deleted']);
    logMessage("  - Clients temporaires avec commandes: " . $stats['temp_with_order_deleted']);
    logMessage("  - Adresses orphelines: " . $stats['addresses_deleted']);
    logMessage("  - Total clients supprimés: " . $stats['total_deleted']);
    
    exit(0);
    
} catch (Exception $e) {
    $pdo->rollBack();
    logMessage("ERREUR pendant le nettoyage: " . $e->getMessage());
    exit(1);
}
?>