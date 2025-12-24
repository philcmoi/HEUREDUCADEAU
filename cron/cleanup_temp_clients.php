<?php
// cron/cleanup_temp_clients.php - Tâche cron quotidienne
require_once '../config/database.php';
require_once '../classes/TempClientManager.php';

// Vérifier si exécuté en ligne de commande
if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande");
}

$tempManager = new TempClientManager($db);

// Nettoyer les clients temporaires de plus de 30 jours
$cleaned = $tempManager->cleanupOldTempClients(30);

// Nettoyer les paniers abandonnés
$abandonedCarts = $this->cleanupAbandonedCarts();

// Log
$log = date('Y-m-d H:i:s') . " - Nettoyage : $cleaned clients temporaires, $abandonedCarts paniers\n";
file_put_contents('../logs/cleanup.log', $log, FILE_APPEND);

echo $log;

// Fonction pour nettoyer les paniers abandonnés
private function cleanupAbandonedCarts($days = 7) {
    $sql = "DELETE FROM panier 
            WHERE statut = 'abandonne' 
            AND date_modification < DATE_SUB(NOW(), INTERVAL :days DAY)";
    
    $stmt = $this->db->prepare($sql);
    $stmt->execute(['days' => $days]);
    
    return $stmt->rowCount();
}
?>