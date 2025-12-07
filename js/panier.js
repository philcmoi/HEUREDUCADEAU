// js/panier.js - Version améliorée avec codes promotionnels

class PanierManager {
  constructor() {
    this.cartItems = document.getElementById("cartItems");
    this.subtotalEl = document.getElementById("subtotal");
    this.totalEl = document.getElementById("total");
    this.reductionEl = document.getElementById("reduction");
    this.cartCountEl = document.querySelector(".cart-count");
    this.promoCodeInput = document.getElementById("promoCodeInput");
    this.applyPromoBtn = document.getElementById("applyPromoBtn");
    this.clearCartBtn = document.getElementById("clearCartBtn");
    this.checkoutBtn = document.getElementById("checkoutBtn");
    this.giftWrapToggle = document.getElementById("giftWrapToggle");
    this.currentPromoCode = "";
    this.init();
  }

  init() {
    this.chargerPanier();
    this.setupEventListeners();
    this.loadPromoCodesFromStorage();
  }

  async chargerPanier() {
    try {
      const response = await fetch("api/panier.php?action=recuperer");
      const data = await response.json();

      if (data.success) {
        this.afficherPanier(data);
        this.updateCartCount(data.total_items);

        // Sauvegarder le code promo dans le localStorage
        if (data.code_promotion) {
          this.currentPromoCode = data.code_promotion;
          localStorage.setItem("last_promo_code", data.code_promotion);
        }
      }
    } catch (error) {
      console.error("Erreur lors du chargement du panier:", error);
      this.afficherMessageErreur();
    }
  }

  afficherPanier(data) {
    if (data.items.length === 0) {
      this.afficherPanierVide();
      return;
    }

    let html = "";

    data.items.forEach((item) => {
      const prixUnitaire = parseFloat(
        item.prix_unitaire_ajuste || item.prix_unitaire
      );
      const totalLigne = parseFloat(item.total_ligne);

      html += `
                <div class="cart-item" data-id="${
                  item.id_produit
                }" data-variant="${item.id_variant || ""}">
                    <div class="item-image">
                        <img src="${
                          item.image || "images/default-product.jpg"
                        }" 
                             alt="${item.nom}"
                             onerror="this.src='images/default-product.jpg'">
                        ${
                          item.quantite_stock < 5
                            ? `
                            <div class="stock-warning" title="Stock limité">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        `
                            : ""
                        }
                    </div>
                    <div class="item-details">
                        <h4 class="item-name">${this.escapeHtml(item.nom)}</h4>
                        
                        <div class="item-category">
                            <i class="fas fa-tag"></i>
                            <span>${this.escapeHtml(item.categorie_nom)}</span>
                        </div>
                        
                        <p class="item-ref">
                            <i class="fas fa-barcode"></i>
                            Réf: ${this.escapeHtml(item.reference)}
                        </p>
                        
                        ${
                          item.nom_variant
                            ? `
                            <div class="item-variant">
                                <i class="fas fa-palette"></i>
                                <span>${this.escapeHtml(
                                  item.nom_variant
                                )}: ${this.escapeHtml(
                                item.variant_valeur
                              )}</span>
                            </div>
                        `
                            : ""
                        }
                        
                        <div class="item-price">
                            <span class="unit-price">
                                ${prixUnitaire.toFixed(2)} €
                                <small class="price-unit">/unité</small>
                            </span>
                            <span class="total-price">
                                ${totalLigne.toFixed(2)} €
                            </span>
                        </div>
                        
                        <div class="item-stock">
                            <i class="fas fa-box${
                              item.quantite_stock > 0 ? "" : "-open"
                            }"></i>
                            <span>Stock: ${
                              item.quantite_stock > 0
                                ? `${item.quantite_stock} disponible${
                                    item.quantite_stock > 1 ? "s" : ""
                                  }`
                                : "Rupture"
                            }</span>
                        </div>
                    </div>
                    <div class="item-quantity">
                        <button class="qty-btn minus" onclick="panierManager.modifierQuantite(${
                          item.id_produit
                        }, ${item.id_variant || "null"}, -1)"
                                ${item.quantite <= 1 ? "disabled" : ""}>
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" 
                               class="qty-input" 
                               value="${item.quantite}" 
                               min="1" 
                               max="${Math.min(item.quantite_stock, 99)}"
                               onchange="panierManager.mettreAJourQuantite(${
                                 item.id_produit
                               }, ${item.id_variant || "null"}, this.value)">
                        <button class="qty-btn plus" onclick="panierManager.modifierQuantite(${
                          item.id_produit
                        }, ${item.id_variant || "null"}, 1)"
                                ${
                                  item.quantite >= item.quantite_stock
                                    ? "disabled"
                                    : ""
                                }>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="item-actions">
                        <button class="btn-remove" 
                                onclick="panierManager.supprimerDuPanier(${
                                  item.id_produit
                                }, ${item.id_variant || "null"})"
                                title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                        <a href="produit.php?slug=${
                          item.slug
                        }" class="btn-view" title="Voir le produit">
                            <i class="fas fa-eye"></i>
                        </a>
                        <button class="btn-wishlist" onclick="panierManager.ajouterWishlist(${
                          item.id_produit
                        })" title="Ajouter à la wishlist">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                </div>
            `;
    });

    this.cartItems.innerHTML = html;

    // Mettre à jour les totaux avec animation
    this.animatePriceUpdate(
      this.subtotalEl,
      `${parseFloat(data.sous_total).toFixed(2)} €`
    );

    if (this.reductionEl) {
      const reduction = parseFloat(data.reduction);
      if (reduction > 0) {
        this.reductionEl.style.display = "flex";
        this.animatePriceUpdate(this.reductionEl, `-${reduction.toFixed(2)} €`);
        this.reductionEl.className = "summary-row reduction";
      } else {
        this.reductionEl.style.display = "none";
      }
    }

    this.animatePriceUpdate(
      this.totalEl,
      `${parseFloat(data.total).toFixed(2)} €`
    );

    // Afficher les infos du code promo
    this.afficherInfoPromo(data.code_info);
  }

