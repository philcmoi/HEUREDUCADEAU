<?php
// paiement-reussi-email.php - Version CORRIGÉE
// Page de succès de paiement avec envoi automatique de facture
// Accessible via ?commande=XXX&token=XXX

// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// VÉRIFICATION DES PARAMÈTRES
// ============================================
$commande_id = isset($_GET['commande']) ? intval($_GET['commande']) : 0;
$token = $_GET['token'] ?? '';

// Si pas de commande_id, rediriger vers l'accueil
if ($commande_id <= 0) {
    header('Location: index.html');
    exit;
}

// ============================================
// CHARGEMENT DES FICHIERS NÉCESSAIRES
// ============================================
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/fonctions_email.php';

// ============================================
// VÉRIFICATION DE LA COMMANDE EN BDD
// ============================================
$pdo = getDB();
$commande_valide = false;
$email_envoye = false;
$commande_details = [];
$total = 0;

if ($pdo) {
    try {
        // Récupérer la commande et vérifier qu'elle existe et est payée
        $stmt = $pdo->prepare("
            SELECT c.*, cl.email, cl.nom, cl.prenom 
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ? 
            AND c.statut_paiement = 'paye'
        ");
        $stmt->execute([$commande_id]);
        $commande = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($commande) {
            $commande_valide = true;
            
            // Récupérer les détails des articles
            $stmt_items = $pdo->prepare("
                SELECT * FROM commande_items 
                WHERE id_commande = ?
            ");
            $stmt_items->execute([$commande_id]);
            $commande_details = $stmt_items->fetchAll();
            
            // Calculer le total
            foreach ($commande_details as $item) {
                $total += $item['quantite'] * $item['prix_unitaire_ttc'];
            }
            
            // ============================================
            // ENVOI DE L'EMAIL (UNIQUEMENT SI PAS DÉJÀ FAIT)
            // ============================================
            // Vérifier si l'email a déjà été envoyé (par exemple dans une table de logs)
            // Pour simplifier, on vérifie juste si la commande a été créée récemment
            $date_commande = strtotime($commande['date_commande']);
            $maintenant = time();
            $diff_minutes = ($maintenant - $date_commande) / 60;
            
            // Si la commande a moins de 10 minutes, on envoie l'email
            if ($diff_minutes < 10) {
                $email_envoye = envoyerFactureEmail($pdo, $commande_id);
                error_log("Email automatique pour commande $commande_id: " . ($email_envoye ? 'Succès' : 'Échec'));
            }
            
            // Stocker en session pour référence
            $_SESSION['commande_recente'] = $commande_id;
        }
    } catch (Exception $e) {
        error_log("Erreur dans paiement-reussi-email.php: " . $e->getMessage());
    }
}

// ============================================
// SI COMMANDE NON VALIDE, REDIRECTION
// ============================================
if (!$commande_valide) {
    // Essayer de trouver la commande même si elle n'est pas marquée 'paye' (peut-être en attente)
    try {
        $stmt = $pdo->prepare("
            SELECT id_commande FROM commandes WHERE id_commande = ?
        ");
        $stmt->execute([$commande_id]);
        if ($stmt->fetch()) {
            // La commande existe mais n'est pas payée - on laisse passer avec un warning
            error_log("Commande #$commande_id affichée mais statut non payé");
        } else {
            // Commande vraiment inexistante
            header('Location: index.html');
            exit;
        }
    } catch (Exception $e) {
        header('Location: index.html');
        exit;
    }
}

// ============================================
// VIDER LE PANIER (S'IL EXISTE ENCORE)
// ============================================
unset($_SESSION['panier']);
unset($_SESSION['panier_id']);
unset($_SESSION['checkout']);
unset($_SESSION['commande_en_cours']);
session_regenerate_id(true);
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
                Votre commande a été traitée avec succès.
            </p>
            
            <!-- Status de l'email -->
            <div class="email-status <?php 
                echo $email_envoye ? 'email-success' : 
                    ($commande_valide ? 'email-warning' : 'email-error'); 
            ?>">
                <?php if ($email_envoye): ?>
                    <i class="fas fa-envelope"></i> 
                    Un email de confirmation avec votre facture vous a été envoyé.
                <?php elseif ($commande_valide): ?>
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
                <?php if ($token): ?>
                <div class="detail-row">
                    <span class="detail-label">Référence transaction</span>
                    <span class="detail-value"><?php echo htmlspecialchars(substr($token, 0, 20)); ?>...</span>
                </div>
                <?php endif; ?>
                
                <?php if ($commande_valide && isset($commande)): ?>
                <div class="detail-row">
                    <span class="detail-label">N° de commande</span>
                    <span class="detail-value"><?php echo htmlspecialchars($commande['numero_commande'] ?? ''); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label">Date</span>
                    <span class="detail-value"><?php echo date('d/m/Y H:i'); ?></span>
                </div>
                
                <?php if ($commande_valide && isset($commande)): ?>
                <div class="detail-row">
                    <span class="detail-label">Total payé</span>
                    <span class="detail-value"><?php echo number_format($commande['total_ttc'] ?? $total, 2, ',', ' '); ?> €</span>
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
                    transaction_id: '<?php echo $commande_id; ?>',
                    value: <?php echo $total; ?>,
                    currency: 'EUR'
                });
            }
        }, 1000);
    </script>
</body>
</html>