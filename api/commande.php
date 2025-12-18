<?php
// api/commande.php - API UNIFIÉE POUR LE FLUX DE COMMANDE
session_start();

// Headers CORS
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

// Connexion BDD (même que panier.php)
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=heureducadeau;charset=utf8",
                "Philippe",
                "l@99339R",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            error_log("Erreur BDD commande: " . $e->getMessage());
            return false;
        }
    }
    
    return $pdo;
}

// Fonction pour trouver ou créer client temporaire (identique à clients.php)
function getOrCreateTempClient($email, $clientData) {
    $pdo = getPDOConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT id_client, is_temporary, statut, nom, prenom 
                FROM clients 
                WHERE email = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $client = $stmt->fetch();
        
        if ($client) {
            // Mettre à jour si informations manquantes
            if (empty($client['nom']) && !empty($clientData['nom'])) {
                $sqlUpdate = "UPDATE clients SET 
                             nom = ?, prenom = ?, telephone = ?
                             WHERE id_client = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdate);
                $stmtUpdate->execute([
                    $clientData['nom'] ?? '',
                    $clientData['prenom'] ?? '',
                    $clientData['telephone'] ?? '',
                    $client['id_client']
                ]);
            }
            
            return [
                'id' => $client['id_client'],
                'is_temporary' => $client['is_temporary'],
                'action' => 'existing'
            ];
        } else {
            $sqlInsert = "INSERT INTO clients (
                email, nom, prenom, telephone, 
                is_temporary, created_from_session, 
                statut, date_inscription, newsletter
            ) VALUES (?, ?, ?, ?, 1, ?, 'actif', NOW(), 0)";
            
            $stmtInsert = $pdo->prepare($sqlInsert);
            $stmtInsert->execute([
                $email,
                $clientData['nom'] ?? '',
                $clientData['prenom'] ?? '',
                $clientData['telephone'] ?? '',
                session_id()
            ]);
            
            return [
                'id' => $pdo->lastInsertId(),
                'is_temporary' => 1,
                'action' => 'created'
            ];
        }
    } catch (PDOException $e) {
        error_log("Erreur getOrCreateTempClient: " . $e->getMessage());
        return false;
    }
}

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer l'action
$action = $_GET['action'] ?? $data['action'] ?? $_POST['action'] ?? '';

// ACTION: VÉRIFIER ADRESSE (NOUVELLE - pour paiement.php)
if ($action === 'check_adresse') {
    $commande = $_SESSION['commande'] ?? null;
    
    if ($commande && isset($commande['adresse_livraison'])) {
        // Récupérer aussi les infos du panier
        $panier = $_SESSION['panier'] ?? null;
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
    
    // Valider email
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
        'is_temporary' => $clientResult['is_temporary']
    ];
    
    // Calculer les frais de livraison
    $frais_livraison = 0;
    $mode_livraison = $data['mode_livraison'] ?? 'standard';
    
    switch ($mode_livraison) {
        case 'express':
            $frais_livraison = 9.90;
            break;
        case 'relais':
            $frais_livraison = 4.90;
            break;
        default:
            $frais_livraison = 0; // standard = gratuit
    }
    
    // 2. Sauvegarder l'adresse dans la session
    $_SESSION['commande'] = [
        'adresse_livraison' => [
            'nom' => htmlspecialchars($data['nom']),
            'prenom' => htmlspecialchars($data['prenom']),
            'email' => htmlspecialchars($data['email']),
            'adresse' => htmlspecialchars($data['adresse']),
            'complement' => htmlspecialchars($data['complement'] ?? ''),
            'code_postal' => htmlspecialchars($data['code_postal']),
            'ville' => htmlspecialchars($data['ville']),
            'pays' => htmlspecialchars($data['pays'] ?? 'France'),
            'telephone' => htmlspecialchars($data['telephone'] ?? ''),
            'societe' => htmlspecialchars($data['societe'] ?? '')
        ],
        'livraison' => [
            'mode' => $mode_livraison,
            'frais' => $frais_livraison,
            'date_estimee' => date('Y-m-d', strtotime('+3 days'))
        ],
        'emballage_cadeau' => $data['emballage_cadeau'] ?? false,
        'frais_emballage' => ($data['emballage_cadeau'] ?? false) ? 3.90 : 0,
        'date_sauvegarde' => date('Y-m-d H:i:s'),
        'client_id' => $clientResult['id']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Adresse sauvegardée',
        'commande' => $_SESSION['commande'],
        'client' => [
            'id' => $clientResult['id'],
            'is_temporary' => $clientResult['is_temporary'],
            'action' => $clientResult['action']
        ],
        'redirect' => 'paiement.php' // AJOUT: Redirection automatique
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
        ]
    ]);
    exit;
}

