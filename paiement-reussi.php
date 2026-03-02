<?php
// ============================================
// PAGE DE CONFIRMATION DE PAIEMENT RÉUSSI
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// Récupérer les paramètres
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$token = $_GET['token'] ?? '';

// Connexion BDD
$pdo = getPDOConnection();

// Récupérer les informations de la commande
$commande = null;
$items = [];

if ($pdo && $commande_id > 0) {
    try {
        // Récupérer la commande
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.nom, cl.prenom
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ?
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch();
        
        // Récupérer les articles
        if ($commande) {
            $stmt_items = $pdo->prepare("
                SELECT * FROM commande_items 
                WHERE id_commande = ?
            ");
            $stmt_items->execute([$commande_id]);
            $items = $stmt_items->fetchAll();
        }
        
        // ========== VIDER LE PANIER ICI ==========
        // Vider complètement le panier en session
        $_SESSION[SESSION_KEY_PANIER] = [];
        
        // Supprimer toutes les clés liées au panier/commande
        unset($_SESSION[SESSION_KEY_PANIER_ID]);
        unset($_SESSION[SESSION_KEY_CHECKOUT]);
        unset($_SESSION[SESSION_KEY_COMMANDE]);
        
        // Supprimer les clés temporaires
        unset($_SESSION['panier_temp']);
        unset($_SESSION['checkout_data']);
        unset($_SESSION['commande_data']);
        
        // Nettoyer les flags PayPal
        cleanPayPalFlags();
        
        // Régénérer l'ID de session
        session_regenerate_id(true);
        
        // Log de confirmation
        error_log("PANIER VIDÉ DANS paiement-reussi.php - Commande #" . $commande_id);
        
    } catch (Exception $e) {
        error_log("Erreur récupération commande: " . $e->getMessage());
    }
}

// Si pas de commande, rediriger
if (!$commande) {
    header('Location: index.html');
    exit;
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirmation-container {
            max-width: 700px;
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
            color: #27ae60;
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
        .confirmation-details {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
            text-align: left;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #7f8c8d;
            font-weight: 500;
        }
        .detail-value {
            color: #2c3e50;
            font-weight: 700;
        }
        .commande-resume {
            margin-top: 20px;
            border-top: 2px dashed #e0e0e0;
            padding-top: 20px;
        }
        .commande-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }
        .commande-total {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #e0e0e0;
            font-weight: 800;
            font-size: 1.3rem;
            color: #e74c3c;
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
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
        }
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(39,174,96,0.3);
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
            color: #27ae60;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Paiement Réussi !</h1>
            <p class="confirmation-message">
                Merci pour votre commande. Votre paiement a été traité avec succès.
            </p>
            
            <div class="confirmation-details">
                <div class="detail-row">
                    <span class="detail-label">Numéro de commande</span>
                    <span class="detail-value"><?= htmlspecialchars($commande['numero_commande'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?= date('d/m/Y H:i', strtotime($commande['date_commande'] ?? 'now')) ?></span>
                </div>
                <?php if ($token): ?>
                <div class="detail-row">
                    <span class="detail-label">Référence PayPal</span>
                    <span class="detail-value"><?= htmlspecialchars($token) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($items)): ?>
                <div class="commande-resume">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;"><i class="fas fa-box"></i> Résumé de la commande</h3>
                    <?php 
                    $total = 0;
                    foreach ($items as $item): 
                        $itemTotal = $item['quantite'] * $item['prix_unitaire_ttc'];
                        $total += $itemTotal;
                    ?>
                    <div class="commande-item">
                        <span><?= htmlspecialchars($item['nom_produit']) ?> x<?= $item['quantite'] ?></span>
                        <span><?= number_format($itemTotal, 2, ',', ' ') ?> €</span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="commande-item">
                        <span>Frais de livraison</span>
                        <span><?= number_format($commande['frais_livraison'] ?? 0, 2, ',', ' ') ?> €</span>
                    </div>
                    
                    <div class="commande-total">
                        <span>Total payé</span>
                        <span><?= number_format($commande['total_ttc'] ?? $total, 2, ',', ' ') ?> €</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="confirmation-actions">
                <a href="index.html" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="commandes.php" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i> Voir mes commandes
                </a>
            </div>
            
            <div class="confirmation-info">
                <p><i class="fas fa-envelope"></i> Un email de confirmation a été envoyé à <strong><?= htmlspecialchars($commande['email'] ?? 'votre adresse') ?></strong></p>
                <p><i class="fas fa-truck"></i> Livraison estimée : 3-5 jours ouvrés</p>
                <p><i class="fas fa-headset"></i> Questions ? Contactez-nous : contact@heureducadeau.fr</p>
            </div>
        </div>
    </div>
    
    <script>
        // Vider le panier côté client
        if (typeof localStorage !== 'undefined') {
            localStorage.removeItem('panier');
        }
        
        // Forcer la mise à jour du compteur panier
        setTimeout(() => {
            // Mettre à jour tous les compteurs de panier sur la page
            document.querySelectorAll('.cart-count').forEach(el => {
                el.textContent = '0';
                el.style.display = 'none';
            });
        }, 500);
        
        // Enregistrer l'événement de succès pour Google Analytics (si utilisé)
        setTimeout(() => {
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    transaction_id: '<?= $commande_id ?>',
                    value: <?= $commande['total_ttc'] ?? 0 ?>,
                    currency: 'EUR'
                });
            }
        }, 1000);
    </script>
</body>
</html>