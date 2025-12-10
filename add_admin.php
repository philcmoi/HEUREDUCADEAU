<?php
/**
 * add_admin_simple.php - Version simplifiée pour login_simple.php PROTÉGÉ
 * Permet à tous les administrateurs connectés d'ajouter d'autres admins
 */

// Démarrer la session et inclure la protection
session_start();
require_once 'admin_protection.php'; // AJOUTÉ

// Vérifier l'accès admin
secureAdminPage('admin'); // AJOUTÉ

// Titre de la page
$page_title = "Ajouter un Administrateur";

// Variables
$error = '';
$success = '';
$form_data = [
    'username' => '',
    'email' => '',
    'full_name' => '',
    'role' => 'admin' // Par défaut, on crée des admin normaux
];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!CSRFProtection::validateToken($csrf_token)) {
        die('Token CSRF invalide. Action refusée.');
    }
    
    // Récupérer et nettoyer les données
    $form_data = [
        'username' => htmlspecialchars(trim($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars(trim($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'full_name' => htmlspecialchars(trim($_POST['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'role' => htmlspecialchars(trim($_POST['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8'),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    // Si l'utilisateur n'est pas superadmin, il ne peut créer que des admin normaux
    if (($_SESSION['admin_role'] ?? 'admin') !== 'superadmin') {
        $form_data['role'] = 'admin'; // Forcer le rôle admin
    }
    
    // Valider les données
    $validation = validateAdminData($form_data);
    
    if ($validation['valid']) {
        // Hacher le mot de passe
        $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
        
        // Ajouter l'administrateur au fichier login_simple.php
        $result = addAdminToLoginFile($form_data, $hashed_password);
        
        if ($result['success']) {
            $success = $result['message'];
            // Réinitialiser le formulaire
            $form_data = [
                'username' => '',
                'email' => '',
                'full_name' => '',
                'role' => 'admin'
            ];
        } else {
            $error = $result['message'];
        }
    } else {
        $error = $validation['message'];
    }
}

/**
 * Valider les données de l'administrateur
 */
function validateAdminData($data) {
    // Vérifier que tous les champs requis sont remplis
    $required = ['username', 'email', 'password', 'confirm_password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return [
                'valid' => false,
                'message' => "Le champ " . ucfirst(str_replace('_', ' ', $field)) . " est requis."
            ];
        }
    }
    
    // Vérifier le nom d'utilisateur
    if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $data['username'])) {
        return [
            'valid' => false,
            'message' => 'Le nom d\'utilisateur doit contenir 3 à 50 caractères (lettres, chiffres, underscore).'
        ];
    }
    
    // Vérifier l'email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'message' => 'L\'adresse email n\'est pas valide.'
        ];
    }
    
    // Vérifier le mot de passe
    if (strlen($data['password']) < 8) {
        return [
            'valid' => false,
            'message' => 'Le mot de passe doit contenir au moins 8 caractères.'
        ];
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return [
            'valid' => false,
            'message' => 'Les mots de passe ne correspondent pas.'
        ];
    }
    
    // Vérifier le rôle
    $valid_roles = ['superadmin', 'admin'];
    if (!in_array($data['role'], $valid_roles)) {
        return [
            'valid' => false,
            'message' => 'Le rôle doit être "admin" ou "superadmin".'
        ];
    }
    
    return ['valid' => true, 'message' => ''];
}

/**
 * Ajouter l'administrateur directement dans login_simple.php
 */
function addAdminToLoginFile($data, $hashed_password) {
    $login_file = __DIR__ . '/login_simple.php';
    
    if (!file_exists($login_file)) {
        return [
            'success' => false,
            'message' => 'Fichier login_simple.php introuvable.'
        ];
    }
    
    // Lire le contenu du fichier
    $content = file_get_contents($login_file);
    
    // Chercher le tableau $valid_users
    $pattern = '/\$valid_users\s*=\s*\[(.*?)\];/s';
    
    if (preg_match($pattern, $content, $matches)) {
        $array_content = $matches[1];
        
        // Vérifier si l'utilisateur existe déjà
        if (strpos($array_content, "'" . $data['username'] . "'") !== false) {
            return [
                'success' => false,
                'message' => 'Ce nom d\'utilisateur existe déjà.'
            ];
        }
        
        // Préparer la nouvelle entrée
        $new_entry = "\n    '{$data['username']}' => '{$hashed_password}', // {$data['email']}";
        
        // Ajouter à la fin du tableau (avant la fermeture ])
        $new_array_content = $array_content . $new_entry;
        
        // Remplacer dans le contenu
        $updated_content = str_replace($array_content, $new_array_content, $content);
        
        // Sauvegarder
        if (file_put_contents($login_file, $updated_content)) {
            return [
                'success' => true,
                'message' => "Administrateur '{$data['username']}' ajouté avec succès. Il peut maintenant se connecter."
            ];
        }
    }
    
    return [
        'success' => false,
        'message' => 'Impossible de modifier le fichier login_simple.php.'
    ];
}

// Générer un token CSRF
$csrf_token = CSRFProtection::generateToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
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
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 600px;
            margin: 20px;
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
        
        .user-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .user-info.superadmin {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
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
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus,
        select:focus {
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
            margin-bottom: 10px;
        }
        
        .btn:hover {
            background: #6a3093;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .requirements {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .requirements ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
        
        .role-notice {
            background: #e7f3ff;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .security-info {
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-top: 15px;
            font-size: 12px;
            color: #155724;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>➕ Ajouter un Administrateur</h1>
        
        <!-- Info utilisateur -->
        <?php 
        $user_role = $_SESSION['admin_role'] ?? 'admin';
        $is_superadmin = ($user_role === 'superadmin');
        ?>
        <div class="user-info <?php echo $is_superadmin ? 'superadmin' : ''; ?>">
            <strong>👤 <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Inconnu'); ?></strong>
            | Rôle : <strong><?php echo htmlspecialchars($user_role); ?></strong>
            | IP : <strong><?php echo getClientIp(); ?></strong>
            <?php if ($is_superadmin): ?>
                <br><small>✨ Vous avez les droits complets</small>
            <?php else: ?>
                <br><small>⚠️ Vous ne pouvez créer que des administrateurs normaux</small>
            <?php endif; ?>
        </div>
        
        <!-- Messages d'alerte -->
        <?php if ($error): ?>
        <div class="alert alert-error">
            ⚠️ <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="alert alert-success">
            ✅ <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <!-- Notice sur les rôles -->
        <?php if (!$is_superadmin): ?>
        <div class="role-notice">
            <strong>Note :</strong> En tant qu'administrateur normal, vous ne pouvez créer que des comptes avec le rôle "Administrateur".
        </div>
        <?php endif; ?>
        
        <!-- Formulaire -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"> <!-- AJOUTÉ -->
            
            <div class="form-group">
                <label for="username">Nom d'utilisateur *</label>
                <input type="text" 
                       id="username" 
                       name="username" 
                       placeholder="ex: jdupont"
                       value="<?php echo htmlspecialchars($form_data['username']); ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       placeholder="ex: admin@exemple.com"
                       value="<?php echo htmlspecialchars($form_data['email']); ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="full_name">Nom complet (optionnel)</label>
                <input type="text" 
                       id="full_name" 
                       name="full_name" 
                       placeholder="ex: Jean Dupont"
                       value="<?php echo htmlspecialchars($form_data['full_name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="role">Rôle *</label>
                <select id="role" name="role" required <?php echo !$is_superadmin ? 'disabled' : ''; ?>>
                    <option value="admin" <?php echo $form_data['role'] == 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                    <option value="superadmin" <?php echo $form_data['role'] == 'superadmin' ? 'selected' : ''; ?>>Super Administrateur</option>
                </select>
                <?php if (!$is_superadmin): ?>
                    <input type="hidden" name="role" value="admin">
                    <small style="color: #666;">Seuls les super administrateurs peuvent créer d'autres super administrateurs.</small>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Mot de passe *</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       placeholder="Minimum 8 caractères"
                       required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe *</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       placeholder="Répétez le mot de passe"
                       required>
            </div>
            
            <!-- Instructions -->
            <div class="requirements">
                <strong>📋 Exigences :</strong>
                <ul>
                    <li>Nom d'utilisateur : 3-50 caractères (lettres, chiffres, _)</li>
                    <li>Email valide</li>
                    <li>Mot de passe : minimum 8 caractères</li>
                    <li>Le nouvel administrateur pourra se connecter via login_simple.php</li>
                </ul>
            </div>
            
            <!-- Info sécurité -->
            <div class="security-info">
                <i class="fas fa-shield-alt"></i> 
                <strong>Sécurité :</strong> Cette action est protégée par token CSRF
            </div>
            
            <button type="submit" class="btn">➕ Créer l'administrateur</button>
            <a href="dashboard.php" class="btn btn-secondary">← Retour au tableau de bord</a>
            <a href="login_simple.php?logout=1" class="btn btn-danger">🚪 Déconnexion</a>
        </form>
        
        <!-- Information -->
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #666; font-size: 14px;">
            <p><strong>💡 Note :</strong> L'administrateur sera ajouté directement dans le fichier login_simple.php</p>
            <p><i class="fas fa-clock"></i> Session active depuis : <?php echo isset($_SESSION['last_activity']) ? date('H:i:s', $_SESSION['last_activity']) : 'N/A'; ?></p>
        </div>
    </div>
    
    <script>
        // Validation côté client
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            // Vérifier le nom d'utilisateur
            if (!/^[a-zA-Z0-9_]{3,50}$/.test(username)) {
                e.preventDefault();
                alert('Nom d\'utilisateur invalide. 3-50 caractères (lettres, chiffres, _)');
                return false;
            }
            
            // Vérifier le mot de passe
            if (password.length < 8) {
                e.preventDefault();
                alert('Le mot de passe doit contenir au moins 8 caractères.');
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Les mots de passe ne correspondent pas.');
                return false;
            }
            
            // Demander confirmation
            if (!confirm('Confirmez-vous la création de cet administrateur ?')) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });
        
        // Générer un mot de passe aléatoire
        function generatePassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            
            document.getElementById('password').value = password;
            document.getElementById('confirm_password').value = password;
            
            alert('Mot de passe généré : ' + password + '\n\nCopiez-le avant de continuer !');
        }
        
        // Ajouter un bouton pour générer un mot de passe
        const passwordField = document.getElementById('password');
        const generateBtn = document.createElement('button');
        generateBtn.type = 'button';
        generateBtn.textContent = '🎲 Générer un mot de passe';
        generateBtn.style.cssText = 'background: #17a2b8; color: white; border: none; padding: 8px 12px; border-radius: 4px; margin-top: 5px; cursor: pointer;';
        generateBtn.onclick = generatePassword;
        passwordField.parentNode.appendChild(generateBtn);
    </script>
</body>
</html>