  afficherPanierVide() {
    this.cartItems.innerHTML = `
            <div class="cart-empty">
                <div class="empty-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Votre panier est vide</h3>
                <p>Il semble que vous n'ayez pas encore trouvé le cadeau parfait.</p>
                <p class="empty-subtitle">Explorez notre collection et remplissez-le d'idées géniales !</p>
                <div class="empty-actions">
                    <a href="produits.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Découvrir nos produits
                    </a>
                    <a href="produits.php?categorie=meilleures-ventes" class="btn btn-secondary">
                        <i class="fas fa-crown"></i> Voir les meilleures ventes
                    </a>
                </div>
            </div>
        `;

    this.subtotalEl.textContent = "0,00 €";
    if (this.reductionEl) {
      this.reductionEl.style.display = "none";
    }
    this.totalEl.textContent = "0,00 €";
  }

  afficherInfoPromo(codeInfo) {
    const promoInfoEl = document.getElementById("promoInfo");
    if (!promoInfoEl) return;

    if (codeInfo) {
      let description = "";
      switch (codeInfo.type_promotion) {
        case "pourcentage":
          description = `${codeInfo.valeur}% de réduction`;
          break;
        case "montant_fixe":
          description = `${codeInfo.valeur}€ de réduction`;
          break;
        case "livraison_gratuite":
          description = "Livraison gratuite";
          break;
      }

      promoInfoEl.innerHTML = `
                <div class="promo-info active">
                    <i class="fas fa-tag"></i>
                    <div class="promo-details">
                        <strong>${this.currentPromoCode}</strong>
                        <span>${description}</span>
                        ${
                          codeInfo.date_fin
                            ? `
                            <small>Valable jusqu'au ${new Date(
                              codeInfo.date_fin
                            ).toLocaleDateString("fr-FR")}</small>
                        `
                            : ""
                        }
                    </div>
                    <button class="btn-remove-promo" onclick="panierManager.supprimerCodePromo()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
    } else {
      promoInfoEl.innerHTML = "";
    }
  }

  async appliquerCodePromo() {
    const code = this.promoCodeInput.value.trim();

    if (!code) {
      this.afficherNotification(
        "Veuillez entrer un code promotionnel",
        "error"
      );
      return;
    }

    try {
      // Vérifier d'abord si le code est valide
      const verifyResponse = await fetch("api/panier.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          action: "appliquer_promo",
          code_promotion: code,
        }),
      });

      const verifyData = await verifyResponse.json();

      if (!verifyData.success) {
        this.afficherNotification(verifyData.message, "error");
        return;
      }

      // Appliquer le code au panier
      this.currentPromoCode = code;
      localStorage.setItem("last_promo_code", code);

      // Recharger le panier avec le code
      const response = await fetch(
        `api/panier.php?action=recuperer&code_promotion=${encodeURIComponent(
          code
        )}`
      );
      const data = await response.json();

      if (data.success) {
        this.afficherPanier(data);
        this.afficherNotification(verifyData.message, "success");

        // Mettre à jour l'interface
        if (this.promoCodeInput) {
          this.promoCodeInput.value = "";
          this.promoCodeInput.placeholder = "Code appliqué ✓";
          setTimeout(() => {
            this.promoCodeInput.placeholder = "Code promotionnel";
          }, 2000);
        }
      }
    } catch (error) {
      console.error("Erreur:", error);
      this.afficherNotification("Une erreur est survenue", "error");
    }
  }

  async supprimerCodePromo() {
    this.currentPromoCode = "";
    localStorage.removeItem("last_promo_code");

    // Recharger le panier sans code
    const response = await fetch("api/panier.php?action=recuperer");
    const data = await response.json();

    if (data.success) {
      this.afficherPanier(data);
      this.afficherNotification("Code promotionnel retiré", "info");
    }
  }

  // Remplacer la méthode viderPanier() dans la classe PanierManager :

  async viderPanier() {
    if (
      !confirm(
        "Êtes-vous sûr de vouloir vider complètement votre panier ? Cette action est irréversible."
      )
    ) {
      return;
    }

    // Afficher un indicateur de chargement
    const originalText = this.clearCartBtn.innerHTML;
    this.clearCartBtn.innerHTML =
      '<i class="fas fa-spinner fa-spin"></i> Vidage en cours...';
    this.clearCartBtn.disabled = true;

    try {
      const response = await fetch("api/panier.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=vider",
      });

      const data = await response.json();

      if (data.success) {
        this.updateCartCount(0);
        this.afficherNotification(
          data.message || "Panier vidé avec succès",
          "success"
        );
        this.afficherPanierVide();

        // Supprimer le code promo
        this.currentPromoCode = "";
        localStorage.removeItem("last_promo_code");

        // Recharger pour s'assurer que tout est bien vide
        await this.chargerPanier();
      } else {
        this.afficherNotification(
          data.message || "Erreur lors du vidage du panier",
          "error"
        );
      }
    } catch (error) {
      console.error("Erreur:", error);
      this.afficherNotification(
        "Une erreur est survenue lors du vidage du panier",
        "error"
      );
    } finally {
      // Restaurer le bouton
      this.clearCartBtn.innerHTML = originalText;
      this.clearCartBtn.disabled = false;
    }
  }

  // Dans setupEventListeners(), assurez-vous que l'écouteur est bien configuré :
  setupEventListeners() {
    // ... autres écouteurs ...

    // Vider le panier
    if (this.clearCartBtn) {
      this.clearCartBtn.addEventListener("click", () => this.viderPanier());
    }

    // ... autres écouteurs ...
  }

  async passerCommande() {
    // Vérifier si le panier n'est pas vide
    const response = await fetch("api/panier.php?action=recuperer");
    const data = await response.json();

    if (!data.success || data.items.length === 0) {
      this.afficherNotification("Votre panier est vide", "error");
      return;
    }

    // Vérifier le stock avant de passer commande
    const stockErrors = [];
    for (const item of data.items) {
      if (item.quantite > item.quantite_stock) {
        stockErrors.push(`${item.nom} (stock: ${item.quantite_stock})`);
      }
    }

    if (stockErrors.length > 0) {
      this.afficherNotification(
        `Stock insuffisant pour : ${stockErrors.join(", ")}`,
        "error"
      );
      this.chargerPanier(); // Recharger pour voir les quantités réelles
      return;
    }

    // Rediriger vers la page de commande avec le code promo
    let url = "commande.php";
    if (this.currentPromoCode) {
      url += `?code_promotion=${encodeURIComponent(this.currentPromoCode)}`;
    }

    window.location.href = url;
  }

  setupEventListeners() {
    // Code promotionnel
    if (this.applyPromoBtn) {
      this.applyPromoBtn.addEventListener("click", () =>
        this.appliquerCodePromo()
      );
    }

    if (this.promoCodeInput) {
      this.promoCodeInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          this.appliquerCodePromo();
        }
      });
    }

    // Vider le panier
    if (this.clearCartBtn) {
      this.clearCartBtn.addEventListener("click", () => this.viderPanier());
    }

    // Paiement
    if (this.checkoutBtn) {
      this.checkoutBtn.addEventListener("click", () => this.passerCommande());
    }

    // Emballage cadeau
    if (this.giftWrapToggle) {
      this.giftWrapToggle.addEventListener("change", (e) => {
        this.gestionEmballageCadeau(e.target.checked);
      });
    }

    // Auto-sauvegarde des modifications
    document.addEventListener("input", (e) => {
      if (e.target.classList.contains("qty-input")) {
        const cartItem = e.target.closest(".cart-item");
        if (cartItem) {
          const id_produit = cartItem.dataset.id;
          const id_variant = cartItem.dataset.variant || null;
          const quantite = parseInt(e.target.value);

          if (!isNaN(quantite) && quantite >= 1) {
            this.autoSaveQuantite(id_produit, id_variant, quantite);
          }
        }
      }
    });
  }

  async autoSaveQuantite(id_produit, id_variant, quantite) {
    try {
      await this.mettreAJourQuantite(id_produit, id_variant, quantite);
    } catch (error) {
      console.error("Erreur auto-save:", error);
    }
  }

  async gestionEmballageCadeau(actif) {
    const prix = actif ? 3.9 : 0;

    // Mettre à jour l'affichage
    const giftWrapPrice = document.getElementById("giftWrapPrice");
    if (giftWrapPrice) {
      giftWrapPrice.textContent = `${prix.toFixed(2)} €`;
      this.animatePriceUpdate(giftWrapPrice, `${prix.toFixed(2)} €`);
    }

    // Recalculer le total
    await this.recalculerTotal();
  }

  async recalculerTotal() {
    try {
      const response = await fetch(
        `api/panier.php?action=recuperer&code_promotion=${encodeURIComponent(
          this.currentPromoCode
        )}`
      );
      const data = await response.json();

      if (data.success) {
        // Ajouter le prix de l'emballage cadeau si actif
        let total = parseFloat(data.total);
        if (this.giftWrapToggle && this.giftWrapToggle.checked) {
          total += 3.9;
        }

        this.animatePriceUpdate(this.totalEl, `${total.toFixed(2)} €`);
      }
    } catch (error) {
      console.error("Erreur recalcul:", error);
    }
  }

  loadPromoCodesFromStorage() {
    const lastPromoCode = localStorage.getItem("last_promo_code");
    if (lastPromoCode && this.promoCodeInput) {
      this.promoCodeInput.value = lastPromoCode;
      this.appliquerCodePromo();
    }
  }

  animatePriceUpdate(element, newValue) {
    if (!element) return;

    element.classList.add("price-updating");
    setTimeout(() => {
      element.textContent = newValue;
      element.classList.remove("price-updating");
      element.classList.add("price-updated");

      setTimeout(() => {
        element.classList.remove("price-updated");
      }, 300);
    }, 150);
  }

  escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  afficherNotification(message, type = "info") {
    // Créer une notification toast
    const toast = document.createElement("div");
    toast.className = `toast-notification toast-${type}`;
    toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas fa-${
                  type === "success"
                    ? "check-circle"
                    : type === "error"
                    ? "exclamation-circle"
                    : type === "warning"
                    ? "exclamation-triangle"
                    : "info-circle"
                }"></i>
            </div>
            <div class="toast-message">${message}</div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;

    document.body.appendChild(toast);

    // Supprimer automatiquement après 4 secondes
    setTimeout(() => {
      if (toast.parentElement) {
        toast.remove();
      }
    }, 4000);
  }

  // Méthodes de base (inchangées)
  async ajouterAuPanier(
    id_produit,
    quantite = 1,
    id_variant = null,
    options = null
  ) {
    // ... code existant ...
  }

  async modifierQuantite(id_produit, id_variant, delta) {
    // ... code existant ...
  }

  async mettreAJourQuantite(id_produit, id_variant, quantite) {
    // ... code existant ...
  }

  async supprimerDuPanier(id_produit, id_variant = null) {
    // ... code existant ...
  }

  updateCartCount(count) {
    // ... code existant ...
  }
}

// Initialiser le panier manager
let panierManager;

document.addEventListener("DOMContentLoaded", () => {
  panierManager = new PanierManager();

  // Exposer les fonctions globalement
  window.panierManager = panierManager;
  window.passerCommande = () => panierManager.passerCommande();
  window.viderPanier = () => panierManager.viderPanier();
});

// Fonction utilitaire pour ajouter un produit au panier
function ajouterAuPanier(
  id_produit,
  quantite = 1,
  id_variant = null,
  options = null
) {
  if (!panierManager) {
    panierManager = new PanierManager();
  }
  return panierManager.ajouterAuPanier(
    id_produit,
    quantite,
    id_variant,
    options
  );
}
