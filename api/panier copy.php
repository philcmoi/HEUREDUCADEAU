<?php
session_start();

// Headers CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json; charset=UTF-8");

// Gérer OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration BD
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'root');
define('DB_PASS', '');

function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            return null;
        }
    }
    
    return $pdo;
}

// Fonction pour récupérer les infos produit depuis BD
function getProduitInfo($id_produit) {
    $pdo = getPDOConnection();
    if (!$pdo) return null;
    
    try {
        $sql = "SELECT 
                    p.id_produit,
                    p.reference,
                    p.nom,
                    p.prix_ttc,
                    p.quantite_stock,
                    c.nom as categorie_nom,
                    (
                        SELECT ip.url_image 
                        FROM images_produits ip 
                        WHERE ip.id_produit = p.id_produit 
                        AND ip.principale = 1 
                        LIMIT 1
                    ) as image
                FROM produits p
                LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                WHERE p.id_produit = :id AND p.statut = 'actif'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id_produit, PDO::PARAM_INT);
        $stmt->execute();
        
        $produit = $stmt->fetch();
        
        if ($produit) {
            if (empty($produit['image'])) {
                $produit['image'] = 'img/default-product.jpg';
            }
            return $produit;
        }
    } catch (PDOException $e) {
        error_log("Erreur getProduitInfo: " . $e->getMessage());
    }
    
    return null;
}

// Initialiser panier dans session
if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
    $_SESSION['panier'] = [
        'items' => [],        // id_produit => [données]
        'count' => 0,
        'total' => 0.00,
        'created' => time()
    ];
}

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer l'action
$action = '';
if (!empty($data) && isset($data['action'])) {
    $action = trim($data['action']);
} elseif (isset($_GET['action'])) {
    $action = trim($_GET['action']);
} elseif (isset($_POST['action'])) {
    $action = trim($_POST['action']);
}

// Fonction: Calculer les totaux
function calculerTotauxPanier() {
    $totalItems = 0;
    $totalPrice = 0.00;
    
    foreach ($_SESSION['panier']['items'] as $item) {
        $totalItems += $item['quantite'];
        $totalPrice += $item['prix_unitaire'] * $item['quantite'];
    }
    
    $_SESSION['panier']['count'] = $totalItems;
    $_SESSION['panier']['total'] = $totalPrice;
    
    return ['items' => $totalItems, 'price' => $totalPrice];
}

// ACTION: AJOUTER AU PANIER
if ($action === 'ajouter') {
    $id_produit = 0;
    $quantite = 1;
    
    if (!empty($data)) {
        $id_produit = isset($data['id_produit']) ? intval($data['id_produit']) : 0;
        $quantite = isset($data['quantite']) ? intval($data['quantite']) : 1;
    } elseif (isset($_GET['id_produit'])) {
        $id_produit = intval($_GET['id_produit']);
        $quantite = isset($_GET['quantite']) ? intval($_GET['quantite']) : 1;
    } elseif (isset($_POST['id_produit'])) {
        $id_produit = intval($_POST['id_produit']);
        $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
    }
    
    if ($id_produit < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'ID produit invalide',
            'received_data' => ['data' => $data, 'get' => $_GET, 'post' => $_POST]
        ]);
        exit;
    }
    
    // Vérifier si le produit existe dans la base
    $produitInfo = getProduitInfo($id_produit);
    if (!$produitInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non disponible',
            'produit_id' => $id_produit
        ]);
        exit;
    }
    
    // Vérifier le stock
    if ($produitInfo['quantite_stock'] <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit en rupture de stock',
            'produit_nom' => $produitInfo['nom']
        ]);
        exit;
    }
    
    // Ajouter au panier (session)
    $itemKey = 'prod_' . $id_produit;
    
    if (isset($_SESSION['panier']['items'][$itemKey])) {
        $nouvelleQuantite = $_SESSION['panier']['items'][$itemKey]['quantite'] + $quantite;
        
        // Vérifier si la quantité totale ne dépasse pas le stock
        if ($nouvelleQuantite > $produitInfo['quantite_stock']) {
            echo json_encode([
                'success' => false,
                'message' => 'Stock insuffisant. Quantité disponible: ' . $produitInfo['quantite_stock'],
                'stock_disponible' => $produitInfo['quantite_stock']
            ]);
            exit;
        }
        
        $_SESSION['panier']['items'][$itemKey]['quantite'] = $nouvelleQuantite;
        $message = 'Quantité mise à jour';
    } else {
        // Nouvel article
        $_SESSION['panier']['items'][$itemKey] = [
            'id_produit' => $id_produit,
            'reference' => $produitInfo['reference'],
            'nom' => $produitInfo['nom'],
            'prix_unitaire' => floatval($produitInfo['prix_ttc']),
            'quantite' => $quantite,
            'image' => $produitInfo['image'],
            'categorie' => $produitInfo['categorie_nom'],
            'date_ajout' => date('Y-m-d H:i:s'),
            'stock_disponible' => $produitInfo['quantite_stock']
        ];
        $message = 'Produit ajouté au panier';
    }
    
    // Calculer les totaux
    $totaux = calculerTotauxPanier();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'produit_nom' => $produitInfo['nom'],
        'produit_id' => $id_produit,
        'quantite_ajoutee' => $quantite,
        'quantite_totale' => $_SESSION['panier']['items'][$itemKey]['quantite'],
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', ''),
        'prix_unitaire' => number_format($produitInfo['prix_ttc'], 2, '.', ''),
        'panier_items_count' => count($_SESSION['panier']['items']),
        'session_id' => session_id()
    ]);
    exit;
}

