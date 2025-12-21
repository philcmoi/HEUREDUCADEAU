<?php
session_start();
echo '<pre>';
echo 'Session ID: ' . session_id() . "\n";
echo 'Session Status: ' . session_status() . "\n";
echo 'Cookie Params: ' . print_r(session_get_cookie_params(), true) . "\n";
echo 'Panier dans session: ' . print_r($_SESSION['panier'] ?? 'Aucun panier', true) . "\n";

// Test d'ajout
if (isset($_GET['ajouter'])) {
    $id = intval($_GET['ajouter']);
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [];
    }
    
    $_SESSION['panier'][] = [
        'id_produit' => $id,
        'nom' => 'Test produit ' . $id,
        'quantite' => 1,
        'date' => date('Y-m-d H:i:s')
    ];
    
    echo "Produit $id ajouté!\n";
}

echo '</pre>';
?>