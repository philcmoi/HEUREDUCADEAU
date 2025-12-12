<?php
// login.php - Adapté à heureducadeau
// Démarrer la session UNIQUEMENT si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connexion à la base de données
$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Si déjà connecté, rediriger vers le dashboard
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_role'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = "Veuillez remplir tous les champs";
    } else {
        try {
            // Vérifier les tentatives de connexion
            $sql = "SELECT login_attempts, last_attempt FROM administrateurs WHERE username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['username' => $username]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Vérifier si le compte est bloqué (trop de tentatives)
            if ($admin_data && $admin_data['login_attempts'] >= 5) {
                $last_attempt = strtotime($admin_data['last_attempt']);
                $now = time();
                $minutes_passed = ($now - $last_attempt) / 60;
                
                if ($minutes_passed < 15) { // Bloqué pendant 15 minutes
                    $remaining = ceil(15 - $minutes_passed);
                    $error = "Compte temporairement bloqué. Réessayez dans $remaining minutes.";
                } else {
                    // Réinitialiser les tentatives après 15 minutes
                    $sql = "UPDATE administrateurs SET login_attempts = 0 WHERE username = :username";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['username' => $username]);
                }
            }
            
            if (empty($error)) {
                // Récupérer l'administrateur
                $sql = "SELECT * FROM administrateurs WHERE username = :username";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['username' => $username]);
                $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($admin) {
                    // Vérifier le statut du compte
                    if ($admin['status'] === 'locked') {
                        $error = "Ce compte est verrouillé. Contactez l'administrateur.";
                    } elseif ($admin['status'] === 'inactive') {
                        $error = "Ce compte est inactif.";
                    } elseif ($admin['password_hash'] === $password) { // Note: Vous devriez utiliser password_verify() avec des hash sécurisés
                        // Connexion réussie
                        
                        // Réinitialiser les tentatives de connexion
                        $sql = "UPDATE administrateurs SET 
                                login_attempts = 0, 
                                last_login = NOW(),
                                last_attempt = NOW()
                                WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['id' => $admin['id']]);
                        
                        // Définir les variables de session
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['last_activity'] = time();
                        $_SESSION['login_time'] = date('Y-m-d H:i:s');
                        
                        // Redirection
                        $redirect = $_GET['redirect'] ?? 'dashboard.php';
                        header('Location: ' . $redirect);
                        exit();
                    } else {
                        // Mot de passe incorrect - Incrémenter les tentatives
                        $sql = "UPDATE administrateurs SET 
                                login_attempts = login_attempts + 1,
                                last_attempt = NOW()
                                WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['id' => $admin['id']]);
                        
                        $error = "Nom d'utilisateur ou mot de passe incorrect";
                    }
                } else {
                    $error = "Nom d'utilisateur ou mot de passe incorrect";
                }
            }
        } catch(PDOException $e) {
            $error = "Erreur système. Veuillez réessayer plus tard.";
            error_log("Erreur connexion admin: " . $e->getMessage());
        }
    }
}

// Messages d'erreur depuis les redirections
if (isset($_GET['error'])) {
    $error_messages = [
        'not_logged_in' => 'Veuillez vous connecter pour accéder à cette page.',
        'no_role' => 'Rôle administrateur non défini.',
        'insufficient_privileges' => 'Vous n\'avez pas les permissions nécessaires.',
        'account_inactive' => 'Votre compte est désactivé.',
        'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
        'account_not_found' => 'Compte administrateur non trouvé.'
    ];
    $error = $error_messages[$_GET['error']] ?? 'Erreur inconnue';
}

if (isset($_GET['message'])) {
    $success_messages = [
        'logged_out' => 'Vous avez été déconnecté avec succès.',
        'password_changed' => 'Mot de passe changé avec succès.'
    ];
    $success = $success_messages[$_GET['message']] ?? '';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Administrateur - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background-color: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 450px;
            overflow: hidden;
        }
        
        .login-header {
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .login-header h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .login-body {
            padding: 40px 30px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #444;
            font-size: 14px;
        }
        
        .input-with-icon {
            position: relative;
        }
        
        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 18px;
        }
        
        .input-with-icon input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-with-icon input:focus {
            outline: none;
            border-color: #6a11cb;
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(106, 17, 203, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 18px;
        }
        
        .demo-credentials {
            margin-top: 25px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #6a11cb;
        }
        
        .demo-credentials h4 {
            color: #6a11cb;
            margin-bottom: 10px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .credential-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .credential-item i {
            color: #6a11cb;
            width: 20px;
        }
        
        .credential-warning {
            margin-top: 10px;
            padding: 10px;
            background-color: #fff3cd;
            border-radius: 6px;
            font-size: 12px;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        @media (max-width: 480px) {
            .login-container {
                border-radius: 15px;
            }
            
            .login-header {
                padding: 30px 20px;
            }
            
            .login-body {
                padding: 30px 20px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>
                <i class="fas fa-gift"></i>
                Heure du Cadeau
            </h1>
            <p>Administration de la boutique</p>
        </div>
        
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Entrez votre nom d'utilisateur">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required 
                               placeholder="Entrez votre mot de passe">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i>
                    Se connecter
                </button>
            </form>
            
            <div class="demo-credentials">
                <h4><i class="fas fa-key"></i> Identifiants de test</h4>
                <div class="credential-item">
                    <i class="fas fa-user"></i>
                    <span><strong>Nom d'utilisateur:</strong> admin</span>
                </div>
                <div class="credential-item">
                    <i class="fas fa-lock"></i>
                    <span><strong>Mot de passe:</strong> 007</span>
                </div>
                <div class="credential-item">
                    <i class="fas fa-user-shield"></i>
                    <span><strong>Rôle:</strong> superadmin</span>
                </div>
                <div class="credential-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Ces identifiants sont pour le développement. Changez les en production!
                </div>
            </div>
            
            <div class="login-footer">
                <p><i class="fas fa-shield-alt"></i> Connexion sécurisée - © <?php echo date('Y'); ?> Heure du Cadeau</p>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Focus sur le champ username au chargement
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Animation d'entrée
            const container = document.querySelector('.login-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.5s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Gestion des erreurs de formulaire
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs');
            }
        });
    </script>
</body>
</html>