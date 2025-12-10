<?php
// admin_produits.php

// INCLURE la classe Database
require_once __DIR__ . '/Database.php';

session_start();

// Vérifier si l'utilisateur est connecté en tant qu'admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit();
}

try {
    $db = Database::getInstance();
    // Pour utiliser la connexion : $db->getConnection()
    $conn = $db->getConnection();
    
    // Le reste de votre code...
} catch (Exception $e) {
    die('Erreur de connexion à la base de données: ' . $e->getMessage());
}

// ... reste du code
?>