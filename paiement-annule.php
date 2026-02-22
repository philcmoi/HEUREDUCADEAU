<?php
// ============================================
// paiement-annule.php - PAGE D'ANNULATION
// ============================================
require_once __DIR__ . '/config.php';
session_start_secure();
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
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .confirmation-container {
            max-width: 500px;
            width: 100%;
        }
        .confirmation-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            animation: slideIn 0.5s ease-out;
        }
        .confirmation-card.cancelled .confirmation-icon {
            background: #e74c3c;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .confirmation-icon {
            width: 100px;
            height: 100px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            margin: 0 auto 30px;
        }
        h1 {
            color: #e74c3c;
            font-size: 32px;
            margin-bottom: 20px;
        }
        .confirmation-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .confirmation-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(52,152,219,0.4);
        }
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        .btn-secondary:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
        }
        .confirmation-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }
        .confirmation-info i {
            color: #3498db;
            margin-right: 8px;
        }
        .confirmation-info p {
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card cancelled">
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
                    <i class="fas fa-home"></i> Accueil
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