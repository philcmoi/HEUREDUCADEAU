<?php
// api/panier.php - VERSION SYNCHRONISÉE AVEC BDD
session_start();

// Configuration BDD
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe');
define('DB_PASS', 'l@99339R');

// Headers CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Connexion BDD
function getPDOConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur BDD: ' . $e->getMessage()]);
            exit();
        }
    }
    return $pdo;
}

$pdo = getPDOConnection();
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer action
$action = '';
if (!empty($data['action'])) $action = $data['action'];
elseif (!empty($_GET['action'])) $action = $_GET['action'];
elseif (!empty($_POST['action'])) $action = $_POST['action'];

// ==============================================
// FONCTIONS UTILITAIRES BDD
// ==============================================

function getOrCreatePanier($pdo, $client_id = null) {
    $session_id = session_id();
    $id_panier = null;
    
    // Chercher par client_id d'abord
    if ($client_id) {
        $sql = "SELECT id_panier FROM panier 
                WHERE (id_client = ? OR session_id = ?) 
                AND statut = 'actif' 
                ORDER BY date_creation DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id, $session_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $id_panier = $result['id_panier'];
            // Mettre à jour la session si nécessaire
            if (empty($result['session_id'])) {
                $update = $pdo->prepare("UPDATE panier SET session_id = ? WHERE id_panier = ?");
                $update->execute([$session_id, $id_panier]);
            }
        }
    }
    
    // Chercher par session_id
    if (!$id_panier) {
        $sql = "SELECT id_panier FROM panier 
                WHERE session_id = ? AND statut = 'actif' 
                ORDER BY date_creation DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$session_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $id_panier = $result['id_panier'];
        }
    }
    
    // Créer nouveau panier
    if (!$id_panier) {
        $sql = "INSERT INTO panier (id_client, session_id, statut, date_creation) 
                VALUES (?, ?, 'actif', NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id, $session_id]);
        $id_panier = $pdo->lastInsertId();
    }
    
    // Stocker dans session
    $_SESSION['panier_id'] = $id_panier;
    
    return $id_panier;
}

function getProduitInfo($pdo, $id_produit) {
    $sql = "SELECT p.id_produit, p.nom, p.prix_ttc, p.quantite_stock, p.description_courte,
                   img.url_image
            FROM produits p
            LEFT JOIN images_produits img ON p.id_produit = img.id_produit AND img.principale = 1
            WHERE p.id_produit = ? AND p.statut = 'actif'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_produit]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getPanierItems($pdo, $id_panier) {
    $sql = "SELECT pi.*, p.nom, p.prix_ttc, img.url_image
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            LEFT JOIN images_produits img ON p.id_produit = img.id_produit AND img.principale = 1
            WHERE pi.id_panier = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_panier]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculerTotauxPanier($pdo, $id_panier) {
    $items = getPanierItems($pdo, $id_panier);
    $totalArticles = 0;
    $totalPrix = 0.00;
    
    foreach ($items as $item) {
        $totalArticles += $item['quantite'];
        $totalPrix += $item['prix_unitaire'] * $item['quantite'];
    }
    
    return ['articles' => $totalArticles, 'prix' => $totalPrix];
}

// ==============================================
// TRAITEMENT DES ACTIONS
// ==============================================

// ACTION: AJOUTER
if ($action === 'ajouter') {
    $id_produit = isset($data['id_produit']) ? intval($data['id_produit']) : 0;
    $quantite = isset($data['quantite']) ? intval($data['quantite']) : 1;
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    
    if ($id_produit < 1) {
        echo json_encode(['success' => false, 'message' => 'ID produit invalide']);
        exit;
    }
    
    // 1. Récupérer infos produit
    $produitInfo = getProduitInfo($pdo, $id_produit);
    if (!$produitInfo) {
        echo json_encode(['success' => false, 'message' => 'Produit non trouvé']);
        exit;
    }
    
    // 2. Vérifier stock
    if ($produitInfo['quantite_stock'] < $quantite) {
        echo json_encode([
            'success' => false, 
            'message' => 'Stock insuffisant',
            'stock_disponible' => $produitInfo['quantite_stock']
        ]);
        exit;
    }
    
    // 3. Récupérer/créer panier
    $id_panier = getOrCreatePanier($pdo, $client_id);
    
    // 4. Vérifier si produit déjà dans panier
    $sqlCheck = "SELECT id_item, quantite FROM panier_items 
                 WHERE id_panier = ? AND id_produit = ?";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id_panier, $id_produit]);
    $existant = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existant) {
        // Mettre à jour quantité
        $nouvelleQuantite = $existant['quantite'] + $quantite;
        
        // Vérifier stock total
        if ($produitInfo['quantite_stock'] < $nouvelleQuantite) {
            echo json_encode([
                'success' => false, 
                'message' => 'Quantité totale dépasse le stock disponible',
                'stock_disponible' => $produitInfo['quantite_stock']
            ]);
            exit;
        }
        
        $sqlUpdate = "UPDATE panier_items 
                     SET quantite = ?, date_ajout = NOW() 
                     WHERE id_item = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$nouvelleQuantite, $existant['id_item']]);
    } else {
        // Ajouter nouveau produit
        $sqlInsert = "INSERT INTO panier_items 
                     (id_panier, id_produit, quantite, prix_unitaire, date_ajout) 
                     VALUES (?, ?, ?, ?, NOW())";
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtInsert->execute([
            $id_panier, 
            $id_produit, 
            $quantite, 
            $produitInfo['prix_ttc']
        ]);
    }
    
    // 5. Mettre à jour panier
    $sqlUpdatePanier = "UPDATE panier SET date_modification = NOW() WHERE id_panier = ?";
    $stmtUpdatePanier = $pdo->prepare($sqlUpdatePanier);
    $stmtUpdatePanier->execute([$id_panier]);
    
    // 6. Calculer totaux
    $totaux = calculerTotauxPanier($pdo, $id_panier);
    
    // 7. Synchroniser session
    $_SESSION['panier'] = [
        'items' => getPanierItems($pdo, $id_panier),
        'count' => $totaux['articles'],
        'total' => $totaux['prix']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'produit_nom' => $produitInfo['nom'],
        'quantite_ajoutee' => $quantite,
        'total_articles' => $totaux['articles'],
        'total_prix' => $totaux['prix'],
        'panier_id' => $id_panier
    ]);
    exit;
}

