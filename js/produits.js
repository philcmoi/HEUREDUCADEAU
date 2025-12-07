// js/produits.js
// Gestion de la recherche et des filtres pour produits.php

document.addEventListener("DOMContentLoaded", function () {
  // Initialisation
  initProduitsPage();

  // Gestionnaires d'événements
  setupEventListeners();

  // Chargement initial des produits
  loadProductsFromServer();
});

// ==================== FONCTIONS D'INITIALISATION ====================

function initProduitsPage() {
  console.log("Initialisation de la page produits");

  // Mettre à jour les compteurs
  updateFilterCounts();

  // Initialiser les sliders de prix
  initPriceSliders();

  // Synchroniser les filtres avec l'URL
  syncFiltersWithURL();

  // Vérifier l'état des filtres actifs
  updateSelectedFiltersDisplay();

  // Initialiser le compteur du panier
  updateCartCount();
}

function setupEventListeners() {
  // Recherche principale
  const mainSearchBtn = document.getElementById("mainSearchBtn");
  const mainSearchInput = document.getElementById("mainSearchInput");

  if (mainSearchBtn && mainSearchInput) {
    mainSearchBtn.addEventListener("click", performSearch);
    mainSearchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") performSearch();
    });
  }

  // Suggestions de recherche
  document.querySelectorAll(".suggestion-tag").forEach((tag) => {
    tag.addEventListener("click", function (e) {
      e.preventDefault();
      const searchTerm = this.getAttribute("data-search");
      document.getElementById("mainSearchInput").value = searchTerm;
      performSearch();
    });
  });

  // Filtres rapides
  document.querySelectorAll(".quick-filter").forEach((filter) => {
    filter.addEventListener("click", function () {
      const filterType = this.getAttribute("data-filter");
      applyQuickFilter(filterType);
    });
  });

  // Filtres par catégorie
  document.querySelectorAll('input[name="category"]').forEach((checkbox) => {
    checkbox.addEventListener("change", function () {
      updateFilterCount("category");
      applyFilters();
    });
  });

  // Filtres de notation
  document.querySelectorAll('input[name="rating"]').forEach((radio) => {
    radio.addEventListener("change", function () {
      applyFilters();
    });
  });

  // Filtres de livraison et caractéristiques
  document
    .querySelectorAll('input[name="delivery"], input[name="feature"]')
    .forEach((input) => {
      input.addEventListener("change", applyFilters);
    });

  // Effacer tous les filtres
  const clearFiltersBtn = document.getElementById("clearFilters");
  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener("click", clearAllFilters);
  }

  // Tri des produits
  const sortSelect = document.getElementById("sortSelect");
  if (sortSelect) {
    sortSelect.addEventListener("change", function () {
      updateURLParameter("tri", this.value);
      reloadProducts();
    });
  }

  // Boutons de vue (grille/liste)
  document.querySelectorAll(".view-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const view = this.getAttribute("data-view");
      switchProductView(view);
    });
  });

  // Pagination
  setupPaginationEvents();

  // Filtres mobiles
  const filterMobileBtn = document.getElementById("filterMobileBtn");
  if (filterMobileBtn) {
    filterMobileBtn.addEventListener("click", openMobileFilters);
  }

  // Réinitialiser la recherche
  const resetSearchBtn = document.getElementById("resetSearchBtn");
  if (resetSearchBtn) {
    resetSearchBtn.addEventListener("click", resetSearch);
  }

  // Gestion des ajouts au panier (déléguée)
  document.addEventListener("click", function (e) {
    if (e.target.closest(".btn-add-cart")) {
      const btn = e.target.closest(".btn-add-cart");
      addToCart(btn.getAttribute("data-id"));
    }

    if (e.target.closest(".btn-view")) {
      e.preventDefault();
      const link = e.target.closest(".btn-view");
      // La navigation est déjà gérée par le href
    }
  });

  // Recherche dans les filtres
  const filterSearch = document.getElementById("filterSearch");
  if (filterSearch) {
    filterSearch.addEventListener("input", function () {
      filterFilterOptions(this.value);
    });
  }
}

