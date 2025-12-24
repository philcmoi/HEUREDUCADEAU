<?php
// admin_temp_clients.php - Interface admin pour gérer les clients temporaires
require_once '../config/database.php';
require_once '../includes/auth_check.php';

// Vérifier les permissions admin
if (!hasPermission('admin')) {
    header('Location: ../index.php');
    exit;
}

$tempManager = new TempClientManager($db);

// Actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'cleanup':
            $cleaned = $tempManager->cleanupOldTempClients(30);
            $_SESSION['message'] = "Nettoyage effectué : $cleaned clients temporaires supprimés";
            break;
            
        case 'convert':
            if (isset($_GET['id'])) {
                $result = $tempManager->convertToPermanent($_GET['id'], [
                    'email' => 'admin_convert@temp.fr',
                    'password' => bin2hex(random_bytes(8)),
                    'nom' => 'Converti',
                    'prenom' => 'Admin'
                ]);
                if ($result['success']) {
                    $_SESSION['message'] = "Client converti avec succès";
                }
            }
            break;
    }
    header('Location: admin_temp_clients.php');
    exit;
}

// Récupérer les statistiques
$stats = getTempClientsStats();
$recentTempClients = getRecentTempClients();
$conversionStats = getConversionStats();

function getTempClientsStats() {
    global $db;
    
    $sql = "SELECT 
            COUNT(*) as total_temp,
            SUM(CASE WHEN DATE(date_inscription) = CURDATE() THEN 1 ELSE 0 END) as today_temp,
            AVG(DATEDIFF(NOW(), date_inscription)) as avg_age_days
            FROM clients 
            WHERE is_temporary = 1";
    
    return $db->query($sql)->fetch(PDO::FETCH_ASSOC);
}

function getRecentTempClients() {
    global $db;
    
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM commandes WHERE id_client = c.id_client) as order_count,
            (SELECT MAX(date_commande) FROM commandes WHERE id_client = c.id_client) as last_order
            FROM clients c
            WHERE c.is_temporary = 1
            ORDER BY c.date_inscription DESC
            LIMIT 50";
    
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function getConversionStats() {
    global $db;
    
    $sql = "SELECT 
            DATE(date_conversion) as conversion_date,
            methode_conversion,
            COUNT(*) as conversions,
            COUNT(DISTINCT id_client_permanent) as clients_convertis
            FROM conversions_temp
            WHERE date_conversion >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(date_conversion), methode_conversion
            ORDER BY conversion_date DESC";
    
    return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion Clients Temporaires - Admin</title>
    <style>
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 30px 0; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-value { font-size: 2em; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; }
        .btn { padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-block; }
        .btn-primary { background: #007bff; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .message { padding: 15px; margin: 20px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <h1>Gestion des Clients Temporaires</h1>
    
    <?php if (isset($_SESSION['message'])): ?>
        <div class="message success"><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total_temp'] ?></div>
            <div class="stat-label">Clients temporaires</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $stats['today_temp'] ?></div>
            <div class="stat-label">Aujourd'hui</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= round($stats['avg_age_days'], 1) ?>j</div>
            <div class="stat-label">Âge moyen</div>
        </div>
        <div class="stat-card">
            <a href="?action=cleanup" class="btn btn-danger" onclick="return confirm('Nettoyer les clients de plus de 30 jours?')">
                Nettoyer anciens
            </a>
        </div>
    </div>
    
    <h2>Clients Temporaires Récents</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Email</th>
                <th>Nom</th>
                <th>Date création</th>
                <th>Commandes</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentTempClients as $client): ?>
            <tr>
                <td><?= $client['id_client'] ?></td>
                <td><?= htmlspecialchars($client['email']) ?></td>
                <td><?= htmlspecialchars($client['nom']) ?> <?= htmlspecialchars($client['prenom']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($client['date_inscription'])) ?></td>
                <td><?= $client['order_count'] ?></td>
                <td>
                    <a href="?action=convert&id=<?= $client['id_client'] ?>" class="btn btn-success" 
                       onclick="return confirm('Convertir ce client en permanent?')">
                        Convertir
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <h2>Statistiques de Conversion (30 jours)</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Méthode</th>
                <th>Conversions</th>
                <th>Clients convertis</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($conversionStats as $stat): ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($stat['conversion_date'])) ?></td>
                <td><?= $stat['methode_conversion'] ?></td>
                <td><?= $stat['conversions'] ?></td>
                <td><?= $stat['clients_convertis'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>