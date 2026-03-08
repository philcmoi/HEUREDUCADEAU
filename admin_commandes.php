<?php
// admin_commandes.php - Gestion complète des commandes (CRUD)
// VERSION RESPONSIVE OPTIMISÉE

require_once 'admin_protection.php';

// ============================================
// CONFIGURATION
//=============================================
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
// FONCTIONS CRUD
// ============================================

/**
 * Récupère toutes les commandes avec pagination et filtres
 */
function getAllCommandes($pdo, $page = 1, $limit = 20, $filtres = []) {
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                c.id_commande,
                c.numero_commande,
                c.date_commande,
                c.total_ttc,
                c.statut,
                c.statut_paiement,
                c.mode_paiement,
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.email as client_email,
                cl.is_temporary,
                (SELECT COUNT(*) FROM commande_items WHERE id_commande = c.id_commande) as nb_articles
            FROM commandes c
            INNER JOIN clients cl ON c.id_client = cl.id_client
            WHERE 1=1";
    
    $params = [];
    
    // Filtre par statut
    if (!empty($filtres['statut'])) {
        $sql .= " AND c.statut = :statut";
        $params['statut'] = $filtres['statut'];
    }
    
    // Filtre par statut paiement
    if (!empty($filtres['statut_paiement'])) {
        $sql .= " AND c.statut_paiement = :statut_paiement";
        $params['statut_paiement'] = $filtres['statut_paiement'];
    }
    
    // Filtre par recherche (numéro commande, email, nom)
    if (!empty($filtres['search'])) {
        $sql .= " AND (c.numero_commande LIKE :search 
                      OR cl.email LIKE :search 
                      OR cl.nom LIKE :search 
                      OR cl.prenom LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    // Filtre par date
    if (!empty($filtres['date_debut'])) {
        $sql .= " AND DATE(c.date_commande) >= :date_debut";
        $params['date_debut'] = $filtres['date_debut'];
    }
    if (!empty($filtres['date_fin'])) {
        $sql .= " AND DATE(c.date_commande) <= :date_fin";
        $params['date_fin'] = $filtres['date_fin'];
    }
    
    // Tri
    $sql .= " ORDER BY c.date_commande DESC LIMIT :limit OFFSET :offset";
    
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
 * Compte le nombre total de commandes (pour pagination)
 */
function countCommandes($pdo, $filtres = []) {
    $sql = "SELECT COUNT(*) as total 
            FROM commandes c
            INNER JOIN clients cl ON c.id_client = cl.id_client
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtres['statut'])) {
        $sql .= " AND c.statut = :statut";
        $params['statut'] = $filtres['statut'];
    }
    
    if (!empty($filtres['statut_paiement'])) {
        $sql .= " AND c.statut_paiement = :statut_paiement";
        $params['statut_paiement'] = $filtres['statut_paiement'];
    }
    
    if (!empty($filtres['search'])) {
        $sql .= " AND (c.numero_commande LIKE :search 
                      OR cl.email LIKE :search 
                      OR cl.nom LIKE :search 
                      OR cl.prenom LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

/**
 * Récupère une commande avec tous ses détails
 */
function getCommandeById($pdo, $id) {
    // Infos principales de la commande
    $sql = "SELECT 
                c.*,
                cl.id_client,
                cl.email as client_email,
                cl.nom as client_nom,
                cl.prenom as client_prenom,
                cl.telephone as client_telephone,
                cl.is_temporary,
                a_liv.nom as livraison_nom,
                a_liv.prenom as livraison_prenom,
                a_liv.adresse as livraison_adresse,
                a_liv.complement as livraison_complement,
                a_liv.code_postal as livraison_code_postal,
                a_liv.ville as livraison_ville,
                a_liv.pays as livraison_pays,
                a_liv.telephone as livraison_telephone,
                a_fact.nom as facturation_nom,
                a_fact.prenom as facturation_prenom,
                a_fact.adresse as facturation_adresse,
                a_fact.complement as facturation_complement,
                a_fact.code_postal as facturation_code_postal,
                a_fact.ville as facturation_ville,
                a_fact.pays as facturation_pays,
                a_fact.telephone as facturation_telephone
            FROM commandes c
            INNER JOIN clients cl ON c.id_client = cl.id_client
            LEFT JOIN adresses a_liv ON c.id_adresse_livraison = a_liv.id_adresse
            LEFT JOIN adresses a_fact ON c.id_adresse_facturation = a_fact.id_adresse
            WHERE c.id_commande = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $commande = $stmt->fetch();
    
    if (!$commande) {
        return null;
    }
    
    // Récupérer les articles
    $sql_items = "SELECT 
                    ci.*,
                    p.reference,
                    p.nom as produit_nom,
                    p.slug
                  FROM commande_items ci
                  LEFT JOIN produits p ON ci.id_produit = p.id_produit
                  WHERE ci.id_commande = :id
                  ORDER BY ci.id_item";
    
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute(['id' => $id]);
    $commande['articles'] = $stmt_items->fetchAll();
    
    // Récupérer les transactions
    $sql_trans = "SELECT * FROM transactions 
                  WHERE id_commande = :id 
                  ORDER BY date_creation DESC";
    
    $stmt_trans = $pdo->prepare($sql_trans);
    $stmt_trans->execute(['id' => $id]);
    $commande['transactions'] = $stmt_trans->fetchAll();
    
    return $commande;
}

/**
 * Met à jour le statut d'une commande
 */
function updateCommandeStatut($pdo, $id, $statut, $statut_paiement = null) {
    $sql = "UPDATE commandes SET 
            statut = :statut,
            date_modification = NOW()";
    
    $params = [
        'statut' => $statut,
        'id' => $id
    ];
    
    if ($statut_paiement !== null) {
        $sql .= ", statut_paiement = :statut_paiement";
        $params['statut_paiement'] = $statut_paiement;
    }
    
    // Si on marque comme expédiée, ajouter la date d'expédition
    if ($statut === 'expediee') {
        $sql .= ", date_expedition = NOW()";
    }
    
    // Si on marque comme livrée, ajouter la date de livraison réelle
    if ($statut === 'livree') {
        $sql .= ", date_livraison_reelle = NOW()";
    }
    
    $sql .= " WHERE id_commande = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Ajoute un numéro de suivi à une commande
 */
function addNumeroSuivi($pdo, $id, $numero_suivi, $transporteur) {
    $sql = "UPDATE commandes SET 
            numero_suivi = :numero_suivi,
            transporteur = :transporteur,
            date_modification = NOW()
            WHERE id_commande = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'numero_suivi' => $numero_suivi,
        'transporteur' => $transporteur,
        'id' => $id
    ]);
}

/**
 * Récupère les statistiques des commandes
 */
function getCommandesStats($pdo) {
    $stats = [];
    
    // Total par statut
    $sql = "SELECT 
            statut,
            COUNT(*) as total,
            SUM(total_ttc) as montant_total
            FROM commandes 
            GROUP BY statut";
    $stmt = $pdo->query($sql);
    $stats['par_statut'] = $stmt->fetchAll();
    
    // Total par statut paiement
    $sql = "SELECT 
            statut_paiement,
            COUNT(*) as total,
            SUM(total_ttc) as montant_total
            FROM commandes 
            GROUP BY statut_paiement";
    $stmt = $pdo->query($sql);
    $stats['par_paiement'] = $stmt->fetchAll();
    
    // Commandes du jour
    $sql = "SELECT COUNT(*) as total, SUM(total_ttc) as montant 
            FROM commandes 
            WHERE DATE(date_commande) = CURDATE()";
    $stmt = $pdo->query($sql);
    $stats['aujourd_hui'] = $stmt->fetch();
    
    // Commandes du mois
    $sql = "SELECT COUNT(*) as total, SUM(total_ttc) as montant 
            FROM commandes 
            WHERE MONTH(date_commande) = MONTH(CURDATE()) 
            AND YEAR(date_commande) = YEAR(CURDATE())";
    $stmt = $pdo->query($sql);
    $stats['ce_mois'] = $stmt->fetch();
    
    // Chiffre d'affaires total
    $sql = "SELECT SUM(total_ttc) as ca_total FROM commandes WHERE statut_paiement = 'paye'";
    $stmt = $pdo->query($sql);
    $stats['ca_total'] = $stmt->fetch()['ca_total'] ?? 0;
    
    return $stats;
}

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================

// Récupérer les filtres depuis l'URL
$filtres = [
    'statut' => $_GET['statut'] ?? '',
    'statut_paiement' => $_GET['statut_paiement'] ?? '',
    'search' => $_GET['search'] ?? '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // MISE À JOUR STATUT
    if (isset($_POST['action']) && $_POST['action'] === 'update_statut') {
        $id = intval($_POST['id_commande']);
        $statut = $_POST['statut'];
        $statut_paiement = $_POST['statut_paiement'] ?? null;
        
        if (updateCommandeStatut($pdo, $id, $statut, $statut_paiement)) {
            // Journaliser l'action
            logAction('info', 'Statut commande mis à jour', [
                'commande_id' => $id,
                'nouveau_statut' => $statut,
                'nouveau_paiement' => $statut_paiement,
                'admin_id' => $_SESSION['admin_id']
            ]);
            
            $_SESSION['message'] = "Statut de la commande #$id mis à jour avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la mise à jour du statut.";
        }
        
        header('Location: admin_commandes.php?action=view&id=' . $id);
        exit;
    }
    
    // AJOUT NUMÉRO DE SUIVI
    if (isset($_POST['action']) && $_POST['action'] === 'add_suivi') {
        $id = intval($_POST['id_commande']);
        $numero_suivi = trim($_POST['numero_suivi']);
        $transporteur = trim($_POST['transporteur']);
        
        if (addNumeroSuivi($pdo, $id, $numero_suivi, $transporteur)) {
            // Si on ajoute un suivi, on peut aussi mettre à jour le statut à "expediee" si ce n'est pas déjà fait
            $commande = getCommandeById($pdo, $id);
            if ($commande && $commande['statut'] === 'confirmee') {
                updateCommandeStatut($pdo, $id, 'expediee');
            }
            
            logAction('info', 'Numéro de suivi ajouté', [
                'commande_id' => $id,
                'transporteur' => $transporteur,
                'numero' => $numero_suivi
            ]);
            
            $_SESSION['message'] = "Numéro de suivi ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout du numéro de suivi.";
        }
        
        header('Location: admin_commandes.php?action=view&id=' . $id);
        exit;
    }
    
    // SUPPRIMER COMMANDE (superadmin uniquement)
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Vérifier que c'est un superadmin
        if ($admin_role !== 'superadmin') {
            $_SESSION['error'] = "Seuls les super administrateurs peuvent supprimer des commandes.";
            header('Location: admin_commandes.php?action=list');
            exit;
        }
        
        $id = intval($_POST['id_commande']);
        
        try {
            $pdo->beginTransaction();
            
            // Supprimer les articles
            $stmt = $pdo->prepare("DELETE FROM commande_items WHERE id_commande = ?");
            $stmt->execute([$id]);
            
            // Supprimer les transactions
            $stmt = $pdo->prepare("DELETE FROM transactions WHERE id_commande = ?");
            $stmt->execute([$id]);
            
            // Supprimer la commande
            $stmt = $pdo->prepare("DELETE FROM commandes WHERE id_commande = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            
            logAction('securite', 'Commande supprimée', [
                'commande_id' => $id,
                'admin_id' => $_SESSION['admin_id']
            ]);
            
            $_SESSION['message'] = "Commande #$id supprimée avec succès.";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur lors de la suppression: " . $e->getMessage();
        }
        
        header('Location: admin_commandes.php?action=list');
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

// Récupérer les statistiques pour le dashboard
$stats = getCommandesStats($pdo);
$total_commandes = countCommandes($pdo, $filtres);
$total_pages = ceil($total_commandes / $limit);

// Récupérer les commandes selon l'action
$commandes = [];
if ($action === 'list') {
    $commandes = getAllCommandes($pdo, $page, $limit, $filtres);
} elseif ($action === 'view' && $id > 0) {
    $commande = getCommandeById($pdo, $id);
    if (!$commande) {
        $error = "Commande non trouvée.";
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestion des Commandes - Heure du Cadeau</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================
           STYLES RESPONSIVES OPTIMISÉS - COHÉRENTS AVEC ADMIN
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
        .stat-card.attente { border-left-color: #FFC107; }
        .stat-card.paye { border-left-color: var(--success-color); }
        .stat-card.expedie { border-left-color: #9C27B0; }
        .stat-card.ca { border-left-color: var(--danger-color); }
        
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
            flex-wrap: wrap;
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
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        @media (max-width: 767px) {
            table {
                font-size: 13px;
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
        
        /* Badges de statut */
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
        
        .badge.attente { background: #fff3cd; color: #856404; }
        .badge.confirmee { background: #d4edda; color: #155724; }
        .badge.preparation { background: #cce5ff; color: #004085; }
        .badge.expediee { background: #d1ecf1; color: #0c5460; }
        .badge.livree { background: #d4edda; color: #155724; }
        .badge.annulee { background: #f8d7da; color: #721c24; }
        .badge.remboursee { background: #e2e3e5; color: #383d41; }
        .badge.paye { background: #d4edda; color: #155724; }
        .badge.en_attente { background: #fff3cd; color: #856404; }
        .badge.echec { background: #f8d7da; color: #721c24; }
        .badge.rembourse { background: #e2e3e5; color: #383d41; }
        
        .badge.temp {
            background: #ffc107;
            color: #000;
            padding: 2px 6px;
            font-size: 10px;
        }
        
        .actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 767px) {
            .actions .btn-sm {
                padding: 6px;
                font-size: 11px;
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
        
        /* Detail commande responsive */
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
        
        .commande-numero {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            word-break: break-word;
        }
        
        @media (min-width: 768px) {
            .commande-numero {
                font-size: 24px;
            }
        }
        
        .commande-date {
            color: #7f8c8d;
            font-size: 13px;
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
        
        /* Suivi info */
        .suivi-info {
            background: #e8f5e9;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        
        /* Articles table responsive */
        .articles-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin: 15px 0;
        }
        
        .articles-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }
        
        .articles-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
        }
        
        .articles-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 13px;
        }
        
        .articles-table tfoot td {
            font-weight: 600;
            background: #f8f9fa;
            padding: 10px;
        }
        
        /* Status form responsive */
        .status-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        @media (min-width: 768px) {
            .status-form {
                padding: 20px;
                margin-top: 30px;
            }
        }
        
        .status-form form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .status-form form {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: flex-end;
            }
        }
        
        .status-form .form-group {
            flex: 1;
        }
        
        .status-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #444;
            font-size: 14px;
        }
        
        .status-form select,
        .status-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            font-size: 14px;
        }
        
        .status-form hr {
            margin: 15px 0;
            border: none;
            border-top: 1px solid #ddd;
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
        
        .modal-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (min-width: 768px) {
            .modal-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
        }
        
        /* Export section responsive */
        .export-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .export-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
            }
        }
        
        .export-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .export-card h3 {
            margin-bottom: 10px;
        }
        
        .export-card select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius-sm);
            margin: 5px 0 15px;
        }
        
        /* Classes utilitaires */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-2 { margin-top: 10px; }
        .mt-3 { margin-top: 15px; }
        .mt-4 { margin-top: 20px; }
        .mb-2 { margin-bottom: 10px; }
        .mb-3 { margin-bottom: 15px; }
        .mb-4 { margin-bottom: 20px; }
        .hide-mobile { display: none; }
        
        @media (min-width: 768px) {
            .hide-mobile { display: table-cell; }
            .hide-mobile-inline { display: inline; }
        }
        
        .show-mobile { display: inline; }
        
        @media (min-width: 768px) {
            .show-mobile { display: none; }
        }
        
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
        
        /* Graphique barres responsive */
        .chart-bar {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .chart-label {
            width: 50px;
            font-size: 12px;
        }
        
        @media (min-width: 768px) {
            .chart-label {
                width: 70px;
                font-size: 13px;
            }
        }
        
        .chart-value {
            flex: 1;
            min-width: 120px;
        }
        
        .chart-bar-fill {
            height: 20px;
            background: linear-gradient(90deg, var(--primary-color), #2575fc);
            border-radius: 4px;
            min-width: 2px;
        }
        
        .chart-number {
            width: 70px;
            text-align: right;
            font-size: 11px;
        }
        
        @media (min-width: 768px) {
            .chart-number {
                width: 90px;
                font-size: 12px;
            }
        }
        
        /* Statistiques internes */
        .stats-inner-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        @media (min-width: 768px) {
            .stats-inner-grid {
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
        }
        
        .stat-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .stat-box h3 {
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .stat-box table {
            min-width: auto;
            width: 100%;
        }
        
        .stat-box td {
            padding: 5px;
            font-size: 13px;
        }
        
        /* Badge temporaire */
        .badge-temp {
            background: #ffc107;
            color: #000;
            padding: 2px 6px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-shopping-cart"></i> Gestion des Commandes</h1>
                <p>Bienvenue, <?php echo htmlspecialchars($admin_username); ?></p>
            </div>
            <div class="role-badge <?php echo $admin_role === 'superadmin' ? 'superadmin-badge' : ''; ?>">
                <i class="fas fa-user-shield"></i> <?php echo htmlspecialchars(ucfirst($admin_role)); ?>
            </div>
        </div>

        <!-- Navigation -->
        <div class="nav-tabs">
            <a href="dashboard.php">
                <i class="fas fa-arrow-left"></i> <span class="hide-mobile-inline">Retour Dashboard</span>
            </a>
            <a href="admin_commandes.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <span>Liste</span>
            </a>
            <a href="admin_commandes.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span>Stats</span>
            </a>
            <?php if ($admin_role === 'superadmin'): ?>
            <a href="admin_commandes.php?action=export" class="<?php echo $action == 'export' ? 'active' : ''; ?>">
                <i class="fas fa-file-export"></i> <span>Export</span>
            </a>
            <?php endif; ?>
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

        <!-- STATS DASHBOARD (résumé sur toutes les pages sauf view) -->
        <?php if ($action !== 'view' && $action !== 'stats'): ?>
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-value"><?php echo number_format($stats['ce_mois']['total'] ?? 0); ?></div>
                <div class="stat-label">Commandes ce mois</div>
                <small><?php echo number_format($stats['ce_mois']['montant'] ?? 0, 0); ?> €</small>
            </div>
            <div class="stat-card attente">
                <?php 
                $attente = 0;
                foreach ($stats['par_statut'] as $s) {
                    if ($s['statut'] == 'en_attente') $attente = $s['total'];
                }
                ?>
                <div class="stat-value"><?php echo $attente; ?></div>
                <div class="stat-label">En attente</div>
            </div>
            <div class="stat-card paye">
                <?php 
                $paye = 0;
                foreach ($stats['par_paiement'] as $p) {
                    if ($p['statut_paiement'] == 'paye') $paye = $p['total'];
                }
                ?>
                <div class="stat-value"><?php echo $paye; ?></div>
                <div class="stat-label">Paiements reçus</div>
            </div>
            <div class="stat-card expedie">
                <?php 
                $expedie = 0;
                foreach ($stats['par_statut'] as $s) {
                    if ($s['statut'] == 'expediee') $expedie = $s['total'];
                }
                ?>
                <div class="stat-value"><?php echo $expedie; ?></div>
                <div class="stat-label">Expédiées</div>
            </div>
            <div class="stat-card ca">
                <div class="stat-value"><?php echo number_format($stats['ca_total'], 0); ?> €</div>
                <div class="stat-label">CA Total</div>
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
                        <input type="text" name="search" placeholder="N° commande, email, nom..." 
                               value="<?php echo htmlspecialchars($filtres['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Statut</label>
                        <select name="statut">
                            <option value="">Tous</option>
                            <option value="en_attente" <?php echo $filtres['statut'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="confirmee" <?php echo $filtres['statut'] == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                            <option value="en_preparation" <?php echo $filtres['statut'] == 'en_preparation' ? 'selected' : ''; ?>>Préparation</option>
                            <option value="expediee" <?php echo $filtres['statut'] == 'expediee' ? 'selected' : ''; ?>>Expédiée</option>
                            <option value="livree" <?php echo $filtres['statut'] == 'livree' ? 'selected' : ''; ?>>Livrée</option>
                            <option value="annulee" <?php echo $filtres['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-credit-card"></i> Paiement</label>
                        <select name="statut_paiement">
                            <option value="">Tous</option>
                            <option value="en_attente" <?php echo $filtres['statut_paiement'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                            <option value="paye" <?php echo $filtres['statut_paiement'] == 'paye' ? 'selected' : ''; ?>>Payé</option>
                            <option value="echec" <?php echo $filtres['statut_paiement'] == 'echec' ? 'selected' : ''; ?>>Échec</option>
                            <option value="rembourse" <?php echo $filtres['statut_paiement'] == 'rembourse' ? 'selected' : ''; ?>>Remboursé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date début</label>
                        <input type="date" name="date_debut" value="<?php echo $filtres['date_debut']; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Date fin</label>
                        <input type="date" name="date_fin" value="<?php echo $filtres['date_fin']; ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                        <a href="admin_commandes.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- LISTE DES COMMANDES -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-list"></i> Liste des commandes (<?php echo $total_commandes; ?>)</h3>
                </div>
                
                <?php if (empty($commandes)): ?>
                    <div class="text-center" style="padding: 40px 20px;">
                        <i class="fas fa-shopping-cart" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777;">Aucune commande trouvée</h3>
                        <p style="color: #999;">Modifiez vos filtres ou revenez plus tard.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>N° Commande</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Articles</th>
                                    <th>Statut</th>
                                    <th class="hide-mobile">Paiement</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commandes as $cmd): ?>
                                <tr>
                                    <td>#<?php echo $cmd['id_commande']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($cmd['numero_commande']); ?></strong>
                                    </td>
                                    <td><?php echo date('d/m H:i', strtotime($cmd['date_commande'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($cmd['client_prenom'] . ' ' . $cmd['client_nom']); ?>
                                        <?php if ($cmd['is_temporary']): ?>
                                            <span class="badge-temp">T</span>
                                        <?php endif; ?>
                                        <div class="show-mobile" style="font-size: 11px; color: #666;">
                                            <?php echo htmlspecialchars($cmd['client_email']); ?>
                                        </div>
                                    </td>
                                    <td><strong><?php echo number_format($cmd['total_ttc'], 0); ?> €</strong></td>
                                    <td><?php echo $cmd['nb_articles']; ?></td>
                                    <td>
                                        <?php
                                        $statut_class = [
                                            'en_attente' => 'attente',
                                            'confirmee' => 'confirmee',
                                            'en_preparation' => 'preparation',
                                            'expediee' => 'expediee',
                                            'livree' => 'livree',
                                            'annulee' => 'annulee'
                                        ];
                                        $class = $statut_class[$cmd['statut']] ?? 'attente';
                                        $statut_text = [
                                            'en_attente' => 'Attente',
                                            'confirmee' => 'Confirmée',
                                            'en_preparation' => 'Prépa',
                                            'expediee' => 'Expédiée',
                                            'livree' => 'Livrée',
                                            'annulee' => 'Annulée'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $class; ?>">
                                            <?php echo $statut_text[$cmd['statut']] ?? $cmd['statut']; ?>
                                        </span>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php
                                        $paiement_class = [
                                            'en_attente' => 'en_attente',
                                            'paye' => 'paye',
                                            'echec' => 'echec',
                                            'rembourse' => 'rembourse'
                                        ];
                                        $p_class = $paiement_class[$cmd['statut_paiement']] ?? 'en_attente';
                                        $p_text = [
                                            'en_attente' => 'Attente',
                                            'paye' => 'Payé',
                                            'echec' => 'Échec',
                                            'rembourse' => 'Remb.'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $p_class; ?>">
                                            <?php echo $p_text[$cmd['statut_paiement']] ?? $cmd['statut_paiement']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="admin_commandes.php?action=view&id=<?php echo $cmd['id_commande']; ?>" 
                                               class="btn btn-info btn-sm" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($cmd['statut'] == 'confirmee'): ?>
                                            <button onclick="openSuiviModal(<?php echo $cmd['id_commande']; ?>, '<?php echo htmlspecialchars($cmd['numero_commande']); ?>')" 
                                                    class="btn btn-success btn-sm" title="Ajouter suivi">
                                                <i class="fas fa-truck"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($admin_role === 'superadmin'): ?>
                                            <button onclick="confirmDelete(<?php echo $cmd['id_commande']; ?>, '<?php echo htmlspecialchars($cmd['numero_commande']); ?>')" 
                                                    class="btn btn-danger btn-sm" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
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

        <?php elseif ($action == 'view' && isset($commande)): ?>
            <!-- DÉTAIL D'UNE COMMANDE -->
            <div class="detail-container">
                <div class="detail-header">
                    <div>
                        <span class="commande-numero">
                            <i class="fas fa-receipt"></i> <?php echo htmlspecialchars($commande['numero_commande']); ?>
                        </span>
                        <div class="commande-date">
                            Créée le <?php echo date('d/m/Y à H:i', strtotime($commande['date_commande'])); ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="admin_commandes.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <span class="hide-mobile-inline">Retour</span>
                        </a>
                        <button onclick="window.print()" class="btn btn-info">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>

                <!-- INFOS CLIENT & ADRESSES -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-user"></i> Client</h3>
                        <div class="info-row">
                            <span class="info-label">Nom</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($commande['client_prenom'] . ' ' . $commande['client_nom']); ?>
                                <?php if ($commande['is_temporary']): ?>
                                    <span class="badge-temp">Temporaire</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($commande['client_email']); ?>">
                                    <?php echo htmlspecialchars($commande['client_email']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Téléphone</span>
                            <span class="info-value">
                                <a href="tel:<?php echo htmlspecialchars($commande['client_telephone']); ?>">
                                    <?php echo htmlspecialchars($commande['client_telephone'] ?: 'Non renseigné'); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">ID Client</span>
                            <span class="info-value">#<?php echo $commande['id_client']; ?></span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-truck"></i> Livraison</h3>
                        <div class="info-row">
                            <span class="info-label">Destinataire</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($commande['livraison_prenom'] . ' ' . $commande['livraison_nom']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Adresse</span>
                            <span class="info-value">
                                <?php echo nl2br(htmlspecialchars($commande['livraison_adresse'])); ?>
                                <?php if (!empty($commande['livraison_complement'])): ?>
                                    <br><?php echo htmlspecialchars($commande['livraison_complement']); ?>
                                <?php endif; ?>
                                <br><?php echo htmlspecialchars($commande['livraison_code_postal'] . ' ' . $commande['livraison_ville']); ?>
                                <br><?php echo htmlspecialchars($commande['livraison_pays']); ?>
                            </span>
                        </div>
                        <?php if (!empty($commande['livraison_telephone'])): ?>
                        <div class="info-row">
                            <span class="info-label">Tél.</span>
                            <span class="info-value"><?php echo htmlspecialchars($commande['livraison_telephone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($commande['transporteur']) || !empty($commande['numero_suivi'])): ?>
                        <div class="suivi-info">
                            <span class="info-label">Suivi</span>
                            <span class="info-value">
                                <strong><?php echo htmlspecialchars($commande['transporteur']); ?></strong><br>
                                <?php if (!empty($commande['numero_suivi'])): ?>
                                N°: <?php echo htmlspecialchars($commande['numero_suivi']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($commande['id_adresse_facturation'] && $commande['id_adresse_facturation'] != $commande['id_adresse_livraison']): ?>
                    <div class="info-card">
                        <h3><i class="fas fa-file-invoice"></i> Facturation</h3>
                        <div class="info-row">
                            <span class="info-label">Destinataire</span>
                            <span class="info-value">
                                <?php echo htmlspecialchars($commande['facturation_prenom'] . ' ' . $commande['facturation_nom']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Adresse</span>
                            <span class="info-value">
                                <?php echo nl2br(htmlspecialchars($commande['facturation_adresse'])); ?>
                                <?php if (!empty($commande['facturation_complement'])): ?>
                                    <br><?php echo htmlspecialchars($commande['facturation_complement']); ?>
                                <?php endif; ?>
                                <br><?php echo htmlspecialchars($commande['facturation_code_postal'] . ' ' . $commande['facturation_ville']); ?>
                                <br><?php echo htmlspecialchars($commande['facturation_pays']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- RÉCAPITULATIF COMMANDE -->
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-box"></i> Articles commandés</h3>
                    <div class="articles-table-container">
                        <table class="articles-table">
                            <thead>
                                <tr>
                                    <th>Réf.</th>
                                    <th>Produit</th>
                                    <th>Qté</th>
                                    <th>Prix HT</th>
                                    <th>TVA</th>
                                    <th>Prix TTC</th>
                                    <th>Total TTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_ht = 0;
                                foreach ($commande['articles'] as $article): 
                                    $total_ht += $article['prix_unitaire_ht'] * $article['quantite'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($article['reference_produit']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($article['nom_produit']); ?></strong>
                                        <?php if (!empty($article['options'])): ?>
                                            <br><small><?php echo htmlspecialchars($article['options']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $article['quantite']; ?></td>
                                    <td><?php echo number_format($article['prix_unitaire_ht'], 0); ?> €</td>
                                    <td><?php echo $article['tva']; ?>%</td>
                                    <td><?php echo number_format($article['prix_unitaire_ttc'], 0); ?> €</td>
                                    <td><strong><?php echo number_format($article['prix_unitaire_ttc'] * $article['quantite'], 0); ?> €</strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" style="text-align: right;"><strong>Sous-total HT:</strong></td>
                                    <td><strong><?php echo number_format($total_ht, 2); ?> €</strong></td>
                                </tr>
                                <tr>
                                    <td colspan="6" style="text-align: right;">Livraison:</td>
                                    <td><?php echo number_format($commande['frais_livraison'], 2); ?> €</td>
                                </tr>
                                <?php if ($commande['reduction'] > 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: right;">Réduction:</td>
                                    <td>-<?php echo number_format($commande['reduction'], 2); ?> €</td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td colspan="6" style="text-align: right; font-size: 1.1em;"><strong>Total TTC:</strong></td>
                                    <td style="font-size: 1.1em; color: #e74c3c;">
                                        <strong><?php echo number_format($commande['total_ttc'], 2); ?> €</strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- PAIEMENT -->
                <?php if (!empty($commande['transactions'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-credit-card"></i> Transactions</h3>
                    <div class="articles-table-container">
                        <table class="articles-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>N° Transaction</th>
                                    <th>Méthode</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commande['transactions'] as $trans): ?>
                                <tr>
                                    <td><?php echo date('d/m H:i', strtotime($trans['date_creation'])); ?></td>
                                    <td><?php echo htmlspecialchars($trans['numero_transaction']); ?></td>
                                    <td><?php echo $trans['methode_paiement']; ?></td>
                                    <td><?php echo number_format($trans['montant'], 2); ?> €</td>
                                    <td>
                                        <span class="badge <?php echo $trans['statut'] == 'paye' ? 'paye' : 'en_attente'; ?>">
                                            <?php echo $trans['statut']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- FORMULAIRE DE MISE À JOUR DU STATUT -->
                <div class="status-form">
                    <h3 style="margin-bottom: 15px;">
                        <i class="fas fa-edit"></i> Mettre à jour le statut
                    </h3>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="update_statut">
                        <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                        
                        <div class="form-group">
                            <label>Statut commande :</label>
                            <select name="statut">
                                <option value="en_attente" <?php echo $commande['statut'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="confirmee" <?php echo $commande['statut'] == 'confirmee' ? 'selected' : ''; ?>>Confirmée</option>
                                <option value="en_preparation" <?php echo $commande['statut'] == 'en_preparation' ? 'selected' : ''; ?>>En préparation</option>
                                <option value="expediee" <?php echo $commande['statut'] == 'expediee' ? 'selected' : ''; ?>>Expédiée</option>
                                <option value="livree" <?php echo $commande['statut'] == 'livree' ? 'selected' : ''; ?>>Livrée</option>
                                <option value="annulee" <?php echo $commande['statut'] == 'annulee' ? 'selected' : ''; ?>>Annulée</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Statut paiement :</label>
                            <select name="statut_paiement">
                                <option value="">-- Inchangé --</option>
                                <option value="en_attente" <?php echo $commande['statut_paiement'] == 'en_attente' ? 'selected' : ''; ?>>En attente</option>
                                <option value="paye" <?php echo $commande['statut_paiement'] == 'paye' ? 'selected' : ''; ?>>Payé</option>
                                <option value="echec" <?php echo $commande['statut_paiement'] == 'echec' ? 'selected' : ''; ?>>Échec</option>
                                <option value="rembourse" <?php echo $commande['statut_paiement'] == 'rembourse' ? 'selected' : ''; ?>>Remboursé</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i> Mettre à jour
                            </button>
                        </div>
                    </form>
                    
                    <!-- Formulaire pour ajouter un numéro de suivi -->
                    <?php if ($commande['statut'] == 'confirmee' || $commande['statut'] == 'expediee'): ?>
                    <hr>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_suivi">
                        <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                        
                        <div class="form-group">
                            <label>Transporteur :</label>
                            <select name="transporteur">
                                <option value="">-- Sélectionner --</option>
                                <option value="Colissimo">Colissimo</option>
                                <option value="Chronopost">Chronopost</option>
                                <option value="Mondial Relay">Mondial Relay</option>
                                <option value="DPD">DPD</option>
                                <option value="UPS">UPS</option>
                                <option value="FedEx">FedEx</option>
                                <option value="Autre">Autre</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Numéro de suivi :</label>
                            <input type="text" name="numero_suivi" placeholder="Ex: 7A12345678901" required>
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-success" style="width: 100%;">
                                <i class="fas fa-truck"></i> Ajouter le suivi
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                    
                    <!-- Bouton de suppression (superadmin uniquement) -->
                    <?php if ($admin_role === 'superadmin'): ?>
                    <hr>
                    
                    <form method="POST" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer cette commande ? Cette action est irréversible !');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_commande" value="<?php echo $commande['id_commande']; ?>">
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-trash"></i> Supprimer définitivement
                        </button>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            <i class="fas fa-exclamation-triangle"></i> Action irréversible
                        </small>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action == 'stats'): ?>
            <!-- STATISTIQUES DÉTAILLÉES -->
            <div class="detail-container">
                <h2 style="margin-bottom: 20px;">
                    <i class="fas fa-chart-bar"></i> Statistiques détaillées
                </h2>
                
                <div class="stats-grid" style="margin-bottom: 30px;">
                    <div class="stat-card total">
                        <div class="stat-value"><?php echo number_format($stats['aujourd_hui']['total'] ?? 0); ?></div>
                        <div class="stat-label">Aujourd'hui</div>
                        <small><?php echo number_format($stats['aujourd_hui']['montant'] ?? 0, 0); ?> €</small>
                    </div>
                    <div class="stat-card total">
                        <div class="stat-value"><?php echo number_format($stats['ce_mois']['total'] ?? 0); ?></div>
                        <div class="stat-label">Ce mois</div>
                        <small><?php echo number_format($stats['ce_mois']['montant'] ?? 0, 0); ?> €</small>
                    </div>
                    <div class="stat-card ca">
                        <div class="stat-value"><?php echo number_format($stats['ca_total'], 0); ?> €</div>
                        <div class="stat-label">CA Total</div>
                    </div>
                </div>
                
                <div class="stats-inner-grid">
                    
                    <!-- Répartition par statut -->
                    <div class="stat-box">
                        <h3>Par statut de commande</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%;">
                                <?php foreach ($stats['par_statut'] as $stat): 
                                    $statut_text = [
                                        'en_attente' => 'En attente',
                                        'confirmee' => 'Confirmée',
                                        'en_preparation' => 'Préparation',
                                        'expediee' => 'Expédiée',
                                        'livree' => 'Livrée',
                                        'annulee' => 'Annulée'
                                    ];
                                    $stat_class = [
                                        'en_attente' => 'attente',
                                        'confirmee' => 'confirmee',
                                        'en_preparation' => 'preparation',
                                        'expediee' => 'expediee',
                                        'livree' => 'livree',
                                        'annulee' => 'annulee'
                                    ];
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $stat_class[$stat['statut']] ?? 'attente'; ?>">
                                            <?php echo $statut_text[$stat['statut']] ?? $stat['statut']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $stat['total']; ?></strong></td>
                                    <td><?php echo number_format($stat['montant_total'] ?? 0, 0); ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Répartition par statut paiement -->
                    <div class="stat-box">
                        <h3>Par statut de paiement</h3>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%;">
                                <?php foreach ($stats['par_paiement'] as $stat): 
                                    $paiement_text = [
                                        'en_attente' => 'En attente',
                                        'paye' => 'Payé',
                                        'echec' => 'Échec',
                                        'rembourse' => 'Remboursé'
                                    ];
                                    $paiement_class = [
                                        'en_attente' => 'en_attente',
                                        'paye' => 'paye',
                                        'echec' => 'echec',
                                        'rembourse' => 'rembourse'
                                    ];
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge <?php echo $paiement_class[$stat['statut_paiement']] ?? 'en_attente'; ?>">
                                            <?php echo $paiement_text[$stat['statut_paiement']] ?? $stat['statut_paiement']; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $stat['total']; ?></strong></td>
                                    <td><?php echo number_format($stat['montant_total'] ?? 0, 0); ?> €</td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Graphique simple en barres -->
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;">Évolution (30 derniers jours)</h3>
                    <?php
                    try {
                        $sql = "SELECT 
                                DATE(date_commande) as jour,
                                COUNT(*) as nb_commandes,
                                SUM(total_ttc) as montant
                                FROM commandes
                                WHERE date_commande >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                GROUP BY DATE(date_commande)
                                ORDER BY jour DESC
                                LIMIT 30";
                        $stmt = $pdo->query($sql);
                        $evolution = $stmt->fetchAll();
                        
                        if (!empty($evolution)):
                            $max = max(array_column($evolution, 'nb_commandes'));
                    ?>
                    <div style="background: white; padding: 15px; border-radius: 8px;">
                        <?php foreach (array_reverse($evolution) as $jour): ?>
                        <div class="chart-bar">
                            <div class="chart-label"><?php echo date('d/m', strtotime($jour['jour'])); ?></div>
                            <div class="chart-value">
                                <div class="chart-bar-fill" style="width: <?php echo ($jour['nb_commandes'] / $max) * 100; ?>%;"></div>
                            </div>
                            <div class="chart-number">
                                <?php echo $jour['nb_commandes']; ?> (<?php echo number_format($jour['montant'], 0); ?>€)
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php 
                        endif;
                    } catch (Exception $e) {
                        echo "<p>Erreur chargement graphique</p>";
                    }
                    ?>
                </div>
            </div>

        <?php elseif ($action == 'export' && $admin_role === 'superadmin'): ?>
            <!-- EXPORT DES COMMANDES -->
            <div class="detail-container">
                <h2 style="margin-bottom: 20px;">
                    <i class="fas fa-file-export"></i> Export des commandes
                </h2>
                
                <div class="export-grid">
                    <div class="export-card">
                        <h3>Export CSV</h3>
                        <p>Télécharger la liste des commandes au format CSV</p>
                        <form method="GET" action="export_commandes.php" style="margin-top: 15px;">
                            <div style="margin-bottom: 15px;">
                                <label>Période :</label>
                                <select name="periode">
                                    <option value="aujourdhui">Aujourd'hui</option>
                                    <option value="semaine">Cette semaine</option>
                                    <option value="mois">Ce mois</option>
                                    <option value="annee">Cette année</option>
                                    <option value="tout">Toutes</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-download"></i> Télécharger CSV
                            </button>
                        </form>
                    </div>
                    
                    <div class="export-card">
                        <h3>Export PDF</h3>
                        <p>Générer un rapport PDF des commandes</p>
                        <form method="GET" action="export_commandes_pdf.php" style="margin-top: 15px;">
                            <div style="margin-bottom: 15px;">
                                <label>Type de rapport :</label>
                                <select name="type">
                                    <option value="recap">Récapitulatif</option>
                                    <option value="details">Liste détaillée</option>
                                    <option value="factures">Factures groupées</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger" style="width: 100%;">
                                <i class="fas fa-file-pdf"></i> Générer PDF
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL DE SUIVI -->
    <div id="suiviModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-truck"></i> Ajouter un numéro de suivi</h3>
            <p>Commande : <strong id="modalCommandeNumero"></strong></p>
            
            <form method="POST" id="suiviForm">
                <input type="hidden" name="action" value="add_suivi">
                <input type="hidden" name="id_commande" id="modalCommandeId">
                
                <div class="form-group">
                    <label>Transporteur :</label>
                    <select name="transporteur" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="Colissimo">Colissimo</option>
                        <option value="Chronopost">Chronopost</option>
                        <option value="Mondial Relay">Mondial Relay</option>
                        <option value="DPD">DPD</option>
                        <option value="UPS">UPS</option>
                        <option value="FedEx">FedEx</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Numéro de suivi :</label>
                    <input type="text" name="numero_suivi" required>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeSuiviModal()" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-success">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE SUPPRESSION -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle" style="color: #f44336; font-size: 24px;"></i>
                <h3 style="font-size: 20px;">Confirmer la suppression</h3>
            </div>
            
            <div style="margin-bottom: 25px; color: #666;">
                <p>Êtes-vous sûr de vouloir supprimer la commande "<span id="deleteCommandeNumero"></span>" ?</p>
                <p style="color: #f44336; font-weight: 600; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action est irréversible !
                </p>
            </div>
            
            <div class="modal-actions">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_commande" id="deleteCommandeId">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fonctions pour la modal de suivi
        function openSuiviModal(id, numero) {
            document.getElementById('modalCommandeId').value = id;
            document.getElementById('modalCommandeNumero').textContent = numero;
            document.getElementById('suiviModal').style.display = 'flex';
        }
        
        function closeSuiviModal() {
            document.getElementById('suiviModal').style.display = 'none';
        }
        
        // Fonctions pour la modal de suppression
        function confirmDelete(id, numero) {
            document.getElementById('deleteCommandeId').value = id;
            document.getElementById('deleteCommandeNumero').textContent = numero;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const suiviModal = document.getElementById('suiviModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === suiviModal) {
                closeSuiviModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Confirmation avant certaines actions
        document.addEventListener('DOMContentLoaded', function() {
            const statutForm = document.querySelector('form[action*="update_statut"]');
            if (statutForm) {
                statutForm.addEventListener('submit', function(e) {
                    const statut = this.querySelector('select[name="statut"]').value;
                    if (statut === 'annulee') {
                        if (!confirm('Attention : Annuler une commande est une action importante. Confirmer ?')) {
                            e.preventDefault();
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>