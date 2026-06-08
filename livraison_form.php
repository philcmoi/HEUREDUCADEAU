<?php
// ============================================
// PAGE DU FORMULAIRE DE LIVRAISON - VERSION CORRIGÉE
// TRAITEMENT DU TOKEN AVANT VÉRIFICATION DU PANIER
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/session_verification.php';

// ============================================
// 1. D'ABORD, RÉCUPÉRER LE TOKEN ET CHARGER LE CLIENT ASSOCIÉ
// ============================================
$token = $_GET['token'] ?? '';
if (!empty($token)) {
    $_SESSION['token_confirmation'] = $token;
    
    $pdo = getPDOConnection();
    if ($pdo) {
        try {
            // Vérifier si la table tokens_confirmation existe
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'tokens_confirmation'");
            $stmt->execute();
            $tableExists = $stmt->rowCount() > 0;
            
            if ($tableExists) {
                $stmt = $pdo->prepare("
                    SELECT id, email, id_client, utilise, expiration 
                    FROM tokens_confirmation 
                    WHERE token = ? AND utilise = 1
                ");
                $stmt->execute([$token]);
                $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tokenData) {
                    // Stocker les infos du client en session
                    $_SESSION['client_email'] = $tokenData['email'];
                    $_SESSION['client_id'] = $tokenData['id_client'];
                    $_SESSION['email_confirme'] = $tokenData['email'];
                    $_SESSION['token_valide'] = true;
                    
                    error_log("Token valide trouvé pour email: " . $tokenData['email']);
                    
                    // CHARGER LE PANIER DE CE CLIENT DANS LA SESSION
                    if ($tokenData['id_client']) {
                        $stmt2 = $pdo->prepare("
                            SELECT lp.idOrigami, lp.quantite, o.prixHorsTaxe, o.nom, p.idPanier
                            FROM LignePanier lp
                            JOIN Panier p ON lp.idPanier = p.idPanier
                            JOIN Origami o ON lp.idOrigami = o.idOrigami
                            WHERE p.idClient = ?
                        ");
                        $stmt2->execute([$tokenData['id_client']]);
                        $articles = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($articles)) {
                            $_SESSION[SESSION_KEY_PANIER] = [];
                            foreach ($articles as $article) {
                                $_SESSION[SESSION_KEY_PANIER][] = [
                                    'id_produit' => $article['idOrigami'],
                                    'quantite' => $article['quantite'],
                                    'prix' => $article['prixHorsTaxe'],
                                    'nom' => $article['nom']
                                ];
                            }
                            $_SESSION[SESSION_KEY_PANIER_ID] = $articles[0]['idPanier'] ?? null;
                            error_log("Panier chargé depuis token: " . count($articles) . " articles");
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Erreur récupération token: " . $e->getMessage());
        }
    }
}

// ============================================
// 2. FONCTION POUR RÉCUPÉRER LE PANIER VIA L'API
// ============================================
function recupererPanierViaAPI() {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://' . $_SERVER['HTTP_HOST'] . '/acheter.php?action=get_panier');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data['status'] === 200 && !empty($data['data']['articles'])) {
            return $data['data'];
        }
    }
    return null;
}

// ============================================
// 3. RÉCUPÉRATION DU PANIER (SESSION + API)
// ============================================

// Vérifier si le panier existe en session
$panierExistant = isset($_SESSION[SESSION_KEY_PANIER]) && !empty($_SESSION[SESSION_KEY_PANIER]);

// Si pas de panier en session, essayer via l'API
if (!$panierExistant) {
    $panierAPI = recupererPanierViaAPI();
    if ($panierAPI && !empty($panierAPI['articles'])) {
        // Reconstruire le panier en session
        $_SESSION[SESSION_KEY_PANIER] = [];
        foreach ($panierAPI['articles'] as $article) {
            $_SESSION[SESSION_KEY_PANIER][] = [
                'id_produit' => $article['idOrigami'],
                'quantite' => $article['quantite'],
                'prix' => $article['prixUnitaire'],
                'nom' => $article['nom']
            ];
        }
        $_SESSION[SESSION_KEY_PANIER_ID] = $panierAPI['articles'][0]['idPanier'] ?? null;
        $panierExistant = true;
        error_log("Panier chargé depuis API: " . count($panierAPI['articles']) . " articles");
    }
}

// 4. VÉRIFICATION CRITIQUE : Si le panier est VIDE, REDIRIGER VERS panier.html
// MAIS UNIQUEMENT SI PAS DE TOKEN VALIDE EN SESSION
if (!$panierExistant && empty($_SESSION['client_id']) && !isset($_GET['debug'])) {
    addSessionMessage('Votre panier est vide. Ajoutez des produits avant de passer commande.', 'error');
    header('Location: panier.html');
    exit;
}

// Si on a un client_id mais pas de panier, essayer de le charger une dernière fois
if (!$panierExistant && !empty($_SESSION['client_id']) && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT lp.idOrigami, lp.quantite, o.prixHorsTaxe, o.nom, p.idPanier
            FROM LignePanier lp
            JOIN Panier p ON lp.idPanier = p.idPanier
            JOIN Origami o ON lp.idOrigami = o.idOrigami
            WHERE p.idClient = ?
        ");
        $stmt->execute([$_SESSION['client_id']]);
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($articles)) {
            $_SESSION[SESSION_KEY_PANIER] = [];
            foreach ($articles as $article) {
                $_SESSION[SESSION_KEY_PANIER][] = [
                    'id_produit' => $article['idOrigami'],
                    'quantite' => $article['quantite'],
                    'prix' => $article['prixHorsTaxe'],
                    'nom' => $article['nom']
                ];
            }
            $_SESSION[SESSION_KEY_PANIER_ID] = $articles[0]['idPanier'] ?? null;
            $panierExistant = true;
            error_log("Panier chargé depuis client_id: " . count($articles) . " articles");
        }
    } catch (Exception $e) {
        error_log("Erreur chargement panier par client_id: " . $e->getMessage());
    }
}

