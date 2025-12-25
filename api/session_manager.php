<?php
// session_manager.php - Gestionnaire de session basé BDD
require_once 'db_functions.php';

class DBSessionManager {
    private $sessionId;
    private $panierId;
    private $clientId;
    
    public function __construct() {
        $this->initSession();
    }
    
    private function initSession() {
        // Désactiver les sessions PHP traditionnelles
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // Récupérer l'ID de session depuis un paramètre GET/POST ou cookie
        $this->sessionId = $_GET['session_id'] ?? $_POST['session_id'] ?? $_COOKIE['db_session_id'] ?? null;
        
        if (!$this->sessionId || !$this->validateSession($this->sessionId)) {
            // Créer une nouvelle session BDD
            $this->sessionId = getOrCreateDBSession();
            
            // Stocker l'ID dans un cookie (optionnel, peut être passé en paramètre)
            if (!headers_sent()) {
                setcookie('db_session_id', $this->sessionId, [
                    'expires' => time() + (24 * 3600),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]);
            }
        }
        
        // Récupérer les infos du panier
        $panierInfo = getPanierBySession($this->sessionId);
        if ($panierInfo) {
            $this->panierId = $panierInfo['id_panier'];
            $this->clientId = $panierInfo['id_client'];
        } else {
            $this->panierId = getOrCreatePanierForSession($this->sessionId);
        }
    }
    
    private function validateSession($sessionId) {
        $db = getDB();
        if (!$db) return false;
        
        try {
            $stmt = $db->prepare("
                SELECT 1 FROM panier_sessions 
                WHERE id_session = ? 
                AND status = 'active'
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$sessionId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Erreur validateSession: " . $e->getMessage());
            return false;
        }
    }
    
    public function getSessionId() {
        return $this->sessionId;
    }
    
    public function getPanierId() {
        return $this->panierId;
    }
    
    public function getClientId() {
        return $this->clientId;
    }
    
    public function setClientId($clientId) {
        if ($clientId && $this->sessionId) {
            $result = associateClientToSession($this->sessionId, $clientId);
            if ($result) {
                $this->clientId = $clientId;
            }
            return $result;
        }
        return false;
    }
    
    public function mergeToClient($clientId) {
        if ($clientId && $this->sessionId) {
            return mergeSessionPanierToClient($this->sessionId, $clientId);
        }
        return false;
    }
    
    public function getPanierItems() {
        return getPanierItemsFromDB($this->panierId);
    }
    
    public function updatePanierItem($produitId, $quantite) {
        return updatePanierItemInDB($this->panierId, $produitId, $quantite);
    }
    
    public function clearPanier() {
        return clearPanierInDB($this->panierId);
    }
    
    public function validateCheckout() {
        return validateCheckoutAccess($this->sessionId);
    }
    
    public function expire() {
        return expireSession($this->sessionId);
    }
}

// Initialiser le gestionnaire global
$sessionManager = new DBSessionManager();
?>