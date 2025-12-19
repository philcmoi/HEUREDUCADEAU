<?php
session_start();

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

// Récupérer les paramètres de l'URL
$numero_commande = isset($_GET['cmd']) ? htmlspecialchars($_GET['cmd']) : '';
$reference_paiement = isset($_GET['ref']) ? htmlspecialchars($_GET['ref']) : '';
$client_id = isset($_GET['client']) ? intval($_GET['client']) : 0;

// Si pas de paramètres, vérifier la session
if (empty($numero_commande) && isset($_SESSION['commande_en_cours'])) {
    $commande = $_SESSION['commande_en_cours'];
    $numero_commande = $commande['numero'] ?? '';
    $reference_paiement = $commande['reference_paiement'] ?? '';
    $client_id = $commande['client_id'] ?? 0;
}

// Si toujours pas de commande, rediriger
if (empty($numero_commande)) {
    header('Location: index.php');
    exit();
}

// Récupérer les détails de la commande depuis la BDD
try {
    $pdo = getDBConnection();
    
    // Récupérer la commande
    $sql_commande = "SELECT c.*, cl.email as client_email, cl.nom as client_nom, 
                     cl.prenom as client_prenom, cl.is_temporary
                     FROM commandes c
                     LEFT JOIN clients cl ON c.id_client = cl.id_client
                     WHERE c.numero_commande = :numero_commande
                     OR c.reference_paiement = :reference_paiement
                     LIMIT 1";
    
    $stmt_commande = $pdo->prepare($sql_commande);
    $stmt_commande->execute([
        'numero_commande' => $numero_commande,
        'reference_paiement' => $reference_paiement
    ]);
    
    $commande_bdd = $stmt_commande->fetch();
    
    if (!$commande_bdd) {
        // Command non trouvée, utiliser les données de session
        if (!isset($_SESSION['commande_en_cours'])) {
            header('Location: index.php');
            exit();
        }
        $commande_bdd = [
            'numero_commande' => $numero_commande,
            'total_ttc' => $_SESSION['commande_en_cours']['total'] ?? 0,
            'sous_total' => $_SESSION['commande_en_cours']['sous_total'] ?? 0,
            'frais_livraison' => $_SESSION['commande_en_cours']['frais_livraison'] ?? 0,
            'date_commande' => $_SESSION['commande_en_cours']['date'] ?? date('Y-m-d H:i:s'),
            'mode_paiement' => $_SESSION['commande_en_cours']['methode_paiement'] ?? 'paypal',
            'client_email' => $_SESSION['commande_en_cours']['adresse_livraison']['email'] ?? '',
            'client_nom' => $_SESSION['commande_en_cours']['adresse_livraison']['nom'] ?? '',
            'client_prenom' => $_SESSION['commande_en_cours']['adresse_livraison']['prenom'] ?? '',
            'is_temporary' => 1
        ];
    }
    
    // Récupérer les items de la commande
    $sql_items = "SELECT ci.*, p.slug, p.image 
                  FROM commande_items ci
                  LEFT JOIN produits p ON ci.id_produit = p.id_produit
                  WHERE ci.id_commande = :id_commande";
    
    $stmt_items = $pdo->prepare($sql_items);
    $stmt_items->execute(['id_commande' => $commande_bdd['id_commande'] ?? 0]);
    $items_commande = $stmt_items->fetchAll();
    
} catch (PDOException $e) {
    error_log("Erreur récupération commande: " . $e->getMessage());
    $commande_bdd = [];
    $items_commande = [];
}

// Formatage des données pour l'affichage
$numero_commande = $commande_bdd['numero_commande'] ?? $numero_commande;
$date_commande = date('d/m/Y H:i', strtotime($commande_bdd['date_commande'] ?? 'now'));
$total_commande = number_format($commande_bdd['total_ttc'] ?? 0, 2, ',', ' ');
$sous_total = number_format($commande_bdd['sous_total'] ?? 0, 2, ',', ' ');
$frais_livraison = number_format($commande_bdd['frais_livraison'] ?? 0, 2, ',', ' ');
$mode_paiement = $commande_bdd['mode_paiement'] ?? 'paypal';
$client_email = $commande_bdd['client_email'] ?? '';
$client_nom = $commande_bdd['client_nom'] ?? '';
$client_prenom = $commande_bdd['client_prenom'] ?? '';
$is_temporary = $commande_bdd['is_temporary'] ?? 1;

