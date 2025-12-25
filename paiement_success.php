<?php
// paiement_success.php
$idCommande = $_GET['commande'] ?? '';
$reference = $_GET['reference'] ?? '';

// Récupérer les infos de la commande depuis la base
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi -Youki and Co</title>
    <style>
        body { 
            font-family: 'Helvetica Neue', Arial, sans-serif; 
            background-color: #f9f9f9; 
            margin: 0; 
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container { 
            max-width: 600px; 
            background: white; 
            padding: 40px; 
            border-radius: 8px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success { 
            color: #28a745; 
            font-size: 48px;
            margin-bottom: 20px;
        }
        .details {
            text-align: left;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .btn { 
            display: inline-block;
            background-color: #d40000; 
            color: white; 
            padding: 12px 30px; 
            text-decoration: none; 
            border-radius: 4px; 
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">✅</div>
        <h1>Paiement Réussi !</h1>
        
        <p>Votre paiement PayPal a été traité avec succès.</p>
        
        <div class="details">
            <p><strong>Commande :</strong> #<?= htmlspecialchars($idCommande) ?></p>
            <p><strong>Référence PayPal :</strong> <?= htmlspecialchars($reference) ?></p>
            <p><strong>Statut :</strong> Paiement confirmé</p>
        </div>
        
        <p>Vous recevrez un email de confirmation sous peu.</p>
        <p>Votre commande est en cours de préparation.</p>
        
        <a href="index.html" class="btn">Retour à l'accueil</a>
        <a href="commande_details.php?id=<?= $idCommande ?>" class="btn" style="background: #007bff; margin-left: 10px;">
            Voir ma commande
        </a>
    </div>
</body>
</html>