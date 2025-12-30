<?php
ob_start(); // Début du buffering pour éviter les erreurs de headers
session_start();

// ============================================
// VÉRIFICATION RENFORCÉE DE L'ACCÈS
// ============================================

// Vérifier si on peut afficher la page - plusieurs conditions possibles
$canDisplay = false;

// Condition 1: Vérifier via les données créées par livraison.php
if (isset($_SESSION['livraison_data']) && isset($_SESSION['commande'])) {
    $canDisplay = true;
}

// Condition 2: Vérifier via le panier (fallback)
if (!$canDisplay && isset($_SESSION['panier']) && !empty($_SESSION['panier']['items'])) {
    // Vérifier si on a au moins une adresse de livraison basique
    if (isset($_SESSION['adresse_livraison']) || isset($_SESSION['donnees_livraison'])) {
        $canDisplay = true;
    }
}

// Condition 3: Vérifier via l'autorisation de checkout
if (!$canDisplay && isset($_SESSION['checkout_authorized']) && $_SESSION['checkout_authorized'] === true) {
    // Vérifier si l'autorisation n'a pas expiré (10 minutes)
    if (isset($_SESSION['checkout_time']) && (time() - $_SESSION['checkout_time']) <= 600) {
        $canDisplay = true;
    } else {
        // L'autorisation a expiré
        unset($_SESSION['checkout_authorized']);
        unset($_SESSION['checkout_time']);
    }
}

// Si aucune condition n'est remplie, rediriger vers panier
if (!$canDisplay) {
    ob_end_clean();
    // Si c'est une requête AJAX
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé. Veuillez d\'abord compléter vos informations de livraison.',
            'redirect' => 'panier.php'
        ]);
        exit();
    }
    // Redirection normale
    header('Location: panier.php');
    exit();
}

// ============================================
// PRÉPARATION DES DONNÉES POUR L'AFFICHAGE
// ============================================

// Récupérer les données de la commande
$commande_data = $_SESSION['commande'] ?? [];
$panier_data = $_SESSION['panier'] ?? ['items' => [], 'sous_total' => 0, 'items_count' => 0];
$livraison_data = $_SESSION['livraison_data'] ?? [];

// Préparer les totaux
$sous_total = floatval($panier_data['sous_total'] ?? 0);
$frais_livraison = floatval($commande_data['livraison']['frais'] ?? 0);
$frais_emballage = floatval($commande_data['frais_emballage'] ?? 0);
$total = $sous_total + $frais_livraison + $frais_emballage;

// Récupérer l'adresse de livraison
$adresse_livraison = [];
if (isset($commande_data['adresse_livraison'])) {
    $adresse_livraison = $commande_data['adresse_livraison'];
} elseif (isset($_SESSION['adresse_livraison'])) {
    $adresse_livraison = $_SESSION['adresse_livraison'];
} elseif (isset($_SESSION['donnees_livraison'])) {
    $adresse_livraison = $_SESSION['donnees_livraison'];
}

// ============================================
// FONCTIONS UTILITAIRES POUR L'AFFICHAGE
// ============================================

function getAdresseDisplay($adresse) {
    if (empty($adresse)) return '';
    
    $html = '<div style="display: flex; justify-content: space-between; align-items: start;">';
    $html .= '<div>';
    $html .= '<p class="adresse-line"><strong>' . htmlspecialchars(($adresse['prenom'] ?? '') . ' ' . ($adresse['nom'] ?? '')) . '</strong></p>';
    $html .= '<p class="adresse-line">' . htmlspecialchars($adresse['adresse'] ?? '') . '</p>';
    
    if (!empty($adresse['complement'])) {
        $html .= '<p class="adresse-line">' . htmlspecialchars($adresse['complement']) . '</p>';
    }
    
    $html .= '<p class="adresse-line">' . htmlspecialchars(($adresse['code_postal'] ?? '') . ' ' . ($adresse['ville'] ?? '')) . '</p>';
    $html .= '<p class="adresse-line">' . htmlspecialchars($adresse['pays'] ?? 'France') . '</p>';
    
    if (!empty($adresse['telephone'])) {
        $html .= '<p class="adresse-line"><i class="fas fa-phone"></i> ' . htmlspecialchars($adresse['telephone']) . '</p>';
    }
    
    if (!empty($adresse['email'])) {
        $html .= '<p class="adresse-line"><i class="fas fa-envelope"></i> ' . htmlspecialchars($adresse['email']) . '</p>';
    }
    
    $html .= '</div>';
    $html .= '<a href="livraison_form.php" style="color: #5a67d8; text-decoration: none;">';
    $html .= '<i class="fas fa-edit"></i> Modifier';
    $html .= '</a>';
    $html .= '</div>';
    
    return $html;
}