// ==================== GESTION DES FILTRES ====================

function applyFilters() {
  // Collecter tous les filtres
  const filters = collectFilters();

  // Mettre à jour l'URL
  updateURLWithFilters(filters);

  // Recharger les produits
  reloadProducts();

  // Mettre à jour l'affichage des filtres actifs
  updateSelectedFiltersDisplay();

  // Mettre à jour le badge mobile
  updateMobileFilterBadge(filters);
}

function collectFilters() {
  const filters = {
    q: "",
    categorie: [],
    prix_min: "",
    prix_max: "",
    rating: "",
    delivery: [],
    feature: [],
    tri: document.getElementById("sortSelect")?.value || "pertinence",
  };

  // Recherche principale
  const mainSearch = document.getElementById("mainSearchInput");
  if (mainSearch && mainSearch.value.trim()) {
    filters.q = mainSearch.value.trim();
  }

  // Catégories
  document.querySelectorAll('input[name="category"]:checked').forEach((cb) => {
    filters.categorie.push(cb.value);
  });

  // Prix
  const priceMin = document.getElementById("priceMin");
  const priceMax = document.getElementById("priceMax");
  if (priceMin && priceMin.value && parseInt(priceMin.value) > 0) {
    filters.prix_min = priceMin.value;
  }
  if (priceMax && priceMax.value && parseInt(priceMax.value) < 1000) {
    filters.prix_max = priceMax.value;
  }

  // Notation
  const selectedRating = document.querySelector('input[name="rating"]:checked');
  if (selectedRating) {
    filters.rating = selectedRating.value;
  }

  // Livraison
  document.querySelectorAll('input[name="delivery"]:checked').forEach((cb) => {
    filters.delivery.push(cb.value);
  });

  // Caractéristiques
  document.querySelectorAll('input[name="feature"]:checked').forEach((cb) => {
    filters.feature.push(cb.value);
  });

  return filters;
}

function updateURLWithFilters(filters) {
  const params = new URLSearchParams();

  if (filters.q) params.set("q", filters.q);
  if (filters.categorie.length > 0)
    params.set("categorie", filters.categorie.join(","));
  if (filters.prix_min) params.set("prix_min", filters.prix_min);
  if (filters.prix_max) params.set("prix_max", filters.prix_max);
  if (filters.rating) params.set("rating", filters.rating);
  if (filters.delivery.length > 0)
    params.set("delivery", filters.delivery.join(","));
  if (filters.feature.length > 0)
    params.set("feature", filters.feature.join(","));
  if (filters.tri && filters.tri !== "pertinence")
    params.set("tri", filters.tri);

  // Garder la page si elle existe
  const currentPage = new URLSearchParams(window.location.search).get("page");
  if (currentPage && currentPage !== "1") {
    params.set("page", currentPage);
  }

  const newUrl = `${window.location.pathname}?${params.toString()}`;
  window.history.replaceState({}, "", newUrl);
}

function syncFiltersWithURL() {
  const params = new URLSearchParams(window.location.search);

  // Recherche
  const q = params.get("q");
  if (q && document.getElementById("mainSearchInput")) {
    document.getElementById("mainSearchInput").value = q;
  }

  // Catégories
  const categories = params.get("categorie");
  if (categories) {
    const catArray = categories.split(",");
    document.querySelectorAll('input[name="category"]').forEach((cb) => {
      cb.checked = catArray.includes(cb.value);
    });
  }

  // Prix
  const prixMin = params.get("prix_min");
  const prixMax = params.get("prix_max");
  if (prixMin && document.getElementById("priceMin")) {
    document.getElementById("priceMin").value = prixMin;
  }
  if (prixMax && document.getElementById("priceMax")) {
    document.getElementById("priceMax").value = prixMax;
  }

  // Notation
  const rating = params.get("rating");
  if (rating) {
    const ratingInput = document.querySelector(
      `input[name="rating"][value="${rating}"]`
    );
    if (ratingInput) ratingInput.checked = true;
  }

  // Tri
  const tri = params.get("tri");
  if (tri && document.getElementById("sortSelect")) {
    document.getElementById("sortSelect").value = tri;
  }
}

