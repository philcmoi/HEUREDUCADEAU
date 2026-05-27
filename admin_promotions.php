<?php
// admin_promotions.php - Gestion complète des promotions (CRUD)
// VERSION AMÉLIORÉE - Édition moderne et responsive

require_once 'admin_protection.php';

// ============================================
// DÉFINITION DE LA FONCTION logAction SI ELLE N'EXISTE PAS
// ============================================
if (!function_exists('logAction')) {
    function logAction($type, $message, $metadata = []) {
        global $pdo;
        
        if (!$pdo) return;
        
        try {
            $sql = "INSERT INTO logs (type_log, niveau, message, utilisateur_id, ip_address, user_agent, metadata, date_log) 
                    VALUES (:type, 'info', :message, :user_id, :ip, :user_agent, :metadata, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'type' => $type,
                'message' => $message,
                'user_id' => $_SESSION['admin_id'] ?? null,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'metadata' => !empty($metadata) ? json_encode($metadata) : null
            ]);
        } catch (Exception $e) {
            error_log("Erreur logAction: " . $e->getMessage());
        }
    }
}

// ============================================
// CONFIGURATION
// ============================================
ini_set('display_errors', 1);
error_reporting(E_ALL);

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? 0;

$admin_username = $_SESSION['admin_username'] ?? 'Administrateur';
$admin_role = $_SESSION['admin_role'] ?? 'Non défini';

// ============================================
// FONCTIONS CRUD PROMOTIONS
// ============================================

function getAllPromotions($pdo, $page = 1, $limit = 20, $filtres = []) {
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                p.*,
                (SELECT COUNT(*) FROM promotions_produits WHERE id_promotion = p.id_promotion) as nb_produits
            FROM promotions p
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtres['search'])) {
        $sql .= " AND (p.code_promotion LIKE :search OR p.description LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    if (isset($filtres['actif']) && $filtres['actif'] !== '') {
        $sql .= " AND p.actif = :actif";
        $params['actif'] = $filtres['actif'];
    }
    
    if (!empty($filtres['type'])) {
        $sql .= " AND p.type_promotion = :type";
        $params['type'] = $filtres['type'];
    }
    
    $sql .= " ORDER BY 
                CASE 
                    WHEN p.actif = 1 AND p.date_debut <= NOW() AND p.date_fin >= NOW() THEN 1
                    WHEN p.date_debut > NOW() AND p.actif = 1 THEN 2
                    ELSE 3
                END,
                p.date_fin ASC";
    
    $stmt = $pdo->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    
    $stmt->execute();
    $allResults = $stmt->fetchAll();
    
    return array_slice($allResults, $offset, $limit);
}

function countPromotions($pdo, $filtres = []) {
    $sql = "SELECT COUNT(*) as total FROM promotions WHERE 1=1";
    $params = [];
    
    if (!empty($filtres['search'])) {
        $sql .= " AND (code_promotion LIKE :search OR description LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    if (isset($filtres['actif']) && $filtres['actif'] !== '') {
        $sql .= " AND actif = :actif";
        $params['actif'] = $filtres['actif'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

function getPromotionById($pdo, $id) {
    $sql = "SELECT * FROM promotions WHERE id_promotion = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function getPromotionProducts($pdo, $promotion_id) {
    $sql = "SELECT pp.*, p.nom, p.reference, p.prix_ttc, p.prix_ht, p.tva, p.quantite_stock
            FROM promotions_produits pp
            INNER JOIN produits p ON pp.id_produit = p.id_produit
            WHERE pp.id_promotion = :promotion_id
            ORDER BY p.nom";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['promotion_id' => $promotion_id]);
    return $stmt->fetchAll();
}

function getPromotionCategories($pdo, $promotion_id) {
    $sql = "SELECT pc.*, c.nom, c.description
            FROM promotions_categories pc
            INNER JOIN categories c ON pc.id_categorie = c.id_categorie
            WHERE pc.id_promotion = :promotion_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['promotion_id' => $promotion_id]);
    return $stmt->fetchAll();
}

function addPromotion($pdo, $data) {
    $sql = "INSERT INTO promotions (
                code_promotion, type_promotion, valeur, montant_minimum,
                utilisations_max, utilisations_actuelles,
                date_debut, date_fin, actif, description, date_creation
            ) VALUES (
                :code_promotion, :type_promotion, :valeur, :montant_minimum,
                :utilisations_max, 0,
                :date_debut, :date_fin, :actif, :description, NOW()
            )";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function updatePromotion($pdo, $id, $data) {
    $data['id_promotion'] = $id;
    
    $sql = "UPDATE promotions SET 
                code_promotion = :code_promotion,
                type_promotion = :type_promotion,
                valeur = :valeur,
                montant_minimum = :montant_minimum,
                utilisations_max = :utilisations_max,
                date_debut = :date_debut,
                date_fin = :date_fin,
                actif = :actif,
                description = :description
            WHERE id_promotion = :id_promotion";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($data);
}

function deletePromotion($pdo, $id) {
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("DELETE FROM promotions_produits WHERE id_promotion = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM promotions_categories WHERE id_promotion = ?");
        $stmt->execute([$id]);
        
        $stmt = $pdo->prepare("DELETE FROM promotions WHERE id_promotion = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression promotion: " . $e->getMessage());
        return false;
    }
}

function addProductToPromotion($pdo, $promotion_id, $product_id, $reduction_personnalisee = null) {
    $sql = "INSERT INTO promotions_produits (id_promotion, id_produit, reduction_personnalisee)
            VALUES (:promotion_id, :product_id, :reduction_personnalisee)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'promotion_id' => $promotion_id,
        'product_id' => $product_id,
        'reduction_personnalisee' => $reduction_personnalisee
    ]);
}

function removeProductFromPromotion($pdo, $promotion_id, $product_id) {
    $sql = "DELETE FROM promotions_produits 
            WHERE id_promotion = :promotion_id AND id_produit = :product_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'promotion_id' => $promotion_id,
        'product_id' => $product_id
    ]);
}

function addCategoryToPromotion($pdo, $promotion_id, $category_id) {
    $sql = "INSERT INTO promotions_categories (id_promotion, id_categorie)
            VALUES (:promotion_id, :category_id)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'promotion_id' => $promotion_id,
        'category_id' => $category_id
    ]);
}

function removeCategoryFromPromotion($pdo, $promotion_id, $category_id) {
    $sql = "DELETE FROM promotions_categories 
            WHERE id_promotion = :promotion_id AND id_categorie = :category_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'promotion_id' => $promotion_id,
        'category_id' => $category_id
    ]);
}

