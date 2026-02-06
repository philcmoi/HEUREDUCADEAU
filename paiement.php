<?php
session_start();

// Vérifier si l'utilisateur vient de la livraison
if (!isset($_SESSION['adresse_livraison']) && !isset($_SESSION['livraison_data'])) {
    // Debug: Vérifier ce qui est dans la session
    error_log("Accès direct à paiement.php sans données de livraison");
    error_log("Session ID: " . session_id());
    error_log("Variables session disponibles: " . implode(', ', array_keys($_SESSION)));
    
    // Rediriger vers la livraison
    header('Location: livraison_form.php');
    exit();
}

// Connexion à la base de données
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'heureducadeau';
            $username = 'Philippe';
            $password = 'l@99339R';
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur connexion BDD: " . $e->getMessage());
            die("Erreur de connexion à la base de données");
        }
    }
    
    return $pdo;
}

// Vérification flexible du panier
$panier_vide = true;
$panier_items_count = 0;

if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    if (is_array($_SESSION['panier'])) {
        foreach ($_SESSION['panier'] as $item) {
            if (is_array($item) && isset($item['quantite']) && intval($item['quantite']) > 0) {
                $panier_vide = false;
                $panier_items_count += intval($item['quantite']);
            } elseif (is_array($item) && isset($item['quantity']) && intval($item['quantity']) > 0) {
                $panier_vide = false;
                $panier_items_count += intval($item['quantity']);
            }
        }
    }
    
    if ($panier_vide && is_array($_SESSION['panier'])) {
        $temp_count = array_sum($_SESSION['panier']);
        if ($temp_count > 0) {
            $panier_vide = false;
            $panier_items_count = $temp_count;
        }
    }
}

if ($panier_vide) {
    header('Location: panier.php');
    exit();
}

// Récupérer les produits depuis la base de données
function getProduitFromDB($id_produit) {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT p.*, c.nom as categorie_nom 
                FROM produits p 
                LEFT JOIN categories c ON p.id_categorie = c.id_categorie 
                WHERE p.id_produit = :id_produit";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id_produit' => $id_produit]);
        
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur BDD getProduitFromDB: " . $e->getMessage());
        return null;
    }
}

// Calculer les totaux avec connexion BDD
$sous_total = 0;
$items_count = 0;
$articles_panier = [];

// Debug optionnel
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<pre>";
    echo "Structure du panier:\n";
    print_r($_SESSION['panier']);
    echo "\n\nAdresse de livraison:\n";
    print_r($_SESSION['adresse_livraison'] ?? 'Non définie');
    echo "\n\nDonnées livraison:\n";
    print_r($_SESSION['livraison_data'] ?? 'Non définies');
    echo "</pre>";
    exit();
}

// Parcourir le panier
if (isset($_SESSION['panier']) && !empty($_SESSION['panier'])) {
    foreach ($_SESSION['panier'] as $id_produit => $item) {
        $quantite = 0;
        
        // Support pour différents formats de panier
        if (is_array($item)) {
            $quantite = intval($item['quantite'] ?? $item['quantity'] ?? 0);
        } else {
            $quantite = intval($item);
        }
        
        if ($quantite > 0) {
            // Récupérer les informations du produit depuis la BDD
            $produit_info = getProduitFromDB($id_produit);
            
            if ($produit_info) {
                $prix = floatval($produit_info['prix_ttc']);
                $nom = $produit_info['nom'];
                $reference = $produit_info['reference'];
                $categorie = $produit_info['categorie_nom'] ?? '';
                $image = $produit_info['image'] ?? '';
                
                $total_article = $quantite * $prix;
                $sous_total += $total_article;
                $items_count += $quantite;
                
                $articles_panier[] = [
                    'id' => $id_produit,
                    'reference' => $reference,
                    'nom' => $nom,
                    'image' => $image,
                    'categorie' => $categorie,
                    'quantite' => $quantite,
                    'prix' => $prix,
                    'total' => $total_article
                ];
            }
        }
    }
}

// Vérifier si on a bien des articles
if ($items_count == 0) {
    if (isset($_GET['debug_empty'])) {
        echo "<h3>Debug - Panier vide après calcul:</h3>";
        echo "<pre>";
        print_r($_SESSION['panier']);
        echo "</pre>";
        echo "<p>Articles calculés: " . count($articles_panier) . "</p>";
        exit();
    }
    
    header('Location: panier.php');
    exit();
}

// Mettre à jour le panier dans la session
$_SESSION['panier_total'] = $sous_total;
$_SESSION['panier_items_count'] = $items_count;

// Obtenir les frais depuis la session
$mode_livraison = isset($_SESSION['mode_livraison']) ? $_SESSION['mode_livraison'] : 'standard';
$frais_livraison = 0;

// Calculer les frais de livraison selon le mode
switch ($mode_livraison) {
    case 'express':
        $frais_livraison = 9.90;
        break;
    case 'relais':
        $frais_livraison = 4.90;
        break;
    case 'standard':
    default:
        $frais_livraison = 0; // Gratuit pour standard
        break;
}