function clearAllFilters() {
  // Réinitialiser les inputs
  document
    .querySelectorAll('input[type="checkbox"], input[type="radio"]')
    .forEach((input) => {
      input.checked = false;
    });

  // Réinitialiser le prix
  if (document.getElementById("priceMin")) {
    document.getElementById("priceMin").value = 0;
  }
  if (document.getElementById("priceMax")) {
    document.getElementById("priceMax").value = 500;
  }

  // Réinitialiser la recherche
  if (document.getElementById("mainSearchInput")) {
    document.getElementById("mainSearchInput").value = "";
  }

  // Réinitialiser le tri
  if (document.getElementById("sortSelect")) {
    document.getElementById("sortSelect").value = "pertinence";
  }

  // Mettre à jour l'URL
  window.history.replaceState({}, "", window.location.pathname);

  // Recharger les produits
  reloadProducts();

  // Mettre à jour l'affichage
  updateSelectedFiltersDisplay();
  updateFilterCounts();
}

function updateSelectedFiltersDisplay() {
  const container = document.getElementById("selectedFilters");
  if (!container) return;

  const activeFilters = [];

  // Catégories
  document.querySelectorAll('input[name="category"]:checked').forEach((cb) => {
    const label = cb.closest("label").querySelector(".option-text").textContent;
    activeFilters.push({
      type: "category",
      value: cb.value,
      label: label,
      display: label,
    });
  });

  // Prix
  const priceMin = document.getElementById("priceMin")?.value || 0;
  const priceMax = document.getElementById("priceMax")?.value || 500;
  if (parseInt(priceMin) > 0 || parseInt(priceMax) < 1000) {
    activeFilters.push({
      type: "price",
      value: `${priceMin}-${priceMax}`,
      label: `Prix : ${priceMin}€ - ${priceMax}€`,
      display: `${priceMin}€-${priceMax}€`,
    });
  }

  // Notation
  const rating = document.querySelector('input[name="rating"]:checked');
  if (rating) {
    const stars = rating
      .closest("label")
      .querySelector(".stars")
      .textContent.trim();
    activeFilters.push({
      type: "rating",
      value: rating.value,
      label: `Note : ${stars}`,
      display: `${rating.value}★+`,
    });
  }

  // Générer le HTML
  if (activeFilters.length === 0) {
    container.innerHTML = "";
    return;
  }

  let html = '<span class="filters-label">Filtres actifs :</span>';
  activeFilters.forEach((filter) => {
    html += `
            <span class="active-filter-tag" data-type="${filter.type}" data-value="${filter.value}">
                ${filter.display}
                <button class="remove-filter" type="button">&times;</button>
            </span>
        `;
  });

  html +=
    '<button class="clear-all-filters" id="clearAllFiltersBtn">Tout effacer</button>';
  container.innerHTML = html;

  // Ajouter les événements pour supprimer les filtres
  container.querySelectorAll(".remove-filter").forEach((btn) => {
    btn.addEventListener("click", function () {
      const tag = this.closest(".active-filter-tag");
      removeFilter(
        tag.getAttribute("data-type"),
        tag.getAttribute("data-value")
      );
    });
  });

  container
    .querySelector("#clearAllFiltersBtn")
    ?.addEventListener("click", clearAllFilters);
}

function removeFilter(type, value) {
  switch (type) {
    case "category":
      const catCheckbox = document.querySelector(
        `input[name="category"][value="${value}"]`
      );
      if (catCheckbox) catCheckbox.checked = false;
      break;
    case "price":
      if (document.getElementById("priceMin")) {
        document.getElementById("priceMin").value = 0;
      }
      if (document.getElementById("priceMax")) {
        document.getElementById("priceMax").value = 500;
      }
      break;
    case "rating":
      const ratingRadio = document.querySelector(
        `input[name="rating"][value="${value}"]`
      );
      if (ratingRadio) ratingRadio.checked = false;
      break;
  }

  applyFilters();
}

// ==================== GESTION DES PRODUITS ====================

