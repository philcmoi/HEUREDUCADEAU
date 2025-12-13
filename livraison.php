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

$sqlPanier = "SELECT pi.*, p.nom, p.reference, p.prix_ttc 
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

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation des champs
    $required_fields = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'telephone'];
    
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
    
    // Si pas d'erreurs
    if (empty($errors)) {
        $adresseData = [
            'nom' => htmlspecialchars($_POST['nom']),
            'prenom' => htmlspecialchars($_POST['prenom']),
            'societe' => !empty($_POST['societe']) ? htmlspecialchars($_POST['societe']) : null,
            'adresse' => htmlspecialchars($_POST['adresse']),
            'complement' => !empty($_POST['complement']) ? htmlspecialchars($_POST['complement']) : null,
            'code_postal' => htmlspecialchars($_POST['code_postal']),
            'ville' => htmlspecialchars($_POST['ville']),
            'pays' => !empty($_POST['pays']) ? htmlspecialchars($_POST['pays']) : 'France',
            'telephone' => htmlspecialchars($_POST['telephone'])
        ];
        
        // Si l'utilisateur est connecté, sauvegarder en base
        if ($client_id) {
            try {
                $pdo->beginTransaction();
                
                // Si l'utilisateur veut utiliser cette adresse comme principale
                $principale = isset($_POST['principale']) ? 1 : 0;
                
                // Si on veut marquer cette adresse comme principale, 
                // on doit d'abord enlever le statut principal des autres adresses
                if ($principale) {
                    $sqlResetPrincipal = "UPDATE adresses SET principale = 0 WHERE id_client = ?";
                    $stmtReset = $pdo->prepare($sqlResetPrincipal);
                    $stmtReset->execute([$client_id]);
                }
                
                // Insérer la nouvelle adresse
                $sqlInsertAdresse = "INSERT INTO adresses (
                    id_client, type_adresse, nom, prenom, societe, adresse, 
                    complement, code_postal, ville, pays, telephone, principale
                ) VALUES (?, 'livraison', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
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
                    $principale
                ]);
                
                $adresse_id = $pdo->lastInsertId();
                $adresseData['id_adresse'] = $adresse_id;
                
                $pdo->commit();
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors['general'] = "Erreur lors de la sauvegarde de l'adresse : " . $e->getMessage();
            }
        }
        
        if (empty($errors)) {
            // Sauvegarder dans la session
            $_SESSION['adresse_livraison'] = $adresseData;
            $success = true;
            
            // Rediriger vers le paiement après 2 secondes
            header("refresh:2;url=paiement.php");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Livraison - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/livraison.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-container">
            <a href="index.html" class="logo">
                <i class="fas fa-gift logo-icon"></i>
                <span class="logo-text">HEURE<span class="logo-highlight">DU CADEAU</span></span>
            </a>
            <nav class="nav-main">
                <ul class="nav-list">
                    <li><a href="index.html" class="nav-link"><i class="fas fa-home"></i> Accueil</a></li>
                    <li><a href="produits.php" class="nav-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                    <li><a href="apropos.html" class="nav-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                    <li><a href="contact.html" class="nav-link"><i class="fas fa-envelope"></i> Contact</a></li>
                    <li><a href="panier.php" class="nav-link cart-link">
                        <i class="fas fa-shopping-cart"></i> Panier
                        <span class="cart-count"><?php echo $totalArticles; ?></span>
                    </a></li>
                </ul>
            </nav>
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
        </div>
        <nav class="nav-mobile" id="navMobile">
            <ul class="nav-mobile-list">
                <li><a href="index.html" class="nav-mobile-link"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="produits.php" class="nav-mobile-link"><i class="fas fa-box-open"></i> Cadeaux</a></li>
                <li><a href="apropos.html" class="nav-mobile-link"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="contact.html" class="nav-mobile-link"><i class="fas fa-envelope"></i> Contact</a></li>
                <li><a href="panier.php" class="nav-mobile-link"><i class="fas fa-shopping-cart"></i> Panier</a></li>
            </ul>
        </nav>
    </header>

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
                <div class="progress-line"></div>
                <div class="progress-step">
                    <span class="step-number">3</span>
                    <span class="step-text">Paiement</span>
                </div>
            </div>

            <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <strong>Adresse enregistrée avec succès !</strong>
                <p>Redirection vers la page de paiement...</p>
            </div>
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errors['general']); ?>
            </div>
            <?php endif; ?>

            <div class="livraison-layout">
                <!-- Section récapitulative -->
                <div class="recap-section">
                    <h2><i class="fas fa-receipt"></i> Votre commande</h2>
                    <div class="recap-card">
                        <div class="recap-row">
                            <span>Articles</span>
                            <span><?php echo $totalArticles; ?></span>
                        </div>
                        <div class="recap-row">
                            <span>Sous-total</span>
                            <span><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                        </div>
                        <div class="recap-row">
                            <span>Livraison</span>
                            <span class="free-shipping">Gratuite</span>
                        </div>
                        <div class="recap-divider"></div>
                        <div class="recap-row total">
                            <span>Total TTC</span>
                            <span><?php echo number_format($panierTotal, 2, ',', ' '); ?> €</span>
                        </div>
                    </div>

                    <div class="adresse-livraison">
                        <h3><i class="fas fa-truck"></i> Options de livraison</h3>
                        <div class="livraison-options">
                            <div class="livraison-option active">
                                <input type="radio" name="mode_livraison" id="standard" checked>
                                <label for="standard">
                                    <i class="fas fa-truck"></i>
                                    <div class="option-content">
                                        <span class="option-title">Livraison standard</span>
                                        <span class="option-desc">Délai : 3-5 jours ouvrés</span>
                                        <span class="option-price">Gratuite</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="livraison-option">
                                <input type="radio" name="mode_livraison" id="express">
                                <label for="express">
                                    <i class="fas fa-bolt"></i>
                                    <div class="option-content">
                                        <span class="option-title">Livraison express</span>
                                        <span class="option-desc">Délai : 24-48h</span>
                                        <span class="option-price">9,90 €</span>
                                    </div>
                                </label>
                            </div>
                            
                            <div class="livraison-option">
                                <input type="radio" name="mode_livraison" id="point-relais">
                                <label for="point-relais">
                                    <i class="fas fa-store"></i>
                                    <div class="option-content">
                                        <span class="option-title">Point relais</span>
                                        <span class="option-desc">Retrait en 2-3 jours</span>
                                        <span class="option-price">4,90 €</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section adresse -->
                <div class="adresse-section">
                    <h2><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h2>
                    
                    <?php if ($client_id && count($adresses) > 0): ?>
                    <div class="adresses-existantes">
                        <h3><i class="fas fa-bookmark"></i> Vos adresses enregistrées</h3>
                        <div class="adresses-list">
                            <?php foreach ($adresses as $adr): ?>
                            <div class="adresse-item <?php echo ($adresseLivraison && $adr['id_adresse'] == $adresseLivraison['id_adresse']) ? 'selected' : ''; ?>">
                                <input type="radio" name="adresse_existante" 
                                       id="adresse_<?php echo $adr['id_adresse']; ?>"
                                       value="<?php echo $adr['id_adresse']; ?>"
                                       <?php echo ($adresseLivraison && $adr['id_adresse'] == $adresseLivraison['id_adresse']) ? 'checked' : ''; ?>>
                                <label for="adresse_<?php echo $adr['id_adresse']; ?>">
                                    <strong><?php echo htmlspecialchars($adr['prenom'] . ' ' . $adr['nom']); ?></strong>
                                    <?php if ($adr['principale']): ?>
                                    <span class="tag-principale">Principale</span>
                                    <?php endif; ?>
                                    <p><?php echo htmlspecialchars($adr['adresse']); ?></p>
                                    <p><?php echo htmlspecialchars($adr['code_postal'] . ' ' . $adr['ville']); ?></p>
                                    <?php if (!empty($adr['telephone'])): ?>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($adr['telephone']); ?></p>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-secondary" id="utiliserAdresseExistante">
                            <i class="fas fa-check"></i> Utiliser cette adresse
                        </button>
                        <div class="divider">
                            <span>Ou saisir une nouvelle adresse</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="adresseForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="prenom">Prénom *</label>
                                <input type="text" id="prenom" name="prenom" 
                                       value="<?php echo htmlspecialchars($_POST['prenom'] ?? $adresseLivraison['prenom'] ?? ''); ?>"
                                       class="<?php echo isset($errors['prenom']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['prenom'])): ?>
                                <span class="error-message"><?php echo $errors['prenom']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="nom">Nom *</label>
                                <input type="text" id="nom" name="nom" 
                                       value="<?php echo htmlspecialchars($_POST['nom'] ?? $adresseLivraison['nom'] ?? ''); ?>"
                                       class="<?php echo isset($errors['nom']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['nom'])): ?>
                                <span class="error-message"><?php echo $errors['nom']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="societe">Société (optionnel)</label>
                            <input type="text" id="societe" name="societe" 
                                   value="<?php echo htmlspecialchars($_POST['societe'] ?? $adresseLivraison['societe'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="adresse">Adresse *</label>
                            <input type="text" id="adresse" name="adresse" 
                                   placeholder="Numéro et nom de rue"
                                   value="<?php echo htmlspecialchars($_POST['adresse'] ?? $adresseLivraison['adresse'] ?? ''); ?>"
                                   class="<?php echo isset($errors['adresse']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['adresse'])): ?>
                            <span class="error-message"><?php echo $errors['adresse']; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="complement">Complément d'adresse (optionnel)</label>
                            <input type="text" id="complement" name="complement" 
                                   placeholder="Bâtiment, étage, appartement..."
                                   value="<?php echo htmlspecialchars($_POST['complement'] ?? $adresseLivraison['complement'] ?? ''); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="code_postal">Code postal *</label>
                                <input type="text" id="code_postal" name="code_postal" 
                                       maxlength="5"
                                       value="<?php echo htmlspecialchars($_POST['code_postal'] ?? $adresseLivraison['code_postal'] ?? ''); ?>"
                                       class="<?php echo isset($errors['code_postal']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['code_postal'])): ?>
                                <span class="error-message"><?php echo $errors['code_postal']; ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="ville">Ville *</label>
                                <input type="text" id="ville" name="ville" 
                                       value="<?php echo htmlspecialchars($_POST['ville'] ?? $adresseLivraison['ville'] ?? ''); ?>"
                                       class="<?php echo isset($errors['ville']) ? 'error' : ''; ?>">
                                <?php if (isset($errors['ville'])): ?>
                                <span class="error-message"><?php echo $errors['ville']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="pays">Pays *</label>
                            <select id="pays" name="pays">
                                <option value="France" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? 'France') === 'France' ? 'selected' : ''; ?>>France</option>
                                <option value="Belgique" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Belgique' ? 'selected' : ''; ?>>Belgique</option>
                                <option value="Suisse" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Suisse' ? 'selected' : ''; ?>>Suisse</option>
                                <option value="Luxembourg" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Luxembourg' ? 'selected' : ''; ?>>Luxembourg</option>
                                <option value="Canada" <?php echo ($_POST['pays'] ?? $adresseLivraison['pays'] ?? '') === 'Canada' ? 'selected' : ''; ?>>Canada</option>
                                <option value="autre">Autre pays</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="telephone">Téléphone *</label>
                            <input type="tel" id="telephone" name="telephone" 
                                   placeholder="06 12 34 56 78"
                                   value="<?php echo htmlspecialchars($_POST['telephone'] ?? $adresseLivraison['telephone'] ?? ''); ?>"
                                   class="<?php echo isset($errors['telephone']) ? 'error' : ''; ?>">
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

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left"></i> Retour au panier
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Continuer vers le paiement
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <!-- Footer identique aux autres pages -->
    </footer>

    <!-- Scripts -->
    <script src="js/main.js"></script>
    <script src="js/livraison.js"></script>
    <script>
        // Gestion des adresses existantes
        document.getElementById('utiliserAdresseExistante')?.addEventListener('click', function() {
            const selectedAddress = document.querySelector('input[name="adresse_existante"]:checked');
            if (selectedAddress) {
                // Récupérer l'ID de l'adresse
                const adresseId = selectedAddress.value;
                
                // Envoyer une requête AJAX pour récupérer les détails de l'adresse
                fetch(`/api/get_adresse.php?id=${adresseId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Mettre à jour les champs du formulaire
                            document.getElementById('prenom').value = data.adresse.prenom;
                            document.getElementById('nom').value = data.adresse.nom;
                            document.getElementById('societe').value = data.adresse.societe || '';
                            document.getElementById('adresse').value = data.adresse.adresse;
                            document.getElementById('complement').value = data.adresse.complement || '';
                            document.getElementById('code_postal').value = data.adresse.code_postal;
                            document.getElementById('ville').value = data.adresse.ville;
                            document.getElementById('pays').value = data.adresse.pays || 'France';
                            document.getElementById('telephone').value = data.adresse.telephone;
                            
                            // Afficher un message de succès
                            alert('Adresse chargée avec succès !');
                        } else {
                            alert('Erreur lors du chargement de l\'adresse');
                        }
                    })
                    .catch(error => {
                        console.error('Erreur:', error);
                        alert('Erreur de connexion au serveur');
                    });
            } else {
                alert('Veuillez sélectionner une adresse');
            }
        });

        // Formatage du téléphone
        document.getElementById('telephone')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) value = value.substr(0, 10);
            
            let formatted = '';
            for (let i = 0; i < value.length; i++) {
                if (i === 2 || i === 4 || i === 6 || i === 8) {
                    formatted += ' ';
                }
                formatted += value[i];
            }
            e.target.value = formatted;
        });

        // Validation du formulaire
        document.getElementById('adresseForm')?.addEventListener('submit', function(e) {
            const requiredFields = ['prenom', 'nom', 'adresse', 'code_postal', 'ville', 'telephone'];
            let isValid = true;
            
            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                const value = input.value.trim();
                
                if (!value) {
                    input.classList.add('error');
                    isValid = false;
                    
                    // Créer un message d'erreur s'il n'existe pas
                    if (!input.nextElementSibling || !input.nextElementSibling.classList.contains('error-message')) {
                        const errorSpan = document.createElement('span');
                        errorSpan.className = 'error-message';
                        errorSpan.textContent = 'Ce champ est obligatoire';
                        input.parentNode.appendChild(errorSpan);
                    }
                } else {
                    input.classList.remove('error');
                    // Supprimer le message d'erreur s'il existe
                    if (input.nextElementSibling && input.nextElementSibling.classList.contains('error-message')) {
                        input.nextElementSibling.remove();
                    }
                }
            });
            
            // Validation spécifique du code postal
            const codePostal = document.getElementById('code_postal');
            if (codePostal.value && !/^\d{5}$/.test(codePostal.value)) {
                codePostal.classList.add('error');
                isValid = false;
                
                if (!codePostal.nextElementSibling || !codePostal.nextElementSibling.classList.contains('error-message')) {
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'error-message';
                    errorSpan.textContent = 'Code postal invalide (5 chiffres)';
                    codePostal.parentNode.appendChild(errorSpan);
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez corriger les erreurs dans le formulaire');
            }
        });

        // Gestion des options de livraison
        document.querySelectorAll('input[name="mode_livraison"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Mettre à jour l'affichage
                document.querySelectorAll('.livraison-option').forEach(option => {
                    option.classList.remove('active');
                });
                this.closest('.livraison-option').classList.add('active');
                
                // Mettre à jour le prix total si nécessaire
                let fraisLivraison = 0;
                if (this.id === 'express') {
                    fraisLivraison = 9.90;
                } else if (this.id === 'point-relais') {
                    fraisLivraison = 4.90;
                }
                
                // Mettre à jour l'affichage du total
                const totalElement = document.querySelector('.recap-row.total span:last-child');
                const sousTotal = <?php echo $panierTotal; ?>;
                const total = sousTotal + fraisLivraison;
                
                if (totalElement) {
                    totalElement.textContent = total.toFixed(2).replace('.', ',') + ' €';
                }
            });
        });
    </script>
</body>
</html>