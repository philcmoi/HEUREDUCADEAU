<?php
session_start();

$erreurs = [];
$donnees = [];

// Traitement du formulaire soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupération et nettoyage des données
    $donnees = [
        'nom' => trim($_POST['nom'] ?? ''),
        'adresse' => trim($_POST['adresse'] ?? ''),
        'ville' => trim($_POST['ville'] ?? ''),
        'code_postal' => trim($_POST['code_postal'] ?? ''),
        'pays' => trim($_POST['pays'] ?? 'France'),
        'telephone' => trim($_POST['telephone'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'instructions' => trim($_POST['instructions'] ?? '')
    ];
    
    // Validation
    if (empty($donnees['nom'])) $erreurs[] = "Le nom est requis";
    if (empty($donnees['adresse'])) $erreurs[] = "L'adresse est requise";
    if (empty($donnees['ville'])) $erreurs[] = "La ville est requise";
    if (empty($donnees['code_postal'])) $erreurs[] = "Le code postal est requis";
    if (!filter_var($donnees['email'], FILTER_VALIDATE_EMAIL)) $erreurs[] = "Email invalide";
    
    // Si pas d'erreurs, enregistrer et rediriger
    if (empty($erreurs)) {
        // Enregistrement en session
        $_SESSION['adresse_livraison'] = $donnees;
        
        // Historique (conserver le passé)
        if (!isset($_SESSION['historique_adresses'])) {
            $_SESSION['historique_adresses'] = [];
        }
        
        // Vérifier si l'adresse existe déjà dans l'historique
        $adresse_existe = false;
        foreach ($_SESSION['historique_adresses'] as $adresse) {
            if ($adresse['adresse'] === $donnees['adresse'] && 
                $adresse['code_postal'] === $donnees['code_postal']) {
                $adresse_existe = true;
                break;
            }
        }
        
        if (!$adresse_existe) {
            $_SESSION['historique_adresses'][] = $donnees;
            // Garder seulement les 5 dernières
            if (count($_SESSION['historique_adresses']) > 5) {
                array_shift($_SESSION['historique_adresses']);
            }
        }
        
        // Redirection vers l'étape suivante
        header('Location: paiement.php');
        exit();
    }
} else {
    // Si pas de POST, pré-remplir avec les données existantes
    if (isset($_SESSION['adresse_livraison'])) {
        $donnees = $_SESSION['adresse_livraison'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Adresse de Livraison</title>
    <style>
        body { font-family: Arial; max-width: 500px; margin: 50px auto; padding: 20px; }
        .erreur { color: #d00; padding: 10px; background: #fee; border-radius: 4px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        .info { background: #e7f3fe; padding: 10px; border-left: 4px solid #2196F3; margin-bottom: 20px; }
        .historique { margin-top: 30px; padding: 15px; background: #f9f9f9; }
        .adresse-historique { padding: 10px; margin: 5px 0; background: white; border: 1px solid #eee; cursor: pointer; }
        .adresse-historique:hover { background: #eef; }
    </style>
</head>
<body>
    <h1>Adresse de Livraison</h1>
    
    <?php if (isset($_SESSION['adresse_livraison'])): ?>
        <div class="info">
            <strong>Vous avez déjà une adresse enregistrée.</strong><br>
            Vous pouvez la modifier ci-dessous ou <a href="paiement.php">continuer avec cette adresse</a>.
        </div>
    <?php endif; ?>
    
    <?php if (!empty($erreurs)): ?>
        <div class="erreur">
            <strong>Veuillez corriger les erreurs suivantes :</strong>
            <ul>
                <?php foreach ($erreurs as $erreur): ?>
                    <li><?php echo htmlspecialchars($erreur); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="nom">Nom complet *</label>
            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($donnees['nom'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="adresse">Adresse *</label>
            <textarea id="adresse" name="adresse" rows="3" required><?php echo htmlspecialchars($donnees['adresse'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label for="ville">Ville *</label>
            <input type="text" id="ville" name="ville" value="<?php echo htmlspecialchars($donnees['ville'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="code_postal">Code postal *</label>
            <input type="text" id="code_postal" name="code_postal" value="<?php echo htmlspecialchars($donnees['code_postal'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="pays">Pays *</label>
            <input type="text" id="pays" name="pays" value="<?php echo htmlspecialchars($donnees['pays'] ?? 'France'); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="telephone">Téléphone *</label>
            <input type="tel" id="telephone" name="telephone" value="<?php echo htmlspecialchars($donnees['telephone'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($donnees['email'] ?? ''); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="instructions">Instructions de livraison</label>
            <textarea id="instructions" name="instructions" rows="2"><?php echo htmlspecialchars($donnees['instructions'] ?? ''); ?></textarea>
        </div>
        
        <button type="submit">Continuer vers le paiement</button>
    </form>
    
    <?php if (isset($_SESSION['historique_adresses']) && count($_SESSION['historique_adresses']) > 0): ?>
        <div class="historique">
            <h3>Vos adresses récentes</h3>
            <?php foreach ($_SESSION['historique_adresses'] as $index => $adresse_hist): ?>
                <div class="adresse-historique" onclick="remplirFormulaire(<?php echo $index; ?>)">
                    <strong><?php echo htmlspecialchars($adresse_hist['nom']); ?></strong><br>
                    <?php echo htmlspecialchars($adresse_hist['adresse']); ?><br>
                    <?php echo htmlspecialchars($adresse_hist['code_postal']) . ' ' . htmlspecialchars($adresse_hist['ville']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <script>
        function remplirFormulaire(index) {
            fetch('get_adresse.php?index=' + index)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('nom').value = data.adresse.nom;
                        document.getElementById('adresse').value = data.adresse.adresse;
                        document.getElementById('ville').value = data.adresse.ville;
                        document.getElementById('code_postal').value = data.adresse.code_postal;
                        document.getElementById('pays').value = data.adresse.pays;
                        document.getElementById('telephone').value = data.adresse.telephone;
                        document.getElementById('email').value = data.adresse.email;
                        document.getElementById('instructions').value = data.adresse.instructions;
                    }
                });
        }
        </script>
    <?php endif; ?>
</body>
</html>