// ACTION: COMPTER ARTICLES
if ($action === 'compter') {
    $count = $_SESSION['panier']['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'total' => $count,
        'has_items' => $count > 0,
        'session_id' => session_id()
    ]);
    exit;
}

// ACTION: GET - Récupérer le panier complet
if ($action === 'get' || $action === 'afficher') {
    $items = $_SESSION['panier']['items'] ?? [];
    $total = $_SESSION['panier']['total'] ?? 0.00;
    $count = $_SESSION['panier']['count'] ?? 0;
    
    // Formater les items
    $formattedItems = [];
    foreach ($items as $key => $item) {
        // Vérifier à nouveau les infos produit (au cas où)
        if (!isset($item['id_item'])) {
            $item['id_item'] = $key;
        }
        
        $formattedItems[] = [
            'id_item' => $key,
            'id_produit' => $item['id_produit'],
            'reference' => $item['reference'] ?? 'REF' . $item['id_produit'],
            'nom' => $item['nom'] ?? 'Produit ' . $item['id_produit'],
            'prix_unitaire' => number_format($item['prix_unitaire'], 2),
            'quantite' => $item['quantite'],
            'total_item' => number_format($item['prix_unitaire'] * $item['quantite'], 2),
            'image' => $item['image'] ?? 'img/default-product.jpg',
            'categorie' => $item['categorie'] ?? 'Non catégorisé',
            'date_ajout' => $item['date_ajout'] ?? date('Y-m-d H:i:s'),
            'stock_disponible' => $item['stock_disponible'] ?? 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier récupéré',
        'items' => $formattedItems,
        'total_prix' => number_format($total, 2),
        'total_articles' => $count,
        'session_id' => session_id()
    ]);
    exit;
}

// ACTION: MODIFIER QUANTITÉ
if ($action === 'modifier') {
    $id_item = '';
    $quantite = 1;
    
    if (!empty($data)) {
        $id_item = isset($data['id_item']) ? trim($data['id_item']) : '';
        $quantite = isset($data['quantite']) ? intval($data['quantite']) : 1;
    } elseif (isset($_GET['id_item'])) {
        $id_item = trim($_GET['id_item']);
        $quantite = isset($_GET['quantite']) ? intval($_GET['quantite']) : 1;
    } elseif (isset($_POST['id_item'])) {
        $id_item = trim($_POST['id_item']);
        $quantite = isset($_POST['quantite']) ? intval($_POST['quantite']) : 1;
    }
    
    // Validation
    if (empty($id_item) || !isset($_SESSION['panier']['items'][$id_item])) {
        echo json_encode([
            'success' => false,
            'message' => 'Article non trouvé dans le panier',
            'id_item' => $id_item
        ]);
        exit;
    }
    
    // Vérifier le stock
    $id_produit = $_SESSION['panier']['items'][$id_item]['id_produit'];
    $produitInfo = getProduitInfo($id_produit);
    
    if ($produitInfo && $quantite > $produitInfo['quantite_stock']) {
        echo json_encode([
            'success' => false,
            'message' => 'Stock insuffisant. Quantité disponible: ' . $produitInfo['quantite_stock'],
            'stock_disponible' => $produitInfo['quantite_stock']
        ]);
        exit;
    }
    
    if ($quantite < 1) {
        // Supprimer l'article
        unset($_SESSION['panier']['items'][$id_item]);
        $message = 'Article supprimé du panier';
    } else {
        // Modifier la quantité
        $_SESSION['panier']['items'][$id_item]['quantite'] = $quantite;
        $message = 'Quantité mise à jour';
    }
    
    // Recalculer les totaux
    $totaux = calculerTotauxPanier();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id_item' => $id_item,
        'nouvelle_quantite' => $quantite,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2),
        'panier_items_count' => count($_SESSION['panier']['items'])
    ]);
    exit;
}

