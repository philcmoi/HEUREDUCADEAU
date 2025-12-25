<?php
// api/commande.php - API UNIFIÉE POUR LE FLUX DE COMMANDE HEURE DU CADEAU
session_start();

// ============================================
// CONFIGURATION HEADERS
// ============================================

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 3600");
header("Content-Type: application/json; charset=UTF-8");

// Gérer OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// INCLUSIONS ET INITIALISATION
// ============================================

require_once 'db_config.php';

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

/**
 * Trouver ou créer un client temporaire
 * @param string $email
 * @param array $clientData
 * @return array|false
 */
function getOrCreateTempClient($email, $clientData) {
    $pdo = getDB();
    if (!$pdo) return false;
    
    try {
        // Vérifier si le client existe déjà
        $sql = "SELECT id_client, is_temporary, statut, nom, prenom, email 
                FROM clients 
                WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client) {
            // Mettre à jour les informations manquantes
            if (empty($client['nom']) && !empty($clientData['nom'])) {
                $sqlUpdate = "UPDATE clients SET 
                             nom = ?, prenom = ?, telephone = ?,
                             date_modification = NOW()
                             WHERE id_client = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    $clientData['nom'] ?? '',
                    $clientData['prenom'] ?? '',
                    $clientData['telephone'] ?? '',
                    $client['id_client']
                ]);
                
                logAction('info', 'Client mis à jour', [
                    'client_id' => $client['id_client'],
                    'email' => $email,
                    'action' => 'update'
                ]);
            }
            
            return [
                'id' => $client['id_client'],
                'is_temporary' => $client['is_temporary'],
                'action' => 'existing',
                'email' => $client['email']
            ];
        } else {
            // Créer un nouveau client temporaire
            $sqlInsert = "INSERT INTO clients (
                email, mot_de_passe, nom, prenom, telephone, 
                is_temporary, newsletter, statut, date_inscription,
                created_from_session
            ) VALUES (?, '', ?, ?, ?, 1, 0, 'actif', NOW(), ?)";
            
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $email,
                $clientData['nom'] ?? '',
                $clientData['prenom'] ?? '',
                $clientData['telephone'] ?? '',
                session_id()
            ]);
            
            $clientId = $pdo->lastInsertId();
            
            logAction('info', 'Client temporaire créé', [
                'client_id' => $clientId,
                'email' => $email,
                'action' => 'create_temp'
            ]);
            
            return [
                'id' => $clientId,
                'is_temporary' => 1,
                'action' => 'created',
                'email' => $email
            ];
        }
    } catch (PDOException $e) {
        error_log("Erreur getOrCreateTempClient: " . $e->getMessage());
        logAction('erreur', 'Erreur création client', [
            'email' => $email,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}

/**
 * Calculer les frais de livraison
 * @param string $mode
 * @param float $sous_total
 * @return float
 */
function calculateShippingFees($mode, $sous_total = 0) {
    $free_threshold = 50.00; // Seuil pour livraison gratuite
    
    if ($sous_total >= $free_threshold && $mode === 'standard') {
        return 0.00;
    }
    
    switch ($mode) {
        case 'express':
            return 9.90;
        case 'relais':
            return 4.90;
        case 'standard':
        default:
            return 0.00;
    }
}

/**
 * Vérifier la disponibilité des produits
 * @param array $panier_items
 * @return array
 */
function checkStockAvailability($panier_items) {
    $pdo = getDB();
    if (!$pdo) {
        return ['available' => false, 'errors' => ['Erreur connexion BDD']];
    }
    
    $errors = [];
    
    foreach ($panier_items as $item) {
        try {
            $sql = "SELECT nom, quantite_stock FROM produits WHERE id_produit = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item['id_produit']]);
            $produit = $stmt->fetch();
            
            if (!$produit) {
                $errors[] = "Produit {$item['nom']} n'existe plus";
                continue;
            }
            
            if ($produit['quantite_stock'] < $item['quantite']) {
                $errors[] = "Stock insuffisant pour {$produit['nom']} (disponible: {$produit['quantite_stock']}, demandé: {$item['quantite']})";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur vérification stock pour {$item['nom']}";
        }
    }
    
    return [
        'available' => empty($errors),
        'errors' => $errors
    ];
}

// ============================================
// TRAITEMENT DE LA REQUÊTE
// ============================================

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer l'action
$action = $_GET['action'] ?? $data['action'] ?? $_POST['action'] ?? '';

// Log de la requête
logAction('info', "API commande - Action: $action", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'action' => $action,
    'session_id' => session_id()
]);

// ============================================
// ACTIONS DE L'API
// ============================================

// ACTION: VÉRIFIER ADRESSE
if ($action === 'check_adresse') {
    $commande = $_SESSION['commande'] ?? null;
    $panier = $_SESSION['panier'] ?? null;
    
    if ($commande && isset($commande['adresse_livraison'])) {
        // Récupérer les infos du panier
        $sous_total = $panier['total'] ?? 0;
        $frais_livraison = $commande['livraison']['frais'] ?? 0;
        $frais_emballage = $commande['frais_emballage'] ?? 0;
        $total = $sous_total + $frais_livraison + $frais_emballage;
        
        echo json_encode([
            'success' => true,
            'adresse' => $commande['adresse_livraison'],
            'totaux' => [
                'sous_total' => number_format($sous_total, 2, '.', ''),
                'frais_livraison' => number_format($frais_livraison, 2, '.', ''),
                'frais_emballage' => number_format($frais_emballage, 2, '.', ''),
                'total' => number_format($total, 2, '.', '')
            ],
            'panier_count' => $panier['count'] ?? 0,
            'message' => 'Adresse trouvée en session'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Aucune adresse enregistrée',
            'code' => 'NO_ADDRESS',
            'redirect' => 'livraison.php',
            'debug' => [
                'commande_exists' => isset($_SESSION['commande']),
                'adresse_exists' => isset($_SESSION['commande']['adresse_livraison']) ?? false,
                'session_id' => session_id()
            ]
        ]);
    }
    exit;
}

// Vérifier si le panier existe (sauf pour certaines actions)
$skip_panier_check = ['check_adresse', 'get_adresse', 'get_commande'];
if (!in_array($action, $skip_panier_check)) {
    if (!isset($_SESSION['panier']) || empty($_SESSION['panier']['items'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Votre panier est vide',
            'redirect' => 'panier.html'
        ]);
        exit;
    }
}

// ACTION: SAUVEGARDER ADRESSE LIVRAISON
if ($action === 'save_adresse') {
    $required = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'email'];
    $missing = [];
    
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        echo json_encode([
            'success' => false,
            'message' => 'Champs manquants: ' . implode(', ', $missing),
            'missing' => $missing
        ]);
        exit;
    }
    
    // Validation email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false,
            'message' => 'Email invalide'
        ]);
        exit;
    }
    
    // 1. Créer ou récupérer le client temporaire
    $clientResult = getOrCreateTempClient($data['email'], [
        'nom' => $data['nom'],
        'prenom' => $data['prenom'],
        'telephone' => $data['telephone'] ?? ''
    ]);
    
    if (!$clientResult) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur création client'
        ]);
        exit;
    }
    
    // Sauvegarder l'ID client dans la session
    $_SESSION['client_temp'] = [
        'id' => $clientResult['id'],
        'email' => $data['email'],
        'is_temporary' => $clientResult['is_temporary'],
        'nom' => $data['nom'],
        'prenom' => $data['prenom']
    ];
    
    // Calculer les frais de livraison
    $mode_livraison = $data['mode_livraison'] ?? 'standard';
    $frais_livraison = calculateShippingFees($mode_livraison, $_SESSION['panier']['total'] ?? 0);
    
    // 2. Sauvegarder l'adresse dans la session
    $_SESSION['commande'] = [
        'adresse_livraison' => [
            'nom' => secureData($data['nom']),
            'prenom' => secureData($data['prenom']),
            'email' => secureData($data['email']),
            'adresse' => secureData($data['adresse']),
            'complement' => secureData($data['complement'] ?? ''),
            'code_postal' => secureData($data['code_postal']),
            'ville' => secureData($data['ville']),
            'pays' => secureData($data['pays'] ?? 'France'),
            'telephone' => secureData($data['telephone'] ?? ''),
            'societe' => secureData($data['societe'] ?? '')
        ],
        'livraison' => [
            'mode' => $mode_livraison,
            'frais' => $frais_livraison,
            'date_estimee' => date('Y-m-d', strtotime('+' . ($mode_livraison === 'express' ? '1' : '3') . ' days'))
        ],
        'emballage_cadeau' => isset($data['emballage_cadeau']) && $data['emballage_cadeau'] == '1',
        'frais_emballage' => (isset($data['emballage_cadeau']) && $data['emballage_cadeau'] == '1') ? 3.90 : 0,
        'instructions' => secureData($data['instructions'] ?? ''),
        'date_sauvegarde' => date('Y-m-d H:i:s'),
        'client_id' => $clientResult['id']
    ];
    
    // Log de l'action
    logAction('info', 'Adresse sauvegardée', [
        'client_id' => $clientResult['id'],
        'email' => $data['email'],
        'mode_livraison' => $mode_livraison
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Adresse sauvegardée',
        'commande' => $_SESSION['commande'],
        'client' => [
            'id' => $clientResult['id'],
            'is_temporary' => $clientResult['is_temporary'],
            'action' => $clientResult['action']
        ],
        'redirect' => 'paiement.php'
    ]);
    exit;
}

