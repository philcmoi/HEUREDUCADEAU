<?php
session_start();
require_once 'config.php';

// Connexion à la base de données
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Vérifier si l'utilisateur a un panier
if (!isset($_SESSION['panier_id'])) {
    // Rediriger vers le panier s'il n'y a pas de panier
    header('Location: panier.php');
    exit;
}

$panier_id = $_SESSION['panier_id'];
$client_id = $_SESSION['client_id'] ?? null;
$errors = [];
$success = false;

// Récupérer les informations du panier pour le récapitulatif
$panierTotal = 0;
$totalArticles = 0;
$itemsPanier = [];

if ($panier_id) {
    $sqlPanier = "SELECT pi.*, p.nom, p.reference, p.prix_ttc, 
                  p.personnalisable, p.made_in_france, p.ecologique, p.artisanal,
                  p.description_courte, p.quantite_stock
                  FROM panier_items pi 
                  JOIN produits p ON pi.id_produit = p.id_produit 
                  WHERE pi.id_panier = ?";
    $stmtPanier = $pdo->prepare($sqlPanier);
    $stmtPanier->execute([$panier_id]);
    $itemsPanier = $stmtPanier->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($itemsPanier as $item) {
        $panierTotal += $item['prix_unitaire'] * $item['quantite'];
        $totalArticles += $item['quantite'];
    }
}

// Si le panier est vide, rediriger vers le panier
if ($totalArticles === 0) {
    header('Location: panier.php');
    exit;
}

// Récupérer les adresses existantes si l'utilisateur est connecté
$adresses = [];
if ($client_id) {
    $sqlAdresses = "SELECT * FROM adresses 
                   WHERE id_client = ? 
                   ORDER BY principale DESC, date_creation DESC";
    $stmtAdresses = $pdo->prepare($sqlAdresses);
    $stmtAdresses->execute([$client_id]);
    $adresses = $stmtAdresses->fetchAll(PDO::FETCH_ASSOC);
}

// Récupérer l'adresse par défaut depuis la session ou la base
$adresseLivraison = $_SESSION['adresse_livraison'] ?? null;
if (!$adresseLivraison && $client_id && count($adresses) > 0) {
    // Chercher l'adresse principale
    foreach ($adresses as $adr) {
        if ($adr['principale']) {
            $adresseLivraison = $adr;
            break;
        }
    }
    // Sinon prendre la première
    if (!$adresseLivraison) {
        $adresseLivraison = $adresses[0];
    }
    $_SESSION['adresse_livraison'] = $adresseLivraison;
}

