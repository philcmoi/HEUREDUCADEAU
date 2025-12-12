<?php
// api/panier.php - VERSION AVEC BASE DE DONNÉES
session_start();

// Headers CORS COMPLETS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

// Gérer OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==============================================
// CONNEXION À LA BASE DE DONNÉES
// ==============================================
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=heureducadeau;charset=utf8",
                "Philippe",
                "l@99339R",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            // Mode silencieux pour ne pas casser l'API
            error_log("Erreur BDD panier: " . $e->getMessage());
            return false;
        }
    }
    
    return $pdo;
}

// Fonction pour récupérer les infos d'un produit depuis la BDD
function getProduitInfo($id_produit) {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT 
                    p.id_produit,
                    p.nom,
                    p.prix_ttc,
                    p.quantite_stock,
                    p.description_courte,
                    p.note_moyenne,
                    c.nom as categorie_nom,
                    img.url_image
                FROM produits p
                LEFT JOIN categories c ON p.id_categorie = c.id_categorie
                LEFT JOIN (
                    SELECT id_produit, url_image 
                    FROM images_produits 
                    WHERE principale = 1 
                    ORDER BY ordre LIMIT 1
                ) img ON p.id_produit = img.id_produit
                WHERE p.id_produit = ? AND p.statut = 'actif'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id_produit]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getProduitInfo: " . $e->getMessage());
        return false;
    }
}

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer l'action (JSON > GET > POST)
$action = '';
if (!empty($data) && isset($data['action'])) {
    $action = trim($data['action']);
} elseif (isset($_GET['action'])) {
    $action = trim($_GET['action']);
} elseif (isset($_POST['action'])) {
    $action = trim($_POST['action']);
}

// Initialiser panier
if (!isset($_SESSION['panier']) || !is_array($_SESSION['panier'])) {
    $_SESSION['panier'] = [
        'items' => [],
        'count' => 0,
        'total' => 0.00,
        'created' => time()
    ];
}

// FONCTION: Calculer les totaux
function calculerTotaux() {
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

// ACTION: AJOUTER (AVEC BDD)
if ($action === 'ajouter') {
    $id_produit = 0;
    $quantite = 1;
    
    // Priorité: JSON > GET > POST
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
            'message' => 'ID produit invalide ou manquant',
            'received_data' => ['data' => $data, 'get' => $_GET, 'post' => $_POST]
        ]);
        exit;
    }
    
    // Récupérer les vraies infos du produit depuis la BDD
    $produitInfo = getProduitInfo($id_produit);
    
    if (!$produitInfo) {
        echo json_encode([
            'success' => false,
            'message' => 'Produit non trouvé ou indisponible',
            'id_produit' => $id_produit
        ]);
        exit;
    }
    
    // Vérifier le stock
    if ($produitInfo['quantite_stock'] < $quantite) {
        echo json_encode([
            'success' => false,
            'message' => 'Stock insuffisant. Disponible: ' . $produitInfo['quantite_stock'],
            'stock_disponible' => $produitInfo['quantite_stock']
        ]);
        exit;
    }
    
    // Ajouter au panier avec les VRAIES informations
    $itemKey = 'item_' . $id_produit;
    
    if (isset($_SESSION['panier']['items'][$itemKey])) {
        // Mettre à jour la quantité
        $_SESSION['panier']['items'][$itemKey]['quantite'] += $quantite;
    } else {
        // Ajouter nouvel article
        $_SESSION['panier']['items'][$itemKey] = [
            'id_produit' => $id_produit,
            'nom' => $produitInfo['nom'],
            'prix_unitaire' => floatval($produitInfo['prix_ttc']),
            'description' => $produitInfo['description_courte'] ?? '',
            'categorie' => $produitInfo['categorie_nom'] ?? '',
            'note' => $produitInfo['note_moyenne'] ?? 0,
            'image' => $produitInfo['url_image'] ?? 'img/default-product.jpg',
            'quantite' => $quantite,
            'date_ajout' => date('Y-m-d H:i:s'),
            'stock_max' => $produitInfo['quantite_stock']
        ];
    }
    
    // Calculer totaux
    $totaux = calculerTotaux();
    
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'produit_nom' => $produitInfo['nom'],
        'produit_id' => $id_produit,
        'produit_prix' => number_format($produitInfo['prix_ttc'], 2, '.', ''),
        'quantite_ajoutee' => $quantite,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', ''),
        'panier_items_count' => count($_SESSION['panier']['items']),
        'session_id' => session_id(),
        'produit_info' => [
            'nom' => $produitInfo['nom'],
            'prix' => $produitInfo['prix_ttc'],
            'image' => $produitInfo['url_image'] ?? 'img/default-product.jpg',
            'categorie' => $produitInfo['categorie_nom'] ?? ''
        ]
    ]);
    exit;
}