async function loadProductsFromServer() {
  const loadingState = document.getElementById("loadingState");
  const emptyState = document.getElementById("emptyState");
  const productsGrid = document.getElementById("productsGrid");
  const productsList = document.getElementById("productsList");

  if (loadingState) loadingState.style.display = "block";
  if (emptyState) emptyState.style.display = "none";

  try {
    const response = await fetch(window.location.href, {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    if (!response.ok) throw new Error("Erreur réseau");

    const html = await response.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, "text/html");

    // Extraire les produits
    const productsContainer = doc.querySelector(".products-grid");
    if (productsContainer) {
      productsGrid.innerHTML = productsContainer.innerHTML;
    }

    // Mettre à jour le compteur de résultats
    const resultsCount = doc.querySelector(".results-count");
    if (resultsCount && document.querySelector(".results-count")) {
      document.querySelector(".results-count").innerHTML =
        resultsCount.innerHTML;
    }

    // Mettre à jour la pagination
    const pagination = doc.querySelector(".pagination");
    if (pagination && document.getElementById("pagination")) {
      document.getElementById("pagination").innerHTML = pagination.innerHTML;
      setupPaginationEvents();
    }

    // Mettre à jour les vues
    updateProductListViews();

    // Masquer le loading
    if (loadingState) loadingState.style.display = "none";

    // Vérifier si aucun résultat
    const productCards = productsGrid.querySelectorAll(".product-card");
    if (productCards.length === 0) {
      if (emptyState) emptyState.style.display = "block";
    }
  } catch (error) {
    console.error("Erreur lors du chargement des produits:", error);
    if (loadingState) loadingState.style.display = "none";
    if (emptyState) {
      emptyState.innerHTML = `
                <i class="fas fa-exclamation-triangle fa-3x"></i>
                <h3>Erreur de chargement</h3>
                <p>Impossible de charger les produits. Veuillez réessayer.</p>
                <button class="btn btn-secondary" onclick="location.reload()">
                    Recharger la page
                </button>
            `;
      emptyState.style.display = "block";
    }
  }
}

function reloadProducts() {
  // Masquer temporairement les produits
  const productsGrid = document.getElementById("productsGrid");
  const productsList = document.getElementById("productsList");
  if (productsGrid) productsGrid.style.opacity = "0.5";
  if (productsList) productsList.style.opacity = "0.5";

  // Recharger
  loadProductsFromServer();
}

function updateProductListViews() {
  // Synchroniser la vue liste avec la vue grille
  const gridView = document.getElementById("productsGrid");
  const listView = document.getElementById("productsList");

  if (!gridView || !listView) return;

  const products = gridView.innerHTML;
  listView.innerHTML = products;

  // Ajouter des classes spécifiques pour la vue liste
  listView.querySelectorAll(".product-card").forEach((card) => {
    card.classList.add("list-view-item");
  });
}

function switchProductView(view) {
  const gridView = document.getElementById("productsGrid");
  const listView = document.getElementById("productsList");
  const gridBtn = document.querySelector('[data-view="grid"]');
  const listBtn = document.querySelector('[data-view="list"]');

  if (!gridView || !listView || !gridBtn || !listBtn) return;

  if (view === "grid") {
    gridView.style.display = "grid";
    listView.style.display = "none";
    gridBtn.classList.add("active");
    listBtn.classList.remove("active");
  } else {
    gridView.style.display = "none";
    listView.style.display = "block";
    listBtn.classList.add("active");
    gridBtn.classList.remove("active");
  }

  // Sauvegarder la préférence
  localStorage.setItem("productViewPreference", view);
}

// ==================== PAGINATION ====================

function setupPaginationEvents() {
  document.querySelectorAll(".page-number").forEach((btn) => {
    btn.addEventListener("click", function () {
      const page = parseInt(this.textContent);
      goToPage(page);
    });
  });

  document
    .querySelectorAll(".pagination-btn.prev, .pagination-btn.next")
    .forEach((btn) => {
      btn.addEventListener("click", function () {
        if (this.classList.contains("prev")) {
          const currentPage = getCurrentPage();
          if (currentPage > 1) {
            goToPage(currentPage - 1);
          }
        } else {
          const currentPage = getCurrentPage();
          goToPage(currentPage + 1);
        }
      });
    });
}

function getCurrentPage() {
  const params = new URLSearchParams(window.location.search);
  return parseInt(params.get("page") || "1");
}

function goToPage(page) {
  updateURLParameter("page", page);
  reloadProducts();

  // Scroll vers le haut
  window.scrollTo({
    top: document.querySelector(".products-results").offsetTop - 100,
    behavior: "smooth",
  });
}

// ==================== RECHERCHE ====================

function performSearch() {
  const searchInput = document.getElementById("mainSearchInput");
  if (!searchInput) return;

  const query = searchInput.value.trim();
  if (query) {
    updateURLParameter("q", query);
  } else {
    removeURLParameter("q");
  }

  // Retour à la page 1
  removeURLParameter("page");

  reloadProducts();
}

function resetSearch() {
  // Réinitialiser uniquement la recherche, pas les filtres
  if (document.getElementById("mainSearchInput")) {
    document.getElementById("mainSearchInput").value = "";
  }

  removeURLParameter("q");
  removeURLParameter("page");

  reloadProducts();
}

function applyQuickFilter(filterType) {
  // Mettre à jour l'état visuel
  document.querySelectorAll(".quick-filter").forEach((f) => {
    f.classList.remove("active");
  });
  event.target.closest(".quick-filter").classList.add("active");

  // Appliquer le filtre selon le type
  switch (filterType) {
    case "nouveautes":
      updateURLParameter("tri", "nouveaute");
      break;
    case "meilleures-ventes":
      updateURLParameter("tri", "plus-vendus");
      break;
    case "promotions":
      // Ici, on pourrait ajouter un paramètre pour les promotions
      break;
    case "cadeaux-express":
      // Cocher la livraison express
      const expressCheckbox = document.querySelector(
        'input[name="delivery"][value="express"]'
      );
      if (expressCheckbox) {
        expressCheckbox.checked = true;
        applyFilters();
      }
      return;
    case "all":
    default:
      // Réinitialiser le tri
      updateURLParameter("tri", "pertinence");
  }

  // Retour à la page 1
  removeURLParameter("page");

  reloadProducts();
}

// ==================== GESTION DU PANIER ====================

async function addToCart(productId) {
  if (!productId) return;

  try {
    const response = await fetch("api/panier.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        action: "ajouter",
        id_produit: productId,
        quantite: 1,
      }),
    });

    const result = await response.json();

    if (result.success) {
      // Mettre à jour le compteur
      updateCartCount(result.total_items);

      // Afficher une notification
      showNotification("Produit ajouté au panier !", "success");
    } else {
      showNotification("Erreur: " + result.message, "error");
    }
  } catch (error) {
    console.error("Erreur:", error);
    showNotification("Une erreur est survenue", "error");
  }
}

