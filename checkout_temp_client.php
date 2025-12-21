<?php
// checkout_temp_client.php
require_once 'config/database.php';
require_once 'classes/TempClientManager.php';
session_start();

class TempCheckoutHandler {
    private $db;
    private $tempManager;
    
    public function __construct($db) {
        $this->db = $db;
        $this->tempManager = new TempClientManager($db);
    }
    
    /**
     * Traite le checkout pour un client temporaire
     */
    public function processTempCheckout($checkoutData) {
        try {
            $this->db->beginTransaction();
            
            // 1. Récupérer ou créer client temporaire
            $clientId = $this->tempManager->getOrCreateTempClient();
            if (!$clientId) {
                throw new Exception("Impossible de créer le client temporaire");
            }
            
            // 2. Créer les adresses
            $addressId = $this->createAddress($clientId, $checkoutData['address']);
            
            // 3. Créer la commande
            $orderId = $this->createOrder($clientId, $addressId, $checkoutData);
            
            // 4. Ajouter les items de commande
            $this->addOrderItems($orderId, $checkoutData['cart']);
            
            // 5. Mettre à jour le stock
            $this->updateStock($checkoutData['cart']);
            
            // 6. Proposer la conversion
            $this->offerConversion($clientId, $checkoutData);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'client_id' => $clientId,
                'is_temporary' => true,
                'offer_conversion' => true
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur checkout temporaire: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Crée une adresse pour le client temporaire
     */
    private function createAddress($clientId, $addressData) {
        $sql = "INSERT INTO adresses (id_client, type_adresse, nom, prenom, adresse, 
                code_postal, ville, pays, telephone, principale, date_creation)
                VALUES (:client_id, 'livraison', :nom, :prenom, :adresse, 
                :code_postal, :ville, :pays, :telephone, 1, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'client_id' => $clientId,
            'nom' => $addressData['nom'],
            'prenom' => $addressData['prenom'],
            'adresse' => $addressData['adresse'],
            'code_postal' => $addressData['code_postal'],
            'ville' => $addressData['ville'],
            'pays' => $addressData['pays'] ?? 'France',
            'telephone' => $addressData['telephone']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Crée la commande
     */
    private function createOrder($clientId, $addressId, $checkoutData) {
        // Calculer les totaux
        $subtotal = $this->calculateSubtotal($checkoutData['cart']);
        $shipping = $this->calculateShipping($subtotal);
        $total = $subtotal + $shipping - ($checkoutData['discount'] ?? 0);
        
        $sql = "INSERT INTO commandes (id_client, client_type, id_adresse_livraison, 
                id_adresse_facturation, statut, sous_total, frais_livraison, 
                reduction, total_ttc, mode_paiement, statut_paiement, date_commande)
                VALUES (:client_id, 'guest', :address_id, :address_id, 'en_attente', 
                :subtotal, :shipping, :discount, :total, :payment_method, 
                'en_attente', NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'client_id' => $clientId,
            'address_id' => $addressId,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $checkoutData['discount'] ?? 0,
            'total' => $total,
            'payment_method' => $checkoutData['payment_method']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Ajoute les items à la commande
     */
    private function addOrderItems($orderId, $cartItems) {
        foreach ($cartItems as $item) {
            // Récupérer les infos produit
            $product = $this->getProductInfo($item['product_id']);
            
            $sql = "INSERT INTO commande_items (id_commande, id_produit, reference_produit, 
                    nom_produit, quantite, prix_unitaire_ht, prix_unitaire_ttc, tva)
                    VALUES (:order_id, :product_id, :reference, :nom, :quantity, 
                    :price_ht, :price_ttc, :tva)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'reference' => $product['reference'],
                'nom' => $product['nom'],
                'quantity' => $item['quantity'],
                'price_ht' => $product['prix_ht'],
                'price_ttc' => $product['prix_ttc'],
                'tva' => $product['tva']
            ]);
        }
    }
    
    /**
     * Met à jour le stock
     */
    private function updateStock($cartItems) {
        foreach ($cartItems as $item) {
            $sql = "UPDATE produits SET 
                    quantite_stock = quantite_stock - :quantity,
                    ventes = ventes + :quantity
                    WHERE id_produit = :product_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'quantity' => $item['quantity'],
                'product_id' => $item['product_id']
            ]);
        }
    }
    
    /**
     * Propose la conversion après commande
     */
    private function offerConversion($clientId, $checkoutData) {
        // Stocker les données pour la conversion future
        $_SESSION['temp_checkout_data'] = [
            'client_id' => $clientId,
            'email' => $checkoutData['email'] ?? null,
            'nom' => $checkoutData['address']['nom'],
            'prenom' => $checkoutData['address']['prenom'],
            'telephone' => $checkoutData['address']['telephone']
        ];
    }
    
    /**
     * Calcule le sous-total
     */
    private function calculateSubtotal($cartItems) {
        $total = 0;
        foreach ($cartItems as $item) {
            $product = $this->getProductInfo($item['product_id']);
            $total += $product['prix_ttc'] * $item['quantity'];
        }
        return $total;
    }
    
    /**
     * Calcule les frais de port
     */
    private function calculateShipping($subtotal) {
        $config = $this->getConfig();
        if ($subtotal >= $config['seuil_livraison_gratuite']) {
            return 0;
        }
        return $config['frais_livraison'];
    }
    
    /**
     * Récupère les infos produit
     */
    private function getProductInfo($productId) {
        $sql = "SELECT * FROM produits WHERE id_produit = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Récupère la configuration
     */
    private function getConfig() {
        $sql = "SELECT cle, valeur FROM configuration WHERE cle IN ('frais_livraison', 'seuil_livraison_gratuite')";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        return [
            'frais_livraison' => floatval($results['frais_livraison'] ?? 4.90),
            'seuil_livraison_gratuite' => floatval($results['seuil_livraison_gratuite'] ?? 50.00)
        ];
    }
}

// Traitement du checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'config/database.php';
    
    $checkoutHandler = new TempCheckoutHandler($db);
    $data = json_decode(file_get_contents('php://input'), true);
    
    $result = $checkoutHandler->processTempCheckout($data);
    
    echo json_encode($result);
}
?>