$frais_emballage = isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau'] ? 3.90 : 0;

// Calculer le total
$total = $sous_total + $frais_livraison + $frais_emballage;

// ID client PayPal - Sandbox ID
$paypal_client_id = "AThDdpC7nCErB8D7uM5K-pjO0qZsyepoQIZ5Qg6H9JqC9gWjs7-WTrXrwqKqbYCLh7v4L4vSGs1sNrKk";

// Fonction pour créer une commande dans la BDD
function createCommandeInDB($commande_data, $articles_panier) {
    try {
        $pdo = getDBConnection();
        
        // Démarrer une transaction
        $pdo->beginTransaction();
        
        // 1. Vérifier/créer le client temporaire
        $client_id = createOrGetTempClient($commande_data['adresse_livraison']['email'], $commande_data['adresse_livraison']);
        
        // 2. Créer l'adresse
        $adresse_id = createAdresse($client_id, $commande_data['adresse_livraison']);
        
        // 3. Créer la commande
        $sql_commande = "INSERT INTO commandes (
            numero_commande, id_client, client_type, id_adresse_livraison, 
            id_adresse_facturation, statut, sous_total, frais_livraison, 
            total_ttc, mode_paiement, statut_paiement, reference_paiement,
            reference_paypal, email_paypal, payer_id, capture_id
        ) VALUES (
            :numero_commande, :id_client, 'guest', :id_adresse_livraison,
            :id_adresse_facturation, :statut, :sous_total, :frais_livraison,
            :total_ttc, :mode_paiement, :statut_paiement, :reference_paiement,
            :reference_paypal, :email_paypal, :payer_id, :capture_id
        )";
        
        $stmt_commande = $pdo->prepare($sql_commande);
        $stmt_commande->execute([
            'numero_commande' => $commande_data['numero'],
            'id_client' => $client_id,
            'id_adresse_livraison' => $adresse_id,
            'id_adresse_facturation' => $adresse_id,
            'statut' => 'en_attente',
            'sous_total' => $commande_data['sous_total'],
            'frais_livraison' => $commande_data['frais_livraison'],
            'total_ttc' => $commande_data['total'],
            'mode_paiement' => $commande_data['methode_paiement'],
            'statut_paiement' => 'paye',
            'reference_paiement' => $commande_data['reference_paiement'],
            'reference_paypal' => $commande_data['reference_paiement'],
            'email_paypal' => $commande_data['adresse_livraison']['email'],
            'payer_id' => $commande_data['payer_id'] ?? null,
            'capture_id' => $commande_data['reference_paiement']
        ]);
        
        $commande_id = $pdo->lastInsertId();
        
        // 4. Ajouter les items de la commande
        foreach ($articles_panier as $article) {
            $sql_item = "INSERT INTO commande_items (
                id_commande, id_produit, reference_produit, nom_produit,
                quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
            ) VALUES (
                :id_commande, :id_produit, :reference_produit, :nom_produit,
                :quantite, :prix_unitaire_ht, :prix_unitaire_ttc, :tva
            )";
            
            // Calculer le prix HT
            $prix_ht = $article['prix'] / 1.20; // 20% TVA
            
            $stmt_item = $pdo->prepare($sql_item);
            $stmt_item->execute([
                'id_commande' => $commande_id,
                'id_produit' => $article['id'],
                'reference_produit' => $article['reference'],
                'nom_produit' => $article['nom'],
                'quantite' => $article['quantite'],
                'prix_unitaire_ht' => $prix_ht,
                'prix_unitaire_ttc' => $article['prix'],
                'tva' => 20.00
            ]);
            
            // 5. Mettre à jour le stock
            $sql_update_stock = "UPDATE produits 
                                SET quantite_stock = quantite_stock - :quantite,
                                    ventes = ventes + :quantite
                                WHERE id_produit = :id_produit";
            
            $stmt_stock = $pdo->prepare($sql_update_stock);
            $stmt_stock->execute([
                'quantite' => $article['quantite'],
                'id_produit' => $article['id']
            ]);
        }
        
        // 6. Créer la transaction
        $sql_transaction = "INSERT INTO transactions (
            numero_transaction, id_commande, id_client, montant,
            methode_paiement, reference_paiement, statut
        ) VALUES (
            :numero_transaction, :id_commande, :id_client, :montant,
            :methode_paiement, :reference_paiement, :statut
        )";
        
        $stmt_trans = $pdo->prepare($sql_transaction);
        $stmt_trans->execute([
            'numero_transaction' => 'TRX-' . time() . '-' . uniqid(),
            'id_commande' => $commande_id,
            'id_client' => $client_id,
            'montant' => $commande_data['total'],
            'methode_paiement' => $commande_data['methode_paiement'],
            'reference_paiement' => $commande_data['reference_paiement'],
            'statut' => 'paye'
        ]);
        
        // 7. Valider la transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'commande_id' => $commande_id,
            'numero_commande' => $commande_data['numero'],
            'client_id' => $client_id
        ];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erreur création commande BDD: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Fonction pour créer ou récupérer un client temporaire
