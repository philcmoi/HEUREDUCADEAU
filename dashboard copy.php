<?php
// dashboard.php
session_start();

// ============================================
// CORRECTION : Supprimer la fonction duplicate
// ============================================
// Supprimez la fonction getClientIp() de ce fichier si elle existe à la ligne 16
// Gardez seulement l'appel à admin_protection.php

// Inclure la protection admin
require_once 'admin_protection.php'; // Utiliser require_once pour éviter les inclusions multiples

// ============================================
// VOTRE CODE DASHBOARD ICI
// ============================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .header {
            background-color: #333;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .user-info {
            background-color: #e8f4fc;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .menu {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .menu a {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
        }
        .menu a:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Tableau de bord Administrateur</h1>
    </div>
    
    <div class="user-info">
        <h3>Informations de session :</h3>
        <p><strong>ID Utilisateur :</strong> <?php echo htmlspecialchars($_SESSION['user_id'] ?? 'Non défini'); ?></p>
        <p><strong>Rôle :</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Non défini'); ?></p>
        <p><strong>IP :</strong> <?php echo getClientIp(); ?></p>
        <p><strong>Session ID :</strong> <?php echo session_id(); ?></p>
    </div>
    
    <div class="menu">
        <a href="admin_produits.php">Gestion des produits</a>
        <a href="admin_users.php">Gestion des utilisateurs</a>
        <a href="admin_orders.php">Commandes</a>
        <a href="logout.php">Déconnexion</a>
    </div>
    
    <div class="content">
        <h2>Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrateur'); ?>!</h2>
        <p>Vous avez accès à toutes les fonctionnalités administratives.</p>
        
        <?php if ($_SESSION['user_role'] === 'superAdmin'): ?>
            <div style="background-color: #ffebee; padding: 15px; border-radius: 5px; border-left: 4px solid #f44336;">
                <h3>🔧 Fonctions Super Admin</h3>
                <p>En tant que Super Admin, vous avez des privilèges étendus :</p>
                <ul>
                    <li>Gestion de tous les administrateurs</li>
                    <li>Configuration du système</li>
                    <li>Accès aux logs et audits</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>