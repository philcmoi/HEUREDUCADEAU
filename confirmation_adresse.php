<?php
session_start();

// Si pas d'adresse en session, retour au formulaire
if (!isset($_SESSION['adresse_livraison'])) {
    header('Location: livraison.html');
    exit();
}

$adresse = $_SESSION['adresse_livraison'];

// Traitement du choix
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'utiliser') {
        // Utiliser l'adresse existante, aller au paiement
        header('Location: paiement.php');
        exit();
    } elseif ($action === 'modifier') {
        // Aller au formulaire pour modification
        header('Location: livraison.html');
        exit();
    } elseif ($action === 'nouvelle') {
        // Effacer l'ancienne adresse et aller au formulaire
        unset($_SESSION['adresse_livraison']);
        header('Location: livraison.html');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmer l'adresse de livraison</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .adresse-box { background: #f5f5f5; padding: 20px; border-radius: 5px; margin: 20px 0; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; text-decoration: none; border-radius: 4px; }
        .btn-utiliser { background: #4CAF50; color: white; }
        .btn-modifier { background: #2196F3; color: white; }
        .btn-nouvelle { background: #ff9800; color: white; }
    </style>
</head>
<body>
    <h1>Utiliser cette adresse ?</h1>
    
    <div class="adresse-box">
        <strong><?php echo htmlspecialchars($adresse['nom']); ?></strong><br>
        <?php echo nl2br(htmlspecialchars($adresse['adresse'])); ?><br>
        <?php echo htmlspecialchars($adresse['code_postal']) . ' ' . htmlspecialchars($adresse['ville']); ?><br>
        <?php echo htmlspecialchars($adresse['pays']); ?><br>
        Tél: <?php echo htmlspecialchars($adresse['telephone']); ?><br>
        Email: <?php echo htmlspecialchars($adresse['email']); ?><br>
        <?php if (!empty($adresse['instructions'])): ?>
            <br><strong>Instructions:</strong><br>
            <?php echo nl2br(htmlspecialchars($adresse['instructions'])); ?>
        <?php endif; ?>
    </div>
    
    <form method="POST">
        <button type="submit" name="action" value="utiliser" class="btn btn-utiliser">
            Utiliser cette adresse
        </button>
        
        <button type="submit" name="action" value="modifier" class="btn btn-modifier">
            Modifier cette adresse
        </button>
        
        <button type="submit" name="action" value="nouvelle" class="btn btn-nouvelle">
            Saisir une nouvelle adresse
        </button>
    </form>
    
    <div style="margin-top: 30px;">
        <h3>Adresses utilisées récemment :</h3>
        <?php if (isset($_SESSION['historique_adresses']) && count($_SESSION['historique_adresses']) > 1): ?>
            <ul>
                <?php foreach ($_SESSION['historique_adresses'] as $index => $hist): 
                    if ($hist['adresse'] === $adresse['adresse'] && $hist['code_postal'] === $adresse['code_postal']) continue;
                ?>
                    <li style="margin-bottom: 10px; padding: 10px; background: #e9e9e9;">
                        <?php echo htmlspecialchars($hist['nom']); ?><br>
                        <?php echo htmlspecialchars($hist['adresse']); ?><br>
                        <?php echo htmlspecialchars($hist['code_postal']) . ' ' . htmlspecialchars($hist['ville']); ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="adresse_index" value="<?php echo $index; ?>">
                            <button type="submit" name="action" value="selectionner" style="font-size: 12px;">
                                Sélectionner
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>