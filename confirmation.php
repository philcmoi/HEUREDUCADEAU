<?php
session_start();

$id_commande = $_GET['commande'] ?? null;

if (!$id_commande) {
    header('Location: index.html');
    exit;
}

// Récupérer les informations de la commande
try {
    $pdo = new PDO("mysql:host=localhost;dbname=heureducadeau;charset=utf8", "Philippe", "l@99339R");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT 
                c.id_commande,
                c.numero_commande,
                c.total_ttc,
                c.date_commande,
                c.statut_paiement,
                c.mode_paiement,
                cl.email,
                cl.prenom,
                cl.nom
            FROM commandes c
            JOIN clients cl ON c.id_client = cl.id_client
            WHERE c.id_commande = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_commande]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        die("Commande non trouvée");
    }
    
    // Récupérer les articles
    $sql_items = "SELECT nom_produit, quantite, prix_unitaire_ttc
                  FROM commande_items
                  WHERE id_commande = ?";
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute([$id_commande]);
    $articles = $stmt_items->fetchAll();
    
} catch (PDOException $e) {
    die("Erreur: " . $e->getMessage());
}

// Vider le panier
unset($_SESSION['panier']);
unset($_SESSION['panier_id']);
unset($_SESSION['adresse_livraison']);
unset($_SESSION['commande']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation de commande</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9f9f9; margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { max-width: 600px; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); text-align: center; }
        .success { color: #28a745; font-size: 64px; margin-bottom: 20px; }
        h1 { color: #333; margin-bottom: 20px; }
        .details { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: left; margin: 20px 0; }
        .btn { display: inline-block; background: #5a67d8; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        .btn:hover { background: #4c51bf; }
    </style>
</head>
<body>
    <div class="container">
        <div class="success">✓</div>
        <h1>Merci pour votre commande !</h1>
        
        <div class="details">
            <p><strong>Numéro de commande :</strong> <?= htmlspecialchars($commande['numero_commande']) ?></p>
            <p><strong>Date :</strong> <?= date('d/m/Y H:i', strtotime($commande['date_commande'])) ?></p>
            <p><strong>Montant total :</strong> <?= number_format($commande['total_ttc'], 2, ',', ' ') ?> €</p>
            <p><strong>Mode de paiement :</strong> 
                <?= $commande['mode_paiement'] === 'paypal' ? 'PayPal' : 'Carte bancaire' ?>
            </p>
            <p><strong>Statut :</strong> Confirmée</p>
        </div>
        
        <p>Un email de confirmation vous a été envoyé à <strong><?= htmlspecialchars($commande['email']) ?></strong></p>
        
        <a href="index.html" class="btn">Retour à l'accueil</a>
    </div>
</body>
</html>