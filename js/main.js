// Données des produits (simulées)
const produits = [
  {
    id: 1,
    nom: "Cadre Photo Personnalisé",
    categorie: "anniversaire",
    prix: 49.99,
    description: "Cadre photo en bois massif avec gravure personnalisée",
    image: "img/produits/cadre.jpg",
    featured: true,
  },
  {
    id: 2,
    nom: "Montre Élégante",
    categorie: "valentin",
    prix: 129.99,
    description: "Montre à gousset vintage avec gravure personnalisée",
    image: "img/produits/montre.jpg",
    featured: true,
  },
  {
    id: 3,
    nom: "Coquetier en Argent",
    categorie: "mariage",
    prix: 89.99,
    description: "Ensemble de coquetiers en argent sterling",
    image: "img/produits/coquetier.jpg",
    featured: true,
  },
  {
    id: 4,
    nom: "Peluche Musicale",
    categorie: "naissance",
    prix: 34.99,
    description: "Peluche douce avec boîte à musique intégrée",
    image: "img/produits/peluche.jpg",
    featured: true,
  },
  {
    id: 5,
    nom: "Stylo Gravure Or",
    categorie: "diplome",
    prix: 79.99,
    description: "Stylo plume en or avec gravure personnalisée",
    image: "img/produits/stylo.jpg",
    featured: true,
  },
  {
    id: 6,
    nom: "Bougie Parfumée",
    categorie: "noel",
    prix: 29.99,
    description: "Bougie artisanale parfum cannelle et orange",
    image: "img/produits/bougie.jpg",
    featured: true,
  },
];

// Gestion du panier
let panier = JSON.parse(localStorage.getItem("panier")) || [];

// Initialisation
document.addEventListener("DOMContentLoaded", function () {
  // Menu mobile
  const menuToggle = document.getElementById("menuToggle");
  const navMobile = document.getElementById("navMobile");

  if (menuToggle && navMobile) {
    menuToggle.addEventListener("click", () => {
      navMobile.classList.toggle("active");
    });
  }

  // Afficher les produits phares
  afficherProduitsPhares();

  // Mettre à jour le compteur du panier
  mettreAJourCompteurPanier();

  // Gestion de la newsletter
  const newsletterForm = document.getElementById("newsletterForm");
  if (newsletterForm) {
    newsletterForm.addEventListener("submit", function (e) {
      e.preventDefault();
      const email = this.querySelector('input[type="email"]').value;
      sInscrireNewsletter(email);
    });
  }

  // Fermer le modal panier
  const closeCartModal = document.getElementById("closeCartModal");
  const continueShopping = document.getElementById("continueShopping");
  const cartModal = document.getElementById("cartModal");

  if (closeCartModal && cartModal) {
    closeCartModal.addEventListener("click", () => {
      cartModal.style.display = "none";
    });
  }

  if (continueShopping && cartModal) {
    continueShopping.addEventListener("click", () => {
      cartModal.style.display = "none";
    });
  }

  // Fermer le modal en cliquant à l'extérieur
  window.addEventListener("click", (e) => {
    if (e.target === cartModal) {
      cartModal.style.display = "none";
    }
  });
});

// Afficher les produits phares
function afficherProduitsPhares() {
  const featuredProducts = document.getElementById("featuredProducts");
  if (!featuredProducts) return;

  const produitsPhares = produits.filter((p) => p.featured);

  featuredProducts.innerHTML = produitsPhares
    .map(
      (produit) => `
        <div class="product-card" data-id="${produit.id}">
            <div class="product-image">
                <img src="${produit.image}" alt="${produit.nom}">
            </div>
            <div class="product-info">
                <div class="product-category">${getCategorieLabel(
                  produit.categorie
                )}</div>
                <h3 class="product-title">${produit.nom}</h3>
                <p class="product-description">${produit.description}</p>
                <div class="product-price">${produit.prix.toFixed(2)} €</div>
                <div class="product-actions">
                    <button class="btn-add-cart" onclick="ajouterAuPanier(${
                      produit.id
                    })">
                        <i class="fas fa-cart-plus"></i> Ajouter
                    </button>
                    <button class="btn-view" onclick="voirProduit(${
                      produit.id
                    })">
                        <i class="fas fa-eye"></i> Voir
                    </button>
                </div>
            </div>
        </div>
    `
    )
    .join("");
}

// Obtenir le label d'une catégorie
function getCategorieLabel(categorie) {
  const categories = {
    anniversaire: "Anniversaire",
    valentin: "Saint-Valentin",
    mariage: "Mariage",
    naissance: "Naissance",
    diplome: "Diplôme",
    noel: "Noël",
  };
  return categories[categorie] || categorie;
}

// Ajouter un produit au panier
function ajouterAuPanier(idProduit) {
  const produit = produits.find((p) => p.id === idProduit);
  if (!produit) return;

  const articleDansPanier = panier.find((item) => item.id === idProduit);

  if (articleDansPanier) {
    articleDansPanier.quantite += 1;
  } else {
    panier.push({
      id: produit.id,
      nom: produit.nom,
      prix: produit.prix,
      image: produit.image,
      quantite: 1,
    });
  }

  // Sauvegarder dans localStorage
  localStorage.setItem("panier", JSON.stringify(panier));

  // Mettre à jour le compteur
  mettreAJourCompteurPanier();

  // Afficher le modal de confirmation
  afficherModalPanier(produit);

  // Animation du bouton
  animerBoutonAjout(idProduit);
}

