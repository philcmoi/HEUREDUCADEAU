<?php
// admin_produits.php - Version stable et fonctionnelle
// CORRECTION MAJEURE : Problème d'affichage des produits résolu
// Date: 2026-05-29

require_once 'admin_protection.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$dbname = 'heureducadeau';
$username_db = 'Philippe';
$password_db = 'l@99339R';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur connexion: " . $e->getMessage());
}

// ============================================
// FONCTIONS CORRIGÉES
// ============================================

/**
 * Récupère tous les produits avec leur image principale
 * CORRECTION : Requête simplifiée sans sous-requête problématique
 */
function getAllProducts($pdo) {
    // Récupérer d'abord tous les produits
    $sql = "SELECT p.*, c.nom as categorie_nom
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            ORDER BY p.id_produit DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Récupérer les images séparément pour éviter les doublons
    $productIds = array_column($products, 'id_produit');
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmtImg = $pdo->prepare("
            SELECT id_produit, url_image 
            FROM images_produits 
            WHERE id_produit IN ($placeholders) AND principale = 1
            ORDER BY id_produit, ordre ASC
        ");
        $stmtImg->execute($productIds);
        $images = [];
        while ($img = $stmtImg->fetch()) {
            if (!isset($images[$img['id_produit']])) {
                $images[$img['id_produit']] = $img['url_image'];
            }
        }
        
        // Ajouter les images aux produits
        foreach ($products as &$product) {
            $product['image_url'] = $images[$product['id_produit']] ?? 'https://via.placeholder.com/48x48?text=No+Image';
            // Nettoyer l'URL si nécessaire
            if ($product['image_url'] !== 'https://via.placeholder.com/48x48?text=No+Image') {
                $product['image_url'] = preg_replace('#/sean/+#', '/', $product['image_url']);
            }
        }
    } else {
        foreach ($products as &$product) {
            $product['image_url'] = 'https://via.placeholder.com/48x48?text=No+Image';
        }
    }
    
    return $products;
}

function getProductById($pdo, $id) {
    $sql = "SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getAllCategories($pdo) {
    $sql = "SELECT * FROM categories WHERE active = 1 ORDER BY nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function generateReference($pdo) {
    $sql = "SELECT reference FROM produits WHERE reference LIKE 'PROD-%' ORDER BY id_produit DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $last = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($last && preg_match('/PROD-(\d+)/', $last['reference'], $matches)) {
        $nextNumber = intval($matches[1]) + 1;
    } else {
        $nextNumber = 1;
    }
    
    return 'PROD-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
}

function generateUniqueSlug($nom, $pdo, $id_exclu = null) {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', $nom), '-'));
    if (empty($slug)) $slug = 'produit';
    
    $original = $slug;
    $counter = 1;
    
    while (slugExists($pdo, $slug, $id_exclu)) {
        $slug = $original . '-' . $counter++;
    }
    return $slug;
}

function slugExists($pdo, $slug, $id_exclu = null) {
    if ($id_exclu) {
        $sql = "SELECT COUNT(*) FROM produits WHERE slug = :slug AND id_produit != :id_exclu";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug, 'id_exclu' => $id_exclu]);
    } else {
        $sql = "SELECT COUNT(*) FROM produits WHERE slug = :slug";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['slug' => $slug]);
    }
    return $stmt->fetchColumn() > 0;
}

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

