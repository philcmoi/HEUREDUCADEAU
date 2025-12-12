-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 10 déc. 2025 à 03:12
-- Version du serveur : 8.2.0
-- Version de PHP : 8.2.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `heureducadeau`
--

DELIMITER $$
--
-- Procédures
--
DROP PROCEDURE IF EXISTS `ajouter_au_panier`$$
CREATE DEFINER=`root`@`localhost` PROCEDURE `ajouter_au_panier` (IN `p_id_client` INT, IN `p_session_id` VARCHAR(255), IN `p_id_produit` INT, IN `p_id_variant` INT, IN `p_quantite` INT, IN `p_options` JSON)   BEGIN
    DECLARE v_id_panier INT;
    DECLARE v_prix DECIMAL(10,2);
    
    -- Trouver ou créer le panier
    SELECT id_panier INTO v_id_panier 
    FROM panier 
    WHERE (id_client = p_id_client OR session_id = p_session_id)
    LIMIT 1;
    
    IF v_id_panier IS NULL THEN
        INSERT INTO panier (id_client, session_id) 
        VALUES (p_id_client, p_session_id);
        SET v_id_panier = LAST_INSERT_ID();
    END IF;
    
    -- Récupérer le prix du produit
    SELECT prix_ttc INTO v_prix 
    FROM produits 
    WHERE id_produit = p_id_produit;
    
    -- Ajouter le produit au panier
    INSERT INTO panier_items (id_panier, id_produit, id_variant, quantite, prix_unitaire, options)
    VALUES (v_id_panier, p_id_produit, p_id_variant, p_quantite, v_prix, p_options)
    ON DUPLICATE KEY UPDATE 
        quantite = quantite + p_quantite,
        date_ajout = CURRENT_TIMESTAMP;
    
    -- Mettre à jour la date de modification du panier
    UPDATE panier SET date_modification = CURRENT_TIMESTAMP WHERE id_panier = v_id_panier;
    
    SELECT v_id_panier as id_panier;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `administrateurs`
--

DROP TABLE IF EXISTS `administrateurs`;
CREATE TABLE IF NOT EXISTS `administrateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','moderator','editor') DEFAULT 'editor',
  `status` enum('active','inactive','locked') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL,
  `last_attempt` datetime DEFAULT NULL,
  `login_attempts` int DEFAULT '0',
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `administrateurs`
--

INSERT INTO `administrateurs` (`id`, `username`, `password_hash`, `email`, `role`, `status`, `last_login`, `last_attempt`, `login_attempts`, `two_factor_enabled`, `two_factor_secret`, `created_at`, `updated_at`) VALUES
(3, 'admin', '007', 'lhpp.philippe@gmail.com', 'superadmin', 'active', '2025-12-10 02:39:26', '2025-12-10 02:39:26', 0, 0, NULL, '2025-12-07 06:28:27', '2025-12-10 02:39:26');

-- --------------------------------------------------------

--
-- Structure de la table `adresses`
--

DROP TABLE IF EXISTS `adresses`;
CREATE TABLE IF NOT EXISTS `adresses` (
  `id_adresse` int NOT NULL AUTO_INCREMENT,
  `id_client` int NOT NULL,
  `type_adresse` enum('livraison','facturation') DEFAULT 'livraison',
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `societe` varchar(255) DEFAULT NULL,
  `adresse` text NOT NULL,
  `complement` text,
  `code_postal` varchar(10) NOT NULL,
  `ville` varchar(100) NOT NULL,
  `pays` varchar(100) DEFAULT 'France',
  `telephone` varchar(20) DEFAULT NULL,
  `principale` tinyint(1) DEFAULT '0',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_adresse`),
  KEY `idx_client` (`id_client`),
  KEY `idx_type` (`type_adresse`),
  KEY `idx_principale` (`principale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE IF NOT EXISTS `avis` (
  `id_avis` int NOT NULL AUTO_INCREMENT,
  `id_produit` int NOT NULL,
  `id_client` int NOT NULL,
  `id_commande` int DEFAULT NULL,
  `note` int DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL,
  `commentaire` text,
  `reponse` text COMMENT 'Réponse du vendeur',
  `statut` enum('en_attente','approuve','rejete') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_avis`),
  UNIQUE KEY `unique_avis_commande` (`id_produit`,`id_client`,`id_commande`),
  KEY `id_commande` (`id_commande`),
  KEY `idx_produit` (`id_produit`),
  KEY `idx_client` (`id_client`),
  KEY `idx_note` (`note`),
  KEY `idx_statut` (`statut`)
) ;

