<?php
session_start();

// Vérifier si une commande a été validée
if (!isset($_SESSION['commande_validee'])) {
    header('Location: index.php');
    exit;
}

$commande = $_SESSION['commande_validee'];

// Effacer les données de la session après affichage
unset($_SESSION['commande_validee']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de commande - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="confirmation-page">
        <div class="container">
            <div class="confirmation-card">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                
                <h1>Commande confirmée !</h1>
                <p class="confirmation-message">
                    Merci pour votre achat. Votre commande a été enregistrée avec succès.
                </p>
                
                <div class="order-details">
                    <div class="detail-row">
                        <span class="detail-label">Numéro de commande :</span>
                        <span class="detail-value"><?php echo htmlspecialchars($commande['numero_commande']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date :</span>
                        <span class="detail-value"><?php echo htmlspecialchars($commande['date']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Montant total :</span>
                        <span class="detail-value"><?php echo number_format($commande['total'], 2, ',', ' '); ?> €</span>
                    </div>
                </div>
                
                <div class="confirmation-info">
                    <h3><i class="fas fa-info-circle"></i> Prochaines étapes</h3>
                    <ul>
                        <li>Vous recevrez un email de confirmation sous peu</li>
                        <li>Vous pouvez suivre votre commande depuis votre espace client</li>
                        <li>Notre équipe prépare votre commande pour expédition</li>
                        <li>Pour toute question, contactez notre service client</li>
                    </ul>
                </div>
                
                <div class="confirmation-actions">
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                    <a href="commandes.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Voir mes commandes
                    </a>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>