// ACTION: SUPPRIMER ARTICLE
if ($action === 'supprimer') {
    $id_item = '';
    
    if (!empty($data) && isset($data['id_item'])) {
        $id_item = trim($data['id_item']);
    } elseif (isset($_GET['id_item'])) {
        $id_item = trim($_GET['id_item']);
    } elseif (isset($_POST['id_item'])) {
        $id_item = trim($_POST['id_item']);
    }
    
    if (empty($id_item) || !isset($_SESSION['panier']['items'][$id_item])) {
        echo json_encode([
            'success' => false,
            'message' => 'Article non trouvé dans le panier',
            'id_item' => $id_item
        ]);
        exit;
    }
    
    $deletedItem = $_SESSION['panier']['items'][$id_item];
    unset($_SESSION['panier']['items'][$id_item]);
    
    $totaux = calculerTotauxPanier();
    
    echo json_encode([
        'success' => true,
        'message' => 'Article supprimé du panier',
        'id_item' => $id_item,
        'article_nom' => $deletedItem['nom'] ?? 'Produit inconnu',
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2),
        'panier_items_count' => count($_SESSION['panier']['items'])
    ]);
    exit;
}

// ACTION: VIDER PANIER
if ($action === 'vider') {
    $confirmation = false;
    
    if (isset($_GET['confirmation']) && ($_GET['confirmation'] == '1' || $_GET['confirmation'] == 'true')) {
        $confirmation = true;
    } elseif (isset($_POST['confirmation']) && ($_POST['confirmation'] == '1' || $_POST['confirmation'] == 'true')) {
        $confirmation = true;
    } elseif (!empty($data) && isset($data['confirmation']) && 
             ($data['confirmation'] === true || $data['confirmation'] == '1' || $data['confirmation'] == 'true')) {
        $confirmation = true;
    } else {
        $confirmation = true; // Simplification
    }
    
    if (!$confirmation) {
        echo json_encode([
            'success' => false,
            'message' => 'Confirmation requise pour vider le panier',
            'hint' => 'Ajoutez ?confirmation=1 à l\'URL'
        ]);
        exit;
    }
    
    $oldCount = $_SESSION['panier']['count'] ?? 0;
    $oldTotal = $_SESSION['panier']['total'] ?? 0;
    
    $_SESSION['panier'] = [
        'items' => [],
        'count' => 0,
        'total' => 0.00,
        'created' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier vidé avec succès',
        'old_count' => $oldCount,
        'old_total' => number_format($oldTotal, 2),
        'new_count' => 0,
        'new_total' => '0.00',
        'session_id' => session_id(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ACTION: ÉTAT (debug)
if ($action === 'etat' || $action === 'debug') {
    echo json_encode([
        'success' => true,
        'panier' => $_SESSION['panier'],
        'session_id' => session_id(),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'method' => $_SERVER['REQUEST_METHOD']
        ]
    ]);
    exit;
}

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non spécifiée ou invalide',
    'received_action' => $action,
    'actions_disponibles' => [
        'ajouter' => 'Ajouter un produit (POST: id_produit, quantite)',
        'compter' => 'Compter les articles (GET)',
        'get' => 'Récupérer le panier (GET)',
        'modifier' => 'Modifier quantité (POST: id_item, quantite)',
        'supprimer' => 'Supprimer article (POST: id_item)',
        'vider' => 'Vider le panier (GET/POST: confirmation=1)',
        'etat' => 'État du panier (debug)'
    ]
]);
?>