--
-- Déclencheurs `avis`
--
DROP TRIGGER IF EXISTS `after_avis_insert`;
DELIMITER $$
CREATE TRIGGER `after_avis_insert` AFTER INSERT ON `avis` FOR EACH ROW BEGIN
    UPDATE produits p
    SET p.note_moyenne = (
        SELECT AVG(note)
        FROM avis a
        WHERE a.id_produit = NEW.id_produit 
        AND a.statut = 'approuve'
    ),
    p.nombre_avis = (
        SELECT COUNT(*)
        FROM avis a
        WHERE a.id_produit = NEW.id_produit
        AND a.statut = 'approuve'
    )
    WHERE p.id_produit = NEW.id_produit;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id_categorie` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `parent_id` int DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `meta_titre` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_categorie`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id_categorie`, `nom`, `slug`, `description`, `parent_id`, `image`, `ordre`, `active`, `meta_titre`, `meta_description`, `date_creation`) VALUES
(1, 'Tous les cadeaux', 'tous-les-cadeaux', 'Notre collection complète', NULL, NULL, 1, 1, NULL, NULL, '2025-12-07 05:13:16'),
(2, 'Anniversaires', 'anniversaires', 'Cadeaux pour anniversaires', NULL, NULL, 2, 1, NULL, NULL, '2025-12-07 05:13:16'),
(3, 'Saint-Valentin', 'saint-valentin', 'Cadeaux romantiques', NULL, NULL, 3, 1, NULL, NULL, '2025-12-07 05:13:16'),
(4, 'Mariage', 'mariage', 'Cadeaux de mariage élégants', NULL, NULL, 4, 1, NULL, NULL, '2025-12-07 05:13:16'),
(5, 'Naissance', 'naissance', 'Pour accueillir bébé', NULL, NULL, 5, 1, NULL, NULL, '2025-12-07 05:13:16'),
(6, 'Diplômés', 'diplomes', 'Cadeaux pour célébrer la réussite', NULL, NULL, 6, 1, NULL, NULL, '2025-12-07 05:13:16'),
(7, 'Noël', 'noel', 'Magie des fêtes de fin d\'année', NULL, NULL, 7, 1, NULL, NULL, '2025-12-07 05:13:16'),
(8, 'Cadeaux d\'entreprise', 'cadeaux-entreprise', 'Cadeaux professionnels', NULL, NULL, 8, 1, NULL, NULL, '2025-12-07 05:13:16'),
(9, 'Retraite', 'retraite', 'Cadeaux pour la retraite', NULL, NULL, 9, 1, NULL, NULL, '2025-12-07 05:13:16');

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

DROP TABLE IF EXISTS `clients`;
CREATE TABLE IF NOT EXISTS `clients` (
  `id_client` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `genre` enum('homme','femme','autre') DEFAULT NULL,
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif','banni') DEFAULT 'actif',
  `newsletter` tinyint(1) DEFAULT '1',
  `dernier_connexion` datetime DEFAULT NULL,
  `token_reset` varchar(255) DEFAULT NULL,
  `token_expiration` datetime DEFAULT NULL,
  PRIMARY KEY (`id_client`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_nom` (`nom`,`prenom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `id_commande` int NOT NULL AUTO_INCREMENT,
  `numero_commande` varchar(50) NOT NULL,
  `id_client` int NOT NULL,
  `id_adresse_livraison` int NOT NULL,
  `id_adresse_facturation` int NOT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','expediee','livree','annulee','remboursee') DEFAULT 'en_attente',
  `sous_total` decimal(10,2) NOT NULL,
  `frais_livraison` decimal(10,2) DEFAULT '0.00',
  `reduction` decimal(10,2) DEFAULT '0.00',
  `total_ttc` decimal(10,2) NOT NULL,
  `mode_paiement` enum('carte','paypal','virement','cheque') DEFAULT 'carte',
  `statut_paiement` enum('en_attente','paye','echec','rembourse') DEFAULT 'en_attente',
  `reference_paiement` varchar(255) DEFAULT NULL,
  `transporteur` varchar(100) DEFAULT NULL,
  `numero_suivi` varchar(100) DEFAULT NULL,
  `instructions` text,
  `date_commande` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_paiement` datetime DEFAULT NULL,
  `date_expedition` datetime DEFAULT NULL,
  `date_livraison_estimee` date DEFAULT NULL,
  `date_livraison_reelle` datetime DEFAULT NULL,
  PRIMARY KEY (`id_commande`),
  UNIQUE KEY `numero_commande` (`numero_commande`),
  KEY `id_adresse_livraison` (`id_adresse_livraison`),
  KEY `id_adresse_facturation` (`id_adresse_facturation`),
  KEY `idx_client` (`id_client`),
  KEY `idx_numero` (`numero_commande`),
  KEY `idx_statut` (`statut`),
  KEY `idx_date` (`date_commande`),
  KEY `idx_commandes_client_date` (`id_client`,`date_commande` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déclencheurs `commandes`
--
DROP TRIGGER IF EXISTS `before_commande_insert`;
DELIMITER $$
CREATE TRIGGER `before_commande_insert` BEFORE INSERT ON `commandes` FOR EACH ROW BEGIN
    SET NEW.numero_commande = CONCAT(
        'CMD-',
        DATE_FORMAT(NOW(), '%Y%m'),
        '-',
        LPAD((SELECT AUTO_INCREMENT FROM information_schema.TABLES 
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commandes'), 6, '0')
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `commande_items`
--

DROP TABLE IF EXISTS `commande_items`;
CREATE TABLE IF NOT EXISTS `commande_items` (
  `id_item` int NOT NULL AUTO_INCREMENT,
  `id_commande` int NOT NULL,
  `id_produit` int NOT NULL,
  `id_variant` int DEFAULT NULL,
  `reference_produit` varchar(50) NOT NULL,
  `nom_produit` varchar(255) NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire_ht` decimal(10,2) NOT NULL,
  `prix_unitaire_ttc` decimal(10,2) NOT NULL,
  `tva` decimal(4,2) NOT NULL,
  `options` text COMMENT 'JSON des options au moment de la commande',
  PRIMARY KEY (`id_item`),
  KEY `idx_commande` (`id_commande`),
  KEY `idx_produit` (`id_produit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `configuration`
--

DROP TABLE IF EXISTS `configuration`;
CREATE TABLE IF NOT EXISTS `configuration` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `cle` varchar(100) NOT NULL,
  `valeur` text,
  `type` enum('string','integer','boolean','json','array') DEFAULT 'string',
  `categorie` varchar(50) DEFAULT NULL,
  `description` text,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_config`),
  UNIQUE KEY `cle` (`cle`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `configuration`
--

INSERT INTO `configuration` (`id_config`, `cle`, `valeur`, `type`, `categorie`, `description`, `date_modification`) VALUES
(1, 'site_nom', 'Cadeaux Élégance', 'string', 'general', 'Nom du site', NULL),
(2, 'site_email', 'contact@cadeaux-elegance.fr', 'string', 'general', 'Email de contact', NULL),
(3, 'site_telephone', '01 23 45 67 89', 'string', 'general', 'Téléphone de contact', NULL),
(4, 'devise', 'EUR', 'string', 'general', 'Devise du site', NULL),
(5, 'tva_par_defaut', '20.00', '', 'general', 'TVA par défaut', NULL),
(6, 'frais_livraison', '4.90', '', 'livraison', 'Frais de livraison standard', NULL),
(7, 'seuil_livraison_gratuite', '50.00', '', 'livraison', 'Montant pour livraison gratuite', NULL),
(8, 'stock_alerte_seuil', '10', 'integer', 'produits', 'Seuil d\'alerte de stock', NULL),
(9, 'produits_par_page', '12', 'integer', 'produits', 'Nombre de produits par page', NULL),
(10, 'recherche_suggestions', 'anniversaire,mariage,naissance,noel', 'array', 'recherche', 'Suggestions de recherche', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `images_produits`
--

DROP TABLE IF EXISTS `images_produits`;
CREATE TABLE IF NOT EXISTS `images_produits` (
  `id_image` int NOT NULL AUTO_INCREMENT,
  `id_produit` int NOT NULL,
  `url_image` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `principale` tinyint(1) DEFAULT '0',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_image`),
  UNIQUE KEY `unique_ordre_produit` (`id_produit`,`ordre`),
  KEY `idx_produit` (`id_produit`),
  KEY `idx_principale` (`principale`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

DROP TABLE IF EXISTS `logs`;
CREATE TABLE IF NOT EXISTS `logs` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `type_log` enum('erreur','info','securite','paiement') NOT NULL,
  `niveau` enum('debug','info','warning','error','critical') DEFAULT 'info',
  `message` text NOT NULL,
  `utilisateur_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `url` text,
  `metadata` text COMMENT 'JSON des données supplémentaires',
  `date_log` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_log`),
  KEY `idx_type` (`type_log`),
  KEY `idx_date` (`date_log`),
  KEY `idx_niveau` (`niveau`),
  KEY `idx_utilisateur` (`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `pages`
--

DROP TABLE IF EXISTS `pages`;
CREATE TABLE IF NOT EXISTS `pages` (
  `id_page` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `meta_titre` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `statut` enum('publie','brouillon','prive') DEFAULT 'publie',
  `ordre` int DEFAULT '0',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_page`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_statut` (`statut`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `pages`
--

INSERT INTO `pages` (`id_page`, `titre`, `slug`, `contenu`, `meta_titre`, `meta_description`, `statut`, `ordre`, `date_creation`, `date_modification`) VALUES
(1, 'À propos', 'a-propos', 'Contenu de la page À propos...', NULL, NULL, 'publie', 1, '2025-12-07 05:17:32', NULL),
(2, 'Conditions générales', 'conditions-generales', 'Contenu des CGV...', NULL, NULL, 'publie', 2, '2025-12-07 05:17:32', NULL),
(3, 'Politique de confidentialité', 'confidentialite', 'Contenu de la politique de confidentialité...', NULL, NULL, 'publie', 3, '2025-12-07 05:17:32', NULL),
(4, 'Mentions légales', 'mentions-legales', 'Contenu des mentions légales...', NULL, NULL, 'publie', 4, '2025-12-07 05:17:32', NULL),
(5, 'Livraison', 'livraison', 'Informations sur la livraison...', NULL, NULL, 'publie', 5, '2025-12-07 05:17:32', NULL),
(6, 'Retours', 'retours', 'Politique de retours...', NULL, NULL, 'publie', 6, '2025-12-07 05:17:32', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `id_panier` int NOT NULL AUTO_INCREMENT,
  `id_client` int DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_panier`),
  UNIQUE KEY `unique_panier` (`id_client`,`session_id`),
  KEY `idx_client` (`id_client`),
  KEY `idx_session` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `panier_items`
--

DROP TABLE IF EXISTS `panier_items`;
CREATE TABLE IF NOT EXISTS `panier_items` (
  `id_item` int NOT NULL AUTO_INCREMENT,
  `id_panier` int NOT NULL,
  `id_produit` int NOT NULL,
  `id_variant` int DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prix_unitaire` decimal(10,2) NOT NULL,
  `options` text COMMENT 'JSON des options supplémentaires',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_item`),
  UNIQUE KEY `unique_item` (`id_panier`,`id_produit`,`id_variant`),
  KEY `id_variant` (`id_variant`),
  KEY `idx_panier` (`id_panier`),
  KEY `idx_produit` (`id_produit`),
  KEY `idx_panier_items_panier_produit` (`id_panier`,`id_produit`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

DROP TABLE IF EXISTS `produits`;
CREATE TABLE IF NOT EXISTS `produits` (
  `id_produit` int NOT NULL AUTO_INCREMENT,
  `reference` varchar(50) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `description_courte` text,
  `prix_ht` decimal(10,2) NOT NULL,
  `tva` decimal(4,2) DEFAULT '20.00',
  `prix_ttc` decimal(10,2) GENERATED ALWAYS AS ((`prix_ht` * (1 + (`tva` / 100)))) STORED,
  `quantite_stock` int DEFAULT '0',
  `seuil_alerte` int DEFAULT '10',
  `id_categorie` int NOT NULL,
  `marque` varchar(100) DEFAULT NULL,
  `poids` decimal(6,2) DEFAULT NULL COMMENT 'en grammes',
  `dimensions` varchar(50) DEFAULT NULL COMMENT 'LxHxP en cm',
  `materiau` varchar(100) DEFAULT NULL,
  `couleur` varchar(50) DEFAULT NULL,
  `made_in` varchar(50) DEFAULT NULL,
  `personnalisable` tinyint(1) DEFAULT '0',
  `ecologique` tinyint(1) DEFAULT '0',
  `made_in_france` tinyint(1) DEFAULT '0',
  `artisanal` tinyint(1) DEFAULT '0',
  `exclusif` tinyint(1) DEFAULT '0',
  `note_moyenne` decimal(3,2) DEFAULT '0.00',
  `nombre_avis` int DEFAULT '0',
  `vues` int DEFAULT '0',
  `ventes` int DEFAULT '0',
  `statut` enum('actif','inactif','rupture','bientot') DEFAULT 'actif',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_produit`),
  UNIQUE KEY `reference` (`reference`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_categorie` (`id_categorie`),
  KEY `idx_reference` (`reference`),
  KEY `idx_slug` (`slug`),
  KEY `idx_prix` (`prix_ttc`),
  KEY `idx_statut` (`statut`),
  KEY `idx_popularite` (`ventes` DESC,`note_moyenne` DESC),
  KEY `idx_produits_categorie_prix` (`id_categorie`,`prix_ttc`,`statut`),
  KEY `idx_produits_popularite` (`ventes` DESC,`note_moyenne` DESC,`date_creation` DESC)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id_produit`, `reference`, `nom`, `slug`, `description`, `description_courte`, `prix_ht`, `tva`, `quantite_stock`, `seuil_alerte`, `id_categorie`, `marque`, `poids`, `dimensions`, `materiau`, `couleur`, `made_in`, `personnalisable`, `ecologique`, `made_in_france`, `artisanal`, `exclusif`, `note_moyenne`, `nombre_avis`, `vues`, `ventes`, `statut`, `date_creation`, `date_modification`) VALUES
(1, 'REF001', 'Bougie parfumée \"Élégance\"', 'bougie-parfumee-elegance', 'Bougie artisanale parfum vanille et santal. 50h de combustion.', 'Bougie artisanale parfum vanille et santal', 29.08, 20.00, 100, 10, 1, 'Artisanat Français', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(2, 'REF002', 'Coffret gourmand \"Délice\"', 'coffret-gourmand-delice', 'Sélection des meilleures spécialités françaises. Emballage cadeau inclus.', 'Sélection de spécialités françaises', 41.58, 20.00, 50, 10, 1, 'Saveurs de France', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(3, 'REF003', 'Montre \"Temps Précieux\"', 'montre-temps-precieux', 'Montre élégante avec gravure personnalisée au dos du boitier.', 'Montre avec gravure personnalisée', 74.92, 20.00, 25, 5, 1, 'Luxe & Style', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(4, 'REF004', 'Set bijoux \"Lumière\"', 'set-bijoux-lumiere', 'Collier, boucles d\'oreilles et bracelet assortis. Argent 925.', 'Set bijoux en argent 925', 1000.00, 20.00, 30, 5, 1, 'Bijoux d\'Exception', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, 0.00, 0, 0, 0, 'actif', '2025-12-07 16:56:32', '2025-12-10 03:17:08');

-- --------------------------------------------------------

--
-- Structure de la table `promotions`
--

DROP TABLE IF EXISTS `promotions`;
CREATE TABLE IF NOT EXISTS `promotions` (
  `id_promotion` int NOT NULL AUTO_INCREMENT,
  `code_promotion` varchar(50) DEFAULT NULL,
  `type_promotion` enum('pourcentage','montant_fixe','livraison_gratuite') DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL,
  `montant_minimum` decimal(10,2) DEFAULT '0.00',
  `utilisations_max` int DEFAULT NULL,
  `utilisations_actuelles` int DEFAULT '0',
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `produits_ids` text COMMENT 'IDs séparés par des virgules, vide = tous',
  `categories_ids` text COMMENT 'IDs séparés par des virgules, vide = toutes',
  `description` text,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_promotion`),
  UNIQUE KEY `code_promotion` (`code_promotion`),
  KEY `idx_code` (`code_promotion`),
  KEY `idx_dates` (`date_debut`,`date_fin`),
  KEY `idx_actif` (`actif`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `recherches`
--

DROP TABLE IF EXISTS `recherches`;
CREATE TABLE IF NOT EXISTS `recherches` (
  `id_recherche` int NOT NULL AUTO_INCREMENT,
  `id_client` int DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `terme_recherche` varchar(255) NOT NULL,
  `categorie_id` int DEFAULT NULL,
  `prix_min` decimal(10,2) DEFAULT NULL,
  `prix_max` decimal(10,2) DEFAULT NULL,
  `filtres` text COMMENT 'JSON des filtres appliqués',
  `nombre_resultats` int DEFAULT NULL,
  `date_recherche` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_recherche`),
  KEY `categorie_id` (`categorie_id`),
  KEY `idx_client` (`id_client`),
  KEY `idx_session` (`session_id`),
  KEY `idx_terme` (`terme_recherche`),
  KEY `idx_date` (`date_recherche`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `statistiques`
--

DROP TABLE IF EXISTS `statistiques`;
CREATE TABLE IF NOT EXISTS `statistiques` (
  `id_statistique` int NOT NULL AUTO_INCREMENT,
  `date_stat` date NOT NULL,
  `type_stat` enum('visite','produit_vu','recherche','panier_ajout','achat') NOT NULL,
  `id_produit` int DEFAULT NULL,
  `id_categorie` int DEFAULT NULL,
  `valeur` int DEFAULT '1',
  `metadata` text COMMENT 'JSON des données supplémentaires',
  PRIMARY KEY (`id_statistique`),
  UNIQUE KEY `unique_stat` (`date_stat`,`type_stat`,`id_produit`,`id_categorie`),
  KEY `id_categorie` (`id_categorie`),
  KEY `idx_date` (`date_stat`),
  KEY `idx_type` (`type_stat`),
  KEY `idx_produit` (`id_produit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `variants`
--

DROP TABLE IF EXISTS `variants`;
CREATE TABLE IF NOT EXISTS `variants` (
  `id_variant` int NOT NULL AUTO_INCREMENT,
  `id_produit` int NOT NULL,
  `nom_variant` varchar(100) NOT NULL COMMENT 'ex: Taille, Couleur',
  `valeur` varchar(100) NOT NULL COMMENT 'ex: L, Rouge',
  `prix_supplement` decimal(10,2) DEFAULT '0.00',
  `quantite_stock` int DEFAULT '0',
  `reference_variant` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id_variant`),
  UNIQUE KEY `unique_variant` (`id_produit`,`nom_variant`,`valeur`),
  KEY `idx_produit` (`id_produit`),
  KEY `idx_actif` (`actif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_stock_alerte`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_produits_stock_alerte`;
CREATE TABLE IF NOT EXISTS `vue_produits_stock_alerte` (
`categorie` varchar(100)
,`id_produit` int
,`nom` varchar(255)
,`quantite_stock` int
,`reference` varchar(50)
,`seuil_alerte` int
,`statut_stock` varchar(7)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_statistiques_produits`
-- (Voir ci-dessous la vue réelle)
--
DROP VIEW IF EXISTS `vue_statistiques_produits`;
CREATE TABLE IF NOT EXISTS `vue_statistiques_produits` (
`categorie` varchar(100)
,`chiffre_affaires` decimal(42,2)
,`dans_wishlist` bigint
,`id_produit` int
,`nom` varchar(255)
,`nombre_avis` int
,`note_moyenne` decimal(3,2)
,`prix_ttc` decimal(10,2)
,`quantite_stock` int
,`quantite_vendue_total` decimal(32,0)
,`reference` varchar(50)
,`ventes` int
);

-- --------------------------------------------------------

--
-- Structure de la table `wishlist`
--

DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE IF NOT EXISTS `wishlist` (
  `id_wishlist` int NOT NULL AUTO_INCREMENT,
  `id_client` int NOT NULL,
  `id_produit` int NOT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_wishlist`),
  UNIQUE KEY `unique_wishlist` (`id_client`,`id_produit`),
  KEY `idx_client` (`id_client`),
  KEY `idx_produit` (`id_produit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_produits_stock_alerte`
--
DROP TABLE IF EXISTS `vue_produits_stock_alerte`;

DROP VIEW IF EXISTS `vue_produits_stock_alerte`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_produits_stock_alerte`  AS SELECT `p`.`id_produit` AS `id_produit`, `p`.`reference` AS `reference`, `p`.`nom` AS `nom`, `p`.`quantite_stock` AS `quantite_stock`, `p`.`seuil_alerte` AS `seuil_alerte`, `c`.`nom` AS `categorie`, (case when (`p`.`quantite_stock` = 0) then 'rupture' when (`p`.`quantite_stock` <= `p`.`seuil_alerte`) then 'alerte' else 'normal' end) AS `statut_stock` FROM (`produits` `p` join `categories` `c` on((`p`.`id_categorie` = `c`.`id_categorie`))) WHERE ((`p`.`statut` = 'actif') AND ((`p`.`quantite_stock` = 0) OR (`p`.`quantite_stock` <= `p`.`seuil_alerte`))) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_statistiques_produits`
--
DROP TABLE IF EXISTS `vue_statistiques_produits`;

DROP VIEW IF EXISTS `vue_statistiques_produits`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_statistiques_produits`  AS SELECT `p`.`id_produit` AS `id_produit`, `p`.`nom` AS `nom`, `p`.`reference` AS `reference`, `c`.`nom` AS `categorie`, `p`.`prix_ttc` AS `prix_ttc`, `p`.`quantite_stock` AS `quantite_stock`, `p`.`ventes` AS `ventes`, `p`.`note_moyenne` AS `note_moyenne`, `p`.`nombre_avis` AS `nombre_avis`, count(distinct `w`.`id_wishlist`) AS `dans_wishlist`, sum(`ci`.`quantite`) AS `quantite_vendue_total`, sum((`ci`.`quantite` * `ci`.`prix_unitaire_ttc`)) AS `chiffre_affaires` FROM (((`produits` `p` left join `categories` `c` on((`p`.`id_categorie` = `c`.`id_categorie`))) left join `wishlist` `w` on((`p`.`id_produit` = `w`.`id_produit`))) left join `commande_items` `ci` on((`p`.`id_produit` = `ci`.`id_produit`))) GROUP BY `p`.`id_produit` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `produits`
--
ALTER TABLE `produits` ADD FULLTEXT KEY `idx_recherche` (`nom`,`description`,`description_courte`,`marque`);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `adresses`
--
ALTER TABLE `adresses`
  ADD CONSTRAINT `adresses_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE;

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `avis_ibfk_3` FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`);

--
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id_categorie`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`),
  ADD CONSTRAINT `commandes_ibfk_2` FOREIGN KEY (`id_adresse_livraison`) REFERENCES `adresses` (`id_adresse`),
  ADD CONSTRAINT `commandes_ibfk_3` FOREIGN KEY (`id_adresse_facturation`) REFERENCES `adresses` (`id_adresse`);

--
-- Contraintes pour la table `commande_items`
--
ALTER TABLE `commande_items`
  ADD CONSTRAINT `commande_items_ibfk_1` FOREIGN KEY (`id_commande`) REFERENCES `commandes` (`id_commande`) ON DELETE CASCADE,
  ADD CONSTRAINT `commande_items_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

--
-- Contraintes pour la table `images_produits`
--
ALTER TABLE `images_produits`
  ADD CONSTRAINT `images_produits_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE;

--
-- Contraintes pour la table `panier`
--
ALTER TABLE `panier`
  ADD CONSTRAINT `panier_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE;

--
-- Contraintes pour la table `panier_items`
--
ALTER TABLE `panier_items`
  ADD CONSTRAINT `panier_items_ibfk_1` FOREIGN KEY (`id_panier`) REFERENCES `panier` (`id_panier`) ON DELETE CASCADE,
  ADD CONSTRAINT `panier_items_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`),
  ADD CONSTRAINT `panier_items_ibfk_3` FOREIGN KEY (`id_variant`) REFERENCES `variants` (`id_variant`);

--
-- Contraintes pour la table `produits`
--
ALTER TABLE `produits`
  ADD CONSTRAINT `produits_ibfk_1` FOREIGN KEY (`id_categorie`) REFERENCES `categories` (`id_categorie`);

--
-- Contraintes pour la table `recherches`
--
ALTER TABLE `recherches`
  ADD CONSTRAINT `recherches_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`),
  ADD CONSTRAINT `recherches_ibfk_2` FOREIGN KEY (`categorie_id`) REFERENCES `categories` (`id_categorie`);

--
-- Contraintes pour la table `statistiques`
--
ALTER TABLE `statistiques`
  ADD CONSTRAINT `statistiques_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`),
  ADD CONSTRAINT `statistiques_ibfk_2` FOREIGN KEY (`id_categorie`) REFERENCES `categories` (`id_categorie`);

--
-- Contraintes pour la table `variants`
--
ALTER TABLE `variants`
  ADD CONSTRAINT `variants_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE;

--
-- Contraintes pour la table `wishlist`
--
ALTER TABLE `wishlist`
  ADD CONSTRAINT `wishlist_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `wishlist_ibfk_2` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
