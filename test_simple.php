<?php
// test_simple.php - Version CORRIGÉE

// Utiliser le même nom que panier.php
session_name('heure_du_cadeau');

// Désactiver cookies
ini_set('session.use_cookies', 0);
ini_set('session.use_only_cookies', 0);

// Récupérer l'ID de session
if (isset($_GET['heure_du_cadeau'])) {
    session_id($_GET['heure_du_cadeau']);
} elseif (isset($_GET['sid'])) {
    // Support alternatif pour 'sid='
    session_id($_GET['sid']);
}

session_start();

// Initialiser compteur
if (!isset($_SESSION['count'])) {
    $_SESSION['count'] = 0;
}
$_SESSION['count']++;

// Générer les URLs avec le bon paramètre
$sid = session_id();
$param_name = session_name(); // 'heure_du_cadeau'
$session_param = $param_name . '=' . $sid;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Simple Session</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .info { background: #f0f0f0; padding: 15px; margin: 10px 0; }
        .test-links a { display: block; margin: 5px 0; padding: 10px; background: #4CAF50; color: white; text-decoration: none; }
        .test-links a:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="info">
        <h2>Informations Session</h2>
        <p><strong>Session Name:</strong> <?php echo session_name(); ?></p>
        <p><strong>Session ID:</strong> <?php echo session_id(); ?></p>
        <p><strong>Compteur:</strong> <?php echo $_SESSION['count']; ?></p>
        <p><strong>Panier items:</strong> <?php echo isset($_SESSION['panier']) ? count($_SESSION['panier']) : 0; ?></p>
    </div>
    
    <p><a href="test_simple.php?<?php echo $session_param; ?>">🔁 Incrémenter le compteur</a></p>
    
    <div class="test-links">
        <h2>📦 Tester Panier API</h2>
        <a href="panier.php?action=test&<?php echo $session_param; ?>">🧪 Test API</a>
        <a href="panier.php?action=ajouter&<?php echo $session_param; ?>&id_produit=1&quantite=1">➕ Ajouter produit 1</a>
        <a href="panier.php?action=ajouter&<?php echo $session_param; ?>&id_produit=2&quantite=2">➕ Ajouter produit 2 (x2)</a>
        <a href="panier.php?action=get&<?php echo $session_param; ?>">👁️ Voir panier</a>
        <a href="panier.php?action=init_checkout&<?php echo $session_param; ?>">🚀 Init Checkout</a>
        <a href="panier.php?action=vider&<?php echo $session_param; ?>">🗑️ Vider panier</a>
    </div>
    
    <div class="test-links">
        <h2>🚚 Tester Livraison</h2>
        <a href="livraison.php?<?php echo $session_param; ?>">📦 Page Livraison</a>
    </div>
    
    <div class="info">
        <h3>URL de votre session :</h3>
        <input type="text" value="?<?php echo $session_param; ?>" style="width: 100%; padding: 10px; margin: 10px 0;">
        <p><em>Copiez ce paramètre pour vos tests</em></p>
    </div>
</body>
</html>