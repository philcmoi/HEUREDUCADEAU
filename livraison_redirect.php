<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Adresse de Livraison - HEURE DU CADEAU</title>
    <style>
      /* CSS inchangé - préservé */
      body {
        font-family: Arial, sans-serif;
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
        background: #f8f9fa;
      }

      .container {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      }

      h1 {
        color: #333;
        border-bottom: 2px solid #5a67d8;
        padding-bottom: 10px;
        margin-bottom: 30px;
      }

      h2 {
        color: #555;
        font-size: 18px;
        margin: 25px 0 15px 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
      }

      .form-group {
        margin-bottom: 20px;
      }

      label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
      }

      .required:after {
        content: " *";
        color: #e53e3e;
      }

      input,
      textarea,
      select {
        width: 100%;
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 16px;
        box-sizing: border-box;
        transition: border 0.3s;
      }

      input:focus,
      textarea:focus,
      select:focus {
        outline: none;
        border-color: #5a67d8;
        box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
      }

      .form-row {
        display: flex;
        gap: 15px;
      }

      .form-row .form-group {
        flex: 1;
      }

      .radio-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 15px;
        background: #f7fafc;
        border-radius: 8px;
        margin-bottom: 20px;
      }

      .radio-option {
        display: flex;
        align-items: center;
        padding: 10px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
      }

      .radio-option:hover {
        border-color: #cbd5e0;
        background: #edf2f7;
      }

      .radio-option.selected {
        border-color: #5a67d8;
        background: rgba(90, 103, 216, 0.05);
      }

      .radio-option input {
        width: auto;
        margin-right: 10px;
      }

      .radio-details {
        flex: 1;
      }

      .radio-price {
        font-weight: bold;
        color: #2d3748;
      }

      .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 15px;
        background: #f0fff4;
        border: 1px solid #9ae6b4;
        border-radius: 8px;
        margin-bottom: 20px;
      }

      .checkbox-group input {
        width: auto;
      }

      button {
        background-color: #5a67d8;
        color: white;
        padding: 15px 30px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        width: 100%;
        transition: all 0.3s;
        margin-top: 20px;
      }

      button:hover {
        background-color: #4c51bf;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(90, 103, 216, 0.3);
      }

      .message {
        padding: 15px;
        margin-bottom: 25px;
        border-radius: 8px;
        border: 1px solid transparent;
      }

      .success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
      }

      .error {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
      }

      .info {
        background-color: #d1ecf1;
        color: #0c5460;
        border-color: #bee5eb;
      }

      .shipping-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: #f7fafc;
        border-radius: 6px;
        margin-top: 5px;
        font-size: 14px;
        color: #718096;
      }

      .shipping-info i {
        color: #38a169;
      }

      .error-field {
        border-color: #e53e3e !important;
      }

      .error-message {
        color: #e53e3e;
        font-size: 14px;
        margin-top: 5px;
        display: none;
      }

      .error-message.show {
        display: block;
      }

      @media (max-width: 768px) {
        .form-row {
          flex-direction: column;
          gap: 0;
        }

        body {
          padding: 20px;
          margin: 20px auto;
        }
      }
    </style>
  </head>
  <body>
    <div class="container">
      <h1><i class="fas fa-truck"></i> Adresse de Livraison</h1>

      <!-- Messages d'information -->
      <div id="info-message"></div>

      <!-- Messages d'erreur depuis session PHP -->
      <?php 
      session_start();






