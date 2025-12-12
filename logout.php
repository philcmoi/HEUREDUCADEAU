<?php
// logout.php - Adapté à heureducadeau
// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sauvegarder le nom d'utilisateur pour le message
$username = $_SESSION['admin_username'] ?? '';

// Détruire toutes les données de session
$_SESSION = array();

// Détruire le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Rediriger vers la page de login avec message
header('Location: login.php?message=logged_out&user=' . urlencode($username));
exit();
?>