// ACTION: COMPTER
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

// ACTION: GET (afficher) - RETOURNE LES VRAIES INFOS
if ($action === 'get' || $action === 'afficher') {
    $items = $_SESSION['panier']['items'] ?? [];
    $total = $_SESSION['panier']['total'] ?? 0.00;
    $count = $_SESSION['panier']['count'] ?? 0;
    
    // Formater les items
    $formattedItems = [];
    foreach ($items as $key => $item) {
        $formattedItems[] = [
            'id_item' => $key,
            'id_produit' => $item['id_produit'],
            'nom' => $item['nom'],
            'prix_unitaire' => number_format($item['prix_unitaire'], 2, '.', ''),
            'description' => $item['description'] ?? '',
            'categorie' => $item['categorie'] ?? '',
            'note' => $item['note'] ?? 0,
            'image' => $item['image'] ?? 'img/default-product.jpg',
            'quantite' => $item['quantite'],
            'total_item' => number_format($item['prix_unitaire'] * $item['quantite'], 2, '.', ''),
            'date_ajout' => $item['date_ajout'],
            'stock_max' => $item['stock_max'] ?? 99
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier récupéré',
        'items' => $formattedItems,
        'total_prix' => number_format($total, 2, '.', ''),
        'total_articles' => $count,
        'session_id' => session_id()
    ]);
    exit;
}

// ACTION: MODIFIER QUANTITÉ (avec vérification stock)
if ($action === 'modifier') {
    $id_item = '';
    $quantite = 1;
    
    // Récupérer les paramètres
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
            'id_item' => $id_item,
            'items_keys' => array_keys($_SESSION['panier']['items'] ?? [])
        ]);
        exit;
    }
    
    // Vérifier le stock en BDD si nouvelle quantité > ancienne
    $ancienneQuantite = $_SESSION['panier']['items'][$id_item]['quantite'];
    $id_produit = $_SESSION['panier']['items'][$id_item]['id_produit'];
    
    if ($quantite > $ancienneQuantite) {
        $produitInfo = getProduitInfo($id_produit);
        if ($produitInfo && $quantite > $produitInfo['quantite_stock']) {
            echo json_encode([
                'success' => false,
                'message' => 'Stock insuffisant. Disponible: ' . $produitInfo['quantite_stock'],
                'stock_disponible' => $produitInfo['quantite_stock']
            ]);
            exit;
        }
    }
    
    if ($quantite < 1) {
        // Si quantité = 0, supprimer l'article
        unset($_SESSION['panier']['items'][$id_item]);
        $message = 'Article supprimé du panier';
    } else {
        // Modifier la quantité
        $_SESSION['panier']['items'][$id_item]['quantite'] = $quantite;
        
        // Mettre à jour le stock max
        if (isset($produitInfo)) {
            $_SESSION['panier']['items'][$id_item]['stock_max'] = $produitInfo['quantite_stock'];
        }
        
        $message = 'Quantité mise à jour';
    }
    
    // Recalculer les totaux
    $totaux = calculerTotaux();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id_item' => $id_item,
        'nouvelle_quantite' => $quantite,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', ''),
        'panier_items_count' => count($_SESSION['panier']['items'])
    ]);
    exit;
}

