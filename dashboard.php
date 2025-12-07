<?php
// dashboard.php - Simple tableau de bord
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login_simple.php');
    exit;
}

// Récupérer les informations de l'admin
$admin_username = $_SESSION['admin_username'] ?? 'Admin';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$is_superadmin = ($admin_role === 'superadmin');

// Fonction pour obtenir l'IP du client
function getClientIp() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED'];
    
    foreach ($headers as $header) {
        if (isset($_SERVER[$header]) && filter_var($_SERVER[$header], FILTER_VALIDATE_IP)) {
            $ip = $_SERVER[$header];
            break;
        }
    }
    
    return $ip;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord - Administration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        /* Header */
        .dashboard-header {
            background: linear-gradient(to right, #8a4baf, #ff6b8b);
            color: white;
            padding: 30px 40px;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        
        .welcome-section {
            position: relative;
            z-index: 1;
        }
        
        .welcome-title {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .welcome-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 20px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .admin-avatar {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #8a4baf;
            font-weight: bold;
        }
        
        .admin-details {
            flex: 1;
        }
        
        .admin-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .admin-role {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        /* Main content */
        .dashboard-main {
            padding: 40px;
        }
        
        .section-title {
            color: #333;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #8a4baf;
        }
        
        /* Cards grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 1px solid #eaeaea;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            border-color: #8a4baf;
        }
        
        .card-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #8a4baf, #ff6b8b);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            color: white;
        }
        
        .card-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .card-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .card-stats {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #8a4baf;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #888;
        }
        
        /* Quick actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }
        
        .action-btn {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .action-btn:hover {
            background: white;
            border-color: #8a4baf;
            transform: translateX(5px);
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #8a4baf, #ff6b8b);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }
        
        .action-text {
            flex: 1;
        }
        
        .action-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* System info */
        .system-info {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-top: 40px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-icon {
            width: 45px;
            height: 45px;
            background: #e9ecef;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8a4baf;
            font-size: 1.2rem;
        }
        
        .info-content {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 600;
            color: #333;
        }
        
        /* Footer */
        .dashboard-footer {
            background: #f8f9fa;
            padding: 25px 40px;
            text-align: center;
            border-top: 1px solid #eaeaea;
        }
        
        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 30px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .footer-text {
            margin-top: 20px;
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .dashboard-header {
                padding: 20px;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .dashboard-main {
                padding: 20px;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dashboard-card, .action-btn {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .dashboard-card:nth-child(1) { animation-delay: 0.1s; }
        .dashboard-card:nth-child(2) { animation-delay: 0.2s; }
        .dashboard-card:nth-child(3) { animation-delay: 0.3s; }
        .dashboard-card:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="welcome-section">
                <h1 class="welcome-title">Tableau de bord Administrateur</h1>
                <p class="welcome-subtitle">Gérez votre boutique Cadeaux Élégance</p>
                
                <div class="admin-info">
                    <div class="admin-avatar">
                        <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                    </div>
                    <div class="admin-details">
                        <div class="admin-name"><?php echo htmlspecialchars($admin_username); ?></div>
                        <div class="admin-role">
                            <i class="fas fa-shield-alt"></i> 
                            <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main content -->
        <main class="dashboard-main">
            <!-- Section principale -->
            <h2 class="section-title">
                <i class="fas fa-tachometer-alt"></i>
                Tableau de bord
            </h2>
            
            <!-- Cartes principales -->
            <div class="cards-grid">
                <!-- Gestion des produits -->
                <a href="admin_produits.php" class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h3 class="card-title">Gestion des produits</h3>
                    <p class="card-description">
                        Ajoutez, modifiez ou supprimez des produits de votre catalogue.
                        Gérez les stocks, les prix et les descriptions.
                    </p>
                    <div class="card-stats">
                        <div>
                            <div class="stat-number">CRUD</div>
                            <div class="stat-label">Complet</div>
                        </div>
                        <i class="fas fa-arrow-right" style="color: #8a4baf;"></i>
                    </div>
                </a>
                
                <!-- Ajouter un administrateur -->
                <a href="add_admin_simple.php" class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3 class="card-title">Ajouter un administrateur</h3>
                    <p class="card-description">
                        Créez de nouveaux comptes administrateurs pour votre équipe.
                        Définissez les rôles et permissions.
                    </p>
                    <div class="card-stats">
                        <div>
                            <div class="stat-number">Admin+</div>
                            <div class="stat-label">Nouveaux comptes</div>
                        </div>
                        <i class="fas fa-arrow-right" style="color: #8a4baf;"></i>
                    </div>
                </a>
                
                <!-- Voir le site -->
                <a href="/sean/index.html" target="_blank" class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <h3 class="card-title">Voir le site public</h3>
                    <p class="card-description">
                        Consultez votre boutique telle qu'elle apparaît aux visiteurs.
                        Vérifiez l'affichage des produits.
                    </p>
                    <div class="card-stats">
                        <div>
                            <div class="stat-number">Site</div>
                            <div class="stat-label">Public</div>
                        </div>
                        <i class="fas fa-external-link-alt" style="color: #8a4baf;"></i>
                    </div>
                </a>
                
                <!-- Recherche de produits -->
                <a href="produits.php" target="_blank" class="dashboard-card">
                    <div class="card-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="card-title">Recherche de produits</h3>
                    <p class="card-description">
                        Testez le moteur de recherche de produits.
                        Vérifiez les filtres et l'affichage des résultats.
                    </p>
                    <div class="card-stats">
                        <div>
                            <div class="stat-number">Test</div>
                            <div class="stat-label">Recherche</div>
                        </div>
                        <i class="fas fa-external-link-alt" style="color: #8a4baf;"></i>
                    </div>
                </a>
            </div>
            
            <!-- Actions rapides -->
            <h3 class="section-title">
                <i class="fas fa-bolt"></i>
                Actions rapides
            </h3>
            
            <div class="quick-actions">
                <a href="admin_produits.php?action=add" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Nouveau produit</div>
                        <div class="action-desc">Ajouter un produit au catalogue</div>
                    </div>
                </a>
                
                <a href="admin_produits.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Liste des produits</div>
                        <div class="action-desc">Voir et gérer tous les produits</div>
                    </div>
                </a>
                
                <a href="add_admin_simple.php" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-user-cog"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Ajouter admin</div>
                        <div class="action-desc">Créer un nouveau compte admin</div>
                    </div>
                </a>
                
                <a href="../produits.php" target="_blank" class="action-btn">
                    <div class="action-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="action-text">
                        <div class="action-title">Boutique</div>
                        <div class="action-desc">Voir la boutique en ligne</div>
                    </div>
                </a>
            </div>
            
            <!-- Informations système -->
            <div class="system-info">
                <h4 style="color: #333; margin-bottom: 20px; font-size: 1.2rem;">
                    <i class="fas fa-info-circle"></i> Informations système
                </h4>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Date et heure</div>
                            <div class="info-value"><?php echo date('d/m/Y H:i:s'); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Connecté en tant que</div>
                            <div class="info-value"><?php echo htmlspecialchars($admin_username); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-network-wired"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Adresse IP</div>
                            <div class="info-value"><?php echo getClientIp(); ?></div>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="info-content">
                            <div class="info-label">Rôle</div>
                            <div class="info-value"><?php echo htmlspecialchars(ucfirst($admin_role)); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="dashboard-footer">
            <a href="login_simple.php?logout=1" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Déconnexion
            </a>
            <p class="footer-text">
                Cadeaux Élégance - Administration © <?php echo date('Y'); ?>
                <br>
                <small style="color: #888;">Session sécurisée | <?php echo $_SERVER['HTTP_HOST']; ?></small>
            </p>
        </footer>
    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter un effet de pulse sur les cartes au survol
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.animation = 'pulse 0.3s';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.animation = '';
                });
            });
            
            // Notification de bienvenue
            console.log('Bienvenue dans l\'administration, <?php echo htmlspecialchars($admin_username); ?>!');
            
            // Key shortcuts (optionnel)
            document.addEventListener('keydown', function(e) {
                // Ctrl + P pour produits
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.location.href = 'admin_produits.php';
                }
                
                // Ctrl + N pour nouveau produit
                if (e.ctrlKey && e.key === 'n') {
                    e.preventDefault();
                    window.location.href = 'admin_produits.php?action=add';
                }
                
                // Ctrl + Q pour déconnexion
                if (e.ctrlKey && e.key === 'q') {
                    e.preventDefault();
                    if (confirm('Déconnexion ?')) {
                        window.location.href = 'login_simple.php?logout=1';
                    }
                }
            });
            
            // Afficher un message si des raccourcis sont disponibles
            if (window.innerWidth > 768) {
                console.log('Raccourcis clavier disponibles:');
                console.log('Ctrl+P: Gestion des produits');
                console.log('Ctrl+N: Nouveau produit');
                console.log('Ctrl+Q: Déconnexion');
            }
        });
        
        // Style pour l'animation pulse
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.02); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>