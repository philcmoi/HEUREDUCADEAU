// js/panier.js - Gestion du panier

// js/panier.js - Gestion du panier

class PanierManager {
  constructor() {
    this.init();
  }

  init() {
    this.bindEvents();
    this.loadCart();
  }

  bindEvents() {
    // Vider le panier
    document
      .getElementById("clearCartBtn")
      ?.addEventListener("click", () => this.clearCart());

    // Procéder au paiement
    document
      .getElementById("checkoutBtn")
      ?.addEventListener("click", () => this.checkout());
  }

  async loadCart() {
    try {
      const response = await fetch("api/panier.php?action=afficher");
      const data = await response.json();

      if (data.success) {
        this.renderCart(data.items);
        this.updateTotals(data.total_prix);
        this.updateCartCount(data.total_items);
      }
    } catch (error) {
      console.error("Erreur chargement panier:", error);
    }
  }

  renderCart(items) {
    const container = document.getElementById("cartItems");

    if (!items || items.length === 0) {
      container.innerHTML = `
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Votre panier est vide</h3>
                    <p>Ajoutez des produits pour commencer vos achats</p>
                    <a href="produits.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Découvrir nos produits
                    </a>
                </div>
            `;
      return;
    }

    let html = "";
    items.forEach((item) => {
      html += `
                <div class="cart-item" data-item-id="${item.id_item}">
                    <div class="cart-item-image">
                        <img src="${
                          item.url_image || "images/default-product.jpg"
                        }" alt="${item.nom}">
                    </div>
                    <div class="cart-item-details">
                        <h4 class="cart-item-title">${item.nom}</h4>
                        <p class="cart-item-ref">Réf: ${item.reference}</p>
                        <p class="cart-item-price">${item.prix_unitaire} €</p>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn minus" onclick="panierManager.updateQuantity(${
                          item.id_item
                        }, -1)">-</button>
                        <input type="number" value="${
                          item.quantite
                        }" min="1" max="${item.stock_disponible}" 
                               onchange="panierManager.updateQuantityInput(${
                                 item.id_item
                               }, this.value)">
                        <button class="quantity-btn plus" onclick="panierManager.updateQuantity(${
                          item.id_item
                        }, 1)">+</button>
                    </div>
                    <div class="cart-item-total">
                        <span>${item.total_item} €</span>
                    </div>
                    <button class="cart-item-remove" onclick="panierManager.removeItem(${
                      item.id_item
                    })">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
    });

    container.innerHTML = html;
  }

  async updateQuantity(itemId, change) {
    try {
      const itemElement = document.querySelector(
        `[data-item-id="${itemId}"] input`
      );
      const currentQuantity = parseInt(itemElement.value);
      const newQuantity = currentQuantity + change;

      if (newQuantity < 1) return;

      await this.saveQuantity(itemId, newQuantity);
    } catch (error) {
      console.error("Erreur mise à jour quantité:", error);
    }
  }

  async updateQuantityInput(itemId, quantity) {
    await this.saveQuantity(itemId, parseInt(quantity));
  }

  async saveQuantity(itemId, quantity) {
    try {
      // À implémenter : appel API pour mettre à jour la quantité
      // Pour l'instant, recharger le panier
      await this.loadCart();
    } catch (error) {
      console.error("Erreur sauvegarde quantité:", error);
    }
  }

  async removeItem(itemId) {
    if (confirm("Voulez-vous vraiment supprimer cet article du panier ?")) {
      // À implémenter : appel API pour supprimer l'article
      await this.loadCart();
    }
  }

  async clearCart() {
    if (confirm("Voulez-vous vraiment vider tout votre panier ?")) {
      // À implémenter : appel API pour vider le panier
      await this.loadCart();
    }
  }

  updateTotals(totalPrice) {
    const subtotalElement = document.getElementById("subtotal");
    const totalElement = document.getElementById("total");

    if (subtotalElement && totalElement) {
      const formattedPrice = totalPrice.toFixed(2).replace(".", ",") + " €";
      subtotalElement.textContent = formattedPrice;
      totalElement.textContent = formattedPrice;
    }
  }

  updateCartCount(count) {
    const cartCountElements = document.querySelectorAll(".cart-count");
    cartCountElements.forEach((el) => {
      el.textContent = count;
    });
  }

  async checkout() {
    // À implémenter : redirection vers la page de paiement
    alert("Fonctionnalité de paiement à implémenter");
  }
}

// Initialiser le gestionnaire de panier
const panierManager = new PanierManager();

// Fonctions globales pour les appels depuis HTML
function ajouterAuPanier(idProduit) {
  // À implémenter : appel API pour ajouter au panier
  panierManager.loadCart();
}

function modifierQuantite(itemId, change) {
  panierManager.updateQuantity(itemId, change);
}

function modifierQuantiteInput(itemId, quantity) {
  panierManager.updateQuantityInput(itemId, quantity);
}

function supprimerArticle(itemId) {
  panierManager.removeItem(itemId);
}

// Initialiser le gestionnaire de panier
const panierManager = new PanierManager();

// Fonctions globales pour les appels depuis HTML
function ajouterAuPanier(idProduit) {
  // À implémenter : appel API pour ajouter au panier
  panierManager.loadCart();
}

function modifierQuantite(itemId, change) {
  panierManager.updateQuantity(itemId, change);
}

function modifierQuantiteInput(itemId, quantity) {
  panierManager.updateQuantityInput(itemId, quantity);
}

function supprimerArticle(itemId) {
  panierManager.removeItem(itemId);
}
