<?php
// admin_produits_clean.php - Version simplifiée mais fonctionnelle

// Activer l'affichage des erreurs (à enlever en production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Inclusion de la protection
require_once 'admin_protection.php';

// Connexion DB
$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'Philippe';
$password_db = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer l'action
$action = $_GET['action'] ?? 'list';

// Récupérer les catégories
$categories = $pdo->query("SELECT * FROM categories WHERE active = 1 ORDER BY nom")->fetchAll();

// Traitement du formulaire d'ajout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    
    $nom = $_POST['nom'] ?? '';
    $prix_ht = floatval($_POST['prix_ht'] ?? 0);
    $quantite_stock = intval($_POST['quantite_stock'] ?? 0);
    $id_categorie = intval($_POST['id_categorie'] ?? 0);
    
    // Générer slug et référence
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $nom), '-'));
    $reference = 'PROD-' . time();
    
    try {
        $sql = "INSERT INTO produits (
                    reference, nom, slug, prix_ht, quantite_stock, id_categorie, statut
                ) VALUES (
                    :reference, :nom, :slug, :prix_ht, :quantite_stock, :id_categorie, 'actif'
                )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'reference' => $reference,
            'nom' => $nom,
            'slug' => $slug,
            'prix_ht' => $prix_ht,
            'quantite_stock' => $quantite_stock,
            'id_categorie' => $id_categorie
        ]);
        
        $success = "Produit ajouté avec succès !";
    } catch(Exception $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestion des produits</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { background: linear-gradient(135deg, #6a11cb, #2575fc); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .nav { background: white; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .nav a { color: #333; text-decoration: none; padding: 10px 20px; margin-right: 10px; }
        .nav a.active { background: #6a11cb; color: white; border-radius: 5px; }
        .form-container { background: white; border-radius: 10px; padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; }
        .btn { background: #4CAF50; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { background: #45a049; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Gestion des produits</h1>
            <p>Bienvenue, <?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></p>
        </div>
        
        <div class="nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="admin_produits_clean.php?action=list" class="<?= $action == 'list' ? 'active' : '' ?>">Liste</a>
            <a href="admin_produits_clean.php?action=add" class="<?= $action == 'add' ? 'active' : '' ?>">Ajouter</a>
        </div>
        
        <?php if ($action == 'add'): ?>
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">Ajouter un produit</h2>
                
                <?php if (isset($success)): ?>
                    <div class="success"><?= $success ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="error"><?= $error ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="form-group">
                        <label>Nom du produit *</label>
                        <input type="text" name="nom" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Prix HT (€) *</label>
                        <input type="number" name="prix_ht" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantité en stock *</label>
                        <input type="number" name="quantite_stock" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Catégorie *</label>
                        <select name="id_categorie" required>
                            <option value="">Sélectionner</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id_categorie'] ?>"><?= htmlspecialchars($cat['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Ajouter le produit</button>
                </form>
            </div>
            
        <?php elseif ($action == 'list'): ?>
            <?php
            // Récupérer la liste des produits
            $produits = $pdo->query("
                SELECT p.*, c.nom as categorie_nom 
                FROM produits p 
                LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
                ORDER BY p.id_produit DESC
            ")->fetchAll();
            ?>
            
            <div style="background: white; border-radius: 10px; padding: 20px;">
                <h2 style="margin-bottom: 20px;">Liste des produits</h2>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 10px; text-align: left;">ID</th>
                            <th style="padding: 10px; text-align: left;">Référence</th>
                            <th style="padding: 10px; text-align: left;">Nom</th>
                            <th style="padding: 10px; text-align: left;">Catégorie</th>
                            <th style="padding: 10px; text-align: left;">Prix</th>
                            <th style="padding: 10px; text-align: left;">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($produits as $p): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td style="padding: 10px;">#<?= $p['id_produit'] ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($p['reference']) ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($p['nom']) ?></td>
                            <td style="padding: 10px;"><?= htmlspecialchars($p['categorie_nom'] ?? '-') ?></td>
                            <td style="padding: 10px;"><?= number_format($p['prix_ttc'] ?? $p['prix_ht'] * 1.2, 2) ?> €</td>
                            <td style="padding: 10px;"><?= $p['quantite_stock'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>