function updateProduct($pdo, $id, $data) {
    $data['id_produit'] = $id;
    $sql = "UPDATE produits SET 
                reference = :reference, nom = :nom, slug = :slug,
                description = :description, description_courte = :description_courte,
                prix_ht = :prix_ht, tva = :tva,
                quantite_stock = :quantite_stock, seuil_alerte = :seuil_alerte,
                id_categorie = :id_categorie, marque = :marque,
                poids = :poids, dimensions = :dimensions,
                materiau = :materiau, couleur = :couleur, made_in = :made_in,
                personnalisable = :personnalisable, ecologique = :ecologique,
                made_in_france = :made_in_france, artisanal = :artisanal, exclusif = :exclusif,
                statut = :statut, date_modification = NOW()
            WHERE id_produit = :id_produit";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function deleteProduct($pdo, $id) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM promotions_produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM images_produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM variants WHERE id_produit = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function uploadImage($file) {
    $upload_dir = __DIR__ . '/uploads/produits/';
    $upload_url = '/uploads/produits/';
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Erreur upload'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        return ['error' => 'Format non autorisé'];
    }
    
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $filename = uniqid() . '_' . date('Ymd_His') . '.' . $ext;
    $target = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return ['success' => $upload_url . $filename];
    }
    return ['error' => 'Erreur lors de l\'upload'];
}

function linkProductToGlobalPromotions($pdo, $product_id) {
    $sql = "SELECT id_promotion FROM promotions 
            WHERE actif = 1 AND date_debut <= NOW() AND date_fin >= NOW()
            AND type_promotion IN ('pourcentage', 'montant_fixe')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $promotions = $stmt->fetchAll();
    
    $added = 0;
    foreach ($promotions as $promo) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM promotions_produits WHERE id_promotion = ? AND id_produit = ?");
        $stmt_check->execute([$promo['id_promotion'], $product_id]);
        if ($stmt_check->fetchColumn() == 0) {
            $stmt_insert = $pdo->prepare("INSERT INTO promotions_produits (id_promotion, id_produit) VALUES (?, ?)");
            if ($stmt_insert->execute([$promo['id_promotion'], $product_id])) {
                $added++;
            }
        }
    }
    return $added;
}