// ACTION: SUPPRIMER ARTICLE
if ($action === 'supprimer') {
    $id_item = '';
    
    // Récupérer l'ID
    if (!empty($data) && isset($data['id_item'])) {
        $id_item = trim($data['id_item']);
    } elseif (isset($_GET['id_item'])) {
        $id_item = trim($_GET['id_item']);
    } elseif (isset($_POST['id_item'])) {
        $id_item = trim($_POST['id_item']);
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
    
    // Sauvegarder info avant suppression
    $deletedItem = $_SESSION['panier']['items'][$id_item];
    
    // Supprimer l'article
    unset($_SESSION['panier']['items'][$id_item]);
    
    // Recalculer les totaux
    $totaux = calculerTotaux();
    
    echo json_encode([
        'success' => true,
        'message' => 'Article supprimé du panier',
        'id_item' => $id_item,
        'article_supprime' => $deletedItem,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', ''),
        'panier_items_count' => count($_SESSION['panier']['items'])
    ]);
    exit;
}

// ACTION: VIDER
if ($action === 'vider') {
    // Vérifier la confirmation
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
    
    // Sauvegarder l'ancien panier
    $oldCount = $_SESSION['panier']['count'] ?? 0;
    $oldTotal = $_SESSION['panier']['total'] ?? 0;
    
    // Réinitialiser le panier
    $_SESSION['panier'] = [
        'items' => [],
        'count' => 0,
        'total' => 0.00,
        'created' => time(),
        'last_emptied' => date('Y-m-d H:i:s'),
        'previous' => [
            'count' => $oldCount,
            'total' => $oldTotal
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier vidé avec succès',
        'old_count' => $oldCount,
        'old_total' => number_format($oldTotal, 2, '.', ''),
        'new_count' => 0,
        'new_total' => '0.00',
        'session_id' => session_id(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// ACTION: ÉTAT (pour débogage)
if ($action === 'etat' || $action === 'debug') {
    echo json_encode([
        'success' => true,
        'panier' => $_SESSION['panier'],
        'session_id' => session_id(),
        'actions_disponibles' => ['ajouter', 'compter', 'get', 'modifier', 'supprimer', 'vider', 'etat'],
        'server_info' => [
            'php_version' => PHP_VERSION,
            'method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'non défini'
        ]
    ]);
    exit;
}

// ACTION: SYNC (mettre à jour les infos depuis la BDD)
if ($action === 'sync') {
    // Synchroniser tous les articles avec la BDD
    $updatedItems = [];
    $itemsToRemove = [];
    
    foreach ($_SESSION['panier']['items'] as $key => $item) {
        $produitInfo = getProduitInfo($item['id_produit']);
        
        if (!$produitInfo) {
            // Produit n'existe plus
            $itemsToRemove[] = $key;
            continue;
        }
        
        // Mettre à jour les informations
        $_SESSION['panier']['items'][$key]['nom'] = $produitInfo['nom'];
        $_SESSION['panier']['items'][$key]['prix_unitaire'] = floatval($produitInfo['prix_ttc']);
        $_SESSION['panier']['items'][$key]['description'] = $produitInfo['description_courte'] ?? '';
        $_SESSION['panier']['items'][$key]['categorie'] = $produitInfo['categorie_nom'] ?? '';
        $_SESSION['panier']['items'][$key]['image'] = $produitInfo['url_image'] ?? 'img/default-product.jpg';
        $_SESSION['panier']['items'][$key]['stock_max'] = $produitInfo['quantite_stock'];
        
        // Ajuster la quantité si nécessaire
        if ($item['quantite'] > $produitInfo['quantite_stock']) {
            $_SESSION['panier']['items'][$key]['quantite'] = $produitInfo['quantite_stock'];
            $updatedItems[] = ['id' => $item['id_produit'], 'ancienne' => $item['quantite'], 'nouvelle' => $produitInfo['quantite_stock']];
        }
    }
    
    // Supprimer les produits inexistants
    foreach ($itemsToRemove as $key) {
        unset($_SESSION['panier']['items'][$key]);
    }
    
    // Recalculer les totaux
    $totaux = calculerTotaux();
    
    echo json_encode([
        'success' => true,
        'message' => 'Panier synchronisé avec la BDD',
        'items_updated' => $updatedItems,
        'items_removed' => $itemsToRemove,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', '')
    ]);
    exit;
}

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non spécifiée ou invalide',
    'received_action' => $action,
    'actions_disponibles' => [
        'ajouter' => 'Ajouter un produit (avec vérification BDD)',
        'compter' => 'Compter les articles',
        'get' => 'Récupérer le panier',
        'modifier' => 'Modifier quantité',
        'supprimer' => 'Supprimer article',
        'vider' => 'Vider le panier',
        'sync' => 'Synchroniser avec BDD',
        'etat' => 'État du panier (debug)'
    ]
]);
?>