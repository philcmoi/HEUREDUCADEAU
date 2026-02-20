<?php
// cart-api.php - Point d'entrée UNIQUE pour tout le site
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Inclure le vrai fichier panier.php
require_once __DIR__ . '/panier.php';
?>