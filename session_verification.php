<?php
// ============================================
// SESSION VERIFICATION - FONCTIONS CENTRALISÉES
// ============================================

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONSTANTES DE SESSION (standardisées)
// ============================================
define('SESSION_KEY_PANIER', 'panier');
define('SESSION_KEY_PANIER_ID', 'panier_id');
define('SESSION_KEY_CLIENT_ID', 'client_id');
define('SESSION_KEY_CHECKOUT', 'checkout');
define('SESSION_KEY_COMMANDE', 'commande_en_cours');
define('SESSION_KEY_MESSAGES', 'session_messages');
define('SESSION_KEY_ERRORS', 'checkout_errors');

// ============================================
// FONCTIONS DE VÉRIFICATION D'ACCÈS
// ============================================

/**
 * Vérifie l'accès à la page de livraison
 */
function checkLivraisonAccess() {
    // Vérifier que le panier n'est pas vide
    if (!hasValidCart()) {
        header('Location: panier.html');
        exit;
    }
    
    // Si on vient de panier.html, on initialise le checkout
    if (!hasCheckout()) {
        initCheckout(getPanierId());
    }
}

/**
 * Vérifie l'accès à la page de paiement
 */
function checkPaiementAccess() {
    // Vérifier que le panier n'est pas vide
    if (!hasValidCart()) {
        header('Location: panier.html');
        exit;
    }
    
    // Vérifier qu'une adresse de livraison a été saisie
    if (!hasShippingAddress()) {
        addSessionMessage('Veuillez d\'abord renseigner votre adresse de livraison', 'error');
        header('Location: livraison_form.php');
        exit;
    }
}

// ============================================
// FONCTIONS DE VÉRIFICATION D'ÉTAT
// ============================================

/**
 * Vérifie si le panier contient des articles
 */
function hasValidCart() {
    return isset($_SESSION[SESSION_KEY_PANIER]) && 
           is_array($_SESSION[SESSION_KEY_PANIER]) && 
           count($_SESSION[SESSION_KEY_PANIER]) > 0;
}

/**
 * Retourne le nombre d'articles dans le panier
 */
function countCartItems() {
    if (!hasValidCart()) return 0;
    
    $total = 0;
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $total += intval($item['quantite'] ?? 1);
    }
    return $total;
}

/**
 * Vérifie si une session de checkout existe
 */
function hasCheckout() {
    return isset($_SESSION[SESSION_KEY_CHECKOUT]) && 
           is_array($_SESSION[SESSION_KEY_CHECKOUT]);
}

/**
 * Vérifie si une adresse de livraison a été saisie
 */
function hasShippingAddress() {
    return hasCheckout() && 
           isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']) && 
           !empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
}

/**
 * Retourne l'ID du panier
 */
function getPanierId() {
    return $_SESSION[SESSION_KEY_PANIER_ID] ?? null;
}

// ============================================
// FONCTIONS D'INITIALISATION
// ============================================

/**
 * Initialise la session de checkout
 */
function initCheckout($panier_id = null) {
    $_SESSION[SESSION_KEY_CHECKOUT] = [
        'panier_id' => $panier_id,
        'etape' => 'livraison',
        'date_creation' => date('Y-m-d H:i:s'),
        'date_modification' => date('Y-m-d H:i:s'),
        'validation' => [
            'panier_valide' => true,
            'adresse_valide' => false,
            'paiement_autorise' => false
        ]
    ];
}

// ============================================
// FONCTIONS DE GESTION DES MESSAGES
// ============================================

/**
 * Ajoute un message en session
 */
function addSessionMessage($message, $type = 'success') {
    if (!isset($_SESSION[SESSION_KEY_MESSAGES])) {
        $_SESSION[SESSION_KEY_MESSAGES] = [];
    }
    $_SESSION[SESSION_KEY_MESSAGES][] = [
        'message' => $message,
        'type' => $type,
        'date' => date('Y-m-d H:i:s')
    ];
}

/**
 * Récupère et efface les messages
 */
function getSessionMessages() {
    $messages = $_SESSION[SESSION_KEY_MESSAGES] ?? [];
    unset($_SESSION[SESSION_KEY_MESSAGES]);
    return $messages;
}

/**
 * Ajoute des erreurs de checkout
 */
function addCheckoutErrors($errors) {
    if (!is_array($errors)) {
        $errors = [$errors];
    }
    $_SESSION[SESSION_KEY_ERRORS] = $errors;
}

/**
 * Récupère et efface les erreurs de checkout
 */
function getCheckoutErrors() {
    $errors = $_SESSION[SESSION_KEY_ERRORS] ?? [];
    unset($_SESSION[SESSION_KEY_ERRORS]);
    return $errors;
}

// ============================================
// FONCTIONS DE CONNEXION BDD
// ============================================

/**
 * Retourne une connexion PDO à la base de données
 */
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $host = 'localhost';
        $dbname = 'heureducadeau';
        $username = 'Philippe';
        $password = 'l@99339R';
        
        try {
            $pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4", 
                $username, 
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur connexion BDD: " . $e->getMessage());
            return null;
        }
    }
    
    return $pdo;
}

// ============================================
// FONCTIONS DE SYNCHRONISATION PANIER
// ============================================

/**
 * Synchronise le panier session avec la BDD
 */
