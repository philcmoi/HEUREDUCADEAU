<?php
// confirmation_adresse.php - PAGE DE CONFIRMATION POUR ANCIEN SYSTÈME
session_start();

// Vérifier si une adresse existe
if (!isset($_SESSION['adresse_livraison'])) {
    header('Location: livraison.html');
    exit();
}

$adresse = $_SESSION['adresse_livraison'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmer l'adresse - HEURE DU CADEAU</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f8f9fa;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #5a67d8;
            padding-bottom: 10px;
        }
        .address-details {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            line-height: 1.6;
        }
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        .btn-confirm {
            background: #5a67d8;
            color: white;
            border: none;
            cursor: pointer;
        }
        .btn-confirm:hover {
            background: #4c51bf;
        }
        .btn-modify {
            background: #edf2f7;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }
        .btn-modify:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> Confirmer votre adresse</h1>
        
        <p>Nous avons trouvé une adresse enregistrée dans notre système :</p>
        
        <div class="address-details">
            <strong><?php echo htmlspecialchars($adresse['nom']); ?></strong><br>
            <?php echo htmlspecialchars($adresse['adresse']); ?><br>
            <?php if (!empty($adresse['complement'])): ?>
                <?php echo htmlspecialchars($adresse['complement']); ?><br>
            <?php endif; ?>
            <?php echo htmlspecialchars($adresse['code_postal'] . ' ' . $adresse['ville']); ?><br>
            <?php echo htmlspecialchars($adresse['pays']); ?><br>
            Tél: <?php echo htmlspecialchars($adresse['telephone']); ?><br>
            Email: <?php echo htmlspecialchars($adresse['email']); ?>
        </div>
        
        <p>Souhaitez-vous utiliser cette adresse pour la livraison ?</p>
        
        <div class="buttons">
            <form action="livraison.php" method="POST" style="flex: 1;">
                <input type="hidden" name="confirmer_adresse" value="1">
                <button type="submit" class="btn btn-confirm">
                    <i class="fas fa-check"></i> Oui, utiliser cette adresse
                </button>
            </form>
            
            <a href="livraison.html" class="btn btn-modify">
                <i class="fas fa-edit"></i> Non, modifier l'adresse
            </a>
        </div>
    </div>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>