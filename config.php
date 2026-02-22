<?php
// ============================================
// config.php - Configuration centrale
// ============================================

// --- Configuration BDD ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'heureducadeau');
define('DB_USER', 'Philippe'); // À MODIFIER selon votre configuration
define('DB_PASS', 'l@99339R');      // À MODIFIER selon votre configuration

// --- URLs absolues du site ---
// IMPORTANT: Remplacez par l'URL réelle de votre site
// Pour test en local avec ngrok: https://votre-sous-domaine.ngrok.io
// Pour un serveur en ligne: https://votre-domaine.com
define('SITE_URL', 'http://localhost'); // CHANGEZ-MOI !
define('PAYPAL_RETURN_URL', SITE_URL . '/paiement_paypal.php?success=true');
define('PAYPAL_CANCEL_URL', SITE_URL . '/paiement_paypal.php?cancel=true');

// --- Configuration PayPal ---
define('PAYPAL_CLIENT_ID', 'AUe7uZH9uo6MpEhUD5qUL0B6kqE69b9OZi4XMaR-3RJGtklCXfgnSBmaNMUo1uyMmznhoBG-U0bmynR_');
define('PAYPAL_CLIENT_SECRET', 'EDTCzIliUZi-_Jqxb3MUsTKjaS5Dkl0YKGQrCKy6LN7Gqde6CEmQhMBWtGEo4tbiUVerejXZ06rLP-2S');
define('PAYPAL_MODE', 'sandbox'); // 'sandbox' ou 'live'
define('PAYPAL_API_BASE', (PAYPAL_MODE === 'sandbox') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com');

// --- Clés de session ---
define('SESSION_KEY_PANIER', 'panier');
define('SESSION_KEY_CHECKOUT', 'checkout_data');
define('SESSION_KEY_COMMANDE', 'commande_en_cours');

// ============================================
// FONCTIONS DE CONNEXION BDD
// ============================================
function getPDOConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur de connexion BDD : " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

// ============================================
// FONCTIONS DE SESSION
// ============================================
function session_start_secure() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function cleanUserSession() {
    unset($_SESSION[SESSION_KEY_PANIER]);
    unset($_SESSION[SESSION_KEY_CHECKOUT]);
    unset($_SESSION[SESSION_KEY_COMMANDE]);
}

function getSessionMessages() {
    $messages = $_SESSION['messages'] ?? [];
    unset($_SESSION['messages']);
    return $messages;
}

function clearSessionMessages() {
    unset($_SESSION['messages']);
}

// ============================================
// FONCTIONS PRODUITS ET PANIER
// ============================================
function getProductDetails($productId, $pdo) {
    if (!$pdo) return null;
    try {
        $stmt = $pdo->prepare("SELECT * FROM produits WHERE id_produit = ? AND statut = 'actif'");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        if ($product && !isset($product['image'])) {
            $product['image'] = 'img/default-product.jpg';
        }
        return $product ?: null;
    } catch (Exception $e) {
        error_log("Erreur getProductDetails: " . $e->getMessage());
        return null;
    }
}

function countCartItems() {
    if (!isset($_SESSION[SESSION_KEY_PANIER]) || !is_array($_SESSION[SESSION_KEY_PANIER])) {
        return 0;
    }
    $count = 0;
    foreach ($_SESSION[SESSION_KEY_PANIER] as $item) {
        $count += intval($item['quantite'] ?? 0);
    }
    return $count;
}

function hasValidCart() {
    return countCartItems() > 0;
}

function hasShippingAddress() {
    return isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']) && 
           !empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison']);
}

function checkPaiementAccess() {
    if (!hasValidCart()) {
        $_SESSION['messages'][] = ['type' => 'error', 'message' => 'Votre panier est vide.'];
        header('Location: panier.html');
        exit;
    }
    if (!hasShippingAddress()) {
        $_SESSION['messages'][] = ['type' => 'error', 'message' => 'Veuillez renseigner votre adresse de livraison.'];
        header('Location: livraison_form.php');
        exit;
    }
}

function calculerTotauxPanier($panier_details, $checkout_data = []) {
    $sous_total = 0;
    $total_items = 0;
    
    foreach ($panier_details as $item) {
        $sous_total += floatval($item['prix_total'] ?? 0);
        $total_items += intval($item['quantite'] ?? 0);
    }
    
    $seuil_gratuit = 50;
    $frais_livraison = ($sous_total < $seuil_gratuit) ? 4.90 : 0;
    $frais_emballage = ($checkout_data['emballage_cadeau'] ?? false) ? 2.50 : 0;
    $total = $sous_total + $frais_livraison + $frais_emballage;
    
    return [
        'sous_total' => $sous_total,
        'frais_livraison' => $frais_livraison,
        'frais_emballage' => $frais_emballage,
        'total' => $total,
        'total_items' => $total_items,
        'seuil_livraison_gratuite' => $seuil_gratuit
    ];
}

// ============================================
// FONCTIONS CLIENT ET ADRESSE
// ============================================
function ensureClientExists($pdo) {
    if (isset($_SESSION['client_id']) && $_SESSION['client_id'] > 0) {
        return $_SESSION['client_id'];
    }
    
    $adresse = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];
    $email = $adresse['email'] ?? '';
    $nom = $adresse['nom'] ?? '';
    $prenom = $adresse['prenom'] ?? '';
    
    try {
        // Vérifier si le client existe déjà
        $stmt = $pdo->prepare("SELECT id_client FROM clients WHERE email = ?");
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client) {
            $_SESSION['client_id'] = $client['id_client'];
            return $client['id_client'];
        }
        
        // Créer un nouveau client
        $stmt = $pdo->prepare("
            INSERT INTO clients (email, nom, prenom, is_temporary, created_from_session, newsletter) 
            VALUES (?, ?, ?, 1, ?, 1)
        ");
        $stmt->execute([$email, $nom, $prenom, session_id()]);
        $client_id = $pdo->lastInsertId();
        
        $_SESSION['client_id'] = $client_id;
        return $client_id;
        
    } catch (Exception $e) {
        error_log("Erreur ensureClientExists: " . $e->getMessage());
        return 0;
    }
}

function createAddressFromCheckout($pdo, $client_id, $adresse_data) {
    if ($client_id <= 0) return 0;
    
    try {
        // Vérifier si l'adresse existe déjà
        $stmt = $pdo->prepare("
            SELECT id_adresse FROM adresses 
            WHERE id_client = ? AND adresse = ? AND code_postal = ? AND ville = ?
        ");
        $stmt->execute([
            $client_id,
            $adresse_data['adresse'] ?? '',
            $adresse_data['code_postal'] ?? '',
            $adresse_data['ville'] ?? ''
        ]);
        $adresse = $stmt->fetch();
        
        if ($adresse) {
            return $adresse['id_adresse'];
        }
        
        // Créer une nouvelle adresse
        $stmt = $pdo->prepare("
            INSERT INTO adresses 
            (id_client, type_adresse, nom, prenom, societe, adresse, complement, 
             code_postal, ville, pays, telephone, principale)
            VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $client_id,
            $adresse_data['nom'] ?? '',
            $adresse_data['prenom'] ?? '',
            $adresse_data['societe'] ?? null,
            $adresse_data['adresse'] ?? '',
            $adresse_data['complement'] ?? null,
            $adresse_data['code_postal'] ?? '',
            $adresse_data['ville'] ?? '',
            $adresse_data['pays'] ?? 'France',
            $adresse_data['telephone'] ?? null,
            1 // principale
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        error_log("Erreur createAddressFromCheckout: " . $e->getMessage());
        return 0;
    }
}