<?php
// ============================================
// paiement-reussi.php - PAGE DE CONFIRMATION
// ============================================
require_once __DIR__ . '/config.php';
session_start_secure();

$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$messages = getSessionMessages();

$pdo = getPDOConnection();
$commande = null;
$items = [];

if ($pdo && $commande_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.nom, cl.prenom
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();
        
        if ($commande) {
            $stmt_items = $pdo->prepare("
                SELECT * FROM commande_items 
                WHERE id_commande = ?
            ");
            $stmt_items->execute([$commande_id]);
            $items = $stmt_items->fetchAll();
        }
    } catch (Exception $e) {
        error_log("Erreur récupération commande: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - HEURE DU CADEAU</title>
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
            max-width: 600px;
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
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .success-icon {
            width: 100px;
            height: 100px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 50px;
            margin: 0 auto 30px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        h1 {
            color: #28a745;
            font-size: 32px;
            margin-bottom: 20px;
        }
        .commande-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: left;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #6c757d;
            font-weight: 500;
        }
        .info-value {
            font-weight: 600;
            color: #28a745;
        }
        .items-list {
            margin-top: 20px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
            border-bottom: 1px dashed #e9ecef;
        }
        .item-name { flex: 2; text-align: left; }
        .item-qty { flex: 1; text-align: center; color: #6c757d; }
        .item-price { flex: 1; text-align: right; font-weight: 600; }
        .total-row {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #28a745;
            font-weight: bold;
            font-size: 18px;
            display: flex;
            justify-content: space-between;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        .confirmation-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid #c3e6cb;
        }
        .confirmation-message i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1>Paiement Réussi !</h1>
            
            <div class="confirmation-message">
                <i class="fas fa-check-circle"></i>
                Votre commande a été confirmée avec succès.
            </div>
            
            <?php if ($commande): ?>
            <div class="commande-info">
                <div class="info-row">
                    <span class="info-label">Numéro de commande</span>
                    <span class="info-value">#<?= htmlspecialchars($commande['numero_commande'] ?? $commande_id) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($commande['date_commande'] ?? 'now')) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Client</span>
                    <span class="info-value"><?= htmlspecialchars(($commande['prenom'] ?? '') . ' ' . ($commande['nom'] ?? '')) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?= htmlspecialchars($commande['email'] ?? '') ?></span>
                </div>
                
                <?php if (!empty($items)): ?>
                <div class="items-list">
                    <h3 style="margin: 20px 0 10px; color: #495057;">Articles commandés</h3>
                    <?php 
                    $total = 0;
                    foreach ($items as $item): 
                        $item_total = $item['quantite'] * $item['prix_unitaire_ttc'];
                        $total += $item_total;
                    ?>
                    <div class="item-row">
                        <span class="item-name"><?= htmlspecialchars($item['nom_produit']) ?></span>
                        <span class="item-qty">x<?= $item['quantite'] ?></span>
                        <span class="item-price"><?= number_format($item_total, 2, ',', ' ') ?> €</span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (isset($commande['frais_livraison']) && $commande['frais_livraison'] > 0): ?>
                    <div class="item-row">
                        <span class="item-name">Frais de livraison</span>
                        <span class="item-qty"></span>
                        <span class="item-price"><?= number_format($commande['frais_livraison'], 2, ',', ' ') ?> €</span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row">
                        <span>Total TTC</span>
                        <span style="color: #28a745;"><?= number_format($commande['total_ttc'] ?? $total, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <p style="color: #6c757d; margin: 20px 0;">
                <i class="fas fa-envelope"></i> Un email de confirmation vous a été envoyé.<br>
                <i class="fas fa-truck"></i> Votre commande sera expédiée sous 24-48h.
            </p>
            
            <div>
                <a href="index.html" class="btn btn-primary">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="commandes.html" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i> Mes commandes
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const icon = document.querySelector('.success-icon');
            icon.style.transform = 'scale(0)';
            setTimeout(() => {
                icon.style.transition = 'transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55)';
                icon.style.transform = 'scale(1)';
            }, 100);
        });
    </script>
</body>
</html>