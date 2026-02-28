<?php
// ============================================
// PAGE DE PAIEMENT ANNULÉ
// ============================================

require_once __DIR__ . '/session_verification.php';

// Nettoyer les flags PayPal
cleanPayPalFlags();

// Ajouter un message
addSessionMessage('Vous avez annulé le paiement. Votre panier a été conservé.', 'info');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Annulé - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirmation-container {
            max-width: 600px;
            width: 100%;
        }
        .confirmation-card {
            background: white;
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .confirmation-icon {
            font-size: 100px;
            color: #e74c3c;
            margin-bottom: 30px;
        }
        h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 15px;
        }
        .confirmation-message {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 40px;
        }
        .btn {
            display: inline-block;
            padding: 16px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            margin: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(231,76,60,0.3);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 2px solid #e0e0e0;
        }
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        .confirmation-info {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            text-align: left;
        }
        .confirmation-info p {
            margin: 10px 0;
            color: #7f8c8d;
        }
        .confirmation-info i {
            margin-right: 10px;
            color: #e74c3c;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Paiement Annulé</h1>
            <p class="confirmation-message">
                Vous avez annulé le processus de paiement.<br>
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
                <p><i class="fas fa-headset"></i> Email : contact@heureducadeau.fr</p>
            </div>
        </div>
    </div>
</body>
</html>