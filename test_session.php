<?php
// ============================================
// TEST DE SESSION - Fichier de diagnostic
// ============================================

// Définir le chemin de session commun
$sessionPath = dirname(__DIR__) . '/sessions';
if (!is_dir($sessionPath)) {
    if (mkdir($sessionPath, 0755, true)) {
        $sessionPathCreated = true;
    } else {
        $sessionPathCreated = false;
    }
} else {
    $sessionPathCreated = true;
}

// Configurer les paramètres AVANT session_start()
ini_set('session.save_path', $sessionPath);
ini_set('session.gc_maxlifetime', 86400);
ini_set('session.cookie_lifetime', 86400);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// Nom de session cohérent
session_name('heure_du_cadeau');

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Tester la session
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}

// Tester l'accès en écriture au dossier sessions
$sessionFile = $sessionPath . '/sess_' . session_id();
$canWriteSession = is_writable($sessionPath);
$sessionFileExists = file_exists($sessionFile);

// Analyser les cookies
$cookieName = session_name();
$cookieExists = isset($_COOKIE[$cookieName]);

// Informations système
$phpVersion = phpversion();
$sessionStatus = session_status();
$sessionModule = function_exists('session_module_name') ? session_module_name() : 'Inconnu';

// Vérifier les paramètres actuels
$currentSavePath = ini_get('session.save_path');
$currentCookieParams = session_get_cookie_params();

// Tester l'écriture dans le dossier sessions
$testFile = $sessionPath . '/test_write.txt';
$testWrite = false;
if ($canWriteSession) {
    if (file_put_contents($testFile, 'Test d\'écriture ' . date('Y-m-d H:i:s'))) {
        $testWrite = true;
        unlink($testFile); // Nettoyer
    }
}

// Vérifier les variables de session panier
$panierInfo = 'NON';
if (isset($_SESSION['panier'])) {
    if (is_array($_SESSION['panier'])) {
        $panierCount = count($_SESSION['panier']);
        $panierInfo = "OUI ($panierCount items)";
        
        // Détail des items
        $panierDetails = [];
        foreach ($_SESSION['panier'] as $index => $item) {
            if (is_array($item)) {
                $panierDetails[] = "Item $index: " . 
                    (isset($item['id_produit']) ? "ID=" . $item['id_produit'] : '') .
                    (isset($item['nom']) ? " Nom=" . substr($item['nom'], 0, 20) : '') .
                    (isset($item['quantite']) ? " Qte=" . $item['quantite'] : '');
            } else {
                $panierDetails[] = "Item $index: " . gettype($item);
            }
        }
    } else {
        $panierInfo = "OUI (type: " . gettype($_SESSION['panier']) . ")";
    }
}