function createOrGetTempClient($email, $adresse_data) {
    try {
        $pdo = getDBConnection();
        
        // Vérifier si le client existe déjà
        $sql_check = "SELECT id_client FROM clients WHERE email = :email";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->execute(['email' => $email]);
        $existing_client = $stmt_check->fetch();
        
        if ($existing_client) {
            return $existing_client['id_client'];
        }
        
        // Créer un nouveau client temporaire
        $sql_insert = "INSERT INTO clients (
            email, mot_de_passe, nom, prenom, telephone,
            is_temporary, newsletter, statut
        ) VALUES (
            :email, :mot_de_passe, :nom, :prenom, :telephone,
            1, 1, 'actif'
        )";
        
        // Générer un mot de passe temporaire
        $temp_password = password_hash(uniqid(), PASSWORD_DEFAULT);
        
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->execute([
            'email' => $email,
            'mot_de_passe' => $temp_password,
            'nom' => $adresse_data['nom'] ?? '',
            'prenom' => $adresse_data['prenom'] ?? '',
            'telephone' => $adresse_data['telephone'] ?? ''
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Erreur création client temporaire: " . $e->getMessage());
        // Retourner un ID temporaire en cas d'erreur
        return 0;
    }
}

// Fonction pour créer une adresse
function createAdresse($client_id, $adresse_data) {
    try {
        $pdo = getDBConnection();
        
        $sql = "INSERT INTO adresses (
            id_client, type_adresse, nom, prenom, adresse,
            complement, code_postal, ville, pays, telephone, principale
        ) VALUES (
            :id_client, 'livraison', :nom, :prenom, :adresse,
            :complement, :code_postal, :ville, :pays, :telephone, 1
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id_client' => $client_id,
            'nom' => $adresse_data['nom'] ?? '',
            'prenom' => $adresse_data['prenom'] ?? '',
            'adresse' => $adresse_data['adresse'] ?? '',
            'complement' => $adresse_data['complement'] ?? '',
            'code_postal' => $adresse_data['code_postal'] ?? '',
            'ville' => $adresse_data['ville'] ?? '',
            'pays' => $adresse_data['pays'] ?? 'France',
            'telephone' => $adresse_data['telephone'] ?? ''
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (PDOException $e) {
        error_log("Erreur création adresse: " . $e->getMessage());
        return 0;
    }
}

// Traitement après retour de PayPal
if (isset($_GET['paymentId']) && isset($_GET['PayerID'])) {
    $payment_id = $_GET['paymentId'];
    $payer_id = $_GET['PayerID'];
    
    // Créer la commande
    $numero_commande = 'CMD-' . date('Ymd') . '-' . strtoupper(uniqid());
    
    $commande = [
        'numero' => $numero_commande,
        'date' => date('Y-m-d H:i:s'),
        'adresse_livraison' => $_SESSION['adresse_livraison'],
        'panier' => $articles_panier,
        'methode_paiement' => 'paypal',
        'reference_paiement' => $payment_id,
        'payer_id' => $payer_id,
        'sous_total' => $sous_total,
        'frais_livraison' => $frais_livraison,
        'frais_emballage' => $frais_emballage,
        'total' => $total,
        'statut' => 'paye'
    ];
    
    // Sauvegarder la commande dans la BDD
    $result = createCommandeInDB($commande, $articles_panier);
    
    if ($result['success']) {
        // Sauvegarder la commande en session
        $_SESSION['commande_en_cours'] = $commande;
        $_SESSION['commande_en_cours']['id_commande'] = $result['commande_id'];
        $_SESSION['commande_en_cours']['client_id'] = $result['client_id'];
        
        // Rediriger vers la confirmation
        header('Location: confirmation.php?cmd=' . $numero_commande . '&ref=' . $payment_id . '&client=' . $result['client_id']);
        exit();
    } else {
        $erreurs[] = "Erreur lors de la création de la commande: " . $result['error'];
    }
}

// Traitement du paiement par carte bancaire
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['methode_paiement']) && $_POST['methode_paiement'] === 'carte') {
    
    // Validation des données de carte
    $numero_carte = str_replace(' ', '', $_POST['numero_carte'] ?? '');
    $date_exp = $_POST['date_expiration'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $titulaire = $_POST['titulaire'] ?? '';
    
    // Validation
    if (strlen($numero_carte) < 16) {
        $erreurs[] = "Numéro de carte invalide";
    }
    
    if (!preg_match('/^\d{2}\/\d{2}$/', $date_exp)) {
        $erreurs[] = "Format de date d'expiration invalide (MM/AA)";
    }
    
    if (strlen($cvv) < 3) {
        $erreurs[] = "Code CVV invalide";
    }
    
    if (empty($titulaire)) {
        $erreurs[] = "Nom du titulaire requis";
    }
    
    if (empty($erreurs)) {
        // Simulation de paiement réussi
        $reference_carte = 'CARD-' . time() . '-' . strtoupper(uniqid());
        
        // Créer la commande
        $numero_commande = 'CMD-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        $commande = [
            'numero' => $numero_commande,
            'date' => date('Y-m-d H:i:s'),
            'adresse_livraison' => $_SESSION['adresse_livraison'],
            'panier' => $articles_panier,
            'methode_paiement' => 'carte',
            'reference_paiement' => $reference_carte,
            'sous_total' => $sous_total,
            'frais_livraison' => $frais_livraison,
            'frais_emballage' => $frais_emballage,
            'total' => $total,
            'statut' => 'paye'
        ];
        
        // Sauvegarder la commande dans la BDD
        $result = createCommandeInDB($commande, $articles_panier);
        
        if ($result['success']) {
            // Sauvegarder la commande en session
            $_SESSION['commande_en_cours'] = $commande;
            $_SESSION['commande_en_cours']['id_commande'] = $result['commande_id'];
            $_SESSION['commande_en_cours']['client_id'] = $result['client_id'];
            
            // Rediriger vers la confirmation
            header('Location: confirmation.php?cmd=' . $numero_commande . '&ref=' . $reference_carte . '&client=' . $result['client_id']);
            exit();
        } else {
            $erreurs[] = "Erreur lors de la création de la commande: " . $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ... VOTRE CSS EXISTANT ... */
        /* Gardez tout votre CSS existant */
        
        .paiement-page {
          padding: 40px 0;
          background: #f8f9fa;
          min-height: calc(100vh - 200px);
        }

        .container {
          max-width: 1200px;
          margin: 0 auto;
          padding: 0 20px;
        }

        .progress-bar {
          display: flex;
          justify-content: center;
          align-items: center;
          margin-bottom: 40px;
          position: relative;
        }

        .progress-step {
          display: flex;
          flex-direction: column;
          align-items: center;
          position: relative;
          z-index: 2;
          padding: 0 30px;
        }

        .step-circle {
          width: 40px;
          height: 40px;
          border-radius: 50%;
          background: #e0e0e0;
          color: #666;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: bold;
          margin-bottom: 10px;
          transition: all 0.3s ease;
        }

        .step-label {
          font-size: 14px;
          color: #666;
          font-weight: 500;
        }

        .progress-step.active .step-circle {
          background: #5a67d8;
          color: white;
          box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
        }

        .progress-step.active .step-label {
          color: #5a67d8;
          font-weight: 600;
        }

        .progress-step.completed .step-circle {
          background: #38a169;
          color: white;
        }

        .progress-step.completed .step-label {
          color: #38a169;
        }

        .progress-line {
          flex: 1;
          height: 3px;
          background: #e0e0e0;
          margin: 0 -20px;
          position: relative;
          top: -20px;
          z-index: 1;
        }

        .progress-line.completed {
          background: #38a169;
        }

        .paiement-container {
          display: grid;
          grid-template-columns: 1fr 350px;
          gap: 40px;
          margin-top: 30px;
        }

        .paiement-section {
          background: white;
          padding: 30px;
          border-radius: 12px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .recap-section {
          background: white;
          padding: 25px;
          border-radius: 12px;
          box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
          height: fit-content;
          position: sticky;
          top: 20px;
        }

        .section-title {
          color: #2d3748;
          margin-bottom: 25px;
          padding-bottom: 15px;
          border-bottom: 2px solid #e2e8f0;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .section-title i {
          color: #5a67d8;
        }

        .adresse-info {
          background: #f7fafc;
          padding: 20px;
          border-radius: 8px;
          margin-bottom: 30px;
        }

        .adresse-line {
          margin-bottom: 5px;
          color: #4a5568;
        }

        .paiement-options {
          margin-bottom: 30px;
        }

        .paiement-option {
          border: 2px solid #e2e8f0;
          border-radius: 8px;
          padding: 20px;
          margin-bottom: 15px;
          cursor: pointer;
          transition: all 0.3s ease;
        }

        .paiement-option:hover {
          border-color: #cbd5e0;
        }

        .paiement-option.selected {
          border-color: #5a67d8;
          background: rgba(90, 103, 216, 0.05);
        }

        .option-header {
          display: flex;
          align-items: center;
          gap: 15px;
          margin-bottom: 15px;
        }

        .option-header img {
          height: 24px;
        }

        .option-body {
          padding-left: 40px;
        }

        .option-body p {
          margin-bottom: 10px;
          color: #718096;
          display: flex;
          align-items: center;
          gap: 10px;
        }

        .option-body p i {
          color: #38a169;
        }

        #paypal-button-container {
          margin-top: 20px;
          min-height: 45px;
        }

        .card-form {
          background: #f7fafc;
          padding: 20px;
          border-radius: 8px;
          margin-top: 20px;
        }

        .form-group {
          margin-bottom: 20px;
        }

        .form-row {
          display: flex;
          gap: 15px;
        }

        .form-row .form-group {
          flex: 1;
        }

        label {
          display: block;
          margin-bottom: 8px;
          font-weight: 600;
          color: #4a5568;
        }

        input {
          width: 100%;
          padding: 12px 15px;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          font-size: 15px;
          transition: all 0.3s ease;
          box-sizing: border-box;
        }

        input:focus {
          outline: none;
          border-color: #5a67d8;
          box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .summary-item {
          display: flex;
          justify-content: space-between;
          margin-bottom: 15px;
          padding-bottom: 15px;
          border-bottom: 1px solid #e2e8f0;
        }

        .summary-item.total {
          border-bottom: none;
          font-size: 18px;
          font-weight: 700;
          color: #2d3748;
          margin-top: 20px;
          padding-top: 20px;
          border-top: 2px solid #e2e8f0;
        }

        .btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          gap: 10px;
          padding: 14px 28px;
          border-radius: 8px;
          font-weight: 600;
          font-size: 16px;
          cursor: pointer;
          transition: all 0.3s ease;
          text-decoration: none;
          border: none;
          width: 100%;
          margin-top: 20px;
        }

        .btn-primary {
          background: #5a67d8;
          color: white;
        }

        .btn-primary:hover {
          background: #4c51bf;
          transform: translateY(-2px);
          box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
        }

        .btn-secondary {
          background: #edf2f7;
          color: #4a5568;
        }

        .btn-secondary:hover {
          background: #e2e8f0;
        }

        .loading {
          display: none;
          text-align: center;
          padding: 20px;
        }

        .loading i {
          font-size: 24px;
          color: #5a67d8;
          animation: spin 1s linear infinite;
        }

        @keyframes spin {
          0% {
            transform: rotate(0deg);
          }
          100% {
            transform: rotate(360deg);
          }
        }

        .securite-note {
          text-align: center;
          margin-top: 20px;
          color: #718096;
          font-size: 14px;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 10px;
        }

        .securite-note i {
          color: #38a169;
        }

        .alert {
          display: flex;
          align-items: flex-start;
          gap: 15px;
          padding: 20px;
          margin-bottom: 30px;
          border-radius: 8px;
          border: 1px solid transparent;
        }

        .alert-danger {
          color: #721c24;
          background-color: #f8d7da;
          border-color: #f5c6cb;
        }

        .alert-danger i {
          color: #721c24;
          font-size: 20px;
        }

        .mode-livraison-info {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 15px;
          background: #f7fafc;
          border-radius: 8px;
          margin-bottom: 20px;
          color: #4a5568;
        }

        .info-box {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 15px;
          background: #d1ecf1;
          border-radius: 8px;
          margin-bottom: 20px;
          color: #0c5460;
          border: 1px solid #bee5eb;
        }

        .articles-list {
          margin-bottom: 30px;
        }

        .article-item {
          display: flex;
          align-items: center;
          gap: 15px;
          padding: 15px 0;
          border-bottom: 1px solid #e2e8f0;
        }

        .article-item:last-child {
          border-bottom: none;
        }

        .article-image {
          width: 60px;
          height: 60px;
          border-radius: 8px;
          overflow: hidden;
          background: #f7fafc;
          display: flex;
          align-items: center;
          justify-content: center;
        }

        .article-details {
          flex: 1;
        }

        .article-nom {
          font-weight: 600;
          color: #2d3748;
          margin-bottom: 5px;
        }

        .article-info {
          font-size: 14px;
          color: #718096;
          margin-bottom: 5px;
        }

        .article-prix {
          font-weight: 600;
          color: #5a67d8;
        }

        .article-total {
          font-weight: 700;
          color: #2d3748;
        }

        .empty-cart {
          text-align: center;
          padding: 40px 20px;
          color: #718096;
        }

        .empty-cart i {
          font-size: 48px;
          margin-bottom: 20px;
          color: #cbd5e0;
        }

        @media (max-width: 992px) {
          .paiement-container {
            grid-template-columns: 1fr;
          }

          .progress-bar {
            flex-wrap: wrap;
          }

          .progress-step {
            padding: 0 15px;
            margin-bottom: 20px;
          }

          .progress-line {
            display: none;
          }
        }

        @media (max-width: 768px) {
          .form-row {
            flex-direction: column;
            gap: 0;
          }
          
          .article-item {
            flex-wrap: wrap;
          }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'partials/header.php'; ?>

    <main class="paiement-page">
        <div class="container">
            <!-- Barre de progression -->
            <div class="progress-bar">
                <div class="progress-step completed">
                    <div class="step-circle">1</div>
                    <div class="step-label">Panier</div>
                </div>
                <div class="progress-line completed"></div>
                <div class="progress-step completed">
                    <div class="step-circle">2</div>
                    <div class="step-label">Livraison</div>
                </div>
                <div class="progress-line active"></div>
                <div class="progress-step active">
                    <div class="step-circle">3</div>
                    <div class="step-label">Paiement</div>
                </div>
            </div>

            <?php if (!empty($erreurs)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <strong>Veuillez corriger les erreurs suivantes :</strong>
                        <ul style="margin: 10px 0 0 20px; padding: 0;">
                            <?php foreach ($erreurs as $erreur): ?>
                                <li><?php echo htmlspecialchars($erreur); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($items_count == 0): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-shopping-cart"></i>
                    <div>
                        <strong>Erreur: Panier vide ou inaccessible</strong>
                        <p>Le système a détecté que votre panier est vide ou contient des données invalides.</p>
                        <p>Vous allez être redirigé vers le panier dans 5 secondes...</p>
                        <p><a href="panier.php">Cliquez ici pour retourner immédiatement au panier</a></p>
                    </div>
                </div>
                <script>
                    setTimeout(function() {
                        window.location.href = 'panier.php';
                    }, 5000);
                </script>
            <?php else: ?>

            <div class="paiement-container">
                <!-- Section paiement -->
                <div class="paiement-section">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i> Mode de paiement
                    </h2>

                    <!-- Adresse de livraison -->
                    <div class="adresse-info">
                        <?php if (isset($_SESSION['adresse_livraison'])): ?>
                            <?php $adresse = $_SESSION['adresse_livraison']; ?>
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <p class="adresse-line"><strong><?php echo htmlspecialchars(($adresse['prenom'] ?? '') . ' ' . ($adresse['nom'] ?? '')); ?></strong></p>
                                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['adresse'] ?? ''); ?></p>
                                    <?php if (!empty($adresse['complement'])): ?>
                                        <p class="adresse-line"><?php echo htmlspecialchars($adresse['complement']); ?></p>
                                    <?php endif; ?>
                                    <p class="adresse-line"><?php echo htmlspecialchars(($adresse['code_postal'] ?? '') . ' ' . ($adresse['ville'] ?? '')); ?></p>
                                    <p class="adresse-line"><?php echo htmlspecialchars($adresse['pays'] ?? 'France'); ?></p>
                                    <p class="adresse-line"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($adresse['email'] ?? ''); ?></p>
                                    <?php if (!empty($adresse['telephone'])): ?>
                                        <p class="adresse-line"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adresse['telephone']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <a href="livraison_form.php" style="color: #5a67d8; text-decoration: none;">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mode-livraison-info">
                        <i class="fas fa-truck"></i>
                        <strong>Mode de livraison :</strong>
                        <?php 
                        $mode_libelle = 'Standard';
                        if ($mode_livraison === 'express') $mode_libelle = 'Express';
                        elseif ($mode_livraison === 'relais') $mode_libelle = 'Point Relais';
                        echo $mode_libelle;
                        ?>
                        <?php if ($frais_livraison > 0): ?>
                            - <?php echo number_format($frais_livraison, 2, ',', ' '); ?> €
                        <?php else: ?>
                            - Gratuite
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau']): ?>
                        <div class="mode-livraison-info">
                            <i class="fas fa-gift"></i>
                            <strong>Emballage cadeau :</strong> Inclus (+3,90 €)
                        </div>
                    <?php endif; ?>

                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <strong>Paiement 100% sécurisé</strong> - Tous les paiements sont cryptés SSL 256-bit.
                    </div>

                    <!-- Options de paiement -->
                    <div class="paiement-options">
                        <!-- Option PayPal -->
                        <div class="paiement-option selected" id="option-paypal">
                            <div class="option-header">
                                <input type="radio" name="methode_paiement" id="paypal" value="paypal" checked style="display: none;">
                                <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal">
                                <span style="font-weight: 600; color: #2d3748">PayPal</span>
                            </div>
                            <div class="option-body">
                                <p><i class="fas fa-check-circle"></i> Paiement sécurisé</p>
                                <p><i class="fas fa-check-circle"></i> Pas besoin de compte PayPal</p>
                                <p><i class="fas fa-check-circle"></i> Protection acheteur incluse</p>
                                
                                <!-- Container PayPal -->
                                <div id="paypal-button-container"></div>
                            </div>
                        </div>

                        <!-- Option Carte bancaire -->
                        <div class="paiement-option" id="option-carte">
                            <div class="option-header">
                                <input type="radio" name="methode_paiement" id="carte" value="carte" style="display: none;">
                                <i class="fas fa-credit-card" style="font-size: 24px; color: #718096"></i>
                                <span style="font-weight: 600; color: #2d3748">Carte bancaire</span>
                            </div>
                            <div class="option-body">
                                <p>Paiement sécurisé via notre système</p>
                                <div style="display: flex; gap: 15px; margin: 15px 0;">
                                    <i class="fab fa-cc-visa" style="font-size: 32px; color: #1434cb"></i>
                                    <i class="fab fa-cc-mastercard" style="font-size: 32px; color: #eb001b"></i>
                                    <i class="fab fa-cc-amex" style="font-size: 32px; color: #2e77bc"></i>
                                </div>

                                <!-- Formulaire carte bancaire -->
                                <form method="POST" id="carte-form" style="display: none;">
                                    <input type="hidden" name="methode_paiement" value="carte">
                                    
                                    <div class="form-group">
                                        <label for="numero_carte">Numéro de carte</label>
                                        <input type="text" id="numero_carte" name="numero_carte" 
                                               placeholder="1234 5678 9012 3456" maxlength="19"
                                               value="<?php echo isset($_POST['numero_carte']) ? htmlspecialchars($_POST['numero_carte']) : ''; ?>">
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="date_expiration">Date d'expiration</label>
                                            <input type="text" id="date_expiration" name="date_expiration" 
                                                   placeholder="MM/AA" maxlength="5"
                                                   value="<?php echo isset($_POST['date_expiration']) ? htmlspecialchars($_POST['date_expiration']) : ''; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label for="cvv">CVV</label>
                                            <input type="text" id="cvv" name="cvv" 
                                                   placeholder="123" maxlength="3"
                                                   value="<?php echo isset($_POST['cvv']) ? htmlspecialchars($_POST['cvv']) : ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="titulaire">Nom du titulaire</label>
                                        <input type="text" id="titulaire" name="titulaire" 
                                               placeholder="JEAN DUPONT"
                                               value="<?php echo isset($_POST['titulaire']) ? htmlspecialchars($_POST['titulaire']) : ''; ?>">
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-lock"></i> Payer <?php echo number_format($total, 2, ',', ' '); ?> €
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Boutons de navigation -->
                    <div style="display: flex; gap: 15px; margin-top: 40px">
                        <a href="livraison_form.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour à la livraison
                        </a>
                    </div>

                    <!-- Loading -->
                    <div class="loading" id="loading">
                        <i class="fas fa-spinner"></i>
                        <p>Traitement en cours...</p>
                    </div>

                    <!-- Note de sécurité -->
                    <p class="securite-note">
                        <i class="fas fa-shield-alt"></i>
                        Transaction cryptée SSL 256-bit - Vos données bancaires sont protégées
                    </p>
                </div>

                <!-- Récapitulatif -->
                <div class="recap-section">
                    <h3 class="section-title">
                        <i class="fas fa-receipt"></i> Récapitulatif de commande
                    </h3>

                    <!-- Liste des articles du panier -->
                    <div class="articles-list">
                        <?php if (!empty($articles_panier)): ?>
                            <?php foreach ($articles_panier as $index => $article): ?>
                                <div class="article-item">
                                    <div class="article-image">
                                        <?php if (!empty($article['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($article['image']); ?>" alt="<?php echo htmlspecialchars($article['nom']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i class="fas fa-gift" style="color: #718096; font-size: 24px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="article-details">
                                        <div class="article-nom"><?php echo htmlspecialchars($article['nom']); ?></div>
                                        <div class="article-info">
                                            <?php if (!empty($article['categorie'])): ?>
                                                <span><?php echo htmlspecialchars($article['categorie']); ?></span> • 
                                            <?php endif; ?>
                                            <span class="article-quantite">Quantité: <?php echo $article['quantite']; ?></span>
                                        </div>
                                        <div class="article-prix">
                                            <?php echo number_format($article['prix'], 2, ',', ' '); ?> €
                                        </div>
                                    </div>
                                    <div class="article-total">
                                        <?php echo number_format($article['total'], 2, ',', ' '); ?> €
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-cart">
                                <i class="fas fa-shopping-cart"></i>
                                <p>Votre panier est vide</p>
                                <a href="produits.php" class="btn btn-secondary" style="width: auto; padding: 10px 20px; margin-top: 15px;">
                                    <i class="fas fa-shopping-bag"></i> Voir les produits
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="summary-details">
                        <div class="summary-item">
                            <span>Articles (<?php echo $items_count; ?>)</span>
                            <span><?php echo number_format($sous_total, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="summary-item">
                            <span>Livraison</span>
                            <span>
                                <?php if ($frais_livraison == 0): ?>
                                    Gratuite
                                <?php else: ?>
                                    <?php echo number_format($frais_livraison, 2, ',', ' '); ?> €
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($frais_emballage > 0): ?>
                        <div class="summary-item">
                            <span>Emballage cadeau</span>
                            <span><?php echo number_format($frais_emballage, 2, ',', ' '); ?> €</span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-item total">
                            <span>Total TTC</span>
                            <span><?php echo number_format($total, 2, ',', ' '); ?> €</span>
                        </div>
                    </div>

                    <div style="margin-top: 20px; padding: 15px; background: #f7fafc; border-radius: 8px;">
                        <p style="font-size: 12px; color: #718096; margin: 0">
                            <i class="fas fa-info-circle"></i>
                            Après paiement, vous serez redirigé vers la page de confirmation.
                        </p>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypal_client_id; ?>&currency=EUR&intent=capture&enable-funding=card"></script>
    
    <script>
        // Variables globales
        const totalAmount = <?php echo $total; ?>;
        let methodePaiement = 'paypal';
        
        // Configuration PayPal
        paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'paypal'
            },
            
            // Étape 1 : Créer la transaction
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: totalAmount.toFixed(2),
                            currency_code: 'EUR'
                        },
                        description: 'Commande HEURE DU CADEAU'
                    }]
                });
            },
            
            // Étape 2 : Approuver la transaction
            onApprove: function(data, actions) {
                // Afficher le loading
                document.getElementById('loading').style.display = 'block';
                
                // Capturer le paiement
                return actions.order.capture().then(function(details) {
                    console.log('Paiement PayPal réussi:', details);
                    
                    // Rediriger vers la page de traitement
                    window.location.href = 'paiement.php?paymentId=' + details.id + '&PayerID=' + details.payer.payer_id;
                }).catch(function(err) {
                    console.error('Erreur capture PayPal:', err);
                    alert('Erreur lors du traitement du paiement: ' + err.message);
                    document.getElementById('loading').style.display = 'none';
                });
            },
            
            // En cas d'erreur
            onError: function(err) {
                console.error('Erreur PayPal:', err);
                alert('Erreur PayPal: ' + err.message);
                document.getElementById('loading').style.display = 'none';
            },
            
            // Avant de quitter la page
            onCancel: function(data) {
                console.log('Paiement PayPal annulé par l\'utilisateur');
            }
            
        }).render('#paypal-button-container');
        
        // Gestion des options de paiement
        document.querySelectorAll('.paiement-option').forEach(option => {
            option.addEventListener('click', function() {
                // Désélectionner toutes les options
                document.querySelectorAll('.paiement-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Sélectionner l'option cliquée
                this.classList.add('selected');
                
                // Mettre à jour la méthode de paiement
                const input = this.querySelector('input[type="radio"]');
                if (input) {
                    input.checked = true;
                    methodePaiement = input.value;
                    
                    // Afficher/masquer les formulaires appropriés
                    if (methodePaiement === 'paypal') {
                        document.getElementById('paypal-button-container').style.display = 'block';
                        document.getElementById('carte-form').style.display = 'none';
                    } else if (methodePaiement === 'carte') {
                        document.getElementById('paypal-button-container').style.display = 'none';
                        document.getElementById('carte-form').style.display = 'block';
                    }
                }
            });
        });
        
        // Formatage du numéro de carte
        document.getElementById('numero_carte')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/\D/g, '');
            if (value.length > 16) value = value.substr(0, 16);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += ' ';
                formatted += value[i];
            }
            e.target.value = formatted;
        });
        
        // Formatage de la date d'expiration
        document.getElementById('date_expiration')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substr(0, 4);
            
            if (value.length >= 2) {
                value = value.substr(0, 2) + '/' + value.substr(2);
            }
            e.target.value = value;
        });
        
        // Formatage du CVV
        document.getElementById('cvv')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substr(0, 3);
        });
        
        // Validation du formulaire carte
        document.getElementById('carte-form')?.addEventListener('submit', function(e) {
            const numeroCarte = document.getElementById('numero_carte').value.replace(/\s/g, '');
            const dateExp = document.getElementById('date_expiration').value;
            const cvv = document.getElementById('cvv').value;
            const titulaire = document.getElementById('titulaire').value.trim();
            
            let erreurs = [];
            
            if (numeroCarte.length < 16) {
                erreurs.push('Numéro de carte invalide');
            }
            
            if (!/^\d{2}\/\d{2}$/.test(dateExp)) {
                erreurs.push('Format de date d\'expiration invalide (MM/AA)');
            }
            
            if (cvv.length < 3) {
                erreurs.push('Code CVV invalide');
            }
            
            if (!titulaire) {
                erreurs.push('Nom du titulaire requis');
            }
            
            if (erreurs.length > 0) {
                e.preventDefault();
                alert('Veuillez corriger les erreurs suivantes :\n' + erreurs.join('\n'));
                return false;
            }
            
            // Afficher le loading
            document.getElementById('loading').style.display = 'block';
            return true;
        });
        
        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Par défaut, on affiche PayPal
            document.getElementById('option-paypal').click();
            
            // Debug: Afficher les totaux dans la console
            console.log('Total commande:', totalAmount, '€');
            console.log('Articles:', <?php echo $items_count; ?>);
            console.log('Sous-total:', <?php echo $sous_total; ?>);
            console.log('Frais livraison:', <?php echo $frais_livraison; ?>);
            console.log('Frais emballage:', <?php echo $frais_emballage; ?>);
        });
    </script>
</body>
</html>