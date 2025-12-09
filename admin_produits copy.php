<?php
/**
 * admin_produits.php - Gestion des produits (CRUD)
 * @version 1.0
 */

// Vérification de l'authentification
session_start();
require_once __DIR__ . '/config/Database.php';


// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
   header('Location: login.php');
    exit;
}


// Vérifier les permissions
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], ['admin', 'superadmin'])) {
    die('<h1>Accès refusé</h1><p>Vous n\'avez pas les permissions nécessaires.</p>');
}

// Récupérer l'instance de la base de données
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}

// Variables
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;
$message = '';
$error = '';
$success = '';

// Traitement des actions
switch ($action) {
    case 'add':
        $page_title = "Ajouter un produit";
        break;
        
    case 'edit':
        $page_title = "Modifier un produit";
        break;
        
    case 'delete':
        $page_title = "Supprimer un produit";
        break;
        
    default:
        $page_title = "Gestion des produits";
        $action = 'list';
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
            case 'edit':
                $result = saveProduit($_POST);
                if ($result['success']) {
                    $success = $result['message'];
                    if ($_POST['action'] === 'add') {
                        // Rediriger vers la liste après ajout
                        header('Location: admin_produits.php?success=' . urlencode($success));
                        exit;
                    }
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'delete':
                $result = deleteProduit($_POST['id_produit']);
                if ($result['success']) {
                    $success = $result['message'];
                    header('Location: admin_produits.php?success=' . urlencode($success));
                    exit;
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Récupérer les messages de succès
if (isset($_GET['success'])) {
    $success = htmlspecialchars($_GET['success']);
}

// Récupérer un produit pour l'édition
$produit = [];
if ($action === 'edit' && $id > 0) {
    $produit = getProduitById($id);
    if (!$produit) {
        $error = "Produit introuvable.";
        $action = 'list';
    }
}

// Récupérer les catégories
$categories = getCategories();

// Récupérer la liste des produits pour l'action 'list'
$produits = [];
if ($action === 'list') {
    $produits = getAllProduits();
}

/**
 * Récupérer toutes les catégories
 */
function getCategories() {
    global $db;
    try {
        $stmt = $db->query("SELECT * FROM categories ORDER BY nom");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Récupérer un produit par son ID
 */
function getProduitById($id) {
    global $db;
    try {
        $stmt = $db->prepare("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Récupérer tous les produits
 */
function getAllProduits() {
    global $db;
    try {
        $stmt = $db->query("
            SELECT p.*, c.nom as categorie_nom 
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            ORDER BY p.date_creation DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Sauvegarder un produit (ajout ou modification)
 */
function saveProduit($data) {
    global $db;
    
    // Validation des données
    $errors = validateProduitData($data);
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode('<br>', $errors)
        ];
    }
    
    // Préparer les données
    $produitData = [
        'reference' => htmlspecialchars(trim($data['reference'])),
        'nom' => htmlspecialchars(trim($data['nom'])),
        'slug' => htmlspecialchars(trim($data['slug'])),
        'description' => htmlspecialchars(trim($data['description'] ?? '')),
        'description_courte' => htmlspecialchars(trim($data['description_courte'] ?? '')),
        'prix_ht' => floatval($data['prix_ht']),
        'tva' => floatval($data['tva']),
        'quantite_stock' => intval($data['quantite_stock']),
        'id_categorie' => intval($data['id_categorie']),
        'marque' => htmlspecialchars(trim($data['marque'] ?? '')),
        'poids' => $data['poids'] ? floatval($data['poids']) : null,
        'dimensions' => htmlspecialchars(trim($data['dimensions'] ?? '')),
        'materiau' => htmlspecialchars(trim($data['materiau'] ?? '')),
        'couleur' => htmlspecialchars(trim($data['couleur'] ?? '')),
        'made_in' => htmlspecialchars(trim($data['made_in'] ?? '')),
        'personnalisable' => isset($data['personnalisable']) ? 1 : 0,
        'ecologique' => isset($data['ecologique']) ? 1 : 0,
        'made_in_france' => isset($data['made_in_france']) ? 1 : 0,
        'artisanal' => isset($data['artisanal']) ? 1 : 0,
        'exclusif' => isset($data['exclusif']) ? 1 : 0,
        'statut' => htmlspecialchars(trim($data['statut']))
    ];
    
    try {
        if (isset($data['id_produit']) && $data['id_produit'] > 0) {
            // Modification
            $produitData['id_produit'] = intval($data['id_produit']);
            
            $stmt = $db->prepare("
                UPDATE produits SET
                    reference = :reference,
                    nom = :nom,
                    slug = :slug,
                    description = :description,
                    description_courte = :description_courte,
                    prix_ht = :prix_ht,
                    tva = :tva,
                    quantite_stock = :quantite_stock,
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
                WHERE id_produit = :id_produit
            ");
            
            $stmt->execute($produitData);
            
            return [
                'success' => true,
                'message' => 'Produit modifié avec succès.',
                'id_produit' => $produitData['id_produit']
            ];
        } else {
            // Ajout
            $stmt = $db->prepare("
                INSERT INTO produits (
                    reference, nom, slug, description, description_courte,
                    prix_ht, tva, quantite_stock, id_categorie, marque,
                    poids, dimensions, materiau, couleur, made_in,
                    personnalisable, ecologique, made_in_france, artisanal,
                    exclusif, statut, date_creation
                ) VALUES (
                    :reference, :nom, :slug, :description, :description_courte,
                    :prix_ht, :tva, :quantite_stock, :id_categorie, :marque,
                    :poids, :dimensions, :materiau, :couleur, :made_in,
                    :personnalisable, :ecologique, :made_in_france, :artisanal,
                    :exclusif, :statut, NOW()
                )
            ");
            
            $stmt->execute($produitData);
            $id_produit = $db->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Produit ajouté avec succès.',
                'id_produit' => $id_produit
            ];
        }
    } catch (PDOException $e) {
        // Vérifier si c'est une erreur de duplication
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            if (strpos($e->getMessage(), 'reference') !== false) {
                return [
                    'success' => false,
                    'message' => 'Cette référence existe déjà.'
                ];
            } elseif (strpos($e->getMessage(), 'slug') !== false) {
                return [
                    'success' => false,
                    'message' => 'Ce slug existe déjà.'
                ];
            }
        }
        
        return [
            'success' => false,
            'message' => 'Erreur de base de données: ' . $e->getMessage()
        ];
    }
}

/**
 * Valider les données du produit
 */
function validateProduitData($data) {
    $errors = [];
    
    // Champs requis
    $required = ['reference', 'nom', 'slug', 'prix_ht', 'id_categorie'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = "Le champ " . ucfirst(str_replace('_', ' ', $field)) . " est requis.";
        }
    }
    
    // Vérifier la référence
    if (isset($data['reference']) && !preg_match('/^[A-Z0-9-]{3,50}$/i', $data['reference'])) {
        $errors[] = "La référence doit contenir 3 à 50 caractères (lettres, chiffres, tirets).";
    }
    
    // Vérifier le slug
    if (isset($data['slug']) && !preg_match('/^[a-z0-9-]{3,100}$/', $data['slug'])) {
        $errors[] = "Le slug doit contenir 3 à 100 caractères (lettres minuscules, chiffres, tirets).";
    }
    
    // Vérifier le prix
    if (isset($data['prix_ht']) && $data['prix_ht'] < 0) {
        $errors[] = "Le prix ne peut pas être négatif.";
    }
    
    // Vérifier la TVA
    if (isset($data['tva']) && ($data['tva'] < 0 || $data['tva'] > 100)) {
        $errors[] = "La TVA doit être entre 0 et 100%.";
    }
    
    // Vérifier la quantité
    if (isset($data['quantite_stock']) && $data['quantite_stock'] < 0) {
        $errors[] = "La quantité en stock ne peut pas être négative.";
    }
    
    return $errors;
}

/**
 * Supprimer un produit
 */
function deleteProduit($id) {
    global $db;
    
    try {
        // Vérifier si le produit existe
        $stmt = $db->prepare("SELECT id_produit FROM produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            return [
                'success' => false,
                'message' => 'Produit introuvable.'
            ];
        }
        
        // Supprimer le produit
        $stmt = $db->prepare("DELETE FROM produits WHERE id_produit = ?");
        $stmt->execute([$id]);
        
        return [
            'success' => true,
            'message' => 'Produit supprimé avec succès.'
        ];
    } catch (PDOException $e) {
        return [
            'success' => false,
            'message' => 'Erreur de suppression: ' . $e->getMessage()
        ];
    }
}

/**
 * Générer un slug à partir d'un nom
 */
function generateSlug($nom) {
    $slug = strtolower($nom);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .admin-header {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .page-title {
            color: #333;
            margin: 0;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-title i {
            color: #8a4baf;
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 10px 0 0 0;
        }
        
        .breadcrumb-item a {
            color: #8a4baf;
            text-decoration: none;
        }
        
        .admin-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .card-title {
            color: #333;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(to right, #8a4baf, #ff6b8b);
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
        }
        
        .btn-warning {
            background: #ffc107;
            border: none;
            color: #000;
        }
        
        .btn-danger {
            background: #dc3545;
            border: none;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-active {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-rupture {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-bientot {
            background: #cce5ff;
            color: #004085;
        }
        
        .stock-low {
            color: #dc3545;
            font-weight: bold;
        }
        
        .stock-ok {
            color: #28a745;
        }
        
        .stock-zero {
            color: #6c757d;
            font-style: italic;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #8a4baf;
            box-shadow: 0 0 0 3px rgba(138, 75, 175, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .product-image-preview {
            max-width: 200px;
            height: auto;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            padding-right: 40px;
        }
        
        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 30px;
        }
        
        .stats-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }
            
            .admin-card {
                padding: 20px;
            }
            
            .table-responsive {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- En-tête -->
        <header class="admin-header">
            <h1 class="page-title">
                <i class="fas fa-box"></i>
                <?php echo htmlspecialchars($page_title); ?>
            </h1>
            
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home"></i> Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="admin_produits.php">Produits</a></li>
                    <?php if ($action !== 'list'): ?>
                    <li class="breadcrumb-item active" aria-current="page">
                        <?php echo htmlspecialchars(ucfirst($action)); ?>
                    </li>
                    <?php endif; ?>
                </ol>
            </nav>
            
            <!-- Info utilisateur -->
            <div class="alert alert-info mt-3">
                <i class="fas fa-user"></i> Connecté en tant que: 
                <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong>
                | Rôle: <strong><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'admin'); ?></strong>
            </div>
        </header>
        
        <!-- Messages d'alerte -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- Contenu principal selon l'action -->
        <?php if ($action === 'list'): ?>
        <!-- ============ LISTE DES PRODUITS ============ -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-box">
                    <div class="stats-number"><?php echo count($produits); ?></div>
                    <div class="stats-label">Produits total</div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $actifs = array_filter($produits, function($p) { return $p['statut'] === 'actif'; });
                ?>
                <div class="stats-box" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="stats-number"><?php echo count($actifs); ?></div>
                    <div class="stats-label">Produits actifs</div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $rupture = array_filter($produits, function($p) { return $p['quantite_stock'] <= 0; });
                ?>
                <div class="stats-box" style="background: linear-gradient(135deg, #dc3545, #e83e8c);">
                    <div class="stats-number"><?php echo count($rupture); ?></div>
                    <div class="stats-label">En rupture</div>
                </div>
            </div>
            <div class="col-md-3">
                <?php 
                $alerte = array_filter($produits, function($p) { 
                    return $p['quantite_stock'] > 0 && $p['quantite_stock'] <= $p['seuil_alerte']; 
                });
                ?>
                <div class="stats-box" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="stats-number"><?php echo count($alerte); ?></div>
                    <div class="stats-label">Stock faible</div>
                </div>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="card-title">
                    <i class="fas fa-list"></i>
                    Liste des produits
                </h2>
                <a href="admin_produits.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau produit
                </a>
            </div>
            
            <!-- Barre de recherche -->
            <div class="search-box">
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un produit...">
                <i class="fas fa-search"></i>
            </div>
            
            <!-- Table des produits -->
            <div class="table-responsive">
                <table class="table table-hover" id="productsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Référence</th>
                            <th>Nom</th>
                            <th>Catégorie</th>
                            <th>Prix</th>
                            <th>Stock</th>
                            <th>Statut</th>
                            <th>Date création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="9" class="text-center">
                                <div class="py-5">
                                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">Aucun produit trouvé</p>
                                    <a href="admin_produits.php?action=add" class="btn btn-primary mt-2">
                                        <i class="fas fa-plus"></i> Ajouter un produit
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($produits as $produit): ?>
                            <tr>
                                <td><?php echo $produit['id_produit']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($produit['reference']); ?></strong>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($produit['nom']); ?></strong>
                                    <?php if ($produit['marque']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($produit['marque']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé'); ?>
                                </td>
                                <td>
                                    <strong><?php echo number_format($produit['prix_ttc'], 2, ',', ' '); ?> €</strong>
                                    <br><small class="text-muted">HT: <?php echo number_format($produit['prix_ht'], 2, ',', ' '); ?> €</small>
                                </td>
                                <td>
                                    <?php
                                    $stockClass = 'stock-ok';
                                    if ($produit['quantite_stock'] <= 0) {
                                        $stockClass = 'stock-zero';
                                    } elseif ($produit['quantite_stock'] <= $produit['seuil_alerte']) {
                                        $stockClass = 'stock-low';
                                    }
                                    ?>
                                    <span class="<?php echo $stockClass; ?>">
                                        <?php echo $produit['quantite_stock']; ?>
                                    </span>
                                    <?php if ($produit['quantite_stock'] <= $produit['seuil_alerte'] && $produit['quantite_stock'] > 0): ?>
                                    <br><small class="text-danger">Seuil: <?php echo $produit['seuil_alerte']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badgeClass = 'badge-active';
                                    switch ($produit['statut']) {
                                        case 'inactif': $badgeClass = 'badge-inactive'; break;
                                        case 'rupture': $badgeClass = 'badge-rupture'; break;
                                        case 'bientot': $badgeClass = 'badge-bientot'; break;
                                    }
                                    ?>
                                    <span class="badge-status <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($produit['statut']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($produit['date_creation'])); ?>
                                    <br><small class="text-muted"><?php echo date('H:i', strtotime($produit['date_creation'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="admin_produits.php?action=edit&id=<?php echo $produit['id_produit']; ?>" 
                                           class="btn btn-warning" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-danger delete-btn" 
                                                title="Supprimer"
                                                data-id="<?php echo $produit['id_produit']; ?>"
                                                data-name="<?php echo htmlspecialchars($produit['nom']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (simplifiée) -->
            <?php if (count($produits) > 20): ?>
            <nav aria-label="Navigation des produits">
                <ul class="pagination">
                    <li class="page-item disabled">
                        <a class="page-link" href="#" tabindex="-1">Précédent</a>
                    </li>
                    <li class="page-item active"><a class="page-link" href="#">1</a></li>
                    <li class="page-item"><a class="page-link" href="#">2</a></li>
                    <li class="page-item"><a class="page-link" href="#">3</a></li>
                    <li class="page-item">
                        <a class="page-link" href="#">Suivant</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- ============ FORMULAIRE AJOUT/MODIFICATION ============ -->
        <div class="admin-card">
            <h2 class="card-title">
                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                <?php echo $action === 'add' ? 'Ajouter un produit' : 'Modifier le produit'; ?>
            </h2>
            
            <form method="POST" action="" id="produitForm">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id_produit" value="<?php echo $produit['id_produit']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <!-- Colonne gauche -->
                    <div class="col-md-8">
                        <!-- Informations de base -->
                        <div class="mb-4">
                            <h4><i class="fas fa-info-circle"></i> Informations de base</h4>
                            <div class="row mt-3">
                                <div class="col-md-6 mb-3">
                                    <label for="reference" class="form-label">Référence *</label>
                                    <input type="text" 
                                           id="reference" 
                                           name="reference" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($produit['reference'] ?? ''); ?>"
                                           required>
                                    <small class="text-muted">Ex: PROD-001, unique, 3-50 caractères</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom du produit *</label>
                                    <input type="text" 
                                           id="nom" 
                                           name="nom" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($produit['nom'] ?? ''); ?>"
                                           required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="slug" class="form-label">Slug *</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               id="slug" 
                                               name="slug" 
                                               class="form-control" 
                                               value="<?php echo htmlspecialchars($produit['slug'] ?? ''); ?>"
                                               required>
                                        <button type="button" class="btn btn-outline-secondary" id="generateSlug">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">URL-friendly, ex: cadeau-anniversaire-personnalise</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="id_categorie" class="form-label">Catégorie *</label>
                                    <select id="id_categorie" name="id_categorie" class="form-select" required>
                                        <option value="">Sélectionner une catégorie</option>
                                        <?php foreach ($categories as $categorie): ?>
                                        <option value="<?php echo $categorie['id_categorie']; ?>"
                                                <?php echo (isset($produit['id_categorie']) && $produit['id_categorie'] == $categorie['id_categorie']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categorie['nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="marque" class="form-label">Marque</label>
                                    <input type="text" 
                                           id="marque" 
                                           name="marque" 
                                           class="form-control" 
                                           value="<?php echo htmlspecialchars($produit['marque'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="statut" class="form-label">Statut *</label>
                                    <select id="statut" name="statut" class="form-select" required>
                                        <option value="actif" <?php echo (isset($produit['statut']) && $produit['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                        <option value="inactif" <?php echo (isset($produit['statut']) && $produit['statut'] === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                        <option value="rupture" <?php echo (isset($produit['statut']) && $produit['statut'] === 'rupture') ? 'selected' : ''; ?>>Rupture de stock</option>
                                        <option value="bientot" <?php echo (isset($produit['statut']) && $produit['statut'] === 'bientot') ? 'selected' : ''; ?>>Bientôt disponible</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Prix et stock -->
                        <div class="mb-4">
                            <h4><i class="fas fa-euro-sign"></i> Prix et stock</h4>
                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <label for="prix_ht" class="form-label">Prix HT *</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               id="prix_ht" 
                                               name="prix_ht" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0"
                                               value="<?php echo isset($produit['prix_ht']) ? $produit['prix_ht'] : '0'; ?>"
                                               required>
                                        <span class="input-group-text">€</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="tva" class="form-label">TVA (%)</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               id="tva" 
                                               name="tva" 
                                               class="form-control" 
                                               step="0.01" 
                                               min="0" 
                                               max="100"
                                               value="<?php echo isset($produit['tva']) ? $produit['tva'] : '20.00'; ?>">
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="prix_ttc_display" class="form-label">Prix TTC</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               id="prix_ttc_display" 
                                               class="form-control" 
                                               readonly>
                                        <span class="input-group-text">€</span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="quantite_stock" class="form-label">Quantité en stock</label>
                                    <input type="number" 
                                           id="quantite_stock" 
                                           name="quantite_stock" 
                                           class="form-control" 
                                           min="0"
                                           value="<?php echo isset($produit['quantite_stock']) ? $produit['quantite_stock'] : '0'; ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="seuil_alerte" class="form-label">Seuil d'alerte stock</label>
                                    <input type="number" 
                                           id="seuil_alerte" 
                                           name="seuil_alerte" 
                                           class="form-control" 
                                           min="0"
                                           value="<?php echo isset($produit['seuil_alerte']) ? $produit['seuil_alerte'] : '10'; ?>">
                                    <small class="text-muted">Alerte quand stock ≤ cette valeur</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Descriptions -->
                        <div class="mb-4">
                            <h4><i class="fas fa-align-left"></i> Descriptions</h4>
                            <div class="row mt-3">
                                <div class="col-12 mb-3">
                                    <label for="description_courte" class="form-label">Description courte</label>
                                    <textarea id="description_courte" 
                                              name="description_courte" 
                                              class="form-control" 
                                              rows="3"
                                              placeholder="Brève description pour les listes de produits"><?php echo htmlspecialchars($produit['description_courte'] ?? ''); ?></textarea>
                                    <small class="text-muted">Maximum 255 caractères</small>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="description" class="form-label">Description complète</label>
                                    <textarea id="description" 
                                              name="description" 
                                              class="form-control" 
                                              rows="6"
                                              placeholder="Description détaillée du produit"><?php echo htmlspecialchars($produit['description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Colonne droite -->
                    <div class="col-md-4">
                        <!-- Caractéristiques -->
                        <div class="mb-4">
                            <h4><i class="fas fa-bolt"></i> Caractéristiques</h4>
                            <div class="checkbox-group mt-3">
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="personnalisable" 
                                           name="personnalisable" 
                                           value="1"
                                           <?php echo (isset($produit['personnalisable']) && $produit['personnalisable']) ? 'checked' : ''; ?>>
                                    <label for="personnalisable">Personnalisable</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="ecologique" 
                                           name="ecologique" 
                                           value="1"
                                           <?php echo (isset($produit['ecologique']) && $produit['ecologique']) ? 'checked' : ''; ?>>
                                    <label for="ecologique">Écologique</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="made_in_france" 
                                           name="made_in_france" 
                                           value="1"
                                           <?php echo (isset($produit['made_in_france']) && $produit['made_in_france']) ? 'checked' : ''; ?>>
                                    <label for="made_in_france">Fabriqué en France</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="artisanal" 
                                           name="artisanal" 
                                           value="1"
                                           <?php echo (isset($produit['artisanal']) && $produit['artisanal']) ? 'checked' : ''; ?>>
                                    <label for="artisanal">Artisanal</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" 
                                           id="exclusif" 
                                           name="exclusif" 
                                           value="1"
                                           <?php echo (isset($produit['exclusif']) && $produit['exclusif']) ? 'checked' : ''; ?>>
                                    <label for="exclusif">Exclusif</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Détails techniques -->
                        <div class="mb-4">
                            <h4><i class="fas fa-cogs"></i> Détails techniques</h4>
                            <div class="row mt-3">
                                <div class="col-12 mb-3">
                                    <label for="poids" class="form-label">Poids (grammes)</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               id="poids" 
                                               name="poids" 
                                               class="form-control" 
                                               step="1" 
                                               min="0"
                                               value="<?php echo $produit['poids'] ?? ''; ?>">
                                        <span class="input-group-text">g</span>
                                    </div>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="dimensions" class="form-label">Dimensions (L x H x P)</label>
                                    <input type="text" 
                                           id="dimensions" 
                                           name="dimensions" 
                                           class="form-control" 
                                           placeholder="ex: 15x20x5"
                                           value="<?php echo htmlspecialchars($produit['dimensions'] ?? ''); ?>">
                                    <small class="text-muted">en centimètres</small>
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="materiau" class="form-label">Matériau principal</label>
                                    <input type="text" 
                                           id="materiau" 
                                           name="materiau" 
                                           class="form-control" 
                                           placeholder="ex: Bois, Métal, Verre"
                                           value="<?php echo htmlspecialchars($produit['materiau'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="couleur" class="form-label">Couleur principale</label>
                                    <input type="text" 
                                           id="couleur" 
                                           name="couleur" 
                                           class="form-control" 
                                           placeholder="ex: Blanc, Noir, Doré"
                                           value="<?php echo htmlspecialchars($produit['couleur'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12 mb-3">
                                    <label for="made_in" class="form-label">Origine de fabrication</label>
                                    <input type="text" 
                                           id="made_in" 
                                           name="made_in" 
                                           class="form-control" 
                                           placeholder="ex: France, Chine, Italie"
                                           value="<?php echo htmlspecialchars($produit['made_in'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Statistiques (édition seulement) -->
                        <?php if ($action === 'edit'): ?>
                        <div class="mb-4">
                            <h4><i class="fas fa-chart-bar"></i> Statistiques</h4>
                            <div class="row mt-3">
                                <div class="col-12 mb-2">
                                    <strong>Vues :</strong> <?php echo $produit['vues'] ?? 0; ?>
                                </div>
                                <div class="col-12 mb-2">
                                    <strong>Ventes :</strong> <?php echo $produit['ventes'] ?? 0; ?>
                                </div>
                                <div class="col-12 mb-2">
                                    <strong>Note moyenne :</strong> 
                                    <?php if ($produit['note_moyenne'] > 0): ?>
                                    <?php echo number_format($produit['note_moyenne'], 2); ?>/5
                                    (<?php echo $produit['nombre_avis'] ?? 0; ?> avis)
                                    <?php else: ?>
                                    Pas encore noté
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Boutons d'action -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between">
                            <a href="admin_produits.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 
                                <?php echo $action === 'add' ? 'Créer le produit' : 'Mettre à jour'; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <?php endif; ?>
    </div>
    
    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le produit <strong id="deleteProductName"></strong> ?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Cette action est irréversible !</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" action="" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_produit" id="deleteProductId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer définitivement
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Scripts personnalisés -->
    <script>
    // Calcul automatique du prix TTC
    function calculateTTC() {
        const prixHT = parseFloat(document.getElementById('prix_ht').value) || 0;
        const tva = parseFloat(document.getElementById('tva').value) || 20;
        const prixTTC = prixHT * (1 + tva / 100);
        document.getElementById('prix_ttc_display').value = prixTTC.toFixed(2);
    }
    
    // Écouteurs pour le calcul du prix TTC
    document.getElementById('prix_ht').addEventListener('input', calculateTTC);
    document.getElementById('tva').addEventListener('input', calculateTTC);
    
    // Calcul initial
    calculateTTC();
    
    // Génération de slug
    document.getElementById('generateSlug').addEventListener('click', function() {
        const nom = document.getElementById('nom').value;
        if (nom) {
            let slug = nom.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Enlever les accents
                .replace(/[^a-z0-9]+/g, '-') // Remplacer les non-alphanum par des tirets
                .replace(/^-+|-+$/g, '') // Enlever les tirets en début et fin
                .substring(0, 100); // Limiter la longueur
            
            document.getElementById('slug').value = slug;
        }
    });
    
    // Recherche dans le tableau
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = document.querySelectorAll('#productsTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });
    
    // Gestion de la suppression
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            
            document.getElementById('deleteProductId').value = productId;
            document.getElementById('deleteProductName').textContent = productName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        });
    });
    
    // Validation du formulaire
    document.getElementById('produitForm')?.addEventListener('submit', function(e) {
        const reference = document.getElementById('reference').value;
        const nom = document.getElementById('nom').value;
        const slug = document.getElementById('slug').value;
        const prixHT = document.getElementById('prix_ht').value;
        
        // Validation basique
        if (!reference.match(/^[A-Z0-9-]{3,50}$/i)) {
            e.preventDefault();
            alert('La référence doit contenir 3 à 50 caractères (lettres, chiffres, tirets).');
            document.getElementById('reference').focus();
            return false;
        }
        
        if (!slug.match(/^[a-z0-9-]{3,100}$/)) {
            e.preventDefault();
            alert('Le slug doit contenir 3 à 100 caractères (lettres minuscules, chiffres, tirets).');
            document.getElementById('slug').focus();
            return false;
        }
        
        if (parseFloat(prixHT) < 0) {
            e.preventDefault();
            alert('Le prix ne peut pas être négatif.');
            document.getElementById('prix_ht').focus();
            return false;
        }
        
        return true;
    });
    
    // Limiteur de caractères pour la description courte
    const descCourte = document.getElementById('description_courte');
    if (descCourte) {
        descCourte.addEventListener('input', function() {
            if (this.value.length > 255) {
                this.value = this.value.substring(0, 255);
                alert('La description courte est limitée à 255 caractères.');
            }
        });
    }
    
    // Affichage des informations de connexion
    console.log('Admin connecté:', '<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>');
    </script>
</body>
</html>