function getSummaryDisplay($sous_total, $frais_livraison, $frais_emballage, $total) {
    $html = '<div class="summary-item">';
    $html .= '<span>Sous-total</span>';
    $html .= '<span>' . number_format($sous_total, 2, ',', ' ') . ' €</span>';
    $html .= '</div>';
    
    $html .= '<div class="summary-item">';
    $html .= '<span>Livraison</span>';
    $html .= '<span>' . number_format($frais_livraison, 2, ',', ' ') . ' €</span>';
    $html .= '</div>';
    
    if ($frais_emballage > 0) {
        $html .= '<div class="summary-item">';
        $html .= '<span>Emballage cadeau</span>';
        $html .= '<span>' . number_format($frais_emballage, 2, ',', ' ') . ' €</span>';
        $html .= '</div>';
    }
    
    $html .= '<div class="summary-item total">';
    $html .= '<span>Total</span>';
    $html .= '<span>' . number_format($total, 2, ',', ' ') . ' €</span>';
    $html .= '</div>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Paiement - HEURE DU CADEAU</title>
    <link rel="stylesheet" href="css/style.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <style>
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

        <div class="paiement-container">
          <!-- Section paiement -->
          <div class="paiement-section">
            <h2 class="section-title">
              <i class="fas fa-credit-card"></i> Mode de paiement
            </h2>

            <!-- Adresse de livraison -->
            <div class="adresse-info" id="adresseDisplay">
              <?php echo getAdresseDisplay($adresse_livraison); ?>
            </div>

            <!-- Options de paiement -->
            <div class="paiement-options">
              <!-- PayPal -->
              <div class="paiement-option selected" id="optionPaypal">
                <div class="option-header">
                  <input
                    type="radio"
                    name="paiement"
                    id="paypal"
                    value="paypal"
                    checked
                    hidden
                  />
                  <img
                    src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg"
                    alt="PayPal"
                  />
                  <span style="font-weight: 600; color: #2d3748">PayPal</span>
                </div>
                <div class="option-body">
                  <p>
                    <i class="fas fa-check-circle"></i> Paiement sécurisé par
                    carte bancaire
                  </p>
                  <p>
                    <i class="fas fa-check-circle"></i> Pas besoin de compte
                    PayPal
                  </p>
                  <p>
                    <i class="fas fa-check-circle"></i> Protection de l'acheteur
                    incluse
                  </p>

                  <div id="paypal-button-container"></div>
                </div>
              </div>

              <!-- Carte bancaire -->
              <div class="paiement-option" id="optionCarte">
                <div class="option-header">
                  <input
                    type="radio"
                    name="paiement"
                    id="carte"
                    value="carte"
                    hidden
                  />
                  <i
                    class="fas fa-credit-card"
                    style="font-size: 24px; color: #718096"
                  ></i>
                  <span style="font-weight: 600; color: #2d3748"
                    >Carte bancaire</span
                  >
                </div>
                <div class="option-body">
                  <p>Paiement sécurisé via notre système</p>
                  <div style="display: flex; gap: 15px; margin: 15px 0">
                    <i
                      class="fab fa-cc-visa"
                      style="font-size: 32px; color: #1434cb"
                    ></i>
                    <i
                      class="fab fa-cc-mastercard"
                      style="font-size: 32px; color: #eb001b"
                    ></i>
                    <i
                      class="fab fa-cc-amex"
                      style="font-size: 32px; color: #2e77bc"
                    ></i>
                  </div>

                  <form id="cardForm" style="display: none">
                    <div class="form-group">
                      <label for="cardNumber">Numéro de carte</label>
                      <input
                        type="text"
                        id="cardNumber"
                        placeholder="1234 5678 9012 3456"
                        maxlength="19"
                      />
                    </div>
                    <div class="form-row">
                      <div class="form-group">
                        <label for="cardExpiry">Date d'expiration</label>
                        <input
                          type="text"
                          id="cardExpiry"
                          placeholder="MM/AA"
                          maxlength="5"
                        />
                      </div>
                      <div class="form-group">
                        <label for="cardCVC">CVC</label>
                        <input
                          type="text"
                          id="cardCVC"
                          placeholder="123"
                          maxlength="3"
                        />
                      </div>
                    </div>
                    <div class="form-group">
                      <label for="cardName">Nom sur la carte</label>
                      <input
                        type="text"
                        id="cardName"
                        placeholder="JEAN DUPONT"
                      />
                    </div>

                    <button
                      type="button"
                      class="btn btn-primary"
                      id="submitCard"
                    >
                      <i class="fas fa-lock"></i> Payer avec ma carte
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
              Paiement 100% sécurisé - Vos données sont cryptées
            </p>
          </div>

          <!-- Récapitulatif -->
          <div class="recap-section">
            <h3 class="section-title">
              <i class="fas fa-receipt"></i> Récapitulatif
            </h3>

            <div id="commandeDetails">
              <div style="margin-bottom: 20px;">
                <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                  <i class="fas fa-box"></i> <?php echo $panier_data['items_count']; ?> article(s)
                </p>
                <p style="color: #718096; font-size: 14px;">
                  <i class="fas fa-truck"></i> Livraison <?php echo $commande_data['livraison']['mode'] ?? 'standard'; ?>
                </p>
                <?php if (($commande_data['emballage_cadeau'] ?? 0) == 1): ?>
                  <p style="color: #718096; font-size: 14px;">
                    <i class="fas fa-gift"></i> Emballage cadeau
                  </p>
                <?php endif; ?>
              </div>
            </div>

            <div class="summary-details" id="summaryDetails">
              <?php echo getSummaryDisplay($sous_total, $frais_livraison, $frais_emballage, $total); ?>
            </div>

            <div
              style="
                margin-top: 20px;
                padding: 15px;
                background: #f7fafc;
                border-radius: 8px;
              "
            >
              <p style="font-size: 12px; color: #718096; margin: 0">
                <i class="fas fa-info-circle"></i>
                Vous recevrez un email de confirmation après le paiement.
              </p>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Footer -->
    <?php include 'partials/footer.php'; ?>

    <!-- PayPal SDK (version test pour développement) -->
    <script src="https://www.paypal.com/sdk/js?client-id=test&currency=EUR"></script>

    <script>
      // Configuration
      const API_BASE_URL = "/api";
      let paiementMethod = "paypal";

      // Récupérer les données de session pour JavaScript
      const commandeData = <?php echo json_encode($commande_data); ?>;
      const panierData = <?php echo json_encode($panier_data); ?>;
      const livraisonData = <?php echo json_encode($livraison_data); ?>;
      
      // Calculer les totaux côté client pour PayPal
      const sousTotal = parseFloat(<?php echo $sous_total; ?>);
      const fraisLivraison = parseFloat(<?php echo $frais_livraison; ?>);
      const fraisEmballage = parseFloat(<?php echo $frais_emballage; ?>);
      const total = sousTotal + fraisLivraison + fraisEmballage;

      // Initialiser PayPal si le total > 0
      if (total > 0) {
        paypal
          .Buttons({
            style: {
              layout: "vertical",
              color: "gold",
              shape: "rect",
              label: "paypal",
            },
            createOrder: function (data, actions) {
              return actions.order.create({
                purchase_units: [
                  {
                    amount: {
                      value: total.toFixed(2),
                      currency_code: "EUR",
                    },
                  },
                ],
              });
            },
            onApprove: function (data, actions) {
              return actions.order.capture().then(function (details) {
                // Paiement réussi - créer la commande
                finaliserCommande("paypal", details.id);
              });
            },
            onError: function (err) {
              console.error("Erreur PayPal:", err);
              alert(
                "Une erreur est survenue avec PayPal. Veuillez réessayer ou choisir un autre mode de paiement."
              );
            },
          })
          .render("#paypal-button-container");
      }

      // Gestion des options de paiement
      document.querySelectorAll(".paiement-option").forEach((option) => {
        option.addEventListener("click", function () {
          // Désélectionner toutes les options
          document.querySelectorAll(".paiement-option").forEach((opt) => {
            opt.classList.remove("selected");
          });

          // Sélectionner l'option cliquée
          this.classList.add("selected");

          // Mettre à jour la méthode de paiement
          const input = this.querySelector('input[type="radio"]');
          if (input) {
            input.checked = true;
            paiementMethod = input.value;

            // Afficher/masquer le formulaire carte
            if (paiementMethod === "carte") {
              document.getElementById("cardForm").style.display = "block";
              document.getElementById("paypal-button-container").style.display = "none";
            } else {
              document.getElementById("cardForm").style.display = "none";
              document.getElementById("paypal-button-container").style.display = "block";
            }
          }
        });
      });

      // Paiement par carte
      document.getElementById("submitCard")?.addEventListener("click", function () {
        const cardNumber = document.getElementById("cardNumber").value.replace(/\s/g, "");
        const cardExpiry = document.getElementById("cardExpiry").value;
        const cardCVC = document.getElementById("cardCVC").value;
        const cardName = document.getElementById("cardName").value.trim();

        // Validation simple
        if (!cardNumber || cardNumber.length < 16) {
          alert("Numéro de carte invalide");
          return;
        }

        if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
          alert("Date d'expiration invalide (format MM/AA)");
          return;
        }

        if (!cardCVC || cardCVC.length < 3) {
          alert("CVC invalide");
          return;
        }

        if (!cardName) {
          alert("Nom sur la carte requis");
          return;
        }

        // Simuler un paiement par carte (en démo)
        if (confirm("En mode démo, nous allons simuler un paiement par carte réussi. Continuer ?")) {
          const reference = "CARD-" + Date.now() + "-" + Math.random().toString(36).substr(2, 9).toUpperCase();
          finaliserCommande("carte", reference);
        }
      });

      // Finaliser la commande
      async function finaliserCommande(methode, reference) {
        try {
          // Afficher le loading
          document.getElementById("loading").style.display = "block";

          // Préparer les données pour la requête
          const requestData = {
            methode_paiement: methode,
            reference_paiement: reference,
            panier_id: livraisonData.panier_id || null,
            client_id: livraisonData.client_id || null,
            total: total,
            session_id: "<?php echo session_id(); ?>"
          };

          // Envoyer la requête pour créer la commande
          const response = await fetch("/api/commande.php?action=create_commande", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify(requestData),
          });

          const result = await response.json();

          if (result.success) {
            // Redirection vers la confirmation
            window.location.href = result.redirect || "confirmation.php?cmd=" + result.commande.numero;
          } else {
            alert("Erreur: " + result.message);
            document.getElementById("loading").style.display = "none";
          }
        } catch (error) {
          console.error("Erreur:", error);
          alert("Une erreur est survenue. Veuillez réessayer.");
          document.getElementById("loading").style.display = "none";
        }
      }

      // Formatage des champs carte
      document.getElementById("cardNumber")?.addEventListener("input", function (e) {
        let value = e.target.value.replace(/\s/g, "").replace(/\D/g, "");
        if (value.length > 16) value = value.substr(0, 16);

        let formatted = "";
        for (let i = 0; i < value.length; i++) {
          if (i > 0 && i % 4 === 0) formatted += " ";
          formatted += value[i];
        }
        e.target.value = formatted;
      });

      document.getElementById("cardExpiry")?.addEventListener("input", function (e) {
        let value = e.target.value.replace(/\D/g, "");
        if (value.length > 4) value = value.substr(0, 4);

        if (value.length >= 2) {
          value = value.substr(0, 2) + "/" + value.substr(2);
        }
        e.target.value = value;
      });

      document.getElementById("cardCVC")?.addEventListener("input", function (e) {
        e.target.value = e.target.value.replace(/\D/g, "").substr(0, 3);
      });

      // Vérifier que nous avons des données
      document.addEventListener("DOMContentLoaded", function () {
        if (total <= 0) {
          console.warn("Total de commande nul ou négatif");
        }
        
        // Si pas d'adresse, afficher un message
        const adresseDisplay = document.getElementById("adresseDisplay");
        if (adresseDisplay && adresseDisplay.innerHTML.trim() === "") {
          adresseDisplay.innerHTML = '<p style="color: #718096;"><i class="fas fa-exclamation-triangle"></i> Aucune adresse trouvée. <a href="livraison_form.php">Veuillez saisir votre adresse</a></p>';
        }
      });
    </script>
  </body>
</html>
<?php ob_end_flush(); // Fin du buffering et envoi du contenu ?>
