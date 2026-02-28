<?php
// livraison.php - VERSION CORRIGÉE AVEC PRÉSERVATION DES ACQUIS
session_start();

// ==============================================
// 1. GESTION DU FLUX API MODERNE
// ==============================================

// Vérifier si le formulaire a été soumis en mode API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // DÉTECTION DU MODE : API ou traditionnel
    $is_api_mode = isset($_POST['api_mode']) || isset($_SERVER['HTTP_X_API_MODE']);
    
    if ($is_api_mode) {
        // ========== MODE API MODERNE ==========
        try {
            // Récupérer les données JSON
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            // Fallback pour compatibilité avec différentes méthodes d'envoi
            if (!$data || json_last_error() !== JSON_ERROR_NONE) {
                $data = $_POST; // Fallback pour compatibilité
            }
            
            // Validation des données
            $required = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'email', 'telephone'];
            $missing = [];
            
            foreach ($required as $field) {
                if (empty(trim($data[$field] ?? ''))) {
                    $missing[] = $field;
                }
            }
            
            if (!empty($missing)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Champs manquants: ' . implode(', ', $missing),
                    'missing' => $missing
                ]);
                exit();
            }
            
            // Validation supplémentaire
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'L\'email n\'est pas valide'
                ]);
                exit();
            }
            
            // Nettoyage des données
            $clean_data = [
                'nom' => htmlspecialchars(trim($data['nom']), ENT_QUOTES, 'UTF-8'),
                'prenom' => htmlspecialchars(trim($data['prenom']), ENT_QUOTES, 'UTF-8'),
                'adresse' => htmlspecialchars(trim($data['adresse']), ENT_QUOTES, 'UTF-8'),
                'complement' => htmlspecialchars(trim($data['complement'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'code_postal' => htmlspecialchars(trim($data['code_postal']), ENT_QUOTES, 'UTF-8'),
                'ville' => htmlspecialchars(trim($data['ville']), ENT_QUOTES, 'UTF-8'),
                'pays' => htmlspecialchars(trim($data['pays'] ?? 'France'), ENT_QUOTES, 'UTF-8'),
                'telephone' => htmlspecialchars(trim($data['telephone']), ENT_QUOTES, 'UTF-8'),
                'email' => filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL),
                'societe' => htmlspecialchars(trim($data['societe'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'mode_livraison' => htmlspecialchars(trim($data['mode_livraison'] ?? 'standard'), ENT_QUOTES, 'UTF-8'),
                'emballage_cadeau' => isset($data['emballage_cadeau']) && $data['emballage_cadeau'] == '1',
                'instructions' => htmlspecialchars(trim($data['instructions'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'date_saisie' => date('Y-m-d H:i:s')
            ];
            
            // Sauvegarder en session pour compatibilité avec ancien système
            $_SESSION['adresse_livraison'] = $clean_data;
            
            // Sauvegarder dans l'historique (acquis du passé)
            if (!isset($_SESSION['historique_adresses'])) {
                $_SESSION['historique_adresses'] = [];
            }
            
            $adresse_existe = false;
            foreach ($_SESSION['historique_adresses'] as $adresse) {
                if ($adresse['adresse'] === $clean_data['adresse'] && 
                    $adresse['code_postal'] === $clean_data['code_postal']) {
                    $adresse_existe = true;
                    break;
                }
            }
            
            if (!$adresse_existe) {
                $_SESSION['historique_adresses'][] = $clean_data;
                
                // Garder seulement les 5 dernières adresses
                if (count($_SESSION['historique_adresses']) > 5) {
                    array_shift($_SESSION['historique_adresses']);
                }
            }
            
            // Réponse API
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Adresse sauvegardée',
                'adresse' => $clean_data,
                'redirect' => 'paiement.html',
                'compat_redirect' => 'paiement.php'
            ]);
            exit();
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ]);
            exit();
        }
        
    } else {
        // ========== MODE TRADITIONNEL (ANCIEN SYSTÈME) ==========
        // Code original préservé avec améliorations
        $donnees = [
            'nom' => trim(htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8')),
            'prenom' => trim(htmlspecialchars($_POST['prenom'] ?? '', ENT_QUOTES, 'UTF-8')),
            'adresse' => trim(htmlspecialchars($_POST['adresse'] ?? '', ENT_QUOTES, 'UTF-8')),
            'ville' => trim(htmlspecialchars($_POST['ville'] ?? '', ENT_QUOTES, 'UTF-8')),
            'code_postal' => trim(htmlspecialchars($_POST['code_postal'] ?? '', ENT_QUOTES, 'UTF-8')),
            'pays' => trim(htmlspecialchars($_POST['pays'] ?? 'France', ENT_QUOTES, 'UTF-8')),
            'telephone' => trim(htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8')),
            'email' => filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL),
            'instructions' => trim(htmlspecialchars($_POST['instructions'] ?? '', ENT_QUOTES, 'UTF-8')),
            'date_saisie' => date('Y-m-d H:i:s')
        ];
        
        // Validation basique
        $erreurs = [];
        
        if (empty($donnees['nom'])) {
            $erreurs[] = "Le nom est requis";
        }
        
        if (empty($donnees['prenom'])) {
            $erreurs[] = "Le prénom est requis";
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
        
        if (empty($donnees['telephone'])) {
            $erreurs[] = "Le téléphone est requis";
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
        
        // Historique des adresses (acquis du passé)
        if (!isset($_SESSION['historique_adresses'])) {
            $_SESSION['historique_adresses'] = [];
        }
        
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
        // Utiliser l'ancienne page paiement.php pour compatibilité
        header('Location: paiement.php');
        exit();
    }
    
} else {
    // ========== ACCÈS DIRECT ==========
    
    // Mode API : retourner les données existantes
    if (isset($_SERVER['HTTP_X_API_MODE']) || isset($_GET['api'])) {
        header('Content-Type: application/json');
        
        if (isset($_SESSION['adresse_livraison'])) {
            echo json_encode([
                'success' => true,
                'hasAddress' => true,
                'adresse' => $_SESSION['adresse_livraison']
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'hasAddress' => false,
                'message' => 'Aucune adresse enregistrée'
            ]);
        }
        exit();
    }
    
    // Mode traditionnel : vérifier si une adresse existe déjà
    if (isset($_SESSION['adresse_livraison'])) {
        // Rediriger vers une page de confirmation si nécessaire
        header('Location: confirmation_adresse.php');
    } else {
        // Sinon, rediriger vers le formulaire
        header('Location: livraison.html');
    }
    exit();
}
?>