// ACTION: RÉCUPÉRER COMMANDE
if ($action === 'get_commande') {
    $commande = $_SESSION['commande'] ?? null;
    $panier = $_SESSION['panier'] ?? null;
    
    if (!$commande || !$panier) {
        echo json_encode([
            'success' => false,
            'message' => 'Pas de commande en cours'
        ]);
        exit;
    }
    
    // Vérifier la disponibilité des stocks
    $stock_check = checkStockAvailability($panier['items'] ?? []);
    
    // Calculer les totaux
    $sous_total = $panier['total'] ?? 0;
    $frais_livraison = $commande['livraison']['frais'] ?? 0;
    $frais_emballage = $commande['frais_emballage'] ?? 0;
    $total = $sous_total + $frais_livraison + $frais_emballage;
    
    echo json_encode([
        'success' => true,
        'commande' => $commande,
        'panier' => [
            'items_count' => $panier['count'] ?? 0,
            'sous_total' => number_format($sous_total, 2, '.', ''),
            'total' => number_format($total, 2, '.', '')
        ],
        'totaux' => [
            'sous_total' => number_format($sous_total, 2, '.', ''),
            'frais_livraison' => number_format($frais_livraison, 2, '.', ''),
            'frais_emballage' => number_format($frais_emballage, 2, '.', ''),
            'total' => number_format($total, 2, '.', '')
        ],
        'stock_check' => $stock_check
    ]);
    exit;
}

