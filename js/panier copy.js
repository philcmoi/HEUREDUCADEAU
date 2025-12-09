// js/panier.js
class PanierManager {
  constructor() {
    this.apiUrl = "api/panier.php";
    this.cartModal = document.getElementById("cartModal");
    this.cartModalBody = document.getElementById("cartModalBody");
    this.initEvents();
    this.updateCartCount();
  }

  initEvents() {
    // Gestion de la fermeture de la modal
    const closeBtn = document.getElementById("closeCartModal");
    const continueBtn = document.getElementById("continueShopping");

    if (closeBtn) {
      closeBtn.addEventListener("click", () => this.hideModal());
    }

    if (continueBtn) {
      continueBtn.addEventListener("click", () => this.hideModal());
    }

    // Fermer la modal en cliquant en dehors
    if (this.cartModal) {
      this.cartModal.addEventListener("click", (e) => {
        if (e.target === this.cartModal) {
          this.hideModal();
        }
      });
    }
  }

  async ajouterAuPanier(id_produit, quantite = 1) {
    try {
      const response = await fetch(this.apiUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "ajouter",
          id_produit: id_produit,
          quantite: quantite,
        }),
      });

      const data = await response.json();

      if (data.success) {
        // Mettre à jour le compteur
        await this.updateCartCount();

        // Récupérer les infos du produit pour la modal
        const produitInfo = await this.getProduitInfo(id_produit);

        // Afficher la modal
        this.showModal(produitInfo);

        // Afficher une notification
        this.showNotification("Produit ajouté au panier !", "success");
      } else {
        this.showNotification(
          data.message || "Erreur lors de l'ajout au panier",
          "error"
        );
      }

      return data;
    } catch (error) {
      console.error("Erreur ajout panier:", error);
      this.showNotification("Une erreur est survenue", "error");
      return { success: false, message: "Erreur réseau" };
    }
  }

  async getProduitInfo(id_produit) {
    try {
      // Pour l'instant, on utilise des données mockées
      // En production, on appellerait l'API produits
      return {
        nom: `Produit ${id_produit}`,
        prix: "XX,XX €",
        image: "img/default-product.jpg",
      };
    } catch (error) {
      console.error("Erreur récupération produit:", error);
      return {
        nom: "Produit ajouté",
        prix: "",
        image: "img/default-product.jpg",
      };
    }
  }

  showModal(produitInfo) {
    if (!this.cartModal || !this.cartModalBody) return;

    this.cartModalBody.innerHTML = `
            <div class="cart-modal-product">
                <div class="modal-product-image">
                    <img src="${produitInfo.image}" alt="${produitInfo.nom}">
                </div>
                <div class="modal-product-info">
                    <h4>${produitInfo.nom}</h4>
                    <p class="modal-product-price">${produitInfo.prix}</p>
                    <p class="modal-success-message">
                        <i class="fas fa-check-circle"></i> Ajouté avec succès !
                    </p>
                </div>
            </div>
        `;

    this.cartModal.classList.add("show");
    document.body.style.overflow = "hidden";
  }

  hideModal() {
    if (this.cartModal) {
      this.cartModal.classList.remove("show");
      document.body.style.overflow = "";
    }
  }

  async updateCartCount() {
    try {
      const response = await fetch(`${this.apiUrl}?action=compter`);
      const data = await response.json();

      if (data.success) {
        const count = data.total || 0;
        document.querySelectorAll(".cart-count").forEach((el) => {
          el.textContent = count;
          el.style.display = count > 0 ? "inline-block" : "none";
        });
      }
    } catch (error) {
      console.error("Erreur mise à jour compteur:", error);
    }
  }

  showNotification(message, type = "info") {
    // Créer la notification
    const toast = document.createElement("div");
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${
                  type === "success" ? "check-circle" : "exclamation-circle"
                }"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

    // Ajouter au DOM
    document.body.appendChild(toast);

    // Auto-suppression après 3 secondes
    setTimeout(() => {
      if (toast.parentElement) {
        toast.remove();
      }
    }, 3000);
  }
}

// Initialisation globale
document.addEventListener("DOMContentLoaded", () => {
  window.panierManager = new PanierManager();

  // Gérer les clics sur les boutons "Ajouter au panier"
  document.addEventListener("click", async (e) => {
    const addToCartBtn = e.target.closest(
      '.btn-add-to-cart, .btn-add-cart, [onclick*="ajouterAuPanier"]'
    );

    if (addToCartBtn && !addToCartBtn.disabled) {
      e.preventDefault();

      // Récupérer l'ID du produit
      let id_produit = null;

      // Si le bouton a un data-id
      if (addToCartBtn.dataset.id) {
        id_produit = parseInt(addToCartBtn.dataset.id);
      }
      // Sinon, essayer de le récupérer de l'attribut onclick
      else if (addToCartBtn.getAttribute("onclick")) {
        const onclick = addToCartBtn.getAttribute("onclick");
        const match = onclick.match(/ajouterAuPanier\((\d+)/);
        if (match) {
          id_produit = parseInt(match[1]);
        }
      }

      if (id_produit) {
        // Récupérer la quantité (si disponible)
        const quantiteInput = addToCartBtn
          .closest(".product-card")
          ?.querySelector(".quantity-input");
        const quantite = quantiteInput ? parseInt(quantiteInput.value) || 1 : 1;

        // Ajouter au panier
        await window.panierManager.ajouterAuPanier(id_produit, quantite);
      }
    }
  });
});

// Fonction globale pour ajouter au panier (pour compatibilité avec onclick)
window.ajouterAuPanier = function (id_produit, quantite = 1) {
  if (!window.panierManager) {
    window.panierManager = new PanierManager();
  }
  return window.panierManager.ajouterAuPanier(id_produit, quantite);
};

// Fonction pour mettre à jour le compteur (accessible globalement)
window.updateCartCount = function () {
  if (window.panierManager) {
    return window.panierManager.updateCartCount();
  }
};
