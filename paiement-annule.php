<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Annulé - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/paiement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card cancelled">
            <div class="confirmation-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Paiement Annulé</h1>
            <p class="confirmation-message">
                Vous avez annulé le processus de paiement.
                Votre panier a été conservé.
            </p>
            
            <div class="confirmation-actions">
                <a href="panier.html" class="btn btn-primary">
                    <i class="fas fa-shopping-cart"></i> Retour au panier
                </a>
                <a href="index.html" class="btn btn-secondary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
            
            <div class="confirmation-info">
                <p><i class="fas fa-info-circle"></i> Aucun prélèvement n'a été effectué.</p>
                <p><i class="fas fa-shield-alt"></i> Pour toute question, contactez-nous.</p>
            </div>
        </div>
    </div>
</body>
</html>