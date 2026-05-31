-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : ven. 29 mai 2026 à 03:44
-- Version du serveur : 8.0.45-0ubuntu0.22.04.1
-- Version de PHP : 8.1.2-1ubuntu2.23

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

-- --------------------------------------------------------

--
-- Structure de la table `administrateurs`
--

CREATE TABLE `administrateurs` (
  `id` int NOT NULL,
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
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `administrateurs`
--

INSERT INTO `administrateurs` (`id`, `username`, `password_hash`, `email`, `role`, `status`, `last_login`, `last_attempt`, `login_attempts`, `two_factor_enabled`, `two_factor_secret`, `created_at`, `updated_at`) VALUES
(3, 'admin', '007', 'lhpp.philippe@gmail.com', 'superadmin', 'active', '2026-05-29 03:34:08', '2026-05-29 03:32:05', 0, 0, NULL, '2025-12-07 06:28:27', '2026-05-29 03:34:08');

-- --------------------------------------------------------

--
-- Structure de la table `adresses`
--

CREATE TABLE `adresses` (
  `id_adresse` int NOT NULL,
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
  `est_facturation_obligatoire` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `adresses`
--

INSERT INTO `adresses` (`id_adresse`, `id_client`, `type_adresse`, `nom`, `prenom`, `societe`, `adresse`, `complement`, `code_postal`, `ville`, `pays`, `telephone`, `principale`, `date_creation`, `est_facturation_obligatoire`) VALUES
(424, 34, 'livraison', 'Lor', 'Philippe', NULL, '116 rue de Javel', NULL, '75015', 'Paris', 'France', '0644982807', 0, '2026-05-18 02:30:10', 0),
(425, 34, 'livraison', 'Lor', 'Philippe', NULL, '116 rue de Javel', NULL, '75015', 'Paris', 'France', '0644982807', 0, '2026-05-18 02:35:49', 0),
(426, 34, 'livraison', 'Lor', 'Philippe', NULL, '116 rue de Javel', NULL, '75015', 'Paris', 'France', '0644982807', 1, '2026-05-27 20:56:37', 0);

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id_avis` int NOT NULL,
  `id_produit` int NOT NULL,
  `id_client` int NOT NULL,
  `id_commande` int DEFAULT NULL,
  `note` int DEFAULT NULL,
  `titre` varchar(255) DEFAULT NULL,
  `commentaire` text,
  `reponse` text COMMENT 'Réponse du vendeur',
  `statut` enum('en_attente','approuve','rejete') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déclencheurs `avis`
--
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

CREATE TABLE `categories` (
  `id_categorie` int NOT NULL,
  `nom` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text,
  `parent_id` int DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `meta_titre` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
-- Structure de la table `checkout_sessions`
--

CREATE TABLE `checkout_sessions` (
  `id` int NOT NULL,
  `panier_id` int NOT NULL,
  `client_id` int NOT NULL,
  `adresse_livraison_id` int DEFAULT NULL,
  `adresse_facturation_id` int DEFAULT NULL,
  `mode_livraison` varchar(50) DEFAULT 'standard',
  `emballage_cadeau` tinyint(1) DEFAULT '0',
  `instructions` text,
  `statut` enum('en_attente','paiement_en_cours','termine','abandonne') DEFAULT 'en_attente',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `clients`
--

CREATE TABLE `clients` (
  `id_client` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `mot_de_passe` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `genre` enum('homme','femme','autre') DEFAULT NULL,
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('actif','inactif','banni') DEFAULT 'actif',
  `is_temporary` tinyint(1) DEFAULT '0',
  `created_from_session` varchar(255) DEFAULT NULL,
  `newsletter` tinyint(1) DEFAULT '1',
  `dernier_connexion` datetime DEFAULT NULL,
  `token_reset` varchar(255) DEFAULT NULL,
  `token_expiration` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `clients`
--

INSERT INTO `clients` (`id_client`, `email`, `mot_de_passe`, `nom`, `prenom`, `telephone`, `date_naissance`, `genre`, `date_inscription`, `statut`, `is_temporary`, `created_from_session`, `newsletter`, `dernier_connexion`, `token_reset`, `token_expiration`) VALUES
(34, 'lhpp.philippe@gmail.com', NULL, 'Lor', 'Philippe', '0644982807', NULL, NULL, '2026-05-18 02:30:10', 'actif', 1, 'fkt289vkgr86d82bmi555pussa', 1, '2026-05-27 20:56:37', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id_commande` int NOT NULL,
  `numero_commande` varchar(50) NOT NULL,
  `id_client` int NOT NULL,
  `client_type` enum('guest','registered') DEFAULT 'registered',
  `id_adresse_livraison` int NOT NULL,
  `id_adresse_facturation` int DEFAULT NULL,
  `statut` enum('en_attente','confirmee','en_preparation','expediee','livree','annulee','remboursee') DEFAULT 'en_attente',
  `sous_total` decimal(10,2) NOT NULL,
  `frais_livraison` decimal(10,2) DEFAULT '0.00',
  `reduction` decimal(10,2) DEFAULT '0.00',
  `total_ttc` decimal(10,2) NOT NULL,
  `mode_paiement` enum('carte','paypal','virement','cheque') DEFAULT 'carte',
  `statut_paiement` enum('en_attente','paye','echec','rembourse') DEFAULT 'en_attente',
  `reference_paiement` varchar(255) DEFAULT NULL,
  `reference_paypal` varchar(255) DEFAULT NULL,
  `transporteur` varchar(100) DEFAULT NULL,
  `numero_suivi` varchar(100) DEFAULT NULL,
  `instructions` text,
  `date_commande` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_paiement` datetime DEFAULT NULL,
  `date_expedition` datetime DEFAULT NULL,
  `date_livraison_estimee` date DEFAULT NULL,
  `date_livraison_reelle` datetime DEFAULT NULL,
  `email_paypal` varchar(255) DEFAULT NULL,
  `payer_id` varchar(255) DEFAULT NULL,
  `capture_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commandes`
--

INSERT INTO `commandes` (`id_commande`, `numero_commande`, `id_client`, `client_type`, `id_adresse_livraison`, `id_adresse_facturation`, `statut`, `sous_total`, `frais_livraison`, `reduction`, `total_ttc`, `mode_paiement`, `statut_paiement`, `reference_paiement`, `reference_paypal`, `transporteur`, `numero_suivi`, `instructions`, `date_commande`, `date_paiement`, `date_expedition`, `date_livraison_estimee`, `date_livraison_reelle`, `email_paypal`, `payer_id`, `capture_id`) VALUES
(208, 'CMD-202605-000001', 34, 'registered', 425, 425, 'confirmee', '12.00', '4.90', '0.00', '16.90', 'paypal', 'paye', '1SK248891V160811V', '1SK248891V160811V', NULL, NULL, NULL, '2026-05-18 02:35:54', '2026-05-18 02:36:28', NULL, NULL, NULL, 'sb-lbcqf47423737@personal.example.com', '7HHSGDAL98AD2', '849127122Y4275735'),
(209, 'CMD-202605-000209', 34, 'registered', 426, 426, 'confirmee', '12.00', '4.90', '0.00', '16.90', 'paypal', 'paye', '2XR39454VK138122F', '2XR39454VK138122F', NULL, NULL, NULL, '2026-05-27 20:57:06', '2026-05-27 20:57:23', NULL, NULL, NULL, 'sb-lbcqf47423737@personal.example.com', '7HHSGDAL98AD2', '97N4813699187480D');

--
-- Déclencheurs `commandes`
--
DELIMITER $$
CREATE TRIGGER `before_commande_insert` BEFORE INSERT ON `commandes` FOR EACH ROW BEGIN
    DECLARE next_id INT;
    DECLARE current_id INT;
    
    -- Obtenir le dernier ID utilisé (pas le prochain)
    SELECT MAX(id_commande) INTO current_id FROM commandes;
    
    -- Si aucune commande n'existe, commencer à 1
    IF current_id IS NULL THEN
        SET current_id = 0;
    END IF;
    
    -- Le prochain ID est current_id + 1
    SET next_id = current_id + 1;
    
    SET NEW.numero_commande = CONCAT(
        'CMD-',
        DATE_FORMAT(NOW(), '%Y%m'),
        '-',
        LPAD(next_id, 6, '0')
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `commande_items`
--

CREATE TABLE `commande_items` (
  `id_item` int NOT NULL,
  `id_commande` int NOT NULL,
  `id_produit` int NOT NULL,
  `id_variant` int DEFAULT NULL,
  `reference_produit` varchar(50) NOT NULL,
  `nom_produit` varchar(255) NOT NULL,
  `quantite` int NOT NULL,
  `prix_unitaire_ht` decimal(10,2) NOT NULL,
  `prix_unitaire_ttc` decimal(10,2) NOT NULL,
  `tva` decimal(4,2) NOT NULL,
  `options` text COMMENT 'JSON des options au moment de la commande'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande_items`
--

INSERT INTO `commande_items` (`id_item`, `id_commande`, `id_produit`, `id_variant`, `reference_produit`, `nom_produit`, `quantite`, `prix_unitaire_ht`, `prix_unitaire_ttc`, `tva`, `options`) VALUES
(218, 194, 4, NULL, 'REF004', 'Set bijoux \"Lumière\"', 1, '1000.00', '1200.00', '20.00', NULL),
(219, 195, 14, NULL, 'PROD-000007', 'TEST', 1, '200.00', '240.00', '20.00', NULL),
(220, 196, 19, NULL, 'PROD-0000012', 'Terre', 1, '20.00', '24.00', '20.00', NULL),
(221, 197, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(222, 198, 28, NULL, 'PROD-000001', 'AZERTY B', 2, '10.00', '12.00', '20.00', NULL),
(223, 199, 28, NULL, 'PROD-000001', 'AZERTY B', 1, '10.00', '12.00', '20.00', NULL),
(224, 200, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(225, 201, 28, NULL, 'PROD-000001', 'AZERTY B', 1, '10.00', '12.00', '20.00', NULL),
(226, 202, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(227, 203, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(228, 204, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(229, 205, 28, NULL, 'PROD-000001', 'AZERTY B', 1, '10.00', '12.00', '20.00', NULL),
(230, 205, 29, NULL, 'PROD-000002', 'AZERTY', 2, '99.99', '119.99', '20.00', NULL),
(231, 206, 28, NULL, 'PROD-000001', 'AZERTY B', 1, '10.00', '12.00', '20.00', NULL),
(232, 206, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(233, 207, 28, NULL, 'PROD-000001', 'AZERTY B', 1, '10.00', '12.00', '20.00', NULL),
(234, 207, 29, NULL, 'PROD-000002', 'AZERTY', 1, '99.99', '119.99', '20.00', NULL),
(235, 208, 31, NULL, 'PROD-000002', '1 EURO', 1, '10.00', '12.00', '20.00', NULL),
(236, 209, 31, NULL, 'PROD-000002', '1 EURO', 1, '10.00', '12.00', '20.00', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `commande_temporaire`
--

CREATE TABLE `commande_temporaire` (
  `id` int NOT NULL,
  `panier_id` varchar(255) NOT NULL,
  `donnees_livraison` text,
  `donnees_facturation` text,
  `mode_livraison` varchar(50) DEFAULT 'standard',
  `emballage_cadeau` tinyint(1) DEFAULT '0',
  `instructions` text,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `commande_temporaire`
--

INSERT INTO `commande_temporaire` (`id`, `panier_id`, `donnees_livraison`, `donnees_facturation`, `mode_livraison`, `emballage_cadeau`, `instructions`, `date_creation`) VALUES
(322, '473', '{\"id\":\"424\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-05-18 02:30:10'),
(323, '474', '{\"id\":\"425\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-05-18 02:35:49'),
(324, '482', '{\"id\":\"426\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-05-27 20:56:37');

-- --------------------------------------------------------

--
-- Structure de la table `configuration`
--

CREATE TABLE `configuration` (
  `id_config` int NOT NULL,
  `cle` varchar(100) NOT NULL,
  `valeur` text,
  `type` enum('string','integer','boolean','json','array') DEFAULT 'string',
  `categorie` varchar(50) DEFAULT NULL,
  `description` text,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `configuration`
--

INSERT INTO `configuration` (`id_config`, `cle`, `valeur`, `type`, `categorie`, `description`, `date_modification`) VALUES
(1, 'site_nom', 'Heure Du Cadeau', 'string', 'general', 'Nom du site', '2025-12-21 05:22:40'),
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
-- Structure de la table `conversions_temp`
--

CREATE TABLE `conversions_temp` (
  `id_conversion` int NOT NULL,
  `id_client_temp` int NOT NULL,
  `id_client_permanent` int DEFAULT NULL,
  `date_conversion` datetime DEFAULT CURRENT_TIMESTAMP,
  `methode_conversion` enum('post_commande','formulaire','newsletter','admin') DEFAULT 'post_commande',
  `source_page` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `historique_prix`
--

CREATE TABLE `historique_prix` (
  `id_historique` int NOT NULL,
  `id_produit` int NOT NULL,
  `ancien_prix_ht` decimal(10,2) DEFAULT NULL,
  `nouveau_prix_ht` decimal(10,2) DEFAULT NULL,
  `ancien_prix_ttc` decimal(10,2) DEFAULT NULL,
  `nouveau_prix_ttc` decimal(10,2) DEFAULT NULL,
  `raison` varchar(255) DEFAULT NULL,
  `modifie_par` int DEFAULT NULL,
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `images_produits`
--

CREATE TABLE `images_produits` (
  `id_image` int NOT NULL,
  `id_produit` int NOT NULL,
  `url_image` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `ordre` int DEFAULT '0',
  `principale` tinyint(1) DEFAULT '0',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `images_produits`
--

INSERT INTO `images_produits` (`id_image`, `id_produit`, `url_image`, `alt_text`, `ordre`, `principale`, `date_ajout`) VALUES
(3, 12, '/uploads/produits/69ad0e40a8c53_20260308_055056.jpg', 'Crue', 0, 1, '2026-03-08 05:50:56'),
(7, 13, '/uploads/produits/69ad1d05d7a80_20260308_065357.png', 'DRAGON', 0, 1, '2026-03-08 06:53:57'),
(8, 4, '/uploads/produits/69ad21bd177bd_20260308_071405.jpg', 'Set bijoux \"Lumière\"', 0, 1, '2026-03-08 07:14:05'),
(9, 3, '/uploads/produits/69ad22733da00_20260308_071707.jpg', 'Montre \"Temps Précieux\"', 0, 1, '2026-03-08 07:17:07'),
(10, 14, '/uploads/produits/69ae3bc85a836_20260309_031728.jpg', 'TEST', 0, 1, '2026-03-09 03:17:28'),
(14, 28, '/uploads/produits/69c777d72953f_20260328_064023.jpg', 'Azerty', 0, 1, '2026-03-28 06:40:23'),
(15, 29, '/uploads/produits/69ead6f2016de_20260424_023530.jpg', 'AZERTY', 0, 1, '2026-04-24 02:35:30'),
(16, 31, '/uploads/produits/69fec8e82a0c3_20260509_054056.jpg', '1 EURO', 0, 1, '2026-05-09 05:40:56'),
(23, 42, '/uploads/produits/6a190930be4a3_20260529_033408.png', 'DRAGON', 0, 1, '2026-05-29 03:34:08');

-- --------------------------------------------------------

--
-- Structure de la table `logs`
--

CREATE TABLE `logs` (
  `id_log` int NOT NULL,
  `type_log` enum('erreur','info','securite','paiement') NOT NULL,
  `niveau` enum('debug','info','warning','error','critical') DEFAULT 'info',
  `message` text NOT NULL,
  `utilisateur_id` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `url` text,
  `metadata` text COMMENT 'JSON des données supplémentaires',
  `date_log` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `logs`
--

INSERT INTO `logs` (`id_log`, `type_log`, `niveau`, `message`, `utilisateur_id`, `ip_address`, `user_agent`, `url`, `metadata`, `date_log`) VALUES
(101, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:41:22'),
(102, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:59:30'),
(103, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 05:00:00'),
(104, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 05:03:02'),
(105, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-07 02:25:29'),
(106, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:05:29'),
(107, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:07:48'),
(108, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:09:22'),
(109, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:28:48'),
(110, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:46:24'),
(111, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:49:22'),
(112, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 02:53:23'),
(113, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:01:49'),
(114, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:12:52'),
(115, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:18:45'),
(116, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:31:16'),
(117, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:38:01'),
(118, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-08 03:39:28'),
(119, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-09 02:00:58'),
(120, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-09 03:40:03'),
(121, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-09 03:42:20'),
(122, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-09 03:47:49'),
(123, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-09 04:21:34'),
(124, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-10 02:02:27'),
(125, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-10 02:10:13'),
(126, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-10 02:20:34'),
(127, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-11 02:50:08'),
(128, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-11 02:54:05'),
(129, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-11 03:00:33'),
(130, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:18:01'),
(131, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:21:45'),
(132, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:26:12'),
(133, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:26:44'),
(134, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:28:51'),
(135, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:29:02'),
(136, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:31:21'),
(137, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:34:59'),
(138, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:36:14'),
(139, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:40:20'),
(140, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:43:33'),
(141, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 01:59:48'),
(142, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:00:09'),
(143, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:06:50'),
(144, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:14:12'),
(145, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:14:23'),
(146, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:15:05'),
(147, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:16:50'),
(148, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:17:31'),
(149, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:18:03'),
(150, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:21:21'),
(151, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:26:15'),
(152, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:27:18'),
(153, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:40:23'),
(154, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:42:24'),
(155, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:42:47'),
(156, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:43:02'),
(157, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:53:07'),
(158, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 02:53:24'),
(159, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:04:28'),
(160, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:33:00'),
(161, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:37:19'),
(162, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:45:38'),
(163, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:45:56'),
(164, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:57:03'),
(165, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 03:58:46'),
(166, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 04:11:23'),
(167, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 04:17:33'),
(168, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 04:20:09'),
(169, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 17, '176.145.254.59', NULL, NULL, NULL, '2026-02-12 04:37:21'),
(170, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 17, '176.145.254.59', NULL, NULL, NULL, '2026-02-13 03:05:48'),
(171, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 17, '176.145.254.59', NULL, NULL, NULL, '2026-02-13 03:17:47'),
(172, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 17, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 02:50:38'),
(173, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:12:33'),
(174, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:19:24'),
(175, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:26:16'),
(176, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:29:07'),
(177, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:41:45'),
(178, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 03:52:16'),
(179, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-14 04:05:13'),
(180, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-15 02:28:26'),
(181, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-15 02:42:03'),
(182, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-15 02:42:10'),
(183, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php?from=livraison', 18, '176.145.254.59', NULL, NULL, NULL, '2026-02-15 02:42:23'),
(184, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"Aucun panier actif trouv\\u00e9.\",\"trace\":\"#0 {main}\"}', '2026-02-15 03:36:44'),
(185, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"Aucun panier actif trouv\\u00e9.\",\"trace\":\"#0 {main}\"}', '2026-02-15 03:36:49'),
(186, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"Aucun panier actif trouv\\u00e9.\",\"trace\":\"#0 {main}\"}', '2026-02-15 03:37:38'),
(187, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"Aucun panier actif trouv\\u00e9.\",\"trace\":\"#0 {main}\"}', '2026-02-15 03:41:26'),
(188, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"Aucun panier actif trouv\\u00e9.\",\"trace\":\"#0 {main}\"}', '2026-02-15 03:41:55'),
(189, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:43:33'),
(190, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:45:05'),
(191, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:46:39'),
(192, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:48:44'),
(193, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:50:51'),
(194, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:51:11'),
(195, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:53:50'),
(196, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:58:54'),
(197, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-15 03:59:22'),
(198, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-16 03:27:31'),
(199, 'info', 'info', 'Formulaire livraison traité avec succès', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"\",\"mode_livraison\":\"standard\"}', '2026-02-16 03:32:37'),
(200, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 02:48:49'),
(201, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 02:49:03'),
(202, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 02:51:50'),
(203, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 02:52:00'),
(204, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 02:59:16'),
(205, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:02:23'),
(206, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:06:48'),
(207, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:12:13'),
(208, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:12:15'),
(209, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:12:15'),
(210, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:12:30'),
(211, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:12:34'),
(212, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:20:53'),
(213, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:20:55'),
(214, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:45'),
(215, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:46'),
(216, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:50'),
(217, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:51'),
(218, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:52'),
(219, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:53'),
(220, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:21:54'),
(221, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:31:07'),
(222, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:31:09'),
(223, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:31:44'),
(224, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:31:46'),
(225, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:36:31'),
(226, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:36:34'),
(227, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:36:39'),
(228, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:36:40'),
(229, 'erreur', 'error', 'Erreur création commande PayPal: SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value', NULL, '176.145.254.59', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_commande\' doesn\'t have a default value\"}', '2026-02-20 03:36:44'),
(230, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"129\",\"mode_livraison\":\"standard\"}', '2026-02-20 03:40:28'),
(231, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"130\",\"mode_livraison\":\"standard\"}', '2026-02-21 05:19:33'),
(232, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 18, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"131\",\"mode_livraison\":\"standard\"}', '2026-02-21 05:28:19'),
(233, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"133\",\"mode_livraison\":\"standard\"}', '2026-02-21 05:51:40'),
(234, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"134\",\"mode_livraison\":\"standard\"}', '2026-02-21 06:03:15'),
(235, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"135\",\"mode_livraison\":\"standard\"}', '2026-02-21 06:05:45'),
(236, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"136\",\"mode_livraison\":\"standard\"}', '2026-02-22 02:40:44'),
(237, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"137\",\"mode_livraison\":\"standard\"}', '2026-02-22 02:54:58'),
(238, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"137\",\"mode_livraison\":\"standard\"}', '2026-02-22 02:55:12'),
(239, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"138\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:03:24'),
(240, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"140\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:12:32'),
(241, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"141\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:13:56'),
(242, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 19, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"10\",\"montant\":39.8}', '2026-02-22 03:14:00'),
(243, 'paiement', 'info', 'Paiement CB réussi pour commande #10', 19, '176.145.254.59', NULL, NULL, NULL, '2026-02-22 03:15:05'),
(244, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 19, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"143\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:18:39'),
(245, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"144\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:28:59'),
(246, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"146\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:30:19'),
(247, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"147\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:34:40'),
(248, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"148\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:37:36'),
(249, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"149\",\"mode_livraison\":\"standard\"}', '2026-02-22 03:48:53'),
(250, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"150\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:00:44'),
(251, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"151\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:14:27'),
(252, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"152\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:21:20'),
(253, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"153\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:43:30'),
(254, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"153\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:43:34'),
(255, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"154\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:47:32'),
(256, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"155\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:50:36'),
(257, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"156\",\"mode_livraison\":\"standard\"}', '2026-02-22 04:55:34'),
(258, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"158\",\"mode_livraison\":\"standard\"}', '2026-02-22 05:04:11'),
(259, 'paiement', 'info', 'Paiement PayPal réussi pour commande #16', 20, '176.145.254.59', NULL, NULL, NULL, '2026-02-22 05:05:07'),
(260, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"160\",\"mode_livraison\":\"standard\"}', '2026-02-22 05:07:45'),
(261, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 20, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"17\",\"montant\":39.8}', '2026-02-22 05:07:49'),
(262, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"161\",\"mode_livraison\":\"standard\"}', '2026-02-22 05:23:39'),
(263, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"162\",\"mode_livraison\":\"standard\"}', '2026-02-22 05:27:11'),
(264, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"163\",\"mode_livraison\":\"standard\"}', '2026-02-22 05:29:31'),
(265, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"164\",\"mode_livraison\":\"standard\"}', '2026-02-22 06:25:06'),
(266, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"165\",\"mode_livraison\":\"standard\"}', '2026-02-22 06:35:35'),
(267, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"166\",\"mode_livraison\":\"standard\"}', '2026-02-22 06:40:20'),
(268, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"167\",\"mode_livraison\":\"standard\"}', '2026-02-22 06:43:26'),
(269, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"168\",\"mode_livraison\":\"standard\"}', '2026-02-22 06:54:32'),
(270, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"169\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:02:26'),
(271, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"171\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:07:56'),
(272, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 20, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"26\",\"montant\":1200}', '2026-02-22 07:08:00'),
(273, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"172\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:08:32'),
(274, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"172\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:10:35'),
(275, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"172\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:11:10'),
(276, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"174\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:21:31'),
(277, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"176\",\"mode_livraison\":\"standard\"}', '2026-02-22 07:45:46'),
(278, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"178\",\"mode_livraison\":\"standard\"}', '2026-02-22 08:08:31'),
(279, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"180\",\"mode_livraison\":\"standard\"}', '2026-02-22 08:10:54'),
(280, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"181\",\"mode_livraison\":\"standard\"}', '2026-02-22 08:12:42'),
(281, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"182\",\"mode_livraison\":\"standard\"}', '2026-02-22 08:28:05'),
(282, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"183\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:16:22'),
(283, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"184\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:35:19'),
(284, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"185\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:38:24'),
(285, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"186\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:45:27'),
(286, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"186\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:48:29'),
(287, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"187\",\"mode_livraison\":\"standard\"}', '2026-02-23 04:59:27'),
(288, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"188\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:01:26'),
(289, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"194\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:09:04'),
(290, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"196\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:12:37'),
(291, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"198\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:24:45'),
(292, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 20, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"44\",\"montant\":69.8}', '2026-02-23 05:24:48'),
(293, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"199\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:29:06'),
(294, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 20, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"45\",\"montant\":1200}', '2026-02-23 05:29:10'),
(295, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"200\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:29:44'),
(296, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"201\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:36:30'),
(297, 'info', 'info', 'Commande créée avec succès (en attente de paiement)', 20, '176.145.254.59', NULL, NULL, '{\"commande_id\":\"46\",\"montant\":89.9}', '2026-02-23 05:36:33'),
(298, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"203\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:43:34'),
(299, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"204\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:50:20'),
(300, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.150.81.12', NULL, NULL, '{\"panier_id\":\"205\",\"mode_livraison\":\"standard\"}', '2026-02-23 05:56:24'),
(301, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '89.85.230.154', NULL, NULL, '{\"panier_id\":\"206\",\"mode_livraison\":\"standard\"}', '2026-02-23 12:27:08'),
(302, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"207\",\"mode_livraison\":\"standard\"}', '2026-02-25 02:36:30'),
(303, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"209\",\"mode_livraison\":\"standard\"}', '2026-02-25 02:41:41'),
(304, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"210\",\"mode_livraison\":\"standard\"}', '2026-02-25 02:43:16'),
(305, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"212\",\"mode_livraison\":\"standard\"}', '2026-02-25 02:47:39'),
(306, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"213\",\"mode_livraison\":\"standard\"}', '2026-02-25 03:03:31'),
(307, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"214\",\"mode_livraison\":\"standard\"}', '2026-02-25 03:10:30'),
(308, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"215\",\"mode_livraison\":\"standard\"}', '2026-02-25 03:19:12'),
(309, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"216\",\"mode_livraison\":\"standard\"}', '2026-02-25 03:27:40'),
(310, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"217\",\"mode_livraison\":\"standard\"}', '2026-02-25 04:39:36'),
(311, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"218\",\"mode_livraison\":\"standard\"}', '2026-02-25 04:49:11'),
(312, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"219\",\"mode_livraison\":\"standard\"}', '2026-02-25 04:55:53'),
(313, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"220\",\"mode_livraison\":\"standard\"}', '2026-02-25 05:11:05'),
(314, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"221\",\"mode_livraison\":\"standard\"}', '2026-02-25 05:17:53'),
(315, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"222\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:09:17'),
(316, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"223\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:23:28'),
(317, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"224\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:48:17'),
(318, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"225\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:49:04'),
(319, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"226\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:50:34'),
(320, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"227\",\"mode_livraison\":\"standard\"}', '2026-02-26 03:56:00'),
(321, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 21, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"228\",\"mode_livraison\":\"standard\"}', '2026-02-26 04:04:50'),
(322, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"229\",\"mode_livraison\":\"standard\"}', '2026-02-26 04:16:58'),
(323, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"230\",\"mode_livraison\":\"standard\"}', '2026-02-26 04:25:55'),
(324, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"231\",\"mode_livraison\":\"standard\"}', '2026-02-26 04:46:37'),
(325, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"232\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:03:10'),
(326, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"233\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:12:37'),
(327, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"234\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:24:08'),
(328, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"234\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:32:23'),
(329, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"235\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:35:13'),
(330, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"236\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:44:23'),
(331, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"237\",\"mode_livraison\":\"standard\"}', '2026-02-26 05:53:01'),
(332, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"238\",\"mode_livraison\":\"standard\"}', '2026-02-27 02:39:38'),
(333, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"239\",\"mode_livraison\":\"standard\"}', '2026-02-27 02:51:34'),
(334, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 22, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"239\",\"mode_livraison\":\"standard\"}', '2026-02-27 02:52:00'),
(335, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"240\",\"mode_livraison\":\"standard\"}', '2026-02-27 02:56:10'),
(336, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"241\",\"mode_livraison\":\"standard\"}', '2026-02-27 02:59:29'),
(337, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"242\",\"mode_livraison\":\"standard\"}', '2026-02-27 03:35:33'),
(338, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"243\",\"mode_livraison\":\"standard\"}', '2026-02-27 03:37:14'),
(339, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"244\",\"mode_livraison\":\"standard\"}', '2026-02-27 04:01:59'),
(340, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"246\",\"mode_livraison\":\"standard\"}', '2026-02-27 04:11:18'),
(341, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"247\",\"mode_livraison\":\"standard\"}', '2026-02-27 04:42:22'),
(342, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"248\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:15:07'),
(343, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"249\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:22:07'),
(344, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"250\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:31:14'),
(345, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"250\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:33:55'),
(346, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"251\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:44:50'),
(347, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"252\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:49:35'),
(348, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"253\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:57:58');
INSERT INTO `logs` (`id_log`, `type_log`, `niveau`, `message`, `utilisateur_id`, `ip_address`, `user_agent`, `url`, `metadata`, `date_log`) VALUES
(349, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"254\",\"mode_livraison\":\"standard\"}', '2026-02-27 06:05:14'),
(350, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"255\",\"mode_livraison\":\"standard\"}', '2026-02-27 06:34:32'),
(351, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"256\",\"mode_livraison\":\"standard\"}', '2026-02-27 06:51:11'),
(352, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"257\",\"mode_livraison\":\"standard\"}', '2026-02-27 06:55:58'),
(353, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"258\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:04:25'),
(354, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"259\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:10:43'),
(355, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"260\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:18:04'),
(356, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"261\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:22:55'),
(357, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"262\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:23:55'),
(358, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"264\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:38:19'),
(359, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"265\",\"mode_livraison\":\"standard\"}', '2026-02-27 07:42:54'),
(360, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"266\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:06:14'),
(361, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"267\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:20:13'),
(362, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"268\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:23:04'),
(363, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"270\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:25:20'),
(364, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"272\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:35:22'),
(365, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"274\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:42:31'),
(366, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"276\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:48:35'),
(367, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"277\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:57:00'),
(368, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"278\",\"mode_livraison\":\"standard\"}', '2026-02-28 03:58:10'),
(369, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"279\",\"mode_livraison\":\"standard\"}', '2026-02-28 04:03:16'),
(370, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"280\",\"mode_livraison\":\"standard\"}', '2026-02-28 04:04:21'),
(371, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"281\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:17:15'),
(372, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"282\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:18:09'),
(373, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"283\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:19:18'),
(374, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"284\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:23:18'),
(375, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"286\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:29:02'),
(376, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"287\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:34:04'),
(377, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"288\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:38:49'),
(378, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"289\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:39:40'),
(379, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"290\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:51:12'),
(380, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"292\",\"mode_livraison\":\"standard\"}', '2026-02-28 06:52:15'),
(381, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.140.220.40', NULL, NULL, '{\"panier_id\":\"293\",\"mode_livraison\":\"standard\"}', '2026-02-28 10:25:16'),
(382, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"294\",\"mode_livraison\":\"standard\"}', '2026-02-28 13:18:37'),
(383, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"295\",\"mode_livraison\":\"standard\"}', '2026-02-28 13:29:39'),
(384, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"296\",\"mode_livraison\":\"standard\"}', '2026-02-28 13:30:52'),
(385, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"297\",\"mode_livraison\":\"standard\"}', '2026-02-28 13:41:24'),
(386, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"298\",\"mode_livraison\":\"standard\"}', '2026-02-28 13:56:52'),
(387, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"299\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:01:30'),
(388, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"300\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:14:45'),
(389, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"300\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:17:07'),
(390, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"301\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:25:44'),
(391, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"302\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:26:44'),
(392, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"304\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:30:14'),
(393, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"306\",\"mode_livraison\":\"standard\"}', '2026-02-28 14:31:20'),
(394, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.140.218.240', NULL, NULL, '{\"panier_id\":\"307\",\"mode_livraison\":\"standard\"}', '2026-02-28 15:17:13'),
(395, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.140.218.240', NULL, NULL, '{\"panier_id\":\"308\",\"mode_livraison\":\"standard\"}', '2026-02-28 15:18:02'),
(396, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"310\",\"mode_livraison\":\"standard\"}', '2026-02-28 15:49:54'),
(397, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"312\",\"mode_livraison\":\"standard\"}', '2026-02-28 15:53:27'),
(398, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"314\",\"mode_livraison\":\"standard\"}', '2026-02-28 16:21:54'),
(399, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"316\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:16:04'),
(400, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"317\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:27:58'),
(401, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"319\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:44:16'),
(402, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"321\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:46:03'),
(403, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"323\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:55:52'),
(404, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"325\",\"mode_livraison\":\"standard\"}', '2026-03-01 03:56:51'),
(405, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"326\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:02:22'),
(406, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"327\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:12:09'),
(407, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"329\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:17:07'),
(408, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"331\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:24:12'),
(409, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"333\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:33:12'),
(410, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"335\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:40:29'),
(411, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"337\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:47:38'),
(412, 'info', 'info', 'Email de confirmation envoyé via SMTP', 24, NULL, NULL, NULL, '{\"commande_id\":161,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000161\"}', '2026-03-01 04:48:01'),
(413, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"339\",\"mode_livraison\":\"standard\"}', '2026-03-01 04:56:24'),
(414, 'info', 'info', 'Email de confirmation envoyé via SMTP', 24, NULL, NULL, NULL, '{\"commande_id\":162,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000162\"}', '2026-03-01 04:56:55'),
(415, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"341\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:02:05'),
(416, 'info', 'info', 'Email de confirmation envoyé via SMTP', 24, NULL, NULL, NULL, '{\"commande_id\":163,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000163\"}', '2026-03-01 05:02:32'),
(417, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"343\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:06:27'),
(418, 'info', 'info', 'Email de confirmation envoyé via SMTP', 24, NULL, NULL, NULL, '{\"commande_id\":164,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000164\"}', '2026-03-01 05:06:51'),
(419, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"345\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:15:19'),
(420, 'info', 'info', 'Email de confirmation envoyé via SMTP', 24, NULL, NULL, NULL, '{\"commande_id\":165,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000165\"}', '2026-03-01 05:15:44'),
(421, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"347\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:22:43'),
(422, 'info', 'info', 'Email avec facture PDF envoyé', 24, NULL, NULL, NULL, '{\"commande_id\":166,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000166\",\"pdf_genere\":false}', '2026-03-01 05:23:11'),
(423, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"349\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:27:39'),
(424, 'info', 'info', 'Email avec facture PDF envoyé', 24, NULL, NULL, NULL, '{\"commande_id\":167,\"email\":\"lhpp.philippe@gmail.com\",\"numero_commande\":\"CMD-202603-000001\",\"pdf_genere\":false}', '2026-03-01 05:28:07'),
(425, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"351\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:34:42'),
(426, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"353\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:46:56'),
(427, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"354\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:47:42'),
(428, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"356\",\"mode_livraison\":\"standard\"}', '2026-03-01 05:59:59'),
(429, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"358\",\"mode_livraison\":\"standard\"}', '2026-03-01 06:47:48'),
(430, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"360\",\"mode_livraison\":\"standard\"}', '2026-03-02 02:54:17'),
(431, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"362\",\"mode_livraison\":\"standard\"}', '2026-03-02 03:28:50'),
(432, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"364\",\"mode_livraison\":\"standard\"}', '2026-03-02 03:30:40'),
(433, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"366\",\"mode_livraison\":\"standard\"}', '2026-03-02 03:31:37'),
(434, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"368\",\"mode_livraison\":\"standard\"}', '2026-03-02 03:49:28'),
(435, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"370\",\"mode_livraison\":\"standard\"}', '2026-03-02 04:02:09'),
(436, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 24, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"372\",\"mode_livraison\":\"standard\"}', '2026-03-02 04:03:55'),
(437, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 25, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"373\",\"mode_livraison\":\"standard\"}', '2026-03-02 04:21:59'),
(438, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 26, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"375\",\"mode_livraison\":\"standard\"}', '2026-03-02 05:25:05'),
(439, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 26, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"378\",\"mode_livraison\":\"standard\"}', '2026-03-02 06:06:26'),
(440, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 26, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"379\",\"mode_livraison\":\"standard\"}', '2026-03-02 06:09:48'),
(441, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 26, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"381\",\"mode_livraison\":\"standard\"}', '2026-03-03 02:14:50'),
(442, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 26, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"381\",\"mode_livraison\":\"standard\"}', '2026-03-03 02:15:13'),
(443, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 27, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"383\",\"mode_livraison\":\"standard\"}', '2026-03-03 05:53:36'),
(444, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 27, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"386\",\"mode_livraison\":\"standard\"}', '2026-03-04 05:08:05'),
(445, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"388\",\"mode_livraison\":\"standard\"}', '2026-03-04 05:36:49'),
(446, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"416\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:12:28'),
(447, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"416\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:12:35'),
(448, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"416\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:15:00'),
(449, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"417\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:19:18'),
(450, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"419\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:23:05'),
(451, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"419\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:29:44'),
(452, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 28, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"420\",\"mode_livraison\":\"standard\"}', '2026-03-08 08:32:56'),
(453, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 29, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"422\",\"mode_livraison\":\"standard\"}', '2026-03-08 13:10:25'),
(454, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 29, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"424\",\"mode_livraison\":\"standard\"}', '2026-03-08 14:12:19'),
(455, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 30, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"434\",\"mode_livraison\":\"standard\"}', '2026-03-09 04:44:43'),
(456, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 31, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"438\",\"mode_livraison\":\"standard\"}', '2026-03-09 05:53:40'),
(457, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 32, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"441\",\"mode_livraison\":\"standard\"}', '2026-03-10 04:07:44'),
(458, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 32, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"443\",\"mode_livraison\":\"standard\"}', '2026-03-10 04:12:17'),
(459, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"446\",\"mode_livraison\":\"standard\"}', '2026-03-11 02:55:52'),
(460, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"450\",\"mode_livraison\":\"standard\"}', '2026-04-24 02:38:31'),
(461, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"451\",\"mode_livraison\":\"standard\"}', '2026-04-27 02:08:23'),
(462, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"454\",\"mode_livraison\":\"standard\"}', '2026-04-28 03:41:44'),
(463, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"456\",\"mode_livraison\":\"standard\"}', '2026-04-28 04:04:48'),
(464, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"458\",\"mode_livraison\":\"standard\"}', '2026-04-28 04:17:08'),
(465, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"460\",\"mode_livraison\":\"standard\"}', '2026-04-28 04:23:05'),
(466, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"462\",\"mode_livraison\":\"standard\"}', '2026-04-30 03:05:25'),
(467, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"464\",\"mode_livraison\":\"standard\"}', '2026-04-30 03:25:52'),
(468, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"467\",\"mode_livraison\":\"standard\"}', '2026-05-03 03:33:01'),
(469, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"469\",\"mode_livraison\":\"standard\"}', '2026-05-03 04:29:05'),
(470, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 33, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"471\",\"mode_livraison\":\"standard\"}', '2026-05-03 07:31:26'),
(471, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 34, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"473\",\"mode_livraison\":\"standard\"}', '2026-05-18 02:30:10'),
(472, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 34, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"474\",\"mode_livraison\":\"standard\"}', '2026-05-18 02:35:49'),
(473, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":4,\"code\":\"ETE2026ETE\",\"admin_id\":3}', '2026-05-26 17:28:15'),
(474, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":3,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-26 17:28:19'),
(475, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":1,\"code\":\"BIENVENUE10\",\"admin_id\":3}', '2026-05-26 17:28:23'),
(476, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":2,\"code\":\"LIVRAISONOFFERTE\",\"admin_id\":3}', '2026-05-26 17:28:26'),
(477, 'info', 'info', 'Promotion ajoutée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":\"5\",\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-26 17:29:29'),
(478, 'info', 'info', 'Promotion ajoutée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":\"6\",\"code\":\"ETE2026ETE\",\"admin_id\":3}', '2026-05-26 17:46:28'),
(479, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":6,\"code\":\"ETE2026ETE\",\"admin_id\":3}', '2026-05-26 17:49:36'),
(480, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":5,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-26 17:56:36'),
(481, 'info', 'info', 'Promotion ajoutée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":\"7\",\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-26 17:57:21'),
(482, 'info', 'info', 'Promotion modifiée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":7,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-26 17:57:51'),
(483, 'securite', 'info', 'Promotion supprimée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":7,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-27 17:58:29'),
(484, 'info', 'info', 'Promotion ajoutée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":\"8\",\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-27 17:59:25'),
(485, 'info', 'info', 'Promotion modifiée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":8,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-27 17:59:56'),
(486, 'info', 'info', 'Promotion modifiée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":8,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-27 18:00:51'),
(487, 'info', 'info', 'Promotion modifiée', 3, '176.145.254.59', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36', NULL, '{\"promotion_id\":8,\"code\":\"ETE2026\",\"admin_id\":3}', '2026-05-27 18:27:03'),
(488, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 34, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"482\",\"mode_livraison\":\"standard\"}', '2026-05-27 20:56:37');

-- --------------------------------------------------------

--
-- Structure de la table `pages`
--

CREATE TABLE `pages` (
  `id_page` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `contenu` text NOT NULL,
  `meta_titre` varchar(255) DEFAULT NULL,
  `meta_description` text,
  `statut` enum('publie','brouillon','prive') DEFAULT 'publie',
  `ordre` int DEFAULT '0',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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

CREATE TABLE `panier` (
  `id_panier` int NOT NULL,
  `id_client` int DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `adresse_livraison` text,
  `email_client` varchar(255) DEFAULT NULL,
  `telephone_client` varchar(20) DEFAULT NULL,
  `statut` enum('actif','fusionne','valide','abandonne') DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier`
--

INSERT INTO `panier` (`id_panier`, `id_client`, `session_id`, `adresse_livraison`, `email_client`, `telephone_client`, `statut`, `date_creation`, `date_modification`, `metadata`) VALUES
(473, 34, 'fkt289vkgr86d82bmi555pussa', NULL, 'lhpp.philippe@gmail.com', '0644982807', 'actif', '2026-05-18 02:29:57', '2026-05-18 02:30:10', NULL),
(474, 34, 'vk3e760olnrtpcpk2d8qieu0e4', NULL, 'lhpp.philippe@gmail.com', '0644982807', 'valide', '2026-05-18 02:35:39', '2026-05-18 02:35:54', NULL),
(475, NULL, 'vk3e760olnrtpcpk2d8qieu0e4', NULL, NULL, NULL, 'actif', '2026-05-18 02:36:26', NULL, NULL),
(476, NULL, '', NULL, NULL, NULL, 'actif', '2026-05-24 05:46:43', NULL, NULL),
(477, NULL, 'nk3emmfkrfqp77n6b8sf6ip4k4', NULL, NULL, NULL, 'actif', '2026-05-27 19:26:11', '2026-05-27 19:26:11', NULL),
(478, NULL, '3rdbp7k69lukbf0vlpvppl0ceu', NULL, NULL, NULL, 'actif', '2026-05-27 19:37:58', '2026-05-27 19:37:58', NULL),
(479, NULL, 'rkk7f2lk7n53ci2v3mlbmbep02', NULL, NULL, NULL, 'actif', '2026-05-27 20:17:34', '2026-05-27 20:17:34', NULL),
(482, 34, '85e0dj50llj8jeevqg7a97poqp', NULL, 'lhpp.philippe@gmail.com', '0644982807', 'valide', '2026-05-27 20:56:27', '2026-05-27 20:57:06', NULL),
(483, NULL, '85e0dj50llj8jeevqg7a97poqp', NULL, NULL, NULL, 'actif', '2026-05-27 20:57:21', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `panier_items`
--

CREATE TABLE `panier_items` (
  `id_item` int NOT NULL,
  `id_panier` int NOT NULL,
  `id_produit` int NOT NULL,
  `id_variant` int DEFAULT NULL,
  `quantite` int NOT NULL DEFAULT '1',
  `prix_unitaire` decimal(10,2) NOT NULL,
  `options` json DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier_items`
--

INSERT INTO `panier_items` (`id_item`, `id_panier`, `id_produit`, `id_variant`, `quantite`, `prix_unitaire`, `options`, `date_ajout`, `date_modification`) VALUES
(88, 473, 31, NULL, 1, '12.00', NULL, '2026-05-18 02:29:57', '2026-05-18 02:29:57'),
(89, 474, 31, NULL, 1, '12.00', NULL, '2026-05-18 02:35:39', '2026-05-18 02:35:39'),
(90, 475, 31, NULL, 1, '12.00', NULL, '2026-05-18 02:36:26', NULL),
(91, 477, 35, NULL, 1, '120.00', NULL, '2026-05-27 19:26:11', '2026-05-27 19:26:11'),
(92, 478, 35, NULL, 1, '120.00', NULL, '2026-05-27 19:37:58', '2026-05-27 19:37:58'),
(93, 479, 35, NULL, 1, '120.00', NULL, '2026-05-27 20:17:34', '2026-05-27 20:17:34'),
(96, 482, 31, NULL, 1, '12.00', NULL, '2026-05-27 20:56:27', '2026-05-27 20:56:27'),
(97, 483, 31, NULL, 1, '12.00', NULL, '2026-05-27 20:57:21', NULL);

--
-- Déclencheurs `panier_items`
--
DELIMITER $$
CREATE TRIGGER `cleanup_empty_carts` AFTER DELETE ON `panier_items` FOR EACH ROW BEGIN
    DELETE FROM panier 
    WHERE id_panier = OLD.id_panier 
    AND NOT EXISTS (
        SELECT 1 FROM panier_items 
        WHERE id_panier = OLD.id_panier
    );
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `panier_logs`
--

CREATE TABLE `panier_logs` (
  `id_log` int NOT NULL,
  `id_panier` int DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `action` enum('ajout','modification','suppression','vider','checkout') NOT NULL,
  `id_produit` int DEFAULT NULL,
  `ancienne_quantite` int DEFAULT NULL,
  `nouvelle_quantite` int DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `panier_logs`
--

INSERT INTO `panier_logs` (`id_log`, `id_panier`, `session_id`, `action`, `id_produit`, `ancienne_quantite`, `nouvelle_quantite`, `ip_address`, `user_agent`, `date_action`) VALUES
(93, 473, 'fkt289vkgr86d82bmi555pussa', 'ajout', 31, NULL, 1, '176.145.254.59', NULL, '2026-05-18 02:29:57'),
(94, 474, 'vk3e760olnrtpcpk2d8qieu0e4', 'ajout', 31, NULL, 1, '176.145.254.59', NULL, '2026-05-18 02:35:39'),
(95, 477, 'nk3emmfkrfqp77n6b8sf6ip4k4', 'ajout', 35, NULL, 1, '176.145.254.59', NULL, '2026-05-27 19:26:11'),
(96, 478, '3rdbp7k69lukbf0vlpvppl0ceu', 'ajout', 35, NULL, 1, '176.145.254.59', NULL, '2026-05-27 19:37:58'),
(97, 479, 'rkk7f2lk7n53ci2v3mlbmbep02', 'ajout', 35, NULL, 1, '176.145.254.59', NULL, '2026-05-27 20:17:34'),
(98, 480, 'hhdbe33h3udqejd0hgci9i6ock', 'ajout', 35, NULL, 1, '176.145.254.59', NULL, '2026-05-27 20:36:23'),
(99, 480, 'hhdbe33h3udqejd0hgci9i6ock', 'suppression', 35, 1, NULL, '176.145.254.59', NULL, '2026-05-27 20:36:33'),
(100, 481, 'rfdp5jj6sm8c70usm1l46lndok', 'ajout', 35, NULL, 1, '176.145.254.59', NULL, '2026-05-27 20:52:04'),
(101, 481, 'rfdp5jj6sm8c70usm1l46lndok', 'suppression', 35, 1, NULL, '176.145.254.59', NULL, '2026-05-27 20:52:08'),
(102, 482, '85e0dj50llj8jeevqg7a97poqp', 'ajout', 31, NULL, 1, '176.145.254.59', NULL, '2026-05-27 20:56:27');

-- --------------------------------------------------------

--
-- Structure de la table `panier_sessions`
--

CREATE TABLE `panier_sessions` (
  `id_session` varchar(32) NOT NULL,
  `id_client` int DEFAULT NULL,
  `id_panier` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_activity` datetime DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `user_agent` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('active','expired','merged','converted') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `panier_temporaire`
--

CREATE TABLE `panier_temporaire` (
  `id` int NOT NULL,
  `token_panier` varchar(64) NOT NULL,
  `produit_id` int NOT NULL,
  `quantite` int DEFAULT '1',
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `produits`
--

CREATE TABLE `produits` (
  `id_produit` int NOT NULL,
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
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `produits`
--

INSERT INTO `produits` (`id_produit`, `reference`, `nom`, `slug`, `description`, `description_courte`, `prix_ht`, `tva`, `quantite_stock`, `seuil_alerte`, `id_categorie`, `marque`, `poids`, `dimensions`, `materiau`, `couleur`, `made_in`, `personnalisable`, `ecologique`, `made_in_france`, `artisanal`, `exclusif`, `note_moyenne`, `nombre_avis`, `vues`, `ventes`, `statut`, `date_creation`, `date_modification`) VALUES
(31, 'PROD-000002', '1 EURO', '1-euro-1', 'AZERTY', '1 EURO', '10.00', '20.00', 8, 2, 1, '', NULL, '', '', '', '', 0, 0, 0, 0, 0, '0.00', 0, 0, 2, 'actif', '2026-05-09 05:40:56', '2026-05-27 20:57:23'),
(42, 'PROD-000003', 'DRAGON', 'produit', '', '', '100.00', '20.00', 10, 3, 1, '', NULL, '', '', '', '', 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2026-05-29 03:33:31', '2026-05-29 03:34:08');

-- --------------------------------------------------------

--
-- Structure de la table `produits_populaires`
--

CREATE TABLE `produits_populaires` (
  `id_populaire` int NOT NULL,
  `id_produit` int NOT NULL,
  `score_popularite` decimal(10,2) DEFAULT '0.00',
  `vues_7jours` int DEFAULT '0',
  `ventes_7jours` int DEFAULT '0',
  `ajouts_panier_7jours` int DEFAULT '0',
  `date_maj` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `promotions`
--

CREATE TABLE `promotions` (
  `id_promotion` int NOT NULL,
  `code_promotion` varchar(50) NOT NULL,
  `type_promotion` enum('pourcentage','montant_fixe','livraison_gratuite') NOT NULL DEFAULT 'pourcentage',
  `valeur` decimal(10,2) NOT NULL,
  `montant_minimum` decimal(10,2) NOT NULL DEFAULT '0.00',
  `utilisations_max` int DEFAULT NULL,
  `utilisations_actuelles` int NOT NULL DEFAULT '0',
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `description` text,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `promotions`
--

INSERT INTO `promotions` (`id_promotion`, `code_promotion`, `type_promotion`, `valeur`, `montant_minimum`, `utilisations_max`, `utilisations_actuelles`, `date_debut`, `date_fin`, `actif`, `description`, `date_creation`) VALUES
(1, 'BIENVENUE10', 'pourcentage', '10.00', '0.00', NULL, 0, '2026-05-27 18:42:41', '2026-06-26 18:42:41', 1, '10% de réduction sur votre première commande', '2026-05-27 18:42:41'),
(2, 'LIVRAISONOFFERTE', 'livraison_gratuite', '0.00', '50.00', 100, 0, '2026-05-27 18:42:41', '2026-06-11 18:42:41', 1, 'Livraison gratuite dès 50€ d\'achat', '2026-05-27 18:42:41'),
(8, 'ETE2026', 'pourcentage', '10.00', '0.00', NULL, 0, '2026-05-27 19:58:00', '2026-06-13 19:58:00', 1, 'test', '2026-05-27 17:59:25');

-- --------------------------------------------------------

--
-- Structure de la table `promotions_categories`
--

CREATE TABLE `promotions_categories` (
  `id` int NOT NULL,
  `id_promotion` int NOT NULL,
  `id_categorie` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `promotions_produits`
--

CREATE TABLE `promotions_produits` (
  `id` int NOT NULL,
  `id_promotion` int NOT NULL,
  `id_produit` int NOT NULL,
  `reduction_personnalisee` decimal(5,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `promotions_produits`
--

INSERT INTO `promotions_produits` (`id`, `id_promotion`, `id_produit`, `reduction_personnalisee`) VALUES
(3, 1, 31, NULL),
(5, 8, 31, NULL),
(17, 1, 42, NULL),
(18, 8, 42, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `recherches`
--

CREATE TABLE `recherches` (
  `id_recherche` int NOT NULL,
  `id_client` int DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `terme_recherche` varchar(255) NOT NULL,
  `categorie_id` int DEFAULT NULL,
  `prix_min` decimal(10,2) DEFAULT NULL,
  `prix_max` decimal(10,2) DEFAULT NULL,
  `filtres` text COMMENT 'JSON des filtres appliqués',
  `nombre_resultats` int DEFAULT NULL,
  `date_recherche` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `sessions_expirees`
--

CREATE TABLE `sessions_expirees` (
  `id_session` varchar(32) NOT NULL,
  `donnees_session` text,
  `date_expiration` datetime NOT NULL,
  `date_sauvegarde` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `statistiques`
--

CREATE TABLE `statistiques` (
  `id_statistique` int NOT NULL,
  `date_stat` date NOT NULL,
  `type_stat` enum('visite','produit_vu','recherche','panier_ajout','achat') NOT NULL,
  `id_produit` int DEFAULT NULL,
  `id_categorie` int DEFAULT NULL,
  `valeur` int DEFAULT '1',
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `transactions`
--

CREATE TABLE `transactions` (
  `id_transaction` int NOT NULL,
  `numero_transaction` varchar(50) NOT NULL,
  `id_commande` int NOT NULL,
  `id_client` int DEFAULT NULL,
  `montant` decimal(10,2) NOT NULL,
  `methode_paiement` enum('carte','paypal','virement','cheque') NOT NULL,
  `reference_paiement` varchar(255) DEFAULT NULL,
  `statut` enum('en_attente','paye','echec','rembourse','annule') DEFAULT 'en_attente',
  `details` json DEFAULT NULL,
  `ip_client` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `session_id` varchar(255) DEFAULT NULL,
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `transactions`
--

INSERT INTO `transactions` (`id_transaction`, `numero_transaction`, `id_commande`, `id_client`, `montant`, `methode_paiement`, `reference_paiement`, `statut`, `details`, `ip_client`, `user_agent`, `session_id`, `date_creation`, `date_modification`) VALUES
(92, 'PP_20260518_6a0a7b2c62bf1', 208, 34, '16.90', 'paypal', '1SK248891V160811V', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"849127122Y4275735\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"1SK248891V160811V\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1SK248891V160811V\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-05-18T02:35:55Z\", \"update_time\": \"2026-05-18T02:36:27Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"16.90\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"849127122Y4275735\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/849127122Y4275735\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/849127122Y4275735/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1SK248891V160811V\", \"method\": \"GET\"}], \"amount\": {\"value\": \"16.90\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"208\", \"invoice_id\": \"INV-20260518-208-6a0a7b0b08baa\", \"create_time\": \"2026-05-18T02:36:27Z\", \"update_time\": \"2026-05-18T02:36:27Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"208\", \"invoice_id\": \"INV-20260518-208-6a0a7b0b08baa\", \"description\": \"Commande #208 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_208\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"1SK248891V160811V\"}', '176.145.254.59', NULL, NULL, '2026-05-18 02:36:28', NULL),
(93, 'PP_20260527_6a175ab37bbf2', 209, 34, '16.90', 'paypal', '2XR39454VK138122F', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"97N4813699187480D\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"2XR39454VK138122F\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/2XR39454VK138122F\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-05-27T20:57:07Z\", \"update_time\": \"2026-05-27T20:57:23Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"16.90\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"97N4813699187480D\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/97N4813699187480D\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/97N4813699187480D/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/2XR39454VK138122F\", \"method\": \"GET\"}], \"amount\": {\"value\": \"16.90\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"209\", \"invoice_id\": \"INV-20260527-209-6a175aa366a6f\", \"create_time\": \"2026-05-27T20:57:22Z\", \"update_time\": \"2026-05-27T20:57:22Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"209\", \"invoice_id\": \"INV-20260527-209-6a175aa366a6f\", \"description\": \"Commande #209 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_209\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"2XR39454VK138122F\"}', '176.145.254.59', NULL, NULL, '2026-05-27 20:57:23', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `variants`
--

CREATE TABLE `variants` (
  `id_variant` int NOT NULL,
  `id_produit` int NOT NULL,
  `nom_variant` varchar(100) NOT NULL COMMENT 'ex: Taille, Couleur',
  `valeur` varchar(100) NOT NULL COMMENT 'ex: L, Rouge',
  `prix_supplement` decimal(10,2) DEFAULT '0.00',
  `quantite_stock` int DEFAULT '0',
  `reference_variant` varchar(50) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_commandes_temporaires`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_commandes_temporaires` (
`numero_commande` varchar(50)
,`date_commande` datetime
,`total_ttc` decimal(10,2)
,`email` varchar(255)
,`nom` varchar(100)
,`prenom` varchar(100)
,`is_temporary` tinyint(1)
,`nombre_items` bigint
,`adresse_facturation_differente` int
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_conversions`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_conversions` (
`date_conversion` date
,`methode_conversion` enum('post_commande','formulaire','newsletter','admin')
,`conversions` bigint
,`clients_convertis` bigint
,`jours_moyen_conversion` decimal(10,1)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_paniers_actifs`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_paniers_actifs` (
`id_panier` int
,`id_client` int
,`session_id` varchar(255)
,`email_client` varchar(255)
,`telephone_client` varchar(20)
,`date_creation` datetime
,`date_modification` datetime
,`statut` enum('actif','fusionne','valide','abandonne')
,`nombre_items` bigint
,`total_articles` decimal(32,0)
,`valeur_totale` decimal(42,2)
,`dernier_ajout` datetime
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_populaires_panier`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_produits_populaires_panier` (
`id_produit` int
,`nom` varchar(255)
,`reference` varchar(50)
,`paniers_actifs` bigint
,`quantite_total_paniers` decimal(32,0)
,`paniers_actifs_count` bigint
,`moyenne_quantite_par_panier` decimal(14,4)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_stock_alerte`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_produits_stock_alerte` (
`id_produit` int
,`reference` varchar(50)
,`nom` varchar(255)
,`quantite_stock` int
,`seuil_alerte` int
,`categorie` varchar(100)
,`statut_stock` varchar(7)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_statistiques_produits`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_statistiques_produits` (
`id_produit` int
,`nom` varchar(255)
,`reference` varchar(50)
,`categorie` varchar(100)
,`prix_ttc` decimal(10,2)
,`quantite_stock` int
,`ventes` int
,`note_moyenne` decimal(3,2)
,`nombre_avis` int
,`dans_wishlist` bigint
,`quantite_vendue_total` decimal(32,0)
,`chiffre_affaires` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Structure de la table `wishlist`
--

CREATE TABLE `wishlist` (
  `id_wishlist` int NOT NULL,
  `id_client` int NOT NULL,
  `id_produit` int NOT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_commandes_temporaires`
--
DROP TABLE IF EXISTS `vue_commandes_temporaires`;

CREATE ALGORITHM=UNDEFINED DEFINER=`phpmyadmin`@`localhost` SQL SECURITY DEFINER VIEW `vue_commandes_temporaires`  AS SELECT `c`.`numero_commande` AS `numero_commande`, `c`.`date_commande` AS `date_commande`, `c`.`total_ttc` AS `total_ttc`, `cl`.`email` AS `email`, `cl`.`nom` AS `nom`, `cl`.`prenom` AS `prenom`, `cl`.`is_temporary` AS `is_temporary`, count(`ci`.`id_item`) AS `nombre_items`, (case when ((`c`.`id_adresse_facturation` is null) or (`c`.`id_adresse_facturation` = `c`.`id_adresse_livraison`)) then 0 else 1 end) AS `adresse_facturation_differente` FROM ((`commandes` `c` join `clients` `cl` on((`c`.`id_client` = `cl`.`id_client`))) left join `commande_items` `ci` on((`c`.`id_commande` = `ci`.`id_commande`))) WHERE (`cl`.`is_temporary` = 1) GROUP BY `c`.`id_commande` ORDER BY `c`.`date_commande` DESC ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_conversions`
--
DROP TABLE IF EXISTS `vue_conversions`;

CREATE ALGORITHM=UNDEFINED DEFINER=`phpmyadmin`@`localhost` SQL SECURITY DEFINER VIEW `vue_conversions`  AS SELECT cast(`c`.`date_conversion` as date) AS `date_conversion`, `c`.`methode_conversion` AS `methode_conversion`, count(0) AS `conversions`, count(distinct `cl`.`id_client`) AS `clients_convertis`, round(avg((to_days(`c`.`date_conversion`) - to_days(`cl`.`date_inscription`))),1) AS `jours_moyen_conversion` FROM (`conversions_temp` `c` join `clients` `cl` on((`c`.`id_client_temp` = `cl`.`id_client`))) WHERE (`cl`.`is_temporary` = 0) GROUP BY cast(`c`.`date_conversion` as date), `c`.`methode_conversion` ORDER BY `date_conversion` DESC ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_paniers_actifs`
--
DROP TABLE IF EXISTS `vue_paniers_actifs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`phpmyadmin`@`localhost` SQL SECURITY DEFINER VIEW `vue_paniers_actifs`  AS SELECT `p`.`id_panier` AS `id_panier`, `p`.`id_client` AS `id_client`, `p`.`session_id` AS `session_id`, `p`.`email_client` AS `email_client`, `p`.`telephone_client` AS `telephone_client`, `p`.`date_creation` AS `date_creation`, `p`.`date_modification` AS `date_modification`, `p`.`statut` AS `statut`, count(`pi`.`id_item`) AS `nombre_items`, sum(`pi`.`quantite`) AS `total_articles`, sum((`pi`.`quantite` * `pi`.`prix_unitaire`)) AS `valeur_totale`, max(`pi`.`date_ajout`) AS `dernier_ajout` FROM (`panier` `p` left join `panier_items` `pi` on((`p`.`id_panier` = `pi`.`id_panier`))) WHERE (`p`.`statut` = 'actif') GROUP BY `p`.`id_panier` ORDER BY `p`.`date_modification` DESC ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_produits_populaires_panier`
--
DROP TABLE IF EXISTS `vue_produits_populaires_panier`;

CREATE ALGORITHM=UNDEFINED DEFINER=`phpmyadmin`@`localhost` SQL SECURITY DEFINER VIEW `vue_produits_populaires_panier`  AS SELECT `p`.`id_produit` AS `id_produit`, `p`.`nom` AS `nom`, `p`.`reference` AS `reference`, count(distinct `pi`.`id_panier`) AS `paniers_actifs`, sum(`pi`.`quantite`) AS `quantite_total_paniers`, count(distinct (case when (`pan`.`statut` = 'actif') then `pi`.`id_panier` end)) AS `paniers_actifs_count`, avg(`pi`.`quantite`) AS `moyenne_quantite_par_panier` FROM ((`produits` `p` left join `panier_items` `pi` on((`p`.`id_produit` = `pi`.`id_produit`))) left join `panier` `pan` on((`pi`.`id_panier` = `pan`.`id_panier`))) GROUP BY `p`.`id_produit` ORDER BY `paniers_actifs` DESC, `quantite_total_paniers` DESC ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_produits_stock_alerte`
--
DROP TABLE IF EXISTS `vue_produits_stock_alerte`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_produits_stock_alerte`  AS SELECT `p`.`id_produit` AS `id_produit`, `p`.`reference` AS `reference`, `p`.`nom` AS `nom`, `p`.`quantite_stock` AS `quantite_stock`, `p`.`seuil_alerte` AS `seuil_alerte`, `c`.`nom` AS `categorie`, (case when (`p`.`quantite_stock` = 0) then 'rupture' when (`p`.`quantite_stock` <= `p`.`seuil_alerte`) then 'alerte' else 'normal' end) AS `statut_stock` FROM (`produits` `p` join `categories` `c` on((`p`.`id_categorie` = `c`.`id_categorie`))) WHERE ((`p`.`statut` = 'actif') AND ((`p`.`quantite_stock` = 0) OR (`p`.`quantite_stock` <= `p`.`seuil_alerte`))) ;

-- --------------------------------------------------------

--
-- Structure de la vue `vue_statistiques_produits`
--
DROP TABLE IF EXISTS `vue_statistiques_produits`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vue_statistiques_produits`  AS SELECT `p`.`id_produit` AS `id_produit`, `p`.`nom` AS `nom`, `p`.`reference` AS `reference`, `c`.`nom` AS `categorie`, `p`.`prix_ttc` AS `prix_ttc`, `p`.`quantite_stock` AS `quantite_stock`, `p`.`ventes` AS `ventes`, `p`.`note_moyenne` AS `note_moyenne`, `p`.`nombre_avis` AS `nombre_avis`, count(distinct `w`.`id_wishlist`) AS `dans_wishlist`, sum(`ci`.`quantite`) AS `quantite_vendue_total`, sum((`ci`.`quantite` * `ci`.`prix_unitaire_ttc`)) AS `chiffre_affaires` FROM (((`produits` `p` left join `categories` `c` on((`p`.`id_categorie` = `c`.`id_categorie`))) left join `wishlist` `w` on((`p`.`id_produit` = `w`.`id_produit`))) left join `commande_items` `ci` on((`p`.`id_produit` = `ci`.`id_produit`))) GROUP BY `p`.`id_produit` ;

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `administrateurs`
--
ALTER TABLE `administrateurs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_role` (`role`);

--
-- Index pour la table `adresses`
--
ALTER TABLE `adresses`
  ADD PRIMARY KEY (`id_adresse`),
  ADD KEY `idx_client` (`id_client`),
  ADD KEY `idx_type` (`type_adresse`),
  ADD KEY `idx_principale` (`principale`);

--
-- Index pour la table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id_avis`),
  ADD UNIQUE KEY `unique_avis_commande` (`id_produit`,`id_client`,`id_commande`),
  ADD KEY `id_commande` (`id_commande`),
  ADD KEY `idx_produit` (`id_produit`),
  ADD KEY `idx_client` (`id_client`),
  ADD KEY `idx_note` (`note`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_categorie`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_active` (`active`);
ALTER TABLE `categories` ADD FULLTEXT KEY `idx_recherche_cat` (`nom`,`description`);

--
-- Index pour la table `checkout_sessions`
--
ALTER TABLE `checkout_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_panier_checkout` (`panier_id`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_creation` (`date_creation`);

--
-- Index pour la table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id_client`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_nom` (`nom`,`prenom`),
  ADD KEY `idx_temporary` (`is_temporary`),
  ADD KEY `idx_clients_date_inscription` (`date_inscription`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id_commande`),
  ADD UNIQUE KEY `numero_commande` (`numero_commande`),
  ADD KEY `idx_client_type` (`client_type`),
  ADD KEY `id_client` (`id_client`),
  ADD KEY `id_adresse_livraison` (`id_adresse_livraison`),
  ADD KEY `id_adresse_facturation` (`id_adresse_facturation`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_statut_paiement` (`statut_paiement`),
  ADD KEY `idx_date_commande` (`date_commande`),
  ADD KEY `idx_commandes_client_date` (`id_client`,`date_commande`);

--
-- Index pour la table `commande_items`
--
ALTER TABLE `commande_items`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_commande` (`id_commande`),
  ADD KEY `id_produit` (`id_produit`),
  ADD KEY `id_variant` (`id_variant`);

--
-- Index pour la table `commande_temporaire`
--
ALTER TABLE `commande_temporaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_panier_id` (`panier_id`);

--
-- Index pour la table `conversions_temp`
--
ALTER TABLE `conversions_temp`
  ADD PRIMARY KEY (`id_conversion`),
  ADD KEY `id_client_temp` (`id_client_temp`),
  ADD KEY `id_client_permanent` (`id_client_permanent`);

--
-- Index pour la table `historique_prix`
--
ALTER TABLE `historique_prix`
  ADD PRIMARY KEY (`id_historique`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Index pour la table `images_produits`
--
ALTER TABLE `images_produits`
  ADD PRIMARY KEY (`id_image`);

--
-- Index pour la table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id_log`);

--
-- Index pour la table `panier`
--
ALTER TABLE `panier`
  ADD PRIMARY KEY (`id_panier`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_panier_session` (`session_id`);

--
-- Index pour la table `panier_items`
--
ALTER TABLE `panier_items`
  ADD PRIMARY KEY (`id_item`),
  ADD KEY `id_panier` (`id_panier`),
  ADD KEY `id_produit` (`id_produit`),
  ADD KEY `id_variant` (`id_variant`),
  ADD KEY `idx_panier_produit` (`id_panier`,`id_produit`),
  ADD KEY `idx_date_modification` (`date_modification`);

--
-- Index pour la table `panier_logs`
--
ALTER TABLE `panier_logs`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `idx_panier_action` (`id_panier`,`action`),
  ADD KEY `idx_date_action` (`date_action`);

--
-- Index pour la table `panier_sessions`
--
ALTER TABLE `panier_sessions`
  ADD PRIMARY KEY (`id_session`),
  ADD KEY `idx_id_client` (`id_client`),
  ADD KEY `idx_id_panier` (`id_panier`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Index pour la table `panier_temporaire`
--
ALTER TABLE `panier_temporaire`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token_panier` (`token_panier`);

--
-- Index pour la table `produits`
--
ALTER TABLE `produits`
  ADD PRIMARY KEY (`id_produit`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `id_categorie` (`id_categorie`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_stock` (`quantite_stock`),
  ADD KEY `idx_note` (`note_moyenne`),
  ADD KEY `idx_produits_prix` (`prix_ttc`);
ALTER TABLE `produits` ADD FULLTEXT KEY `idx_recherche_nom` (`nom`);
ALTER TABLE `produits` ADD FULLTEXT KEY `idx_recherche_desc` (`description`);

--
-- Index pour la table `produits_populaires`
--
ALTER TABLE `produits_populaires`
  ADD PRIMARY KEY (`id_populaire`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Index pour la table `promotions`
--
ALTER TABLE `promotions`
  ADD PRIMARY KEY (`id_promotion`),
  ADD UNIQUE KEY `code_promotion` (`code_promotion`);

--
-- Index pour la table `promotions_categories`
--
ALTER TABLE `promotions_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_promotion` (`id_promotion`),
  ADD KEY `id_categorie` (`id_categorie`);

--
-- Index pour la table `promotions_produits`
--
ALTER TABLE `promotions_produits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_promotion` (`id_promotion`),
  ADD KEY `id_produit` (`id_produit`);

--
-- Index pour la table `sessions_expirees`
--
ALTER TABLE `sessions_expirees`
  ADD PRIMARY KEY (`id_session`),
  ADD KEY `idx_date_expiration` (`date_expiration`);

--
-- Index pour la table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id_transaction`);

--
-- Index pour la table `variants`
--
ALTER TABLE `variants`
  ADD PRIMARY KEY (`id_variant`),
  ADD KEY `id_produit` (`id_produit`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `adresses`
--
ALTER TABLE `adresses`
  MODIFY `id_adresse` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=427;

--
-- AUTO_INCREMENT pour la table `avis`
--
ALTER TABLE `avis`
  MODIFY `id_avis` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id_categorie` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `checkout_sessions`
--
ALTER TABLE `checkout_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id_client` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id_commande` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=210;

--
-- AUTO_INCREMENT pour la table `commande_items`
--
ALTER TABLE `commande_items`
  MODIFY `id_item` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=237;

--
-- AUTO_INCREMENT pour la table `commande_temporaire`
--
ALTER TABLE `commande_temporaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=325;

--
-- AUTO_INCREMENT pour la table `conversions_temp`
--
ALTER TABLE `conversions_temp`
  MODIFY `id_conversion` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `historique_prix`
--
ALTER TABLE `historique_prix`
  MODIFY `id_historique` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `images_produits`
--
ALTER TABLE `images_produits`
  MODIFY `id_image` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=489;

--
-- AUTO_INCREMENT pour la table `panier`
--
ALTER TABLE `panier`
  MODIFY `id_panier` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=484;

--
-- AUTO_INCREMENT pour la table `panier_items`
--
ALTER TABLE `panier_items`
  MODIFY `id_item` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT pour la table `panier_logs`
--
ALTER TABLE `panier_logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT pour la table `panier_temporaire`
--
ALTER TABLE `panier_temporaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT pour la table `produits_populaires`
--
ALTER TABLE `produits_populaires`
  MODIFY `id_populaire` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `promotions`
--
ALTER TABLE `promotions`
  MODIFY `id_promotion` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT pour la table `promotions_categories`
--
ALTER TABLE `promotions_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `promotions_produits`
--
ALTER TABLE `promotions_produits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id_transaction` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=94;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `adresses`
--
ALTER TABLE `adresses`
  ADD CONSTRAINT `fk_adresses_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE;

--
-- Contraintes pour la table `checkout_sessions`
--
ALTER TABLE `checkout_sessions`
  ADD CONSTRAINT `fk_checkout_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_checkout_panier` FOREIGN KEY (`panier_id`) REFERENCES `panier` (`id_panier`) ON DELETE CASCADE;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `fk_commandes_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE;

--
-- Contraintes pour la table `conversions_temp`
--
ALTER TABLE `conversions_temp`
  ADD CONSTRAINT `conversions_temp_ibfk_1` FOREIGN KEY (`id_client_temp`) REFERENCES `clients` (`id_client`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversions_temp_ibfk_2` FOREIGN KEY (`id_client_permanent`) REFERENCES `clients` (`id_client`) ON DELETE SET NULL;

--
-- Contraintes pour la table `historique_prix`
--
ALTER TABLE `historique_prix`
  ADD CONSTRAINT `historique_prix_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

--
-- Contraintes pour la table `panier_items`
--
ALTER TABLE `panier_items`
  ADD CONSTRAINT `panier_items_ibfk_1` FOREIGN KEY (`id_panier`) REFERENCES `panier` (`id_panier`) ON DELETE CASCADE;

--
-- Contraintes pour la table `panier_sessions`
--
ALTER TABLE `panier_sessions`
  ADD CONSTRAINT `fk_panier_sessions_client` FOREIGN KEY (`id_client`) REFERENCES `clients` (`id_client`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_panier_sessions_panier` FOREIGN KEY (`id_panier`) REFERENCES `panier` (`id_panier`) ON DELETE CASCADE;

--
-- Contraintes pour la table `produits_populaires`
--
ALTER TABLE `produits_populaires`
  ADD CONSTRAINT `produits_populaires_ibfk_1` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

--
-- Contraintes pour la table `promotions_categories`
--
ALTER TABLE `promotions_categories`
  ADD CONSTRAINT `fk_promo_cat_categorie` FOREIGN KEY (`id_categorie`) REFERENCES `categories` (`id_categorie`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_promo_cat_promo` FOREIGN KEY (`id_promotion`) REFERENCES `promotions` (`id_promotion`) ON DELETE CASCADE;

--
-- Contraintes pour la table `promotions_produits`
--
ALTER TABLE `promotions_produits`
  ADD CONSTRAINT `fk_promo_produit_produit` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_promo_produit_promo` FOREIGN KEY (`id_promotion`) REFERENCES `promotions` (`id_promotion`) ON DELETE CASCADE;

DELIMITER $$
--
-- Évènements
--
CREATE DEFINER=`phpmyadmin`@`localhost` EVENT `cleanup_checkout_sessions` ON SCHEDULE EVERY 1 HOUR STARTS '2025-12-27 01:26:11' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
  DELETE FROM checkout_sessions 
  WHERE statut = 'abandonne' 
  OR (statut = 'en_attente' AND date_creation < DATE_SUB(NOW(), INTERVAL 24 HOUR));
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
