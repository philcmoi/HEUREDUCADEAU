<?php
// check_session.php - FICHIER DE COMPATIBILITÉ
session_start();

header('Content-Type: application/json');

// Vérifier les données dans l'ancien format
$hasAddress = isset($_SESSION['adresse_livraison']);
$addressData = null;

if ($hasAddress) {
    // Convertir l'ancien format en nouveau format
    $oldAddress = $_SESSION['adresse_livraison'];
    
    $addressData = [
        'nom' => $oldAddress['nom'] ?? '',
        'prenom' => $oldAddress['prenom'] ?? '',
        'adresse' => $oldAddress['adresse'] ?? '',
        'complement' => $oldAddress['complement'] ?? '',
        'code_postal' => $oldAddress['code_postal'] ?? '',
        'ville' => $oldAddress['ville'] ?? '',
        'pays' => $oldAddress['pays'] ?? 'France',
        'telephone' => $oldAddress['telephone'] ?? '',
        'email' => $oldAddress['email'] ?? '',
        'societe' => $oldAddress['societe'] ?? '',
        'instructions' => $oldAddress['instructions'] ?? '',
        'mode_livraison' => $oldAddress['mode_livraison'] ?? 'standard',
        'emballage_cadeau' => $oldAddress['emballage_cadeau'] ?? false
    ];
}

// Vérifier aussi dans le nouveau système (commande API)
if (!$hasAddress && isset($_SESSION['commande'])) {
    $commande = $_SESSION['commande'];
    if (isset($commande['adresse_livraison'])) {
        $hasAddress = true;
        $addressData = $commande['adresse_livraison'];
    }
}

echo json_encode([
    'success' => true,
    'hasAddress' => $hasAddress,
    'adresse' => $addressData,
    'session_id' => session_id(),
    'system' => 'compat'
]);
?>