// Lister toutes les variables de session
$allSessionVars = [];
foreach ($_SESSION as $key => $value) {
    if (is_array($value)) {
        $allSessionVars[$key] = "array(" . count($value) . " items)";
    } else {
        $allSessionVars[$key] = htmlspecialchars((string)$value);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Session - HEURE DU CADEAU</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
            overflow: hidden;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3498db;
        }
        
        .header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .header .subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .test-results {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .test-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            border-left: 5px solid #3498db;
            transition: transform 0.3s ease;
        }
        
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .test-card.success {
            border-left-color: #27ae60;
            background: #d4edda;
        }
        
        .test-card.warning {
            border-left-color: #f39c12;
            background: #fff3cd;
        }
        
        .test-card.error {
            border-left-color: #e74c3c;
            background: #f8d7da;
        }
        
        .test-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .test-card h3 i {
            font-size: 1.2rem;
        }
        
        .test-card p {
            color: #555;
            margin: 8px 0;
        }
        
        .test-card code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .session-details {
            background: #2c3e50;
            color: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
        }
        
        .session-details h2 {
            color: #3498db;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        
        .var-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .var-item {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid #3498db;
        }
        
        .var-key {
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
            font-family: monospace;
        }
        
        .var-value {
            color: #ecf0f1;
            word-break: break-all;
        }
        
        .actions {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 2px solid #eee;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 0 10px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
            background: linear-gradient(135deg, #2980b9, #2573a7);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #27ae60, #219653);
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #219653, #1e8449);
        }
        
        .debug-info {
            background: #34495e;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            font-family: monospace;
            font-size: 0.9rem;
            overflow-x: auto;
        }
        
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-ok {
            background-color: #27ae60;
            box-shadow: 0 0 8px #27ae60;
        }
        
        .status-warning {
            background-color: #f39c12;
            box-shadow: 0 0 8px #f39c12;
        }
        
        .status-error {
            background-color: #e74c3c;
            box-shadow: 0 0 8px #e74c3c;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .test-results {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-vial"></i> Test de Session</h1>
            <p class="subtitle">Diagnostic du système de sessions PHP</p>
        </div>
        
        <div class="test-results">
            <!-- Test 1: Dossier sessions -->
            <div class="test-card <?php echo $sessionPathCreated ? 'success' : 'error'; ?>">
                <h3>
                    <span class="status-indicator <?php echo $sessionPathCreated ? 'status-ok' : 'status-error'; ?>"></span>
                    <i class="fas fa-folder"></i> Dossier Sessions
                </h3>
                <p><strong>Chemin:</strong> <?php echo htmlspecialchars($sessionPath); ?></p>
                <p><strong>Existe:</strong> <?php echo is_dir($sessionPath) ? '✅ OUI' : '❌ NON'; ?></p>
                <p><strong>Écriture:</strong> <?php echo $canWriteSession ? '✅ OUI' : '❌ NON'; ?></p>
                <p><strong>Test écriture:</strong> <?php echo $testWrite ? '✅ RÉUSSI' : '❌ ÉCHEC'; ?></p>
                <?php if (!$sessionPathCreated): ?>
                    <p><em>⚠️ Impossible de créer le dossier. Vérifiez les permissions.</em></p>
                <?php endif; ?>
            </div>
            
            <!-- Test 2: Session PHP -->
            <div class="test-card success">
                <h3>
                    <span class="status-indicator status-ok"></span>
                    <i class="fas fa-cogs"></i> Session PHP
                </h3>
                <p><strong>Status:</strong> 
                    <?php 
                    switch($sessionStatus) {
                        case PHP_SESSION_DISABLED: echo '❌ DÉSACTIVÉE'; break;
                        case PHP_SESSION_NONE: echo '⚠️ NON DÉMARRÉE'; break;
                        case PHP_SESSION_ACTIVE: echo '✅ ACTIVE'; break;
                    }
                    ?>
                </p>
                <p><strong>Session ID:</strong> <code><?php echo session_id(); ?></code></p>
                <p><strong>Nom session:</strong> <?php echo session_name(); ?></p>
                <p><strong>Module:</strong> <?php echo $sessionModule; ?></p>
                <p><strong>Compteur test:</strong> <?php echo $_SESSION['test_counter']; ?></p>
            </div>
            
            <!-- Test 3: Cookie -->
            <div class="test-card <?php echo $cookieExists ? 'success' : 'warning'; ?>">
                <h3>
                    <span class="status-indicator <?php echo $cookieExists ? 'status-ok' : 'status-warning'; ?>"></span>
                    <i class="fas fa-cookie"></i> Cookie Session
                </h3>
                <p><strong>Nom cookie:</strong> <?php echo $cookieName; ?></p>
                <p><strong>Existe:</strong> <?php echo $cookieExists ? '✅ OUI' : '⚠️ NON'; ?></p>
                <p><strong>Valeur:</strong> <?php echo $cookieExists ? $_COOKIE[$cookieName] : 'Non défini'; ?></p>
                <p><strong>Fichier session:</strong> <?php echo $sessionFileExists ? '✅ EXISTE' : '⚠️ ABSENT'; ?></p>
                <?php if (!$cookieExists): ?>
                    <p><em>⚠️ Le cookie n'est pas présent. Peut-être que les cookies sont désactivés.</em></p>
                <?php endif; ?>
            </div>
            
            <!-- Test 4: Configuration -->
            <div class="test-card success">
                <h3>
                    <span class="status-indicator status-ok"></span>
                    <i class="fas fa-sliders-h"></i> Configuration
                </h3>
                <p><strong>PHP Version:</strong> <?php echo $phpVersion; ?></p>
                <p><strong>Chemin save:</strong> <?php echo $currentSavePath; ?></p>
                <p><strong>Lifetime:</strong> <?php echo ini_get('session.gc_maxlifetime'); ?> sec</p>
                <p><strong>Cookie Lifetime:</strong> <?php echo $currentCookieParams['lifetime']; ?> sec</p>
                <p><strong>Secure:</strong> <?php echo $currentCookieParams['secure'] ? '✅ OUI' : '⚠️ NON'; ?></p>
                <p><strong>HTTP Only:</strong> <?php echo $currentCookieParams['httponly'] ? '✅ OUI' : '⚠️ NON'; ?></p>
            </div>
        </div>
        
        <!-- Panier info -->
        <div class="test-card <?php echo isset($_SESSION['panier']) ? 'success' : 'warning'; ?>">
            <h3>
                <span class="status-indicator <?php echo isset($_SESSION['panier']) ? 'status-ok' : 'status-warning'; ?>"></span>
                <i class="fas fa-shopping-cart"></i> Panier dans Session
            </h3>
            <p><strong>Présent:</strong> <?php echo $panierInfo; ?></p>
            
            <?php if (isset($_SESSION['panier']) && is_array($_SESSION['panier']) && !empty($_SESSION['panier'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 5px;">
                    <p><strong>Contenu détaillé:</strong></p>
                    <?php foreach ($panierDetails as $detail): ?>
                        <p style="margin: 5px 0; padding-left: 15px; font-family: monospace; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($detail); ?>
                        </p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['checkout_authorized'])): ?>
                <p style="margin-top: 10px; color: #27ae60;">
                    <i class="fas fa-check-circle"></i> Checkout autorisé: OUI
                </p>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['panier_bdd_id'])): ?>
                <p><strong>Panier BDD ID:</strong> <?php echo $_SESSION['panier_bdd_id']; ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Toutes les variables de session -->
        <div class="session-details">
            <h2><i class="fas fa-list"></i> Variables de Session</h2>
            
            <?php if (!empty($allSessionVars)): ?>
                <div class="var-list">
                    <?php foreach ($allSessionVars as $key => $value): ?>
                        <div class="var-item">
                            <div class="var-key"><?php echo htmlspecialchars($key); ?></div>
                            <div class="var-value"><?php echo $value; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #bdc3c7; font-style: italic;">
                    Aucune variable de session définie.
                </p>
            <?php endif; ?>
        </div>
        
        <!-- Informations de débogage -->
        <div class="debug-info">
            <h3 style="color: #3498db; margin-bottom: 10px;">Debug Info:</h3>
            <p>$_SERVER['HTTP_COOKIE']: <?php echo isset($_SERVER['HTTP_COOKIE']) ? htmlspecialchars($_SERVER['HTTP_COOKIE']) : 'Non défini'; ?></p>
            <p>$_SERVER['REQUEST_TIME']: <?php echo date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']); ?></p>
            <p>session_save_path(): <?php echo session_save_path(); ?></p>
            <p>session_cache_limiter(): <?php echo session_cache_limiter(); ?></p>
            <p>ini_get('session.use_strict_mode'): <?php echo ini_get('session.use_strict_mode'); ?></p>
            <p>ini_get('session.use_trans_sid'): <?php echo ini_get('session.use_trans_sid'); ?></p>
        </div>
        
        <!-- Actions -->
        <div class="actions">
            <a href="test_session.php" class="btn">
                <i class="fas fa-redo"></i> Recharger (Incrémente compteur)
            </a>
            
            <a href="test_session.php?clear=1" class="btn btn-danger">
                <i class="fas fa-trash"></i> Effacer Session
            </a>
            
            <a href="panier.php?action=test" class="btn">
                <i class="fas fa-shopping-cart"></i> Tester API Panier
            </a>
            
            <a href="panier.php?action=get" class="btn">
                <i class="fas fa-eye"></i> Voir Panier JSON
            </a>
            
            <a href="livraison.php" class="btn btn-success">
                <i class="fas fa-truck"></i> Tester Livraison.php
            </a>
            
            <button onclick="addTestProduct()" class="btn">
                <i class="fas fa-plus"></i> Ajouter produit test
            </button>
        </div>
    </div>
    
    <script>
        function addTestProduct() {
            // Ajouter un produit de test au panier via AJAX
            fetch('panier.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'ajouter',
                    id_produit: 1, // ID du premier produit
                    quantite: 1
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Produit ajouté au panier! Rechargez la page pour voir les changements.');
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erreur de connexion: ' + error.message);
            });
        }
        
        // Gestion du paramètre clear
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('clear')) {
            fetch('panier.php?action=vider')
                .then(() => {
                    // Forcer le rechargement après nettoyage
                    setTimeout(() => {
                        window.location.href = 'test_session.php';
                    }, 500);
                });
        }
        
        // Vérifier si on peut écrire des cookies
        document.cookie = "test_cookie=test_value; path=/; max-age=60";
        const canWriteCookies = document.cookie.indexOf('test_cookie') !== -1;
        
        if (!canWriteCookies) {
            alert('⚠️ Attention: Les cookies semblent désactivés dans votre navigateur.\nVeuillez les activer pour que les sessions fonctionnent correctement.');
        }
    </script>
</body>
</html>