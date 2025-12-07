<?php
// dashboard.php - Simple tableau de bord
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login_simple.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #8a4baf;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>Tableau de bord</h1>
            <p>Bienvenue, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
            <p>Rôle : <?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'admin'); ?></p>
            
            <h2>Actions :</h2>
            <a href="add_admin_simple.php" class="btn">➕ Ajouter un admin</a>
            <a href="login_simple.php?logout=1" class="btn" style="background: #dc3545;">🚪 Déconnexion</a>
        </div>
    </div>
</body>
</html>