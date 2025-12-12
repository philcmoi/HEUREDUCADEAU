<?php
// admin_produits.php - CORRIGÉ et adapté à heureducadeau
// NE PAS mettre session_start() ici

// Inclure la protection
require_once 'admin_protection.php';

// ============================================
// CONFIGURATION DE LA BASE DE DONNÉES heureducadeau
// ============================================
$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'root';
$password_db = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

// ============================================
// FONCTIONS CRUD adaptées à votre structure
// ============================================

// Fonction pour récupérer tous les produits avec catégorie
function getAllProducts($pdo) {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            ORDER BY p.id_produit DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer un produit par ID
function getProductById($pdo, $id) {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fonction pour récupérer toutes les catégories
function getAllCategories($pdo) {
    $sql = "SELECT * FROM categories WHERE active = 1 ORDER BY nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour ajouter un produit
function addProduct($pdo, $data) {
    $sql = "INSERT INTO produits (
                reference, nom, slug, description, description_courte, 
                prix_ht, tva, quantite_stock, seuil_alerte, id_categorie,
                marque, poids, dimensions, materiau, couleur, made_in,
                personnalisable, ecologique, made_in_france, artisanal, exclusif,
                statut
            ) VALUES (
                :reference, :nom, :slug, :description, :description_courte,
                :prix_ht, :tva, :quantite_stock, :seuil_alerte, :id_categorie,
                :marque, :poids, :dimensions, :materiau, :couleur, :made_in,
                :personnalisable, :ecologique, :made_in_france, :artisanal, :exclusif,
                :statut
            )";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

// Fonction pour modifier un produit
function updateProduct($pdo, $id, $data) {
    $data['id_produit'] = $id;
    
    $sql = "UPDATE produits SET 
                reference = :reference,
                nom = :nom,
                slug = :slug,
                description = :description,
                description_courte = :description_courte,
                prix_ht = :prix_ht,
                tva = :tva,
                quantite_stock = :quantite_stock,
                seuil_alerte = :seuil_alerte,
                id_categorie = :id_categorie,
                marque = :marque,
                poids = :poids,
                dimensions = :dimensions,
                materiau = :materiau,
                couleur = :couleur,
                made_in = :made_in,
                personnalisable = :personnalisable,
                ecologique = :ecologique,
                made_in_france = :made_in_france,
                artisanal = :artisanal,
                exclusif = :exclusif,
                statut = :statut,
                date_modification = NOW()
            WHERE id_produit = :id_produit";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

// Fonction pour supprimer un produit
function deleteProduct($pdo, $id) {
    // Supprimer d'abord les images associées (si vous avez la table images_produits)
    try {
        $sql = "DELETE FROM images_produits WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {
        // Table images_produits peut ne pas exister
    }
    
    // Supprimer les variants associés
    try {
        $sql = "DELETE FROM variants WHERE id_produit = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
    } catch(Exception $e) {
        // Table variants peut ne pas exister
    }
    
    // Supprimer le produit
    $sql = "DELETE FROM produits WHERE id_produit = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute(['id' => $id]);
}

// Fonction pour générer un slug à partir du nom
function generateSlug($nom) {
    $slug = strtolower($nom);
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Fonction pour générer une référence automatique
function generateReference($pdo) {
    // Compter le nombre de produits et incrémenter
    $sql = "SELECT COUNT(*) as count FROM produits";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $nextNumber = $result['count'] + 1;
    return 'PROD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

// Fonction pour uploader une image
function uploadImage($file) {
    $target_dir = "uploads/produits/";
    
    // Créer le dossier s'il n'existe pas
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Vérifier si c'est une vraie image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return ['error' => 'Le fichier n\'est pas une image.'];
    }
    
    // Vérifier la taille (max 2MB)
    if ($file["size"] > 2000000) {
        return ['error' => 'L\'image est trop volumineuse (max 2MB).'];
    }
    
    // Autoriser certains formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($imageFileType, $allowed_types)) {
        return ['error' => 'Seuls JPG, JPEG, PNG, GIF et WebP sont autorisés.'];
    }
    
    // Générer un nom unique pour éviter les conflits
    $new_filename = uniqid() . '.' . $imageFileType;
    $target_file = $target_dir . $new_filename;
    
    // Uploader le fichier
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => $new_filename];
    } else {
        return ['error' => 'Erreur lors de l\'upload.'];
    }
}

// ============================================
// TRAITEMENT DES ACTIONS CRUD
// ============================================
$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Récupérer les catégories pour les formulaires
$categories = getAllCategories($pdo);

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            
            // AJOUTER UN PRODUIT
            case 'add':
                // Générer le slug
                $slug = generateSlug($_POST['nom']);
                
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'],
                    'description_courte' => $_POST['description_courte'],
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte']),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'],
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'],
                    'materiau' => $_POST['materiau'],
                    'couleur' => $_POST['couleur'],
                    'made_in' => $_POST['made_in'],
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut']
                ];
                
                if (addProduct($pdo, $data)) {
                    $lastId = $pdo->lastInsertId();
                    
                    // Gestion de l'upload d'image
                    if (!empty($_FILES['image']['name'])) {
                        $upload_result = uploadImage($_FILES['image']);
                        if (isset($upload_result['success'])) {
                            // Enregistrer l'image dans la table images_produits
                            try {
                                $sql = "INSERT INTO images_produits (id_produit, url_image, alt_text, principale) 
                                        VALUES (:id_produit, :url_image, :alt_text, 1)";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([
                                    'id_produit' => $lastId,
                                    'url_image' => $upload_result['success'],
                                    'alt_text' => $_POST['nom']
                                ]);
                            } catch(Exception $e) {
                                // Table images_produits peut ne pas exister
                            }
                        } else {
                            $error = $upload_result['error'];
                            break;
                        }
                    }
                    
                    $message = 'Produit ajouté avec succès!';
                    header('Location: admin_produits.php?action=list&message=added');
                    exit();
                } else {
                    $error = 'Erreur lors de l\'ajout du produit.';
                }
                break;
            
            // MODIFIER UN PRODUIT
            case 'edit':
                $id = intval($_POST['id_produit']);
                
                // Récupérer le produit existant
                $existingProduct = getProductById($pdo, $id);
                if (!$existingProduct) {
                    $error = 'Produit non trouvé!';
                    break;
                }
                
                // Garder le même slug si le nom n'a pas changé
                $slug = ($existingProduct['nom'] == $_POST['nom']) 
                    ? $existingProduct['slug'] 
                    : generateSlug($_POST['nom']);
                
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'],
                    'description_courte' => $_POST['description_courte'],
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte']),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'],
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'],
                    'materiau' => $_POST['materiau'],
                    'couleur' => $_POST['couleur'],
                    'made_in' => $_POST['made_in'],
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut'],
                    'id_produit' => $id
                ];
                
                if (updateProduct($pdo, $id, $data)) {
                    // Gestion de l'upload d'image
                    if (!empty($_FILES['image']['name'])) {
                        $upload_result = uploadImage($_FILES['image']);
                        if (isset($upload_result['success'])) {
                            try {
                                // Supprimer l'ancienne image si elle existe
                                $sql = "DELETE FROM images_produits WHERE id_produit = :id_produit AND principale = 1";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute(['id_produit' => $id]);
                                
                                // Ajouter la nouvelle image
                                $sql = "INSERT INTO images_produits (id_produit, url_image, alt_text, principale) 
                                        VALUES (:id_produit, :url_image, :alt_text, 1)";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([
                                    'id_produit' => $id,
                                    'url_image' => $upload_result['success'],
                                    'alt_text' => $_POST['nom']
                                ]);
                            } catch(Exception $e) {
                                // Table images_produits peut ne pas exister
                            }
                        } else {
                            $error = $upload_result['error'];
                            break;
                        }
                    }
                    
                    $message = 'Produit modifié avec succès!';
                    header('Location: admin_produits.php?action=list&message=updated');
                    exit();
                } else {
                    $error = 'Erreur lors de la modification du produit.';
                }
                break;
            
            // SUPPRIMER UN PRODUIT
            case 'delete':
                $id = intval($_POST['id_produit']);
                
                if (deleteProduct($pdo, $id)) {
                    $message = 'Produit supprimé avec succès!';
                    header('Location: admin_produits.php?action=list&message=deleted');
                    exit();
                } else {
                    $error = 'Erreur lors de la suppression du produit.';
                }
                break;
        }
    }
}