// livraison_redirect.php
header('Location: livraison.php');
exit();









      if (isset($_SESSION['erreurs_livraison'])): ?>
      <div class="message error">
        <strong>Erreurs :</strong>
        <ul>
          <?php foreach ($_SESSION['erreurs_livraison'] as $erreur): ?>
          <li><?php echo htmlspecialchars($erreur, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php 
      unset($_SESSION['erreurs_livraison']);
      endif; ?>

      <!-- Pré-remplissage depuis session PHP -->
      <?php
      $donnees_saisies = $_SESSION['donnees_saisies'] ?? [];
      unset($_SESSION['donnees_saisies']);
      ?>

      <form action="livraison.php" method="POST" id="livraison-form">
        <!-- Champ caché pour détecter le mode -->
        <input type="hidden" name="api_mode" value="1" />

        <h2>Informations personnelles</h2>

        <div class="form-row">
          <div class="form-group">
            <label for="prenom" class="required">Prénom</label>
            <input 
              type="text" 
              id="prenom" 
              name="prenom" 
              value="<?php echo htmlspecialchars($donnees_saisies['prenom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
              required 
            />
            <div class="error-message" id="error-prenom"></div>
          </div>
          <div class="form-group">
            <label for="nom" class="required">Nom</label>
            <input 
              type="text" 
              id="nom" 
              name="nom" 
              value="<?php echo htmlspecialchars($donnees_saisies['nom'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
              required 
            />
            <div class="error-message" id="error-nom"></div>
          </div>
        </div>

        <div class="form-group">
          <label for="email" class="required">Email</label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            value="<?php echo htmlspecialchars($donnees_saisies['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
            required 
          />
          <div class="error-message" id="error-email"></div>
          <div class="shipping-info">
            <i class="fas fa-info-circle"></i>
            Votre confirmation de commande sera envoyée à cette adresse
          </div>
        </div>

        <div class="form-group">
          <label for="telephone">Téléphone</label>
          <input 
            type="tel" 
            id="telephone" 
            name="telephone" 
            value="<?php echo htmlspecialchars($donnees_saisies['telephone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
            
          />
          <div class="error-message" id="error-telephone"></div>
          <div class="shipping-info">
            <i class="fas fa-info-circle"></i>
            Pour vous contacter en cas de problème de livraison
          </div>
        </div>

        <div class="form-group">
          <label for="societe">Société (optionnel)</label>
          <input 
            type="text" 
            id="societe" 
            name="societe" 
            value="<?php echo htmlspecialchars($donnees_saisies['societe'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
          />
        </div>

        <h2>Adresse de livraison</h2>

        <div class="form-group">
          <label for="adresse" class="required">Adresse</label>
          <textarea 
            id="adresse" 
            name="adresse" 
            rows="3" 
            required
          ><?php echo htmlspecialchars($donnees_saisies['adresse'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          <div class="error-message" id="error-adresse"></div>
        </div>

        <div class="form-group">
          <label for="complement">Complément d'adresse (appartement, étage, etc.)</label>
          <input 
            type="text" 
            id="complement" 
            name="complement" 
            value="<?php echo htmlspecialchars($donnees_saisies['complement'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
          />
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="code_postal" class="required">Code postal</label>
            <input 
              type="text" 
              id="code_postal" 
              name="code_postal" 
              value="<?php echo htmlspecialchars($donnees_saisies['code_postal'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
              required 
            />
            <div class="error-message" id="error-code_postal"></div>
          </div>
          <div class="form-group">
            <label for="ville" class="required">Ville</label>
            <input 
              type="text" 
              id="ville" 
              name="ville" 
              value="<?php echo htmlspecialchars($donnees_saisies['ville'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
              required 
            />
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

        <h2>Options de livraison</h2>

        <div class="radio-group" id="livraisonOptions">
          <div class="radio-option selected" data-value="standard">
            <input
              type="radio"
              name="mode_livraison"
              value="standard"
              checked
              hidden
            />
            <div class="radio-details">
              <strong>Livraison Standard</strong>
              <p>Livraison en 3-5 jours ouvrés</p>
            </div>
            <div class="radio-price">Gratuite</div>
          </div>

          <div class="radio-option" data-value="express">
            <input type="radio" name="mode_livraison" value="express" hidden />
            <div class="radio-details">
              <strong>Livraison Express</strong>
              <p>Livraison en 24h (hors week-end)</p>
            </div>
            <div class="radio-price">9,90 €</div>
          </div>

          <div class="radio-option" data-value="relais">
            <input type="radio" name="mode_livraison" value="relais" hidden />
            <div class="radio-details">
              <strong>Point Relais</strong>
              <p>Retrait dans un point relais partenaire</p>
            </div>
            <div class="radio-price">4,90 €</div>
          </div>
        </div>

        <h2>Options supplémentaires</h2>

        <div class="checkbox-group">
          <input
            type="checkbox"
            id="emballage_cadeau"
            name="emballage_cadeau"
            value="1"
            <?php echo (isset($donnees_saisies['emballage_cadeau']) && $donnees_saisies['emballage_cadeau']) ? 'checked' : ''; ?>
          />
          <div>
            <label for="emballage_cadeau" style="font-weight: bold">
              <i class="fas fa-gift"></i> Emballage cadeau
            </label>
            <p style="margin: 5px 0 0 0; color: #718096; font-size: 14px">
              Emballage élégant avec carte personnalisée -
              <strong>+3,90 €</strong>
            </p>
          </div>
        </div>

        <div class="form-group">
          <label for="instructions">Instructions de livraison (optionnel)</label>
          <textarea
            id="instructions"
            name="instructions"
            rows="2"
            placeholder="Ex: Sonner au portail rouge, livrer au gardien, etc."
          ><?php echo htmlspecialchars($donnees_saisies['instructions'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>

        <button type="submit" id="submit-btn">
          <i class="fas fa-arrow-right"></i> Continuer vers le paiement
        </button>

        <div
          style="
            text-align: center;
            margin-top: 20px;
            color: #718096;
            font-size: 14px;
          "
        >
          <i class="fas fa-lock"></i> Vos données sont protégées et ne seront
          pas partagées avec des tiers
        </div>
      </form>
    </div>

    <!-- Font Awesome pour les icônes -->
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />

    <script>
      // Vérifier si une adresse existe déjà
      document.addEventListener("DOMContentLoaded", function () {
        let isLoading = false;
        
        // Fonction pour charger l'adresse existante
        function loadExistingAddress() {
          // Essayer d'abord l'API moderne
          fetch("livraison.php?api=1")
            .then(response => {
              if (!response.ok) {
                throw new Error('Erreur réseau');
              }
              return response.json();
            })
            .then(data => {
              if (data.success && data.hasAddress && data.adresse) {
                displayExistingAddress(data.adresse);
              } else {
                // Fallback : essayer les données session PHP
                try {
                  const addressData = <?php
                    if (isset($_SESSION['adresse_livraison'])) {
                      echo json_encode($_SESSION['adresse_livraison']);
                    } else {
                      echo 'null';
                    }
                  ?>;
                  
                  if (addressData) {
                    displayExistingAddress(addressData);
                  }
                } catch (e) {
                  console.log("Aucune adresse trouvée");
                }
              }
            })
            .catch(error => {
              console.log("API non disponible, utilisation des données session");
              // Fallback aux données session PHP
              try {
                const addressData = <?php
                  if (isset($_SESSION['adresse_livraison'])) {
                    echo json_encode($_SESSION['adresse_livraison']);
                  } else {
                    echo 'null';
                  }
                ?>;
                
                if (addressData) {
                  displayExistingAddress(addressData);
                }
              } catch (e) {
                console.log("Aucune adresse en session");
              }
            });
        }
        
        // Charger l'adresse existante au démarrage
        loadExistingAddress();

        // Gestion des options de livraison
        document.querySelectorAll('.radio-option').forEach(option => {
          option.addEventListener('click', function() {
            // Désélectionner toutes les options
            document.querySelectorAll('.radio-option').forEach(opt => {
              opt.classList.remove('selected');
            });

            // Sélectionner celle cliquée
            this.classList.add('selected');

            // Cochez le radio correspondant
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
              radio.checked = true;
            }
          });
        });

        // Fonction de validation des champs
        function validateField(fieldId, errorId) {
          const field = document.getElementById(fieldId);
          const error = document.getElementById(errorId);
          
          if (!field.value.trim()) {
            field.classList.add('error-field');
            error.textContent = 'Ce champ est requis';
            error.classList.add('show');
            return false;
          } else {
            field.classList.remove('error-field');
            error.classList.remove('show');
            
            // Validation spécifique pour email
            if (fieldId === 'email') {
              const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
              if (!emailRegex.test(field.value)) {
                field.classList.add('error-field');
                error.textContent = 'Veuillez entrer une adresse email valide';
                error.classList.add('show');
                return false;
              }
            }
            
            // Validation spécifique pour téléphone (format français)
            if (fieldId === 'telephone') {
              const phoneRegex = /^[0-9]{10}$/;
              const cleanedPhone = field.value.replace(/\s/g, '');
              if (!phoneRegex.test(cleanedPhone)) {
                field.classList.add('error-field');
                error.textContent = 'Veuillez entrer un numéro de téléphone valide (10 chiffres)';
                error.classList.add('show');
                return false;
              }
            }
            
            // Validation spécifique pour code postal (format français)
            if (fieldId === 'code_postal') {
              const cpRegex = /^[0-9]{5}$/;
              if (!cpRegex.test(field.value)) {
                field.classList.add('error-field');
                error.textContent = 'Veuillez entrer un code postal valide (5 chiffres)';
                error.classList.add('show');
                return false;
              }
            }
            
            return true;
          }
        }

        // Fonction de validation globale
        function validateForm() {
          const fields = [
            { id: 'nom', error: 'error-nom' },
            { id: 'prenom', error: 'error-prenom' },
            { id: 'adresse', error: 'error-adresse' },
            { id: 'code_postal', error: 'error-code_postal' },
            { id: 'ville', error: 'error-ville' },
            { id: 'email', error: 'error-email' },
            { id: 'telephone', error: 'error-telephone' }
          ];
          
          let isValid = true;
          
          fields.forEach(field => {
            if (!validateField(field.id, field.error)) {
              isValid = false;
            }
          });
          
          return isValid;
        }

        // Soumission du formulaire
        document.getElementById('livraison-form').addEventListener('submit', async function(e) {
          e.preventDefault();
          
          if (isLoading) return;
          
          // Validation
          if (!validateForm()) {
            return;
          }
          
          isLoading = true;
          const submitBtn = document.getElementById('submit-btn');
          const originalText = submitBtn.innerHTML;
          submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
          submitBtn.disabled = true;
          
          // Préparer les données
          const formData = new FormData(this);
          const data = {};
          formData.forEach((value, key) => {
            // Gérer les checkbox
            if (key === 'emballage_cadeau') {
              data[key] = value === '1' ? '1' : '0';
            } else {
              data[key] = value;
            }
          });
          
          try {
            // Essayer l'API moderne d'abord
            const response = await fetch('livraison.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-API-Mode': '1'
              },
              body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
              // Redirection selon le système disponible
              if (result.redirect && await checkPageExists(result.redirect)) {
                window.location.href = result.redirect;
              } else if (result.compat_redirect && await checkPageExists(result.compat_redirect)) {
                window.location.href = result.compat_redirect;
              } else if (await checkPageExists('paiement.html')) {
                window.location.href = 'paiement.html';
              } else if (await checkPageExists('paiement.php')) {
                window.location.href = 'paiement.php';
              } else {
                // Fallback ultime : soumission traditionnelle
                console.warn('Aucune page de paiement trouvée, fallback à la soumission traditionnelle');
                this.submit();
              }
            } else {
              // Afficher les erreurs de validation
              if (result.missing && result.missing.length > 0) {
                result.missing.forEach(field => {
                  const errorId = 'error-' + field;
                  const errorElement = document.getElementById(errorId);
                  const fieldElement = document.getElementById(field);
                  if (errorElement && fieldElement) {
                    fieldElement.classList.add('error-field');
                    errorElement.textContent = 'Ce champ est requis';
                    errorElement.classList.add('show');
                  }
                });
                
                // Faire défiler jusqu'au premier champ manquant
                const firstMissingField = document.getElementById(result.missing[0]);
                if (firstMissingField) {
                  firstMissingField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
              }
              
              // Afficher un message d'erreur général
              const messageDiv = document.getElementById('info-message');
              messageDiv.className = 'message error';
              messageDiv.innerHTML = `
                <strong><i class="fas fa-exclamation-triangle"></i> Erreur :</strong><br>
                ${result.message || 'Une erreur est survenue'}
              `;
              
              isLoading = false;
              submitBtn.innerHTML = originalText;
              submitBtn.disabled = false;
            }
          } catch (error) {
            console.error('Erreur API:', error);
            // Fallback: soumission traditionnelle
            this.submit();
          }
        });

        // Vérifier si une page existe
        async function checkPageExists(url) {
          try {
            const response = await fetch(url, { method: 'HEAD' });
            return response.ok;
          } catch {
            return false;
          }
        }

        // Ajouter des écouteurs d'événements pour la validation en temps réel
        const fieldsToValidate = ['nom', 'prenom', 'adresse', 'code_postal', 'ville', 'email', 'telephone'];
        fieldsToValidate.forEach(fieldId => {
          const field = document.getElementById(fieldId);
          if (field) {
            field.addEventListener('blur', () => {
              const errorId = 'error-' + fieldId;
              validateField(fieldId, errorId);
            });
            
            field.addEventListener('input', () => {
              const errorId = 'error-' + fieldId;
              const error = document.getElementById(errorId);
              field.classList.remove('error-field');
              error.classList.remove('show');
            });
          }
        });
      });

      function displayExistingAddress(address) {
        const messageDiv = document.getElementById('info-message');
        messageDiv.className = 'message success';
        messageDiv.innerHTML = `
          <strong><i class="fas fa-check-circle"></i> Adresse déjà enregistrée :</strong><br>
          ${address.prenom || ''} ${address.nom || ''}<br>
          ${address.adresse || ''}<br>
          ${address.complement ? address.complement + '<br>' : ''}
          ${address.code_postal || ''} ${address.ville || ''}<br>
          ${address.pays || 'France'}<br>
          <small>Vous pouvez modifier ces informations ci-dessous si nécessaire.</small>
        `;

        // Pré-remplir le formulaire
        const fields = ['prenom', 'nom', 'adresse', 'complement', 'code_postal', 'ville', 'pays', 'telephone', 'email', 'societe', 'instructions'];
        fields.forEach(field => {
          const input = document.getElementById(field);
          if (input && address[field]) {
            input.value = address[field];
          }
        });

        // Pré-sélectionner le mode de livraison
        if (address.mode_livraison) {
          const options = document.querySelectorAll('.radio-option');
          options.forEach(option => {
            if (option.getAttribute('data-value') === address.mode_livraison) {
              option.click();
            }
          });
        }

        // Cocher l'emballage cadeau
        if (address.emballage_cadeau) {
          document.getElementById('emballage_cadeau').checked = true;
        }
      }
    </script>
  </body>
</html>