<?php
session_start();
header('Content-Type: application/json');

$response = [
    'hasAddress' => false,
    'adresse' => null
];

if (isset($_SESSION['adresse_livraison'])) {
    $response['hasAddress'] = true;
    $response['adresse'] = $_SESSION['adresse_livraison'];
}

echo json_encode($response);
?>