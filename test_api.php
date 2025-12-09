<?php
// api/test_simple.php
session_start();
header('Content-Type: application/json');

// Simplement retourner les données reçues
echo json_encode([
    'success' => true,
    'message' => 'Test OK',
    'received' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'get' => $_GET,
        'post' => $_POST,
        'input' => file_get_contents('php://input'),
        'session_id' => session_id()
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
?>