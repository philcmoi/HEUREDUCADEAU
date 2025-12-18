<?php
session_start();
header('Content-Type: application/json');

$response = ['success' => false, 'adresse' => null];

if (isset($_GET['index']) && isset($_SESSION['historique_adresses'])) {
    $index = intval($_GET['index']);
    if (isset($_SESSION['historique_adresses'][$index])) {
        $response['success'] = true;
        $response['adresse'] = $_SESSION['historique_adresses'][$index];
    }
}

echo json_encode($response);
?>