<?php
// api/panier_fix.php - Version ultra-simple qui marche
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

// Debug
error_log("Panier fix appelé: " . date('Y-m-d H:i:s'));

// Toujours répondre à OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Lire les données
$json = file_get_contents('php://input');
$data = json_decode($json, true);
error_log("JSON reçu: " . $json);

// Récupérer action
$action = $_GET['action'] ?? $data['action'] ?? $_POST['action'] ?? '';
error_log("Action détectée: " . $action);

// Initialiser panier
if (!isset($_SESSION['panier'])) {
    $_SESSION['panier'] = [
        'items' => [],
        'count' => 0,
        'total' => 0.00
    ];
}

// Action: AJOUTER
if ($action === 'ajouter') {
    $id = (int)($_GET['id_produit'] ?? $data['id_produit'] ?? $_POST['id_produit'] ?? 0);
    $qty = (int)($_GET['quantite'] ?? $data['quantite'] ?? $_POST['quantite'] ?? 1);
    
    if ($id < 1) {
        echo json_encode(['success' => false, 'message' => 'ID invalide: ' . $id]);
        exit;
    }
    
    // Ajouter
    $itemKey = 'prod_' . $id;
    if (isset($_SESSION['panier']['items'][$itemKey])) {
        $_SESSION['panier']['items'][$itemKey]['quantite'] += $qty;
    } else {
        $_SESSION['panier']['items'][$itemKey] = [
            'id' => $id,
            'nom' => 'Produit ' . $id,
            'prix' => 29.99,
            'quantite' => $qty,
            'added' => time()
        ];
    }
    
    // Calculer totaux
    $totalItems = 0;
    $totalPrice = 0.00;
    foreach ($_SESSION['panier']['items'] as $item) {
        $totalItems += $item['quantite'];
        $totalPrice += $item['prix'] * $item['quantite'];
    }
    
    $_SESSION['panier']['count'] = $totalItems;
    $_SESSION['panier']['total'] = $totalPrice;
    
    echo json_encode([
        'success' => true,
        'message' => 'Ajouté avec succès!',
        'produit_nom' => 'Produit ' . $id,
        'produit_id' => $id,
        'quantite_ajoutee' => $qty,
        'total_articles' => $totalItems,
        'total_prix' => number_format($totalPrice, 2),
        'session_id' => session_id(),
        'debug' => [
            'items_count' => count($_SESSION['panier']['items']),
            'items' => array_keys($_SESSION['panier']['items'])
        ]
    ]);
    exit;
}

// Action: COMPTER
if ($action === 'compter') {
    echo json_encode([
        'success' => true,
        'total' => $_SESSION['panier']['count'] ?? 0,
        'has_items' => ($_SESSION['panier']['count'] ?? 0) > 0,
        'session_id' => session_id()
    ]);
    exit;
}

// Action inconnue
echo json_encode([
    'success' => false,
    'message' => 'Action inconnue: ' . $action,
    'received' => [
        'json' => $data,
        'get' => $_GET,
        'post' => $_POST,
        'input' => $json
    ],
    'session_id' => session_id()
]);
?>