function updateCartCount(count = null) {
  if (count !== null) {
    // Mettre à jour tous les compteurs
    document.querySelectorAll(".cart-count").forEach((el) => {
      el.textContent = count;
    });
    return;
  }

  // Récupérer le nombre depuis le serveur
  fetch("api/panier.php?action=get-count")
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.querySelectorAll(".cart-count").forEach((el) => {
          el.textContent = data.total_items;
        });
      }
    })
    .catch(console.error);
}

// ==================== FONCTIONS UTILITAIRES ====================

function initPriceSliders() {
  const sliderMin = document.getElementById("priceSliderMin");
  const sliderMax = document.getElementById("priceSliderMax");
  const inputMin = document.getElementById("priceMin");
  const inputMax = document.getElementById("priceMax");
  const display = document.getElementById("priceRangeDisplay");

  if (!sliderMin || !sliderMax || !inputMin || !inputMax || !display) return;

  function updatePriceDisplay() {
    const min = parseInt(sliderMin.value);
    const max = parseInt(sliderMax.value);

    // Éviter que min dépasse max
    if (min > max) {
      sliderMin.value = max;
      inputMin.value = max;
    }

    // Éviter que max soit inférieur à min
    if (max < min) {
      sliderMax.value = min;
      inputMax.value = min;
    }

    // Mettre à jour les inputs
    inputMin.value = sliderMin.value;
    inputMax.value = sliderMax.value;

    // Mettre à jour l'affichage
    display.textContent = `${sliderMin.value}€ - ${sliderMax.value}€`;
  }

  // Événements pour les sliders
  sliderMin.addEventListener("input", function () {
    updatePriceDisplay();
    applyFilters();
  });

  sliderMax.addEventListener("input", function () {
    updatePriceDisplay();
    applyFilters();
  });

  // Événements pour les inputs
  inputMin.addEventListener("change", function () {
    let value = parseInt(this.value);
    if (isNaN(value) || value < 0) value = 0;
    if (value > 1000) value = 1000;
    this.value = value;
    sliderMin.value = value;
    updatePriceDisplay();
    applyFilters();
  });

  inputMax.addEventListener("change", function () {
    let value = parseInt(this.value);
    if (isNaN(value) || value < 0) value = 0;
    if (value > 1000) value = 1000;
    this.value = value;
    sliderMax.value = value;
    updatePriceDisplay();
    applyFilters();
  });

  // Initialiser l'affichage
  updatePriceDisplay();
}

