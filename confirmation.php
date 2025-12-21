<?php
// confirmation.php - Page de confirmation de commande HEURE DU CADEAU
session_start();
require_once 'db_config.php';

// Vérifier la connexion BDD
$pdo = getDB();
if (!$pdo) {
    die("Erreur de connexion à la base de données");
}

// Récupérer le numéro de commande
$numero_commande = $_GET['cmd'] ?? $_SESSION['commande_confirmée']['numero_commande'] ?? '';

if (empty($numero_commande)) {
    header('Location: index.php');
    exit;
}

// Récupérer les infos de la commande depuis la session ou la BDD
$commande_info = null;
$client_info = null;
$items_commande = [];

if (isset($_SESSION['commande_confirmée'])) {
    $commande_info = $_SESSION['commande_confirmée'];
    $client_id = $commande_info['client_id'] ?? 0;
    
    // Récupérer les infos client depuis la BDD
    if ($client_id) {
        try {
            $sql_client = "SELECT nom, prenom, email FROM clients WHERE id_client = ?";
            $stmt_client = $pdo->prepare($sql_client);
            $stmt_client->execute([$client_id]);
            $client_info = $stmt_client->fetch();
        } catch (PDOException $e) {
            error_log("Erreur récupération client: " . $e->getMessage());
        }
    }
    
    // Récupérer les items de la commande depuis la BDD
    if (isset($commande_info['commande_id'])) {
        try {
            $sql_items = "SELECT ci.*, p.reference, p.image 
                         FROM commande_items ci 
                         LEFT JOIN produits p ON ci.id_produit = p.id_produit 
                         WHERE ci.id_commande = ?";
            $stmt_items = $pdo->prepare($sql_items);
            $stmt_items->execute([$commande_info['commande_id']]);
            $items_commande = $stmt_items->fetchAll();
        } catch (PDOException $e) {
            error_log("Erreur récupération items: " . $e->getMessage());
        }
    }
} else {
    // Fallback : récupérer depuis la BDD
    try {
        $sql = "SELECT c.*, cl.email, cl.nom, cl.prenom, cl.telephone 
                FROM commandes c 
                LEFT JOIN clients cl ON c.id_client = cl.id_client 
                WHERE c.numero_commande = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$numero_commande]);
        $commande_info = $stmt->fetch();
        
        if (!$commande_info) {
            header('Location: index.php');
            exit;
        }
        
        $client_info = [
            'nom' => $commande_info['nom'],
            'prenom' => $commande_info['prenom'],
            'email' => $commande_info['email'],
            'telephone' => $commande_info['telephone']
        ];
        
        // Récupérer les items de la commande
        $sql_items = "SELECT ci.*, p.reference, p.nom as produit_nom, p.image 
                     FROM commande_items ci 
                     LEFT JOIN produits p ON ci.id_produit = p.id_produit 
                     WHERE ci.id_commande = ?";
        $stmt_items = $pdo->prepare($sql_items);
        $stmt_items->execute([$commande_info['id_commande']]);
        $items_commande = $stmt_items->fetchAll();
        
    } catch (PDOException $e) {
        error_log("Erreur récupération commande: " . $e->getMessage());
        header('Location: index.php');
        exit;
    }
}

// Récupérer l'adresse de livraison
$adresse_livraison = null;
if (isset($commande_info['id_commande'])) {
    try {
        $sql_adresse = "SELECT a.* 
                       FROM adresses a 
                       INNER JOIN commandes c ON a.id_adresse = c.id_adresse_livraison 
                       WHERE c.id_commande = ?";
        $stmt_adresse = $pdo->prepare($sql_adresse);
        $stmt_adresse->execute([$commande_info['id_commande']]);
        $adresse_livraison = $stmt_adresse->fetch();
    } catch (PDOException $e) {
        error_log("Erreur récupération adresse: " . $e->getMessage());
    }
}

// Formater les dates
$date_commande = isset($commande_info['date_commande']) ? 
    date('d/m/Y à H:i', strtotime($commande_info['date_commande'])) : 
    date('d/m/Y à H:i');

