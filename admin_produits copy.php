<?php
/**
 * admin_produits.php - Gestion des produits (CRUD) PROTÉGÉ
 * @version 1.1
 */

// Vérification de l'authentification via admin_protection.php
session_start();
require_once 'admin_protection.php'; // AJOUTÉ

// Vérifier l'accès admin
secureAdminPage('admin'); // AJOUTÉ

require_once __DIR__ . '/config/Database.php';

// Récupérer l'instance de la base de données
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die('Erreur de connexion à la base de données: ' . htmlspecialchars($e->getMessage()));
}

// Variables avec filtrage
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING) ?? 'list';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?? 0;
$message = $error = $success = '';

// Actions autorisées
$allowed_actions = ['list', 'add', 'edit', 'delete'];
if (!in_array($action, $allowed_actions)) {
    $action = 'list';
}

// Titres des pages
$page_titles = [
    'add' => "Ajouter un produit",
    'edit' => "Modifier un produit", 
    'delete' => "Supprimer un produit",
    'list' => "Gestion des produits"
];
$page_title = $page_titles[$action] ?? $page_titles['list'];

// Traitement du formulaire POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF pour les actions critiques
    if (in_array($_POST['action'] ?? '', ['delete'])) {
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!CSRFProtection::validateToken($csrf_token)) {
            die('Token CSRF invalide. Action refusée.');
        }
    }
    
    $post_action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    switch ($post_action) {
        case 'add':
        case 'edit':
            $result = saveProduit($_POST);
            if ($result['success']) {
                $success = $result['message'];
                if ($post_action === 'add') {
                    header('Location: admin_produits.php?success=' . urlencode($success));
                    exit;
                }
            } else {
                $error = $result['message'];
            }
            break;
            
        case 'delete':
            $id_produit = filter_input(INPUT_POST, 'id_produit', FILTER_VALIDATE_INT);
            if ($id_produit) {
                $result = deleteProduit($id_produit);
                if ($result['success']) {
                    header('Location: admin_produits.php?success=' . urlencode($result['message']));
                    exit;
                } else {
                    $error = $result['message'];
                }
            }
            break;
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

// Récupérer les catégories (cache en session pour éviter requêtes répétées)
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
    
    // Utiliser le cache de session pour éviter des requêtes répétées
    if (isset($_SESSION['categories_cache']) && 
        isset($_SESSION['categories_cache_time']) && 
        (time() - $_SESSION['categories_cache_time'] < 300)) { // 5 minutes cache
        return $_SESSION['categories_cache'];
    }
    
    try {
        $stmt = $db->prepare("SELECT id_categorie, nom FROM categories ORDER BY nom");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Mettre en cache
        $_SESSION['categories_cache'] = $categories;
        $_SESSION['categories_cache_time'] = time();
        
        return $categories;
    } catch (PDOException $e) {
        error_log("Erreur catégories: " . $e->getMessage());
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
            SELECT p.*, c.nom as categorie_nom,
                   (p.prix_ht * (1 + p.tva/100)) as prix_ttc,
                   COALESCE(p.seuil_alerte, 10) as seuil_alerte
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.id_produit = ? AND p.statut != 'supprime'
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur produit ID $id: " . $e->getMessage());
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
            SELECT p.*, c.nom as categorie_nom,
                   (p.prix_ht * (1 + p.tva/100)) as prix_ttc,
                   COALESCE(p.seuil_alerte, 10) as seuil_alerte
            FROM produits p 
            LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
            WHERE p.statut != 'supprime'
            ORDER BY p.date_creation DESC
            LIMIT 500
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur liste produits: " . $e->getMessage());
        return [];
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
    if (isset($data['reference']) && !preg_match('/^[A-Z0-9-]{3,50}$/i', trim($data['reference']))) {
        $errors[] = "La référence doit contenir 3 à 50 caractères (lettres, chiffres, tirets).";
    }
    
    // Vérifier le slug
    if (isset($data['slug']) && !preg_match('/^[a-z0-9-]{3,100}$/', trim($data['slug']))) {
        $errors[] = "Le slug doit contenir 3 à 100 caractères (lettres minuscules, chiffres, tirets).";
    }
    
    // Vérifier le prix
    if (isset($data['prix_ht']) && floatval($data['prix_ht']) < 0) {
        $errors[] = "Le prix ne peut pas être négatif.";
    }
    
    // Vérifier la TVA
    if (isset($data['tva']) && (floatval($data['tva']) < 0 || floatval($data['tva']) > 100)) {
        $errors[] = "La TVA doit être entre 0 et 100%.";
    }
    
    // Vérifier la quantité
    if (isset($data['quantite_stock']) && intval($data['quantite_stock']) < 0) {
        $errors[] = "La quantité en stock ne peut pas être négative.";
    }
    
    return $errors;
}

/**
 * Préparer les données du produit
 */
function prepareProduitData($data) {
    $produitData = [
        'reference' => htmlspecialchars(trim($data['reference'])),
        'nom' => htmlspecialchars(trim($data['nom'])),
        'slug' => htmlspecialchars(trim($data['slug'])),
        'description' => htmlspecialchars(trim($data['description'] ?? '')),
        'description_courte' => htmlspecialchars(substr(trim($data['description_courte'] ?? ''), 0, 255)),
        'prix_ht' => floatval($data['prix_ht']),
        'tva' => floatval($data['tva'] ?? 20.0),
        'quantite_stock' => intval($data['quantite_stock'] ?? 0),
        'id_categorie' => intval($data['id_categorie']),
        'marque' => htmlspecialchars(trim($data['marque'] ?? '')),
        'poids' => !empty($data['poids']) ? floatval($data['poids']) : null,
        'dimensions' => htmlspecialchars(trim($data['dimensions'] ?? '')),
        'materiau' => htmlspecialchars(trim($data['materiau'] ?? '')),
        'couleur' => htmlspecialchars(trim($data['couleur'] ?? '')),
        'made_in' => htmlspecialchars(trim($data['made_in'] ?? '')),
        'personnalisable' => isset($data['personnalisable']) ? 1 : 0,
        'ecologique' => isset($data['ecologique']) ? 1 : 0,
        'made_in_france' => isset($data['made_in_france']) ? 1 : 0,
        'artisanal' => isset($data['artisanal']) ? 1 : 0,
        'exclusif' => isset($data['exclusif']) ? 1 : 0,
        'statut' => htmlspecialchars(trim($data['statut'] ?? 'actif')),
        'seuil_alerte' => intval($data['seuil_alerte'] ?? 10)
    ];
    
    return $produitData;
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
    
    $produitData = prepareProduitData($data);
    
    try {
        $db->beginTransaction();
        
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
                    seuil_alerte = :seuil_alerte,
                    date_modification = NOW()
                WHERE id_produit = :id_produit
            ");
            
            $stmt->execute($produitData);
            $db->commit();
            
            // Invalider le cache des catégories
            unset($_SESSION['categories_cache']);
            
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
                    exclusif, statut, seuil_alerte, date_creation
                ) VALUES (
                    :reference, :nom, :slug, :description, :description_courte,
                    :prix_ht, :tva, :quantite_stock, :id_categorie, :marque,
                    :poids, :dimensions, :materiau, :couleur, :made_in,
                    :personnalisable, :ecologique, :made_in_france, :artisanal,
                    :exclusif, :statut, :seuil_alerte, NOW()
                )
            ");
            
            $stmt->execute($produitData);
            $id_produit = $db->lastInsertId();
            $db->commit();
            
            // Invalider le cache des catégories
            unset($_SESSION['categories_cache']);
            
            return [
                'success' => true,
                'message' => 'Produit ajouté avec succès.',
                'id_produit' => $id_produit
            ];
        }
    } catch (PDOException $e) {
        $db->rollBack();
        
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
        
        error_log("Erreur sauvegarde produit: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur de base de données.'
        ];
    }
}