// Traitement du formulaire de livraison et paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des champs de livraison
    $required_fields = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'telephone', 'email'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[$field] = "Ce champ est obligatoire";
        }
    }
    
    // Validation du code postal
    if (!empty($_POST['code_postal']) && !preg_match('/^\d{5}$/', $_POST['code_postal'])) {
        $errors['code_postal'] = "Code postal invalide (5 chiffres requis)";
    }
    
    // Validation du téléphone
    if (!empty($_POST['telephone']) && !preg_match('/^0[1-9][0-9]{8}$/', str_replace(' ', '', $_POST['telephone']))) {
        $errors['telephone'] = "Numéro de téléphone invalide";
    }
    
    // Validation de l'email
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Adresse email invalide";
    }
    
    // Validation des options de livraison
    if (empty($_POST['mode_livraison'])) {
        $errors['mode_livraison'] = "Veuillez sélectionner un mode de livraison";
    }
    
    // Validation du paiement
    if (empty($_POST['mode_paiement'])) {
        $errors['mode_paiement'] = "Veuillez sélectionner un mode de paiement";
    }
    
    // Validation des conditions générales
    if (empty($_POST['conditions'])) {
        $errors['conditions'] = "Vous devez accepter les conditions générales";
    }
    
    // Validation spécifique pour la carte bancaire
    if ($_POST['mode_paiement'] === 'carte') {
        if (empty($_POST['card_number']) || !preg_match('/^\d{16}$/', str_replace(' ', '', $_POST['card_number']))) {
            $errors['card_number'] = "Numéro de carte invalide";
        }
        
        if (empty($_POST['card_expiry']) || !preg_match('/^\d{2}\/\d{2}$/', $_POST['card_expiry'])) {
            $errors['card_expiry'] = "Date d'expiration invalide (format MM/AA)";
        }
        
        if (empty($_POST['card_cvc']) || !preg_match('/^\d{3}$/', $_POST['card_cvc'])) {
            $errors['card_cvc'] = "Cryptogramme invalide";
        }
        
        if (empty($_POST['card_name'])) {
            $errors['card_name'] = "Nom sur la carte requis";
        }
    }
    
    // Si pas d'erreurs, traiter la commande
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // 1. Créer l'adresse de livraison
            $adresseData = [
                'nom' => htmlspecialchars($_POST['nom']),
                'prenom' => htmlspecialchars($_POST['prenom']),
                'societe' => !empty($_POST['societe']) ? htmlspecialchars($_POST['societe']) : null,
                'adresse' => htmlspecialchars($_POST['adresse']),
                'complement' => !empty($_POST['complement']) ? htmlspecialchars($_POST['complement']) : null,
                'code_postal' => htmlspecialchars($_POST['code_postal']),
                'ville' => htmlspecialchars($_POST['ville']),
                'pays' => !empty($_POST['pays']) ? htmlspecialchars($_POST['pays']) : 'France',
                'telephone' => htmlspecialchars($_POST['telephone']),
                'email' => htmlspecialchars($_POST['email'])
            ];
            
            // Si l'utilisateur est connecté, sauvegarder l'adresse
            $id_adresse_livraison = null;
            if ($client_id) {
                $principale = isset($_POST['principale']) ? 1 : 0;
                
                // Si on veut marquer cette adresse comme principale
                if ($principale) {
                    $sqlResetPrincipal = "UPDATE adresses SET principale = 0 WHERE id_client = ?";
                    $stmtReset = $pdo->prepare($sqlResetPrincipal);
                    $stmtReset->execute([$client_id]);
                }
                
                // Insérer la nouvelle adresse
                $sqlInsertAdresse = "INSERT INTO adresses (
                    id_client, type_adresse, nom, prenom, societe, adresse, 
                    complement, code_postal, ville, pays, telephone, email, principale
                ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmtInsert = $pdo->prepare($sqlInsertAdresse);
                $stmtInsert->execute([
                    $client_id,
                    $adresseData['nom'],
                    $adresseData['prenom'],
                    $adresseData['societe'],
                    $adresseData['adresse'],
                    $adresseData['complement'],
                    $adresseData['code_postal'],
                    $adresseData['ville'],
                    $adresseData['pays'],
                    $adresseData['telephone'],
                    $adresseData['email'],
                    $principale
                ]);
                
                $id_adresse_livraison = $pdo->lastInsertId();
                $adresseData['id_adresse'] = $id_adresse_livraison;
            }
            
            // 2. Calculer les frais de livraison
            $frais_livraison = 0;
            $transporteur = 'Standard';
            
            switch ($_POST['mode_livraison']) {
                case 'express':
                    $frais_livraison = 9.90;
                    $transporteur = 'Express (24-48h)';
                    break;
                case 'point_relais':
                    $frais_livraison = 4.90;
                    $transporteur = 'Point Relais';
                    break;
                default:
                    $frais_livraison = 0;
                    $transporteur = 'Standard (3-5 jours)';
            }
            
            // 3. Créer la commande
            $total_ttc = $panierTotal + $frais_livraison;
            
            // Générer un numéro de commande
            $numero_commande = 'CMD-' . date('Ym') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
            
            $sqlCommande = "INSERT INTO commandes (
                numero_commande,
                id_client,
                id_adresse_livraison,
                id_adresse_facturation,
                statut,
                sous_total,
                frais_livraison,
                total_ttc,
                mode_paiement,
                transporteur,
                instructions
            ) VALUES (?, ?, ?, ?, 'en_attente', ?, ?, ?, ?, ?, ?)";
            
            $stmtCommande = $pdo->prepare($sqlCommande);
            $stmtCommande->execute([
                $numero_commande,
                $client_id,
                $id_adresse_livraison,
                $id_adresse_livraison, // Même adresse pour facturation
                $panierTotal,
                $frais_livraison,
                $total_ttc,
                $_POST['mode_paiement'],
                $transporteur,
                !empty($_POST['instructions']) ? htmlspecialchars($_POST['instructions']) : null
            ]);
            
            $id_commande = $pdo->lastInsertId();
            
            // 4. Ajouter les articles de la commande
            foreach ($itemsPanier as $item) {
                // Récupérer la TVA du produit
                $tva = $item['prix_ttc'] - ($item['prix_ttc'] / 1.20);
                
                $sqlItem = "INSERT INTO commande_items (
                    id_commande, id_produit, id_variant, reference_produit,
                    nom_produit, quantite, prix_unitaire_ht, prix_unitaire_ttc, tva
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmtItem = $pdo->prepare($sqlItem);
                $stmtItem->execute([
                    $id_commande,
                    $item['id_produit'],
                    $item['id_variant'],
                    $item['reference'],
                    $item['nom'],
                    $item['quantite'],
                    $item['prix_unitaire'] / 1.20, // Calcul HT
                    $item['prix_unitaire'],
                    $tva
                ]);
                
                // Mettre à jour le stock
                $sqlUpdateStock = "UPDATE produits 
                                  SET quantite_stock = quantite_stock - ?, 
                                      ventes = ventes + ? 
                                  WHERE id_produit = ?";
                $stmtUpdateStock = $pdo->prepare($sqlUpdateStock);
                $stmtUpdateStock->execute([$item['quantite'], $item['quantite'], $item['id_produit']]);
            }
            
            // 5. Créer une transaction de paiement
            if ($_POST['mode_paiement'] === 'carte') {
                // Générer une référence de transaction
                $reference_paiement = 'TXN-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
                
                // Stocker les infos de carte (en pratique, utiliser un service sécurisé comme Stripe)
                // Ici, on ne stocke que la référence et on simule le paiement
                $sqlTransaction = "INSERT INTO transactions (
                    numero_transaction, id_commande, id_client, montant,
                    methode_paiement, reference_paiement, statut,
                    ip_client, user_agent, date_creation
                ) VALUES (?, ?, ?, ?, 'carte', ?, 'paye', ?, ?, NOW())";
                
                $stmtTransaction = $pdo->prepare($sqlTransaction);
                $stmtTransaction->execute([
                    $reference_paiement,
                    $id_commande,
                    $client_id,
                    $total_ttc,
                    $reference_paiement,
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_USER_AGENT']
                ]);
                
                // Mettre à jour le statut de paiement de la commande
                $sqlUpdateCommande = "UPDATE commandes 
                                     SET statut_paiement = 'paye', 
                                         reference_paiement = ?,
                                         date_paiement = NOW()
                                     WHERE id_commande = ?";
                $stmtUpdate = $pdo->prepare($sqlUpdateCommande);
                $stmtUpdate->execute([$reference_paiement, $id_commande]);
            }
            
            // 6. Vider le panier
            $sqlDeletePanierItems = "DELETE FROM panier_items WHERE id_panier = ?";
            $stmtDeleteItems = $pdo->prepare($sqlDeletePanierItems);
            $stmtDeleteItems->execute([$panier_id]);
            
            $sqlDeletePanier = "DELETE FROM panier WHERE id_panier = ?";
            $stmtDeletePanier = $pdo->prepare($sqlDeletePanier);
            $stmtDeletePanier->execute([$panier_id]);
            
            $pdo->commit();
            
            // Sauvegarder les données dans la session pour la confirmation
            $_SESSION['commande_validee'] = [
                'id_commande' => $id_commande,
                'numero_commande' => $numero_commande,
                'total' => $total_ttc,
                'date' => date('d/m/Y H:i')
            ];
            
            // Supprimer l'ID du panier de la session
            unset($_SESSION['panier_id']);
            
            // Rediriger vers la page de confirmation
            header('Location: confirmation.php');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['general'] = "Erreur lors du traitement de la commande : " . $e->getMessage();
        }
    }
}

