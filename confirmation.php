[file name]: confirmation.php
[file content begin]
<?php
session_start();

// Vérifier si une commande a été créée
$commande_numero = $_GET['cmd'] ?? null;
$commande_data = $_SESSION['commande_confirmée'] ?? null;

if (!$commande_numero && !$commande_data) {
    header('Location: panier.php');
    exit();
}

// Nettoyer la session après confirmation
$adresse_livraison = $_SESSION['commande']['adresse_livraison'] ?? [];
$total = $_SESSION['commande']['total'] ?? 0;
$mode_livraison = $_SESSION['commande']['livraison']['mode'] ?? 'standard';

// Nettoyer les données de session
unset($_SESSION['commande']);
unset($_SESSION['panier']);
unset($_SESSION['livraison_data']);
unset($_SESSION['checkout_authorized']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Confirmation de commande - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .confirmation-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .success-icon {
            color: #38a169;
            font-size: 60px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 20px;
        }
        
        .commande-numero {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 18px;
            font-weight: bold;
            color: #5a67d8;
        }
        
        .adresse-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: left;
        }
        
        .total-box {
            background: #e6fffa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 20px;
            font-weight: bold;
            color: #234e52;
        }
        
        .btn {
            display: inline-block;
            background: #5a67d8;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #4c51bf;
            transform: translateY(-2px);
        }
        
        .info-box {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Commande confirmée !</h1>
        <p>Merci pour votre commande. Nous vous avons envoyé un email de confirmation.</p>
        
        <div class="commande-numero">
            Numéro de commande : <?php echo htmlspecialchars($commande_numero ?: 'CMD-' . time()); ?>
        </div>
        
        <?php if (!empty($adresse_livraison)): ?>
        <div class="adresse-box">
            <h3><i class="fas fa-truck"></i> Adresse de livraison</h3>
            <p><strong><?php echo htmlspecialchars(($adresse_livraison['prenom'] ?? '') . ' ' . ($adresse_livraison['nom'] ?? '')); ?></strong></p>
            <p><?php echo htmlspecialchars($adresse_livraison['adresse'] ?? ''); ?></p>
            <?php if (!empty($adresse_livraison['complement'])): ?>
            <p><?php echo htmlspecialchars($adresse_livraison['complement']); ?></p>
            <?php endif; ?>
            <p><?php echo htmlspecialchars(($adresse_livraison['code_postal'] ?? '') . ' ' . ($adresse_livraison['ville'] ?? '')); ?></p>
            <p><?php echo htmlspecialchars($adresse_livraison['pays'] ?? 'France'); ?></p>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adresse_livraison['email'] ?? ''); ?></p>
        </div>
        <?php endif; ?>
        
        <div class="total-box">
            <p>Total payé : <?php echo number_format($total, 2, ',', ' '); ?> €</p>
            <p>Mode de livraison : <?php echo htmlspecialchars(ucfirst($mode_livraison)); ?></p>
        </div>
        
        <div class="info-box">
            <p><i class="fas fa-info-circle"></i> Vous recevrez un email de suivi lorsque votre commande sera expédiée.</p>
        </div>
        
        <a href="index.php" class="btn">
            <i class="fas fa-home"></i> Retour à l'accueil
        </a>
    </div>
</body>
</html>
[file content end]