/**
 * Supprimer un produit (soft delete)
 */
function deleteProduit($id) {
    global $db;
    
    try {
        $db->beginTransaction();
        
        // Vérifier si le produit existe
        $stmt = $db->prepare("SELECT id_produit, nom FROM produits WHERE id_produit = ? AND statut != 'supprime'");
        $stmt->execute([$id]);
        $produit = $stmt->fetch();
        
        if (!$produit) {
            return [
                'success' => false,
                'message' => 'Produit introuvable.'
            ];
        }
        
        // Soft delete au lieu de suppression physique
        $stmt = $db->prepare("UPDATE produits SET statut = 'supprime', date_modification = NOW() WHERE id_produit = ?");
        $stmt->execute([$id]);
        
        $db->commit();
        
        // Invalider le cache des catégories
        unset($_SESSION['categories_cache']);
        
        return [
            'success' => true,
            'message' => 'Produit supprimé avec succès.'
        ];
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Erreur suppression produit $id: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Erreur de suppression.'
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
    return substr($slug, 0, 100);
}

// Calculer les statistiques
$stats = [
    'total' => count($produits),
    'actifs' => 0,
    'rupture' => 0,
    'alerte' => 0
];

if ($action === 'list') {
    foreach ($produits as $p) {
        if ($p['statut'] === 'actif') $stats['actifs']++;
        if ($p['quantite_stock'] <= 0) $stats['rupture']++;
        if ($p['quantite_stock'] > 0 && $p['quantite_stock'] <= $p['seuil_alerte']) $stats['alerte']++;
    }
}

// Générer un token CSRF pour la suppression
$csrf_token = CSRFProtection::generateToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Administration</title>
    
    <!-- CSS optimisé -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Styles CSS existants */
        .admin-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .page-title {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .admin-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .stats-box {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .stats-label {
            font-size: 1rem;
            opacity: 0.9;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            padding-left: 40px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .stock-ok {
            color: #28a745;
            font-weight: bold;
        }
        
        .stock-low {
            color: #ffc107;
            font-weight: bold;
        }
        
        .stock-zero {
            color: #dc3545;
            font-weight: bold;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
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
                <ol class="breadcrumb" style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 5px;">
                    <li class="breadcrumb-item"><a href="dashboard.php" style="color: white;"><i class="fas fa-home"></i> Tableau de bord</a></li>
                    <li class="breadcrumb-item"><a href="admin_produits.php" style="color: white;">Produits</a></li>
                    <?php if ($action !== 'list'): ?>
                    <li class="breadcrumb-item active" style="color: white;"><?php echo htmlspecialchars(ucfirst($action)); ?></li>
                    <?php endif; ?>
                </ol>
            </nav>
            
            <div class="alert alert-info mt-3" style="background: rgba(255,255,255,0.2); border: none; color: white;">
                <i class="fas fa-user"></i> Connecté en tant que: 
                <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong>
                | Rôle: <strong><?php echo htmlspecialchars($_SESSION['admin_role'] ?? 'admin'); ?></strong>
                | IP: <strong><?php echo getClientIp(); ?></strong>
            </div>
        </header>
        
        <!-- Messages -->
        <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Contenu principal -->
        <?php if ($action === 'list'): ?>
        <!-- Liste des produits -->
        <div class="row row-cols-1 row-cols-md-4 g-3 mb-4">
            <div class="col">
                <div class="stats-box">
                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                    <div class="stats-label">Produits total</div>
                </div>
            </div>
            <div class="col">
                <div class="stats-box" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="stats-number"><?php echo $stats['actifs']; ?></div>
                    <div class="stats-label">Produits actifs</div>
                </div>
            </div>
            <div class="col">
                <div class="stats-box" style="background: linear-gradient(135deg, #dc3545, #e83e8c);">
                    <div class="stats-number"><?php echo $stats['rupture']; ?></div>
                    <div class="stats-label">En rupture</div>
                </div>
            </div>
            <div class="col">
                <div class="stats-box" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="stats-number"><?php echo $stats['alerte']; ?></div>
                    <div class="stats-label">Stock faible</div>
                </div>
            </div>
        </div>
        
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="card-title"><i class="fas fa-list"></i> Liste des produits</h2>
                <a href="admin_produits.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouveau produit
                </a>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un produit...">
                <i class="fas fa-search"></i>
            </div>
            
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
                            <th>Création</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produits)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun produit trouvé</p>
                                <a href="admin_produits.php?action=add" class="btn btn-primary mt-2">
                                    <i class="fas fa-plus"></i> Ajouter un produit
                                </a>
                            </td>
                        </tr>
                        <?php else: foreach ($produits as $produit): ?>
                        <tr>
                            <td><?php echo $produit['id_produit']; ?></td>
                            <td><strong><?php echo htmlspecialchars($produit['reference']); ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($produit['nom']); ?></strong>
                                <?php if ($produit['marque']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($produit['marque']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($produit['categorie_nom'] ?? 'Non catégorisé'); ?></td>
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
                                <span class="<?php echo $stockClass; ?>"><?php echo $produit['quantite_stock']; ?></span>
                                <?php if ($produit['quantite_stock'] <= $produit['seuil_alerte'] && $produit['quantite_stock'] > 0): ?>
                                <br><small class="text-danger">Seuil: <?php echo $produit['seuil_alerte']; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match($produit['statut']) {
                                    'inactif' => 'badge-inactive',
                                    'rupture' => 'badge-rupture',
                                    'bientot' => 'badge-bientot',
                                    default => 'badge-active'
                                };
                                ?>
                                <span class="badge-status <?php echo $badgeClass; ?>"><?php echo ucfirst($produit['statut']); ?></span>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($produit['date_creation'])); ?>
                                <br><small><?php echo date('H:i', strtotime($produit['date_creation'])); ?></small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="admin_produits.php?action=edit&id=<?php echo $produit['id_produit']; ?>" 
                                       class="btn btn-warning" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger delete-btn" 
                                            data-id="<?php echo $produit['id_produit']; ?>"
                                            data-name="<?php echo htmlspecialchars($produit['nom']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php elseif ($action === 'add' || $action === 'edit'): ?>
        <!-- Formulaire -->
        <div class="admin-card">
            <h2 class="card-title">
                <i class="fas fa-<?php echo $action === 'add' ? 'plus' : 'edit'; ?>"></i>
                <?php echo $action === 'add' ? 'Ajouter un produit' : 'Modifier le produit'; ?>
            </h2>
            
            <form method="POST" action="" id="produitForm" data-action="<?php echo $action; ?>">
                <input type="hidden" name="action" value="<?php echo $action; ?>">
                <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id_produit" value="<?php echo $produit['id_produit']; ?>">
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-8">
                        <!-- Informations de base -->
                        <div class="mb-4">
                            <h4><i class="fas fa-info-circle"></i> Informations de base</h4>
                            <div class="row mt-3 g-3">
                                <div class="col-md-6">
                                    <label for="reference" class="form-label">Référence *</label>
                                    <input type="text" id="reference" name="reference" class="form-control" 
                                           value="<?php echo htmlspecialchars($produit['reference'] ?? ''); ?>" required
                                           pattern="[A-Za-z0-9-]{3,50}" title="3-50 caractères (lettres, chiffres, tirets)">
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">Nom du produit *</label>
                                    <input type="text" id="nom" name="nom" class="form-control" 
                                           value="<?php echo htmlspecialchars($produit['nom'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="slug" class="form-label">Slug *</label>
                                    <div class="input-group">
                                        <input type="text" id="slug" name="slug" class="form-control" 
                                               value="<?php echo htmlspecialchars($produit['slug'] ?? ''); ?>" required
                                               pattern="[a-z0-9-]{3,100}" title="3-100 caractères (minuscules, chiffres, tirets)">
                                        <button type="button" class="btn btn-outline-secondary" id="generateSlug">
                                            <i class="fas fa-magic"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="id_categorie" class="form-label">Catégorie *</label>
                                    <select id="id_categorie" name="id_categorie" class="form-select" required>
                                        <option value="">Sélectionner...</option>
                                        <?php foreach ($categories as $categorie): ?>
                                        <option value="<?php echo $categorie['id_categorie']; ?>" 
                                                <?php echo (isset($produit['id_categorie']) && $produit['id_categorie'] == $categorie['id_categorie']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($categorie['nom']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Prix et stock -->
                        <div class="mb-4">
                            <h4><i class="fas fa-euro-sign"></i> Prix et stock</h4>
                            <div class="row mt-3 g-3">
                                <div class="col-md-4">
                                    <label for="prix_ht" class="form-label">Prix HT *</label>
                                    <input type="number" id="prix_ht" name="prix_ht" class="form-control" 
                                           step="0.01" min="0" required
                                           value="<?php echo $produit['prix_ht'] ?? '0'; ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="tva" class="form-label">TVA (%)</label>
                                    <input type="number" id="tva" name="tva" class="form-control" 
                                           step="0.01" min="0" max="100"
                                           value="<?php echo $produit['tva'] ?? '20.00'; ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Prix TTC</label>
                                    <input type="text" id="prix_ttc_display" class="form-control" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Caractéristiques -->
                        <div class="mb-4">
                            <h4><i class="fas fa-bolt"></i> Caractéristiques</h4>
                            <div class="form-check mt-2">
                                <input type="checkbox" id="personnalisable" name="personnalisable" value="1" 
                                       class="form-check-input" <?php echo (isset($produit['personnalisable']) && $produit['personnalisable']) ? 'checked' : ''; ?>>
                                <label for="personnalisable" class="form-check-label">Personnalisable</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="ecologique" name="ecologique" value="1"
                                       class="form-check-input" <?php echo (isset($produit['ecologique']) && $produit['ecologique']) ? 'checked' : ''; ?>>
                                <label for="ecologique" class="form-check-label">Écologique</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" id="made_in_france" name="made_in_france" value="1"
                                       class="form-check-input" <?php echo (isset($produit['made_in_france']) && $produit['made_in_france']) ? 'checked' : ''; ?>>
                                <label for="made_in_france" class="form-check-label">Fabriqué en France</label>
                            </div>
                        </div>
                        
                        <!-- Statut -->
                        <div class="mb-4">
                            <h4><i class="fas fa-flag"></i> Statut</h4>
                            <select id="statut" name="statut" class="form-select">
                                <option value="actif" <?php echo (isset($produit['statut']) && $produit['statut'] === 'actif') ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo (isset($produit['statut']) && $produit['statut'] === 'inactif') ? 'selected' : ''; ?>>Inactif</option>
                                <option value="rupture" <?php echo (isset($produit['statut']) && $produit['statut'] === 'rupture') ? 'selected' : ''; ?>>Rupture</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="admin_produits.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> 
                        <?php echo $action === 'add' ? 'Créer' : 'Mettre à jour'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Supprimer le produit <strong id="deleteProductName"></strong> ?</p>
                    <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Action irréversible !</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_produit" id="deleteProductId">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>"> <!-- AJOUTÉ -->
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
    // Configuration globale
    const config = {
        debug: <?php echo ($_SESSION['admin_role'] === 'superadmin') ? 'true' : 'false'; ?>
    };
    
    // Calcul du prix TTC
    function calculateTTC() {
        const prixHT = parseFloat(document.getElementById('prix_ht')?.value) || 0;
        const tva = parseFloat(document.getElementById('tva')?.value) || 20;
        const prixTTC = prixHT * (1 + tva / 100);
        const display = document.getElementById('prix_ttc_display');
        if (display) display.value = prixTTC.toFixed(2) + ' €';
    }
    
    // Génération de slug
    function generateSlug(text) {
        return text.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 100);
    }
    
    // Initialisation
    document.addEventListener('DOMContentLoaded', function() {
        // Calcul TTC
        const prixHT = document.getElementById('prix_ht');
        const tva = document.getElementById('tva');
        if (prixHT && tva) {
            prixHT.addEventListener('input', calculateTTC);
            tva.addEventListener('input', calculateTTC);
            calculateTTC();
        }
        
        // Génération slug
        const generateBtn = document.getElementById('generateSlug');
        const nomInput = document.getElementById('nom');
        const slugInput = document.getElementById('slug');
        
        if (generateBtn && nomInput && slugInput) {
            generateBtn.addEventListener('click', () => {
                if (nomInput.value) {
                    slugInput.value = generateSlug(nomInput.value);
                }
            });
        }
        
        // Recherche
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                const filter = this.value.toLowerCase();
                document.querySelectorAll('#productsTable tbody tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
                });
            }, 300));
        }
        
        // Suppression
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('deleteProductId').value = this.dataset.id;
                document.getElementById('deleteProductName').textContent = this.dataset.name;
                new bootstrap.Modal(document.getElementById('deleteModal')).show();
            });
        });
        
        // Validation
        const form = document.getElementById('produitForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (!this.checkValidity()) {
                    e.preventDefault();
                    this.classList.add('was-validated');
                }
            });
        }
        
        // Debug
        if (config.debug) {
            console.log('Admin:', '<?php echo htmlspecialchars($_SESSION['admin_username'] ?? ''); ?>');
            console.log('CSRF Token:', '<?php echo $csrf_token; ?>');
        }
    });
    
    // Fonction debounce pour performances
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    </script>
</body>
</html>