<?php
// db_functions.php - Fonctions utilitaires basées BDD
require_once 'db_config.php';

/**
 * Génère un ID de session unique basé sur l'IP et le user agent
 */
function generateSessionId() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $random = bin2hex(random_bytes(16));
    return hash('sha256', $ip . $userAgent . $random . microtime());
}

/**
 * Récupère ou crée une session en BDD
 */
function getOrCreateDBSession() {
    $db = getDB();
    if (!$db) return null;
    
    // Générer un ID de session unique
    $sessionId = generateSessionId();
    
    try {
        // Vérifier si une session existe déjà pour cet IP/user agent
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            SELECT id_session, id_panier 
            FROM panier_sessions 
            WHERE ip_address = ? 
            AND user_agent LIKE ?
            AND status = 'active'
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY last_activity DESC 
            LIMIT 1
        ");
        $stmt->execute([$ip, substr($userAgent, 0, 100)]);
        $existingSession = $stmt->fetch();
        
        if ($existingSession) {
            // Mettre à jour la dernière activité
            $stmt = $db->prepare("
                UPDATE panier_sessions 
                SET last_activity = NOW() 
                WHERE id_session = ?
            ");
            $stmt->execute([$existingSession['id_session']]);
            return $existingSession['id_session'];
        }
        
        // Créer une nouvelle session
        $stmt = $db->prepare("
            INSERT INTO panier_sessions (
                id_session, ip_address, user_agent, expires_at
            ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ");
        $stmt->execute([$sessionId, $ip, $userAgent]);
        
        return $sessionId;
        
    } catch (PDOException $e) {
        error_log("Erreur getOrCreateDBSession: " . $e->getMessage());
        return $sessionId; // Retourner l'ID généré même en cas d'erreur
    }
}

/**
 * Obtient le panier associé à une session
 */
function getPanierBySession($sessionId) {
    if (!$sessionId) return null;
    
    $db = getDB();
    if (!$db) return null;
    
    try {
        $stmt = $db->prepare("
            SELECT p.id_panier, ps.id_client, p.statut
            FROM panier_sessions ps
            LEFT JOIN panier p ON ps.id_panier = p.id_panier
            WHERE ps.id_session = ? 
            AND ps.status = 'active'
            AND (ps.expires_at IS NULL OR ps.expires_at > NOW())
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur getPanierBySession: " . $e->getMessage());
        return null;
    }
}

/**
 * Crée ou récupère un panier pour une session
 */
function getOrCreatePanierForSession($sessionId) {
    if (!$sessionId) return null;
    
    $db = getDB();
    if (!$db) return null;
    
    try {
        // Vérifier si la session a déjà un panier
        $stmt = $db->prepare("
            SELECT id_panier FROM panier_sessions 
            WHERE id_session = ? AND id_panier IS NOT NULL
        ");
        $stmt->execute([$sessionId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return $existing['id_panier'];
        }
        
        // Créer un nouveau panier
        $stmt = $db->prepare("
            INSERT INTO panier (date_creation, statut) 
            VALUES (NOW(), 'actif')
        ");
        $stmt->execute();
        $panierId = $db->lastInsertId();
        
        // Associer le panier à la session
        $stmt = $db->prepare("
            UPDATE panier_sessions 
            SET id_panier = ?, last_activity = NOW() 
            WHERE id_session = ?
        ");
        $stmt->execute([$panierId, $sessionId]);
        
        return $panierId;
        
    } catch (PDOException $e) {
        error_log("Erreur getOrCreatePanierForSession: " . $e->getMessage());
        return null;
    }
}

/**
 * Récupère les items d'un panier depuis la BDD
 */
function getPanierItemsFromDB($panierId) {
    if (!$panierId) return [];
    
    $db = getDB();
    if (!$db) return [];
    
    try {
        $stmt = $db->prepare("
            SELECT pi.*, p.nom, p.prix_ttc, p.reference, p.quantite_stock, p.statut,
                   COALESCE(
                       (SELECT url_image FROM images_produits 
                        WHERE id_produit = pi.id_produit AND principale = 1 LIMIT 1),
                       'img/default-product.jpg'
                   ) as image
            FROM panier_items pi
            JOIN produits p ON pi.id_produit = p.id_produit
            WHERE pi.id_panier = ?
            ORDER BY pi.date_ajout DESC
        ");
        $stmt->execute([$panierId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur getPanierItemsFromDB: " . $e->getMessage());
        return [];
    }
}

/**
 * Met à jour un item dans le panier BDD
 */
function updatePanierItemInDB($panierId, $produitId, $quantite) {
    if (!$panierId) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        if ($quantite <= 0) {
            // Supprimer l'item
            $stmt = $db->prepare("
                DELETE FROM panier_items 
                WHERE id_panier = ? AND id_produit = ?
            ");
            return $stmt->execute([$panierId, $produitId]);
        }
        
        // Vérifier si l'item existe déjà
        $stmt = $db->prepare("
            SELECT id_item FROM panier_items 
            WHERE id_panier = ? AND id_produit = ?
        ");
        $stmt->execute([$panierId, $produitId]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Mettre à jour la quantité
            $stmt = $db->prepare("
                UPDATE panier_items 
                SET quantite = ?, date_modification = NOW() 
                WHERE id_panier = ? AND id_produit = ?
            ");
            return $stmt->execute([$quantite, $panierId, $produitId]);
        } else {
            // Ajouter un nouvel item
            $produit = getProductDetails($produitId);
            if (!$produit) return false;
            
            $stmt = $db->prepare("
                INSERT INTO panier_items (
                    id_panier, id_produit, quantite, prix_unitaire, date_ajout
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            return $stmt->execute([
                $panierId, 
                $produitId, 
                $quantite, 
                $produit['prix_ttc']
            ]);
        }
    } catch (PDOException $e) {
        error_log("Erreur updatePanierItemInDB: " . $e->getMessage());
        return false;
    }
}

/**
 * Vide un panier dans la BDD
 */
function clearPanierInDB($panierId) {
    if (!$panierId) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("DELETE FROM panier_items WHERE id_panier = ?");
        return $stmt->execute([$panierId]);
    } catch (PDOException $e) {
        error_log("Erreur clearPanierInDB: " . $e->getMessage());
        return false;
    }
}

/**
 * Associe un client à une session
 */
function associateClientToSession($sessionId, $clientId) {
    if (!$sessionId || !$clientId) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("
            UPDATE panier_sessions 
            SET id_client = ?, last_activity = NOW() 
            WHERE id_session = ?
        ");
        return $stmt->execute([$clientId, $sessionId]);
    } catch (PDOException $e) {
        error_log("Erreur associateClientToSession: " . $e->getMessage());
        return false;
    }
}

/**
 * Fusionne le panier d'une session avec celui d'un client
 */
function mergeSessionPanierToClient($sessionId, $clientId) {
    if (!$sessionId || !$clientId) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        $db->beginTransaction();
        
        // Récupérer le panier de la session
        $sessionPanier = getPanierBySession($sessionId);
        if (!$sessionPanier || !$sessionPanier['id_panier']) {
            $db->rollBack();
            return false;
        }
        
        // Récupérer le panier actif du client
        $stmt = $db->prepare("
            SELECT id_panier FROM panier 
            WHERE id_client = ? AND statut = 'actif'
            ORDER BY date_creation DESC 
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        $clientPanier = $stmt->fetch();
        
        if ($clientPanier) {
            // Fusionner les items
            $stmt = $db->prepare("
                INSERT INTO panier_items (id_panier, id_produit, quantite, prix_unitaire, date_ajout)
                SELECT ?, pi.id_produit, pi.quantite, pi.prix_unitaire, NOW()
                FROM panier_items pi
                WHERE pi.id_panier = ?
                ON DUPLICATE KEY UPDATE 
                quantite = quantite + VALUES(quantite),
                date_modification = NOW()
            ");
            $stmt->execute([$clientPanier['id_panier'], $sessionPanier['id_panier']]);
            
            // Supprimer l'ancien panier de session
            $stmt = $db->prepare("DELETE FROM panier WHERE id_panier = ?");
            $stmt->execute([$sessionPanier['id_panier']]);
            
            // Mettre à jour la référence du panier dans la session
            $stmt = $db->prepare("
                UPDATE panier_sessions 
                SET id_panier = ?, id_client = ?, status = 'merged'
                WHERE id_session = ?
            ");
            $stmt->execute([$clientPanier['id_panier'], $clientId, $sessionId]);
        } else {
            // Associer simplement le panier au client
            $stmt = $db->prepare("
                UPDATE panier 
                SET id_client = ? 
                WHERE id_panier = ?
            ");
            $stmt->execute([$clientId, $sessionPanier['id_panier']]);
            
            $stmt = $db->prepare("
                UPDATE panier_sessions 
                SET id_client = ?, status = 'converted'
                WHERE id_session = ?
            ");
            $stmt->execute([$clientId, $sessionId]);
        }
        
        $db->commit();
        return true;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Erreur mergeSessionPanierToClient: " . $e->getMessage());
        return false;
    }
}

/**
 * Valide l'accès à la livraison basé sur le panier BDD
 */
function validateCheckoutAccess($sessionId) {
    if (!$sessionId) return false;
    
    $panier = getPanierBySession($sessionId);
    if (!$panier || !$panier['id_panier']) {
        return false;
    }
    
    // Vérifier si le panier a des items
    $items = getPanierItemsFromDB($panier['id_panier']);
    if (empty($items)) {
        return false;
    }
    
    // Vérifier la disponibilité des produits
    foreach ($items as $item) {
        if ($item['statut'] !== 'actif' || $item['quantite_stock'] < $item['quantite']) {
            return false;
        }
    }
    
    return true;
}

/**
 * Marque une session comme expirée
 */
function expireSession($sessionId) {
    if (!$sessionId) return false;
    
    $db = getDB();
    if (!$db) return false;
    
    try {
        $stmt = $db->prepare("
            UPDATE panier_sessions 
            SET status = 'expired', expires_at = NOW() 
            WHERE id_session = ?
        ");
        return $stmt->execute([$sessionId]);
    } catch (PDOException $e) {
        error_log("Erreur expireSession: " . $e->getMessage());
        return false;
    }
}
?>