function synchroniserPanierSessionBDD($pdo, $session_id) {
    if (!$pdo) return;
    
    try {
        // Chercher un panier existant pour cette session
        $stmt = $pdo->prepare("
            SELECT id_panier FROM panier 
            WHERE session_id = ? AND statut = 'actif'
            ORDER BY date_creation DESC LIMIT 1
        ");
        $stmt->execute([$session_id]);
        $panier_bdd = $stmt->fetch();
        
        if ($panier_bdd) {
            $_SESSION[SESSION_KEY_PANIER_ID] = $panier_bdd['id_panier'];
            
            // Si la session a des articles mais pas la BDD, on synchronise
            if (hasValidCart()) {
                $stmt = $pdo->prepare("
                    SELECT id_produit, quantite FROM panier_items 
                    WHERE id_panier = ?
                ");
                $stmt->execute([$panier_bdd['id_panier']]);
                $items_bdd = $stmt->fetchAll();
                
                // Si BDD vide mais session pleine, on sauvegarde en BDD
                if (empty($items_bdd)) {
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    
                    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
                        $produit = getProductDetails($item['id_produit'], $pdo);
                        $prix = $produit['prix_ttc'] ?? $item['prix'] ?? 0;
                        
                        $stmt_insert->execute([
                            $panier_bdd['id_panier'],
                            $item['id_produit'],
                            $item['quantite'],
                            $prix
                        ]);
                    }
                }
            }
        } else if (hasValidCart()) {
            // Créer un nouveau panier en BDD
            $stmt = $pdo->prepare("
                INSERT INTO panier (session_id, statut, date_creation)
                VALUES (?, 'actif', NOW())
            ");
            $stmt->execute([$session_id]);
            $panier_id = $pdo->lastInsertId();
            $_SESSION[SESSION_KEY_PANIER_ID] = $panier_id;
            
            // Ajouter les articles
            $stmt_item = $pdo->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
                $produit = getProductDetails($item['id_produit'], $pdo);
                $prix = $produit['prix_ttc'] ?? $item['prix'] ?? 0;
                
                $stmt_item->execute([
                    $panier_id,
                    $item['id_produit'],
                    $item['quantite'],
                    $prix
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Erreur synchronisation panier: " . $e->getMessage());
    }
}

// ============================================
// FONCTIONS PRODUITS
// ============================================

/**
 * Récupère les détails d'un produit
 */
function getProductDetails($id_produit, $pdo) {
    if (!$pdo) {
        return [
            'id_produit' => $id_produit,
            'nom' => 'Produit #' . $id_produit,
            'prix_ttc' => 19.99,
            'reference' => 'REF' . $id_produit,
            'quantite_stock' => 100,
            'image' => 'img/default-product.jpg'
        ];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id_produit, nom, prix_ttc, reference, quantite_stock, 
                   (SELECT url_image FROM images_produits WHERE id_produit = p.id_produit AND principale = 1 LIMIT 1) as image
            FROM produits p
            WHERE id_produit = ?
        ");
        $stmt->execute([$id_produit]);
        $produit = $stmt->fetch();
        
        if (!$produit) {
            return [
                'id_produit' => $id_produit,
                'nom' => 'Produit #' . $id_produit,
                'prix_ttc' => 19.99,
                'reference' => 'REF' . $id_produit,
                'quantite_stock' => 100,
                'image' => 'img/default-product.jpg'
            ];
        }
        
        return $produit;
    } catch (Exception $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return [
            'id_produit' => $id_produit,
            'nom' => 'Produit #' . $id_produit,
            'prix_ttc' => 19.99,
            'reference' => 'REF' . $id_produit,
            'quantite_stock' => 100,
            'image' => 'img/default-product.jpg'
        ];
    }
}

// ============================================
// FONCTIONS DE CALCUL
// ============================================

/**
 * Calcule les totaux du panier
 */
function calculerTotauxPanier($panier_details, $checkout = []) {
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($panier_details as $item) {
        $sous_total += floatval($item['prix_total'] ?? 0);
        $total_items += intval($item['quantite'] ?? 1);
    }
    
    // Frais de livraison
    $mode_livraison = $checkout['mode_livraison'] ?? 'standard';
    $frais_livraison = 0;
    
    if ($mode_livraison === 'express') {
        $frais_livraison = 9.90;
    } elseif ($mode_livraison === 'relais') {
        $frais_livraison = 4.90;
    } elseif ($sous_total < 50.00) {
        $frais_livraison = 4.90;
    }
    
    // Emballage cadeau
    $frais_emballage = ($checkout['emballage_cadeau'] ?? false) ? 3.90 : 0;
    
    $total = $sous_total + $frais_livraison + $frais_emballage;
    
    return [
        'sous_total' => round($sous_total, 2),
        'total_items' => $total_items,
        'frais_livraison' => round($frais_livraison, 2),
        'frais_emballage' => round($frais_emballage, 2),
        'total' => round($total, 2),
        'seuil_livraison_gratuite' => 50.00
    ];
}

// ============================================
// FONCTIONS DE NETTOYAGE
// ============================================

/**
 * Nettoie la session utilisateur après commande
 */
function cleanUserSession() {
    unset($_SESSION[SESSION_KEY_PANIER]);
    unset($_SESSION[SESSION_KEY_CHECKOUT]);
    unset($_SESSION[SESSION_KEY_COMMANDE]);
    // On garde SESSION_KEY_PANIER_ID et SESSION_KEY_CLIENT_ID pour l'historique
}

/**
 * Nettoie les flags de session PayPal
 */
function cleanPayPalFlags() {
    unset($_SESSION['paypal_processing']);
    unset($_SESSION['paypal_order_id']);
}

?>