function updateFilterCounts() {
  // Compter les catégories cochées
  const categoryCount = document.querySelectorAll(
    'input[name="category"]:checked'
  ).length;
  const categoryCountEl = document.getElementById("categoryCount");
  if (categoryCountEl) {
    categoryCountEl.textContent = categoryCount;
  }
}

function updateFilterCount(filterType) {
  if (filterType === "category") {
    updateFilterCounts();
  }
}

function updateMobileFilterBadge(filters) {
  let activeCount = 0;

  // Compter les filtres actifs
  activeCount += filters.categorie.length;
  if (filters.prix_min || filters.prix_max) activeCount++;
  if (filters.rating) activeCount++;
  activeCount += filters.delivery.length;
  activeCount += filters.feature.length;

  // Mettre à jour le badge
  const badge = document.querySelector(".filter-badge");
  if (badge) {
    badge.textContent = activeCount;
  }

  // Mettre à jour le bouton d'application mobile
  const applyBtn = document.getElementById("applyFiltersMobile");
  if (applyBtn) {
    applyBtn.textContent = `Appliquer (${activeCount})`;
  }
}

function filterFilterOptions(searchTerm) {
  const options = document.querySelectorAll(".filter-option");
  const searchLower = searchTerm.toLowerCase();

  options.forEach((option) => {
    const text = option.querySelector(".option-text").textContent.toLowerCase();
    if (text.includes(searchLower)) {
      option.style.display = "flex";
    } else {
      option.style.display = "none";
    }
  });
}

