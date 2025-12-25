<!DOCTYPE html>
<html lang="fr">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recherche de Cadeaux - Cadeaux Élégance</title>
    <link rel="stylesheet" href="css/style.css" />
    <link rel="stylesheet" href="css/produits.css" />
    <link
      rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600&display=swap"
      rel="stylesheet"
    />
  </head>
  <body>
    <!-- Header (identique à l'accueil) -->
    <header class="header">
      <div class="container header-container">
        <a href="index.html" class="logo">
          <i class="fas fa-gift logo-icon"></i>
          <span class="logo-text"
            >Cadeaux<span class="logo-highlight">Élégance</span></span
          >
        </a>

        <nav class="nav-main">
          <ul class="nav-list">
            <li>
              <a href="index.html" class="nav-link"
                ><i class="fas fa-home"></i> Accueil</a
              >
            </li>
            <li>
              <a href="produits.html" class="nav-link active"
                ><i class="fas fa-box-open"></i> Cadeaux</a
              >
            </li>
            <li>
              <a href="apropos.html" class="nav-link"
                ><i class="fas fa-info-circle"></i> À propos</a
              >
            </li>
            <li>
              <a href="contact.html" class="nav-link"
                ><i class="fas fa-envelope"></i> Contact</a
              >
            </li>
            <li>
              <a href="panier.html" class="nav-link cart-link">
                <i class="fas fa-shopping-cart"></i> Panier
                <span class="cart-count">0</span>
              </a>
            </li>
          </ul>
        </nav>

        <!-- Menu mobile -->
        <button class="menu-toggle" id="menuToggle">
          <i class="fas fa-bars"></i>
        </button>
      </div>

      <!-- Navigation mobile -->
      <nav class="nav-mobile" id="navMobile">
        <ul class="nav-mobile-list">
          <li>
            <a href="index.html" class="nav-mobile-link"
              ><i class="fas fa-home"></i> Accueil</a
            >
          </li>
          <li>
            <a href="produits.html" class="nav-mobile-link active"
              ><i class="fas fa-box-open"></i> Cadeaux</a
            >
          </li>
          <li>
            <a href="apropos.html" class="nav-mobile-link"
              ><i class="fas fa-info-circle"></i> À propos</a
            >
          </li>
          <li>
            <a href="contact.html" class="nav-mobile-link"
              ><i class="fas fa-envelope"></i> Contact</a
            >
          </li>
          <li>
            <a href="panier.html" class="nav-mobile-link"
              ><i class="fas fa-shopping-cart"></i> Panier</a
            >
          </li>
        </ul>
      </nav>
    </header>

    <!-- Bannière de recherche -->
    <section class="search-hero">
      <div class="container">
        <div class="search-hero-content">
          <h1>Trouvez le cadeau parfait</h1>
          <p class="search-subtitle">
            Plus de 500 cadeaux originaux pour toutes les occasions
          </p>

          <!-- Barre de recherche principale -->
          <div class="main-search-bar">
            <div class="search-input-group">
              <i class="fas fa-search search-icon"></i>
              <input
                type="text"
                id="mainSearchInput"
                placeholder="Rechercher un cadeau, une occasion, un budget..."
                autocomplete="off"
              />
              <button class="search-btn" id="mainSearchBtn">
                <i class="fas fa-search"></i> Rechercher
              </button>
            </div>
            <div class="search-suggestions" id="searchSuggestions">
              <span class="suggestion-title">Suggestions :</span>
              <a href="#" class="suggestion-tag" data-search="anniversaire"
                >Anniversaire</a
              >
              <a href="#" class="suggestion-tag" data-search="mariage"
                >Mariage</a
              >
              <a href="#" class="suggestion-tag" data-search="moins de 50€"
                >Moins de 50€</a
              >
              <a href="#" class="suggestion-tag" data-search="personnalisé"
                >Personnalisé</a
              >
              <a href="#" class="suggestion-tag" data-search="écologique"
                >Écologique</a
              >
            </div>
          </div>

          <!-- Filtres rapides -->
          <div class="quick-filters">
            <div class="quick-filter active" data-filter="all">
              <i class="fas fa-star"></i>
              <span>Tous les cadeaux</span>
            </div>
            <div class="quick-filter" data-filter="nouveautes">
              <i class="fas fa-fire"></i>
              <span>Nouveautés</span>
            </div>
            <div class="quick-filter" data-filter="meilleures-ventes">
              <i class="fas fa-crown"></i>
              <span>Meilleures ventes</span>
            </div>
            <div class="quick-filter" data-filter="promotions">
              <i class="fas fa-percentage"></i>
              <span>Promotions</span>
            </div>
            <div class="quick-filter" data-filter="cadeaux-express">
              <i class="fas fa-shipping-fast"></i>
              <span>Livraison express</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Section principale de recherche -->
    <main class="products-page">
      <div class="container">
        <div class="products-layout">
          <!-- Sidebar des filtres -->
          <aside class="products-sidebar">
            <div class="sidebar-section">
              <h3 class="sidebar-title">
                <i class="fas fa-filter"></i> Filtres
                <button class="clear-filters" id="clearFilters">
                  Tout effacer
                </button>
              </h3>

              <!-- Recherche dans les filtres -->
              <div class="filter-search">
                <input
                  type="text"
                  placeholder="Rechercher dans les filtres..."
                  id="filterSearch"
                />
                <i class="fas fa-search"></i>
              </div>
            </div>

            <!-- Filtre par catégorie -->
            <div class="sidebar-section">
              <h4 class="filter-title">
                <i class="fas fa-tags"></i> Catégories
                <span class="filter-count" id="categoryCount">0</span>
              </h4>
              <div class="filter-options" id="categoryFilters">
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="anniversaire"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Anniversaires</span>
                  <span class="option-count">42</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="valentin"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Saint-Valentin</span>
                  <span class="option-count">28</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="mariage"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Mariage</span>
                  <span class="option-count">35</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="naissance"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Naissance</span>
                  <span class="option-count">23</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="diplome"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Diplômés</span>
                  <span class="option-count">19</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="noel"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Noël</span>
                  <span class="option-count">47</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="entreprise"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Cadeaux d'entreprise</span>
                  <span class="option-count">31</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="category"
                    value="retraite"
                    data-filter="category"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Retraite</span>
                  <span class="option-count">15</span>
                </label>
              </div>
            </div>

            <!-- Filtre par prix -->
            <div class="sidebar-section">
              <h4 class="filter-title">
                <i class="fas fa-euro-sign"></i> Fourchette de prix
              </h4>
              <div class="price-filter">
                <div class="price-inputs">
                  <div class="price-input-group">
                    <label>Min</label>
                    <input
                      type="number"
                      id="priceMin"
                      placeholder="0"
                      min="0"
                      max="1000"
                      value="0"
                    />
                    <span>€</span>
                  </div>
                  <div class="price-separator">-</div>
                  <div class="price-input-group">
                    <label>Max</label>
                    <input
                      type="number"
                      id="priceMax"
                      placeholder="500"
                      min="0"
                      max="1000"
                      value="500"
                    />
                    <span>€</span>
                  </div>
                </div>
                <div class="price-slider">
                  <input
                    type="range"
                    id="priceSliderMin"
                    min="0"
                    max="1000"
                    value="0"
                    step="10"
                  />
                  <input
                    type="range"
                    id="priceSliderMax"
                    min="0"
                    max="1000"
                    value="500"
                    step="10"
                  />
                  <div class="price-slider-track"></div>
                </div>
                <div class="price-display">
                  <span id="priceRangeDisplay">0€ - 500€</span>
                </div>
              </div>
            </div>

            <!-- Filtre par notation -->
            <div class="sidebar-section">
              <h4 class="filter-title">
                <i class="fas fa-star"></i> Avis clients
              </h4>
              <div class="rating-filter">
                <label class="rating-option">
                  <input
                    type="radio"
                    name="rating"
                    value="5"
                    data-filter="rating"
                  />
                  <span class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <span class="rating-text">et plus</span>
                  </span>
                  <span class="rating-count">156</span>
                </label>
                <label class="rating-option">
                  <input
                    type="radio"
                    name="rating"
                    value="4"
                    data-filter="rating"
                  />
                  <span class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="far fa-star"></i>
                    <span class="rating-text">et plus</span>
                  </span>
                  <span class="rating-count">203</span>
                </label>
                <label class="rating-option">
                  <input
                    type="radio"
                    name="rating"
                    value="3"
                    data-filter="rating"
                  />
                  <span class="stars">
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="fas fa-star"></i>
                    <i class="far fa-star"></i>
                    <i class="far fa-star"></i>
                    <span class="rating-text">et plus</span>
                  </span>
                  <span class="rating-count">89</span>
                </label>
              </div>
            </div>

            <!-- Filtre par livraison -->
            <div class="sidebar-section">
              <h4 class="filter-title">
                <i class="fas fa-truck"></i> Livraison
              </h4>
              <div class="filter-options">
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="delivery"
                    value="express"
                    data-filter="delivery"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Livraison express (24h)</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="delivery"
                    value="gratuite"
                    data-filter="delivery"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Livraison gratuite</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="delivery"
                    value="cadeau"
                    data-filter="delivery"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Emballage cadeau</span>
                </label>
              </div>
            </div>

            <!-- Filtre par caractéristiques -->
            <div class="sidebar-section">
              <h4 class="filter-title">
                <i class="fas fa-bolt"></i> Caractéristiques
              </h4>
              <div class="filter-options">
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="feature"
                    value="personnalisable"
                    data-filter="feature"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Personnalisable</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="feature"
                    value="ecologique"
                    data-filter="feature"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Écologique</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="feature"
                    value="madeinfrance"
                    data-filter="feature"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Fabriqué en France</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="feature"
                    value="artisanal"
                    data-filter="feature"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Artisanal</span>
                </label>
                <label class="filter-option">
                  <input
                    type="checkbox"
                    name="feature"
                    value="exclusif"
                    data-filter="feature"
                  />
                  <span class="checkmark"></span>
                  <span class="option-text">Exclusif</span>
                </label>
              </div>
            </div>
          </aside>

          <!-- Contenu principal -->
          <div class="products-main">
            <!-- Barre d'outils -->
            <div class="products-toolbar">
              <div class="toolbar-left">
                <p class="results-count">
                  <span id="productsCount">0</span> résultats
                  <span id="searchQueryText"></span>
                </p>
                <div class="selected-filters" id="selectedFilters">
                  <!-- Filtres actifs -->
                </div>
              </div>
              <div class="toolbar-right">
                <div class="sort-by">
                  <label for="sortSelect">Trier par :</label>
                  <select id="sortSelect" class="sort-select">
                    <option value="pertinence">Pertinence</option>
                    <option value="nouveaute">Nouveautés</option>
                    <option value="prix-croissant">Prix croissant</option>
                    <option value="prix-decriossant">Prix décroissant</option>
                    <option value="meilleurs-avis">Meilleurs avis</option>
                    <option value="plus-vendus">Plus vendus</option>
                  </select>
                </div>
                <div class="view-toggle">
                  <button
                    class="view-btn active"
                    data-view="grid"
                    title="Vue grille"
                  >
                    <i class="fas fa-th"></i>
                  </button>
                  <button class="view-btn" data-view="list" title="Vue liste">
                    <i class="fas fa-list"></i>
                  </button>
                </div>
              </div>
            </div>

            <!-- Résultats de recherche -->
            <div class="products-results">
              <!-- Message d'état -->
              <div class="search-state" id="searchState">
                <div class="state-loading" id="loadingState">
                  <div class="loading-spinner"></div>
                  <p>Recherche en cours...</p>
                </div>
                <div class="state-empty" id="emptyState" style="display: none">
                  <i class="fas fa-search fa-3x"></i>
                  <h3>Aucun résultat trouvé</h3>
                  <p>Essayez de modifier vos critères de recherche</p>
                  <button class="btn btn-secondary" id="resetSearchBtn">
                    Réinitialiser la recherche
                  </button>
                </div>
              </div>

              <!-- Grille des produits -->
              <div class="products-grid grid-view" id="productsGrid">
                <!-- Les produits seront injectés par JavaScript -->
              </div>

              <!-- Liste des produits -->
              <div
                class="products-list list-view"
                id="productsList"
                style="display: none"
              >
                <!-- Les produits en vue liste seront injectés par JavaScript -->
              </div>

              <!-- Pagination -->
              <div class="pagination" id="pagination">
                <button class="pagination-btn prev" disabled>
                  <i class="fas fa-chevron-left"></i> Précédent
                </button>
                <div class="pagination-numbers">
                  <button class="page-number active">1</button>
                  <button class="page-number">2</button>
                  <button class="page-number">3</button>
                  <span class="page-dots">...</span>
                  <button class="page-number">8</button>
                </div>
                <button class="pagination-btn next">
                  Suivant <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>

            <!-- Suggestions de recherche -->
            <div class="search-suggestions-bottom">
              <h4>Recherches fréquentes :</h4>
              <div class="suggestion-tags">
                <a href="#" class="suggestion-tag" data-search="cadeau homme"
                  >Cadeau pour homme</a
                >
                <a href="#" class="suggestion-tag" data-search="cadeau femme"
                  >Cadeau pour femme</a
                >
                <a href="#" class="suggestion-tag" data-search="moins de 30€"
                  >Moins de 30€</a
                >
                <a href="#" class="suggestion-tag" data-search="cadeau original"
                  >Cadeau original</a
                >
                <a
                  href="#"
                  class="suggestion-tag"
                  data-search="cadeau naissance fille"
                  >Naissance fille</a
                >
                <a href="#" class="suggestion-tag" data-search="cadeau de noel"
                  >Cadeau de Noël</a
                >
                <a
                  href="#"
                  class="suggestion-tag"
                  data-search="cadeau personnalisé"
                  >Cadeau personnalisé</a
                >
                <a href="#" class="suggestion-tag" data-search="cadeau chic"
                  >Cadeau chic</a
                >
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Modal de filtre mobile -->
    <div class="filter-modal" id="filterModal">
      <div class="filter-modal-content">
        <div class="filter-modal-header">
          <h3>Filtres</h3>
          <button class="filter-modal-close">&times;</button>
        </div>
        <div class="filter-modal-body">
          <!-- Les filtres mobiles seront injectés ici -->
        </div>
        <div class="filter-modal-footer">
          <button class="btn btn-secondary" id="clearFiltersMobile">
            Effacer tout
          </button>
          <button class="btn btn-primary" id="applyFiltersMobile">
            Appliquer (0)
          </button>
        </div>
      </div>
    </div>

    <!-- Bouton filtre mobile -->
    <button class="filter-mobile-btn" id="filterMobileBtn">
      <i class="fas fa-filter"></i>
      <span>Filtres</span>
      <span class="filter-badge">0</span>
    </button>

    <!-- Footer (identique à l'accueil) -->
    <footer class="footer">
      <!-- ... même contenu que l'accueil ... -->
    </footer>

    <!-- Scripts -->
    <script src="js/main.js"></script>
    <script src="js/produits.js"></script>
  </body>
</html>


<?php
// produits.php

require_once 'config/database.php';

// Récupérer les paramètres de recherche
$recherche = $_GET['q'] ?? '';
$categorie = $_GET['categorie'] ?? '';
$prix_min = $_GET['prix_min'] ?? '';
$prix_max = $_GET['prix_max'] ?? '';
$tri = $_GET['tri'] ?? 'pertinence';
$page = max(1, intval($_GET['page'] ?? 1));

// Construire les filtres
$filtres = [
    'recherche' => $recherche,
    'categorie' => $categorie,
    'prix_min' => $prix_min,
    'prix_max' => $prix_max,
    'tri' => $tri,
    'page' => $page,
    'limit' => 12
];

// Récupérer les produits
$produits = getProduitsFiltres($filtres);

// Récupérer les catégories pour les filtres
$categories = getCategoriesAvecCompteur();

// Compter le total des produits pour la pagination
$db = Database::getInstance();
$sqlCount = "SELECT COUNT(*) as total FROM produits p WHERE p.statut = 'actif'";
// Ajouter les mêmes conditions que pour la recherche
// ...
$stmtCount = $db->prepare($sqlCount);
$stmtCount->execute($paramsCount);
$totalProduits = $stmtCount->fetch()['total'];
$totalPages = ceil($totalProduits / $filtres['limit']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recherche de produits - Cadeaux Élégance</title>
    <!-- CSS et métadonnées -->
</head>
<body>
    <!-- Barre de recherche -->
    <form method="GET" class="search-form">
        <input type="text" name="q" value="<?= htmlspecialchars($recherche) ?>" 
               placeholder="Rechercher un cadeau...">
        
        <select name="categorie">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id_categorie'] ?>" 
                    <?= $categorie == $cat['id_categorie'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nom']) ?> (<?= $cat['nb_produits'] ?>)
            </option>
            <?php endforeach; ?>
        </select>
        
        <input type="number" name="prix_min" placeholder="Prix min" value="<?= $prix_min ?>">
        <input type="number" name="prix_max" placeholder="Prix max" value="<?= $prix_max ?>">
        
        <select name="tri">
            <option value="pertinence" <?= $tri == 'pertinence' ? 'selected' : '' ?>>Pertinence</option>
            <option value="prix-croissant" <?= $tri == 'prix-croissant' ? 'selected' : '' ?>>Prix croissant</option>
            <option value="prix-decriossant" <?= $tri == 'prix-decriossant' ? 'selected' : '' ?>>Prix décroissant</option>
            <option value="nouveaute" <?= $tri == 'nouveaute' ? 'selected' : '' ?>>Nouveautés</option>
        </select>
        
        <button type="submit">Rechercher</button>
    </form>
    
    <!-- Affichage des résultats -->
    <div class="results-info">
        <?php if ($recherche): ?>
            <h2>Résultats pour "<?= htmlspecialchars($recherche) ?>"</h2>
        <?php endif; ?>
        <p><?= $totalProduits ?> produit(s) trouvé(s)</p>
    </div>
    
    <!-- Grille de produits -->
    <div class="products-grid">
        <?php foreach ($produits as $produit): ?>
        <div class="product-card">
            <?php if ($produit['image']): ?>
            <img src="<?= htmlspecialchars($produit['image']) ?>" 
                 alt="<?= htmlspecialchars($produit['nom']) ?>">
            <?php endif; ?>
            
            <div class="product-info">
                <span class="product-category"><?= htmlspecialchars($produit['categorie_nom']) ?></span>
                <h3><?= htmlspecialchars($produit['nom']) ?></h3>
                <p class="product-price"><?= number_format($produit['prix_ttc'], 2, ',', ' ') ?> €</p>
                
                <?php if ($produit['note_moyenne'] > 0): ?>
                <div class="product-rating">
                    <?php 
                    $fullStars = floor($produit['note_moyenne']);
                    $hasHalfStar = ($produit['note_moyenne'] - $fullStars) >= 0.5;
                    ?>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <?php if ($i <= $fullStars): ?>
                            <i class="fas fa-star"></i>
                        <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                            <i class="fas fa-star-half-alt"></i>
                        <?php else: ?>
                            <i class="far fa-star"></i>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <span>(<?= $produit['nombre_avis'] ?>)</span>
                </div>
                <?php endif; ?>
                
                <a href="produit.php?id=<?= $produit['id_produit'] ?>" class="btn-view">Voir le produit</a>
                <button class="btn-add-cart" data-id="<?= $produit['id_produit'] ?>">
                    Ajouter au panier
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
           class="page-link">Précédent</a>
        <?php endif; ?>
        
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
               class="page-link <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
            <span class="page-dots">...</span>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
           class="page-link">Suivant</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Scripts -->
    <script>
    // Gestion de l'ajout au panier
    document.querySelectorAll('.btn-add-cart').forEach(button => {
        button.addEventListener('click', async function() {
            const produitId = this.dataset.id;
            
            try {
                const response = await fetch('api/panier.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'ajouter',
                        id_produit: produitId,
                        quantite: 1
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Mettre à jour le compteur du panier
                    document.querySelector('.cart-count').textContent = result.total_items;
                    
                    // Afficher une notification
                    alert('Produit ajouté au panier !');
                } else {
                    alert('Erreur : ' + result.message);
                }
            } catch (error) {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            }
        });
    });
    </script>
</body>
</html>