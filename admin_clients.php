<?php
// admin_clients.php - Gestion complète des clients (CRUD)
// VERSION RESPONSIVE OPTIMISÉE

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
// FONCTIONS CRUD CLIENTS
// ============================================

/**
 * Récupère tous les clients avec pagination et filtres
 */
function getAllClients($pdo, $page = 1, $limit = 20, $filtres = []) {
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT 
                c.id_client,
                c.email,
                c.nom,
                c.prenom,
                c.telephone,
                c.date_inscription,
                c.statut,
                c.is_temporary,
                c.newsletter,
                (SELECT COUNT(*) FROM commandes WHERE id_client = c.id_client) as nb_commandes,
                (SELECT SUM(total_ttc) FROM commandes WHERE id_client = c.id_client AND statut_paiement = 'paye') as ca_total,
                (SELECT MAX(date_commande) FROM commandes WHERE id_client = c.id_client) as derniere_commande
            FROM clients c
            WHERE 1=1";
    
    $params = [];
    
    // Filtre par recherche (email, nom, prénom)
    if (!empty($filtres['search'])) {
        $sql .= " AND (c.email LIKE :search 
                      OR c.nom LIKE :search 
                      OR c.prenom LIKE :search 
                      OR c.telephone LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    // Filtre par statut
    if (!empty($filtres['statut'])) {
        $sql .= " AND c.statut = :statut";
        $params['statut'] = $filtres['statut'];
    }
    
    // Filtre par type (temporaire/permanent)
    if (isset($filtres['temporaire']) && $filtres['temporaire'] !== '') {
        $sql .= " AND c.is_temporary = :temporaire";
        $params['temporaire'] = $filtres['temporaire'];
    }
    
    // Filtre par newsletter
    if (isset($filtres['newsletter']) && $filtres['newsletter'] !== '') {
        $sql .= " AND c.newsletter = :newsletter";
        $params['newsletter'] = $filtres['newsletter'];
    }
    
    // Filtre par date d'inscription
    if (!empty($filtres['date_debut'])) {
        $sql .= " AND DATE(c.date_inscription) >= :date_debut";
        $params['date_debut'] = $filtres['date_debut'];
    }
    if (!empty($filtres['date_fin'])) {
        $sql .= " AND DATE(c.date_inscription) <= :date_fin";
        $params['date_fin'] = $filtres['date_fin'];
    }
    
    // Tri
    $sql .= " ORDER BY c.id_client DESC LIMIT :limit OFFSET :offset";
    
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
 * Compte le nombre total de clients (pour pagination)
 */
function countClients($pdo, $filtres = []) {
    $sql = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
    
    $params = [];
    
    if (!empty($filtres['search'])) {
        $sql .= " AND (email LIKE :search OR nom LIKE :search OR prenom LIKE :search)";
        $params['search'] = '%' . $filtres['search'] . '%';
    }
    
    if (!empty($filtres['statut'])) {
        $sql .= " AND statut = :statut";
        $params['statut'] = $filtres['statut'];
    }
    
    if (isset($filtres['temporaire']) && $filtres['temporaire'] !== '') {
        $sql .= " AND is_temporary = :temporaire";
        $params['temporaire'] = $filtres['temporaire'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

/**
 * Récupère un client avec tous ses détails
 */
function getClientById($pdo, $id) {
    // Infos principales du client
    $sql = "SELECT 
                c.*,
                (SELECT COUNT(*) FROM commandes WHERE id_client = c.id_client) as nb_commandes,
                (SELECT COUNT(*) FROM commandes WHERE id_client = c.id_client AND statut = 'en_attente') as commandes_attente,
                (SELECT SUM(total_ttc) FROM commandes WHERE id_client = c.id_client AND statut_paiement = 'paye') as ca_total,
                (SELECT MAX(date_commande) FROM commandes WHERE id_client = c.id_client) as derniere_commande
            FROM clients c
            WHERE c.id_client = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        return null;
    }
    
    // Récupérer les adresses du client
    $sql_adresses = "SELECT * FROM adresses 
                     WHERE id_client = :id_client 
                     ORDER BY principale DESC, date_creation DESC";
    $stmt_adresses = $pdo->prepare($sql_adresses);
    $stmt_adresses->execute(['id_client' => $id]);
    $client['adresses'] = $stmt_adresses->fetchAll();
    
    // Récupérer les dernières commandes du client
    $sql_commandes = "SELECT 
                        c.id_commande,
                        c.numero_commande,
                        c.date_commande,
                        c.total_ttc,
                        c.statut,
                        c.statut_paiement,
                        COUNT(ci.id_item) as nb_articles
                      FROM commandes c
                      LEFT JOIN commande_items ci ON c.id_commande = ci.id_commande
                      WHERE c.id_client = :id_client
                      GROUP BY c.id_commande
                      ORDER BY c.date_commande DESC
                      LIMIT 10";
    $stmt_commandes = $pdo->prepare($sql_commandes);
    $stmt_commandes->execute(['id_client' => $id]);
    $client['commandes'] = $stmt_commandes->fetchAll();
    
    return $client;
}

/**
 * Met à jour un client
 */
function updateClient($pdo, $id, $data) {
    // Construire la requête dynamiquement
    $champs = [];
    $params = ['id' => $id];
    
    $champs_modifiables = ['nom', 'prenom', 'email', 'telephone', 'statut', 'newsletter'];
    
    foreach ($champs_modifiables as $champ) {
        if (array_key_exists($champ, $data)) {
            $champs[] = "$champ = :$champ";
            $params[$champ] = $data[$champ];
        }
    }
    
    // Si modification du mot de passe
    if (!empty($data['mot_de_passe'])) {
        $champs[] = "mot_de_passe = :mot_de_passe";
        $params['mot_de_passe'] = password_hash($data['mot_de_passe'], PASSWORD_DEFAULT);
    }
    
    if (empty($champs)) {
        return false;
    }
    
    $sql = "UPDATE clients SET " . implode(', ', $champs) . " WHERE id_client = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Convertit un client temporaire en client permanent
 */
function convertToPermanent($pdo, $id, $password) {
    $sql = "UPDATE clients SET 
            is_temporary = 0,
            mot_de_passe = :mot_de_passe,
            date_modification = NOW()
            WHERE id_client = :id AND is_temporary = 1";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        'id' => $id,
        'mot_de_passe' => password_hash($password, PASSWORD_DEFAULT)
    ]);
}

/**
 * Supprime un client (superadmin uniquement)
 */
function deleteClient($pdo, $id) {
    try {
        $pdo->beginTransaction();
        
        // Supprimer les adresses associées
        $stmt = $pdo->prepare("DELETE FROM adresses WHERE id_client = ?");
        $stmt->execute([$id]);
        
        // Supprimer les entrées de wishlist
        $stmt = $pdo->prepare("DELETE FROM wishlist WHERE id_client = ?");
        $stmt->execute([$id]);
        
        // Supprimer les conversions temporaires
        $stmt = $pdo->prepare("DELETE FROM conversions_temp WHERE id_client_temp = ?");
        $stmt->execute([$id]);
        
        // Supprimer les paniers associés
        $stmt = $pdo->prepare("UPDATE panier SET id_client = NULL WHERE id_client = ?");
        $stmt->execute([$id]);
        
        // Supprimer le client
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id_client = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur suppression client: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère les statistiques des clients
 */
function getClientsStats($pdo) {
    $stats = [];
    
    // Total clients
    $sql = "SELECT COUNT(*) as total FROM clients";
    $stmt = $pdo->query($sql);
    $stats['total'] = $stmt->fetch()['total'];
    
    // Clients temporaires vs permanents
    $sql = "SELECT 
            SUM(CASE WHEN is_temporary = 1 THEN 1 ELSE 0 END) as temporaires,
            SUM(CASE WHEN is_temporary = 0 THEN 1 ELSE 0 END) as permanents
            FROM clients";
    $stmt = $pdo->query($sql);
    $stats['types'] = $stmt->fetch();
    
    // Par statut
    $sql = "SELECT statut, COUNT(*) as total FROM clients GROUP BY statut";
    $stmt = $pdo->query($sql);
    $stats['par_statut'] = $stmt->fetchAll();
    
    // Inscriptions aujourd'hui
    $sql = "SELECT COUNT(*) as total FROM clients WHERE DATE(date_inscription) = CURDATE()";
    $stmt = $pdo->query($sql);
    $stats['aujourd_hui'] = $stmt->fetch()['total'];
    
    // Inscriptions ce mois
    $sql = "SELECT COUNT(*) as total FROM clients 
            WHERE MONTH(date_inscription) = MONTH(CURDATE()) 
            AND YEAR(date_inscription) = YEAR(CURDATE())";
    $stmt = $pdo->query($sql);
    $stats['ce_mois'] = $stmt->fetch()['total'];
    
    // Newsletter
    $sql = "SELECT 
            SUM(CASE WHEN newsletter = 1 THEN 1 ELSE 0 END) as inscrits,
            SUM(CASE WHEN newsletter = 0 THEN 1 ELSE 0 END) as non_inscrits
            FROM clients";
    $stmt = $pdo->query($sql);
    $stats['newsletter'] = $stmt->fetch();
    
    return $stats;
}

/**
 * Récupère les adresses d'un client
 */
function getClientAddresses($pdo, $client_id) {
    $sql = "SELECT * FROM adresses 
            WHERE id_client = :id_client 
            ORDER BY principale DESC, date_creation DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_client' => $client_id]);
    return $stmt->fetchAll();
}

// ============================================
// TRAITEMENT DES ACTIONS
// ============================================

// Récupérer les filtres depuis l'URL
$filtres = [
    'search' => $_GET['search'] ?? '',
    'statut' => $_GET['statut'] ?? '',
    'temporaire' => isset($_GET['temporaire']) ? (int)$_GET['temporaire'] : '',
    'newsletter' => isset($_GET['newsletter']) ? (int)$_GET['newsletter'] : '',
    'date_debut' => $_GET['date_debut'] ?? '',
    'date_fin' => $_GET['date_fin'] ?? ''
];

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Traitement des formulaires POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // MODIFICATION CLIENT
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        $id = intval($_POST['id_client']);
        
        $data = [
            'nom' => $_POST['nom'],
            'prenom' => $_POST['prenom'],
            'email' => $_POST['email'],
            'telephone' => $_POST['telephone'] ?? '',
            'statut' => $_POST['statut'],
            'newsletter' => isset($_POST['newsletter']) ? 1 : 0
        ];
        
        // Validation email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Format d'email invalide.";
            header('Location: admin_clients.php?action=edit&id=' . $id);
            exit;
        }
        
        // Vérifier si l'email existe déjà pour un autre client
        $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ? AND id_client != ?");
        $stmt->execute([$data['email'], $id]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Cet email est déjà utilisé par un autre client.";
            header('Location: admin_clients.php?action=edit&id=' . $id);
            exit;
        }
        
        // Ajouter le mot de passe si fourni
        if (!empty($_POST['mot_de_passe'])) {
            $data['mot_de_passe'] = $_POST['mot_de_passe'];
        }
        
        if (updateClient($pdo, $id, $data)) {
            logAction('info', 'Client modifié', [
                'client_id' => $id,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Client #$id modifié avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la modification du client.";
        }
        
        header('Location: admin_clients.php?action=view&id=' . $id);
        exit;
    }
    
    // CONVERSION EN PERMANENT
    if (isset($_POST['action']) && $_POST['action'] === 'convert') {
        $id = intval($_POST['id_client']);
        $password = $_POST['mot_de_passe'];
        
        if (strlen($password) < 6) {
            $_SESSION['error'] = "Le mot de passe doit contenir au moins 6 caractères.";
            header('Location: admin_clients.php?action=view&id=' . $id);
            exit;
        }
        
        if (convertToPermanent($pdo, $id, $password)) {
            logAction('info', 'Client temporaire converti en permanent', [
                'client_id' => $id,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Client #$id converti en compte permanent avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la conversion du client.";
        }
        
        header('Location: admin_clients.php?action=view&id=' . $id);
        exit;
    }
    
    // SUPPRESSION CLIENT (superadmin uniquement)
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Vérifier que c'est un superadmin
        if ($admin_role !== 'superadmin') {
            $_SESSION['error'] = "Seuls les super administrateurs peuvent supprimer des clients.";
            header('Location: admin_clients.php?action=list');
            exit;
        }
        
        $id = intval($_POST['id_client']);
        
        if (deleteClient($pdo, $id)) {
            logAction('securite', 'Client supprimé', [
                'client_id' => $id,
                'admin_id' => $_SESSION['admin_id']
            ]);
            $_SESSION['message'] = "Client #$id supprimé avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de la suppression du client.";
        }
        
        header('Location: admin_clients.php?action=list');
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
$stats = getClientsStats($pdo);
$total_clients = countClients($pdo, $filtres);
$total_pages = ceil($total_clients / $limit);

// Récupérer les clients selon l'action
$clients = [];
if ($action === 'list') {
    $clients = getAllClients($pdo, $page, $limit, $filtres);
} elseif ($action === 'view' && $id > 0) {
    $client = getClientById($pdo, $id);
    if (!$client) {
        $error = "Client non trouvé.";
        $action = 'list';
    }
} elseif ($action === 'edit' && $id > 0) {
    $client = getClientById($pdo, $id);
    if (!$client) {
        $error = "Client non trouvé.";
        $action = 'list';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Gestion des Clients - Heure du Cadeau</title>
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
        .stat-card.temp { border-left-color: var(--warning-color); }
        .stat-card.permanent { border-left-color: var(--success-color); }
        .stat-card.newsletter { border-left-color: #9C27B0; }
        .stat-card.today { border-left-color: #00BCD4; }
        .stat-card.month { border-left-color: #3F51B5; }
        
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
            min-width: 1000px;
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
        
        .badge.actif { background: #d4edda; color: #155724; }
        .badge.inactif { background: #e2e3e5; color: #383d41; }
        .badge.banni { background: #f8d7da; color: #721c24; }
        .badge.temp { background: #fff3cd; color: #856404; }
        .badge.permanent { background: #d4edda; color: #155724; }
        .badge.newsletter-yes { background: #d4edda; color: #155724; }
        .badge.newsletter-no { background: #e2e3e5; color: #383d41; }
        .badge.en_attente { background: #fff3cd; color: #856404; }
        .badge.paye { background: #d4edda; color: #155724; }
        .badge.echec { background: #f8d7da; color: #721c24; }
        .badge.rembourse { background: #e2e3e5; color: #383d41; }
        .badge.confirmee { background: #d4edda; color: #155724; }
        .badge.preparation { background: #cce5ff; color: #004085; }
        .badge.expediee { background: #d1ecf1; color: #0c5460; }
        .badge.livree { background: #d4edda; color: #155724; }
        .badge.annulee { background: #f8d7da; color: #721c24; }
        
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
        
        /* Detail client responsive */
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
        
        .client-nom {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            word-break: break-word;
        }
        
        @media (min-width: 768px) {
            .client-nom {
                font-size: 24px;
            }
        }
        
        .client-email {
            color: #7f8c8d;
            font-size: 13px;
            word-break: break-word;
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        @media (min-width: 768px) {
            .client-email {
                flex-direction: row;
                align-items: center;
                font-size: 14px;
            }
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
        
        /* Cartes adresses responsives */
        .addresses-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-top: 15px;
        }
        
        @media (min-width: 768px) {
            .addresses-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
            }
        }
        
        .address-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #6c757d;
        }
        
        .address-card.principale {
            border-left-color: var(--success-color);
        }
        
        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .address-type {
            font-weight: 600;
        }
        
        .address-principale {
            background: var(--success-color);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .address-content {
            font-size: 14px;
            line-height: 1.6;
            word-break: break-word;
        }
        
        /* Commandes table responsive */
        .commandes-table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 15px;
        }
        
        .commandes-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        
        .commandes-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .commandes-table td {
            padding: 10px;
            border-bottom: 1px solid #e9ecef;
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
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (min-width: 768px) {
            .form-row-2 {
                grid-template-columns: repeat(2, 1fr);
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
            .hide-mobile { display: inline; }
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
        
        /* Statut commande badges */
        .statut-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1><i class="fas fa-users"></i> Gestion des Clients</h1>
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
            <a href="admin_clients.php?action=list" class="<?php echo $action == 'list' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> <span>Liste</span>
            </a>
            <a href="admin_clients.php?action=stats" class="<?php echo $action == 'stats' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span>Stats</span>
            </a>
            <?php if ($admin_role === 'superadmin'): ?>
            <a href="admin_temp_clients.php" class="<?php echo $action == 'temp' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span>Temporaires</span>
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

        <!-- STATS DASHBOARD -->
        <?php if ($action === 'stats'): ?>
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">Total clients</div>
                </div>
                <div class="stat-card temp">
                    <div class="stat-value"><?php echo number_format($stats['types']['temporaires'] ?? 0); ?></div>
                    <div class="stat-label">Temporaires</div>
                </div>
                <div class="stat-card permanent">
                    <div class="stat-value"><?php echo number_format($stats['types']['permanents'] ?? 0); ?></div>
                    <div class="stat-label">Permanents</div>
                </div>
                <div class="stat-card newsletter">
                    <div class="stat-value"><?php echo number_format($stats['newsletter']['inscrits'] ?? 0); ?></div>
                    <div class="stat-label">Newsletter</div>
                </div>
                <div class="stat-card today">
                    <div class="stat-value"><?php echo number_format($stats['aujourd_hui']); ?></div>
                    <div class="stat-label">Aujourd'hui</div>
                </div>
                <div class="stat-card month">
                    <div class="stat-value"><?php echo number_format($stats['ce_mois']); ?></div>
                    <div class="stat-label">Ce mois</div>
                </div>
            </div>
            
            <!-- Répartition par statut -->
            <div class="form-container" style="margin-top: 20px;">
                <h3 style="margin-bottom: 20px;">Répartition par statut</h3>
                <?php foreach ($stats['par_statut'] as $stat): 
                    $percentage = ($stat['total'] / max($stats['total'], 1)) * 100;
                    $color = $stat['statut'] == 'actif' ? '#4CAF50' : ($stat['statut'] == 'inactif' ? '#6c757d' : '#f44336');
                ?>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; flex-wrap: wrap; gap: 5px;">
                        <span style="font-weight: 600; color: <?php echo $color; ?>">
                            <?php echo ucfirst($stat['statut']); ?>
                        </span>
                        <span><?php echo $stat['total']; ?> (<?php echo number_format($percentage, 1); ?>%)</span>
                    </div>
                    <div style="height: 8px; background-color: #eee; border-radius: 4px; overflow: hidden;">
                        <div style="height: 100%; width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
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
                        <input type="text" name="search" placeholder="Email, nom, prénom, téléphone..." 
                               value="<?php echo htmlspecialchars($filtres['search']); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-tag"></i> Statut</label>
                        <select name="statut">
                            <option value="">Tous</option>
                            <option value="actif" <?php echo $filtres['statut'] == 'actif' ? 'selected' : ''; ?>>Actif</option>
                            <option value="inactif" <?php echo $filtres['statut'] == 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                            <option value="banni" <?php echo $filtres['statut'] == 'banni' ? 'selected' : ''; ?>>Banni</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-clock"></i> Type</label>
                        <select name="temporaire">
                            <option value="">Tous</option>
                            <option value="0" <?php echo $filtres['temporaire'] === '0' ? 'selected' : ''; ?>>Permanent</option>
                            <option value="1" <?php echo $filtres['temporaire'] === '1' ? 'selected' : ''; ?>>Temporaire</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-envelope"></i> Newsletter</label>
                        <select name="newsletter">
                            <option value="">Tous</option>
                            <option value="1" <?php echo $filtres['newsletter'] === '1' ? 'selected' : ''; ?>>Inscrit</option>
                            <option value="0" <?php echo $filtres['newsletter'] === '0' ? 'selected' : ''; ?>>Non inscrit</option>
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
                        <a href="admin_clients.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- LISTE DES CLIENTS -->
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-users"></i> Liste des clients (<?php echo $total_clients; ?>)</h3>
                </div>
                
                <?php if (empty($clients)): ?>
                    <div class="text-center" style="padding: 40px 20px;">
                        <i class="fas fa-users" style="font-size: 60px; color: #ccc; margin-bottom: 20px;"></i>
                        <h3 style="color: #777; margin-bottom: 10px;">Aucun client trouvé</h3>
                        <p style="color: #999;">Modifiez vos filtres ou revenez plus tard.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th class="hide-mobile">Contact</th>
                                    <th>Type</th>
                                    <th class="hide-mobile">Commandes</th>
                                    <th class="hide-mobile">CA</th>
                                    <th>Newsletter</th>
                                    <th class="hide-mobile">Inscription</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td>#<?php echo $c['id_client']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($c['prenom'] . ' ' . $c['nom']); ?></strong>
                                        <div class="show-mobile" style="font-size: 12px; color: #666; margin-top: 3px;">
                                            <?php echo htmlspecialchars($c['email']); ?>
                                        </div>
                                    </td>
                                    <td class="hide-mobile">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($c['email']); ?><br>
                                        <?php if (!empty($c['telephone'])): ?>
                                            <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($c['telephone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['is_temporary']): ?>
                                            <span class="badge temp">Temp</span>
                                        <?php else: ?>
                                            <span class="badge permanent">Perm</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <strong><?php echo $c['nb_commandes']; ?></strong><br>
                                        <?php if ($c['derniere_commande']): ?>
                                            <small><?php echo date('d/m', strtotime($c['derniere_commande'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php if ($c['ca_total'] > 0): ?>
                                            <strong><?php echo number_format($c['ca_total'], 0); ?> €</strong>
                                        <?php else: ?>
                                            <span style="color: #999;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['newsletter']): ?>
                                            <span class="badge newsletter-yes">Oui</span>
                                        <?php else: ?>
                                            <span class="badge newsletter-no">Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile"><?php echo date('d/m/Y', strtotime($c['date_inscription'])); ?></td>
                                    <td>
                                        <?php
                                        $statut_class = [
                                            'actif' => 'actif',
                                            'inactif' => 'inactif',
                                            'banni' => 'banni'
                                        ];
                                        $class = $statut_class[$c['statut']] ?? 'actif';
                                        ?>
                                        <span class="badge <?php echo $class; ?>">
                                            <?php echo $c['statut'] == 'actif' ? 'A' : ($c['statut'] == 'inactif' ? 'I' : 'B'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="admin_clients.php?action=view&id=<?php echo $c['id_client']; ?>" 
                                               class="btn btn-info btn-sm" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="admin_clients.php?action=edit&id=<?php echo $c['id_client']; ?>" 
                                               class="btn btn-warning btn-sm" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($c['is_temporary']): ?>
                                            <button onclick="openConvertModal(<?php echo $c['id_client']; ?>, '<?php echo htmlspecialchars($c['email']); ?>')" 
                                                    class="btn btn-success btn-sm" title="Convertir">
                                                <i class="fas fa-user-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <?php if ($admin_role === 'superadmin'): ?>
                                            <button onclick="confirmDelete(<?php echo $c['id_client']; ?>, '<?php echo htmlspecialchars($c['email']); ?>')" 
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

        <?php elseif ($action == 'view' && isset($client)): ?>
            <!-- DÉTAIL D'UN CLIENT -->
            <div class="detail-container">
                <div class="detail-header">
                    <div>
                        <span class="client-nom">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?>
                        </span>
                        <div class="client-email">
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['email']); ?>
                            <?php if ($client['is_temporary']): ?>
                                <span class="badge temp">Compte temporaire</span>
                            <?php else: ?>
                                <span class="badge permanent">Compte permanent</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <a href="admin_clients.php?action=list" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <span class="hide-mobile">Retour</span>
                        </a>
                        <a href="admin_clients.php?action=edit&id=<?php echo $client['id_client']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <?php if ($client['is_temporary']): ?>
                        <button onclick="openConvertModal(<?php echo $client['id_client']; ?>, '<?php echo htmlspecialchars($client['email']); ?>')" 
                                class="btn btn-success">
                            <i class="fas fa-user-check"></i> Convertir
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INFOS CLIENT -->
                <div class="info-grid">
                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> Informations générales</h3>
                        <div class="info-row">
                            <span class="info-label">ID Client</span>
                            <span class="info-value">#<?php echo $client['id_client']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nom complet</span>
                            <span class="info-value"><?php echo htmlspecialchars($client['prenom'] . ' ' . $client['nom']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email</span>
                            <span class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                                    <?php echo htmlspecialchars($client['email']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Téléphone</span>
                            <span class="info-value">
                                <?php if (!empty($client['telephone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($client['telephone']); ?>">
                                        <?php echo htmlspecialchars($client['telephone']); ?>
                                    </a>
                                <?php else: ?>
                                    Non renseigné
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Statut</span>
                            <span class="info-value">
                                <?php
                                $statut_class = [
                                    'actif' => 'badge actif',
                                    'inactif' => 'badge inactif',
                                    'banni' => 'badge banni'
                                ];
                                $class = $statut_class[$client['statut']] ?? 'badge actif';
                                ?>
                                <span class="<?php echo $class; ?>">
                                    <?php echo ucfirst($client['statut']); ?>
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="info-card">
                        <h3><i class="fas fa-chart-line"></i> Statistiques</h3>
                        <div class="info-row">
                            <span class="info-label">Date inscription</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($client['date_inscription'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Newsletter</span>
                            <span class="info-value">
                                <?php if ($client['newsletter']): ?>
                                    <span class="badge newsletter-yes">Inscrit</span>
                                <?php else: ?>
                                    <span class="badge newsletter-no">Non inscrit</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total commandes</span>
                            <span class="info-value"><strong><?php echo $client['nb_commandes']; ?></strong> commande(s)</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Commandes en attente</span>
                            <span class="info-value"><?php echo $client['commandes_attente'] ?? 0; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Chiffre d'affaires</span>
                            <span class="info-value"><strong><?php echo number_format($client['ca_total'] ?? 0, 2); ?> €</strong></span>
                        </div>
                        <?php if ($client['derniere_commande']): ?>
                        <div class="info-row">
                            <span class="info-label">Dernière commande</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($client['derniere_commande'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ADRESSES -->
                <?php if (!empty($client['adresses'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-map-marker-alt"></i> Adresses enregistrées</h3>
                    
                    <div class="addresses-grid">
                        <?php foreach ($client['adresses'] as $adresse): ?>
                        <div class="address-card <?php echo $adresse['principale'] ? 'principale' : ''; ?>">
                            <div class="address-header">
                                <strong class="address-type"><?php echo $adresse['type_adresse'] == 'livraison' ? 'Livraison' : 'Facturation'; ?></strong>
                                <?php if ($adresse['principale']): ?>
                                    <span class="address-principale">Principale</span>
                                <?php endif; ?>
                            </div>
                            <div class="address-content">
                                <?php echo htmlspecialchars($adresse['prenom'] . ' ' . $adresse['nom']); ?><br>
                                <?php echo htmlspecialchars($adresse['adresse']); ?><br>
                                <?php if (!empty($adresse['complement'])): ?>
                                    <?php echo htmlspecialchars($adresse['complement']); ?><br>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($adresse['code_postal'] . ' ' . $adresse['ville']); ?><br>
                                <?php echo htmlspecialchars($adresse['pays']); ?><br>
                                <?php if (!empty($adresse['telephone'])): ?>
                                    Tél: <?php echo htmlspecialchars($adresse['telephone']); ?>
                                <?php endif; ?>
                                <div style="margin-top: 8px; font-size: 12px; color: #666;">
                                    Créée le <?php echo date('d/m/Y', strtotime($adresse['date_creation'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- COMMANDES -->
                <?php if (!empty($client['commandes'])): ?>
                <div style="margin-top: 20px;">
                    <h3><i class="fas fa-shopping-cart"></i> Dernières commandes</h3>
                    
                    <div class="commandes-table-container">
                        <table class="commandes-table">
                            <thead>
                                <tr>
                                    <th>N° Commande</th>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Articles</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($client['commandes'] as $cmd): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($cmd['numero_commande']); ?></strong></td>
                                    <td><?php echo date('d/m', strtotime($cmd['date_commande'])); ?></td>
                                    <td><?php echo number_format($cmd['total_ttc'], 2); ?> €</td>
                                    <td><?php echo $cmd['nb_articles']; ?></td>
                                    <td>
                                        <?php
                                        $statut_class = [
                                            'en_attente' => 'badge en_attente',
                                            'confirmee' => 'badge confirmee',
                                            'en_preparation' => 'badge preparation',
                                            'expediee' => 'badge expediee',
                                            'livree' => 'badge livree',
                                            'annulee' => 'badge annulee'
                                        ];
                                        $class = $statut_class[$cmd['statut']] ?? 'badge en_attente';
                                        $statut_text = [
                                            'en_attente' => 'Attente',
                                            'confirmee' => 'Confirmée',
                                            'en_preparation' => 'Prépa',
                                            'expediee' => 'Expédiée',
                                            'livree' => 'Livrée',
                                            'annulee' => 'Annulée'
                                        ];
                                        ?>
                                        <span class="<?php echo $class; ?>">
                                            <?php echo $statut_text[$cmd['statut']] ?? $cmd['statut']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $paiement_class = [
                                            'en_attente' => 'badge en_attente',
                                            'paye' => 'badge paye',
                                            'echec' => 'badge echec',
                                            'rembourse' => 'badge rembourse'
                                        ];
                                        $p_class = $paiement_class[$cmd['statut_paiement']] ?? 'badge en_attente';
                                        $p_text = [
                                            'en_attente' => 'Attente',
                                            'paye' => 'Payé',
                                            'echec' => 'Échec',
                                            'rembourse' => 'Remb.'
                                        ];
                                        ?>
                                        <span class="<?php echo $p_class; ?>">
                                            <?php echo $p_text[$cmd['statut_paiement']] ?? $cmd['statut_paiement']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="admin_commandes.php?action=view&id=<?php echo $cmd['id_commande']; ?>" 
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
                
                <!-- BOUTONS D'ACTION SUPPLEMENTAIRES -->
                <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                    <?php if ($admin_role === 'superadmin'): ?>
                    <form method="POST" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer ce client ? Cette action est irréversible et supprimera toutes ses données (commandes, adresses, etc.) !');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_client" value="<?php echo $client['id_client']; ?>">
                        
                        <button type="submit" class="btn btn-danger" style="width: 100%;">
                            <i class="fas fa-trash"></i> Supprimer définitivement
                        </button>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Action irréversible - réservée aux super administrateurs
                        </small>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($action == 'edit' && isset($client)): ?>
            <!-- FORMULAIRE DE MODIFICATION CLIENT -->
            <div class="form-container">
                <h2 style="margin-bottom: 25px; color: #333; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <i class="fas fa-edit"></i> Modifier le client #<?php echo $client['id_client']; ?>
                </h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id_client" value="<?php echo $client['id_client']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                            <input type="text" id="prenom" name="prenom" class="form-control" 
                                   value="<?php echo htmlspecialchars($client['prenom']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                            <input type="text" id="nom" name="nom" class="form-control" 
                                   value="<?php echo htmlspecialchars($client['nom']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($client['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="telephone"><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="text" id="telephone" name="telephone" class="form-control" 
                               value="<?php echo htmlspecialchars($client['telephone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="mot_de_passe"><i class="fas fa-lock"></i> Nouveau mot de passe</label>
                        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control" 
                               placeholder="Laissez vide pour ne pas changer">
                        <small style="color: #666;">Minimum 6 caractères</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="statut"><i class="fas fa-tag"></i> Statut</label>
                            <select id="statut" name="statut" class="form-control">
                                <option value="actif" <?php echo $client['statut'] == 'actif' ? 'selected' : ''; ?>>Actif</option>
                                <option value="inactif" <?php echo $client['statut'] == 'inactif' ? 'selected' : ''; ?>>Inactif</option>
                                <option value="banni" <?php echo $client['statut'] == 'banni' ? 'selected' : ''; ?>>Banni</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-envelope-open-text"></i> Newsletter</label>
                            <div class="checkbox-group">
                                <input type="checkbox" id="newsletter" name="newsletter" value="1" 
                                       <?php echo $client['newsletter'] ? 'checked' : ''; ?>>
                                <label for="newsletter">Inscrit à la newsletter</label>
                            </div>
                        </div>
                    </div>
                    
                    <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Enregistrer les modifications
                        </button>
                        <a href="admin_clients.php?action=view&id=<?php echo $client['id_client']; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- MODAL DE CONVERSION (pour les clients temporaires) -->
    <div id="convertModal" class="modal">
        <div class="modal-content">
            <h3 style="margin-bottom: 20px;"><i class="fas fa-user-check"></i> Convertir en compte permanent</h3>
            <p>Client : <strong id="modalClientEmail"></strong></p>
            
            <form method="POST" id="convertForm">
                <input type="hidden" name="action" value="convert">
                <input type="hidden" name="id_client" id="modalClientId">
                
                <div class="form-group">
                    <label for="convert_password">Mot de passe *</label>
                    <input type="password" name="mot_de_passe" id="convert_password" class="form-control" 
                           required minlength="6">
                    <small>Minimum 6 caractères</small>
                </div>
                
                <div class="modal-actions">
                    <button type="button" onclick="closeConvertModal()" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-success">Convertir</button>
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
                <p>Êtes-vous sûr de vouloir supprimer le client "<span id="deleteClientEmail"></span>" ?</p>
                <p style="color: #f44336; font-weight: 600; margin-top: 10px;">
                    <i class="fas fa-exclamation-circle"></i> Cette action supprimera également toutes ses commandes, adresses et données associées !
                </p>
            </div>
            
            <div class="modal-actions">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_client" id="deleteClientId">
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-danger">Supprimer définitivement</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Fonctions pour la modal de conversion
        function openConvertModal(id, email) {
            document.getElementById('modalClientId').value = id;
            document.getElementById('modalClientEmail').textContent = email;
            document.getElementById('convertModal').style.display = 'flex';
        }
        
        function closeConvertModal() {
            document.getElementById('convertModal').style.display = 'none';
        }
        
        // Fonctions pour la modal de suppression
        function confirmDelete(id, email) {
            document.getElementById('deleteClientId').value = id;
            document.getElementById('deleteClientEmail').textContent = email;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            const convertModal = document.getElementById('convertModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === convertModal) {
                closeConvertModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Ajuster le style pour les appareils mobiles
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter la classe hide-mobile/show-mobile si elle n'existe pas
            const style = document.createElement('style');
            style.textContent = `
                @media (max-width: 767px) {
                    .hide-mobile { display: none !important; }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</body>
</html>