// Nettoyer la session (sauf client_temp si on veut garder les infos)
if (isset($_SESSION['commande_confirmée'])) {
    unset($_SESSION['commande_confirmée']);
}
if (isset($_SESSION['commande'])) {
    unset($_SESSION['commande']);
}
if (isset($_SESSION['adresse_livraison'])) {
    unset($_SESSION['adresse_livraison']);
}

// Log de la confirmation
logAction('info', 'Confirmation de commande affichée', [
    'numero_commande' => $numero_commande,
    'client_email' => $client_info['email'] ?? ''
]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de Commande - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
            position: relative;
        }
        
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .header h1 {
            margin: 0;
            font-size: 36px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.9;
            margin: 10px 0 0 0;
        }
        
        .content {
            padding: 40px;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 2px dashed #dee2e6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            transition: transform 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        .info-card h3 {
            color: #495057;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-card h3 i {
            color: #667eea;
        }
        
        .info-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            flex: 1;
        }
        
        .info-value {
            flex: 2;
            text-align: right;
            color: #212529;
            font-weight: 500;
        }
        
        .total-amount {
            font-size: 28px;
            font-weight: 700;
            color: #28a745;
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border: 2px solid #28a745;
        }
        
        .order-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border-radius: 50px;
            display: inline-block;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 35px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 2px solid transparent;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: #667eea;
            border-color: #667eea;
        }
        
        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
        }
        
        .products-list {
            margin-top: 30px;
        }
        
        .product-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .product-item:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 20px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        
        .product-details {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: #212529;
            margin-bottom: 5px;
        }
        
        .product-meta {
            display: flex;
            justify-content: space-between;
            color: #6c757d;
            font-size: 14px;
        }
        
        .footer-note {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 20px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 28px;
            }
            
            .content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .order-number {
                font-size: 20px;
                padding: 12px 24px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .success-icon {
                font-size: 60px;
            }
            
            .total-amount {
                font-size: 24px;
                padding: 15px;
            }
            
            .product-item {
                flex-direction: column;
                text-align: center;
            }
            
            .product-image {
                margin-right: 0;
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">🎉</div>
            <h1>Commande Confirmée !</h1>
            <p>Merci pour votre confiance. Votre commande a été enregistrée avec succès.</p>
            
            <div class="order-number">
                <?php echo htmlspecialchars($numero_commande); ?>
            </div>
        </div>
        
        <div class="content">
            <div class="order-summary">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h2 style="color: #495057; margin: 0;">
                        <i class="fas fa-receipt"></i> Récapitulatif de votre commande
                    </h2>
                    <p style="color: #6c757d; margin: 10px 0 0 0;">
                        Commandé le <?php echo $date_commande; ?>
                    </p>
                </div>
                
                <?php if (!empty($items_commande)): ?>
                <div class="products-list">
                    <h3><i class="fas fa-box-open"></i> Articles commandés</h3>
                    <?php foreach ($items_commande as $item): ?>
                    <div class="product-item">
                        <div class="product-image">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nom_produit'] ?? $item['produit_nom']); ?>">
                            <?php else: ?>
                                <i class="fas fa-gift" style="font-size: 30px; color: #667eea;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="product-details">
                            <div class="product-name">
                                <?php echo htmlspecialchars($item['nom_produit'] ?? $item['reference']); ?>
                            </div>
                            <div class="product-meta">
                                <span>Quantité: <?php echo htmlspecialchars($item['quantite']); ?></span>
                                <span>Prix unitaire: <?php echo number_format($item['prix_unitaire_ttc'], 2, ',', ' '); ?> €</span>
                                <span>Total: <?php echo number_format($item['quantite'] * $item['prix_unitaire_ttc'], 2, ',', ' '); ?> €</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="total-amount">
                    Total: <?php echo number_format($commande_info['total'] ?? $commande_info['total_ttc'] ?? 0, 2, ',', ' '); ?> €
                </div>
            </div>
            
            <div class="info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-user"></i> Informations client</h3>
                    <?php if ($client_info): ?>
                    <div class="info-item">
                        <span class="info-label">Nom :</span>
                        <span class="info-value"><?php echo htmlspecialchars($client_info['prenom'] . ' ' . $client_info['nom']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email :</span>
                        <span class="info-value"><?php echo htmlspecialchars($client_info['email']); ?></span>
                    </div>
                    <?php if (!empty($client_info['telephone'])): ?>
                    <div class="info-item">
                        <span class="info-label">Téléphone :</span>
                        <span class="info-value"><?php echo htmlspecialchars($client_info['telephone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-truck"></i> Livraison</h3>
                    <?php if ($adresse_livraison): ?>
                    <div class="info-item">
                        <span class="info-label">Adresse :</span>
                        <span class="info-value">
                            <?php echo htmlspecialchars($adresse_livraison['adresse']); ?><br>
                            <?php if (!empty($adresse_livraison['complement'])): ?>
                            <?php echo htmlspecialchars($adresse_livraison['complement']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($adresse_livraison['code_postal'] . ' ' . $adresse_livraison['ville']); ?><br>
                            <?php echo htmlspecialchars($adresse_livraison['pays']); ?>
                        </span>
                    </div>
                    <?php if (!empty($adresse_livraison['telephone'])): ?>
                    <div class="info-item">
                        <span class="info-label">Téléphone :</span>
                        <span class="info-value"><?php echo htmlspecialchars($adresse_livraison['telephone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="info-item">
                        <span class="info-label">Statut :</span>
                        <span class="info-value" style="color: #28a745;">
                            <i class="fas fa-clock"></i> En préparation
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-card">
                    <h3><i class="fas fa-info-circle"></i> Informations complémentaires</h3>
                    <div class="info-item">
                        <span class="info-label">Numéro commande :</span>
                        <span class="info-value"><?php echo htmlspecialchars($numero_commande); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Date de commande :</span>
                        <span class="info-value"><?php echo $date_commande; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Mode de paiement :</span>
                        <span class="info-value">Carte bancaire</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Statut :</span>
                        <span class="info-value" style="color: #28a745; font-weight: bold;">
                            <i class="fas fa-check-circle"></i> Confirmée
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <a href="index.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
                <a href="produits.html" class="btn btn-secondary">
                    <i class="fas fa-shopping-bag"></i> Continuer mes achats
                </a>
            </div>
            
            <div class="footer-note">
                <p>
                    <i class="fas fa-envelope"></i> Un email de confirmation vous a été envoyé à 
                    <strong><?php echo htmlspecialchars($client_info['email'] ?? ''); ?></strong>
                </p>
                <p>
                    <i class="fas fa-headset"></i> Pour toute question concernant votre commande, 
                    contactez-nous à contact@heureducadeau.fr
                </p>
                <p style="margin-top: 20px; font-size: 12px; color: #adb5bd;">
                    <i class="fas fa-lock"></i> Votre commande est sécurisée. 
                    Nous ne partagerons jamais vos informations personnelles.
                </p>
            </div>
        </div>
    </div>
    
    <script>
        // Animation supplémentaire
        document.addEventListener('DOMContentLoaded', function() {
            // Sauvegarder le numéro de commande dans le localStorage pour référence
            const orderNumber = '<?php echo $numero_commande; ?>';
            if (orderNumber) {
                localStorage.setItem('last_order', orderNumber);
            }
            
            // Ajouter un effet de confetti virtuel
            const confetti = document.createElement('div');
            confetti.innerHTML = '🎉';
            confetti.style.position = 'fixed';
            confetti.style.top = '0';
            confetti.style.left = '0';
            confetti.style.width = '100%';
            confetti.style.height = '100%';
            confetti.style.pointerEvents = 'none';
            confetti.style.zIndex = '9999';
            confetti.style.opacity = '0';
            confetti.style.transition = 'opacity 2s';
            document.body.appendChild(confetti);
            
            setTimeout(() => {
                confetti.style.opacity = '0.3';
                setTimeout(() => {
                    confetti.style.opacity = '0';
                    setTimeout(() => {
                        document.body.removeChild(confetti);
                    }, 2000);
                }, 1000);
            }, 500);
        });
    </script>
</body>
</html>