function getAllActiveProducts($pdo, $search = '') {
    $sql = "SELECT id_produit, nom, reference, prix_ttc, prix_ht, tva, quantite_stock 
            FROM produits 
            WHERE statut = 'actif'";
    
    if (!empty($search)) {
        $sql .= " AND (nom LIKE :search OR reference LIKE :search)";
    }
    
    $sql .= " ORDER BY nom LIMIT 100";
    
    $stmt = $pdo->prepare($sql);
    
    if (!empty($search)) {
        $stmt->execute(['search' => '%' . $search . '%']);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

function getAllActiveCategories($pdo) {
    $sql = "SELECT id_categorie, nom, description FROM categories WHERE active = 1 ORDER BY nom";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getPromotionsStats($pdo) {
    $stats = [];
    
    $sql = "SELECT COUNT(*) as total FROM promotions";
    $stmt = $pdo->query($sql);
    $stats['total'] = $stmt->fetch()['total'];
    
    $sql = "SELECT COUNT(*) as actives FROM promotions WHERE actif = 1 AND date_debut <= NOW() AND date_fin >= NOW()";
    $stmt = $pdo->query($sql);
    $stats['actives'] = $stmt->fetch()['actives'];
    
    $sql = "SELECT COUNT(*) as a_venir FROM promotions WHERE date_debut > NOW() AND actif = 1";
    $stmt = $pdo->query($sql);
    $stats['a_venir'] = $stmt->fetch()['a_venir'];
    
    $sql = "SELECT COUNT(*) as expirees FROM promotions WHERE date_fin < NOW() AND actif = 1";
    $stmt = $pdo->query($sql);
    $stats['expirees'] = $stmt->fetch()['expirees'];
    
    $sql = "SELECT COUNT(DISTINCT id_produit) as produits_soldes 
            FROM promotions_produits pp
            INNER JOIN promotions p ON pp.id_promotion = p.id_promotion
            WHERE p.actif = 1 AND p.date_debut <= NOW() AND p.date_fin >= NOW()";
    $stmt = $pdo->query($sql);
    $stats['produits_soldes'] = $stmt->fetch()['produits_soldes'] ?? 0;
    
    $sql = "SELECT MAX(valeur) as max_reduction FROM promotions WHERE actif = 1 AND type_promotion = 'pourcentage'";
    $stmt = $pdo->query($sql);
    $stats['max_reduction'] = $stmt->fetch()['max_reduction'] ?? 0;
    
    return $stats;
}

function codeExists($pdo, $code, $exclude_id = null) {
    $sql = "SELECT COUNT(*) FROM promotions WHERE code_promotion = :code";
    if ($exclude_id) {
        $sql .= " AND id_promotion != :exclude_id";
    }
    
    $stmt = $pdo->prepare($sql);
    $params = ['code' => $code];
    if ($exclude_id) {
        $params['exclude_id'] = $exclude_id;
    }
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}

function getSoldesProducts($pdo) {
    $sql = "
        SELECT p.*, c.nom as categorie_nom,
               COALESCE(
                   (SELECT MIN(pp.reduction_personnalisee) 
                    FROM promotions_produits pp 
                    WHERE pp.id_produit = p.id_produit 
                      AND pp.id_promotion IN (
                          SELECT pr.id_promotion FROM promotions pr 
                          WHERE pr.actif = 1 
                            AND pr.date_debut <= NOW() 
                            AND pr.date_fin >= NOW()
                      )),
                   (SELECT MIN(pr.valeur) 
                    FROM promotions pr 
                    INNER JOIN promotions_produits pp ON pr.id_promotion = pp.id_promotion
                    WHERE pp.id_produit = p.id_produit 
                      AND pr.actif = 1 
                      AND pr.date_debut <= NOW() 
                      AND pr.date_fin >= NOW()
                      AND pr.type_promotion = 'pourcentage')
               ) as reduction_valeur,
               (SELECT pr.type_promotion 
                FROM promotions pr 
                INNER JOIN promotions_produits pp ON pr.id_promotion = pp.id_promotion
                WHERE pp.id_produit = p.id_produit 
                  AND pr.actif = 1 
                  AND pr.date_debut <= NOW() 
                  AND pr.date_fin >= NOW()
                LIMIT 1) as type_promotion,
               (SELECT pr.id_promotion 
                FROM promotions pr 
                INNER JOIN promotions_produits pp ON pr.id_promotion = pp.id_promotion
                WHERE pp.id_produit = p.id_produit 
                  AND pr.actif = 1 
                  AND pr.date_debut <= NOW() 
                  AND pr.date_fin >= NOW()
                LIMIT 1) as id_promotion
        FROM produits p
        LEFT JOIN categories c ON p.id_categorie = c.id_categorie
        WHERE p.statut = 'actif'
          AND EXISTS (
              SELECT 1 FROM promotions_produits pp
              INNER JOIN promotions pr ON pp.id_promotion = pr.id_promotion
              WHERE pp.id_produit = p.id_produit
                AND pr.actif = 1 
                AND pr.date_debut <= NOW() 
                AND pr.date_fin >= NOW()
          )
        ORDER BY p.id_produit DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================

$filtres = [
    'search' => $_GET['search'] ?? '',
    'actif' => isset($_GET['actif']) ? (int)$_GET['actif'] : '',
    'type' => $_GET['type'] ?? ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        $code = strtoupper(trim($_POST['code_promotion']));
        
        if (codeExists($pdo, $code)) {
            $_SESSION['error'] = "Ce code promotion existe déjà.";
            header('Location: admin_promotions.php?action=add');
            exit;
        }
        
        $data = [
            'code_promotion' => $code,
            'type_promotion' => $_POST['type_promotion'],
            'valeur' => floatval($_POST['valeur']),
            'montant_minimum' => floatval($_POST['montant_minimum']),
            'utilisations_max' => !empty($_POST['utilisations_max']) ? intval($_POST['utilisations_max']) : null,
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
            'actif' => isset($_POST['actif']) ? 1 : 0,
            'description' => $_POST['description']
        ];
        
        if ($data['type_promotion'] === 'pourcentage' && $data['valeur'] > 100) {
            $_SESSION['error'] = "Le pourcentage de réduction ne peut pas dépasser 100%.";
            header('Location: admin_promotions.php?action=add');
            exit;
        }
        
        if (strtotime($data['date_fin']) < strtotime($data['date_debut'])) {
            $_SESSION['error'] = "La date de fin doit être postérieure à la date de début.";
            header('Location: admin_promotions.php?action=add');
            exit;
        }
        
        if (addPromotion($pdo, $data)) {
            $newId = $pdo->lastInsertId();
            
            if (!empty($_POST['produits'])) {
                foreach ($_POST['produits'] as $product_id) {
                    $reduction = !empty($_POST['reduction_' . $product_id]) ? floatval($_POST['reduction_' . $product_id]) : null;
                    addProductToPromotion($pdo, $newId, $product_id, $reduction);
                }
            }
            
            if (!empty($_POST['categories'])) {
                foreach ($_POST['categories'] as $category_id) {
                    addCategoryToPromotion($pdo, $newId, $category_id);
                }
            }
            
            if (function_exists('logAction')) {
                logAction('info', 'Promotion ajoutée', [
                    'promotion_id' => $newId,
                    'code' => $code,
                    'admin_id' => $_SESSION['admin_id']
                ]);
            }
            
            $_SESSION['message'] = "Promotion '$code' ajoutée avec succès.";
            header('Location: admin_promotions.php?action=list');
            exit;
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout de la promotion.";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id_promotion']);
        $code = strtoupper(trim($_POST['code_promotion']));
        
        if (codeExists($pdo, $code, $id)) {
            $_SESSION['error'] = "Ce code promotion existe déjà.";
            header('Location: admin_promotions.php?action=edit&id=' . $id);
            exit;
        }
        
        $data = [
            'code_promotion' => $code,
            'type_promotion' => $_POST['type_promotion'],
            'valeur' => floatval($_POST['valeur']),
            'montant_minimum' => floatval($_POST['montant_minimum']),
            'utilisations_max' => !empty($_POST['utilisations_max']) ? intval($_POST['utilisations_max']) : null,
            'date_debut' => $_POST['date_debut'],
            'date_fin' => $_POST['date_fin'],
            'actif' => isset($_POST['actif']) ? 1 : 0,
            'description' => $_POST['description']
        ];
        
        if ($data['type_promotion'] === 'pourcentage' && $data['valeur'] > 100) {
            $_SESSION['error'] = "Le pourcentage de réduction ne peut pas dépasser 100%.";
            header('Location: admin_promotions.php?action=edit&id=' . $id);
            exit;
        }
        
        if (updatePromotion($pdo, $id, $data)) {
            if (function_exists('logAction')) {
                logAction('info', 'Promotion modifiée', [
                    'promotion_id' => $id,
                    'code' => $code,
                    'admin_id' => $_SESSION['admin_id']
                ]);
            }
            $_SESSION['message'] = "Promotion #$id modifiée avec succès.";
            header('Location: admin_promotions.php?action=view&id=' . $id);
            exit;
        } else {
            $_SESSION['error'] = "Erreur lors de la modification de la promotion.";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
        $promotion_id = intval($_POST['promotion_id']);
        $product_id = intval($_POST['product_id']);
        $reduction = !empty($_POST['reduction_personnalisee']) ? floatval($_POST['reduction_personnalisee']) : null;
        
        if (addProductToPromotion($pdo, $promotion_id, $product_id, $reduction)) {
            $_SESSION['message'] = "Produit ajouté à la promotion avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du produit.";
        }
        
        header('Location: admin_promotions.php?action=view&id=' . $promotion_id);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'remove_product') {
        $promotion_id = intval($_POST['promotion_id']);
        $product_id = intval($_POST['product_id']);
        
        if (removeProductFromPromotion($pdo, $promotion_id, $product_id)) {
            $_SESSION['message'] = "Produit retiré de la promotion.";
        } else {
            $_SESSION['error'] = "Erreur lors du retrait du produit.";
        }
        
        header('Location: admin_promotions.php?action=view&id=' . $promotion_id);
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = intval($_POST['id_promotion']);
        
        $promotion = getPromotionById($pdo, $id);
        $code = $promotion ? $promotion['code_promotion'] : "ID $id";
        
        if (deletePromotion($pdo, $id)) {
            if (function_exists('logAction')) {
                logAction('securite', 'Promotion supprimée', [
                    'promotion_id' => $id,
                    'code' => $code,
                    'admin_id' => $_SESSION['admin_id']
                ]);
            }
            $_SESSION['message'] = "Promotion '$code' supprimée avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression de la promotion.";
        }
        
        header('Location: admin_promotions.php?action=list');
        exit;
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

$stats = getPromotionsStats($pdo);
$total_promotions = countPromotions($pdo, $filtres);
$total_pages = ceil($total_promotions / $limit);

$promotions = [];
if ($action === 'list') {
    $promotions = getAllPromotions($pdo, $page, $limit, $filtres);
} elseif ($action === 'view' && $id > 0) {
    $promotion = getPromotionById($pdo, $id);
    if (!$promotion) {
        $error = "Promotion non trouvée.";
        $action = 'list';
    } else {
        $promotion['produits'] = getPromotionProducts($pdo, $id);
        $promotion['categories'] = getPromotionCategories($pdo, $id);
    }
} elseif ($action === 'edit' && $id > 0) {
    $promotion = getPromotionById($pdo, $id);
    if (!$promotion) {
        $error = "Promotion non trouvée.";
        $action = 'list';
    } else {
        $promotion['produits'] = getPromotionProducts($pdo, $id);
        $promotion['categories'] = getPromotionCategories($pdo, $id);
    }
}

$all_products = getAllActiveProducts($pdo);
$all_categories = getAllActiveCategories($pdo);

$produits_soldes = [];
if ($action === 'soldes') {
    $produits_soldes = getSoldesProducts($pdo);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestion des Promotions - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* ============================================
           STYLES RESPONSIVES OPTIMISÉS
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #6a11cb;
            --primary-gradient: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --dark-color: #1f2937;
            --light-bg: #f3f4f6;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --border-radius: 16px;
            --border-radius-sm: 12px;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-color);
            line-height: 1.5;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 16px;
        }
        
        @media (min-width: 768px) {
            .container {
                padding: 24px;
            }
        }
        
        /* Header */
        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 24px;
            box-shadow: var(--shadow-lg);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        @media (min-width: 768px) {
            .header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                padding: 24px 32px;
            }
        }
        
        .header h1 { 
            font-size: 1.5rem; 
            font-weight: 700; 
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        @media (min-width: 768px) {
            .header h1 { font-size: 1.8rem; }
        }
        
        .role-badge {
            background-color: rgba(255,255,255,0.2);
            padding: 8px 16px;
            border-radius: 40px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            width: fit-content;
        }
        
        /* Navigation responsive avec scroll horizontal */
        .nav-tabs {
            display: flex;
            background-color: white;
            border-radius: var(--border-radius);
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            gap: 4px;
            padding: 4px;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }
        
        .nav-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .nav-tabs a {
            padding: 12px 18px;
            text-decoration: none;
            color: #555;
            font-weight: 500;
            border-radius: var(--border-radius-sm);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 0.85rem;
        }
        
        @media (min-width: 640px) {
            .nav-tabs a {
                padding: 14px 24px;
                font-size: 0.9rem;
            }
        }
        
        .nav-tabs a:hover { 
            background-color: #f0f0f0; 
            color: var(--primary-color); 
        }
        
        .nav-tabs a.active {
            background: var(--primary-gradient);
            color: white;
        }
        
        /* Alertes */
        .alert {
            padding: 14px 18px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            word-break: break-word;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger-color);
        }
        
        /* Stats cards - grille responsive */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        
        @media (min-width: 480px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 16px;
            }
        }
        
        @media (min-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(5, 1fr);
                gap: 20px;
            }
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 16px 12px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-value {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-color);
        }
        
        @media (min-width: 768px) {
            .stat-value {
                font-size: 2rem;
            }
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #666;
            margin-top: 4px;
        }
        
        @media (min-width: 768px) {
            .stat-label {
                font-size: 0.85rem;
            }
        }
        
        /* Filtres - design moderne */
        .filters-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
        }
        
        .filters-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        @media (min-width: 768px) {
            .filters-form {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: flex-end;
            }
        }
        
        .filter-group {
            flex: 1;
            min-width: 140px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            background-color: white;
            transition: all 0.3s;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
        }
        
        /* Boutons */
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-sm { 
            padding: 8px 12px; 
            font-size: 0.8rem; 
        }
        
        .btn-primary { background: var(--primary-gradient); color: white; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-success { background-color: var(--success-color); color: white; }
        .btn-success:hover { background-color: #059669; transform: translateY(-2px); }
        .btn-warning { background-color: var(--warning-color); color: white; }
        .btn-warning:hover { background-color: #d97706; transform: translateY(-2px); }
        .btn-danger { background-color: var(--danger-color); color: white; }
        .btn-danger:hover { background-color: #dc2626; transform: translateY(-2px); }
        .btn-secondary { background-color: #6b7280; color: white; }
        .btn-secondary:hover { background-color: #4b5563; transform: translateY(-2px); }
        .btn-info { background-color: var(--info-color); color: white; }
        .btn-info:hover { background-color: #2563eb; transform: translateY(-2px); }
        .btn-outline { background: white; border: 1px solid var(--border-color); color: #555; }
        .btn-outline:hover { background: #f9fafb; transform: translateY(-2px); }
        
        /* Cartes promotions - NOUVEAU DESIGN RESPONSIVE */
        .promotions-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-top: 20px;
        }
        
        @media (min-width: 640px) {
            .promotions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .promotions-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (min-width: 1400px) {
            .promotions-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        .promo-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .promo-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .promo-card-header {
            background: var(--primary-gradient);
            color: white;
            padding: 16px;
            position: relative;
        }
        
        .promo-code {
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 1px;
            word-break: break-word;
        }
        
        .promo-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .promo-card-body {
            padding: 16px;
        }
        
        .promo-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--danger-color);
            margin-bottom: 12px;
        }
        
        .promo-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }
        
        .promo-info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: #555;
        }
        
        .promo-info-item i {
            width: 20px;
            color: var(--primary-color);
        }
        
        .promo-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .status-active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-expired {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-upcoming {
            background: #fed7aa;
            color: #92400e;
        }
        
        .status-inactive {
            background: #f3f4f6;
            color: #374151;
        }
        
        .promo-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        
        .promo-actions .btn {
            flex: 1;
            padding: 8px;
            font-size: 0.75rem;
        }
        
        /* Table pour desktop (optionnelle) */
        .table-view {
            display: none;
        }
        
        @media (min-width: 992px) {
            .promotions-grid {
                display: none;
            }
            .table-view {
                display: block;
            }
        }
        
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .table-header {
            background-color: #f8f9fa;
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border-bottom: 1px solid #eee;
        }
        
        @media (min-width: 768px) {
            .table-header {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
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
        
        th {
            background-color: #f8f9fa;
            padding: 14px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.85rem;
        }
        
        td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
            font-size: 0.85rem;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-align: center;
        }
        
        .badge.active { background: #d1fae5; color: #065f46; }
        .badge.inactive { background: #f3f4f6; color: #374151; }
        .badge.expired { background: #fee2e2; color: #991b1b; }
        .badge.upcoming { background: #fed7aa; color: #92400e; }
        .badge.pourcentage { background: #dbeafe; color: #1e40af; }
        .badge.montant { background: #d1fae5; color: #065f46; }
        .badge.livraison { background: #fef3c7; color: #92400e; }
        
        .actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 8px;
            margin: 20px 0;
        }
        
        .page-link {
            padding: 8px 14px;
            background: white;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            color: var(--dark-color);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .page-link:hover { background: #f0f0f0; }
        
        .page-link.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: var(--border-radius);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #777;
            margin-bottom: 10px;
        }
        
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
            padding: 16px;
        }
        
        .modal-content {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 24px;
            max-width: 450px;
            width: 100%;
            box-shadow: var(--shadow-lg);
        }
        
        /* FORMULAIRE AMÉLIORÉ */
        .form-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .form-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 24px 28px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-header p {
            color: #6b7280;
            font-size: 0.9rem;
            margin-top: 4px;
        }
        
        .form-body {
            padding: 28px;
        }
        
        .form-grid {
            display: grid;
            gap: 24px;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        
        @media (min-width: 768px) {
            .form-row-2 {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #374151;
            font-size: 0.9rem;
        }
        
        .form-group label i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .required {
            color: var(--danger-color);
            margin-left: 4px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.95rem;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(106, 17, 203, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check label {
            margin-bottom: 0;
            cursor: pointer;
            font-weight: normal;
        }
        
        .form-help {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 6px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-top: 8px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: var(--primary-color);
        }
        
        .products-list, .categories-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            max-height: 400px;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        @media (min-width: 640px) {
            .products-list, .categories-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        .product-item, .category-item {
            background: #f9fafb;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 12px;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        
        .product-item:hover, .category-item:hover {
            background: #f3f4f6;
            border-color: var(--primary-color);
        }
        
        .product-info, .category-info {
            flex: 1;
        }
        
        .product-name, .category-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 4px;
        }
        
        .product-details, .category-details {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .product-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 0.85rem;
        }
        
        .remove-btn {
            background: none;
            border: none;
            color: var(--danger-color);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.3s;
        }
        
        .remove-btn:hover {
            background: #fee2e2;
            transform: scale(1.1);
        }
        
        .add-section {
            background: #f9fafb;
            border-radius: var(--border-radius-sm);
            padding: 20px;
            margin-top: 24px;
        }
        
        .add-section h4 {
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        @media (min-width: 640px) {
            .add-form {
                flex-direction: row;
                align-items: flex-end;
            }
        }
        
        .add-form .form-group {
            flex: 2;
            margin-bottom: 0;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Classes utilitaires */
        .text-center { text-align: center; }
        .mt-4 { margin-top: 20px; }
        .mb-2 { margin-bottom: 10px; }
        .w-100 { width: 100%; }
        
        @media (max-width: 991px) {
            .hide-on-mobile {
                display: none;
            }
        }
        
        @media (min-width: 992px) {
            .show-on-mobile {
                display: none;
            }
        }
        
        /* Produits soldés - grille responsive */
        .soldes-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: var(--shadow-md);
        }
        
        .soldes-section h2 {
            color: var(--danger-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        
        .soldes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .solde-card {
            background: #fff;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s;
            border: 1px solid #eee;
            position: relative;
        }
        
        .solde-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .solde-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            z-index: 1;
        }
        
        .solde-image {
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
        }
        
        .solde-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .solde-card:hover .solde-image img {
            transform: scale(1.05);
        }
        
        .solde-info {
            padding: 16px;
        }
        
        .solde-info h3 {
            margin: 0 0 8px;
            font-size: 1rem;
            color: #2c3e50;
        }
        
        .solde-prices {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            flex-wrap: wrap;
        }
        
        .old-price {
            color: #7f8c8d;
            text-decoration: line-through;
            font-size: 0.85rem;
        }
        
        .new-price {
            color: #e74c3c;
            font-size: 1.2rem;
            font-weight: 800;
        }
        
        .solde-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
        }
        
        .solde-actions .btn {
            flex: 1;
            padding: 8px;
            font-size: 0.75rem;
        }
        
        /* Animation pour les boutons */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert {
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-percent"></i> Gestion des Promotions</h1>
                <p style="font-size: 0.85rem; opacity: 0.9;">Bienvenue, <?php echo htmlspecialchars($admin_username); ?></p>
            </div>
            <div class="role-badge">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> <span>Retour</span>
            </a>
            <a href="admin_promotions.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <span>Liste</span>
            </a>
            <a href="admin_promotions.php?action=add" class="<?php echo $action == 'add' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i> <span>Ajouter</span>
            </a>
            <a href="admin_promotions.php?action=soldes" class="<?php echo $action == 'soldes' ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i> <span>Soldes</span>
            </a>
            <a href="admin_promotions.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span>Stats</span>
            </a>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- STATS DASHBOARD -->
        <?php if ($action === 'stats'): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total promotions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['actives']); ?></div>
                    <div class="stat-label">Actives</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['a_venir']); ?></div>
                    <div class="stat-label">À venir</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['expirees']); ?></div>
                    <div class="stat-label">Expirées</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo number_format($stats['produits_soldes']); ?></div>
                    <div class="stat-label">Produits soldés</div>
                </div>
            </div>
            <div class="stat-card" style="margin-top: 0;">
                <div class="stat-value"><?php echo number_format($stats['max_reduction'], 0); ?>%</div>
                <div class="stat-label">Plus grosse réduction</div>
            </div>
        <?php endif; ?>

        <!-- SECTION PRODUITS SOLDÉS -->
        <?php if ($action === 'soldes'): ?>
            <div class="soldes-section">
                <h2>
                    <i class="fas fa-fire" style="color: #e74c3c;"></i> 
                    Produits actuellement en solde
                    <span class="badge active"><?php echo count($produits_soldes); ?> produits</span>
                </h2>
                
                <?php if (empty($produits_soldes)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle"></i>
                        Aucun produit n'est actuellement en solde.
                    </div>
                <?php else: ?>
                    <div class="soldes-grid">
                        <?php foreach ($produits_soldes as $produit): 
                            $prix_original = $produit['prix_ttc'];
                            $reduction = $produit['reduction_valeur'] ?? 0;
                            $prix_solde = $prix_original * (1 - $reduction / 100);
                            
                            $stmt_img = $pdo->prepare("SELECT url_image FROM images_produits WHERE id_produit = ? AND principale = 1 LIMIT 1");
                            $stmt_img->execute([$produit['id_produit']]);
                            $image = $stmt_img->fetchColumn();
                            $image_url = $image ?: 'https://via.placeholder.com/300x200/95a5a6/ffffff?text=Produit';
                        ?>
                            <div class="solde-card">
                                <div class="solde-badge">-<?php echo round($reduction); ?>%</div>
                                <div class="solde-image">
                                    <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                         alt="<?php echo htmlspecialchars($produit['nom']); ?>">
                                </div>
                                <div class="solde-info">
                                    <h3><?php echo htmlspecialchars($produit['nom']); ?></h3>
                                    <div class="solde-prices">
                                        <span class="old-price"><?php echo number_format($prix_original, 2); ?> €</span>
                                        <span class="new-price"><?php echo number_format($prix_solde, 2); ?> €</span>
                                    </div>
                                    <div class="solde-actions">
                                        <a href="admin_produits.php?action=edit&id=<?php echo $produit['id_produit']; ?>" class="btn btn-warning btn-sm">
                                            <i class="fas fa-edit"></i> Modifier
                                        </a>
                                        <a href="admin_promotions.php?action=view&id=<?php echo $produit['id_promotion'] ?? 0; ?>" class="btn btn-info btn-sm">
                                            <i class="fas fa-tag"></i> Voir promo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- LISTE DES PROMOTIONS -->
        <?php if ($action == 'list'): ?>
            <!-- FILTRES -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <input type="hidden" name="action" value="list">
                    
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Recherche</label>
                        <input type="text" name="search" placeholder="Code promo..." 
                               value="<?php echo htmlspecialchars($filtres['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-toggle-on"></i> Statut</label>
                        <select name="actif">
                            <option value="">Tous</option>
                            <option value="1" <?php echo $filtres['actif'] === '1' ? 'selected' : ''; ?>>Actif</option>
                            <option value="0" <?php echo $filtres['actif'] === '0' ? 'selected' : ''; ?>>Inactif</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Type</label>
                        <select name="type">
                            <option value="">Tous</option>
                            <option value="pourcentage" <?php echo $filtres['type'] === 'pourcentage' ? 'selected' : ''; ?>>Pourcentage</option>
                            <option value="montant_fixe" <?php echo $filtres['type'] === 'montant_fixe' ? 'selected' : ''; ?>>Montant fixe</option>
                            <option value="livraison_gratuite" <?php echo $filtres['type'] === 'livraison_gratuite' ? 'selected' : ''; ?>>Livraison gratuite</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="admin_promotions.php?action=list" class="btn btn-outline">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- HEADER AVEC BOUTON AJOUT -->
            <div class="table-header" style="margin-bottom: 0;">
                <h3 style="margin: 0;"><i class="fas fa-percent"></i> Promotions (<?php echo $total_promotions; ?>)</h3>
                <a href="admin_promotions.php?action=add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvelle promotion
                </a>
            </div>

            <?php if (empty($promotions)): ?>
                <div class="empty-state">
                    <i class="fas fa-percent"></i>
                    <h3>Aucune promotion trouvée</h3>
                    <p>Créez votre première promotion pour attirer plus de clients.</p>
                    <a href="admin_promotions.php?action=add" class="btn btn-primary mt-4">
                        <i class="fas fa-plus"></i> Créer une promotion
                    </a>
                </div>
            <?php else: ?>
                
                <!-- VUE CARTES (MOBILE/TABLETTE) -->
                <div class="promotions-grid">
                    <?php foreach ($promotions as $promo): 
                        $now = new DateTime();
                        $date_fin = new DateTime($promo['date_fin']);
                        $date_debut = new DateTime($promo['date_debut']);
                        
                        if ($promo['actif'] && $date_fin >= $now && $date_debut <= $now) {
                            $status = 'active';
                            $status_text = 'Actif';
                            $status_class = 'status-active';
                        } elseif ($promo['actif'] && $date_debut > $now) {
                            $status = 'upcoming';
                            $status_text = 'À venir';
                            $status_class = 'status-upcoming';
                        } elseif ($promo['actif'] && $date_fin < $now) {
                            $status = 'expired';
                            $status_text = 'Expirée';
                            $status_class = 'status-expired';
                        } else {
                            $status = 'inactive';
                            $status_text = 'Inactif';
                            $status_class = 'status-inactive';
                        }
                        
                        $valeur_affichee = '';
                        if ($promo['type_promotion'] == 'pourcentage') {
                            $valeur_affichee = '-' . $promo['valeur'] . '%';
                        } elseif ($promo['type_promotion'] == 'montant_fixe') {
                            $valeur_affichee = '-' . number_format($promo['valeur'], 2) . ' €';
                        } else {
                            $valeur_affichee = 'Livraison offerte';
                        }
                    ?>
                        <div class="promo-card">
                            <div class="promo-card-header">
                                <div class="promo-code">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($promo['code_promotion']); ?>
                                </div>
                                <div class="promo-badge">
                                    <?php echo $promo['type_promotion'] == 'pourcentage' ? '%' : ($promo['type_promotion'] == 'montant_fixe' ? '€' : '🚚'); ?>
                                </div>
                            </div>
                            <div class="promo-card-body">
                                <div class="promo-value"><?php echo $valeur_affichee; ?></div>
                                
                                <div class="promo-status <?php echo $status_class; ?>">
                                    <i class="fas <?php echo $status == 'active' ? 'fa-check-circle' : ($status == 'upcoming' ? 'fa-clock' : ($status == 'expired' ? 'fa-times-circle' : 'fa-ban')); ?>"></i>
                                    <?php echo $status_text; ?>
                                </div>
                                
                                <div class="promo-info">
                                    <div class="promo-info-item">
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>Jusqu'au <?php echo date('d/m/Y', strtotime($promo['date_fin'])); ?></span>
                                    </div>
                                    <?php if ($promo['montant_minimum'] > 0): ?>
                                    <div class="promo-info-item">
                                        <i class="fas fa-euro-sign"></i>
                                        <span>Min: <?php echo number_format($promo['montant_minimum'], 2); ?> €</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="promo-info-item">
                                        <i class="fas fa-users"></i>
                                        <span><?php echo $promo['utilisations_actuelles']; ?>/<?php echo $promo['utilisations_max'] ?: '∞'; ?></span>
                                    </div>
                                    <div class="promo-info-item">
                                        <i class="fas fa-box"></i>
                                        <span><?php echo $promo['nb_produits']; ?> produit(s)</span>
                                    </div>
                                </div>
                                
                                <div class="promo-actions">
                                    <a href="admin_promotions.php?action=view&id=<?php echo $promo['id_promotion']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                    <a href="admin_promotions.php?action=edit&id=<?php echo $promo['id_promotion']; ?>" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $promo['id_promotion']; ?>, '<?php echo htmlspecialchars($promo['code_promotion']); ?>')" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- VUE TABLE (DESKTOP) -->
                <div class="table-view table-container">
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Code</th>
                                    <th>Type</th>
                                    <th>Valeur</th>
                                    <th>Montant mini</th>
                                    <th>Date fin</th>
                                    <th>Utilisations</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotions as $promo): 
                                    $now = new DateTime();
                                    $date_fin = new DateTime($promo['date_fin']);
                                    $is_valid = $promo['actif'] && $date_fin >= $now;
                                    
                                    $type_labels = [
                                        'pourcentage' => '%',
                                        'montant_fixe' => '€',
                                        'livraison_gratuite' => 'Livraison'
                                    ];
                                    $type_label = $type_labels[$promo['type_promotion']] ?? '-';
                                ?>
                                    <tr>
                                        <td>#<?php echo $promo['id_promotion']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($promo['code_promotion']); ?></strong></td>
                                        <td>
                                            <span class="badge <?php echo $promo['type_promotion'] == 'pourcentage' ? 'pourcentage' : ($promo['type_promotion'] == 'montant_fixe' ? 'montant' : 'livraison'); ?>">
                                                <?php echo $type_label; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($promo['type_promotion'] == 'pourcentage'): ?>
                                                <?php echo $promo['valeur']; ?>%
                                            <?php elseif ($promo['type_promotion'] == 'montant_fixe'): ?>
                                                <?php echo number_format($promo['valeur'], 2); ?> €
                                            <?php else: ?>
                                                <i class="fas fa-truck"></i> Gratuite
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $promo['montant_minimum'] > 0 ? number_format($promo['montant_minimum'], 2) . ' €' : '-'; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($promo['date_fin'])); ?></td>
                                        <td><?php echo $promo['utilisations_actuelles']; ?><?php echo $promo['utilisations_max'] ? '/' . $promo['utilisations_max'] : ''; ?></td>
                                        <td>
                                            <?php if ($promo['actif'] && $date_fin >= $now && new DateTime($promo['date_debut']) <= $now): ?>
                                                <span class="badge active">Actif</span>
                                            <?php elseif ($promo['actif'] && new DateTime($promo['date_debut']) > $now): ?>
                                                <span class="badge upcoming">À venir</span>
                                            <?php elseif ($promo['actif'] && $date_fin < $now): ?>
                                                <span class="badge expired">Expirée</span>
                                            <?php else: ?>
                                                <span class="badge inactive">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="actions">
                                                <a href="admin_promotions.php?action=view&id=<?php echo $promo['id_promotion']; ?>" class="btn btn-info btn-sm" title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="admin_promotions.php?action=edit&id=<?php echo $promo['id_promotion']; ?>" class="btn btn-warning btn-sm" title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="confirmDelete(<?php echo $promo['id_promotion']; ?>, '<?php echo htmlspecialchars($promo['code_promotion']); ?>')" class="btn btn-danger btn-sm" title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?action=list&page=<?php echo ($page-1); ?>&<?php echo http_build_query($filtres); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                        <a href="?action=list&page=<?php echo $i; ?>&<?php echo http_build_query($filtres); ?>" 
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?action=list&page=<?php echo ($page+1); ?>&<?php echo http_build_query($filtres); ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($action == 'view' && isset($promotion)): ?>
            <!-- VUE DÉTAIL (simplifiée pour la longueur) -->
            <div class="table-container" style="padding: 24px;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 16px; margin-bottom: 24px;">
                    <div>
                        <h2 style="font-size: 1.8rem; margin-bottom: 8px;">
                            <i class="fas fa-tag" style="color: var(--primary-color);"></i> 
                            <?php echo htmlspecialchars($promotion['code_promotion']); ?>
                        </h2>
                        <p style="color: #6b7280;"><?php echo htmlspecialchars($promotion['description'] ?: 'Aucune description'); ?></p>
                    </div>
                    <div class="actions">
                        <a href="admin_promotions.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                        <a href="admin_promotions.php?action=edit&id=<?php echo $promotion['id_promotion']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                    </div>
                </div>
                
                <div class="stats-grid" style="margin-bottom: 24px;">
                    <div class="stat-card">
                        <div class="stat-value">
                            <?php if ($promotion['type_promotion'] == 'pourcentage'): ?>
                                <?php echo $promotion['valeur']; ?>%
                            <?php elseif ($promotion['type_promotion'] == 'montant_fixe'): ?>
                                <?php echo number_format($promotion['valeur'], 2); ?> €
                            <?php else: ?>
                                <i class="fas fa-truck"></i>
                            <?php endif; ?>
                        </div>
                        <div class="stat-label">Réduction</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo date('d/m/Y', strtotime($promotion['date_debut'])); ?></div>
                        <div class="stat-label">Début</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo date('d/m/Y', strtotime($promotion['date_fin'])); ?></div>
                        <div class="stat-label">Fin</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $promotion['utilisations_actuelles']; ?>/<?php echo $promotion['utilisations_max'] ?: '∞'; ?></div>
                        <div class="stat-label">Utilisations</div>
                    </div>
                </div>
                
                <?php if (!empty($promotion['produits'])): ?>
                <div style="margin-top: 24px;">
                    <h3><i class="fas fa-box"></i> Produits concernés (<?php echo count($promotion['produits']); ?>)</h3>
                    <div class="products-list" style="margin-top: 16px;">
                        <?php foreach ($promotion['produits'] as $produit): ?>
                        <div class="product-item">
                            <div class="product-info">
                                <div class="product-name"><?php echo htmlspecialchars($produit['nom']); ?></div>
                                <div class="product-details">
                                    Réf: <?php echo htmlspecialchars($produit['reference']); ?> | 
                                    Prix: <?php echo number_format($produit['prix_ttc'], 2); ?> €
                                    <?php if ($produit['reduction_personnalisee']): ?>
                                    <span class="badge pourcentage" style="margin-left: 8px;">-<?php echo $produit['reduction_personnalisee']; ?>%</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($promotion['categories'])): ?>
                <div style="margin-top: 24px;">
                    <h3><i class="fas fa-folder"></i> Catégories concernées (<?php echo count($promotion['categories']); ?>)</h3>
                    <div class="categories-list" style="margin-top: 16px;">
                        <?php foreach ($promotion['categories'] as $categorie): ?>
                        <div class="category-item">
                            <div class="category-info">
                                <div class="category-name"><?php echo htmlspecialchars($categorie['nom']); ?></div>
                                <div class="category-details"><?php echo htmlspecialchars($categorie['description'] ?: 'Aucune description'); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        <?php elseif ($action == 'add' || ($action == 'edit' && isset($promotion))): ?>
            <!-- FORMULAIRE AMÉLIORÉ -->
            <div class="form-container">
                <div class="form-header">
                    <h2>
                        <i class="fas <?php echo $action == 'add' ? 'fa-plus-circle' : 'fa-edit'; ?>"></i>
                        <?php echo $action == 'add' ? 'Créer une promotion' : 'Modifier la promotion'; ?>
                    </h2>
                    <p>
                        <?php echo $action == 'add' 
                            ? 'Créez une nouvelle promotion pour attirer vos clients' 
                            : 'Modifiez les informations de votre promotion'; ?>
                    </p>
                </div>
                
                <div class="form-body">
                    <form method="POST" action="" id="promotionForm">
                        <input type="hidden" name="action" value="<?php echo $action == 'add' ? 'add' : 'edit'; ?>">
                        <?php if ($action == 'edit'): ?>
                            <input type="hidden" name="id_promotion" value="<?php echo $promotion['id_promotion']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <!-- Informations principales -->
                            <div>
                                <div class="section-title">
                                    <i class="fas fa-info-circle"></i>
                                    Informations générales
                                </div>
                                
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-code"></i> Code promotion <span class="required">*</span></label>
                                        <input type="text" name="code_promotion" class="form-control" required 
                                               value="<?php echo htmlspecialchars($promotion['code_promotion'] ?? ''); ?>"
                                               placeholder="Ex: BIENVENUE10">
                                        <div class="form-help">Le code que vos clients utiliseront en caisse</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-tag"></i> Type de promotion <span class="required">*</span></label>
                                        <select name="type_promotion" class="form-control" required id="typePromotion">
                                            <option value="pourcentage" <?php echo (isset($promotion['type_promotion']) && $promotion['type_promotion'] == 'pourcentage') ? 'selected' : ''; ?>>
                                                💰 Pourcentage (%) - Réduction en pourcentage
                                            </option>
                                            <option value="montant_fixe" <?php echo (isset($promotion['type_promotion']) && $promotion['type_promotion'] == 'montant_fixe') ? 'selected' : ''; ?>>
                                                💶 Montant fixe (€) - Réduction en euros
                                            </option>
                                            <option value="livraison_gratuite" <?php echo (isset($promotion['type_promotion']) && $promotion['type_promotion'] == 'livraison_gratuite') ? 'selected' : ''; ?>>
                                                🚚 Livraison gratuite - Offrez les frais de port
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row-2">
                                    <div class="form-group" id="valeurGroup">
                                        <label><i class="fas fa-percent"></i> Valeur de réduction <span class="required">*</span></label>
                                        <input type="number" step="0.01" name="valeur" class="form-control" required 
                                               value="<?php echo $promotion['valeur'] ?? ''; ?>"
                                               id="valeurInput">
                                        <div class="form-help" id="valeurHelp">Exemple: 10 pour -10%</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-euro-sign"></i> Montant minimum d'achat</label>
                                        <input type="number" step="0.01" name="montant_minimum" class="form-control" 
                                               value="<?php echo $promotion['montant_minimum'] ?? '0'; ?>"
                                               placeholder="0.00">
                                        <div class="form-help">Montant minimum pour utiliser cette promotion (0 = aucun minimum)</div>
                                    </div>
                                </div>
                                
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-alt"></i> Date de début <span class="required">*</span></label>
                                        <input type="datetime-local" name="date_debut" class="form-control" required 
                                               value="<?php echo isset($promotion['date_debut']) ? date('Y-m-d\TH:i', strtotime($promotion['date_debut'])) : ''; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar-check"></i> Date de fin <span class="required">*</span></label>
                                        <input type="datetime-local" name="date_fin" class="form-control" required 
                                               value="<?php echo isset($promotion['date_fin']) ? date('Y-m-d\TH:i', strtotime($promotion['date_fin'])) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row-2">
                                    <div class="form-group">
                                        <label><i class="fas fa-users"></i> Utilisations maximales</label>
                                        <input type="number" name="utilisations_max" class="form-control" 
                                               value="<?php echo $promotion['utilisations_max'] ?? ''; ?>"
                                               placeholder="Illimité">
                                        <div class="form-help">Nombre maximum d'utilisations (vide = illimité)</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <div class="form-check" style="margin-top: 32px;">
                                            <input type="checkbox" name="actif" id="actif" value="1" 
                                                   <?php echo (!isset($promotion['actif']) || $promotion['actif'] == 1) ? 'checked' : ''; ?>>
                                            <label for="actif">
                                                <i class="fas fa-toggle-on"></i> Promotion active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-align-left"></i> Description</label>
                                    <textarea name="description" class="form-control" rows="3" 
                                              placeholder="Décrivez votre promotion (optionnel)..."><?php echo htmlspecialchars($promotion['description'] ?? ''); ?></textarea>
                                    <div class="form-help">Information supplémentaire visible par les clients</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Boutons d'action -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Créer la promotion' : 'Enregistrer les modifications'; ?>
                            </button>
                            <a href="admin_promotions.php?action=list" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL SUPPRESSION -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 28px;"></i>
                <h3>Confirmer la suppression</h3>
            </div>
            <p>Supprimer la promotion "<span id="promoName"></span>" ?</p>
            <p style="color: #f44336; font-size: 0.85rem; margin: 16px 0;">
                <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
            </p>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id_promotion" id="promoId">
                <div style="display: flex; gap: 12px;">
                    <button type="button" onclick="closeModal()" class="btn btn-secondary w-100">Annuler</button>
                    <button type="submit" class="btn btn-danger w-100">Supprimer</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(id, name) {
            document.getElementById('promoId').value = id;
            document.getElementById('promoName').textContent = name;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) closeModal();
        }
        
        // Gestion dynamique du message d'aide pour la valeur de réduction
        const typeSelect = document.getElementById('typePromotion');
        const valeurInput = document.getElementById('valeurInput');
        const valeurHelp = document.getElementById('valeurHelp');
        
        if (typeSelect) {
            function updateValeurHelp() {
                const type = typeSelect.value;
                if (type === 'pourcentage') {
                    valeurHelp.innerHTML = 'Exemple: 10 pour -10% (maximum 100%)';
                    valeurInput.placeholder = '10';
                    valeurInput.max = 100;
                    valeurInput.step = 0.01;
                } else if (type === 'montant_fixe') {
                    valeurHelp.innerHTML = 'Exemple: 10 pour une réduction de 10€';
                    valeurInput.placeholder = '10.00';
                    valeurInput.max = null;
                    valeurInput.step = 0.01;
                } else {
                    valeurHelp.innerHTML = 'La valeur sera ignorée pour la livraison gratuite';
                    valeurInput.placeholder = '0';
                    valeurInput.disabled = true;
                    valeurInput.value = 0;
                }
            }
            
            typeSelect.addEventListener('change', function() {
                if (typeSelect.value === 'livraison_gratuite') {
                    valeurInput.disabled = true;
                    valeurInput.value = 0;
                } else {
                    valeurInput.disabled = false;
                }
                updateValeurHelp();
            });
            
            updateValeurHelp();
        }
        
        // Validation du formulaire
        document.getElementById('promotionForm')?.addEventListener('submit', function(e) {
            const dateDebut = new Date(document.querySelector('[name="date_debut"]').value);
            const dateFin = new Date(document.querySelector('[name="date_fin"]').value);
            
            if (dateFin <= dateDebut) {
                e.preventDefault();
                alert('La date de fin doit être postérieure à la date de début.');
                return false;
            }
            
            const type = document.querySelector('[name="type_promotion"]').value;
            const valeur = parseFloat(document.querySelector('[name="valeur"]').value);
            
            if (type === 'pourcentage' && valeur > 100) {
                e.preventDefault();
                alert('Le pourcentage de réduction ne peut pas dépasser 100%.');
                return false;
            }
            
            if (type !== 'livraison_gratuite' && (isNaN(valeur) || valeur <= 0)) {
                e.preventDefault();
                alert('Veuillez entrer une valeur de réduction valide (supérieure à 0).');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>