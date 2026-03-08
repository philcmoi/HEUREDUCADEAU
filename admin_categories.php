<?php
// admin_categories.php - Gestion complète des catégories (CRUD)
// VERSION CORRIGÉE ET RESPONSIVE

require_once 'admin_protection.php';

// ============================================
// CONFIGURATION
// ============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

// Récupérer les informations de l'admin depuis la session
$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';

// ============================================
// FONCTIONS CRUD CATÉGORIES
// ============================================

/**
 * Récupère toutes les catégories avec pagination et filtres
 */
function getAllCategories($pdo, $page = 1, $limit = 20, $filtres = []) {
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                c.*,
                c2.nom as parent_nom,
                (SELECT COUNT(*) FROM produits WHERE id_categorie = c.id_categorie) as nb_produits,
                (SELECT COUNT(*) FROM categories WHERE parent_id = c.id_categorie) as nb_sous_categories
            FROM categories c
            LEFT JOIN categories c2 ON c.parent_id = c2.id_categorie
            WHERE 1=1";
    
    $params = [];
    
    // Filtre par recherche (nom)
    if (!empty($filtres['search'])) {
        $sql .= " AND (c.nom LIKE :search OR c.description LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    // Filtre par statut actif/inactif
    if (isset($filtres['active']) && $filtres['active'] !== '') {
        $sql .= " AND c.active = :active";
        $params['active'] = $filtres['active'];
    }
    
    // Tri
    $sql .= " ORDER BY c.ordre, c.nom LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Binding des paramètres
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Compte le nombre total de catégories (pour pagination)
 */
function countCategories($pdo, $filtres = []) {
    $sql = "SELECT COUNT(*) as total FROM categories WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtres['search'])) {
        $sql .= " AND (nom LIKE :search OR description LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    if (isset($filtres['active']) && $filtres['active'] !== '') {
        $sql .= " AND active = :active";
        $params['active'] = $filtres['active'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

/**
 * Récupère une catégorie par son ID
 */
function getCategoryById($pdo, $id) {
    $sql = "SELECT c.*, c2.nom as parent_nom 
            FROM categories c
            LEFT JOIN categories c2 ON c.parent_id = c2.id_categorie
            WHERE c.id_categorie = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

/**
 * Récupère toutes les catégories pour le sélecteur de parent
 */
function getParentCategories($pdo, $exclude_id = null) {
    $sql = "SELECT id_categorie, nom, ordre FROM categories WHERE 1=1";
    
    if ($exclude_id) {
        $sql .= " AND id_categorie != :exclude_id";
    }
    
    $sql .= " ORDER BY ordre, nom";
    
    $stmt = $pdo->prepare($sql);
    
    if ($exclude_id) {
        $stmt->execute(['exclude_id' => $exclude_id]);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

/**
 * Ajoute une catégorie
 */
function addCategory($pdo, $data) {
    $sql = "INSERT INTO categories (
                nom, slug, description, parent_id, ordre, active,
                meta_titre, meta_description
            ) VALUES (
                :nom, :slug, :description, :parent_id, :ordre, :active,
                :meta_titre, :meta_description
            )";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

/**
 * Met à jour une catégorie
 */
function updateCategory($pdo, $id, $data) {
    $data['id_categorie'] = $id;
    
    $sql = "UPDATE categories SET 
                nom = :nom,
                slug = :slug,
                description = :description,
                parent_id = :parent_id,
                ordre = :ordre,
                active = :active,
                meta_titre = :meta_titre,
                meta_description = :meta_description
            WHERE id_categorie = :id_categorie";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

/**
 * Supprime une catégorie
 */
function deleteCategory($pdo, $id) {
    try {
        $pdo->beginTransaction();
        
        // Mettre à jour les produits qui utilisaient cette catégorie
        $sql = "UPDATE produits SET id_categorie = NULL WHERE id_categorie = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        // Mettre à jour les sous-catégories
        $sql = "UPDATE categories SET parent_id = NULL WHERE parent_id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        // Supprimer la catégorie
        $sql = "DELETE FROM categories WHERE id_categorie = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression catégorie: " . $e->getMessage());
        return false;
    }
}

/**
 * Génère un slug à partir du nom
 */
function generateSlug($nom) {
    $slug = strtolower($nom);
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Vérifie si un slug existe déjà
 */
function slugExists($pdo, $slug, $exclude_id = null) {
    $sql = "SELECT COUNT(*) as count FROM categories WHERE slug = :slug";
    
    if ($exclude_id) {
        $sql .= " AND id_categorie != :exclude_id";
    }
    
    $stmt = $pdo->prepare($sql);
    $params = ['slug' => $slug];
    
    if ($exclude_id) {
        $params['exclude_id'] = $exclude_id;
    }
    
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Récupère les statistiques des catégories
 */
function getCategoriesStats($pdo) {
    $stats = [];
    
    // Total catégories
    $sql = "SELECT COUNT(*) as total FROM categories";
    $stmt = $pdo->query($sql);
    $stats['total'] = $stmt->fetch()['total'];
    
    // Catégories actives vs inactives
    $sql = "SELECT 
            SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as actives,
            SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactives
            FROM categories";
    $stmt = $pdo->query($sql);
    $stats['statut'] = $stmt->fetch();
    
    // Catégories avec produits
    $sql = "SELECT COUNT(DISTINCT c.id_categorie) as avec_produits
            FROM categories c
            INNER JOIN produits p ON c.id_categorie = p.id_categorie";
    $stmt = $pdo->query($sql);
    $stats['avec_produits'] = $stmt->fetch()['avec_produits'] ?? 0;
    
    // Catégories sans produits
    $sql = "SELECT COUNT(*) as sans_produits
            FROM categories c
            LEFT JOIN produits p ON c.id_categorie = p.id_categorie
            WHERE p.id_produit IS NULL";
    $stmt = $pdo->query($sql);
    $stats['sans_produits'] = $stmt->fetch()['sans_produits'] ?? 0;
    
    // Nombre total de produits dans les catégories
    $sql = "SELECT COUNT(*) as total FROM produits WHERE id_categorie IS NOT NULL";
    $stmt = $pdo->query($sql);
    $stats['produits_categorises'] = $stmt->fetch()['total'];
    
    return $stats;
}

/**
 * Récupère l'arborescence des catégories
 */
function getCategoryTree($pdo, $parent_id = null, $level = 0) {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM categories WHERE parent_id = c.id_categorie) as nb_enfants,
            (SELECT COUNT(*) FROM produits WHERE id_categorie = c.id_categorie) as nb_produits
            FROM categories c
            WHERE ";
    
    if ($parent_id === null) {
        $sql .= "c.parent_id IS NULL";
    } else {
        $sql .= "c.parent_id = :parent_id";
    }
    
    $sql .= " ORDER BY c.ordre, c.nom";
    
    $stmt = $pdo->prepare($sql);
    
    if ($parent_id !== null) {
        $stmt->execute(['parent_id' => $parent_id]);
    } else {
        $stmt->execute();
    }
    
    $categories = $stmt->fetchAll();
    
    foreach ($categories as &$categorie) {
        $categorie['niveau'] = $level;
        if ($categorie['nb_enfants'] > 0) {
            $categorie['enfants'] = getCategoryTree($pdo, $categorie['id_categorie'], $level + 1);
        }
    }
    
    return $categories;
}

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================

// Récupérer les filtres depuis l'URL
$filtres = [
    'search' => $_GET['search'] ?? '',
    'active' => isset($_GET['active']) ? (int)$_GET['active'] : ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // AJOUT CATÉGORIE
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $nom = trim($_POST['nom']);
        $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : generateSlug($nom);
        
        // Vérifier si le slug existe déjà
        if (slugExists($pdo, $slug)) {
            $_SESSION['error'] = "Ce slug existe déjà. Veuillez en choisir un autre.";
            header('Location: admin_categories.php?action=add');
            exit;
        }
        
        $data = [
            'nom' => $nom,
            'slug' => $slug,
            'description' => $_POST['description'] ?? null,
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'ordre' => (int)$_POST['ordre'],
            'active' => isset($_POST['active']) ? 1 : 0,
            'meta_titre' => $_POST['meta_titre'] ?? null,
            'meta_description' => $_POST['meta_description'] ?? null
        ];
        
        if (addCategory($pdo, $data)) {
            $newId = $pdo->lastInsertId();
            logAction('info', 'Catégorie ajoutée', [
                'categorie_id' => $newId,
                'nom' => $nom,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Catégorie '$nom' ajoutée avec succès.";
            header('Location: admin_categories.php?action=list');
            exit;
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout de la catégorie.";
        }
    }
    
    // MODIFICATION CATÉGORIE
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id_categorie']);
        $nom = trim($_POST['nom']);
        $slug = !empty($_POST['slug']) ? trim($_POST['slug']) : generateSlug($nom);
        
        // Vérifier si le slug existe déjà (pour un autre ID)
        if (slugExists($pdo, $slug, $id)) {
            $_SESSION['error'] = "Ce slug existe déjà. Veuillez en choisir un autre.";
            header('Location: admin_categories.php?action=edit&id=' . $id);
            exit;
        }
        
        $data = [
            'nom' => $nom,
            'slug' => $slug,
            'description' => $_POST['description'] ?? null,
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'ordre' => (int)$_POST['ordre'],
            'active' => isset($_POST['active']) ? 1 : 0,
            'meta_titre' => $_POST['meta_titre'] ?? null,
            'meta_description' => $_POST['meta_description'] ?? null
        ];
        
        if (updateCategory($pdo, $id, $data)) {
            logAction('info', 'Catégorie modifiée', [
                'categorie_id' => $id,
                'nom' => $nom,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Catégorie #$id modifiée avec succès.";
            header('Location: admin_categories.php?action=view&id=' . $id);
            exit;
        } else {
            $_SESSION['error'] = "Erreur lors de la modification de la catégorie.";
        }
    }
    
    // SUPPRESSION CATÉGORIE
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id_categorie']);
        
        // Récupérer le nom pour le message
        $categorie = getCategoryById($pdo, $id);
        $nom = $categorie ? $categorie['nom'] : "ID $id";
        
        if (deleteCategory($pdo, $id)) {
            logAction('info', 'Catégorie supprimée', [
                'categorie_id' => $id,
                'nom' => $nom,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Catégorie '$nom' supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression de la catégorie.";
        }
        
        header('Location: admin_categories.php?action=list');
        exit;
    }
}

// Récupérer les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Récupérer les statistiques
$stats = getCategoriesStats($pdo);
$total_categories = countCategories($pdo, $filtres);
$total_pages = ceil($total_categories / $limit);

// Récupérer les catégories selon l'action
$categories = [];
if ($action === 'list') {
    $categories = getAllCategories($pdo, $page, $limit, $filtres);
} elseif ($action === 'view' && $id > 0) {
    $categorie = getCategoryById($pdo, $id);
    if (!$categorie) {
        $error = "Catégorie non trouvée.";
        $action = 'list';
    } else {
        // Récupérer les produits de cette catégorie
        $sql = "SELECT id_produit, reference, nom, prix_ttc, quantite_stock, statut 
                FROM produits 
                WHERE id_categorie = :id_categorie
                ORDER BY date_creation DESC
                LIMIT 20";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_categorie' => $id]);
        $categorie['produits'] = $stmt->fetchAll();
        
        // Récupérer les sous-catégories
        $sql = "SELECT id_categorie, nom, active 
                FROM categories 
                WHERE parent_id = :parent_id
                ORDER BY ordre, nom";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['parent_id' => $id]);
        $categorie['sous_categories'] = $stmt->fetchAll();
    }
} elseif ($action === 'edit' && $id > 0) {
    $categorie = getCategoryById($pdo, $id);
    if (!$categorie) {
        $error = "Catégorie non trouvée.";
        $action = 'list';
    }
}

// Récupérer les catégories parentes pour les formulaires
$parent_categories = getParentCategories($pdo, $action === 'edit' ? $id : null);

// Récupérer l'arborescence pour la vue hiérarchique
$tree_categories = ($action === 'tree') ? getCategoryTree($pdo) : [];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestion des Catégories - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           STYLES RESPONSIVES OPTIMISÉS POUR MOBILE/TABLETTE/DESKTOP
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #6a11cb;
            --primary-gradient: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            --success-color: #4CAF50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #17a2b8;
            --dark-color: #333;
            --light-bg: #f5f7fa;
            --border-color: #dee2e6;
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-lg: 0 4px 20px rgba(0,0,0,0.1);
            --border-radius: 10px;
            --border-radius-sm: 6px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-color);
            line-height: 1.6;
            font-size: 16px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
        }
        
        @media (min-width: 768px) {
            .container { padding: 20px; }
        }
        
        /* Header responsive */
        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 25px;
            }
        }
        
        .header h1 { 
            font-size: 24px; 
            font-weight: 600; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (min-width: 768px) {
            .header h1 { font-size: 28px; }
        }
        
        .role-badge {
            background-color: var(--success-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }
        
        .superadmin-badge { background-color: var(--danger-color); }
        
        /* Navigation responsive */
        .nav-tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        
        .nav-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .nav-tabs::-webkit-scrollbar-thumb {
            background-color: #ccc;
            border-radius: 4px;
        }
        
        .nav-tabs a {
            padding: 15px 20px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 14px;
        }
        
        @media (min-width: 768px) {
            .nav-tabs a {
                padding: 18px 25px;
                font-size: 16px;
            }
        }
        
        .nav-tabs a:hover { 
            background-color: #f8f9fa; 
            color: var(--primary-color); 
        }
        
        .nav-tabs a.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            background-color: #f0f8ff;
        }
        
        /* Alertes */
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            word-break: break-word;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Stats cards responsives */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--shadow-md);
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card.total { border-left-color: #2196F3; }
        .stat-card.active { border-left-color: var(--success-color); }
        .stat-card.inactive { border-left-color: var(--danger-color); }
        .stat-card.products { border-left-color: var(--warning-color); }
        .stat-card.empty { border-left-color: #9C27B0; }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-color);
        }
        
        @media (min-width: 768px) {
            .stat-value { font-size: 28px; }
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Filtres responsifs */
        .filters-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
        }
        
        .filters-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .filters-form {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: flex-end;
            }
        }
        
        .filter-group {
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .filter-group {
                flex: 1;
                min-width: 150px;
            }
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 16px;
            background-color: white;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }
        
        @media (min-width: 768px) {
            .filter-actions {
                margin-left: auto;
                margin-top: 0;
            }
        }
        
        /* Boutons responsifs */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            width: 100%;
            text-align: center;
        }
        
        @media (min-width: 768px) {
            .btn {
                width: auto;
                padding: 10px 20px;
            }
        }
        
        .btn-sm { 
            padding: 8px 12px; 
            font-size: 14px; 
        }
        
        .btn-primary { background-color: var(--primary-color); color: white; }
        .btn-primary:hover { background-color: #5a0cb3; }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-warning { background-color: var(--warning-color); color: white; }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-info { background-color: var(--info-color); color: white; }
        
        /* Table responsive */
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
        }
        
        .table-header {
            background-color: #f8f9fa;
            padding: 15px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border-bottom: 1px solid #eee;
        }
        
        @media (min-width: 768px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 20px;
            }
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        @media (max-width: 767px) {
            table {
                font-size: 14px;
            }
        }
        
        th {
            background-color: #f1f5fd;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }
        
        td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        tr:hover { background-color: #f9f9f9; }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }
        
        @media (min-width: 768px) {
            .badge {
                padding: 5px 12px;
                font-size: 12px;
            }
        }
        
        .badge.active { background: #d4edda; color: #155724; }
        .badge.inactive { background: #e2e3e5; color: #383d41; }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .actions .btn-sm {
                padding: 8px;
                font-size: 12px;
            }
        }
        
        /* Pagination responsive */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px;
            margin: 20px 0;
        }
        
        .page-link {
            padding: 8px 12px;
            background: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        @media (min-width: 768px) {
            .page-link {
                padding: 10px 15px;
            }
        }
        
        .page-link:hover { background: #f0f0f0; }
        
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-link.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        /* Detail catégorie responsive */
        .detail-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow-md);
        }
        
        @media (min-width: 768px) {
            .detail-container {
                padding: 30px;
            }
        }
        
        .detail-header {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        @media (min-width: 768px) {
            .detail-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
            }
        }
        
        .categorie-nom {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            word-break: break-word;
        }
        
        @media (min-width: 768px) {
            .categorie-nom {
                font-size: 24px;
            }
        }
        
        .categorie-slug {
            color: #7f8c8d;
            font-size: 13px;
            word-break: break-word;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 30px;
                margin-bottom: 30px;
            }
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .info-card h3 {
            margin-bottom: 10px;
            color: #2c3e50;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-row {
            display: flex;
            flex-direction: column;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        @media (min-width: 768px) {
            .info-row {
                flex-direction: row;
            }
        }
        
        .info-label {
            font-weight: 500;
            color: #6c757d;
            margin-bottom: 3px;
        }
        
        @media (min-width: 768px) {
            .info-label {
                width: 120px;
                margin-bottom: 0;
            }
        }
        
        .info-value {
            flex: 1;
            font-weight: 500;
            word-break: break-word;
        }
        
        /* Arborescence responsive */
        .tree-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px;
            box-shadow: var(--shadow-md);
        }
        
        .tree-item {
            margin: 5px 0;
        }
        
        .tree-item.level-0 { margin-left: 0; }
        .tree-item.level-1 { margin-left: 15px; }
        .tree-item.level-2 { margin-left: 30px; }
        .tree-item.level-3 { margin-left: 45px; }
        .tree-item.level-4 { margin-left: 60px; }
        .tree-item.level-5 { margin-left: 75px; }
        
        @media (min-width: 768px) {
            .tree-item.level-1 { margin-left: 30px; }
            .tree-item.level-2 { margin-left: 60px; }
            .tree-item.level-3 { margin-left: 90px; }
            .tree-item.level-4 { margin-left: 120px; }
            .tree-item.level-5 { margin-left: 150px; }
        }
        
        .tree-row {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 10px;
            background: #f8f9fa;
            border-radius: var(--border-radius-sm);
            margin-bottom: 5px;
            transition: all 0.3s;
            gap: 8px;
        }
        
        @media (min-width: 768px) {
            .tree-row {
                flex-direction: row;
                align-items: center;
                padding: 10px 15px;
            }
        }
        
        .tree-row:hover {
            background: #e9ecef;
        }
        
        .tree-icon {
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--primary-color);
        }
        
        .tree-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 5px;
            width: 100%;
        }
        
        @media (min-width: 768px) {
            .tree-info {
                flex-direction: row;
                align-items: center;
                gap: 20px;
                width: auto;
            }
        }
        
        .tree-name {
            font-weight: 600;
            color: #2c3e50;
            word-break: break-word;
        }
        
        .tree-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            color: #6c757d;
            font-size: 12px;
        }
        
        @media (min-width: 768px) {
            .tree-meta {
                flex-direction: row;
                gap: 15px;
                font-size: 13px;
            }
        }
        
        .tree-actions {
            display: flex;
            gap: 5px;
            width: 100%;
            justify-content: flex-start;
        }
        
        @media (min-width: 768px) {
            .tree-actions {
                width: auto;
                justify-content: flex-end;
            }
        }
        
        /* Formulaires responsifs */
        .form-container {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-md);
        }
        
        @media (min-width: 768px) {
            .form-container {
                padding: 30px;
            }
        }
        
        .form-group { margin-bottom: 15px; }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
            font-size: 15px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        textarea.form-control { min-height: 100px; resize: vertical; }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
        }
        
        /* Grille responsive pour les formulaires */
        .form-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .form-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .form-row-3 {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        .form-row-4 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .form-row-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* Modal responsive */
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
            padding: 15px;
        }
        
        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 20px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
        }
        
        @media (min-width: 768px) {
            .modal-content {
                padding: 30px;
            }
        }
        
        /* Utilitaires */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        
        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
        }
        
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        
        /* Loading spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Cards pour les stats */
        .stats-cards-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        @media (min-width: 480px) {
            .stats-cards-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .stats-cards-container {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-tags"></i> Gestion des Catégories</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($admin_username); ?></p>
            </div>
            <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> <span class="hide-mobile">Retour Dashboard</span>
            </a>
            <a href="admin_categories.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <span>Liste</span>
            </a>
            <a href="admin_categories.php?action=tree" class="<?php echo $action == 'tree' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap"></i> <span>Arborescence</span>
            </a>
            <a href="admin_categories.php?action=add" class="<?php echo $action == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> <span>Ajouter</span>
            </a>
            <a href="admin_categories.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span>Stats</span>
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

        <!-- STATS DASHBOARD -->
        <?php if ($action === 'stats'): ?>
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total catégories</div>
                </div>
                <div class="stat-card active">
                    <div class="stat-value"><?php echo number_format($stats['statut']['actives'] ?? 0); ?></div>
                    <div class="stat-label">Catégories actives</div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-value"><?php echo number_format($stats['statut']['inactives'] ?? 0); ?></div>
                    <div class="stat-label">Catégories inactives</div>
                </div>
                <div class="stat-card products">
                    <div class="stat-value"><?php echo number_format($stats['produits_categorises']); ?></div>
                    <div class="stat-label">Produits catégorisés</div>
                </div>
                <div class="stat-card empty">
                    <div class="stat-value"><?php echo number_format($stats['sans_produits'] ?? 0); ?></div>
                    <div class="stat-label">Catégories vides</div>
                </div>
            </div>
            
            <!-- Graphique simple -->
            <div class="form-container" style="margin-top: 20px;">
                <h3 style="margin-bottom: 20px;">Répartition des catégories</h3>
                <div>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; flex-wrap: wrap; gap: 5px;">
                            <span style="font-weight: 600;">Catégories avec produits</span>
                            <span><?php echo $stats['avec_produits']; ?> (<?php echo round(($stats['avec_produits'] / max($stats['total'], 1)) * 100); ?>%)</span>
                        </div>
                        <div style="height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo ($stats['avec_produits'] / max($stats['total'], 1)) * 100; ?>%; background-color: #4CAF50;"></div>
                        </div>
                    </div>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 5px; flex-wrap: wrap; gap: 5px;">
                            <span style="font-weight: 600;">Catégories sans produit</span>
                            <span><?php echo $stats['sans_produits']; ?> (<?php echo round(($stats['sans_produits'] / max($stats['total'], 1)) * 100); ?>%)</span>
                        </div>
                        <div style="height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo ($stats['sans_produits'] / max($stats['total'], 1)) * 100; ?>%; background-color: #FF9800;"></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- CONTENU SELON L'ACTION -->
        <?php if ($action == 'list'): ?>
            <!-- FILTRES -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <input type="hidden" name="action" value="list">
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Recherche</label>
                        <input type="text" name="search" placeholder="Nom de la catégorie..." 
                               value="<?php echo htmlspecialchars($filtres['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-toggle-on"></i> Statut</label>
                        <select name="active">
                            <option value="">Tous</option>
                            <option value="1" <?php echo $filtres['active'] === '1' ? 'selected' : ''; ?>>Actif</option>
                            <option value="0" <?php echo $filtres['active'] === '0' ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="admin_categories.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- LISTE DES CATÉGORIES -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-tags"></i> Liste des catégories (<?php echo $total_categories; ?>)</h3>
                    <a href="admin_categories.php?action=add" class="btn btn-primary" style="width: auto;">
                        <i class="fas fa-plus"></i> Nouvelle catégorie
                    </a>
                </div>
                
                <?php if (empty($categories)): ?>
                    <div class="text-center" style="padding: 40px 20px;">
                        <i class="fas fa-tags" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777; margin-bottom: 10px;">Aucune catégorie trouvée</h3>
                        <p style="color: #999; margin-bottom: 20px;">Commencez par créer votre première catégorie.</p>
                        <a href="admin_categories.php?action=add" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Créer une catégorie
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th class="hide-mobile">Slug</th>
                                    <th class="hide-mobile">Parent</th>
                                    <th class="hide-mobile">Ordre</th>
                                    <th>Produits</th>
                                    <th class="hide-mobile">Sous-cat.</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                <tr>
                                    <td>#<?php echo $cat['id_categorie']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cat['nom']); ?></strong>
                                        <div class="show-mobile" style="font-size: 12px; color: #666; margin-top: 3px;">
                                            <?php echo htmlspecialchars($cat['slug']); ?>
                                        </div>
                                    </td>
                                    <td class="hide-mobile"><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                                    <td class="hide-mobile">
                                        <?php if ($cat['parent_nom']): ?>
                                            <?php echo htmlspecialchars($cat['parent_nom']); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile"><?php echo $cat['ordre']; ?></td>
                                    <td>
                                        <span class="badge" style="background: #17a2b8; color: white;">
                                            <?php echo $cat['nb_produits']; ?>
                                        </span>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php if ($cat['nb_sous_categories'] > 0): ?>
                                            <span class="badge" style="background: #6a11cb; color: white;">
                                                <?php echo $cat['nb_sous_categories']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cat['active']): ?>
                                            <span class="badge active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge inactive">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="admin_categories.php?action=view&id=<?php echo $cat['id_categorie']; ?>" 
                                               class="btn btn-info btn-sm" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="admin_categories.php?action=edit&id=<?php echo $cat['id_categorie']; ?>" 
                                               class="btn btn-warning btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($cat['nb_produits'] == 0 && $cat['nb_sous_categories'] == 0): ?>
                                            <button onclick="confirmDelete(<?php echo $cat['id_categorie']; ?>, '<?php echo htmlspecialchars($cat['nom']); ?>')" 
                                                    class="btn btn-danger btn-sm" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <span class="tooltip">
                                                <button class="btn btn-danger btn-sm" disabled style="opacity: 0.5;" title="Impossible de supprimer : catégorie non vide">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <span class="tooltiptext">Cette catégorie contient des produits ou sous-catégories</span>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div style="padding: 15px; border-top: 1px solid #eee;">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                            <a href="?action=list&page=<?php echo ($page-1); ?>&<?php echo http_build_query($filtres); ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-chevron-left"></i>
                            </span>
                            <?php endif; ?>
                            
                            <?php 
                            // Afficher un nombre limité de pages sur mobile
                            $show_all_pages = isset($_GET['show_all']) || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') === false);
                            
                            for ($i = 1; $i <= $total_pages; $i++): 
                                $show_page = false;
                                if ($show_all_pages) {
                                    if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)) {
                                        $show_page = true;
                                    }
                                } else {
                                    if ($i == 1 || $i == $total_pages || ($i >= $page - 1 && $i <= $page + 1)) {
                                        $show_page = true;
                                    }
                                }
                                
                                if ($show_page): ?>
                                <a href="?action=list&page=<?php echo $i; ?>&<?php echo http_build_query($filtres); ?>" 
                                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php elseif ($i == $page - 2 || $i == $page + 2): ?>
                                <span class="page-link">...</span>
                            <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                            <a href="?action=list&page=<?php echo ($page+1); ?>&<?php echo http_build_query($filtres); ?>" 
                               class="page-link">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php else: ?>
                            <span class="page-link disabled">
                                <i class="fas fa-chevron-right"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action == 'tree'): ?>
            <!-- ARBORESCENCE DES CATÉGORIES -->
            <div class="tree-container">
                <h2 style="margin-bottom: 20px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <i class="fas fa-sitemap"></i> Arborescence des catégories
                </h2>
                
                <?php if (empty($tree_categories)): ?>
                    <div class="text-center" style="padding: 40px 20px;">
                        <i class="fas fa-folder-open" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777; margin-bottom: 10px;">Aucune catégorie</h3>
                        <p style="color: #999;">Commencez par créer des catégories.</p>
                    </div>
                <?php else: ?>
                    <?php function renderTree($categories, $level = 0) { ?>
                        <?php foreach ($categories as $cat): ?>
                            <div class="tree-item level-<?php echo $level; ?>">
                                <div class="tree-row">
                                    <div class="tree-icon">
                                        <?php if ($cat['nb_enfants'] > 0): ?>
                                            <i class="fas fa-folder-open"></i>
                                        <?php else: ?>
                                            <i class="fas fa-folder"></i>
                                        <?php endif; ?>
                                        <span class="tree-name show-mobile"><?php echo htmlspecialchars($cat['nom']); ?></span>
                                    </div>
                                    <div class="tree-info">
                                        <span class="tree-name hide-mobile"><?php echo htmlspecialchars($cat['nom']); ?></span>
                                        <div class="tree-meta">
                                            <span><i class="fas fa-box"></i> <?php echo $cat['nb_produits']; ?> pdt</span>
                                            <?php if ($cat['nb_enfants'] > 0): ?>
                                                <span class="hide-mobile"><i class="fas fa-sitemap"></i> <?php echo $cat['nb_enfants']; ?> ss-cat</span>
                                            <?php endif; ?>
                                            <?php if (!$cat['active']): ?>
                                                <span class="badge inactive">Inactif</span>
                                            <?php endif; ?>
                                            <span class="hide-mobile"><i class="fas fa-sort"></i> Ordre <?php echo $cat['ordre']; ?></span>
                                        </div>
                                    </div>
                                    <div class="tree-actions">
                                        <a href="admin_categories.php?action=view&id=<?php echo $cat['id_categorie']; ?>" 
                                           class="btn btn-info btn-sm" title="Voir">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="admin_categories.php?action=edit&id=<?php echo $cat['id_categorie']; ?>" 
                                           class="btn btn-warning btn-sm" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($cat['enfants'])): ?>
                                <?php renderTree($cat['enfants'], $level + 1); ?>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php } ?>
                    
                    <?php renderTree($tree_categories); ?>
                <?php endif; ?>
            </div>

        <?php elseif ($action == 'view' && isset($categorie)): ?>
            <!-- DÉTAIL D'UNE CATÉGORIE -->
            <div class="detail-container">
                <div class="detail-header">
                    <div>
                        <span class="categorie-nom">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($categorie['nom']); ?>
                        </span>
                        <div class="categorie-slug">
                            <i class="fas fa-link"></i> Slug: <?php echo htmlspecialchars($categorie['slug']); ?>
                            <?php if (!$categorie['active']): ?>
                                <span class="badge inactive" style="margin-left: 10px;">Inactif</span>
                            <?php else: ?>
                                <span class="badge active" style="margin-left: 10px;">Actif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="admin_categories.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <span class="hide-mobile">Retour</span>
                        </a>
                        <a href="admin_categories.php?action=edit&id=<?php echo $categorie['id_categorie']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    </div>
                </div>

                <!-- INFOS CATÉGORIE -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> Informations générales</h3>
                        <div class="info-row">
                            <span class="info-label">ID</span>
                            <span class="info-value">#<?php echo $categorie['id_categorie']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nom</span>
                            <span class="info-value"><?php echo htmlspecialchars($categorie['nom']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Slug</span>
                            <span class="info-value"><code><?php echo htmlspecialchars($categorie['slug']); ?></code></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Parent</span>
                            <span class="info-value">
                                <?php if ($categorie['parent_nom']): ?>
                                    <?php echo htmlspecialchars($categorie['parent_nom']); ?>
                                    (ID: <?php echo $categorie['parent_id']; ?>)
                                <?php else: ?>
                                    <span style="color: #999;">Catégorie racine</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Ordre</span>
                            <span class="info-value"><?php echo $categorie['ordre']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date création</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($categorie['date_creation'])); ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-chart-line"></i> Statistiques</h3>
                        <div class="info-row">
                            <span class="info-label">Produits</span>
                            <span class="info-value"><strong><?php echo count($categorie['produits']); ?></strong> produit(s)</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Sous-catégories</span>
                            <span class="info-value"><strong><?php echo count($categorie['sous_categories']); ?></strong> sous-catégorie(s)</span>
                        </div>
                    </div>
                </div>

                <!-- DESCRIPTION -->
                <?php if (!empty($categorie['description'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-align-left"></i> Description</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px; word-break: break-word;">
                        <?php echo nl2br(htmlspecialchars($categorie['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- MÉTA-DONNÉES SEO -->
                <?php if (!empty($categorie['meta_titre']) || !empty($categorie['meta_description'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-chart-line"></i> SEO</h3>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-top: 10px;">
                        <?php if (!empty($categorie['meta_titre'])): ?>
                        <div class="info-row">
                            <span class="info-label">Meta titre</span>
                            <span class="info-value"><?php echo htmlspecialchars($categorie['meta_titre']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($categorie['meta_description'])): ?>
                        <div class="info-row">
                            <span class="info-label">Meta description</span>
                            <span class="info-value"><?php echo htmlspecialchars($categorie['meta_description']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SOUS-CATÉGORIES -->
                <?php if (!empty($categorie['sous_categories'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-sitemap"></i> Sous-catégories</h3>
                    <div class="table-responsive" style="margin-top: 10px;">
                        <table style="min-width: 500px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorie['sous_categories'] as $sub): ?>
                                <tr>
                                    <td>#<?php echo $sub['id_categorie']; ?></td>
                                    <td><?php echo htmlspecialchars($sub['nom']); ?></td>
                                    <td>
                                        <?php if ($sub['active']): ?>
                                            <span class="badge active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge inactive">Inactif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="admin_categories.php?action=view&id=<?php echo $sub['id_categorie']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- PRODUITS -->
                <?php if (!empty($categorie['produits'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-box"></i> Produits dans cette catégorie</h3>
                    <div class="table-responsive" style="margin-top: 10px;">
                        <table style="min-width: 800px;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Référence</th>
                                    <th>Nom</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categorie['produits'] as $prod): ?>
                                <tr>
                                    <td>#<?php echo $prod['id_produit']; ?></td>
                                    <td><?php echo htmlspecialchars($prod['reference']); ?></td>
                                    <td><?php echo htmlspecialchars($prod['nom']); ?></td>
                                    <td><?php echo number_format($prod['prix_ttc'], 2); ?> €</td>
                                    <td><?php echo $prod['quantite_stock']; ?></td>
                                    <td>
                                        <?php if ($prod['statut'] == 'actif'): ?>
                                            <span class="badge active">Actif</span>
                                        <?php else: ?>
                                            <span class="badge inactive"><?php echo $prod['statut']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="admin_produits.php?action=view&id=<?php echo $prod['id_produit']; ?>" 
                                           class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action == 'add' || ($action == 'edit' && isset($categorie))): ?>
            <!-- FORMULAIRE AJOUT/MODIFICATION -->
            <div class="form-container">
                <h2 style="margin-bottom: 25px; color: #333; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                    <?php echo $action == 'add' ? 'Ajouter une catégorie' : 'Modifier la catégorie #' . $categorie['id_categorie']; ?>
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $action == 'add' ? 'add' : 'edit'; ?>">
                    <?php if ($action == 'edit'): ?>
                        <input type="hidden" name="id_categorie" value="<?php echo $categorie['id_categorie']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="nom"><i class="fas fa-tag"></i> Nom de la catégorie *</label>
                        <input type="text" id="nom" name="nom" class="form-control" 
                               value="<?php echo htmlspecialchars($categorie['nom'] ?? ''); ?>" required
                               oninput="updateSlug(this.value)">
                        <small id="slug-preview" style="color: #666; margin-top: 5px; display: block; word-break: break-word;">
                            Slug généré : <span id="slug-text"><?php echo $categorie['slug'] ?? ''; ?></span>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="slug"><i class="fas fa-link"></i> Slug (URL)</label>
                        <input type="text" id="slug" name="slug" class="form-control" 
                               value="<?php echo htmlspecialchars($categorie['slug'] ?? ''); ?>"
                               placeholder="Laissez vide pour génération automatique">
                        <small style="color: #666; word-break: break-word;">Utilisé dans l'URL, uniquement lettres, chiffres et tirets</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description"><i class="fas fa-align-left"></i> Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($categorie['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="parent_id"><i class="fas fa-sitemap"></i> Catégorie parente</label>
                            <select id="parent_id" name="parent_id" class="form-control">
                                <option value="">-- Aucune (catégorie racine) --</option>
                                <?php foreach ($parent_categories as $parent): ?>
                                    <option value="<?php echo $parent['id_categorie']; ?>"
                                        <?php echo (isset($categorie['parent_id']) && $categorie['parent_id'] == $parent['id_categorie']) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('-', $parent['ordre']) . ' ' . htmlspecialchars($parent['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="ordre"><i class="fas fa-sort"></i> Ordre d'affichage</label>
                            <input type="number" id="ordre" name="ordre" class="form-control" 
                                   value="<?php echo $categorie['ordre'] ?? '0'; ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="active" name="active" value="1" 
                                   <?php echo (!isset($categorie['active']) || $categorie['active'] == 1) ? 'checked' : ''; ?>>
                            <label for="active">Catégorie active (visible sur le site)</label>
                        </div>
                    </div>
                    
                    <h3 style="margin: 20px 0 15px; color: #333;"><i class="fas fa-chart-line"></i> Optimisation SEO</h3>
                    
                    <div class="form-group">
                        <label for="meta_titre"><i class="fas fa-heading"></i> Meta titre</label>
                        <input type="text" id="meta_titre" name="meta_titre" class="form-control" 
                               value="<?php echo htmlspecialchars($categorie['meta_titre'] ?? ''); ?>"
                               maxlength="70">
                        <small id="meta-titre-count" style="color: #666;">0/70 caractères</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="meta_description"><i class="fas fa-paragraph"></i> Meta description</label>
                        <textarea id="meta_description" name="meta_description" class="form-control" rows="3" maxlength="160"><?php echo htmlspecialchars($categorie['meta_description'] ?? ''); ?></textarea>
                        <small id="meta-desc-count" style="color: #666;">0/160 caractères</small>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 20px;">
                        @media (min-width: 768px) {
                            <div style="display: flex; gap: 15px; margin-top: 30px; flex-direction: row;">
                        }
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Ajouter la catégorie' : 'Mettre à jour'; ?>
                        </button>
                        <a href="admin_categories.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
            
            <script>
                function updateSlug(nom) {
                    const slug = nom.toLowerCase()
                        .replace(/[^a-z0-9-]/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                    document.getElementById('slug-text').textContent = slug || '(vide)';
                }
                
                // Compter les caractères pour le meta titre
                const metaTitre = document.getElementById('meta_titre');
                const metaDesc = document.getElementById('meta_description');
                const titreCount = document.getElementById('meta-titre-count');
                const descCount = document.getElementById('meta-desc-count');
                
                if (metaTitre) {
                    metaTitre.addEventListener('input', function() {
                        titreCount.textContent = this.value.length + '/70 caractères';
                    });
                    titreCount.textContent = (metaTitre.value || '').length + '/70 caractères';
                }
                
                if (metaDesc) {
                    metaDesc.addEventListener('input', function() {
                        descCount.textContent = this.value.length + '/160 caractères';
                    });
                    descCount.textContent = (metaDesc.value || '').length + '/160 caractères';
                }
            </script>
        <?php endif; ?>
    </div>

    <!-- MODAL DE SUPPRESSION -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 24px;"></i>
                <h3 style="font-size: 20px; color: #333;">Confirmer la suppression</h3>
            </div>
            <div style="margin-bottom: 25px; color: #666;">
                <p>Êtes-vous sûr de vouloir supprimer la catégorie "<span id="categoryName"></span>" ?</p>
                <p style="color: #f44336; font-weight: 600; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
                </p>
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                @media (min-width: 768px) {
                    <div style="display: flex; justify-content: flex-end; gap: 10px; flex-direction: row;">
                }
                <form id="deleteForm" method="POST" action="">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_categorie" id="categoryId">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary" style="width: 100%;">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-danger" style="width: 100%;">
                        <i class="fas fa-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fonctions pour la modal de suppression
        function confirmDelete(id, name) {
            document.getElementById('categoryId').value = id;
            document.getElementById('categoryName').textContent = name;
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
        
        // Ajuster le style pour les appareils mobiles
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la classe hide-mobile/show-mobile si elle n'existe pas
            const style = document.createElement('style');
            style.textContent = `
                .show-mobile { display: none; }
                @media (max-width: 767px) {
                    .hide-mobile { display: none !important; }
                    .show-mobile { display: block; }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>