// ACTION: GET (récupérer panier)
if ($action === 'get' || $action === 'afficher') {
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $id_panier = null;
    
    // Chercher panier actif
    if (!empty($_SESSION['panier_id'])) {
        $id_panier = $_SESSION['panier_id'];
    } else {
        $id_panier = getOrCreatePanier($pdo, $client_id);
    }
    
    $items = getPanierItems($pdo, $id_panier);
    $totaux = calculerTotauxPanier($pdo, $id_panier);
    
    // Formater items pour réponse
    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItems[] = [
            'id_item' => $item['id_item'],
            'id_produit' => $item['id_produit'],
            'nom' => $item['nom'],
            'prix_unitaire' => floatval($item['prix_unitaire']),
            'quantite' => $item['quantite'],
            'total_item' => floatval($item['prix_unitaire'] * $item['quantite']),
            'image' => $item['url_image'] ?? 'img/default-product.jpg'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $formattedItems,
        'total_articles' => $totaux['articles'],
        'total_prix' => $totaux['prix'],
        'panier_id' => $id_panier
    ]);
    exit;
}

// ACTION: COMPTER
if ($action === 'compter') {
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    
    try {
        $sql = "SELECT SUM(pi.quantite) as total
                FROM panier p
                JOIN panier_items pi ON p.id_panier = pi.id_panier
                WHERE p.statut = 'actif' 
                AND (p.id_client = ? OR p.session_id = ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$client_id, session_id()]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $total = $result['total'] ?? 0;
        
        echo json_encode([
            'success' => true,
            'total' => intval($total)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'total' => 0,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// ACTION: MODIFIER QUANTITE
if ($action === 'modifier') {
    $id_item = isset($data['id_item']) ? intval($data['id_item']) : 0;
    $quantite = isset($data['quantite']) ? intval($data['quantite']) : 1;
    
    if ($id_item < 1) {
        echo json_encode(['success' => false, 'message' => 'ID item invalide']);
        exit;
    }
    
    // Récupérer l'item pour vérifier le produit
    $sqlItem = "SELECT pi.*, p.quantite_stock 
                FROM panier_items pi
                JOIN produits p ON pi.id_produit = p.id_produit
                WHERE pi.id_item = ?";
    $stmtItem = $pdo->prepare($sqlItem);
    $stmtItem->execute([$id_item]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item non trouvé']);
        exit;
    }
    
    // Vérifier stock si augmentation
    if ($quantite > $item['quantite'] && $quantite > $item['quantite_stock']) {
        echo json_encode([
            'success' => false, 
            'message' => 'Stock insuffisant',
            'stock_disponible' => $item['quantite_stock']
        ]);
        exit;
    }
    
    if ($quantite < 1) {
        // Supprimer l'item
        $sqlDelete = "DELETE FROM panier_items WHERE id_item = ?";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([$id_item]);
        $message = 'Article supprimé';
    } else {
        // Mettre à jour quantité
        $sqlUpdate = "UPDATE panier_items SET quantite = ? WHERE id_item = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$quantite, $id_item]);
        $message = 'Quantité mise à jour';
    }
    
    // Mettre à jour panier
    $sqlUpdatePanier = "UPDATE panier SET date_modification = NOW() 
                       WHERE id_panier = ?";
    $stmtUpdatePanier = $pdo->prepare($sqlUpdatePanier);
    $stmtUpdatePanier->execute([$item['id_panier']]);
    
    // Synchroniser session
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $id_panier = getOrCreatePanier($pdo, $client_id);
    $totaux = calculerTotauxPanier($pdo, $id_panier);
    
    $_SESSION['panier'] = [
        'items' => getPanierItems($pdo, $id_panier),
        'count' => $totaux['articles'],
        'total' => $totaux['prix']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'total_articles' => $totaux['articles'],
        'total_prix' => $totaux['prix']
    ]);
    exit;
}

// ACTION: SUPPRIMER
if ($action === 'supprimer') {
    $id_item = isset($data['id_item']) ? intval($data['id_item']) : 0;
    
    if ($id_item < 1) {
        echo json_encode(['success' => false, 'message' => 'ID item invalide']);
        exit;
    }
    
    // Récupérer id_panier avant suppression
    $sqlGet = "SELECT id_panier FROM panier_items WHERE id_item = ?";
    $stmtGet = $pdo->prepare($sqlGet);
    $stmtGet->execute([$id_item]);
    $result = $stmtGet->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Item non trouvé']);
        exit;
    }
    
    $id_panier = $result['id_panier'];
    
    // Supprimer l'item
    $sqlDelete = "DELETE FROM panier_items WHERE id_item = ?";
    $stmtDelete = $pdo->prepare($sqlDelete);
    $stmtDelete->execute([$id_item]);
    
    // Mettre à jour panier
    $sqlUpdate = "UPDATE panier SET date_modification = NOW() WHERE id_panier = ?";
    $stmtUpdate = $pdo->prepare($sqlUpdate);
    $stmtUpdate->execute([$id_panier]);
    
    // Synchroniser session
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $totaux = calculerTotauxPanier($pdo, $id_panier);
    
    $_SESSION['panier'] = [
        'items' => getPanierItems($pdo, $id_panier),
        'count' => $totaux['articles'],
        'total' => $totaux['prix']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Article supprimé',
        'total_articles' => $totaux['articles'],
        'total_prix' => $totaux['prix']
    ]);
    exit;
}

// ACTION: VIDER
if ($action === 'vider') {
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $session_id = session_id();
    
    // Trouver le panier
    $sql = "SELECT id_panier FROM panier 
            WHERE (id_client = ? OR session_id = ?) 
            AND statut = 'actif' 
            ORDER BY date_creation DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id, $session_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $id_panier = $result['id_panier'];
        
        // Supprimer tous les items
        $sqlDelete = "DELETE FROM panier_items WHERE id_panier = ?";
        $stmtDelete = $pdo->prepare($sqlDelete);
        $stmtDelete->execute([$id_panier]);
        
        // Mettre à jour panier
        $sqlUpdate = "UPDATE panier SET date_modification = NOW() WHERE id_panier = ?";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([$id_panier]);
    }
    
    // Vider session
    $_SESSION['panier'] = [
        'items' => [],
        'count' => 0,
        'total' => 0.00
    ];
    unset($_SESSION['panier_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier vidé',
        'total_articles' => 0,
        'total_prix' => 0.00
    ]);
    exit;
}