function openMobileFilters() {
  const modal = document.getElementById("filterModal");
  if (!modal) return;

  // Cloner les filtres dans la modal
  const sidebar = document.querySelector(".products-sidebar");
  const modalBody = modal.querySelector(".filter-modal-body");

  if (sidebar && modalBody) {
    modalBody.innerHTML = sidebar.innerHTML;

    // Réinitialiser les événements dans la modal
    modalBody.querySelectorAll('input[name="category"]').forEach((cb) => {
      cb.addEventListener("change", function () {
        updateMobileFilterCount();
      });
    });

    modalBody
      .querySelectorAll(
        'input[name="rating"], input[name="delivery"], input[name="feature"]'
      )
      .forEach((input) => {
        input.addEventListener("change", updateMobileFilterCount);
      });

    // Initialiser les sliders de prix dans la modal
    const priceSliders = modalBody.querySelector(".price-slider");
    if (priceSliders) {
      // Copier les valeurs actuelles
      const currentMin = document.getElementById("priceMin").value;
      const currentMax = document.getElementById("priceMax").value;

      const modalMin = modalBody.querySelector("#priceMin");
      const modalMax = modalBody.querySelector("#priceMax");
      const modalSliderMin = modalBody.querySelector("#priceSliderMin");
      const modalSliderMax = modalBody.querySelector("#priceSliderMax");

      if (modalMin && modalMax && modalSliderMin && modalSliderMax) {
        modalMin.value = currentMin;
        modalMax.value = currentMax;
        modalSliderMin.value = currentMin;
        modalSliderMax.value = currentMax;

        // Réappliquer la logique des sliders
        initModalPriceSliders();
      }
    }
  }

  modal.style.display = "block";
  document.body.style.overflow = "hidden";

  // Fermer la modal
  const closeBtn = modal.querySelector(".filter-modal-close");
  if (closeBtn) {
    closeBtn.onclick = function () {
      modal.style.display = "none";
      document.body.style.overflow = "auto";
    };
  }

  // Gestion des boutons d'action
  const clearBtn = document.getElementById("clearFiltersMobile");
  const applyBtn = document.getElementById("applyFiltersMobile");

  if (clearBtn) {
    clearBtn.onclick = function () {
      modalBody
        .querySelectorAll('input[type="checkbox"], input[type="radio"]')
        .forEach((input) => {
          input.checked = false;
        });
      updateMobileFilterCount();
    };
  }

  if (applyBtn) {
    applyBtn.onclick = function () {
      // Transférer les filtres de la modal vers la sidebar
      transferFiltersFromModal(modalBody);
      modal.style.display = "none";
      document.body.style.overflow = "auto";
      applyFilters();
    };
  }

  // Fermer en cliquant à l'extérieur
  modal.onclick = function (e) {
    if (e.target === modal) {
      modal.style.display = "none";
      document.body.style.overflow = "auto";
    }
  };
}

function updateMobileFilterCount() {
  const modalBody = document.querySelector(".filter-modal-body");
  if (!modalBody) return;

  let count = 0;

  // Catégories
  count += modalBody.querySelectorAll('input[name="category"]:checked').length;

  // Notation
  if (modalBody.querySelector('input[name="rating"]:checked')) count++;

  // Livraison et caractéristiques
  count += modalBody.querySelectorAll('input[name="delivery"]:checked').length;
  count += modalBody.querySelectorAll('input[name="feature"]:checked').length;

  // Prix (si différent des valeurs par défaut)
  const modalMin = modalBody.querySelector("#priceMin");
  const modalMax = modalBody.querySelector("#priceMax");
  if (modalMin && modalMax) {
    const minVal = parseInt(modalMin.value);
    const maxVal = parseInt(modalMax.value);
    if (minVal > 0 || maxVal < 1000) count++;
  }

  const applyBtn = document.getElementById("applyFiltersMobile");
  if (applyBtn) {
    applyBtn.textContent = `Appliquer (${count})`;
  }
}

function transferFiltersFromModal(modalBody) {
  // Catégories
  const modalCategories = modalBody.querySelectorAll('input[name="category"]');
  const sidebarCategories = document.querySelectorAll('input[name="category"]');

  modalCategories.forEach((modalCb, index) => {
    if (sidebarCategories[index]) {
      sidebarCategories[index].checked = modalCb.checked;
    }
  });

  // Prix
  const modalMin = modalBody.querySelector("#priceMin");
  const modalMax = modalBody.querySelector("#priceMax");
  const sidebarMin = document.getElementById("priceMin");
  const sidebarMax = document.getElementById("priceMax");

  if (modalMin && sidebarMin) sidebarMin.value = modalMin.value;
  if (modalMax && sidebarMax) sidebarMax.value = modalMax.value;

  // Notation
  const modalRating = modalBody.querySelector('input[name="rating"]:checked');
  const sidebarRating = document.querySelector(
    `input[name="rating"][value="${modalRating?.value}"]`
  );
  if (sidebarRating) sidebarRating.checked = true;

  // Livraison et caractéristiques (similaire aux catégories)
  // ... implémentation similaire
}

