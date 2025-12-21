<?php
// skip_conversion.php
session_start();

// Supprimer les données de conversion
unset($_SESSION['temp_checkout_data']);

// Rediriger vers la page d'accueil
header('Location: index.php?message=conversion_skipped');
exit;
?>