// Calculer les frais d'emballage
$frais_emballage = isset($_SESSION['emballage_cadeau']) && $_SESSION['emballage_cadeau'] ? 3.90 : 0;

// Fonction pour convertir un client temporaire en permanent
function convertClientToPermanent($client_id, $password, $confirm_password, $newsletter = 1) {
    try {
        $pdo = getDBConnection();
        
        // Vérifier que les mots de passe correspondent
        if ($password !== $confirm_password) {
            return ['success' => false, 'message' => 'Les mots de passe ne correspondent pas'];
        }
        
        // Vérifier la longueur du mot de passe
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères'];
        }
        
        // Hasher le mot de passe
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Démarrer une transaction
        $pdo->beginTransaction();
        
        // Mettre à jour le client
        $sql_update = "UPDATE clients 
                       SET mot_de_passe = :mot_de_passe, 
                           is_temporary = 0,
                           newsletter = :newsletter,
                           statut = 'actif'
                       WHERE id_client = :id_client 
                       AND is_temporary = 1";
        
        $stmt_update = $pdo->prepare($sql_update);
        $stmt_update->execute([
            'mot_de_passe' => $password_hash,
            'newsletter' => $newsletter,
            'id_client' => $client_id
        ]);
        
        // Enregistrer la conversion
        $sql_conversion = "INSERT INTO conversions_temp 
                          (id_client_temp, methode_conversion, source_page)
                          VALUES (:id_client_temp, 'post_commande', 'confirmation.php')";
        
        $stmt_conversion = $pdo->prepare($sql_conversion);
        $stmt_conversion->execute([
            'id_client_temp' => $client_id
        ]);
        
        $pdo->commit();
        
        // Nettoyer la session
        unset($_SESSION['commande_en_cours']);
        unset($_SESSION['panier']);
        unset($_SESSION['adresse_livraison']);
        
        return ['success' => true, 'message' => 'Compte créé avec succès'];
        
    } catch (PDOException $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Erreur conversion client: " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur lors de la création du compte'];
    }
}