function initModalPriceSliders() {
  // Similaire à initPriceSliders mais pour la modal
  const modalBody = document.querySelector(".filter-modal-body");
  if (!modalBody) return;

  const sliderMin = modalBody.querySelector("#priceSliderMin");
  const sliderMax = modalBody.querySelector("#priceSliderMax");
  const inputMin = modalBody.querySelector("#priceMin");
  const inputMax = modalBody.querySelector("#priceMax");
  const display = modalBody.querySelector("#priceRangeDisplay");

  if (!sliderMin || !sliderMax || !inputMin || !inputMax || !display) return;

  function updateDisplay() {
    const min = parseInt(sliderMin.value);
    const max = parseInt(sliderMax.value);

    if (min > max) {
      sliderMin.value = max;
      inputMin.value = max;
    }

    if (max < min) {
      sliderMax.value = min;
      inputMax.value = min;
    }

    inputMin.value = sliderMin.value;
    inputMax.value = sliderMax.value;
    display.textContent = `${sliderMin.value}€ - ${sliderMax.value}€`;
  }

  sliderMin.addEventListener("input", updateDisplay);
  sliderMax.addEventListener("input", updateDisplay);

  inputMin.addEventListener("change", function () {
    let value = parseInt(this.value);
    if (isNaN(value) || value < 0) value = 0;
    if (value > 1000) value = 1000;
    this.value = value;
    sliderMin.value = value;
    updateDisplay();
  });

  inputMax.addEventListener("change", function () {
    let value = parseInt(this.value);
    if (isNaN(value) || value < 0) value = 0;
    if (value > 1000) value = 1000;
    this.value = value;
    sliderMax.value = value;
    updateDisplay();
  });

  updateDisplay();
}

function updateURLParameter(param, value) {
  const url = new URL(window.location);
  if (value) {
    url.searchParams.set(param, value);
  } else {
    url.searchParams.delete(param);
  }
  window.history.replaceState({}, "", url);
}

function removeURLParameter(param) {
  updateURLParameter(param, null);
}

function showNotification(message, type = "info") {
  // Créer une notification
  const notification = document.createElement("div");
  notification.className = `notification notification-${type}`;
  notification.innerHTML = `
        <span>${message}</span>
        <button class="notification-close">&times;</button>
    `;

  document.body.appendChild(notification);

  // Animation d'entrée
  setTimeout(() => {
    notification.classList.add("show");
  }, 10);

  // Fermer la notification
  notification.querySelector(".notification-close").onclick = function () {
    notification.classList.remove("show");
    setTimeout(() => {
      notification.remove();
    }, 300);
  };

  // Fermeture automatique
  setTimeout(() => {
    if (notification.parentNode) {
      notification.classList.remove("show");
      setTimeout(() => {
        if (notification.parentNode) {
          notification.remove();
        }
      }, 300);
    }
  }, 3000);
}

// ==================== STYLES DYNAMIQUES ====================

// Ajouter des styles pour les notifications
const style = document.createElement("style");
style.textContent = `
    .notification {
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        z-index: 10000;
        transform: translateX(120%);
        transition: transform 0.3s ease;
        display: flex;
        justify-content: space-between;
        align-items: center;
        min-width: 300px;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .notification.show {
        transform: translateX(0);
    }
    
    .notification-success {
        background: linear-gradient(135deg, #2ecc71, #27ae60);
    }
    
    .notification-error {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
    }
    
    .notification-info {
        background: linear-gradient(135deg, #3498db, #2980b9);
    }
    
    .notification-close {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        margin-left: 15px;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0.8;
    }
    
    .notification-close:hover {
        opacity: 1;
    }
    
    .active-filter-tag {
        background: #e9ecef;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        padding: 4px 12px;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        margin: 0 4px;
    }
    
    .remove-filter {
        background: none;
        border: none;
        color: #6c757d;
        font-size: 18px;
        cursor: pointer;
        margin-left: 6px;
        padding: 0;
        line-height: 1;
    }
    
    .clear-all-filters {
        background: none;
        border: none;
        color: #dc3545;
        cursor: pointer;
        margin-left: 8px;
        font-size: 14px;
    }
    
    .clear-all-filters:hover {
        text-decoration: underline;
    }
    
    .filters-label {
        color: #6c757d;
        font-size: 14px;
        margin-right: 8px;
    }
`;
document.head.appendChild(style);