// Dernière vérification : si toujours pas de panier et pas en debug, rediriger
if (!$panierExistant && !isset($_GET['debug'])) {
    addSessionMessage('Votre panier est vide. Ajoutez des produits avant de passer commande.', 'error');
    header('Location: panier.html');
    exit;
}

// Connexion BDD
$pdo = getPDOConnection();

// Si un token est présent et que l'email n'est pas en session (cas où token non utilisé), le récupérer
if (!empty($token) && empty($_SESSION['client_email']) && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT email, id_client FROM tokens_confirmation WHERE token = ? AND utilise = 1");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($tokenData) {
            $_SESSION['client_email'] = $tokenData['email'];
            $_SESSION['client_id'] = $tokenData['id_client'];
        }
    } catch (Exception $e) {
        error_log("Erreur récupération token: " . $e->getMessage());
    }
}

// Vérification d'accès (uniquement si panier non vide)
if ($panierExistant) {
    checkLivraisonAccess();
}

// Synchroniser le panier avec la BDD
if ($pdo) {
    synchroniserPanierSessionBDD($pdo, session_id());
}

// Récupérer les messages
$messages = getSessionMessages();

// Récupérer les données existantes
$donnees_saisies = $_SESSION[SESSION_KEY_CHECKOUT]['adresse_livraison'] ?? [];

// Si email en session, pré-remplir
if (empty($donnees_saisies['email']) && isset($_SESSION['client_email'])) {
    $donnees_saisies['email'] = $_SESSION['client_email'];
}