// Récupérer les paramètres de configuration
$sqlConfig = "SELECT cle, valeur FROM configuration";
$stmtConfig = $pdo->query($sqlConfig);
$config = [];
while ($row = $stmtConfig->fetch(PDO::FETCH_ASSOC)) {
    $config[$row['cle']] = $row['valeur'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison & Paiement - <?php echo htmlspecialchars($config['site_nom'] ?? 'HEURE DU CADEAU'); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/livraison.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <?php include 'header.php'; ?>

    <main class="livraison-page">
        <div class="container">
            <div class="livraison-progress">
                <div class="progress-step completed">
                    <span class="step-number">1</span>
                    <span class="step-text">Panier</span>
                </div>
                <div class="progress-line active"></div>
                <div class="progress-step active">
                    <span class="step-number">2</span>
                    <span class="step-text">Livraison</span>
                </div>
                <div class="progress-line active"></div>
                <div class="progress-step active">
                    <span class="step-number">3</span>
                    <span class="step-text">Paiement</span>
                </div>
            </div>

            <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="checkoutForm" class="checkout-layout">
                <div class="checkout-form">
                    <!-- Section Livraison -->
                    <div class="livraison-form">
                        <h2><i class="fas fa-truck"></i> Adresse de livraison</h2>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope"></i> Email *</label>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? $adresseLivraison['email'] ?? ''); ?>"
                                    class="<?php echo isset($errors['email']) ? 'error' : ''; ?>"
                                    placeholder="exemple@email.com"
                                />
                                <?php if (isset($errors['email'])): ?>
                                <span class="error-message"><?php echo $errors['email']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="nom"><i class="fas fa-user"></i> Nom *</label>
                                <input type="text" id="nom" name="nom" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? $adresseLivraison['nom'] ?? ''); ?>"
                                       class="<?php echo isset($errors['nom']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['nom'])): ?>
                                <span class="error-message"><?php echo $errors['nom']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="prenom"><i class="fas fa-user"></i> Prénom *</label>
                                <input type="text" id="prenom" name="prenom" 
                                       required
                                       value="<?php echo htmlspecialchars($_POST['prenom'] ?? $adresseLivraison['prenom'] ?? ''); ?>"
                                       class="<?php echo isset($errors['prenom']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['prenom'])): ?>
                                <span class="error-message"><?php echo $errors['prenom']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="adresse"
                                ><i class="fas fa-map-marker-alt"></i> Adresse *</label
                            >
                            <input
                                type="text"
                                id="adresse"
                                name="adresse"
                                required
                                value="<?php echo htmlspecialchars($_POST['adresse'] ?? $adresseLivraison['adresse'] ?? ''); ?>"
                                class="<?php echo isset($errors['adresse']) ? 'error' : ''; ?>"
                                placeholder="Numéro et nom de rue"
                            />
                            <?php if (isset($errors['adresse'])): ?>
                            <span class="error-message"><?php echo $errors['adresse']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="complement"
                                ><i class="fas fa-building"></i> Complément d'adresse</label
                            >
                            <input
                                type="text"
                                id="complement"
                                name="complement"
                                value="<?php echo htmlspecialchars($_POST['complement'] ?? $adresseLivraison['complement'] ?? ''); ?>"
                                placeholder="Appartement, étage, etc."
                            />
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="code_postal"
                                    ><i class="fas fa-mailbox"></i> Code postal *</label
                                >
                                <input
                                    type="text"
                                    id="code_postal"
                                    name="code_postal"
                                    required
                                    pattern="[0-9]{5}"
                                    value="<?php echo htmlspecialchars($_POST['code_postal'] ?? $adresseLivraison['code_postal'] ?? ''); ?>"
                                    class="<?php echo isset($errors['code_postal']) ? 'error' : ''; ?>"
                                    placeholder="75001"
                                />
                                <?php if (isset($errors['code_postal'])): ?>
                                <span class="error-message"><?php echo $errors['code_postal']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="ville"><i class="fas fa-city"></i> Ville *</label>
                                <input
                                    type="text"
                                    id="ville"
                                    name="ville"
                                    required
                                    value="<?php echo htmlspecialchars($_POST['ville'] ?? $adresseLivraison['ville'] ?? ''); ?>"
                                    class="<?php echo isset($errors['ville']) ? 'error' : ''; ?>"
                                    placeholder="Paris"
                                />
                                <?php if (isset($errors['ville'])): ?>
                                <span class="error-message"><?php echo $errors['ville']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pays"><i class="fas fa-globe"></i> Pays *</label>
                            <select id="pays" name="pays" required>
                                <option value="France" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? 'France') === 'France' ? 'selected' : ''; ?>>France</option>
                                <option value="Belgique" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Belgique' ? 'selected' : ''; ?>>Belgique</option>
                                <option value="Suisse" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Suisse' ? 'selected' : ''; ?>>Suisse</option>
                                <option value="Luxembourg" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Luxembourg' ? 'selected' : ''; ?>>Luxembourg</option>
                                <option value="autre">Autre pays</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="telephone"
                                ><i class="fas fa-phone"></i> Téléphone *
                            </label>
                            <input
                                type="tel"
                                id="telephone"
                                name="telephone"
                                required
                                value="<?php echo htmlspecialchars($_POST['telephone'] ?? $adresseLivraison['telephone'] ?? ''); ?>"
                                class="<?php echo isset($errors['telephone']) ? 'error' : ''; ?>"
                                pattern="[0-9]{10}"
                                placeholder="0123456789"
                            />
                            <?php if (isset($errors['telephone'])): ?>
                            <span class="error-message"><?php echo $errors['telephone']; ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($client_id): ?>
                        <div class="form-checkbox">
                            <input type="checkbox" id="principale" name="principale" value="1">
                            <label for="principale">
                                <i class="fas fa-star"></i>
                                Définir comme adresse principale
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Options de livraison -->
                    <div class="livraison-section">
                        <h3><i class="fas fa-shipping-fast"></i> Mode de livraison</h3>
                        <div class="livraison-options">
                            <div class="livraison-option <?php echo (!isset($_POST['mode_livraison']) || $_POST['mode_livraison'] === 'standard') ? 'selected' : ''; ?>">
                                <input type="radio" id="standard" name="mode_livraison" value="standard" 
                                       <?php echo (!isset($_POST['mode_livraison']) || $_POST['mode_livraison'] === 'standard') ? 'checked' : ''; ?>>
                                <label for="standard">
                                    <div class="livraison-option-header">
                                        <div class="livraison-option-icon">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div class="livraison-option-title">Livraison Standard</div>
                                        <div class="livraison-option-price">GRATUIT</div>
                                    </div>
                                    <div class="livraison-option-desc">
                                        Livraison en 3-5 jours ouvrés
                                    </div>
                                </label>
                            </div>

                            <div class="livraison-option <?php echo ($_POST['mode_livraison'] ?? '') === 'express' ? 'selected' : ''; ?>">
                                <input type="radio" id="express" name="mode_livraison" value="express"
                                       <?php echo ($_POST['mode_livraison'] ?? '') === 'express' ? 'checked' : ''; ?>>
                                <label for="express">
                                    <div class="livraison-option-header">
                                        <div class="livraison-option-icon">
                                            <i class="fas fa-bolt"></i>
                                        </div>
                                        <div class="livraison-option-title">Livraison Express</div>
                                        <div class="livraison-option-price">9,90 €</div>
                                    </div>
                                    <div class="livraison-option-desc">
                                        Livraison en 24-48h (jours ouvrés)
                                    </div>
                                </label>
                            </div>

                            <div class="livraison-option <?php echo ($_POST['mode_livraison'] ?? '') === 'point_relais' ? 'selected' : ''; ?>">
                                <input type="radio" id="point_relais" name="mode_livraison" value="point_relais"
                                       <?php echo ($_POST['mode_livraison'] ?? '') === 'point_relais' ? 'checked' : ''; ?>>
                                <label for="point_relais">
                                    <div class="livraison-option-header">
                                        <div class="livraison-option-icon">
                                            <i class="fas fa-store"></i>
                                        </div>
                                        <div class="livraison-option-title">Point Relais</div>
                                        <div class="livraison-option-price">4,90 €</div>
                                    </div>
                                    <div class="livraison-option-desc">
                                        Retrait en point relais sous 2-3 jours
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php if (isset($errors['mode_livraison'])): ?>
                        <span class="error-message"><?php echo $errors['mode_livraison']; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Section Paiement -->
                    <div class="paiement-form">
                        <h2><i class="fas fa-credit-card"></i> Moyen de paiement</h2>

                        <div class="paiement-methods">
                            <div class="paiement-method <?php echo (!isset($_POST['mode_paiement']) || $_POST['mode_paiement'] === 'carte') ? 'selected' : ''; ?>">
                                <input type="radio" id="carte" name="mode_paiement" value="carte"
                                       <?php echo (!isset($_POST['mode_paiement']) || $_POST['mode_paiement'] === 'carte') ? 'checked' : ''; ?>>
                                <label for="carte">
                                    <div class="paiement-method-header">
                                        <div class="paiement-method-icon">
                                            <i class="fas fa-credit-card"></i>
                                        </div>
                                        <div class="paiement-method-title">Carte bancaire</div>
                                    </div>
                                    <div class="paiement-method-desc">
                                        Paiement sécurisé par carte Visa, Mastercard ou CB
                                    </div>
                                    
                                    <!-- Détails carte bancaire -->
                                    <div class="card-details <?php echo (!isset($_POST['mode_paiement']) || $_POST['mode_paiement'] === 'carte') ? 'active' : ''; ?>">
                                        <div class="form-group">
                                            <label for="card_number">Numéro de carte *</label>
                                            <input
                                                type="text"
                                                id="card_number"
                                                name="card_number"
                                                value="<?php echo htmlspecialchars($_POST['card_number'] ?? ''); ?>"
                                                placeholder="1234 5678 9012 3456"
                                                maxlength="19"
                                            />
                                            <?php if (isset($errors['card_number'])): ?>
                                            <span class="error-message"><?php echo $errors['card_number']; ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-group">
                                            <label for="card_name">Nom sur la carte *</label>
                                            <input
                                                type="text"
                                                id="card_name"
                                                name="card_name"
                                                value="<?php echo htmlspecialchars($_POST['card_name'] ?? ''); ?>"
                                                placeholder="M. DUPONT Jean"
                                            />
                                            <?php if (isset($errors['card_name'])): ?>
                                            <span class="error-message"><?php echo $errors['card_name']; ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="form-row">
                                            <div class="form-group card-small">
                                                <label for="card_expiry">Date d'expiration *</label>
                                                <input
                                                    type="text"
                                                    id="card_expiry"
                                                    name="card_expiry"
                                                    value="<?php echo htmlspecialchars($_POST['card_expiry'] ?? ''); ?>"
                                                    placeholder="MM/AA"
                                                    maxlength="5"
                                                />
                                                <?php if (isset($errors['card_expiry'])): ?>
                                                <span class="error-message"><?php echo $errors['card_expiry']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="form-group card-small">
                                                <label for="card_cvc">Cryptogramme *</label>
                                                <input
                                                    type="text"
                                                    id="card_cvc"
                                                    name="card_cvc"
                                                    value="<?php echo htmlspecialchars($_POST['card_cvc'] ?? ''); ?>"
                                                    placeholder="123"
                                                    maxlength="3"
                                                />
                                                <?php if (isset($errors['card_cvc'])): ?>
                                                <span class="error-message"><?php echo $errors['card_cvc']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="paiement-method <?php echo ($_POST['mode_paiement'] ?? '') === 'paypal' ? 'selected' : ''; ?>">
                                <input type="radio" id="paypal" name="mode_paiement" value="paypal"
                                       <?php echo ($_POST['mode_paiement'] ?? '') === 'paypal' ? 'checked' : ''; ?>>
                                <label for="paypal">
                                    <div class="paiement-method-header">
                                        <div class="paiement-method-icon">
                                            <i class="fab fa-paypal"></i>
                                        </div>
                                        <div class="paiement-method-title">PayPal</div>
                                    </div>
                                    <div class="paiement-method-desc">
                                        Paiement rapide et sécurisé via votre compte PayPal
                                    </div>
                                </label>
                            </div>

                            <div class="paiement-method <?php echo ($_POST['mode_paiement'] ?? '') === 'virement' ? 'selected' : ''; ?>">
                                <input type="radio" id="virement" name="mode_paiement" value="virement"
                                       <?php echo ($_POST['mode_paiement'] ?? '') === 'virement' ? 'checked' : ''; ?>>
                                <label for="virement">
                                    <div class="paiement-method-header">
                                        <div class="paiement-method-icon">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <div class="paiement-method-title">Virement bancaire</div>
                                    </div>
                                    <div class="paiement-method-desc">
                                        Effectuez un virement après validation de la commande
                                    </div>
                                </label>
                            </div>
                        </div>
                        <?php if (isset($errors['mode_paiement'])): ?>
                        <span class="error-message"><?php echo $errors['mode_paiement']; ?></span>
                        <?php endif; ?>

                        <div class="security-info">
                            <i class="fas fa-lock"></i>
                            <div class="security-text">
                                <strong>Paiement 100% sécurisé</strong><br>
                                Vos informations de paiement sont cryptées et protégées
                            </div>
                        </div>
                    </div>

                    <!-- Conditions générales -->
                    <div class="form-group conditions-section">
                        <div class="form-checkbox">
                            <input type="checkbox" id="conditions" name="conditions" value="1"
                                   <?php echo isset($_POST['conditions']) ? 'checked' : ''; ?>>
                            <label for="conditions" class="conditions-label">
                                J'accepte les <a href="conditions-generales.php">conditions générales de vente</a>
                                et j'ai pris connaissance de la 
                                <a href="confidentialite.php">politique de confidentialité</a>. *
                            </label>
                        </div>
                        <?php if (isset($errors['conditions'])): ?>
                        <span class="error-message"><?php echo $errors['conditions']; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Instructions spéciales -->
                    <div class="form-group">
                        <label for="instructions"><i class="fas fa-sticky-note"></i> Instructions spéciales (optionnel)</label>
                        <textarea id="instructions" name="instructions" rows="3" 
                                  placeholder="Porte de service, interphone, créneau horaire, etc."><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- Colonne de droite : Récapitulatif -->
                <div class="recap-section">
                    <h3><i class="fas fa-receipt"></i> Récapitulatif de commande</h3>
                    
                    <div class="recap-items">
                        <?php foreach ($itemsPanier as $item): ?>
                        <div class="recap-item">
                            <div class="recap-item-info">
                                <div class="recap-item-image">
                                    <!-- Image du produit (à implémenter avec images_produits) -->
                                    <i class="fas fa-gift" style="color: #5a67d8;"></i>
                                </div>
                                <div class="recap-item-details">
                                    <div class="recap-item-name"><?php echo htmlspecialchars($item['nom']); ?></div>
                                    <div class="recap-item-price"><?php echo number_format($item['prix_unitaire'], 2, ',', ' '); ?> €</div>
                                    <div class="recap-item-reference">Réf: <?php echo htmlspecialchars($item['reference']); ?></div>
                                </div>
                            </div>
                            <div class="recap-item-quantity">x<?php echo $item['quantite']; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="recap-totals">
                        <div class="recap-row">
                            <span>Sous-total</span>
                            <span id="sousTotal"><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="recap-row">
                            <span>Livraison</span>
                            <span class="free-shipping" id="shippingCost">GRATUIT</span>
                        </div>
                        <div class="recap-row total">
                            <span>Total TTC</span>
                            <span id="totalAmount"><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                        </div>
                    </div>

                    <div class="order-info">
                        <h4><i class="fas fa-info-circle"></i> Informations importantes</h4>
                        <ul>
                            <li>Livraison offerte à partir de 50€ d'achat</li>
                            <li>Satisfait ou remboursé sous 30 jours</li>
                            <li>Paiement 100% sécurisé</li>
                            <li>Service client : <?php echo htmlspecialchars($config['site_telephone'] ?? '01 23 45 67 89'); ?></li>
                        </ul>
                    </div>

                    <!-- Boutons d'action -->
                    <div class="form-actions">
                        <a href="panier.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour au panier
                        </a>
                        <button
                            type="submit"
                            class="btn btn-primary"
                            id="validerCommande"
                        >
                            <i class="fas fa-lock"></i> Payer et confirmer la commande
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'footer.php'; ?>

    <!-- Scripts -->
    <script src="js/main.js"></script>
    <script>
        // Données de base
        const sousTotal = <?php echo $panierTotal; ?>;
        let fraisLivraison = 0;

        // Gestion des options de livraison
        document.querySelectorAll('input[name="mode_livraison"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Mettre à jour l'affichage des options
                document.querySelectorAll('.livraison-option').forEach(option => {
                    option.classList.remove('selected');
                });
                this.closest('.livraison-option').classList.add('selected');
                
                // Calculer les frais de livraison
                switch (this.value) {
                    case 'express':
                        fraisLivraison = 9.90;
                        document.getElementById('shippingCost').textContent = '9,90 €';
                        document.getElementById('shippingCost').className = '';
                        break;
                    case 'point_relais':
                        fraisLivraison = 4.90;
                        document.getElementById('shippingCost').textContent = '4,90 €';
                        document.getElementById('shippingCost').className = '';
                        break;
                    default:
                        fraisLivraison = 0;
                        document.getElementById('shippingCost').textContent = 'GRATUIT';
                        document.getElementById('shippingCost').className = 'free-shipping';
                }
                
                // Mettre à jour le total
                const total = sousTotal + fraisLivraison;
                document.getElementById('totalAmount').textContent = total.toFixed(2).replace('.', ',') + ' €';
            });
        });

        // Gestion des méthodes de paiement
        document.querySelectorAll('input[name="mode_paiement"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Mettre à jour l'affichage des méthodes
                document.querySelectorAll('.paiement-method').forEach(method => {
                    method.classList.remove('selected');
                    const cardDetails = method.querySelector('.card-details');
                    if (cardDetails) {
                        cardDetails.classList.remove('active');
                    }
                });
                
                this.closest('.paiement-method').classList.add('selected');
                
                // Afficher les détails de la carte si nécessaire
                if (this.value === 'carte') {
                    const cardDetails = this.closest('.paiement-method').querySelector('.card-details');
                    if (cardDetails) {
                        cardDetails.classList.add('active');
                    }
                }
            });
        });

        // Formatage du numéro de carte
        document.getElementById('card_number')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 16) value = value.substring(0, 16);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        // Formatage de la date d'expiration
        document.getElementById('card_expiry')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 4) value = value.substring(0, 4);
            
            if (value.length >= 2) {
                let month = value.substring(0, 2);
                if (parseInt(month) > 12) {
                    month = '12';
                }
                if (parseInt(month) < 1) {
                    month = '01';
                }
                value = month + value.substring(2);
            }
            
            let formatted = value;
            if (value.length >= 2) {
                formatted = value.substring(0, 2) + '/' + value.substring(2);
            }
            e.target.value = formatted;
        });

        // Formatage automatique du téléphone
        document.getElementById('telephone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.substring(0, 10);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 2 || i === 4 || i === 6 || i === 8) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        // Validation avant soumission
        document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Réinitialiser les erreurs
            document.querySelectorAll('.error-message').forEach(el => el.remove());
            document.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
            
            // Validation des champs obligatoires
            const requiredFields = [
                'email', 'nom', 'prenom', 'adresse', 'code_postal', 
                'ville', 'telephone'
            ];
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    isValid = false;
                    input.classList.add('error');
                    showError(input, 'Ce champ est obligatoire');
                }
            });
            
            // Validation du mode de livraison
            const modeLivraison = document.querySelector('input[name="mode_livraison"]:checked');
            if (!modeLivraison) {
                isValid = false;
                const livraisonSection = document.querySelector('.livraison-section');
                showError(livraisonSection, 'Veuillez sélectionner un mode de livraison');
            }
            
            // Validation du mode de paiement
            const modePaiement = document.querySelector('input[name="mode_paiement"]:checked');
            if (!modePaiement) {
                isValid = false;
                const paiementSection = document.querySelector('.paiement-form');
                showError(paiementSection, 'Veuillez sélectionner un mode de paiement');
            }
            
            // Validation des conditions générales
            const conditions = document.getElementById('conditions');
            if (!conditions.checked) {
                isValid = false;
                conditions.classList.add('error');
                showError(conditions, 'Vous devez accepter les conditions générales');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez corriger les erreurs dans le formulaire');
            } else {
                // Afficher un loader
                const submitBtn = document.getElementById('validerCommande');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
                submitBtn.disabled = true;
                
                // Réactiver après 2 secondes (pour l'exemple)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            }
        });
        
        function showError(element, message) {
            const errorSpan = document.createElement('span');
            errorSpan.className = 'error-message';
            errorSpan.textContent = message;
            element.parentNode.appendChild(errorSpan);
        }
    </script>
</body>
</html>