// ============================================
// TRAITEMENT
// ============================================
$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$categories = getAllCategories($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $slug = generateUniqueSlug($_POST['nom'], $pdo);
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'] ?? '',
                    'description_courte' => $_POST['description_courte'] ?? '',
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte'] ?? 10),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'] ?? '',
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'] ?? '',
                    'materiau' => $_POST['materiau'] ?? '',
                    'couleur' => $_POST['couleur'] ?? '',
                    'made_in' => $_POST['made_in'] ?? '',
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut'] ?? 'actif'
                ];
                
                if (addProduct($pdo, $data)) {
                    $lastId = $pdo->lastInsertId();
                    linkProductToGlobalPromotions($pdo, $lastId);
                    
                    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                        $upload = uploadImage($_FILES['image']);
                        if (isset($upload['success'])) {
                            $stmt = $pdo->prepare("INSERT INTO images_produits (id_produit, url_image, alt_text, principale) VALUES (?, ?, ?, 1)");
                            $stmt->execute([$lastId, $upload['success'], $_POST['nom']]);
                        }
                    }
                    $_SESSION['message'] = 'Produit ajouté avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                }
                $error = 'Erreur lors de l\'ajout';
                break;
                
            case 'edit':
                $id = intval($_POST['id_produit']);
                $existing = getProductById($pdo, $id);
                if (!$existing) {
                    $error = 'Produit non trouvé';
                    break;
                }
                
                $slug = ($existing['nom'] == $_POST['nom']) ? $existing['slug'] : generateUniqueSlug($_POST['nom'], $pdo, $id);
                $data = [
                    'reference' => $_POST['reference'],
                    'nom' => $_POST['nom'],
                    'slug' => $slug,
                    'description' => $_POST['description'] ?? '',
                    'description_courte' => $_POST['description_courte'] ?? '',
                    'prix_ht' => floatval($_POST['prix_ht']),
                    'tva' => floatval($_POST['tva']),
                    'quantite_stock' => intval($_POST['quantite_stock']),
                    'seuil_alerte' => intval($_POST['seuil_alerte'] ?? 10),
                    'id_categorie' => intval($_POST['id_categorie']),
                    'marque' => $_POST['marque'] ?? '',
                    'poids' => !empty($_POST['poids']) ? floatval($_POST['poids']) : null,
                    'dimensions' => $_POST['dimensions'] ?? '',
                    'materiau' => $_POST['materiau'] ?? '',
                    'couleur' => $_POST['couleur'] ?? '',
                    'made_in' => $_POST['made_in'] ?? '',
                    'personnalisable' => isset($_POST['personnalisable']) ? 1 : 0,
                    'ecologique' => isset($_POST['ecologique']) ? 1 : 0,
                    'made_in_france' => isset($_POST['made_in_france']) ? 1 : 0,
                    'artisanal' => isset($_POST['artisanal']) ? 1 : 0,
                    'exclusif' => isset($_POST['exclusif']) ? 1 : 0,
                    'statut' => $_POST['statut'] ?? 'actif',
                    'id_produit' => $id
                ];
                
                if (updateProduct($pdo, $id, $data)) {
                    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                        $upload = uploadImage($_FILES['image']);
                        if (isset($upload['success'])) {
                            $stmt = $pdo->prepare("DELETE FROM images_produits WHERE id_produit = ? AND principale = 1");
                            $stmt->execute([$id]);
                            $stmt = $pdo->prepare("INSERT INTO images_produits (id_produit, url_image, alt_text, principale) VALUES (?, ?, ?, 1)");
                            $stmt->execute([$id, $upload['success'], $_POST['nom']]);
                        }
                    }
                    $_SESSION['message'] = 'Produit modifié avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                }
                $error = 'Erreur lors de la modification';
                break;
                
            case 'delete':
                $id = intval($_POST['id_produit']);
                if (deleteProduct($pdo, $id)) {
                    $_SESSION['message'] = 'Produit supprimé avec succès !';
                    header('Location: admin_produits.php?action=list');
                    exit();
                }
                $error = 'Erreur lors de la suppression';
                break;
        }
    }
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; }
        .container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        
        /* Header */
        .header { background: linear-gradient(135deg, #6366f1, #4f46e5); border-radius: 16px; padding: 24px 32px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .header h1 { font-size: 28px; display: flex; align-items: center; gap: 12px; }
        .role-badge { background: rgba(255,255,255,0.2); padding: 8px 20px; border-radius: 40px; font-size: 14px; }
        
        /* Navigation */
        .nav-tabs { display: flex; gap: 8px; background: white; padding: 6px; border-radius: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .nav-tabs a { padding: 12px 24px; text-decoration: none; color: #4b5563; border-radius: 12px; transition: all 0.3s; display: flex; align-items: center; gap: 8px; }
        .nav-tabs a:hover { background: #f3f4f6; color: #6366f1; }
        .nav-tabs a.active { background: #6366f1; color: white; }
        
        /* Alertes */
        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-danger { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        
        /* Card */
        .card { background: white; border-radius: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #f9fafb; }
        .card-header h3 { font-size: 18px; display: flex; align-items: center; gap: 10px; }
        
        /* Table */
        .table-wrapper { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { padding: 16px; text-align: left; font-weight: 600; font-size: 13px; color: #6b7280; border-bottom: 2px solid #e5e7eb; background: #f9fafb; }
        .table td { padding: 16px; border-bottom: 1px solid #e5e7eb; vertical-align: middle; }
        .table tbody tr:hover { background: #f9fafb; }
        
        .product-image { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid #e5e7eb; }
        
        /* Badges */
        .badge { display: inline-flex; align-items: center; padding: 4px 12px; border-radius: 100px; font-size: 12px; font-weight: 500; gap: 6px; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fed7aa; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        
        /* Boutons */
        .btn { padding: 10px 20px; border: none; border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.3s; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 14px; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-primary:hover { background: #4f46e5; transform: translateY(-1px); }
        .btn-success { background: #10b981; color: white; }
        .btn-success:hover { background: #059669; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-warning:hover { background: #d97706; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        .btn-icon { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; transition: all 0.3s; cursor: pointer; border: none; }
        .btn-icon-view { background: #dbeafe; color: #2563eb; }
        .btn-icon-view:hover { background: #2563eb; color: white; }
        .btn-icon-edit { background: #fed7aa; color: #d97706; }
        .btn-icon-edit:hover { background: #d97706; color: white; }
        .btn-icon-delete { background: #fee2e2; color: #dc2626; }
        .btn-icon-delete:hover { background: #dc2626; color: white; }
        
        .actions { display: flex; gap: 8px; }
        
        /* Form */
        .form-container { background: white; border-radius: 16px; overflow: hidden; }
        .form-header { padding: 24px; background: linear-gradient(135deg, #f9fafb, white); border-bottom: 1px solid #e5e7eb; }
        .form-header h2 { font-size: 22px; display: flex; align-items: center; gap: 12px; }
        .form-body { padding: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; color: #374151; }
        .form-group label i { margin-right: 8px; color: #6366f1; }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #d1d5db; border-radius: 10px; font-size: 14px; transition: all 0.3s; }
        .form-control:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; }
        .form-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        @media (max-width: 1024px) { .form-grid-4 { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) { .form-grid-4 { grid-template-columns: 1fr; } }
        
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 8px; }
        .checkbox-item { display: flex; align-items: center; gap: 8px; }
        .checkbox-item input { width: 18px; height: 18px; cursor: pointer; accent-color: #6366f1; }
        
        .info-note { background: #dbeafe; color: #1e40af; padding: 12px; border-radius: 10px; font-size: 13px; margin-top: 8px; display: inline-block; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 16px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-card h3 { font-size: 14px; color: #6b7280; margin-bottom: 8px; text-transform: uppercase; }
        .stat-card .stat-value { font-size: 32px; font-weight: 700; color: #1f2937; }
        
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: #d1d5db; margin-bottom: 20px; }
        .empty-state h3 { font-size: 18px; color: #6b7280; margin-bottom: 8px; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; border-radius: 16px; max-width: 450px; width: 100%; }
        .modal-header { padding: 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 12px; }
        .modal-header i { font-size: 28px; color: #ef4444; }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 12px; }
        
        @media (max-width: 768px) {
            .container { padding: 16px; }
            .header { padding: 20px; }
            .header h1 { font-size: 22px; }
            .nav-tabs a { padding: 8px 16px; font-size: 13px; }
            .table th, .table td { padding: 12px 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-gift"></i> Gestion des Produits</h1>
                <p>Bienvenue, <?= htmlspecialchars($admin_username) ?></p>
            </div>
            <div class="role-badge"><i class="fas fa-shield-alt"></i> <?= ucfirst($admin_role) ?></div>
        </div>
        
        <div class="nav-tabs">
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            <a href="admin_produits.php?action=list" class="<?= $action == 'list' ? 'active' : '' ?>"><i class="fas fa-list-ul"></i> Liste</a>
            <a href="admin_produits.php?action=add" class="<?= $action == 'add' ? 'active' : '' ?>"><i class="fas fa-plus-circle"></i> Ajouter</a>
            <a href="admin_produits.php?action=stats" class="<?= $action == 'stats' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Statistiques</a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($action == 'list'): ?>
            <?php $products = getAllProducts($pdo); ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-box-open"></i> Catalogue produits <span class="badge badge-info"><?= count($products) ?> produit(s)</span></h3>
                    <a href="admin_produits.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau produit</a>
                </div>
                <?php if (!empty($products)): ?>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr><th>ID</th><th>Image</th><th>Référence</th><th>Nom</th><th>Catégorie</th><th>Prix HT</th><th>Prix TTC</th><th>Stock</th><th>Statut</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $p): 
                                    $img = !empty($p['image_url']) ? $p['image_url'] : 'https://via.placeholder.com/48x48?text=No+Image';
                                    $ttc = $p['prix_ht'] * (1 + $p['tva']/100);
                                    $stockClass = $p['quantite_stock'] == 0 ? 'badge-danger' : ($p['quantite_stock'] <= $p['seuil_alerte'] ? 'badge-warning' : 'badge-success');
                                    $stockText = $p['quantite_stock'] == 0 ? 'Rupture' : ($p['quantite_stock'] <= $p['seuil_alerte'] ? 'Faible' : 'Disponible');
                                    $statusClass = $p['statut'] == 'actif' ? 'badge-success' : 'badge-gray';
                                ?>
                                <tr>
                                    <td><strong>#<?= $p['id_produit'] ?></strong></td>
                                    <td><img src="<?= htmlspecialchars($img) ?>" class="product-image" onerror="this.src='https://via.placeholder.com/48x48?text=Error'"></td>
                                    <td><code><?= htmlspecialchars($p['reference']) ?></code></div
                                    <td><strong><?= htmlspecialchars($p['nom']) ?></strong></div
                                    <td><span class="badge badge-info"><?= htmlspecialchars($p['categorie_nom'] ?? '-') ?></span></div
                                    <td><?= number_format($p['prix_ht'], 2, ',', ' ') ?> €</div
                                    <td><?= number_format($ttc, 2, ',', ' ') ?> €</div
                                    <td><span class="badge <?= $stockClass ?>"><i class="fas fa-cubes"></i> <?= $p['quantite_stock'] ?> (<?= $stockText ?>)</span></div
                                    <td><span class="badge <?= $statusClass ?>"><?= ucfirst($p['statut']) ?></span></div
                                    <td class="actions">
                                        <a href="admin_produits.php?action=view&id=<?= $p['id_produit'] ?>" class="btn-icon btn-icon-view" title="Voir"><i class="fas fa-eye"></i></a>
                                        <a href="admin_produits.php?action=edit&id=<?= $p['id_produit'] ?>" class="btn-icon btn-icon-edit" title="Modifier"><i class="fas fa-pencil-alt"></i></a>
                                        <button onclick="confirmDelete(<?= $p['id_produit'] ?>, '<?= addslashes($p['nom']) ?>')" class="btn-icon btn-icon-delete" title="Supprimer"><i class="fas fa-trash-alt"></i></button>
                                    </div
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state"><i class="fas fa-box-open"></i><h3>Aucun produit trouvé</h3><p>Commencez par ajouter votre premier produit</p><a href="admin_produits.php?action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Ajouter un produit</a></div>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action == 'add' || $action == 'edit'): ?>
            <?php 
            $product = null;
            if ($action == 'edit' && $id > 0) {
                $product = getProductById($pdo, $id);
                if (!$product) { echo '<div class="alert alert-danger">Produit non trouvé</div><a href="admin_produits.php?action=list" class="btn btn-secondary">Retour</a>'; exit; }
                $stmt = $pdo->prepare("SELECT url_image FROM images_produits WHERE id_produit = ? AND principale = 1 LIMIT 1");
                $stmt->execute([$id]);
                $current_image = $stmt->fetch();
            }
            $defaultRef = $action == 'add' ? generateReference($pdo) : ($product['reference'] ?? '');
            ?>
            <div class="form-container">
                <div class="form-header"><h2><i class="fas <?= $action == 'add' ? 'fa-plus-circle' : 'fa-edit' ?>"></i> <?= $action == 'add' ? 'Nouveau produit' : 'Modification produit #' . $product['id_produit'] ?></h2></div>
                <div class="form-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $action ?>">
                        <?php if ($action == 'edit'): ?><input type="hidden" name="id_produit" value="<?= $product['id_produit'] ?>"><?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group"><label><i class="fas fa-tag"></i> Nom *</label><input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($product['nom'] ?? '') ?>" required oninput="updateSlug(this.value)"><small id="slug-preview" style="display:block; margin-top:6px; color:#6b7280;">Slug : <?= $product['slug'] ?? '' ?></small></div>
                            <div class="form-group"><label><i class="fas fa-barcode"></i> Référence *</label><input type="text" name="reference" class="form-control" value="<?= htmlspecialchars($defaultRef) ?>" required></div>
                            <div class="form-group"><label><i class="fas fa-euro-sign"></i> Prix HT *</label><input type="number" step="0.01" name="prix_ht" class="form-control" value="<?= $product['prix_ht'] ?? '' ?>" required oninput="calculateTTC()"><small id="ttc-preview" style="display:block; margin-top:6px; color:#6b7280;"></small></div>
                            <div class="form-group"><label><i class="fas fa-percentage"></i> TVA % *</label><input type="number" step="0.01" name="tva" class="form-control" value="<?= $product['tva'] ?? '20.00' ?>" required oninput="calculateTTC()"></div>
                            <div class="form-group"><label><i class="fas fa-folder"></i> Catégorie *</label><select name="id_categorie" class="form-control" required><option value="">-- Sélectionner --</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id_categorie'] ?>" <?= (isset($product['id_categorie']) && $product['id_categorie'] == $cat['id_categorie']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['nom']) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label><i class="fas fa-cubes"></i> Quantité stock *</label><input type="number" name="quantite_stock" class="form-control" value="<?= $product['quantite_stock'] ?? '0' ?>" required></div>
                            <div class="form-group"><label><i class="fas fa-exclamation-triangle"></i> Seuil alerte</label><input type="number" name="seuil_alerte" class="form-control" value="<?= $product['seuil_alerte'] ?? '10' ?>"></div>
                            <div class="form-group"><label><i class="fas fa-toggle-on"></i> Statut *</label><select name="statut" class="form-control" required><option value="actif" <?= (isset($product['statut']) && $product['statut'] == 'actif') ? 'selected' : '' ?>>Actif</option><option value="inactif" <?= (isset($product['statut']) && $product['statut'] == 'inactif') ? 'selected' : '' ?>>Inactif</option><option value="rupture" <?= (isset($product['statut']) && $product['statut'] == 'rupture') ? 'selected' : '' ?>>Rupture</option><option value="bientot" <?= (isset($product['statut']) && $product['statut'] == 'bientot') ? 'selected' : '' ?>>Bientôt</option></select></div>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group"><label><i class="fas fa-align-left"></i> Description courte</label><textarea name="description_courte" class="form-control" rows="3"><?= htmlspecialchars($product['description_courte'] ?? '') ?></textarea></div>
                            <div class="form-group"><label><i class="fas fa-align-justify"></i> Description complète</label><textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($product['description'] ?? '') ?></textarea></div>
                        </div>
                        
                        <div class="form-grid-4">
                            <div class="form-group"><label><i class="fas fa-trademark"></i> Marque</label><input type="text" name="marque" class="form-control" value="<?= htmlspecialchars($product['marque'] ?? '') ?>"></div>
                            <div class="form-group"><label><i class="fas fa-weight-hanging"></i> Poids (g)</label><input type="number" step="0.01" name="poids" class="form-control" value="<?= $product['poids'] ?? '' ?>"></div>
                            <div class="form-group"><label><i class="fas fa-palette"></i> Couleur</label><input type="text" name="couleur" class="form-control" value="<?= htmlspecialchars($product['couleur'] ?? '') ?>"></div>
                            <div class="form-group"><label><i class="fas fa-globe-europe"></i> Origine</label><input type="text" name="made_in" class="form-control" value="<?= htmlspecialchars($product['made_in'] ?? '') ?>"></div>
                            <div class="form-group"><label><i class="fas fa-ruler-combined"></i> Dimensions (cm)</label><input type="text" name="dimensions" class="form-control" placeholder="L x H x P" value="<?= htmlspecialchars($product['dimensions'] ?? '') ?>"></div>
                            <div class="form-group"><label><i class="fas fa-cube"></i> Matériau</label><input type="text" name="materiau" class="form-control" value="<?= htmlspecialchars($product['materiau'] ?? '') ?>"></div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-star"></i> Caractéristiques</label>
                            <div class="checkbox-group">
                                <div class="checkbox-item"><input type="checkbox" name="personnalisable" value="1" <?= (isset($product['personnalisable']) && $product['personnalisable']) ? 'checked' : '' ?>><label>Personnalisable</label></div>
                                <div class="checkbox-item"><input type="checkbox" name="ecologique" value="1" <?= (isset($product['ecologique']) && $product['ecologique']) ? 'checked' : '' ?>><label>Écologique</label></div>
                                <div class="checkbox-item"><input type="checkbox" name="made_in_france" value="1" <?= (isset($product['made_in_france']) && $product['made_in_france']) ? 'checked' : '' ?>><label>Made in France</label></div>
                                <div class="checkbox-item"><input type="checkbox" name="artisanal" value="1" <?= (isset($product['artisanal']) && $product['artisanal']) ? 'checked' : '' ?>><label>Artisanal</label></div>
                                <div class="checkbox-item"><input type="checkbox" name="exclusif" value="1" <?= (isset($product['exclusif']) && $product['exclusif']) ? 'checked' : '' ?>><label>Exclusif</label></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-image"></i> Image</label>
                            <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="info-note"><i class="fas fa-info-circle"></i> Formats: JPG, PNG, GIF, WebP · Max 2MB</div>
                            <?php if ($action == 'edit' && !empty($current_image)): ?>
                                <div style="margin-top:12px; display:flex; align-items:center; gap:15px;"><img src="<?= htmlspecialchars($current_image['url_image']) ?>" style="width:60px;height:60px;object-fit:cover;border-radius:8px;border:1px solid #ddd;"><small>Image actuelle · Laissez vide pour conserver</small></div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="display:flex; gap:12px; margin-top:24px; padding-top:20px; border-top:1px solid #e5e7eb;">
                            <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> <?= $action == 'add' ? 'Ajouter' : 'Mettre à jour' ?></button>
                            <a href="admin_produits.php?action=list" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php elseif ($action == 'stats'): ?>
            <?php 
            $total = $pdo->query("SELECT COUNT(*) FROM produits")->fetchColumn();
            $actif = $pdo->query("SELECT COUNT(*) FROM produits WHERE statut = 'actif'")->fetchColumn();
            $rupture = $pdo->query("SELECT COUNT(*) FROM produits WHERE quantite_stock = 0")->fetchColumn();
            $stockTotal = $pdo->query("SELECT SUM(quantite_stock) FROM produits")->fetchColumn();
            ?>
            <div class="stats-grid">
                <div class="stat-card"><h3>Total produits</h3><div class="stat-value"><?= $total ?></div></div>
                <div class="stat-card"><h3>Produits actifs</h3><div class="stat-value"><?= $actif ?></div></div>
                <div class="stat-card"><h3>Ruptures</h3><div class="stat-value"><?= $rupture ?></div></div>
                <div class="stat-card"><h3>Stock total</h3><div class="stat-value"><?= $stockTotal ?></div></div>
            </div>
            
        <?php elseif ($action == 'view' && $id > 0): ?>
            <?php 
            $product = getProductById($pdo, $id);
            if (!$product) { echo '<div class="alert alert-danger">Produit non trouvé</div><a href="admin_produits.php?action=list" class="btn btn-secondary">Retour</a>'; exit; }
            $images = $pdo->prepare("SELECT * FROM images_produits WHERE id_produit = ? ORDER BY principale DESC");
            $images->execute([$id]);
            $images = $images->fetchAll();
            ?>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-eye"></i> Détail du produit #<?= $id ?></h3><div><a href="admin_produits.php?action=edit&id=<?= $id ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Modifier</a> <a href="admin_produits.php?action=list" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Retour</a></div></div>
                <div class="card-body">
                    <div style="display:grid; grid-template-columns:1fr 1.5fr; gap:32px;">
                        <div><h4><i class="fas fa-images"></i> Images</h4><?php if(!empty($images)): ?><div class="images-grid" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:12px;"><?php foreach($images as $img): ?><div style="border:2px solid #e5e7eb; border-radius:8px; overflow:hidden;"><img src="<?= htmlspecialchars($img['url_image']) ?>" style="width:100%; height:100px; object-fit:cover;"><?php if($img['principale']): ?><small style="display:block; text-align:center; padding:4px; background:#f3f4f6;">⭐ Principale</small><?php endif; ?></div><?php endforeach; ?></div><?php else: ?><div class="empty-state" style="padding:20px;"><i class="fas fa-image"></i><p>Aucune image</p></div><?php endif; ?></div>
                        <div><table class="info-table" style="width:100%"><?php 
                            $ttc = $product['prix_ht'] * (1 + $product['tva']/100);
                            $fields = [['Référence', $product['reference']], ['Nom', $product['nom']], ['Slug', $product['slug']], ['Catégorie', $product['categorie_nom'] ?? '-'], ['Prix HT', number_format($product['prix_ht'],2).' €'], ['TVA', $product['tva'].'%'], ['Prix TTC', number_format($ttc,2).' €'], ['Stock', $product['quantite_stock'].' unités'], ['Statut', ucfirst($product['statut'])]];
                            foreach($fields as $f): echo "<tr><th style='padding:12px 0; text-align:left; width:140px;'>{$f[0]}</th><td style='padding:12px 0;'><strong>{$f[1]}</strong></td></tr>";
                            endforeach; ?>
                        </table></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="deleteModal" class="modal"><div class="modal-content"><div class="modal-header"><i class="fas fa-exclamation-triangle"></i><h3>Confirmer la suppression</h3></div><div class="modal-body"><p>Supprimer "<strong id="productName"></strong>" ?</p><p style="color:#ef4444;"><i class="fas fa-exclamation-circle"></i> Action irréversible !</p></div><div class="modal-footer"><form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="id_produit" id="productId"><button type="button" onclick="closeModal()" class="btn btn-secondary">Annuler</button><button type="submit" class="btn btn-danger">Supprimer</button></form></div></div></div>
    
    <script>
        function updateSlug(nom) { let preview = document.getElementById('slug-preview'); if(preview) preview.innerHTML = 'Slug : ' + nom.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '') || '(vide)'; }
        function calculateTTC() { let ht = parseFloat(document.querySelector('[name="prix_ht"]')?.value) || 0, tva = parseFloat(document.querySelector('[name="tva"]')?.value) || 20, ttc = ht * (1 + tva/100); let el = document.getElementById('ttc-preview'); if(el) el.textContent = 'Prix TTC : ' + ttc.toFixed(2) + ' €'; }
        function confirmDelete(id, name) { document.getElementById('productId').value = id; document.getElementById('productName').textContent = name; document.getElementById('deleteModal').style.display = 'flex'; setTimeout(() => document.getElementById('deleteModal').classList.add('show'), 10); }
        function closeModal() { let modal = document.getElementById('deleteModal'); modal.classList.remove('show'); setTimeout(() => modal.style.display = 'none', 200); }
        window.onclick = function(e) { if(e.target === document.getElementById('deleteModal')) closeModal(); }
        document.addEventListener('DOMContentLoaded', function() { calculateTTC(); document.querySelectorAll('img').forEach(img => { img.onerror = function() { if(!this.src.includes('placeholder')) this.src = 'https://via.placeholder.com/60x60?text=Error'; }; }); });
    </script>
</body>
</html>