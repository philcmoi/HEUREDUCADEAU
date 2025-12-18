<?php
// Démarrer la session
session_start();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Nettoyer et valider les données
    $donnees = [
        'nom' => trim(htmlspecialchars($_POST['nom'] ?? '')),
        'adresse' => trim(htmlspecialchars($_POST['adresse'] ?? '')),
        'ville' => trim(htmlspecialchars($_POST['ville'] ?? '')),
        'code_postal' => trim(htmlspecialchars($_POST['code_postal'] ?? '')),
        'pays' => trim(htmlspecialchars($_POST['pays'] ?? 'France')),
        'telephone' => trim(htmlspecialchars($_POST['telephone'] ?? '')),
        'email' => trim(htmlspecialchars($_POST['email'] ?? '')),
        'instructions' => trim(htmlspecialchars($_POST['instructions'] ?? '')),
        'date_saisie' => date('Y-m-d H:i:s')
    ];
    
    // Validation basique
    $erreurs = [];
    
    if (empty($donnees['nom'])) {
        $erreurs[] = "Le nom est requis";
    }
    
    if (empty($donnees['adresse'])) {
        $erreurs[] = "L'adresse est requise";
    }
    
    if (empty($donnees['ville'])) {
        $erreurs[] = "La ville est requise";
    }
    
    if (empty($donnees['code_postal'])) {
        $erreurs[] = "Le code postal est requis";
    }
    
    if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'email n'est pas valide";
    }
    
    // Si il y a des erreurs, retourner au formulaire avec les messages
    if (!empty($erreurs)) {
        $_SESSION['erreurs_livraison'] = $erreurs;
        $_SESSION['donnees_saisies'] = $donnees;
        header('Location: livraison.html');
        exit();
    }
    
    // Stocker les données en session
    $_SESSION['adresse_livraison'] = $donnees;
    
    // Effacer les éventuelles données temporaires
    unset($_SESSION['erreurs_livraison']);
    unset($_SESSION['donnees_saisies']);
    
    // Historique des adresses (conserver les acquis du passé)
    if (!isset($_SESSION['historique_adresses'])) {
        $_SESSION['historique_adresses'] = [];
    }
    
    // Ajouter à l'historique (sans doublons récents)
    $adresse_existe = false;
    foreach ($_SESSION['historique_adresses'] as $adresse) {
        if ($adresse['adresse'] === $donnees['adresse'] && 
            $adresse['code_postal'] === $donnees['code_postal']) {
            $adresse_existe = true;
            break;
        }
    }
    
    if (!$adresse_existe) {
        $_SESSION['historique_adresses'][] = $donnees;
        
        // Garder seulement les 5 dernières adresses
        if (count($_SESSION['historique_adresses']) > 5) {
            array_shift($_SESSION['historique_adresses']);
        }
    }
    
    // Redirection vers l'étape suivante (paiement)
    header('Location: paiement.php');
    exit();
    
} else {
    // Accès direct au script sans soumission de formulaire
    // Vérifier si une adresse existe déjà
    if (isset($_SESSION['adresse_livraison'])) {
        // Si une adresse existe, proposer de l'utiliser ou d'en saisir une nouvelle
        header('Location: confirmation_adresse.php');
    } else {
        // Sinon, rediriger vers le formulaire
        header('Location: livraison.html');
    }
    exit();
}
?>