$meme_adresse_checked = !isset($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse']) || 
                        empty($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse']);

// Valeurs par défaut
if (!isset($_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'])) {
    $_SESSION[SESSION_KEY_CHECKOUT]['mode_livraison'] = 'standard';
}
if (!isset($_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'])) {
    $_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] = false;
}

// Logs de débogage
error_log("=== LIVRAISON_FORM (après corrections) ===");
error_log("Panier existant: " . ($panierExistant ? 'OUI' : 'NON'));
error_log("Token présent: " . (!empty($token) ? 'OUI' : 'NON'));
error_log("Client ID: " . ($_SESSION['client_id'] ?? 'non défini'));
error_log("Nombre articles panier: " . (count($_SESSION[SESSION_KEY_PANIER] ?? [])));

// Mode debug
if (isset($_GET['debug'])) {
    echo "<div style='background:#f0f0f0;padding:10px;margin:10px;border:1px solid #ccc;'>";
    echo "<strong>DEBUG MODE</strong><br>";
    echo "Panier en session: " . (count($_SESSION[SESSION_KEY_PANIER] ?? []) > 0 ? count($_SESSION[SESSION_KEY_PANIER]) . " article(s)" : "VIDE") . "<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "Client ID: " . ($_SESSION[SESSION_KEY_CLIENT_ID] ?? 'non défini') . "<br>";
    echo "Token: " . (!empty($token) ? substr($token, 0, 20) . "..." : 'aucun') . "<br>";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adresse de Livraison - Youki and Co</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-card {
            background: white;
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #e74c3c;
            padding-bottom: 15px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        h1 i { color: #e74c3c; }
        h2 {
            color: #2c3e50;
            font-size: 1.3rem;
            margin: 25px 0 15px 0;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        h2 i { color: #e74c3c; }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .required:after {
            content: " *";
            color: #e74c3c;
        }
        input, textarea, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231,76,60,0.1);
        }
        .form-row {
            display: flex;
            gap: 20px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .radio-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 20px;
        }
        .radio-option {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .radio-option:hover {
            border-color: #e74c3c;
            background: rgba(231,76,60,0.05);
        }
        .radio-option.selected {
            border-color: #27ae60;
            background: rgba(39,174,96,0.05);
        }
        .radio-info {
            flex: 1;
        }
        .radio-info strong {
            display: block;
            color: #2c3e50;
        }
        .radio-info p {
            color: #7f8c8d;
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .radio-price {
            font-weight: 700;
            color: #27ae60;
            font-size: 1.1rem;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 20px;
            background: #f0fff4;
            border: 2px solid #9ae6b4;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .checkbox-group input {
            width: auto;
        }
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #27ae60, #219653);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            transition: all 0.3s ease;
            margin-top: 30px;
        }
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(39,174,96,0.3);
        }
        .btn-submit:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        .message {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 12px;
            border-left: 5px solid transparent;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border-left-color: #27ae60;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #e74c3c;
        }
        .message.info {
            background: #d1ecf1;
            color: #0c5460;
            border-left-color: #3498db;
        }
        .error-field {
            border-color: #e74c3c !important;
        }
        .error-message {
            color: #e74c3c;
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        .error-message.show {
            display: block;
        }
        #adresse-facturation-different {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 16px;
            margin-top: 20px;
            margin-bottom: 25px;
            display: none;
        }
        .info-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 12px;
            background: #ebf8ff;
            border-left: 5px solid #3498db;
        }
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .form-card { padding: 25px; }
            h1 { font-size: 1.5rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <h1><i class="fas fa-truck"></i> Adresse de livraison</h1>

            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?php echo htmlspecialchars($msg['type']); ?>">
                        <?php echo htmlspecialchars($msg['message']); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($token)): ?>
            <div class="info-message">
                <strong><i class="fas fa-check-circle"></i> Email confirmé !</strong><br>
                Votre adresse email a été vérifiée avec succès.
            </div>
            <?php endif; ?>

            <div id="info-message"></div>

            <form id="livraison-form" method="POST" action="livraison.php">
                <input type="hidden" name="api_mode" value="1">
                <input type="hidden" name="panier_id" value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_PANIER_ID] ?? ''); ?>">
                <?php if (!empty($token)): ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <?php endif; ?>

                <h2><i class="fas fa-user"></i> Informations personnelles</h2>

                <div class="form-row">
                    <div class="form-group">
                        <label for="prenom" class="required">Prénom</label>
                        <input type="text" id="prenom" name="prenom" 
                               value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? ''); ?>" required>
                        <div class="error-message" id="error-prenom"></div>
                    </div>
                    <div class="form-group">
                        <label for="nom" class="required">Nom</label>
                        <input type="text" id="nom" name="nom" 
                               value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? ''); ?>" required>
                        <div class="error-message" id="error-nom"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email" class="required">Email</label>
                    <input type="email" id="email" name="email" 
                           value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? $_SESSION['client_email'] ?? ''); ?>" required>
                    <div class="error-message" id="error-email"></div>
                    <div style="font-size: 0.85rem; color: #7f8c8d; margin-top: 5px;">
                        <i class="fas fa-info-circle"></i> Votre confirmation de commande sera envoyée à cette adresse
                    </div>
                </div>

                <div class="form-group">
                    <label for="telephone">Téléphone</label>
                    <input type="tel" id="telephone" name="telephone" 
                           value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? ''); ?>">
                    <div class="error-message" id="error-telephone"></div>
                </div>

                <div class="form-group">
                    <label for="societe">Société (optionnel)</label>
                    <input type="text" id="societe" name="societe" 
                           value="<?php echo htmlspecialchars($donnees_saisies['societe'] ?? ''); ?>">
                </div>

                <h2><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h2>

                <div class="form-group">
                    <label for="adresse" class="required">Adresse</label>
                    <textarea id="adresse" name="adresse" rows="3" required><?php echo htmlspecialchars($donnees_saisies['adresse'] ?? ''); ?></textarea>
                    <div class="error-message" id="error-adresse"></div>
                </div>

                <div class="form-group">
                    <label for="complement">Complément d'adresse</label>
                    <input type="text" id="complement" name="complement" 
                           value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? ''); ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="code_postal" class="required">Code postal</label>
                        <input type="text" id="code_postal" name="code_postal" 
                               value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? ''); ?>" required>
                        <div class="error-message" id="error-code_postal"></div>
                    </div>
                    <div class="form-group">
                        <label for="ville" class="required">Ville</label>
                        <input type="text" id="ville" name="ville" 
                               value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? ''); ?>" required>
                        <div class="error-message" id="error-ville"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pays" class="required">Pays</label>
                    <select id="pays" name="pays" required>
                        <option value="France" <?php echo (($donnees_saisies['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                        <option value="Belgique" <?php echo (($donnees_saisies['pays'] ?? '') === 'Belgique') ? 'selected' : ''; ?>>Belgique</option>
                        <option value="Suisse" <?php echo (($donnees_saisies['pays'] ?? '') === 'Suisse') ? 'selected' : ''; ?>>Suisse</option>
                        <option value="Luxembourg" <?php echo (($donnees_saisies['pays'] ?? '') === 'Luxembourg') ? 'selected' : ''; ?>>Luxembourg</option>
                        <option value="autre" <?php echo (($donnees_saisies['pays'] ?? '') === 'autre') ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>

                <h2><i class="fas fa-file-invoice"></i> Adresse de facturation</h2>

                <div class="checkbox-group">
                    <input type="checkbox" id="meme_adresse_facturation" name="meme_adresse_facturation" value="1" 
                           <?php echo $meme_adresse_checked ? 'checked' : ''; ?>>
                    <div>
                        <label for="meme_adresse_facturation" style="font-weight: 600;">Utiliser la même adresse pour la facturation</label>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.85rem;">Si décoché, vous pourrez saisir une adresse différente</p>
                    </div>
                </div>

                <div id="adresse-facturation-different">
                    <h3 style="margin-bottom: 15px; color: #2c3e50;">Adresse de facturation</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="facturation_prenom">Prénom</label>
                            <input type="text" id="facturation_prenom" name="facturation_prenom" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['prenom'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="facturation_nom">Nom</label>
                            <input type="text" id="facturation_nom" name="facturation_nom" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['nom'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_societe">Société (optionnel)</label>
                        <input type="text" id="facturation_societe" name="facturation_societe" 
                               value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['societe'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_adresse">Adresse</label>
                        <textarea id="facturation_adresse" name="facturation_adresse" rows="2"><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['adresse'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="facturation_code_postal">Code postal</label>
                            <input type="text" id="facturation_code_postal" name="facturation_code_postal" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['code_postal'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="facturation_ville">Ville</label>
                            <input type="text" id="facturation_ville" name="facturation_ville" 
                                   value="<?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['ville'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="facturation_pays">Pays</label>
                        <select id="facturation_pays" name="facturation_pays">
                            <option value="France" <?php echo (($_SESSION[SESSION_KEY_CHECKOUT]['adresse_facturation']['pays'] ?? 'France') === 'France') ? 'selected' : ''; ?>>France</option>
                            <option value="Belgique">Belgique</option>
                            <option value="Suisse">Suisse</option>
                            <option value="Luxembourg">Luxembourg</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                </div>

                <h2><i class="fas fa-truck"></i> Options de livraison</h2>

                <div class="radio-group" id="livraisonOptions">
                    <div class="radio-option selected" data-value="standard">
                        <div class="radio-info">
                            <strong>Livraison Standard</strong>
                            <p>Livraison en 3-5 jours ouvrés</p>
                        </div>
                        <div class="radio-price">Gratuite</div>
                        <input type="radio" name="mode_livraison" value="standard" checked hidden>
                    </div>

                    <div class="radio-option" data-value="express">
                        <div class="radio-info">
                            <strong>Livraison Express</strong>
                            <p>Livraison en 24h (hors week-end)</p>
                        </div>
                        <div class="radio-price">9,90 €</div>
                        <input type="radio" name="mode_livraison" value="express" hidden>
                    </div>

                    <div class="radio-option" data-value="relais">
                        <div class="radio-info">
                            <strong>Point Relais</strong>
                            <p>Retrait dans un point relais partenaire</p>
                        </div>
                        <div class="radio-price">4,90 €</div>
                        <input type="radio" name="mode_livraison" value="relais" hidden>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="emballage_cadeau" name="emballage_cadeau" value="1"
                           <?php echo ($_SESSION[SESSION_KEY_CHECKOUT]['emballage_cadeau'] ?? false) ? 'checked' : ''; ?>>
                    <div>
                        <label for="emballage_cadeau" style="font-weight: 600;">
                            <i class="fas fa-gift"></i> Emballage cadeau
                        </label>
                        <p style="margin: 0; color: #7f8c8d; font-size: 0.85rem;">Emballage élégant avec carte personnalisée - <strong>+3,90 €</strong></p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="instructions">Instructions de livraison (optionnel)</label>
                    <textarea id="instructions" name="instructions" rows="2"
                        placeholder="Ex: Sonner au portail rouge, livrer au gardien, etc."><?php echo htmlspecialchars($_SESSION[SESSION_KEY_CHECKOUT]['instructions'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn">
                    <i class="fas fa-arrow-right"></i> Continuer vers le paiement
                </button>

                <div style="text-align: center; margin-top: 20px; color: #7f8c8d; font-size: 0.85rem;">
                    <i class="fas fa-lock"></i> Vos données sont protégées et cryptées
                </div>
            </form>
        </div>
    </div>

    <script>
        let isLoading = false;

        function setupLivraisonOptions() {
            document.querySelectorAll('.radio-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) radio.checked = true;
                });
            });
        }

        function setupFacturationToggle() {
            const sameAddressCheckbox = document.getElementById('meme_adresse_facturation');
            const facturationDiv = document.getElementById('adresse-facturation-different');
            
            if (!sameAddressCheckbox || !facturationDiv) return;
            
            sameAddressCheckbox.addEventListener('change', function(e) {
                if (this.checked) {
                    facturationDiv.style.display = 'none';
                    facturationDiv.querySelectorAll('input, textarea, select').forEach(field => {
                        field.removeAttribute('required');
                    });
                } else {
                    facturationDiv.style.display = 'block';
                }
            });
        }

        function validateField(fieldId, errorId) {
            const field = document.getElementById(fieldId);
            const error = document.getElementById(errorId);
            
            if (!field) return true;
            
            if (field.hasAttribute('required') && !field.value.trim()) {
                field.classList.add('error-field');
                if (error) {
                    error.textContent = 'Ce champ est requis';
                    error.classList.add('show');
                }
                return false;
            }
            
            field.classList.remove('error-field');
            if (error) error.classList.remove('show');
            
            if (fieldId === 'email' && field.value.trim()) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(field.value)) {
                    field.classList.add('error-field');
                    if (error) {
                        error.textContent = 'Email invalide';
                        error.classList.add('show');
                    }
                    return false;
                }
            }
            
            if (fieldId === 'code_postal' && field.value.trim()) {
                const cpRegex = /^[0-9]{5}$/;
                if (!cpRegex.test(field.value)) {
                    field.classList.add('error-field');
                    if (error) {
                        error.textContent = 'Code postal invalide (5 chiffres)';
                        error.classList.add('show');
                    }
                    return false;
                }
            }
            
            return true;
        }

        function validateForm() {
            const fields = [
                { id: 'nom', error: 'error-nom' },
                { id: 'prenom', error: 'error-prenom' },
                { id: 'adresse', error: 'error-adresse' },
                { id: 'code_postal', error: 'error-code_postal' },
                { id: 'ville', error: 'error-ville' },
                { id: 'email', error: 'error-email' }
            ];
            
            let isValid = true;
            fields.forEach(field => {
                if (!validateField(field.id, field.error)) isValid = false;
            });
            
            return isValid;
        }

        function setupFormSubmission() {
            const form = document.getElementById('livraison-form');
            const submitBtn = document.getElementById('submit-btn');
            
            if (!form || !submitBtn) return;
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (!validateForm()) {
                    alert('Veuillez corriger les erreurs dans le formulaire.');
                    return;
                }
                
                if (isLoading) return;
                isLoading = true;
                
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
                submitBtn.disabled = true;
                
                const formData = new FormData(form);
                
                fetch('livraison.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'paiement.php';
                    } else {
                        let errorMsg = data.message || 'Une erreur est survenue';
                        alert(errorMsg);
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                        isLoading = false;
                        
                        if (data.missing) {
                            data.missing.forEach(fieldName => {
                                const field = document.getElementById(fieldName);
                                if (field) field.classList.add('error-field');
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion. Veuillez réessayer.');
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    isLoading = false;
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            setupLivraisonOptions();
            setupFacturationToggle();
            setupFormSubmission();
        });
    </script>
</body>
</html>