// ACTION: CRÉER COMMANDE EN BDD
if ($action === 'create_commande') {
    $pdo = getPDOConnection();
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
    
    try {
        $pdo->beginTransaction();
        
        $clientId = $_SESSION['client_temp']['id'];
        $email = $_SESSION['commande']['adresse_livraison']['email'];
        $adresseData = $_SESSION['commande']['adresse_livraison'];
        
        // 1. Vérifier/Créer l'adresse en BDD
        $sqlCheckAdresse = "SELECT id_adresse FROM adresses 
                           WHERE id_client = ? AND principale = 1";
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
                complement, code_postal, ville, pays, telephone, principale
            ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
            
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
        
        // 2. Générer numéro de commande
        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // 3. Calculer les totaux
        $sous_total = $_SESSION['panier']['total'] ?? 0;
        $frais_livraison = $_SESSION['commande']['livraison']['frais'] ?? 0;
        $frais_emballage = $_SESSION['commande']['frais_emballage'] ?? 0;
        $total_ttc = $sous_total + $frais_livraison + $frais_emballage;
        
        // 4. Créer la commande
        $sqlCommande = "INSERT INTO commandes (
            numero_commande, id_client, client_type,
            id_adresse_livraison, id_adresse_facturation,
            statut, sous_total, frais_livraison, reduction, total_ttc,
            mode_paiement, statut_paiement, instructions, date_commande
        ) VALUES (?, ?, ?, ?, ?, 'en_attente', ?, ?, 0, ?, 'paypal', 'en_attente', ?, NOW())";
        
        $instructions = $_SESSION['commande']['emballage_cadeau'] ? 'Emballage cadeau demandé' : '';
        $clientType = $_SESSION['client_temp']['is_temporary'] == 1 ? 'guest' : 'registered';
        
        $stmtCommande = $pdo->prepare($sqlCommande);
        $stmtCommande->execute([
            $numeroCommande,
            $clientId,
            $clientType,
            $adresseId,
            $adresseId,
            $sous_total,
            $frais_livraison,
            $total_ttc,
            $instructions
        ]);
        
        $commandeId = $pdo->lastInsertId();
        
        // 5. Ajouter les articles de la commande
        foreach ($_SESSION['panier']['items'] as $itemKey => $item) {
            // Récupérer les infos produit depuis BDD
            $sqlProduit = "SELECT prix_ht, tva, quantite_stock FROM produits WHERE id_produit = ?";
            $stmtProduit = $pdo->prepare($sqlProduit);
            $stmtProduit->execute([$item['id_produit']]);
            $produit = $stmtProduit->fetch();
            
            if ($produit) {
                // Vérifier le stock
                if ($produit['quantite_stock'] < $item['quantite']) {
                    throw new Exception("Stock insuffisant pour: " . $item['nom']);
                }
                
                $sqlItem = "INSERT INTO commande_items (
                    id_commande, id_produit, reference_produit, nom_produit,
                    quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                
                $refProduit = 'REF-' . str_pad($item['id_produit'], 6, '0', STR_PAD_LEFT);
                
                $stmtItem = $pdo->prepare($sqlItem);
                $stmtItem->execute([
                    $commandeId,
                    $item['id_produit'],
                    $refProduit,
                    $item['nom'],
                    $item['quantite'],
                    $produit['prix_ht'],
                    $item['prix_unitaire'],
                    $produit['tva']
                ]);
                
                // Mettre à jour le stock
                $sqlUpdateStock = "UPDATE produits 
                                  SET quantite_stock = quantite_stock - ?,
                                      ventes = ventes + ?
                                  WHERE id_produit = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdateStock);
                $stmtUpdate->execute([$item['quantite'], $item['quantite'], $item['id_produit']]);
            }
        }
        
        // 6. Créer une transaction
        $transactionId = 'TRX-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        $sqlTransaction = "INSERT INTO transactions (
            numero_transaction, id_commande, id_client, montant,
            methode_paiement, statut, ip_client, session_id
        ) VALUES (?, ?, ?, ?, 'paypal', 'en_attente', ?, ?)";
        
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
        
        // 9. Préparer la réponse
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
            'redirect' => 'confirmation.html?cmd=' . $numeroCommande
        ];
        
        // 10. Si client temporaire, ajouter un flag pour création de compte
        if ($_SESSION['client_temp']['is_temporary'] == 1) {
            $response['suggest_account'] = true;
            $response['account_creation_url'] = '/api/clients.php?action=convert_to_permanent';
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Erreur création commande: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création de la commande: ' . $e->getMessage()
        ]);
    }
    exit;
}

// ACTION: RÉCUPÉRER ADRESSE POUR PAIEMENT
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
    // Supprimer les données de commande de la session
    unset($_SESSION['commande']);
    unset($_SESSION['commande_confirmée']);
    
    // Conserver le client temporaire si existant
    if (isset($_SESSION['client_temp'])) {
        // Garder seulement les infos client
        $clientTemp = $_SESSION['client_temp'];
        $_SESSION['client_temp'] = $clientTemp;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Commande annulée',
        'redirect' => 'panier.html'
    ]);
    exit;
}

// ACTION NON RECONNUE
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
        'annuler_commande' => 'Annuler commande'
    ]
]);
?>