// Traitement du formulaire de création de compte
$message_success = '';
$message_erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_account') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;
    
    $result = convertClientToPermanent($client_id, $password, $confirm_password, $newsletter);
    
    if ($result['success']) {
        $message_success = $result['message'];
        $is_temporary = 0; // Mettre à jour l'affichage
    } else {
        $message_erreur = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Confirmation - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* VOTRE CSS EXISTANT (inchangé) */
        .confirmation-page {
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: calc(100vh - 200px);
            text-align: center;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .confirmation-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .confirmation-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .confirmation-icon i {
            font-size: 36px;
            color: white;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 32px;
        }
        
        .confirmation-message {
            background: #f7fafc;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .confirmation-message p {
            margin: 10px 0;
            color: #4a5568;
            font-size: 16px;
        }
        
        .order-details {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #718096;
            font-weight: 500;
        }
        
        .detail-value {
            color: #2d3748;
            font-weight: 600;
        }
        
        .order-number {
            color: #5a67d8;
            font-size: 18px;
            font-weight: 700;
        }
        
        .next-steps {
            background: #f7fafc;
            padding: 30px;
            border-radius: 10px;
            margin: 40px 0;
            text-align: left;
        }
        
        .next-steps h3 {
            color: #2d3748;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        
        .step:last-child {
            margin-bottom: 0;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            background: #5a67d8;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 20px;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            margin: 0 0 8px 0;
            color: #2d3748;
        }
        
        .step-content p {
            margin: 0;
            color: #718096;
            line-height: 1.5;
        }
        
        .email-notice {
            background: #e6f7ff;
            border: 1px solid #91d5ff;
            border-radius: 10px;
            padding: 20px;
            margin: 30px 0;
            text-align: left;
        }
        
        .email-notice i {
            color: #1890ff;
            margin-right: 10px;
            font-size: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 40px;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
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

        /* NOUVEAU STYLE POUR LA CRÉATION DE COMPTE */
        .account-creation-section {
            margin: 40px 0;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            text-align: left;
        }

        .account-creation-section h3 {
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .account-benefits {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .benefit-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .benefit-item i {
            color: #5a67d8;
            font-size: 20px;
            margin-top: 2px;
        }

        .benefit-content h4 {
            margin: 0 0 5px 0;
            color: #2d3748;
            font-size: 14px;
        }

        .benefit-content p {
            margin: 0;
            color: #718096;
            font-size: 13px;
            line-height: 1.4;
        }

        .account-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-account {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
        }

        .btn-create {
            background: #5a67d8;
            color: white;
        }

        .btn-create:hover {
            background: #4c51bf;
            transform: translateY(-2px);
        }

        .btn-skip {
            background: #edf2f7;
            color: #4a5568;
        }

        .btn-skip:hover {
            background: #e2e8f0;
        }

        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .password-strength.weak {
            color: #e53e3e;
        }
        .password-strength.medium {
            color: #d69e2e;
        }
        .password-strength.strong {
            color: #38a169;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }
        
        .alert-danger {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }

            .account-benefits {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .confirmation-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'partials/header.php'; ?>

    <main class="confirmation-page">
        <div class="container">
            <div class="confirmation-card">
                <div class="confirmation-icon">
                    <i class="fas fa-check"></i>
                </div>

                <h1>Commande confirmée !</h1>

                <div class="confirmation-message">
                    <p>Merci pour votre commande ! Elle a bien été enregistrée et sera traitée dans les plus brefs délais.</p>
                    <p>Un email récapitulatif vous a été envoyé à l'adresse <strong><?php echo htmlspecialchars($client_email); ?></strong>.</p>
                </div>

                <div class="order-details">
                    <div class="detail-row">
                        <span class="detail-label">Numéro de commande</span>
                        <span class="detail-value order-number"><?php echo $numero_commande; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Date de la commande</span>
                        <span class="detail-value"><?php echo $date_commande; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Statut</span>
                        <span class="detail-value" style="color: #38a169;">
                            <i class="fas fa-check-circle"></i> Confirmée
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email de contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($client_email); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Mode de paiement</span>
                        <span class="detail-value">
                            <?php 
                            $mode_libelle = 'Carte bancaire';
                            if ($mode_paiement === 'paypal') $mode_libelle = 'PayPal';
                            echo $mode_libelle;
                            ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Total TTC</span>
                        <span class="detail-value" style="font-size: 18px; color: #5a67d8; font-weight: 700;">
                            <?php echo $total_commande; ?> €
                        </span>
                    </div>
                </div>

                <!-- SECTION CRÉATION DE COMPTE -->
                <?php if ($is_temporary && $client_id > 0 && empty($message_success)): ?>
                <div id="accountCreationSection" class="account-creation-section">
                    <h3>
                        <i class="fas fa-user-plus"></i> Créez votre compte permanent
                    </h3>

                    <p>Profitez de ces avantages en créant un compte :</p>

                    <div class="account-benefits">
                        <div class="benefit-item">
                            <i class="fas fa-shipping-fast"></i>
                            <div class="benefit-content">
                                <h4>Suivi de commande</h4>
                                <p>Suivez l'état de votre commande en temps réel</p>
                            </div>
                        </div>

                        <div class="benefit-item">
                            <i class="fas fa-history"></i>
                            <div class="benefit-content">
                                <h4>Historique d'achats</h4>
                                <p>Retrouvez tous vos achats passés</p>
                            </div>
                        </div>

                        <div class="benefit-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="benefit-content">
                                <h4>Adresses sauvegardées</h4>
                                <p>Vos adresses enregistrées pour commander plus vite</p>
                            </div>
                        </div>

                        <div class="benefit-item">
                            <i class="fas fa-gift"></i>
                            <div class="benefit-content">
                                <h4>Promotions exclusives</h4>
                                <p>Recevez des offres spéciales en avant-première</p>
                            </div>
                        </div>
                    </div>

                    <div class="account-form">
                        <?php if (!empty($message_erreur)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message_erreur); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form id="createAccountForm" method="POST">
                            <input type="hidden" name="action" value="create_account">
                            <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="password">Mot de passe *</label>
                                    <input type="password" id="password" name="password" required minlength="6" />
                                    <div id="passwordStrength" class="password-strength"></div>
                                </div>

                                <div class="form-group">
                                    <label for="confirmPassword">Confirmer le mot de passe *</label>
                                    <input type="password" id="confirmPassword" name="confirm_password" required minlength="6" />
                                    <div id="passwordMatch" style="font-size: 12px; margin-top: 5px"></div>
                                </div>
                            </div>

                            <div class="form-group" style="margin-top: 20px">
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer">
                                    <input type="checkbox" id="newsletter" name="newsletter" value="1" checked />
                                    <span>Je souhaite recevoir les offres spéciales et nouveautés par email</span>
                                </label>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn-account btn-create" id="createAccountBtn">
                                    <i class="fas fa-check"></i> Créer mon compte
                                </button>
                                <button type="button" class="btn-account btn-skip" id="skipAccountBtn">
                                    <i class="fas fa-times"></i> Plus tard
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif (!empty($message_success)): ?>
                <div class="account-creation-section">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Compte créé avec succès !</strong> Vous pouvez maintenant vous connecter avec votre email et mot de passe.
                    </div>
                </div>
                <?php endif; ?>

                <div class="next-steps">
                    <h3><i class="fas fa-list-ol"></i> Prochaines étapes</h3>

                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Email de confirmation</h4>
                            <p>
                                Vous recevrez un email de confirmation dans les prochaines minutes avec tous les détails de votre commande.
                            </p>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Préparation de votre commande</h4>
                            <p>
                                Notre équipe prépare votre commande avec soin. Vous serez informé dès son expédition.
                            </p>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Livraison</h4>
                            <p>
                                Suivez votre colis grâce au numéro de suivi qui vous sera envoyé par email.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="email-notice">
                    <i class="fas fa-envelope"></i>
                    <strong>Vérifiez vos spams !</strong>
                    <p style="margin: 5px 0 0 0">
                        Si vous ne recevez pas l'email de confirmation, pensez à vérifier votre dossier de courriers indésirables.
                    </p>
                </div>

                <div class="action-buttons">
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Retour à l'accueil
                    </a>
                    <a href="produits.php" class="btn btn-secondary">
                        <i class="fas fa-shopping-bag"></i> Continuer mes achats
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <script>
        // Gestion de la force du mot de passe
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthDiv = document.getElementById('passwordStrength');

            if (password.length === 0) {
                strengthDiv.textContent = '';
                strengthDiv.className = 'password-strength';
                return;
            }

            let strength = 'weak';
            let message = 'Faible';

            if (password.length >= 8 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'strong';
                message = 'Fort';
            } else if (password.length >= 6) {
                strength = 'medium';
                message = 'Moyen';
            }

            strengthDiv.textContent = `Sécurité : ${message}`;
            strengthDiv.className = `password-strength ${strength}`;
        });

        // Vérification de la correspondance des mots de passe
        document.getElementById('confirmPassword')?.addEventListener('input', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;
            const matchDiv = document.getElementById('passwordMatch');

            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
                return;
            }

            if (password === confirmPassword) {
                matchDiv.textContent = '✓ Les mots de passe correspondent';
                matchDiv.style.color = '#38a169';
            } else {
                matchDiv.textContent = '✗ Les mots de passe ne correspondent pas';
                matchDiv.style.color = '#e53e3e';
            }
        });

        // Ignorer la création de compte
        document.getElementById('skipAccountBtn')?.addEventListener('click', function() {
            if (confirm('Vous pourrez créer un compte plus tard en utilisant le lien dans l\'email de confirmation. Continuer ?')) {
                document.getElementById('accountCreationSection').style.display = 'none';
            }
        });

        // Nettoyer le localStorage
        sessionStorage.removeItem("livraisonData");

        // Mettre à jour le compteur du panier
        document.addEventListener("DOMContentLoaded", function () {
            const cartCounts = document.querySelectorAll(".cart-count");
            cartCounts.forEach((el) => {
                el.textContent = "0";
                el.style.display = "none";
            });
        });
    </script>
</body>
</html>