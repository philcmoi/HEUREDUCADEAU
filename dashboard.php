<?php
// dashboard.php - Adapté à heureducadeau
require_once 'admin_protection.php';

// ============================================
// RÉCUPÉRATION DES STATISTIQUES
// ============================================
try {
    // Nombre total de produits
    $sql = "SELECT COUNT(*) as total_produits FROM produits";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_produits = $stmt->fetch(PDO::FETCH_ASSOC)['total_produits'];
    
    // Nombre total de commandes
    $sql = "SELECT COUNT(*) as total_commandes FROM commandes";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_commandes = $stmt->fetch(PDO::FETCH_ASSOC)['total_commandes'];
    
    // Nombre total de clients
    $sql = "SELECT COUNT(*) as total_clients FROM clients";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $total_clients = $stmt->fetch(PDO::FETCH_ASSOC)['total_clients'];
    
    // Produits en alerte de stock
    $sql = "SELECT COUNT(*) as alert_stock FROM produits WHERE quantite_stock <= seuil_alerte AND statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $alert_stock = $stmt->fetch(PDO::FETCH_ASSOC)['alert_stock'];
    
    // Commandes en attente
    $sql = "SELECT COUNT(*) as commandes_attente FROM commandes WHERE statut = 'en_attente'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $commandes_attente = $stmt->fetch(PDO::FETCH_ASSOC)['commandes_attente'];
    
    // Chiffre d'affaires total
    $sql = "SELECT SUM(total_ttc) as chiffre_affaires FROM commandes WHERE statut_paiement = 'paye'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $chiffre_affaires = $stmt->fetch(PDO::FETCH_ASSOC)['chiffre_affaires'] ?? 0;
    
} catch(PDOException $e) {
    $error_stats = "Erreur lors du chargement des statistiques: " . $e->getMessage();
}

