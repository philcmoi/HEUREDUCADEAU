<?php
// paiement-reussi-email.php - Page de succès de paiement avec envoi automatique de facture

session_start();
require_once 'config.php';
require_once 'fonctions_email.php';

// Récupérer les paramètres PayPal
$orderId = $_GET['token'] ?? $_GET['paymentId'] ?? '';
$payerId = $_GET['PayerID'] ?? '';

$pdo = getPDOConnection();

// ============================================
// ENVOI AUTOMATIQUE DE LA FACTURE
// ============================================
$email_envoye = false;
$commande_id = null;
$commande_details = [];
$total = 0;

if ($pdo && $orderId) {
    try {
        // 1. Récupérer l'ID de commande à partir de la référence PayPal
        $stmt = $pdo->prepare("
            SELECT id_commande 
            FROM commandes 
            WHERE reference_paiement = ? 
               OR reference_paypal = ?
            ORDER BY date_commande DESC 
            LIMIT 1
        ");
        $stmt->execute([$orderId, $orderId]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande) {
            $commande_id = $commande['id_commande'];
            
            // Stocker en session pour téléchargement ultérieur
            $_SESSION['commande_recente'] = $commande_id;
            
            // 2. Envoyer la facture par email
            $email_envoye = envoyerFactureEmail($pdo, $commande_id);
            
            // 3. Journaliser
            error_log("Tentative d'envoi email pour commande $commande_id: " . ($email_envoye ? 'Succès' : 'Échec'));
            
            // 4. Récupérer les détails pour affichage
            $stmt_details = $pdo->prepare("
                SELECT c.*, ci.nom_produit, ci.quantite, ci.prix_unitaire_ttc 
                FROM commandes c
                LEFT JOIN commande_items ci ON c.id_commande = ci.id_commande
                WHERE c.id_commande = ?
            ");
            $stmt_details->execute([$commande_id]);
            $commande_details = $stmt_details->fetchAll();
            
            if ($commande_details) {
                foreach ($commande_details as $item) {
                    $total += $item['quantite'] * $item['prix_unitaire_ttc'];
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'envoi automatique: " . $e->getMessage());
    }
}
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
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .confirmation-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
        }
        .confirmation-card.success {
            border-top: 5px solid #28a745;
        }
        .confirmation-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .confirmation-card h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .confirmation-message {
            color: #666;
            font-size: 18px;
            margin-bottom: 30px;
        }
        .email-status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .email-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .email-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        .email-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .download-link {
            display: inline-block;
            margin-top: 10px;
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .download-link:hover {
            background-color: #0056b3;
        }
        .confirmation-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        .commande-resume {
            margin-top: 20px;
        }
        .commande-resume h3 {
            color: #333;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .commande-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .commande-total {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            font-size: 18px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #dee2e6;
        }
        .confirmation-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin: 30px 0;
        }
        .btn {
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        .confirmation-info {
            color: #666;
            font-size: 14px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
        }
        .confirmation-info p {
            margin: 5px 0;
        }
        .confirmation-info i {
            margin-right: 8px;
            color: #007bff;
        }
    </style>
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
            
            <!-- Status de l'email -->
            <div class="email-status <?php 
                echo $email_envoye ? 'email-success' : 
                    ($commande_id ? 'email-warning' : 'email-error'); 
            ?>">
                <?php if ($email_envoye): ?>
                    <i class="fas fa-envelope"></i> 
                    Un email de confirmation avec votre facture vous a été envoyé à l'adresse associée à votre commande.
                <?php elseif ($commande_id): ?>
                    <i class="fas fa-exclamation-triangle"></i> 
                    L'email de confirmation n'a pas pu être envoyé, mais votre commande est bien enregistrée.
                    <br>
                    <a href="telecharger-facture.php?commande_id=<?php echo $commande_id; ?>" class="download-link">
                        <i class="fas fa-download"></i> Télécharger ma facture
                    </a>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i> 
                    Une erreur est survenue. Veuillez contacter le service client.
                <?php endif; ?>
            </div>
            
            <div class="confirmation-details">
                <?php if ($orderId): ?>
                <p><strong>Référence PayPal :</strong> <?php echo htmlspecialchars($orderId); ?></p>
                <?php endif; ?>
                
                <?php if ($commande_id && !empty($commande_details)): ?>
                <p><strong>N° de commande :</strong> <?php echo htmlspecialchars($commande_details[0]['numero_commande'] ?? ''); ?></p>
                <?php endif; ?>
                
                <p><strong>Date :</strong> <?php echo date('d/m/Y H:i'); ?></p>
                
                <?php if (!empty($commande_details)): ?>
                <div class="commande-resume">
                    <h3><i class="fas fa-box"></i> Résumé de commande</h3>
                    
                    <?php foreach ($commande_details as $item): ?>
                    <div class="commande-item">
                        <span><?php echo htmlspecialchars($item['nom_produit']); ?></span>
                        <span><?php echo $item['quantite']; ?> x <?php echo number_format($item['prix_unitaire_ttc'], 2); ?> €</span>
                    </div>
                    <?php endforeach; ?>
                    
                    <div class="commande-total">
                        <span>Total TTC</span>
                        <span><?php echo number_format($total, 2); ?> €</span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="confirmation-actions">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="commandes.php" class="btn btn-secondary">
                    <i class="fas fa-clipboard-list"></i> Voir mes commandes
                </a>
            </div>
            
            <div class="confirmation-info">
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
        
        // Vider le panier côté client
        localStorage.removeItem('panier');
        sessionStorage.removeItem('panier');
        
        // Enregistrer l'événement de succès pour analytics
        setTimeout(() => {
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    transaction_id: '<?php echo $orderId; ?>',
                    value: <?php echo $total; ?>,
                    currency: 'EUR'
                });
            }
        }, 1000);
    </script>
</body>
</html>