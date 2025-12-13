<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT * FROM adresses WHERE id_adresse = ? AND id_client = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_GET['id'], $_SESSION['client_id'] ?? 0]);
    $adresse = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($adresse) {
        echo json_encode(['success' => true, 'adresse' => $adresse]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Adresse non trouvée']);
    }
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
}
?>