// ACTION: CRÉER COMMANDE EN BDD
if ($action === 'create_commande') {
    $pdo = getDB();
    if (!$pdo) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur de connexion à la base de données'
        ]);
        exit;
    }
    
    // Vérifier que nous avons toutes les données nécessaires
    if (!isset($_SESSION['commande']) || !isset($_SESSION['client_temp'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Données de commande incomplètes'
        ]);
        exit;
    }
    
    // Vérifier la disponibilité des stocks
    $stock_check = checkStockAvailability($_SESSION['panier']['items'] ?? []);
    if (!$stock_check['available']) {
        echo json_encode([
            'success' => false,
            'message' => 'Problème de stock',
            'errors' => $stock_check['errors']
        ]);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $clientId = $_SESSION['client_temp']['id'];
        $email = $_SESSION['commande']['adresse_livraison']['email'];
        $adresseData = $_SESSION['commande']['adresse_livraison'];
        
        // 1. Vérifier/Créer l'adresse en BDD
        $sqlCheckAdresse = "SELECT id_adresse FROM adresses 
                           WHERE id_client = ? AND principale = 1 AND type_adresse = 'livraison'";
        $stmtCheck = $pdo->prepare($sqlCheckAdresse);
        $stmtCheck->execute([$clientId]);
        $existingAdresse = $stmtCheck->fetch();
        
        if ($existingAdresse) {
            $adresseId = $existingAdresse['id_adresse'];
            
            // Mettre à jour l'adresse existante
            $sqlUpdateAdresse = "UPDATE adresses SET
                                nom = ?, prenom = ?, societe = ?, adresse = ?,
                                complement = ?, code_postal = ?, ville = ?,
                                pays = ?, telephone = ?, date_creation = NOW()
                                WHERE id_adresse = ?";
            
            $stmtUpdate = $pdo->prepare($sqlUpdateAdresse);
            $stmtUpdate->execute([
                $adresseData['nom'],
                $adresseData['prenom'],
                $adresseData['societe'],
                $adresseData['adresse'],
                $adresseData['complement'],
                $adresseData['code_postal'],
                $adresseData['ville'],
                $adresseData['pays'],
                $adresseData['telephone'],
                $adresseId
            ]);
        } else {
            // Créer une nouvelle adresse
            $sqlAdresse = "INSERT INTO adresses (
                id_client, type_adresse, nom, prenom, societe, adresse, 
                complement, code_postal, ville, pays, telephone, principale, date_creation
            ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
            
            $stmtAdresse = $pdo->prepare($sqlAdresse);
            $stmtAdresse->execute([
                $clientId,
                $adresseData['nom'],
                $adresseData['prenom'],
                $adresseData['societe'],
                $adresseData['adresse'],
                $adresseData['complement'],
                $adresseData['code_postal'],
                $adresseData['ville'],
                $adresseData['pays'],
                $adresseData['telephone']
            ]);
            
            $adresseId = $pdo->lastInsertId();
        }
        
        // 2. Générer numéro de commande (le trigger s'en chargera automatiquement)
        // Le trigger before_commande_insert générera automatiquement le numéro_commande
        
        // 3. Calculer les totaux
        $sous_total = $_SESSION['panier']['total'] ?? 0;
        $frais_livraison = $_SESSION['commande']['livraison']['frais'] ?? 0;
        $frais_emballage = $_SESSION['commande']['frais_emballage'] ?? 0;
        $reduction = 0.00; // À implémenter si vous avez des promotions
        $total_ttc = $sous_total + $frais_livraison + $frais_emballage - $reduction;
        
        // 4. Créer la commande (le trigger générera le numéro)
        $sqlCommande = "INSERT INTO commandes (
            id_client, client_type,
            id_adresse_livraison, id_adresse_facturation,
            statut, sous_total, frais_livraison, reduction, total_ttc,
            mode_paiement, statut_paiement, instructions, date_commande
        ) VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?, ?, 'carte', 'en_attente', ?, NOW())";
        
        $instructions = '';
        if ($_SESSION['commande']['emballage_cadeau']) {
            $instructions .= 'Emballage cadeau demandé';
        }
        if (!empty($_SESSION['commande']['instructions'])) {
            $instructions .= ($instructions ? ' | ' : '') . $_SESSION['commande']['instructions'];
        }
        
        $clientType = $_SESSION['client_temp']['is_temporary'] == 1 ? 'guest' : 'registered';
        
        $stmtCommande = $pdo->prepare($sqlCommande);
        $stmtCommande->execute([
            $clientId,
            $clientType,
            $adresseId,
            $adresseId,
            $sous_total,
            $frais_livraison,
            $reduction,
            $total_ttc,
            $instructions
        ]);
        
        $commandeId = $pdo->lastInsertId();
        
        // Récupérer le numéro de commande généré
        $sqlGetNumero = "SELECT numero_commande FROM commandes WHERE id_commande = ?";
        $stmtGetNumero = $pdo->prepare($sqlGetNumero);
        $stmtGetNumero->execute([$commandeId]);
        $commandeData = $stmtGetNumero->fetch();
        $numeroCommande = $commandeData['numero_commande'];
        
        // 5. Ajouter les articles de la commande
        foreach ($_SESSION['panier']['items'] as $item) {
            // Récupérer les infos produit depuis BDD
            $sqlProduit = "SELECT prix_ht, tva, reference FROM produits WHERE id_produit = ?";
            $stmtProduit = $pdo->prepare($sqlProduit);
            $stmtProduit->execute([$item['id_produit']]);
            $produit = $stmtProduit->fetch();
            
            if ($produit) {
                // Calculer prix TTC
                $prix_unitaire_ttc = $item['prix_unitaire'];
                $prix_unitaire_ht = $prix_unitaire_ttc / (1 + ($produit['tva'] / 100));
                
                // Insérer l'article
                $sqlItem = "INSERT INTO commande_items (
                    id_commande, id_produit, reference_produit, nom_produit,
                    quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmtItem = $pdo->prepare($sqlItem);
                $stmtItem->execute([
                    $commandeId,
                    $item['id_produit'],
                    $produit['reference'],
                    $item['nom'],
                    $item['quantite'],
                    $prix_unitaire_ht,
                    $prix_unitaire_ttc,
                    $produit['tva']
                ]);
                
                // Mettre à jour le stock et ventes
                $sqlUpdateStock = "UPDATE produits 
                                  SET quantite_stock = quantite_stock - ?,
                                      ventes = ventes + ?
                                  WHERE id_produit = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdateStock);
                $stmtUpdate->execute([$item['quantite'], $item['quantite'], $item['id_produit']]);
            }
        }
        
        // 6. Créer une transaction
        $transactionId = 'TRX-' . date('YmdHis') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $sqlTransaction = "INSERT INTO transactions (
            numero_transaction, id_commande, id_client, montant,
            methode_paiement, statut, ip_client, session_id, date_creation
        ) VALUES (?, ?, ?, ?, 'carte', 'en_attente', ?, ?, NOW())";
        
        $stmtTransaction = $pdo->prepare($sqlTransaction);
        $stmtTransaction->execute([
            $transactionId,
            $commandeId,
            $clientId,
            $total_ttc,
            $_SERVER['REMOTE_ADDR'] ?? '',
            session_id()
        ]);
        
        $pdo->commit();
        
        // 7. Sauvegarder la commande dans la session pour la confirmation
        $_SESSION['commande_confirmée'] = [
            'numero_commande' => $numeroCommande,
            'commande_id' => $commandeId,
            'total' => $total_ttc,
            'date' => date('Y-m-d H:i:s'),
            'client_email' => $email,
            'client_id' => $clientId,
            'client_type' => $clientType,
            'transaction_id' => $transactionId
        ];
        
        // 8. Vider le panier
        $_SESSION['panier'] = [
            'items' => [],
            'count' => 0,
            'total' => 0.00,
            'created' => time()
        ];
        
        // 9. Log de la création de commande
        logAction('paiement', 'Commande créée avec succès', [
            'commande_id' => $commandeId,
            'numero_commande' => $numeroCommande,
            'client_id' => $clientId,
            'montant' => $total_ttc,
            'items_count' => count($_SESSION['commande_confirmée'])
        ]);
        
        // 10. Préparer la réponse
        $response = [
            'success' => true,
            'message' => 'Commande créée avec succès',
            'commande' => [
                'numero' => $numeroCommande,
                'id' => $commandeId,
                'total' => number_format($total_ttc, 2, '.', ''),
                'date' => date('Y-m-d H:i:s')
            ],
            'client' => [
                'id' => $clientId,
                'email' => $email,
                'is_temporary' => $_SESSION['client_temp']['is_temporary']
            ],
            'redirect' => 'confirmation.php?cmd=' . urlencode($numeroCommande)
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création commande: " . $e->getMessage());
        
        logAction('erreur', 'Erreur création commande', [
            'error' => $e->getMessage(),
            'client_id' => $clientId ?? null,
            'session_id' => session_id()
        ]);
        
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ACTION: RÉCUPÉRER ADRESSE
if ($action === 'get_adresse') {
    $commande = $_SESSION['commande'] ?? null;
    
    if (!$commande) {
        echo json_encode([
            'success' => false,
            'message' => 'Pas d\'adresse sauvegardée'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'adresse' => $commande['adresse_livraison']
    ]);
    exit;
}

// ACTION: ANNULER COMMANDE
if ($action === 'annuler_commande') {
    // Sauvegarder les infos du client avant suppression
    $client_temp = $_SESSION['client_temp'] ?? null;
    
    // Supprimer les données de commande de la session
    unset($_SESSION['commande']);
    unset($_SESSION['commande_confirmée']);
    
    // Réinitialiser le client temporaire
    if ($client_temp) {
        $_SESSION['client_temp'] = $client_temp;
    }
    
    logAction('info', 'Commande annulée par l\'utilisateur', [
        'session_id' => session_id(),
        'client_id' => $client_temp['id'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Commande annulée',
        'redirect' => 'panier.html'
    ]);
    exit;
}

// ACTION: VALIDER STOCK
if ($action === 'validate_stock') {
    $panier_items = $_SESSION['panier']['items'] ?? [];
    
    if (empty($panier_items)) {
        echo json_encode([
            'success' => false,
            'message' => 'Panier vide'
        ]);
        exit;
    }
    
    $stock_check = checkStockAvailability($panier_items);
    
    echo json_encode([
        'success' => $stock_check['available'],
        'available' => $stock_check['available'],
        'errors' => $stock_check['errors'],
        'message' => $stock_check['available'] ? 'Stock disponible' : 'Problème de stock'
    ]);
    exit;
}

// ACTION NON RECONNUE
logAction('erreur', 'Action non reconnue dans API commande', ['action' => $action]);

echo json_encode([
    'success' => false,
    'message' => 'Action non reconnue',
    'received_action' => $action,
    'available_actions' => [
        'check_adresse' => 'Vérifier si adresse existe',
        'save_adresse' => 'Sauvegarder adresse livraison',
        'get_commande' => 'Récupérer infos commande',
        'create_commande' => 'Créer commande en BDD',
        'get_adresse' => 'Récupérer adresse',
        'annuler_commande' => 'Annuler commande',
        'validate_stock' => 'Valider disponibilité stock'
    ]
]);
?>