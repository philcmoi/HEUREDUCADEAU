<?php
// telecharger-facture_simple.php - Version simplifiée pour test

session_start();

$commande_id = isset($_GET['commande_id']) ? intval($_GET['commande_id']) : 0;

if ($commande_id <= 0) {
    die('ID commande invalide');
}

// Connexion directe BDD
$host = 'localhost';
$dbname = 'heureducadeau';
$username = 'Philippe';
$password = 'l@99339R';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Récupérer la commande
    $stmt = $pdo->prepare("
        SELECT c.*, cl.email, cl.nom as client_nom, cl.prenom as client_prenom
        FROM commandes c
        JOIN clients cl ON c.id_client = cl.id_client
        WHERE c.id_commande = ?
    ");
    $stmt->execute([$commande_id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        die('Commande non trouvée');
    }
    
    // Vérification simple : soit la session contient l'ID, soit on autorise pour test
    $autorise = false;
    if (isset($_SESSION['commande_recente']) && $_SESSION['commande_recente'] == $commande_id) {
        $autorise = true;
    }
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] == $commande['id_client']) {
        $autorise = true;
    }
    
    if (!$autorise) {
        // Pour test seulement - À SUPPRIMER
        $autorise = true; // TEMPORAIRE - Permettre le test
    }
    
    if (!$autorise) {
        die('Accès non autorisé');
    }
    
    // Récupérer les articles
    $stmt_items = $pdo->prepare("SELECT * FROM commande_items WHERE id_commande = ?");
    $stmt_items->execute([$commande_id]);
    $items = $stmt_items->fetchAll();
    
    // Récupérer les adresses
    $stmt_addr = $pdo->prepare("
        SELECT a.*, af.*
        FROM commandes c
        LEFT JOIN adresses a ON c.id_adresse_livraison = a.id_adresse
        LEFT JOIN adresses af ON c.id_adresse_facturation = af.id_adresse
        WHERE c.id_commande = ?
    ");
    $stmt_addr->execute([$commande_id]);
    $adresses = $stmt_addr->fetch();
    
    if ($adresses) {
        $commande = array_merge($commande, $adresses);
    }
    
    // Générer un PDF simple (sans TCPDF)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Facture <?= $commande['numero_commande'] ?></title>
        <style>
            body { font-family: Arial; margin: 40px; }
            .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 20px; }
            .facture-info { margin: 30px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            th { background: #f5f5f5; }
            .total { font-weight: bold; font-size: 1.2em; text-align: right; margin-top: 20px; }
            .footer { margin-top: 50px; text-align: center; font-size: 0.8em; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>HEURE DU CADEAU</h1>
            <h2>FACTURE</h2>
            <p>N° <?= $commande['numero_commande'] ?></p>
            <p>Date : <?= date('d/m/Y', strtotime($commande['date_commande'])) ?></p>
        </div>
        
        <div class="facture-info">
            <h3>Client</h3>
            <p>
                <?= htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']) ?><br>
                <?= htmlspecialchars($commande['email']) ?>
            </p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Produit</th>
                    <th>Référence</th>
                    <th>Quantité</th>
                    <th>Prix unitaire</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total = 0;
                foreach ($items as $item): 
                    $prix_total = $item['quantite'] * $item['prix_unitaire_ttc'];
                    $total += $prix_total;
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['nom_produit']) ?></td>
                    <td><?= htmlspecialchars($item['reference_produit']) ?></td>
                    <td><?= $item['quantite'] ?></td>
                    <td><?= number_format($item['prix_unitaire_ttc'], 2) ?> €</td>
                    <td><?= number_format($prix_total, 2) ?> €</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total">
            <p>Sous-total : <?= number_format($total, 2) ?> €</p>
            <p>Frais de livraison : <?= number_format($commande['frais_livraison'], 2) ?> €</p>
            <p style="font-size: 1.3em; color: #e74c3c;">TOTAL TTC : <?= number_format($commande['total_ttc'], 2) ?> €</p>
        </div>
        
        <div class="footer">
            <p>HEURE DU CADEAU - 123 Rue des Cadeaux, 75001 Paris</p>
            <p>contact@heureducadeau.fr - 01 23 45 67 89</p>
        </div>
        
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    die('Erreur : ' . $e->getMessage());
}
?>