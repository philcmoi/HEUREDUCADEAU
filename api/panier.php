<?php
// api/panier.php - VERSION COMPLÈTE CORRIGÉE
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

// ACTION: AJOUTER
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
    
    // Ajouter au panier
    $itemKey = 'item_' . $id_produit;
    
    if (isset($_SESSION['panier']['items'][$itemKey])) {
        $_SESSION['panier']['items'][$itemKey]['quantite'] += $quantite;
    } else {
        $_SESSION['panier']['items'][$itemKey] = [
            'id_produit' => $id_produit,
            'nom' => 'Produit ' . $id_produit,
            'prix_unitaire' => 29.99,
            'quantite' => $quantite,
            'date_ajout' => date('Y-m-d H:i:s')
        ];
    }
    
    // Calculer totaux
    $totaux = calculerTotaux();
    
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté au panier',
        'produit_nom' => 'Produit ' . $id_produit,
        'produit_id' => $id_produit,
        'quantite_ajoutee' => $quantite,
        'total_articles' => $totaux['items'],
        'total_prix' => number_format($totaux['price'], 2, '.', ''),
        'prix_final' => '29.99',
        'panier_items_count' => count($_SESSION['panier']['items']),
        'session_id' => session_id(),
        'debug' => ['action_received' => $action, 'id_received' => $id_produit]
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

// ACTION: GET (afficher)
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
            'prix_unitaire' => number_format($item['prix_unitaire'], 2),
            'quantite' => $item['quantite'],
            'total_item' => number_format($item['prix_unitaire'] * $item['quantite'], 2),
            'date_ajout' => $item['date_ajout']
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
    
    if ($quantite < 1) {
        // Si quantité = 0, supprimer l'article
        unset($_SESSION['panier']['items'][$id_item]);
        $message = 'Article supprimé du panier';
    } else {
        // Modifier la quantité
        $_SESSION['panier']['items'][$id_item]['quantite'] = $quantite;
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
        'total_prix' => number_format($totaux['price'], 2),
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
        'total_prix' => number_format($totaux['price'], 2),
        'panier_items_count' => count($_SESSION['panier']['items'])
    ]);
    exit;
}

// ACTION: VIDER
if ($action === 'vider') {
    // Vérifier la confirmation - Accepte toutes les méthodes
    $confirmation = false;
    
    // 1. Via paramètre GET
    if (isset($_GET['confirmation']) && ($_GET['confirmation'] == '1' || $_GET['confirmation'] == 'true')) {
        $confirmation = true;
    }
    // 2. Via POST form-data
    elseif (isset($_POST['confirmation']) && ($_POST['confirmation'] == '1' || $_POST['confirmation'] == 'true')) {
        $confirmation = true;
    }
    // 3. Via JSON
    elseif (!empty($data) && isset($data['confirmation']) && 
           ($data['confirmation'] === true || $data['confirmation'] == '1' || $data['confirmation'] == 'true')) {
        $confirmation = true;
    }
    // 4. Pas de confirmation requise si action est 'vider' seul
    else {
        $confirmation = true; // Simplification pour le moment
    }
    
    if (!$confirmation) {
        echo json_encode([
            'success' => false,
            'message' => 'Confirmation requise pour vider le panier',
            'hint' => 'Ajoutez ?confirmation=1 à l\'URL',
            'action' => $action,
            'received_confirmation' => [
                'get' => $_GET['confirmation'] ?? 'non',
                'post' => $_POST['confirmation'] ?? 'non',
                'json' => $data['confirmation'] ?? 'non'
            ]
        ]);
        exit;
    }
    
    // Sauvegarder l'ancien panier pour log
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
        'old_total' => number_format($oldTotal, 2),
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

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non spécifiée ou invalide',
    'received_action' => $action,
    'input_data' => $data,
    'raw_input' => $input,
    'actions_disponibles' => [
        'ajouter' => 'Ajouter un produit',
        'compter' => 'Compter les articles',
        'get' => 'Récupérer le panier',
        'modifier' => 'Modifier quantité',
        'supprimer' => 'Supprimer article',
        'vider' => 'Vider le panier',
        'etat' => 'État du panier (debug)'
    ],
    'session_info' => [
        'session_id' => session_id(),
        'panier_items' => count($_SESSION['panier']['items'] ?? []),
        'panier_total' => $_SESSION['panier']['total'] ?? 0
    ]
]);
?>