// ACTION: SYNC (synchroniser session avec BDD)
if ($action === 'sync') {
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $id_panier = getOrCreatePanier($pdo, $client_id);
    
    $items = getPanierItems($pdo, $id_panier);
    $totaux = calculerTotauxPanier($pdo, $id_panier);
    
    // Mettre à jour session
    $_SESSION['panier'] = [
        'items' => $items,
        'count' => $totaux['articles'],
        'total' => $totaux['prix']
    ];
    $_SESSION['panier_id'] = $id_panier;
    
    echo json_encode([
        'success' => true,
        'message' => 'Session synchronisée avec BDD',
        'total_articles' => $totaux['articles'],
        'total_prix' => $totaux['prix'],
        'panier_id' => $id_panier
    ]);
    exit;
}

// ACTION: ETAT (debug)
if ($action === 'etat') {
    $client_id = isset($_SESSION['client_id']) ? $_SESSION['client_id'] : null;
    $session_id = session_id();
    
    $sql = "SELECT p.*, COUNT(pi.id_item) as nb_items
            FROM panier p
            LEFT JOIN panier_items pi ON p.id_panier = pi.id_panier
            WHERE (p.id_client = ? OR p.session_id = ?) 
            AND p.statut = 'actif'
            GROUP BY p.id_panier";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$client_id, $session_id]);
    $panierBDD = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'session' => [
            'session_id' => session_id(),
            'client_id' => $client_id,
            'panier_session' => $_SESSION['panier'] ?? null,
            'panier_id_session' => $_SESSION['panier_id'] ?? null
        ],
        'bdd' => [
            'panier' => $panierBDD
        ]
    ]);
    exit;
}

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non reconnue',
    'received_action' => $action,
    'actions_disponibles' => [
        'ajouter' => 'Ajouter un produit',
        'get' => 'Récupérer le panier',
        'compter' => 'Compter les articles',
        'modifier' => 'Modifier quantité',
        'supprimer' => 'Supprimer article',
        'vider' => 'Vider le panier',
        'sync' => 'Synchroniser session-BDD'
    ]
]);
?>