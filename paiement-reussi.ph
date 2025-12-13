<?php
session_start();
require_once 'config.php';

// Récupérer les paramètres PayPal
$orderId = $_GET['token'] ?? $_GET['paymentId'] ?? '';
$payerId = $_GET['PayerID'] ?? '';

$pdo = getPDOConnection();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Réussi - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/paiement.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-card success">
            <div class="confirmation-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Paiement Réussi !</h1>
            <p class="confirmation-message">
                Votre commande a été traitée avec succès.
            </p>
            
            <div class="confirmation-details">
                <?php if ($orderId): ?>
                <p><strong>Référence PayPal :</strong> <?php echo htmlspecialchars($orderId); ?></p>
                <?php endif; ?>
                
                <p><strong>Date :</strong> <?php echo date('d/m/Y H:i'); ?></p>
                
                <?php
                // Récupérer les infos de la commande
                if ($pdo && $orderId) {
                    $stmt = $pdo->prepare("
                        SELECT c.*, ci.nom_produit, ci.quantite, ci.prix_unitaire_ttc 
                        FROM commandes c
                        LEFT JOIN commande_items ci ON c.id_commande = ci.id_commande
                        WHERE c.reference_paiement = ?
                    ");
                    $stmt->execute([$orderId]);
                    $commandes = $stmt->fetchAll();
                    
                    if ($commandes) {
                        echo '<div class="commande-resume">';
                        echo '<h3><i class="fas fa-box"></i> Résumé de commande</h3>';
                        
                        $total = 0;
                        foreach ($commandes as $item) {
                            $itemTotal = $item['quantite'] * $item['prix_unitaire_ttc'];
                            $total += $itemTotal;
                            
                            echo '<div class="commande-item">';
                            echo '<span>' . htmlspecialchars($item['nom_produit']) . '</span>';
                            echo '<span>' . $item['quantite'] . ' x ' . number_format($item['prix_unitaire_ttc'], 2) . ' €</span>';
                            echo '</div>';
                        }
                        
                        echo '<div class="commande-total">';
                        echo '<span>Total</span>';
                        echo '<span>' . number_format($total, 2) . ' €</span>';
                        echo '</div>';
                        echo '</div>';
                    }
                }
                ?>
            </div>
            
            <div class="confirmation-actions">
                <a href="index.html" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="commandes.html" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i> Voir mes commandes
                </a>
            </div>
            
            <div class="confirmation-info">
                <p><i class="fas fa-envelope"></i> Un email de confirmation vous a été envoyé.</p>
                <p><i class="fas fa-truck"></i> Livraison estimée : 3-5 jours ouvrés</p>
                <p><i class="fas fa-headset"></i> Questions ? Contactez-nous : contact@heureducadeau.fr</p>
            </div>
        </div>
    </div>
    
    <script>
        // Mettre à jour le compteur du panier
        if (typeof mettreAJourCompteur === 'function') {
            mettreAJourCompteur();
        }
        
        // Enregistrer l'événement de succès
        setTimeout(() => {
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    transaction_id: '<?php echo $orderId; ?>',
                    value: <?php echo $total ?? 0; ?>,
                    currency: 'EUR'
                });
            }
        }, 1000);
    </script>
</body>
</html>