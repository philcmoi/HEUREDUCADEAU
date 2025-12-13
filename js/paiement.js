// paiement.js - Gestion des paiements PayPal

// Configuration
const PAYPAL_API = "/api/paypal.php";

// Variables globales
let orderData = null;
let currentOrderId = null;

// Initialiser les options de paiement
function initialiserOptionsPaiement() {
  const options = document.querySelectorAll(".paiement-option .option-header");

  options.forEach((option) => {
    option.addEventListener("click", function () {
      // Désactiver toutes les options
      document.querySelectorAll(".paiement-option").forEach((opt) => {
        opt.classList.remove("active");
        opt.querySelector('input[type="radio"]').checked = false;
      });

      // Activer l'option cliquée
      const parent = this.closest(".paiement-option");
      parent.classList.add("active");
      parent.querySelector('input[type="radio"]').checked = true;

      // Si PayPal est sélectionné, initialiser les boutons
      if (parent.id === "optionPayPal") {
        setTimeout(() => {
          if (typeof paypal !== "undefined") {
            initialiserBoutonsPayPal();
          }
        }, 100);
      }
    });
  });
}

// Initialiser les boutons PayPal
function initialiserBoutonsPayPal() {
  // Vérifier si les boutons sont déjà initialisés
  const container = document.getElementById("paypal-button-container");
  if (!container || container.querySelector("button")) {
    return;
  }

  paypal
    .Buttons({
      style: {
        layout: "vertical",
        color: "gold",
        shape: "rect",
        label: "paypal",
        height: 50,
        tagline: false,
      },

      createOrder: async function (data, actions) {
        try {
          const response = await fetch(PAYPAL_API + "?action=create_payment", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              montant: panierTotal,
              currency: "EUR",
            }),
          });

          const result = await response.json();

          if (result.id) {
            currentOrderId = result.id;
            return result.id;
          } else {
            throw new Error("Erreur création commande PayPal");
          }
        } catch (error) {
          console.error("Erreur création commande:", error);
          alert("Erreur lors de la création du paiement. Veuillez réessayer.");
        }
      },

      onApprove: async function (data, actions) {
        try {
          // Afficher un indicateur de chargement
          const button = document.querySelector("#confirmerPaiement");
          const originalText = button.innerHTML;
          button.innerHTML =
            '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
          button.disabled = true;

          // Capturer le paiement
          const response = await fetch(
            PAYPAL_API + "?action=capture_payment&order_id=" + data.orderID
          );
          const result = await response.json();

          if (result.success) {
            // Rediriger vers la page de succès
            window.location.href = "paiement-reussi.php?token=" + data.orderID;
          } else {
            throw new Error(result.message || "Échec du paiement");
          }
        } catch (error) {
          console.error("Erreur capture paiement:", error);
          alert(
            "Une erreur est survenue lors du traitement du paiement: " +
              error.message
          );

          // Réactiver le bouton
          const button = document.querySelector("#confirmerPaiement");
          button.innerHTML =
            '<i class="fas fa-lock"></i> Confirmer le paiement';
          button.disabled = false;
        }
      },

      onError: function (err) {
        console.error("Erreur PayPal:", err);
        alert(
          "Une erreur est survenue avec PayPal. Veuillez réessayer ou choisir un autre mode de paiement."
        );
      },

      onCancel: function (data) {
        window.location.href = "paiement-annule.php";
      },
    })
    .render("#paypal-button-container");
}

// Valider l'email pour le paiement
function validerEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

// Payer par email (fonction avancée)
async function payerParEmail() {
  const emailInput = document.getElementById("paypalEmail");
  const email = emailInput.value.trim();

  if (!email) {
    alert("Veuillez saisir votre adresse email.");
    emailInput.focus();
    return;
  }

  if (!validerEmail(email)) {
    alert("Veuillez saisir une adresse email valide.");
    emailInput.focus();
    return;
  }

  try {
    // Afficher un indicateur de chargement
    const button = document.querySelector("#confirmerPaiement");
    const originalText = button.innerHTML;
    button.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Création du paiement...';
    button.disabled = true;

    // Créer un paiement par email
    const response = await fetch(PAYPAL_API + "?action=create_email_payment", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        email: email,
        amount: panierTotal,
      }),
    });

    const result = await response.json();

    if (result.success && result.approval_url) {
      // Rediriger vers PayPal
      window.location.href = result.approval_url;
    } else {
      alert(
        "Cette fonctionnalité nécessite un compte PayPal Business. Veuillez utiliser le bouton PayPal standard."
      );
    }
  } catch (error) {
    console.error("Erreur paiement email:", error);
    alert("Erreur lors de la création du paiement.");
  } finally {
    // Réactiver le bouton
    const button = document.querySelector("#confirmerPaiement");
    button.innerHTML = originalText;
    button.disabled = false;
  }
}

// Vérifier l'adresse de livraison
function verifierAdresseLivraison() {
  if (!adresseLivraison) {
    alert("Veuillez d'abord saisir une adresse de livraison.");
    window.location.href = "livraison.html";
    return false;
  }
  return true;
}

// Initialiser le processus de paiement
function initialiserProcessusPaiement() {
  const btnConfirmer = document.getElementById("confirmerPaiement");

  if (btnConfirmer) {
    btnConfirmer.addEventListener("click", async function () {
      // Vérifier l'adresse
      if (!verifierAdresseLivraison()) return;

      // Vérifier le mode de paiement sélectionné
      const modePaypal = document.getElementById("paypalRadio").checked;
      const modeCarte = document.getElementById("carteRadio").checked;

      if (modePaypal || modeCarte) {
        // Déclencher le bouton PayPal
        const paypalButton = document.querySelector(
          "#paypal-button-container button"
        );
        if (paypalButton) {
          paypalButton.click();
        } else {
          alert("Veuillez patienter pendant l'initialisation de PayPal...");
        }
      } else {
        alert("Veuillez sélectionner un mode de paiement.");
      }
    });
  }
}

// Initialisation complète
document.addEventListener("DOMContentLoaded", function () {
  initialiserOptionsPaiement();
  initialiserProcessusPaiement();

  // Initialiser PayPal dès que le SDK est chargé
  if (typeof paypal !== "undefined") {
    initialiserBoutonsPayPal();
  } else {
    // Attendre le chargement du SDK
    const checkPayPal = setInterval(() => {
      if (typeof paypal !== "undefined") {
        clearInterval(checkPayPal);
        initialiserBoutonsPayPal();
      }
    }, 100);
  }
});

// Exposer les fonctions globalement
window.payerParEmail = payerParEmail;