// Récupérer le nom de l'admin depuis la session
$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .user-info {
            text-align: right;
        }
        
        .role-badge {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-top: 10px;
        }
        
        .superadmin-badge {
            background-color: #f44336;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 5px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }
        
        .stat-card.products { border-left-color: #2196F3; }
        .stat-card.orders { border-left-color: #4CAF50; }
        .stat-card.clients { border-left-color: #FF9800; }
        .stat-card.revenue { border-left-color: #9C27B0; }
        .stat-card.alerts { border-left-color: #F44336; }
        .stat-card.pending { border-left-color: #FFC107; }
        
        .stat-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .stat-card.products .stat-icon { background-color: #2196F3; }
        .stat-card.orders .stat-icon { background-color: #4CAF50; }
        .stat-card.clients .stat-icon { background-color: #FF9800; }
        .stat-card.revenue .stat-icon { background-color: #9C27B0; }
        .stat-card.alerts .stat-icon { background-color: #F44336; }
        .stat-card.pending .stat-icon { background-color: #FFC107; }
        
        .stat-title {
            font-size: 16px;
            color: #666;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stat-change {
            font-size: 14px;
            color: #666;
        }
        
        .stat-change.positive { color: #4CAF50; }
        .stat-change.negative { color: #F44336; }
        
        /* Quick Actions */
        .quick-actions {
            margin-bottom: 40px;
        }
        
        .quick-actions h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #333;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            text-decoration: none;
            color: #333;
            border: 2px solid transparent;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: #2196F3;
        }
        
        .action-icon {
            font-size: 36px;
            margin-bottom: 15px;
            color: #2196F3;
        }
        
        .action-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .action-desc {
            font-size: 14px;
            color: #666;
        }
        
        /* Recent Activity */
        .recent-activity {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 40px;
        }
        
        .recent-activity h2 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #f0f8ff;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2196F3;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .activity-time {
            font-size: 14px;
            color: #666;
        }
        
        /* System Info */
        .system-info {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        
        .system-info h2 {
            font-size: 24px;
            margin-bottom: 25px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .info-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .user-info {
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Session Info */
        .session-info {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
            border-left: 4px solid #2196F3;
        }
        
        .session-info h3 {
            color: #2196F3;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            font-size: 14px;
        }
        
        .session-item {
            background-color: white;
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }
        
        .session-label {
            color: #666;
            font-weight: 500;
        }
        
        .session-value {
            color: #333;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div>
                    <h1><i class="fas fa-gift"></i> Tableau de bord - Heure du Cadeau</h1>
                    <p>Gestion complète de votre boutique en ligne</p>
                </div>
                <div class="user-info">
                    <p>Bienvenue, <strong><?php echo htmlspecialchars($admin_username); ?></strong></p>
                    <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                        <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($error_stats)): ?>
            <div style="background-color: #ffebee; color: #c62828; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_stats); ?>
            </div>
        <?php endif; ?>
        
        <!-- Statistiques principales -->
        <div class="stats-grid">
            <div class="stat-card products">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div>
                        <div class="stat-title">Produits</div>
                        <div class="stat-value"><?php echo number_format($total_produits ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-line"></i> Total des produits en boutique
                </div>
            </div>
            
            <div class="stat-card orders">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div>
                        <div class="stat-title">Commandes</div>
                        <div class="stat-value"><?php echo number_format($total_commandes ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-bar"></i> Commandes totales
                </div>
            </div>
            
            <div class="stat-card clients">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div class="stat-title">Clients</div>
                        <div class="stat-value"><?php echo number_format($total_clients ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-user-plus"></i> Clients inscrits
                </div>
            </div>
            
            <div class="stat-card revenue">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div>
                        <div class="stat-title">Chiffre d'affaires</div>
                        <div class="stat-value"><?php echo number_format($chiffre_affaires ?? 0, 2, ',', ' '); ?> €</div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-chart-pie"></i> CA total
                </div>
            </div>
            
            <div class="stat-card alerts">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div>
                        <div class="stat-title">Alertes stock</div>
                        <div class="stat-value"><?php echo number_format($alert_stock ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change negative">
                    <i class="fas fa-bell"></i> Produits à réapprovisionner
                </div>
            </div>
            
            <div class="stat-card pending">
                <div class="stat-header">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div>
                        <div class="stat-title">Commandes en attente</div>
                        <div class="stat-value"><?php echo number_format($commandes_attente ?? 0); ?></div>
                    </div>
                </div>
                <div class="stat-change">
                    <i class="fas fa-hourglass-half"></i> À traiter
                </div>
            </div>
        </div>
        
        <!-- Actions rapides -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Actions rapides</h2>
            <div class="actions-grid">
                <a href="admin_produits.php?action=add" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-title">Ajouter un produit</div>
                    <div class="action-desc">Créez un nouveau produit dans votre catalogue</div>
                </a>
                
                <a href="admin_produits.php?action=list" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="action-title">Gérer les produits</div>
                    <div class="action-desc">Modifiez, supprimez vos produits</div>
                </a>
                
                <a href="admin_commandes.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-title">Voir les commandes</div>
                    <div class="action-desc">Gérez les commandes clients</div>
                </a>
                
                <a href="admin_clients.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="action-title">Gérer les clients</div>
                    <div class="action-desc">Consultez la liste des clients</div>
                </a>
                
                <a href="admin_categories.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="action-title">Catégories</div>
                    <div class="action-desc">Organisez vos produits par catégories</div>
                </a>
                
                <a href="admin_promotions.php" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-percent"></i>
                    </div>
                    <div class="action-title">Promotions</div>
                    <div class="action-desc">Créez des codes promotionnels</div>
                </a>
            </div>
        </div>
        
        <!-- Informations système -->
        <div class="system-info">
            <h2><i class="fas fa-info-circle"></i> Informations système</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Version PHP</div>
                    <div class="info-value"><?php echo phpversion(); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Base de données</div>
                    <div class="info-value">heureducadeau</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Serveur</div>
                    <div class="info-value"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Dernière connexion</div>
                    <div class="info-value"><?php echo date('d/m/Y H:i:s'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Informations de session -->
        <div class="session-info">
            <h3><i class="fas fa-user-circle"></i> Informations de session</h3>
            <div class="session-details">
                <div class="session-item">
                    <div class="session-label">ID Admin</div>
                    <div class="session-value"><?php echo htmlspecialchars($_SESSION['admin_id'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="session-item">
                    <div class="session-label">Nom d'utilisateur</div>
                    <div class="session-value"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="session-item">
                    <div class="session-label">Rôle</div>
                    <div class="session-value"><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'Non défini'); ?></div>
                </div>
                
                <div class="session-item">
                    <div class="session-label">IP Client</div>
                    <div class="session-value"><?php echo getClientIp(); ?></div>
                </div>
                
                <div class="session-item">
                    <div class="session-label">Session ID</div>
                    <div class="session-value"><?php echo session_id(); ?></div>
                </div>
                
                <div class="session-item">
                    <div class="session-label">Dernière activité</div>
                    <div class="session-value"><?php echo isset($_SESSION['last_activity']) ? date('H:i:s', $_SESSION['last_activity']) : 'Maintenant'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Menu de navigation -->
        <div style="margin-top: 40px; padding: 25px; background-color: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);">
            <h2 style="margin-bottom: 20px; color: #333;"><i class="fas fa-cogs"></i> Administration complète</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="admin_produits.php" style="background-color: #2196F3; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-box"></i> Produits
                </a>
                
                <a href="admin_categories.php" style="background-color: #4CAF50; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-tags"></i> Catégories
                </a>
                
                <a href="admin_commandes.php" style="background-color: #FF9800; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-shopping-cart"></i> Commandes
                </a>
                
                <a href="admin_clients.php" style="background-color: #9C27B0; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-users"></i> Clients
                </a>
                
                <a href="admin_promotions.php" style="background-color: #F44336; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-percent"></i> Promotions
                </a>
                
                <a href="admin_pages.php" style="background-color: #607D8B; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-file-alt"></i> Pages
                </a>
                
                <a href="admin_configuration.php" style="background-color: #795548; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-cog"></i> Configuration
                </a>
                
                <a href="logout.php" style="background-color: #333; color: white; padding: 15px; border-radius: 8px; text-decoration: none; text-align: center; font-weight: 500; transition: background-color 0.3s;">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </div>
        
        <!-- Super Admin Features -->
        <?php if ($admin_role === 'superadmin'): ?>
        <div style="margin-top: 30px; padding: 25px; background: linear-gradient(135deg, #ff6b6b 0%, #ffa8a8 100%); border-radius: 12px; color: white;">
            <h2 style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-crown"></i> Fonctions Super Admin
            </h2>
            <p style="margin-bottom: 20px; opacity: 0.9;">En tant que Super Admin, vous avez accès à toutes les fonctionnalités :</p>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="background-color: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                    <i class="fas fa-user-shield"></i> <strong>Gestion administrateurs</strong>
                    <p style="font-size: 14px; margin-top: 5px;">Ajoutez/modifiez/supprimez des administrateurs</p>
                </div>
                <div style="background-color: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                    <i class="fas fa-database"></i> <strong>Sauvegarde BDD</strong>
                    <p style="font-size: 14px; margin-top: 5px;">Sauvegardez/restaurez la base de données</p>
                </div>
                <div style="background-color: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                    <i class="fas fa-chart-line"></i> <strong>Statistiques avancées</strong>
                    <p style="font-size: 14px; margin-top: 5px;">Analyses détaillées et rapports</p>
                </div>
                <div style="background-color: rgba(255, 255, 255, 0.1); padding: 15px; border-radius: 8px;">
                    <i class="fas fa-cogs"></i> <strong>Configuration système</strong>
                    <p style="font-size: 14px; margin-top: 5px;">Paramètres avancés du site</p>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <a href="admin_administrateurs.php" style="background-color: white; color: #ff6b6b; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-block;">
                    <i class="fas fa-users-cog"></i> Gérer les administrateurs
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Mettre à jour l'heure toutes les minutes
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            document.querySelectorAll('.time-display').forEach(el => {
                el.textContent = timeString;
            });
        }
        
        // Mettre à jour toutes les minutes
        setInterval(updateTime, 60000);
        
        // Initialiser l'heure
        updateTime();
        
        // Animation des cartes statistiques
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
                card.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>