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

// Lire les données JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Déterminer l'action
$action = $_GET['action'] ?? $data['action'] ?? $_POST['action'] ?? '';

// Vérifier si le panier existe
if (!isset($_SESSION['panier'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Votre panier est vide',
        'redirect' => 'panier.html'
    ]);
    exit;
}

// Vérifier si le panier a des articles
if (empty($_SESSION['panier']['items'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Votre panier est vide',
        'redirect' => 'panier.html'
    ]);
    exit;
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
    
    // Sauvegarder dans la session
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
            'mode' => $data['mode_livraison'] ?? 'standard',
            'frais' => $data['frais_livraison'] ?? 0,
            'date_estimee' => date('Y-m-d', strtotime('+3 days'))
        ],
        'emballage_cadeau' => $data['emballage_cadeau'] ?? false,
        'frais_emballage' => ($data['emballage_cadeau'] ?? false) ? 3.90 : 0,
        'date_sauvegarde' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode([
        'success' => true,
        'message' => 'Adresse sauvegardée',
        'commande' => $_SESSION['commande']
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
    
    try {
        $pdo->beginTransaction();
        
        // 1. Créer le client (temporaire ou existant)
        $email = $_SESSION['commande']['adresse_livraison']['email'];
        $clientId = null;
        
        // Vérifier si le client existe
        $sqlCheckClient = "SELECT id_client FROM clients WHERE email = ?";
        $stmtCheck = $pdo->prepare($sqlCheckClient);
        $stmtCheck->execute([$email]);
        $existingClient = $stmtCheck->fetch();
        
        if ($existingClient) {
            $clientId = $existingClient['id_client'];
        } else {
            // Créer un client temporaire
            $sqlClient = "INSERT INTO clients (
                email, nom, prenom, telephone, is_temporary, 
                created_from_session, statut, date_inscription
            ) VALUES (?, ?, ?, ?, 1, ?, 'actif', NOW())";
            
            $stmtClient = $pdo->prepare($sqlClient);
            $adresse = $_SESSION['commande']['adresse_livraison'];
            $stmtClient->execute([
                $email,
                $adresse['nom'],
                $adresse['prenom'],
                $adresse['telephone'],
                session_id()
            ]);
            
            $clientId = $pdo->lastInsertId();
        }
        
        // 2. Créer l'adresse de livraison
        $sqlAdresse = "INSERT INTO adresses (
            id_client, type_adresse, nom, prenom, societe, adresse, 
            complement, code_postal, ville, pays, telephone, principale
        ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmtAdresse = $pdo->prepare($sqlAdresse);
        $adresse = $_SESSION['commande']['adresse_livraison'];
        $stmtAdresse->execute([
            $clientId,
            $adresse['nom'],
            $adresse['prenom'],
            $adresse['societe'],
            $adresse['adresse'],
            $adresse['complement'],
            $adresse['code_postal'],
            $adresse['ville'],
            $adresse['pays'],
            $adresse['telephone']
        ]);
        
        $adresseId = $pdo->lastInsertId();
        
        // 3. Générer numéro de commande
        $numeroCommande = 'CMD-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // 4. Créer la commande
        $sous_total = $_SESSION['panier']['total'] ?? 0;
        $frais_livraison = $_SESSION['commande']['livraison']['frais'] ?? 0;
        $frais_emballage = $_SESSION['commande']['frais_emballage'] ?? 0;
        $total_ttc = $sous_total + $frais_livraison + $frais_emballage;
        
        $sqlCommande = "INSERT INTO commandes (
            numero_commande, id_client, id_adresse_livraison, id_adresse_facturation,
            statut, sous_total, frais_livraison, reduction, total_ttc,
            mode_paiement, statut_paiement, instructions, date_commande
        ) VALUES (?, ?, ?, ?, 'en_attente', ?, ?, 0, ?, 'paypal', 'en_attente', ?, NOW())";
        
        $instructions = $_SESSION['commande']['emballage_cadeau'] ? 'Emballage cadeau demandé' : '';
        
        $stmtCommande = $pdo->prepare($sqlCommande);
        $stmtCommande->execute([
            $numeroCommande,
            $clientId,
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
            $sqlProduit = "SELECT prix_ht, tva FROM produits WHERE id_produit = ?";
            $stmtProduit = $pdo->prepare($sqlProduit);
            $stmtProduit->execute([$item['id_produit']]);
            $produit = $stmtProduit->fetch();
            
            if ($produit) {
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
        
        $pdo->commit();
        
        // Sauvegarder la commande dans la session pour la confirmation
        $_SESSION['commande_confirmée'] = [
            'numero_commande' => $numeroCommande,
            'commande_id' => $commandeId,
            'total' => $total_ttc,
            'date' => date('Y-m-d H:i:s'),
            'client_email' => $email
        ];
        
        // Vider le panier
        $_SESSION['panier'] = [
            'items' => [],
            'count' => 0,
            'total' => 0.00,
            'created' => time()
        ];
        
        echo json_encode([
            'success' => true,
            'message' => 'Commande créée avec succès',
            'commande' => [
                'numero' => $numeroCommande,
                'id' => $commandeId,
                'total' => number_format($total_ttc, 2, '.', ''),
                'date' => date('Y-m-d H:i:s')
            ],
            'redirect' => 'confirmation.html?cmd=' . $numeroCommande
        ]);
        
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

// ACTION NON RECONNUE
echo json_encode([
    'success' => false,
    'message' => 'Action non reconnue',
    'received_action' => $action
]);
?>