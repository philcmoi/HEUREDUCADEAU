<?php
// login_simple.php - Version ultra simple et fonctionnelle

// Démarrer la session
session_start();

// Gestion de la connexion/déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login_simple.php?message=deconnected');
    exit;
}

// Mode démo - utilisateurs valides
$valid_users = [
    'admin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password = 'password'
    'superadmin' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',

    'Philippe' => '$2y$10$UBzIZl9kTsUvamfOTUhGFufEWNNWQOa7nJ7k2Mp5Baj0eTquTPTg2' // lhpp.philippe@gmail.com];
];
// Traitement du formulaire
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validation simple
    if (empty($username) || empty($password)) {
        $error = 'Veuillez remplir tous les champs';
    } 
    // Vérification des identifiants
    elseif (isset($valid_users[$username]) && password_verify($password, $valid_users[$username])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = 1; // Important pour admin_protection.php
    $_SESSION['admin_username'] = $username;
    // Pour que "admin" soit aussi superadmin
    $_SESSION['admin_role'] = (in_array($username, ['superadmin', 'admin'])) ? 'superadmin' : 'admin';    $_SESSION['admin_ip'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $_SESSION['last_activity'] = time(); header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Identifiants incorrects';
    }
}

// Message de déconnexion
if (isset($_GET['message']) && $_GET['message'] === 'deconnected') {
    $message = 'Vous avez été déconnecté avec succès';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Simple</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h1 {
            color: #8a4baf;
            text-align: center;
            margin-bottom: 30px;
        }
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #ffebee;
            color: #c62828;
            border-left: 4px solid #c62828;
        }
        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #8a4baf;
            outline: none;
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: #8a4baf;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #6a3093;
        }
        .credentials {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
            font-size: 14px;
            color: #666;
        }
        .credentials strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>🔐 Connexion Admin</h1>
        
        <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($message): ?>
        <div class="alert alert-success">
            ✅ <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Nom d'utilisateur</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       placeholder="admin" 
                       required
                       autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="password" 
                       required>
            </div>
            
            <button type="submit" class="btn">Se connecter</button>
        </form>
        
        <!--<div class="credentials">
            <p><strong>Identifiants de test :</strong></p>
            <p>Nom d'utilisateur : <strong>admin</strong> ou <strong>superadmin</strong></p>
            <p>Mot de passe : <strong>password</strong></p>
        </div>-->
    </div>
</body>
</html>