// Traitement des actions GET
if ($action === 'delete_confirm' && $id > 0) {
    $product = getProductById($pdo, $id);
    if (!$product) {
        header('Location: admin_produits.php?action=list&error=notfound');
        exit();
    }
}

// Messages depuis les redirections
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'added': $message = 'Produit ajouté avec succès!'; break;
        case 'updated': $message = 'Produit modifié avec succès!'; break;
        case 'deleted': $message = 'Produit supprimé avec succès!'; break;
    }
}
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'notfound': $error = 'Produit non trouvé!'; break;
    }
}

// ============================================
// AFFICHAGE DE L'INTERFACE
// ============================================

// Récupérer les informations de l'admin depuis la session
$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles CSS - Raccourcis pour la lisibilité */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f7fa; color: #333; line-height: 1.6; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header { 
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); 
            color: white; 
            padding: 25px; 
            border-radius: 15px; 
            margin-bottom: 30px; 
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .header h1 { font-size: 28px; font-weight: 600; }
        
        /* Badge de rôle - CORRIGÉ ICI */
        .role-badge { 
            background-color: #4CAF50; 
            color: white; 
            padding: 8px 15px; 
            border-radius: 20px; 
            font-size: 14px; 
            font-weight: 500;
        }
        .superadmin-badge { background-color: #f44336; }
        
        /* Messages */
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Navigation */
        .nav-tabs { 
            display: flex; 
            background-color: white; 
            border-radius: 10px; 
            overflow: hidden; 
            margin-bottom: 30px; 
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .nav-tabs a { 
            padding: 18px 25px; 
            text-decoration: none; 
            color: #555; 
            font-weight: 500; 
            border-bottom: 3px solid transparent; 
            transition: all 0.3s ease; 
            display: flex; 
            align-items: center; 
            gap: 10px;
        }
        .nav-tabs a:hover { background-color: #f8f9fa; color: #6a11cb; }
        .nav-tabs a.active { color: #6a11cb; border-bottom-color: #6a11cb; background-color: #f0f8ff; }
        
        /* Boutons */
        .btn { 
            padding: 10px 20px; 
            border: none; 
            border-radius: 6px; 
            cursor: pointer; 
            font-weight: 500; 
            font-size: 14px; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            text-decoration: none;
        }
        .btn-primary { background-color: #6a11cb; color: white; }
        .btn-primary:hover { background-color: #5a0cb3; }
        .btn-success { background-color: #4CAF50; color: white; }
        .btn-warning { background-color: #ff9800; color: white; }
        .btn-danger { background-color: #f44336; color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        
        /* Tableaux */
        .table-container { 
            background-color: white; 
            border-radius: 10px; 
            overflow: hidden; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); 
            margin-bottom: 30px;
        }
        .table-header { 
            background-color: #f8f9fa; 
            padding: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            border-bottom: 1px solid #eee;
        }
        table { width: 100%; border-collapse: collapse; }
        th { 
            background-color: #f1f5fd; 
            padding: 16px 15px; 
            text-align: left; 
            font-weight: 600; 
            color: #2c3e50; 
            border-bottom: 2px solid #dee2e6;
        }
        td { padding: 16px 15px; border-bottom: 1px solid #eee; vertical-align: middle; }
        tr:hover { background-color: #f9f9f9; }
        
        /* Images produits */
        .product-image { 
            width: 60px; 
            height: 60px; 
            object-fit: cover; 
            border-radius: 8px; 
            border: 1px solid #ddd;
        }
        
        /* Prix et quantités */
        .price { font-weight: 600; color: #2e7d32; }
        .quantity { 
            display: inline-block; 
            padding: 5px 12px; 
            border-radius: 20px; 
            font-size: 13px; 
            font-weight: 500;
        }
        .quantity-low { background-color: #ffebee; color: #c62828; }
        .quantity-medium { background-color: #fff8e1; color: #f57c00; }
        .quantity-high { background-color: #e8f5e9; color: #2e7d32; }
        
        /* Actions */
        .actions { display: flex; gap: 8px; }
        
        /* Formulaires */
        .form-container { 
            background-color: white; 
            border-radius: 10px; 
            padding: 30px; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 500; 
            color: #444;
        }
        .form-control { 
            width: 100%; 
            padding: 12px 15px; 
            border: 1px solid #ddd; 
            border-radius: 6px; 
            font-size: 16px; 
            transition: border-color 0.3s;
        }
        .form-control:focus { 
            outline: none; 
            border-color: #6a11cb; 
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        textarea.form-control { min-height: 120px; resize: vertical; }
        
        /* Modal */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background-color: rgba(0, 0, 0, 0.5); 
            z-index: 1000; 
            align-items: center; 
            justify-content: center;
        }
        .modal-content { 
            background-color: white; 
            border-radius: 10px; 
            padding: 30px; 
            max-width: 500px; 
            width: 90%; 
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header { flex-direction: column; text-align: center; }
            .nav-tabs { flex-wrap: wrap; }
            .nav-tabs a { flex: 1; min-width: 140px; justify-content: center; }
            .table-header { flex-direction: column; gap: 15px; align-items: flex-start; }
            .actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header - CORRIGÉ ICI (ligne 723-724) -->
        <div class="header">
            <div>
                <h1><i class="fas fa-boxes"></i> Gestion des Produits</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($admin_username); ?></p>
            </div>
            <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>
        
        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> Retour Dashboard
            </a>
            <a href="admin_produits.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Liste des produits
            </a>
            <a href="admin_produits.php?action=add" class="<?php echo $action == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> Ajouter un produit
            </a>
            <a href="admin_produits.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Statistiques
            </a>
        </div>
        
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Contenu selon l'action -->
        <?php if ($action == 'list'): ?>
            <!-- LISTE DES PRODUITS -->
            <?php 
            $products = getAllProducts($pdo);
            $totalProducts = count($products);
            ?>
            
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-box-open"></i> Liste des produits (<?php echo $totalProducts; ?>)</h3>
                    <a href="admin_produits.php?action=add" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Nouveau produit
                    </a>
                </div>
                
                <?php if ($totalProducts > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Référence</th>
                                <th>Nom</th>
                                <th>Catégorie</th>
                                <th>Prix HT</th>
                                <th>Prix TTC</th>
                                <th>Stock</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): 
                                // Calcul du prix TTC
                                $prix_ttc = $product['prix_ht'] * (1 + ($product['tva'] / 100));
                                
                                // Déterminer la classe pour la quantité
                                $quantityClass = 'quantity-high';
                                if ($product['quantite_stock'] == 0) {
                                    $quantityClass = 'quantity-low';
                                    $stockStatus = 'Rupture';
                                } elseif ($product['quantite_stock'] <= $product['seuil_alerte']) {
                                    $quantityClass = 'quantity-medium';
                                    $stockStatus = 'Faible';
                                } else {
                                    $stockStatus = 'OK';
                                }
                            ?>
                            <tr>
                                <td>#<?php echo $product['id_produit']; ?></td>
                                <td><strong><?php echo htmlspecialchars($product['reference']); ?></strong></td>
                                <td><?php echo htmlspecialchars($product['nom']); ?></td>
                                <td><?php echo htmlspecialchars($product['categorie_nom'] ?? 'Non catégorisé'); ?></td>
                                <td class="price"><?php echo number_format($product['prix_ht'], 2, ',', ' '); ?> €</td>
                                <td class="price"><?php echo number_format($prix_ttc, 2, ',', ' '); ?> €</td>
                                <td>
                                    <span class="quantity <?php echo $quantityClass; ?>">
                                        <?php echo $product['quantite_stock']; ?> unités
                                        <small>(<?php echo $stockStatus; ?>)</small>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $statusColors = [
                                        'actif' => 'success',
                                        'inactif' => 'secondary',
                                        'rupture' => 'danger',
                                        'bientot' => 'warning'
                                    ];
                                    $statusText = [
                                        'actif' => 'Actif',
                                        'inactif' => 'Inactif',
                                        'rupture' => 'Rupture',
                                        'bientot' => 'Bientôt'
                                    ];
                                    $status = $product['statut'] ?? 'actif';
                                    $color = $statusColors[$status] ?? 'secondary';
                                    ?>
                                    <span style="display: inline-block; padding: 4px 10px; font-size: 12px; border-radius: 4px; background-color: var(--color, #6c757d); color: white; --color: <?php 
                                        echo $color === 'success' ? '#4CAF50' : 
                                              ($color === 'secondary' ? '#6c757d' : 
                                              ($color === 'danger' ? '#f44336' : '#ff9800'));
                                    ?>">
                                        <?php echo $statusText[$status] ?? $status; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="admin_produits.php?action=edit&id=<?php echo $product['id_produit']; ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $product['id_produit']; ?>, '<?php echo addslashes($product['nom']); ?>')" 
                                                class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                        <a href="admin_produits.php?action=view&id=<?php echo $product['id_produit']; ?>" 
                                           class="btn btn-secondary btn-sm">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <i class="fas fa-box-open" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777; margin-bottom: 10px;">Aucun produit trouvé</h3>
                        <p style="color: #999; margin-bottom: 30px;">Commencez par ajouter votre premier produit.</p>
                        <a href="admin_produits.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter un produit
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <!-- FORMULAIRE AJOUT/MODIFICATION -->
            <?php 
            $product = null;
            if ($action == 'edit' && $id > 0) {
                $product = getProductById($pdo, $id);
                if (!$product) {
                    echo '<div class="alert alert-danger">Produit non trouvé!</div>';
                    echo '<a href="admin_produits.php?action=list" class="btn btn-secondary">Retour à la liste</a>';
                    exit();
                }
            }
            
            // Valeurs par défaut pour l'ajout
            $defaultReference = $action == 'add' ? generateReference($pdo) : ($product['reference'] ?? '');
            ?>
            
            <div class="form-container">
                <h2 style="margin-bottom: 25px; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                    <?php echo $action == 'add' ? 'Ajouter un nouveau produit' : 'Modifier le produit #' . $product['id_produit']; ?>
                </h2>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $action == 'add' ? 'add' : 'edit'; ?>">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id_produit" value="<?php echo $product['id_produit']; ?>">
                    <?php endif; ?>
                    
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="nom"><i class="fas fa-tag"></i> Nom du produit *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['nom'] ?? ''); ?>" required
                                   oninput="document.getElementById('slug-preview').textContent = 'Slug : ' + generateSlug(this.value)">
                            <small id="slug-preview" style="color: #666; margin-top: 5px;">
                                Slug : <?php echo $product['slug'] ?? ''; ?>
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label for="reference"><i class="fas fa-barcode"></i> Référence *</label>
                            <input type="text" id="reference" name="reference" class="form-control" 
                                   value="<?php echo htmlspecialchars($defaultReference); ?>" required>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="description_courte"><i class="fas fa-align-left"></i> Description courte</label>
                            <textarea id="description_courte" name="description_courte" class="form-control" rows="3"><?php echo htmlspecialchars($product['description_courte'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="description"><i class="fas fa-align-justify"></i> Description complète</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="prix_ht"><i class="fas fa-euro-sign"></i> Prix HT (€) *</label>
                            <input type="number" id="prix_ht" name="prix_ht" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['prix_ht'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="tva"><i class="fas fa-percentage"></i> TVA (%) *</label>
                            <input type="number" id="tva" name="tva" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['tva'] ?? '20.00'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="id_categorie"><i class="fas fa-folder"></i> Catégorie *</label>
                            <select id="id_categorie" name="id_categorie" class="form-control" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id_categorie']; ?>"
                                        <?php echo (isset($product['id_categorie']) && $product['id_categorie'] == $category['id_categorie']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="quantite_stock"><i class="fas fa-cubes"></i> Quantité en stock *</label>
                            <input type="number" id="quantite_stock" name="quantite_stock" class="form-control" 
                                   min="0" value="<?php echo $product['quantite_stock'] ?? '0'; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="seuil_alerte"><i class="fas fa-exclamation-triangle"></i> Seuil d'alerte</label>
                            <input type="number" id="seuil_alerte" name="seuil_alerte" class="form-control" 
                                   min="0" value="<?php echo $product['seuil_alerte'] ?? '10'; ?>">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="marque"><i class="fas fa-trademark"></i> Marque</label>
                            <input type="text" id="marque" name="marque" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['marque'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="poids"><i class="fas fa-weight"></i> Poids (g)</label>
                            <input type="number" id="poids" name="poids" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $product['poids'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="couleur"><i class="fas fa-palette"></i> Couleur</label>
                            <input type="text" id="couleur" name="couleur" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['couleur'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="made_in"><i class="fas fa-globe"></i> Origine</label>
                            <input type="text" id="made_in" name="made_in" class="form-control" 
                                   value="<?php echo htmlspecialchars($product['made_in'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dimensions"><i class="fas fa-ruler-combined"></i> Dimensions (LxHxP en cm)</label>
                        <input type="text" id="dimensions" name="dimensions" class="form-control" 
                               value="<?php echo htmlspecialchars($product['dimensions'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="materiau"><i class="fas fa-cube"></i> Matériau</label>
                        <input type="text" id="materiau" name="materiau" class="form-control" 
                               value="<?php echo htmlspecialchars($product['materiau'] ?? ''); ?>">
                    </div>
                    
                    <!-- Caractéristiques spéciales -->
                    <div class="form-group">
                        <label><i class="fas fa-star"></i> Caractéristiques spéciales</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-top: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="personnalisable" name="personnalisable" 
                                       value="1" <?php echo (isset($product['personnalisable']) && $product['personnalisable'] == 1) ? 'checked' : ''; ?>>
                                <label for="personnalisable">Personnalisable</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="ecologique" name="ecologique" 
                                       value="1" <?php echo (isset($product['ecologique']) && $product['ecologique'] == 1) ? 'checked' : ''; ?>>
                                <label for="ecologique">Écologique</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="made_in_france" name="made_in_france" 
                                       value="1" <?php echo (isset($product['made_in_france']) && $product['made_in_france'] == 1) ? 'checked' : ''; ?>>
                                <label for="made_in_france">Made in France</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="artisanal" name="artisanal" 
                                       value="1" <?php echo (isset($product['artisanal']) && $product['artisanal'] == 1) ? 'checked' : ''; ?>>
                                <label for="artisanal">Artisanal</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="exclusif" name="exclusif" 
                                       value="1" <?php echo (isset($product['exclusif']) && $product['exclusif'] == 1) ? 'checked' : ''; ?>>
                                <label for="exclusif">Exclusif</label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="statut"><i class="fas fa-toggle-on"></i> Statut *</label>
                            <select id="statut" name="statut" class="form-control" required>
                                <option value="actif" <?php echo (isset($product['statut']) && $product['statut'] == 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($product['statut']) && $product['statut'] == 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="rupture" <?php echo (isset($product['statut']) && $product['statut'] == 'rupture') ? 'selected' : ''; ?>>Rupture de stock</option>
                                <option value="bientot" <?php echo (isset($product['statut']) && $product['statut'] == 'bientot') ? 'selected' : ''; ?>>Bientôt disponible</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="image"><i class="fas fa-image"></i> Image du produit</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                            <?php if ($action == 'edit'): ?>
                                <small>Laissez vide pour conserver l'image actuelle</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; margin-top: 30px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Ajouter le produit' : 'Mettre à jour'; ?>
                        </button>
                        <a href="admin_produits.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action == 'stats'): ?>
            <!-- STATISTIQUES (simplifié pour éviter les erreurs) -->
            <div class="form-container">
                <h2 style="margin-bottom: 25px; color: #333; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-chart-bar"></i> Statistiques produits
                </h2>
                
                <?php 
                try {
                    // Nombre total de produits
                    $sql = "SELECT COUNT(*) as total FROM produits";
                    $stmt = $pdo->query($sql);
                    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    // Produits par statut
                    $sql = "SELECT statut, COUNT(*) as count FROM produits GROUP BY statut";
                    $stmt = $pdo->query($sql);
                    $statuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Produits en alerte
                    $sql = "SELECT COUNT(*) as alertes FROM produits WHERE quantite_stock <= seuil_alerte AND statut = 'actif'";
                    $stmt = $pdo->query($sql);
                    $alertes = $stmt->fetch(PDO::FETCH_ASSOC)['alertes'];
                    
                    // Produits en rupture
                    $sql = "SELECT COUNT(*) as rupture FROM produits WHERE quantite_stock = 0 AND statut = 'actif'";
                    $stmt = $pdo->query($sql);
                    $rupture = $stmt->fetch(PDO::FETCH_ASSOC)['rupture'];
                    
                    // Valeur totale du stock
                    $sql = "SELECT SUM(prix_ht * quantite_stock * (1 + tva/100)) as valeur FROM produits WHERE statut = 'actif'";
                    $stmt = $pdo->query($sql);
                    $valeur = $stmt->fetch(PDO::FETCH_ASSOC)['valeur'];
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div style="background-color: white; padding: 20px; border-radius: 8px; border-left: 4px solid #2196F3; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h3 style="color: #2196F3; margin-bottom: 10px;">Total produits</h3>
                        <p style="font-size: 32px; font-weight: 700;"><?php echo $total; ?></p>
                    </div>
                    
                    <div style="background-color: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4CAF50; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h3 style="color: #4CAF50; margin-bottom: 10px;">Valeur stock</h3>
                        <p style="font-size: 32px; font-weight: 700;"><?php echo number_format($valeur ?? 0, 2, ',', ' '); ?> €</p>
                    </div>
                    
                    <div style="background-color: white; padding: 20px; border-radius: 8px; border-left: 4px solid #FF9800; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h3 style="color: #FF9800; margin-bottom: 10px;">Alertes stock</h3>
                        <p style="font-size: 32px; font-weight: 700;"><?php echo $alertes; ?></p>
                    </div>
                    
                    <div style="background-color: white; padding: 20px; border-radius: 8px; border-left: 4px solid #F44336; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <h3 style="color: #F44336; margin-bottom: 10px;">Ruptures</h3>
                        <p style="font-size: 32px; font-weight: 700;"><?php echo $rupture; ?></p>
                    </div>
                </div>
                
                <div style="background-color: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <h3 style="margin-bottom: 20px; color: #333;">Répartition par statut</h3>
                    <?php foreach ($statuts as $stat): ?>
                        <?php 
                        $percentage = $total > 0 ? ($stat['count'] / $total) * 100 : 0;
                        $colors = [
                            'actif' => '#4CAF50',
                            'inactif' => '#9E9E9E',
                            'rupture' => '#F44336',
                            'bientot' => '#FF9800'
                        ];
                        $color = $colors[$stat['statut']] ?? '#333';
                        ?>
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span style="font-weight: 600; color: <?php echo $color; ?>">
                                    <?php echo ucfirst($stat['statut']); ?>
                                </span>
                                <span><?php echo $stat['count']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div style="height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden;">
                                <div style="height: 100%; width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php } catch(Exception $e) { ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> Erreur lors du chargement des statistiques
                    </div>
                <?php } ?>
            </div>
            
        <?php endif; ?>
    </div>
    
    <!-- Modal de confirmation suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 24px;"></i>
                <h3 style="font-size: 22px; color: #333;">Confirmer la suppression</h3>
            </div>
            <div style="margin-bottom: 25px; color: #666;">
                <p>Êtes-vous sûr de vouloir supprimer le produit "<span id="productName"></span>" ?</p>
                <p style="color: #f44336; font-weight: 600; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
                </p>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <form id="deleteForm" method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_produit" id="productId">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Fonction pour générer un slug
        function generateSlug(text) {
            return text
                .toLowerCase()
                .replace(/[^a-z0-9-]/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        }
        
        // Fonctions pour la modal de suppression
        function confirmDelete(id, name) {
            document.getElementById('productId').value = id;
            document.getElementById('productName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fermer la modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Calcul automatique du prix TTC
        document.getElementById('prix_ht')?.addEventListener('input', calculateTTC);
        document.getElementById('tva')?.addEventListener('input', calculateTTC);
        
        function calculateTTC() {
            const prixHT = parseFloat(document.getElementById('prix_ht')?.value) || 0;
            const tva = parseFloat(document.getElementById('tva')?.value) || 20;
            const prixTTC = prixHT * (1 + tva / 100);
            
            const ttcElement = document.getElementById('ttc-preview');
            if (ttcElement) {
                ttcElement.textContent = 'Prix TTC : ' + prixTTC.toFixed(2) + ' €';
            }
        }
        
        // Initialiser le calcul TTC au chargement
        document.addEventListener('DOMContentLoaded', function() {
            calculateTTC();
            
            // Ajouter un aperçu du prix TTC si le champ existe
            const prixHTField = document.getElementById('prix_ht');
            if (prixHTField) {
                const ttcPreview = document.createElement('small');
                ttcPreview.id = 'ttc-preview';
                ttcPreview.style.color = '#666';
                ttcPreview.style.marginTop = '5px';
                ttcPreview.style.display = 'block';
                prixHTField.parentNode.appendChild(ttcPreview);
                calculateTTC();
            }
        });
    </script>
</body>
</html>