// Mettre à jour le compteur du panier
function mettreAJourCompteurPanier() {
  const cartCount = document.querySelector(".cart-count");
  if (cartCount) {
    const totalArticles = panier.reduce(
      (total, item) => total + item.quantite,
      0
    );
    cartCount.textContent = totalArticles;
  }
}

// Afficher le modal du panier
function afficherModalPanier(produit) {
  const cartModal = document.getElementById("cartModal");
  const cartModalBody = document.getElementById("cartModalBody");

  if (!cartModal || !cartModalBody) return;

  cartModalBody.innerHTML = `
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
            <img src="${produit.image}" alt="${
    produit.nom
  }" style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
            <div>
                <h4 style="margin-bottom: 0.5rem;">${produit.nom}</h4>
                <p style="color: var(--primary-color); font-weight: bold;">${produit.prix.toFixed(
                  2
                )} €</p>
                <p style="color: #666; font-size: 0.9rem;">Ajouté avec succès !</p>
            </div>
        </div>
        <p>Votre panier contient maintenant ${panier.reduce(
          (total, item) => total + item.quantite,
          0
        )} article(s)</p>
    `;

  cartModal.style.display = "flex";
}

// Animer le bouton d'ajout
function animerBoutonAjout(idProduit) {
  const bouton = document.querySelector(
    `.product-card[data-id="${idProduit}"] .btn-add-cart`
  );
  if (!bouton) return;

  bouton.innerHTML = '<i class="fas fa-check"></i> Ajouté !';
  bouton.style.background = "#4CAF50";

  setTimeout(() => {
    bouton.innerHTML = '<i class="fas fa-cart-plus"></i> Ajouter';
    bouton.style.background = "";
  }, 2000);
}

// Voir un produit
function voirProduit(idProduit) {
  // Rediriger vers la page produit
  window.location.href = `produit.html?id=${idProduit}`;
}

// S'inscrire à la newsletter
function sInscrireNewsletter(email) {
  // Simuler l'envoi à une API
  console.log(`Inscription newsletter: ${email}`);

  // Afficher un message de confirmation
  const newsletterForm = document.getElementById("newsletterForm");
  if (newsletterForm) {
    newsletterForm.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #4CAF50; margin-bottom: 1rem;"></i>
                <h3>Merci pour votre inscription !</h3>
                <p>Vous recevrez bientôt nos dernières nouveautés.</p>
            </div>
        `;
  }
}

// Calculer le total du panier
function calculerTotalPanier() {
  return panier.reduce((total, item) => total + item.prix * item.quantite, 0);
}

// Modifier la quantité d'un article
function modifierQuantite(idProduit, nouvelleQuantite) {
  if (nouvelleQuantite < 1) {
    supprimerDuPanier(idProduit);
    return;
  }

  const article = panier.find((item) => item.id === idProduit);
  if (article) {
    article.quantite = nouvelleQuantite;
    localStorage.setItem("panier", JSON.stringify(panier));
    mettreAJourCompteurPanier();

    // Si on est sur la page panier, mettre à jour l'affichage
    if (typeof mettreAJourAffichagePanier === "function") {
      mettreAJourAffichagePanier();
    }
  }
}

// Supprimer un article du panier
function supprimerDuPanier(idProduit) {
  panier = panier.filter((item) => item.id !== idProduit);
  localStorage.setItem("panier", JSON.stringify(panier));
  mettreAJourCompteurPanier();

  // Si on est sur la page panier, mettre à jour l'affichage
  if (typeof mettreAJourAffichagePanier === "function") {
    mettreAJourAffichagePanier();
  }
}

// Vider le panier
function viderPanier() {
  if (confirm("Voulez-vous vraiment vider votre panier ?")) {
    panier = [];
    localStorage.setItem("panier", JSON.stringify(panier));
    mettreAJourCompteurPanier();

    // Si on est sur la page panier, mettre à jour l'affichage
    if (typeof mettreAJourAffichagePanier === "function") {
      mettreAJourAffichagePanier();
    }
  }
}

// Dans js/main.js, ajoutez :
async function ajouterAuPanier(idProduit) {
  console.log("Ajout au panier produit ID:", idProduit);
  try {
    const response = await fetch("api/panier.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "ajouter",
        id_produit: idProduit,
        quantite: 1,
      }),
    });

    const data = await response.json();
    console.log("Réponse API:", data);

    if (data.success) {
      // Mettre à jour le compteur
      mettreAJourCompteur();
      return true;
    } else {
      console.error("Erreur API:", data.message);
      return false;
    }
  } catch (error) {
    console.error("Erreur réseau:", error);
    return false;
  }
}

async function mettreAJourCompteur() {
  try {
    const response = await fetch("api/panier.php?action=compter");
    const data = await response.json();

    if (data.success) {
      const cartCountElements = document.querySelectorAll(".cart-count");
      cartCountElements.forEach((el) => {
        el.textContent = data.total;
      });
    }
  } catch (error) {
    console.error("Erreur mise à jour compteur:", error);
  }
}

// Mettre à jour le compteur au chargement de chaque page
document.addEventListener("DOMContentLoaded", mettreAJourCompteur);
