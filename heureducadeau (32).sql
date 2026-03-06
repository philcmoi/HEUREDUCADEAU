-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : mer. 04 mars 2026 à 04:25
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
(3, 'admin', '007', 'lhpp.philippe@gmail.com', 'superadmin', 'active', '2026-03-04 04:22:45', '2026-03-04 03:57:34', 0, 0, NULL, '2025-12-07 06:28:27', '2026-03-04 04:22:45');

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
(396, 27, 'livraison', 'Lor', 'Philippe', NULL, '116 rue de Javel', NULL, '75015', 'Paris', 'France', '0644982807', 1, '2026-03-03 05:53:36', 0);

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
(27, 'lhpp.philippe@gmail.com', NULL, 'Lor', 'Philippe', '0644982807', NULL, NULL, '2026-03-03 05:53:36', 'actif', 1, '196t6427vju3n72sl57v7nb5vv', 1, NULL, NULL, NULL);

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
(185, 'CMD-202603-000001', 27, 'registered', 396, 396, 'confirmee', '134.70', '0.00', '0.00', '134.70', 'paypal', 'paye', '16965061BP5556438', '16965061BP5556438', NULL, NULL, NULL, '2026-03-03 05:53:39', '2026-03-03 05:54:11', NULL, NULL, NULL, 'sb-lbcqf47423737@personal.example.com', '7HHSGDAL98AD2', '2VL76989JC7758511');

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
(207, 185, 1, NULL, 'REF001', 'Bougie parfumée \"Élégance\"', 1, '29.08', '34.90', '20.00', NULL),
(208, 185, 2, NULL, 'REF002', 'Coffret gourmand \"Délice\"', 2, '41.58', '49.90', '20.00', NULL);

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
(293, '375', '{\"id\":\"391\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-03-02 05:25:05'),
(294, '378', '{\"id\":\"392\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-03-02 06:06:26'),
(295, '379', '{\"id\":\"393\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-03-02 06:09:48'),
(296, '381', '{\"id\":\"395\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-03-03 02:15:13'),
(297, '383', '{\"id\":\"396\",\"nom\":\"Lor\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":\"0644982807\",\"societe\":null,\"adresse\":\"116 rue de Javel\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\"}', NULL, 'standard', 0, NULL, '2026-03-03 05:53:36');

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
(10, 'recherche_suggestions', 'anniversaire,mariage,naissance,noel', 'array', 'recherche', 'Suggestions de recherche', NULL),
(0, 'panier_expiration_days', '30', 'integer', 'panier', 'Nombre de jours avant expiration d\'un panier inactif', NULL),
(0, 'panier_sync_enabled', '1', 'boolean', 'panier', 'Activer la synchronisation BDD du panier', NULL),
(0, 'panier_max_items', '50', 'integer', 'panier', 'Nombre maximum d\'articles dans le panier', NULL),
(0, 'panier_cleanup_interval', '24', 'integer', 'panier', 'Intervalle en heures pour le nettoyage des paniers', NULL);

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
(1, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'mot_de_passe\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(251): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:01:08'),
(2, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'mot_de_passe\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(251): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:01:08'),
(3, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'mot_de_passe\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(251): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:02:27'),
(4, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'mot_de_passe\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(251): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:02:27'),
(5, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_adresse\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(291): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:06:45'),
(6, 'erreur', 'error', 'Erreur lors du traitement du formulaire livraison', NULL, '15.188.86.32', NULL, NULL, '{\"error\":\"SQLSTATE[HY000]: General error: 1364 Field \'id_adresse\' doesn\'t have a default value\",\"trace\":\"#0 \\/var\\/www\\/sean\\/livraison.php(291): PDOStatement->execute()\\n#1 {main}\"}', '2025-12-25 03:06:45'),
(7, 'info', 'info', 'Formulaire livraison traité avec succès', 6, '15.188.86.32', NULL, NULL, NULL, '2025-12-25 03:08:41'),
(8, 'info', 'info', 'Formulaire livraison traité avec succès', 6, '15.188.86.32', NULL, NULL, NULL, '2025-12-25 03:08:41'),
(9, 'info', 'info', 'Formulaire livraison traité avec succès', 7, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:47:40'),
(10, 'info', 'info', 'Formulaire livraison traité avec succès', 7, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:47:40'),
(11, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:49:00'),
(12, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:49:00'),
(13, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:52:53'),
(14, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:52:54'),
(15, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:57:22'),
(16, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 04:57:22'),
(17, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:04:16'),
(18, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:04:16'),
(19, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:08:41'),
(20, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:08:41'),
(21, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:17:20'),
(22, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:17:20'),
(23, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:21:07'),
(24, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:21:07'),
(25, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:27:21'),
(26, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:27:21'),
(27, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:31:50'),
(28, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:31:50'),
(29, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:33:11'),
(30, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:33:12'),
(31, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:36:04'),
(32, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:36:04'),
(33, 'info', 'info', 'Formulaire livraison traité avec succès', 10, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:38:17'),
(34, 'info', 'info', 'Formulaire livraison traité avec succès', 10, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:38:17'),
(35, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:46:18'),
(36, 'info', 'info', 'Formulaire livraison traité avec succès', 9, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 05:46:18'),
(37, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:12:24'),
(38, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:12:24'),
(39, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:25:25'),
(40, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:25:25'),
(41, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:30:20'),
(42, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:30:20'),
(43, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:44:28'),
(44, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:44:28'),
(45, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:44:36'),
(46, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:44:36'),
(47, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:57:51'),
(48, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:57:51'),
(49, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:58:37'),
(50, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 06:58:37'),
(51, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:03:50'),
(52, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:03:50'),
(53, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:10:27'),
(54, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:10:28'),
(55, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:19'),
(56, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:19'),
(57, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:38'),
(58, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:38'),
(59, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:47'),
(60, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:16:47'),
(61, 'info', 'info', 'Formulaire livraison traité avec succès', 11, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:19:53'),
(62, 'info', 'info', 'Formulaire livraison traité avec succès', 11, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:19:53'),
(63, 'info', 'info', 'Formulaire livraison traité avec succès', 11, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:20:05'),
(64, 'info', 'info', 'Formulaire livraison traité avec succès', 11, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:20:05'),
(65, 'info', 'info', 'Formulaire livraison traité avec succès', 12, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:35:30'),
(66, 'info', 'info', 'Formulaire livraison traité avec succès', 12, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:35:31'),
(67, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:39:30'),
(68, 'info', 'info', 'Formulaire livraison traité avec succès', 8, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:39:30'),
(69, 'info', 'info', 'Formulaire livraison traité avec succès', 12, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:43:20'),
(70, 'info', 'info', 'Formulaire livraison traité avec succès', 12, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:43:20'),
(71, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:53:48'),
(72, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:53:48'),
(73, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:54:45'),
(74, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:54:45'),
(75, 'info', 'info', 'Formulaire livraison traité avec succès', 14, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:55:14'),
(76, 'info', 'info', 'Formulaire livraison traité avec succès', 14, '15.237.111.200', NULL, NULL, NULL, '2025-12-25 07:55:14'),
(77, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '13.38.65.236', NULL, NULL, NULL, '2025-12-26 01:48:00'),
(78, 'info', 'info', 'Formulaire livraison traité avec succès', 13, '13.38.65.236', NULL, NULL, NULL, '2025-12-26 01:48:00'),
(79, 'info', 'info', 'Formulaire livraison traité avec succès', 15, '13.38.65.236', NULL, NULL, NULL, '2025-12-26 02:15:21'),
(80, 'info', 'info', 'Formulaire livraison traité avec succès', 15, '13.38.65.236', NULL, NULL, NULL, '2025-12-26 02:15:22'),
(81, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:50:01'),
(82, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:50:01'),
(83, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:51:57'),
(84, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:51:57'),
(85, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:52:34'),
(86, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:52:34'),
(87, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:57:09'),
(88, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:57:09'),
(89, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:58:36'),
(90, 'info', 'info', 'Formulaire livraison traité avec succès', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 03:58:36'),
(91, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:00'),
(92, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:01'),
(93, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:02'),
(94, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:08'),
(95, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:09'),
(96, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:10'),
(97, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:11'),
(98, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:20:44'),
(99, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:38:26'),
(100, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 16, '176.145.254.59', NULL, NULL, NULL, '2026-02-06 04:40:05'),
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
(281, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 20, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"182\",\"mode_livraison\":\"standard\"}', '2026-02-22 08:28:05');
INSERT INTO `logs` (`id_log`, `type_log`, `niveau`, `message`, `utilisateur_id`, `ip_address`, `user_agent`, `url`, `metadata`, `date_log`) VALUES
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
(348, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 23, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"253\",\"mode_livraison\":\"standard\"}', '2026-02-27 05:57:58'),
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
(443, 'info', 'info', 'Formulaire livraison traité avec succès - Redirection vers paiement.php', 27, '176.145.254.59', NULL, NULL, '{\"panier_id\":\"383\",\"mode_livraison\":\"standard\"}', '2026-03-03 05:53:36');

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
(383, 27, '196t6427vju3n72sl57v7nb5vv', NULL, 'lhpp.philippe@gmail.com', '0644982807', 'valide', '2026-03-03 05:53:32', '2026-03-03 05:53:39', NULL),
(384, NULL, '196t6427vju3n72sl57v7nb5vv', NULL, NULL, NULL, 'actif', '2026-03-03 05:54:10', NULL, NULL),
(385, NULL, 'm74ukffqge44gurf30p8709ets', NULL, NULL, NULL, 'actif', '2026-03-03 17:23:08', NULL, NULL);

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
(1, 'REF001', 'Bougie parfumée \"Élégance\"', 'bougie-parfumee-elegance', 'Bougie artisanale parfum vanille et santal. 50h de combustion.', 'Bougie artisanale parfum vanille et santal', '29.08', '20.00', 39, 10, 1, 'Artisanat Français', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 61, 'actif', '2025-12-07 16:56:32', '2026-03-03 05:54:11'),
(2, 'REF002', 'Coffret gourmand \"Délice\"', 'coffret-gourmand-delice', 'Sélection des meilleures spécialités françaises. Emballage cadeau inclus.', 'Sélection de spécialités françaises', '41.58', '20.00', 15, 10, 1, 'Saveurs de France', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 35, 'actif', '2025-12-07 16:56:32', '2026-03-03 05:54:11'),
(3, 'REF003', 'Montre \"Temps Précieux\"', 'montre-temps-precieux', 'Montre élégante avec gravure personnalisée au dos du boitier.', 'Montre avec gravure personnalisée', '74.92', '20.00', 11, 5, 1, 'Luxe & Style', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 14, 'actif', '2025-12-07 16:56:32', '2026-03-03 02:16:42'),
(4, 'REF004', 'Set bijoux \"Lumière\"', 'set-bijoux-lumiere', 'Collier, boucles d\'oreilles et bracelet assortis. Argent 925.', 'Set bijoux en argent 925', '1000.00', '20.00', 25, 5, 1, 'Bijoux d\'Exception', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 5, 'actif', '2025-12-07 16:56:32', '2026-02-28 04:03:47'),
(6, 'PROD-1772597891', 'TEST Philippe', 'test-philippe', NULL, NULL, '10.00', '20.00', 99, 10, 1, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2026-03-04 04:18:11', NULL),
(7, 'PROD-000006', 'AZERTY', 'azerty', '', '', '2.00', '20.00', 199, 10, 1, '', NULL, '', '', '', '', 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2026-03-04 04:22:45', NULL);

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
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
(4, 'PP_20260222_699a780009298', 12, 20, '39.80', 'paypal', 'PAY-1771730943955-34pxbek7', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 03:29:04', NULL),
(5, 'PP_20260222_699a8e8389e89', 16, 20, '39.80', 'paypal', '1GS08956UR3051241', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"8LW22734JF563013R\", \"payment_id\": \"1GS08956UR3051241\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"1GS08956UR3051241\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1GS08956UR3051241\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-22T05:04:14Z\", \"update_time\": \"2026-02-22T05:05:07Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"8LW22734JF563013R\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8LW22734JF563013R\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8LW22734JF563013R/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1GS08956UR3051241\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"16\", \"invoice_id\": \"INV-20260222-16\", \"create_time\": \"2026-02-22T05:05:06Z\", \"update_time\": \"2026-02-22T05:05:06Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"shipping\": {\"name\": {\"full_name\": \"Philippe LOR\"}, \"address\": {\"postal_code\": \"75015\", \"admin_area_2\": \"Paris\", \"country_code\": \"FR\", \"address_line_1\": \"azerty\"}}, \"custom_id\": \"16\", \"invoice_id\": \"INV-20260222-16\", \"description\": \"Commande #16 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_16\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"1GS08956UR3051241\"}', '176.145.254.59', NULL, NULL, '2026-02-22 05:05:07', NULL),
(6, 'PP_20260222_699aaa33a0aca', 25, 20, '84.80', 'paypal', '9JM563763P283711U', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 07:03:15', NULL),
(7, 'PP_20260222_699aabb684f09', 26, 20, '1200.00', 'paypal', '7XP85875AE009443R', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 07:09:42', NULL),
(8, 'PP_20260222_699aac2249e7e', 27, 20, '1299.80', 'paypal', '5XL29512XK5855606', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 07:11:30', NULL),
(9, 'PP_20260222_699aae9aeb58e', 28, 20, '84.80', 'paypal', '2HJ049124J516205N', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 07:22:02', NULL),
(10, 'PP_20260222_699ab45b3e21c', 29, 20, '89.90', 'paypal', '9TF00132BY194133Y', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 07:46:35', NULL),
(11, 'PP_20260222_699ab9b079097', 30, 20, '84.80', 'paypal', '0EW55988BA311964W', 'paye', NULL, '176.145.254.59', NULL, NULL, '2026-02-22 08:09:20', NULL),
(12, 'PP_20260223_699be11100ba0', 42, 20, '39.80', 'paypal', '9NC54361S05663611', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"39934965G8323020F\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"9NC54361S05663611\"}', '176.145.254.59', NULL, NULL, '2026-02-23 05:09:37', NULL),
(13, 'PP_20260225_699e606775633', 48, 20, '84.80', 'paypal', '4FP21985UP4254914', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"33U958660N747483C\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"4FP21985UP4254914\"}', '176.145.254.59', NULL, NULL, '2026-02-25 02:37:27', NULL),
(14, 'PP_20260225_699e621b68aad', 50, 20, '54.80', 'paypal', '7WV94958FU783735R', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"1FS12282LF9627900\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"7WV94958FU783735R\"}', '176.145.254.59', NULL, NULL, '2026-02-25 02:44:43', NULL),
(15, 'CARD_20260226_699fbce816824', 68, 21, '39.80', 'carte', 'TEST_CARD_20260226032424_699fbce8165b5', 'paye', '{\"card_type\": \"Visa\", \"test_mode\": true, \"card_last4\": \"0820\"}', '176.145.254.59', NULL, NULL, '2026-02-26 03:24:24', NULL),
(16, 'CB_20260227_69a107fdc6f92', 85, 23, '84.80', 'carte', '6EF6821114356604W', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"6EF6821114356604W\", \"capture_id\": \"4D190775R83419647\", \"full_response\": {\"id\": \"6EF6821114356604W\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6EF6821114356604W\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T02:57:01Z\", \"update_time\": \"2026-02-27T02:57:01Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2030-03\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"9207\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"4D190775R83419647\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/4D190775R83419647\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/4D190775R83419647/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6EF6821114356604W\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"85\", \"invoice_id\": \"INV-20260227-85\", \"create_time\": \"2026-02-27T02:57:01Z\", \"update_time\": \"2026-02-27T02:57:01Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"295848109595141\", \"network\": \"VISA\"}}]}, \"custom_id\": \"85\", \"invoice_id\": \"INV-20260227-85\", \"description\": \"Commande #85 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_85\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-27 02:57:01', NULL),
(17, 'CB_20260227_69a108beb14f1', 86, 23, '89.90', 'carte', '35063419WE539304V', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"35063419WE539304V\", \"capture_id\": \"0W185671CT355581D\", \"full_response\": {\"id\": \"35063419WE539304V\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/35063419WE539304V\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T03:00:14Z\", \"update_time\": \"2026-02-27T03:00:14Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2030-11\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"2207\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"0W185671CT355581D\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/0W185671CT355581D\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/0W185671CT355581D/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/35063419WE539304V\", \"method\": \"GET\"}], \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"86\", \"invoice_id\": \"INV-20260227-86\", \"create_time\": \"2026-02-27T03:00:14Z\", \"update_time\": \"2026-02-27T03:00:14Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"928788037093288\", \"network\": \"VISA\"}}]}, \"custom_id\": \"86\", \"invoice_id\": \"INV-20260227-86\", \"description\": \"Commande #86 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_86\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-27 03:00:14', NULL),
(18, 'PP_20260227_69a11138e6b3e', 87, 23, '39.80', 'paypal', '1AK61345EV336101R', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"33V3375928187715L\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"1AK61345EV336101R\"}', '176.145.254.59', NULL, NULL, '2026-02-27 03:36:24', NULL),
(19, 'CB_20260227_69a111a2ca0f9', 88, 23, '54.80', 'carte', '1XL02374JW013482L', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"1XL02374JW013482L\", \"capture_id\": \"84V261841K2701646\", \"full_response\": {\"id\": \"1XL02374JW013482L\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1XL02374JW013482L\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T03:38:10Z\", \"update_time\": \"2026-02-27T03:38:10Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2029-12\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"1329\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"84V261841K2701646\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/84V261841K2701646\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/84V261841K2701646/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1XL02374JW013482L\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"88\", \"invoice_id\": \"INV-20260227-88\", \"create_time\": \"2026-02-27T03:38:10Z\", \"update_time\": \"2026-02-27T03:38:10Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"803619798948505\", \"network\": \"VISA\"}}]}, \"custom_id\": \"88\", \"invoice_id\": \"INV-20260227-88\", \"description\": \"Commande #88 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_88\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-27 03:38:10', NULL),
(20, 'PP_20260227_69a11755e5ad8', 89, 23, '84.80', 'paypal', '3GU792312J588822J', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"30R11643B65575330\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"3GU792312J588822J\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3GU792312J588822J\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T04:02:03Z\", \"update_time\": \"2026-02-27T04:02:29Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"30R11643B65575330\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/30R11643B65575330\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/30R11643B65575330/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3GU792312J588822J\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"89\", \"create_time\": \"2026-02-27T04:02:29Z\", \"update_time\": \"2026-02-27T04:02:29Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"89\", \"description\": \"Commande #89 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_89\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"3GU792312J588822J\"}', '176.145.254.59', NULL, NULL, '2026-02-27 04:02:29', NULL),
(21, 'PP_20260227_69a1466b15ba9', 101, 24, '39.80', 'paypal', '39F57635SH1064839', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"61H799918J823103R\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"39F57635SH1064839\"}', '176.145.254.59', NULL, NULL, '2026-02-27 07:23:23', NULL),
(22, 'CB_20260227_69a14a2e67772', 104, 24, '39.80', 'carte', '0VR11057FS547123H', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"0VR11057FS547123H\", \"capture_id\": \"1BJ46011U7870893B\", \"full_response\": {\"id\": \"0VR11057FS547123H\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0VR11057FS547123H\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T07:39:26Z\", \"update_time\": \"2026-02-27T07:39:26Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2028-08\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"1427\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"1BJ46011U7870893B\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1BJ46011U7870893B\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1BJ46011U7870893B/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0VR11057FS547123H\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"104\", \"invoice_id\": \"INV-20260227-104\", \"create_time\": \"2026-02-27T07:39:26Z\", \"update_time\": \"2026-02-27T07:39:26Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"158536990433167\", \"network\": \"VISA\"}}]}, \"custom_id\": \"104\", \"invoice_id\": \"INV-20260227-104\", \"description\": \"Commande #104 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_104\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-27 07:39:26', NULL),
(23, 'CB_20260227_69a14b3d426c1', 105, 24, '89.90', 'carte', '93F89319616704529', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"93F89319616704529\", \"capture_id\": \"0R3954829N854430U\", \"full_response\": {\"id\": \"93F89319616704529\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/93F89319616704529\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-27T07:43:57Z\", \"update_time\": \"2026-02-27T07:43:57Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2031-04\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"8457\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"0R3954829N854430U\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/0R3954829N854430U\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/0R3954829N854430U/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/93F89319616704529\", \"method\": \"GET\"}], \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"105\", \"invoice_id\": \"INV-20260227-105\", \"create_time\": \"2026-02-27T07:43:57Z\", \"update_time\": \"2026-02-27T07:43:57Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"856371375819069\", \"network\": \"VISA\"}}]}, \"custom_id\": \"105\", \"invoice_id\": \"INV-20260227-105\", \"description\": \"Commande #105 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_105\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-27 07:43:57', NULL),
(24, 'CB_20260228_69a25f1bbc44b', 107, 24, '39.80', 'carte', '35673243EG506164X', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"35673243EG506164X\", \"capture_id\": \"3RW35791B9182912G\", \"full_response\": {\"id\": \"35673243EG506164X\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/35673243EG506164X\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-28T03:20:59Z\", \"update_time\": \"2026-02-28T03:20:59Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2031-06\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"7067\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"3RW35791B9182912G\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3RW35791B9182912G\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3RW35791B9182912G/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/35673243EG506164X\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"107\", \"invoice_id\": \"INV-20260228-107-69a25f18ed7ac-1189\", \"create_time\": \"2026-02-28T03:20:59Z\", \"update_time\": \"2026-02-28T03:20:59Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"831230888473593\", \"network\": \"VISA\"}}]}, \"custom_id\": \"107\", \"invoice_id\": \"INV-20260228-107-69a25f18ed7ac-1189\", \"description\": \"Commande #107 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_107\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-28 03:20:59', NULL),
(25, 'PP_20260228_69a25fb667d0a', 108, 24, '39.80', 'paypal', '6T302547401377709', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"9WS709411J496313B\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"6T302547401377709\"}', '176.145.254.59', NULL, NULL, '2026-02-28 03:23:34', NULL),
(26, 'PP_20260228_69a2603f6a6f6', 109, 24, '39.80', 'paypal', '3CX055815U7702120', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"6EH83713948607420\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"3CX055815U7702120\"}', '176.145.254.59', NULL, NULL, '2026-02-28 03:25:51', NULL),
(27, 'PP_20260228_69a26295beb8f', 110, 24, '54.80', 'paypal', '5NW90509G6151304H', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"8LT71372E6291110S\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"5NW90509G6151304H\"}', '176.145.254.59', NULL, NULL, '2026-02-28 03:35:49', NULL),
(28, 'PP_20260228_69a26445697e7', 111, 24, '139.80', 'paypal', '4AD52908FK7680417', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"3JL56511NP727541K\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"4AD52908FK7680417\"}', '176.145.254.59', NULL, NULL, '2026-02-28 03:43:01', NULL),
(29, 'PP_20260228_69a265b2bae52', 112, 24, '89.90', 'paypal', '93S332914X601221J', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"1L833336PN7562307\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"93S332914X601221J\"}', '176.145.254.59', NULL, NULL, '2026-02-28 03:49:06', NULL),
(30, 'PP_20260228_69a26923397f8', 115, 24, '1200.00', 'paypal', '3NL187145C9521150', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2SU607690X430040F\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"3NL187145C9521150\"}', '176.145.254.59', NULL, NULL, '2026-02-28 04:03:47', NULL),
(31, 'PP_20260228_69a28890842cd', 117, 24, '54.80', 'paypal', '8S289706BF843802D', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"3RT17461VE5811427\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"8S289706BF843802D\"}', '176.145.254.59', NULL, NULL, '2026-02-28 06:17:52', NULL),
(32, 'PP_20260228_69a289e05b8e9', 120, 24, '54.80', 'paypal', '4S693452B6055021J', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"6BS962554J911194P\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"4S693452B6055021J\"}', '176.145.254.59', NULL, NULL, '2026-02-28 06:23:28', NULL),
(33, 'PP_20260228_69a28d9373e9b', 127, 24, '39.80', 'paypal', '66920335FL767582M', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"8GX703000F9287522\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"66920335FL767582M\"}', '176.145.254.59', NULL, NULL, '2026-02-28 06:39:15', NULL),
(34, 'CB_20260228_69a28ddaf2508', 128, 24, '89.90', 'carte', '5R9604950C642414L', 'paye', '{\"status\": \"COMPLETED\", \"order_id\": \"5R9604950C642414L\", \"capture_id\": \"873129740H592670R\", \"full_response\": {\"id\": \"5R9604950C642414L\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/5R9604950C642414L\", \"method\": \"GET\"}], \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-28T06:40:26Z\", \"update_time\": \"2026-02-28T06:40:26Z\", \"payment_source\": {\"card\": {\"name\": \"Philippe Lor\", \"type\": \"CREDIT\", \"brand\": \"VISA\", \"expiry\": \"2030-06\", \"bin_details\": {\"bin\": \"402002\", \"bin_country_code\": \"FR\"}, \"last_digits\": \"7282\", \"available_networks\": [\"VISA\"]}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"873129740H592670R\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/873129740H592670R\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/873129740H592670R/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/5R9604950C642414L\", \"method\": \"GET\"}], \"amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"status\": \"COMPLETED\", \"custom_id\": \"128\", \"invoice_id\": \"INV-20260228-128-69a28dd8112f9-4534\", \"create_time\": \"2026-02-28T06:40:26Z\", \"update_time\": \"2026-02-28T06:40:26Z\", \"final_capture\": true, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}, \"processor_response\": {\"avs_code\": \"A\", \"cvv_code\": \"M\", \"response_code\": \"0000\"}, \"seller_receivable_breakdown\": {\"net_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}, \"gross_amount\": {\"value\": \"89.90\", \"currency_code\": \"EUR\"}}, \"network_transaction_reference\": {\"id\": \"092125780856150\", \"network\": \"VISA\"}}]}, \"custom_id\": \"128\", \"invoice_id\": \"INV-20260228-128-69a28dd8112f9-4534\", \"description\": \"Commande #128 - HEURE DU CADEAU\", \"reference_id\": \"COMMANDE_128\", \"soft_descriptor\": \"TEST STORE\"}]}}', '176.145.254.59', NULL, NULL, '2026-02-28 06:40:26', NULL),
(35, 'PP_20260228_69a29088db0ad', 130, 24, '39.80', 'paypal', '7A5912259U2434429', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"4F786181LA562913C\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"7A5912259U2434429\"}', '176.145.254.59', NULL, NULL, '2026-02-28 06:51:52', NULL),
(36, 'PP_20260228_69a2fb6c51fcd', 140, 24, '54.80', 'paypal', '82H47240CN558410V', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"36V96231FJ4917232\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"82H47240CN558410V\"}', '176.145.254.59', NULL, NULL, '2026-02-28 14:27:56', NULL),
(37, 'PP_20260228_69a2fc22cb4e8', 141, 24, '54.80', 'paypal', '8G472281M8442025G', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"97Y18653U7388160A\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"8G472281M8442025G\"}', '176.145.254.59', NULL, NULL, '2026-02-28 14:30:58', NULL),
(38, 'PP_20260228_69a307f88648a', 144, 24, '39.80', 'paypal', '33090506G7040064S', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"3T6001497E762834R\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"paypal_order_id\": \"33090506G7040064S\"}', '176.140.218.240', NULL, NULL, '2026-02-28 15:21:28', NULL),
(39, 'PP_20260228_69a30ebcdca70', 145, 24, '54.80', 'paypal', '07021226KP2879427', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"28M48744AD2953849\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"07021226KP2879427\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/07021226KP2879427\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-28T15:49:57Z\", \"update_time\": \"2026-02-28T15:50:20Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"28M48744AD2953849\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/28M48744AD2953849\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/28M48744AD2953849/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/07021226KP2879427\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"145\", \"invoice_id\": \"INV-20260228-145-69a30ea4f12b2\", \"create_time\": \"2026-02-28T15:50:20Z\", \"update_time\": \"2026-02-28T15:50:20Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"145\", \"invoice_id\": \"INV-20260228-145-69a30ea4f12b2\", \"description\": \"Commande #145 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_145\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"07021226KP2879427\"}', '176.145.254.59', NULL, NULL, '2026-02-28 15:50:20', NULL),
(40, 'PP_20260228_69a30f93e1245', 146, 24, '39.80', 'paypal', '18T666794G446413G', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"97A269413T720974Y\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"18T666794G446413G\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/18T666794G446413G\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-28T15:53:30Z\", \"update_time\": \"2026-02-28T15:53:55Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"97A269413T720974Y\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/97A269413T720974Y\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/97A269413T720974Y/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/18T666794G446413G\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"146\", \"invoice_id\": \"INV-20260228-146-69a30f7a85502\", \"create_time\": \"2026-02-28T15:53:55Z\", \"update_time\": \"2026-02-28T15:53:55Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"146\", \"invoice_id\": \"INV-20260228-146-69a30f7a85502\", \"description\": \"Commande #146 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_146\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"18T666794G446413G\"}', '176.145.254.59', NULL, NULL, '2026-02-28 15:53:55', NULL),
(41, 'PP_20260228_69a3163abd3ea', 147, 24, '54.80', 'paypal', '0JR596167E231603S', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"62700346B1544562K\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"0JR596167E231603S\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0JR596167E231603S\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-02-28T16:21:57Z\", \"update_time\": \"2026-02-28T16:22:18Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"62700346B1544562K\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62700346B1544562K\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62700346B1544562K/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0JR596167E231603S\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"147\", \"invoice_id\": \"INV-20260228-147-69a316254b8d4\", \"create_time\": \"2026-02-28T16:22:18Z\", \"update_time\": \"2026-02-28T16:22:18Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"147\", \"invoice_id\": \"INV-20260228-147-69a316254b8d4\", \"description\": \"Commande #147 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_147\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"0JR596167E231603S\"}', '176.145.254.59', NULL, NULL, '2026-02-28 16:22:18', NULL),
(42, 'PP_20260301_69a3b261a6126', 149, 24, '54.80', 'paypal', '3D248340378726012', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"86X76230J59139128\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"3D248340378726012\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3D248340378726012\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T03:28:04Z\", \"update_time\": \"2026-03-01T03:28:33Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"86X76230J59139128\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/86X76230J59139128\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/86X76230J59139128/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3D248340378726012\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"149\", \"invoice_id\": \"INV-20260301-149-69a3b243de30e\", \"create_time\": \"2026-03-01T03:28:33Z\", \"update_time\": \"2026-03-01T03:28:33Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"149\", \"invoice_id\": \"INV-20260301-149-69a3b243de30e\", \"description\": \"Commande #149 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_149\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"3D248340378726012\"}', '176.145.254.59', NULL, NULL, '2026-03-01 03:28:33', NULL),
(43, 'PP_20260301_69a3b63026eb2', 150, 24, '39.80', 'paypal', '44338952BG330364R', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2A6375581S065923H\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"44338952BG330364R\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/44338952BG330364R\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T03:44:20Z\", \"update_time\": \"2026-03-01T03:44:47Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"2A6375581S065923H\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2A6375581S065923H\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2A6375581S065923H/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/44338952BG330364R\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"150\", \"invoice_id\": \"INV-20260301-150-69a3b6140e167\", \"create_time\": \"2026-03-01T03:44:47Z\", \"update_time\": \"2026-03-01T03:44:47Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"150\", \"invoice_id\": \"INV-20260301-150-69a3b6140e167\", \"description\": \"Commande #150 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_150\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"44338952BG330364R\"}', '176.145.254.59', NULL, NULL, '2026-03-01 03:44:48', NULL),
(44, 'PP_20260301_69a3b8e7b08bf', 153, 24, '39.80', 'paypal', '09711311M25913113', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"60S816206S020034E\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"09711311M25913113\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/09711311M25913113\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T03:55:56Z\", \"update_time\": \"2026-03-01T03:56:23Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"60S816206S020034E\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/60S816206S020034E\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/60S816206S020034E/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/09711311M25913113\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"153\", \"invoice_id\": \"INV-20260301-153-69a3b8cc1e455\", \"create_time\": \"2026-03-01T03:56:23Z\", \"update_time\": \"2026-03-01T03:56:23Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"153\", \"invoice_id\": \"INV-20260301-153-69a3b8cc1e455\", \"description\": \"Commande #153 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_153\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"09711311M25913113\"}', '176.145.254.59', NULL, NULL, '2026-03-01 03:56:23', NULL),
(45, 'PP_20260301_69a3bcb7604f8', 156, 24, '39.80', 'paypal', '6M3286849V600024X', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"62H49805DE202344P\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"6M3286849V600024X\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6M3286849V600024X\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:12:13Z\", \"update_time\": \"2026-03-01T04:12:39Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"62H49805DE202344P\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62H49805DE202344P\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62H49805DE202344P/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6M3286849V600024X\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"156\", \"invoice_id\": \"INV-20260301-156-69a3bc9d25f27\", \"create_time\": \"2026-03-01T04:12:38Z\", \"update_time\": \"2026-03-01T04:12:38Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"156\", \"invoice_id\": \"INV-20260301-156-69a3bc9d25f27\", \"description\": \"Commande #156 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_156\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"6M3286849V600024X\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:12:39', NULL),
(46, 'PP_20260301_69a3bde2996ef', 157, 24, '54.80', 'paypal', '6W109827PV812901V', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"1BH48833CB200024K\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"6W109827PV812901V\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6W109827PV812901V\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:17:10Z\", \"update_time\": \"2026-03-01T04:17:38Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"1BH48833CB200024K\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1BH48833CB200024K\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1BH48833CB200024K/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6W109827PV812901V\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"157\", \"invoice_id\": \"INV-20260301-157-69a3bdc69aab5\", \"create_time\": \"2026-03-01T04:17:38Z\", \"update_time\": \"2026-03-01T04:17:38Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"157\", \"invoice_id\": \"INV-20260301-157-69a3bdc69aab5\", \"description\": \"Commande #157 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_157\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"6W109827PV812901V\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:17:38', NULL);
INSERT INTO `transactions` (`id_transaction`, `numero_transaction`, `id_commande`, `id_client`, `montant`, `methode_paiement`, `reference_paiement`, `statut`, `details`, `ip_client`, `user_agent`, `session_id`, `date_creation`, `date_modification`) VALUES
(47, 'PP_20260301_69a3bf9319dcf', 158, 24, '54.80', 'paypal', '7RD93705428112624', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"8LY32502WY0338403\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"7RD93705428112624\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/7RD93705428112624\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:24:17Z\", \"update_time\": \"2026-03-01T04:24:50Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"8LY32502WY0338403\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8LY32502WY0338403\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8LY32502WY0338403/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/7RD93705428112624\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"158\", \"invoice_id\": \"INV-20260301-158-69a3bf71200e3\", \"create_time\": \"2026-03-01T04:24:50Z\", \"update_time\": \"2026-03-01T04:24:50Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"158\", \"invoice_id\": \"INV-20260301-158-69a3bf71200e3\", \"description\": \"Commande #158 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_158\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"7RD93705428112624\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:24:51', NULL),
(48, 'PP_20260301_69a3c1a3acd60', 159, 24, '54.80', 'paypal', '84154322VA962954U', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"75L956755E701950G\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"84154322VA962954U\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/84154322VA962954U\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:33:16Z\", \"update_time\": \"2026-03-01T04:33:39Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"75L956755E701950G\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/75L956755E701950G\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/75L956755E701950G/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/84154322VA962954U\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"159\", \"invoice_id\": \"INV-20260301-159-69a3c18bd85ae\", \"create_time\": \"2026-03-01T04:33:39Z\", \"update_time\": \"2026-03-01T04:33:39Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"159\", \"invoice_id\": \"INV-20260301-159-69a3c18bd85ae\", \"description\": \"Commande #159 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_159\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"84154322VA962954U\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:33:39', NULL),
(49, 'PP_20260301_69a3c353ab6ba', 160, 24, '54.80', 'paypal', '0A900202WA152310A', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"62B29627TJ282945M\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"0A900202WA152310A\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0A900202WA152310A\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:40:32Z\", \"update_time\": \"2026-03-01T04:40:51Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"62B29627TJ282945M\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62B29627TJ282945M\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/62B29627TJ282945M/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/0A900202WA152310A\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"160\", \"invoice_id\": \"INV-20260301-160-69a3c34054ecc\", \"create_time\": \"2026-03-01T04:40:51Z\", \"update_time\": \"2026-03-01T04:40:51Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"160\", \"invoice_id\": \"INV-20260301-160-69a3c34054ecc\", \"description\": \"Commande #160 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_160\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"0A900202WA152310A\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:40:51', NULL),
(50, 'PP_20260301_69a3c4ffe7d43', 161, 24, '39.80', 'paypal', '8UE587308L062421H', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"5883679949597881M\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"8UE587308L062421H\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/8UE587308L062421H\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:47:41Z\", \"update_time\": \"2026-03-01T04:47:59Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"5883679949597881M\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/5883679949597881M\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/5883679949597881M/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/8UE587308L062421H\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"161\", \"invoice_id\": \"INV-20260301-161-69a3c4ece78e8\", \"create_time\": \"2026-03-01T04:47:59Z\", \"update_time\": \"2026-03-01T04:47:59Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"161\", \"invoice_id\": \"INV-20260301-161-69a3c4ece78e8\", \"description\": \"Commande #161 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_161\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"8UE587308L062421H\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:47:59', NULL),
(51, 'PP_20260301_69a3c71535546', 162, 24, '39.80', 'paypal', '96A22642XU6277810', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2PK55060EE777473P\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"96A22642XU6277810\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/96A22642XU6277810\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T04:56:26Z\", \"update_time\": \"2026-03-01T04:56:53Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"2PK55060EE777473P\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2PK55060EE777473P\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2PK55060EE777473P/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/96A22642XU6277810\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"162\", \"invoice_id\": \"INV-20260301-162-69a3c6faa0b27\", \"create_time\": \"2026-03-01T04:56:52Z\", \"update_time\": \"2026-03-01T04:56:52Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"162\", \"invoice_id\": \"INV-20260301-162-69a3c6faa0b27\", \"description\": \"Commande #162 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_162\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"96A22642XU6277810\"}', '176.145.254.59', NULL, NULL, '2026-03-01 04:56:53', NULL),
(52, 'PP_20260301_69a3c8679c247', 163, 24, '39.80', 'paypal', '8MY10592CR052342F', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"1JX435121K668170B\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"8MY10592CR052342F\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/8MY10592CR052342F\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:02:08Z\", \"update_time\": \"2026-03-01T05:02:31Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"1JX435121K668170B\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1JX435121K668170B\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1JX435121K668170B/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/8MY10592CR052342F\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"163\", \"invoice_id\": \"INV-20260301-163-69a3c84fac76e\", \"create_time\": \"2026-03-01T05:02:30Z\", \"update_time\": \"2026-03-01T05:02:30Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"163\", \"invoice_id\": \"INV-20260301-163-69a3c84fac76e\", \"description\": \"Commande #163 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_163\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"8MY10592CR052342F\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:02:31', NULL),
(53, 'PP_20260301_69a3c96b03b7d', 164, 24, '39.80', 'paypal', '04M60177AY9337327', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"9BF9089835199452L\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"04M60177AY9337327\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/04M60177AY9337327\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:06:30Z\", \"update_time\": \"2026-03-01T05:06:50Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"9BF9089835199452L\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9BF9089835199452L\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9BF9089835199452L/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/04M60177AY9337327\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"164\", \"invoice_id\": \"INV-20260301-164-69a3c9562b851\", \"create_time\": \"2026-03-01T05:06:50Z\", \"update_time\": \"2026-03-01T05:06:50Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"164\", \"invoice_id\": \"INV-20260301-164-69a3c9562b851\", \"description\": \"Commande #164 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_164\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"04M60177AY9337327\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:06:51', NULL),
(54, 'PP_20260301_69a3cb7fe56b0', 165, 24, '39.80', 'paypal', '3AM72464K6252134Y', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"1V788061BR425871J\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"3AM72464K6252134Y\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3AM72464K6252134Y\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:15:22Z\", \"update_time\": \"2026-03-01T05:15:43Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"1V788061BR425871J\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1V788061BR425871J\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/1V788061BR425871J/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/3AM72464K6252134Y\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"165\", \"invoice_id\": \"INV-20260301-165-69a3cb6a2175e\", \"create_time\": \"2026-03-01T05:15:43Z\", \"update_time\": \"2026-03-01T05:15:43Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"165\", \"invoice_id\": \"INV-20260301-165-69a3cb6a2175e\", \"description\": \"Commande #165 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_165\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"3AM72464K6252134Y\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:15:43', NULL),
(55, 'PP_20260301_69a3cd3e3811b', 166, 24, '54.80', 'paypal', '95A54611DD228842N', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"3BD28643AT475311N\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"95A54611DD228842N\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/95A54611DD228842N\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:22:47Z\", \"update_time\": \"2026-03-01T05:23:10Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"3BD28643AT475311N\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3BD28643AT475311N\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3BD28643AT475311N/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/95A54611DD228842N\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"166\", \"invoice_id\": \"INV-20260301-166-69a3cd26ee5ff\", \"create_time\": \"2026-03-01T05:23:09Z\", \"update_time\": \"2026-03-01T05:23:09Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"166\", \"invoice_id\": \"INV-20260301-166-69a3cd26ee5ff\", \"description\": \"Commande #166 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_166\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"95A54611DD228842N\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:23:10', NULL),
(56, 'PP_20260301_69a3ce664390c', 167, 24, '39.80', 'paypal', '02S11715T7746225C', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"3XR60274B0612961V\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"02S11715T7746225C\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/02S11715T7746225C\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:27:42Z\", \"update_time\": \"2026-03-01T05:28:06Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"3XR60274B0612961V\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3XR60274B0612961V\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/3XR60274B0612961V/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/02S11715T7746225C\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"167\", \"invoice_id\": \"INV-20260301-167-69a3ce4e93dc2\", \"create_time\": \"2026-03-01T05:28:05Z\", \"update_time\": \"2026-03-01T05:28:05Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"167\", \"invoice_id\": \"INV-20260301-167-69a3ce4e93dc2\", \"description\": \"Commande #167 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_167\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"02S11715T7746225C\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:28:06', NULL),
(57, 'PP_20260301_69a3d00fb7611', 168, 24, '84.80', 'paypal', '597884701V9904848', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"56R46120FH315632P\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"597884701V9904848\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/597884701V9904848\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:34:45Z\", \"update_time\": \"2026-03-01T05:35:11Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"56R46120FH315632P\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/56R46120FH315632P\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/56R46120FH315632P/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/597884701V9904848\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"168\", \"invoice_id\": \"INV-20260301-168-69a3cff5007e6\", \"create_time\": \"2026-03-01T05:35:11Z\", \"update_time\": \"2026-03-01T05:35:11Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"168\", \"invoice_id\": \"INV-20260301-168-69a3cff5007e6\", \"description\": \"Commande #168 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_168\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"597884701V9904848\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:35:11', NULL),
(58, 'PP_20260301_69a3d316eda65', 170, 24, '54.80', 'paypal', '1GD57361KW019471W', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"5EF85247R5074132B\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"1GD57361KW019471W\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1GD57361KW019471W\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T05:47:46Z\", \"update_time\": \"2026-03-01T05:48:06Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"5EF85247R5074132B\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/5EF85247R5074132B\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/5EF85247R5074132B/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/1GD57361KW019471W\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"170\", \"invoice_id\": \"INV-20260301-170-69a3d302053ba\", \"create_time\": \"2026-03-01T05:48:06Z\", \"update_time\": \"2026-03-01T05:48:06Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"170\", \"invoice_id\": \"INV-20260301-170-69a3d302053ba\", \"description\": \"Commande #170 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_170\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"1GD57361KW019471W\"}', '176.145.254.59', NULL, NULL, '2026-03-01 05:48:06', NULL),
(59, 'PP_20260301_69a3d5f641fa7', 171, 24, '54.80', 'paypal', '7C6644994V2672632', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2GN76091Y30459211\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"7C6644994V2672632\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/7C6644994V2672632\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T06:00:02Z\", \"update_time\": \"2026-03-01T06:00:22Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"2GN76091Y30459211\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2GN76091Y30459211\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2GN76091Y30459211/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/7C6644994V2672632\", \"method\": \"GET\"}], \"amount\": {\"value\": \"54.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"171\", \"invoice_id\": \"INV-20260301-171-69a3d5e1bba74\", \"create_time\": \"2026-03-01T06:00:21Z\", \"update_time\": \"2026-03-01T06:00:21Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"171\", \"invoice_id\": \"INV-20260301-171-69a3d5e1bba74\", \"description\": \"Commande #171 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_171\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"7C6644994V2672632\"}', '176.145.254.59', NULL, NULL, '2026-03-01 06:00:22', NULL),
(60, 'PP_20260301_69a3e133976ee', 172, 24, '84.80', 'paypal', '6N7793456M0013249', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"35C19386ML690605N\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"6N7793456M0013249\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6N7793456M0013249\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-01T06:47:51Z\", \"update_time\": \"2026-03-01T06:48:19Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"35C19386ML690605N\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/35C19386ML690605N\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/35C19386ML690605N/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6N7793456M0013249\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"172\", \"invoice_id\": \"INV-20260301-172-69a3e11738565\", \"create_time\": \"2026-03-01T06:48:19Z\", \"update_time\": \"2026-03-01T06:48:19Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"172\", \"invoice_id\": \"INV-20260301-172-69a3e11738565\", \"description\": \"Commande #172 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_172\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"6N7793456M0013249\"}', '176.145.254.59', NULL, NULL, '2026-03-01 06:48:19', NULL),
(61, 'PP_20260302_69a4fbfed19a4', 173, 24, '84.80', 'paypal', '65922228HT443833Y', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"7J768322B5731914H\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"65922228HT443833Y\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/65922228HT443833Y\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T02:54:25Z\", \"update_time\": \"2026-03-02T02:54:54Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"7J768322B5731914H\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/7J768322B5731914H\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/7J768322B5731914H/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/65922228HT443833Y\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"173\", \"invoice_id\": \"INV-20260302-173-69a4fbe12d61a\", \"create_time\": \"2026-03-02T02:54:54Z\", \"update_time\": \"2026-03-02T02:54:54Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"173\", \"invoice_id\": \"INV-20260302-173-69a4fbe12d61a\", \"description\": \"Commande #173 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_173\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"65922228HT443833Y\"}', '176.145.254.59', NULL, NULL, '2026-03-02 02:54:54', NULL),
(62, 'PP_20260302_69a50411984ae', 174, 24, '39.80', 'paypal', '11402914NX430835P', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"9RP20629AC4033943\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"11402914NX430835P\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/11402914NX430835P\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T03:28:54Z\", \"update_time\": \"2026-03-02T03:29:21Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"9RP20629AC4033943\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9RP20629AC4033943\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9RP20629AC4033943/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/11402914NX430835P\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"174\", \"invoice_id\": \"INV-20260302-174-69a503f634506\", \"create_time\": \"2026-03-02T03:29:20Z\", \"update_time\": \"2026-03-02T03:29:20Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"174\", \"invoice_id\": \"INV-20260302-174-69a503f634506\", \"description\": \"Commande #174 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_174\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"11402914NX430835P\"}', '176.145.254.59', NULL, NULL, '2026-03-02 03:29:21', NULL),
(63, 'PP_20260302_69a5046981c67', 175, 24, '39.80', 'paypal', '69Y5542383286374E', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2Y311136X4241872K\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"69Y5542383286374E\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/69Y5542383286374E\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T03:30:43Z\", \"update_time\": \"2026-03-02T03:30:49Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"2Y311136X4241872K\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2Y311136X4241872K\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2Y311136X4241872K/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/69Y5542383286374E\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"175\", \"invoice_id\": \"INV-20260302-175-69a504636ab74\", \"create_time\": \"2026-03-02T03:30:48Z\", \"update_time\": \"2026-03-02T03:30:48Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"175\", \"invoice_id\": \"INV-20260302-175-69a504636ab74\", \"description\": \"Commande #175 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_175\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"69Y5542383286374E\"}', '176.145.254.59', NULL, NULL, '2026-03-02 03:30:49', NULL),
(64, 'PP_20260302_69a504bc1b5cd', 176, 24, '39.80', 'paypal', '4UY61291KX073094W', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"8VM39461V22039225\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"4UY61291KX073094W\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4UY61291KX073094W\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T03:31:44Z\", \"update_time\": \"2026-03-02T03:32:11Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"8VM39461V22039225\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8VM39461V22039225\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/8VM39461V22039225/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4UY61291KX073094W\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"176\", \"invoice_id\": \"INV-20260302-176-69a504a048748\", \"create_time\": \"2026-03-02T03:32:11Z\", \"update_time\": \"2026-03-02T03:32:11Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"176\", \"invoice_id\": \"INV-20260302-176-69a504a048748\", \"description\": \"Commande #176 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_176\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"4UY61291KX073094W\"}', '176.145.254.59', NULL, NULL, '2026-03-02 03:32:12', NULL),
(65, 'PP_20260302_69a508db96da3', 177, 24, '84.80', 'paypal', '9HM29848PF193760C', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"74244753TL406701N\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"9HM29848PF193760C\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/9HM29848PF193760C\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T03:49:33Z\", \"update_time\": \"2026-03-02T03:49:47Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"74244753TL406701N\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/74244753TL406701N\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/74244753TL406701N/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/9HM29848PF193760C\", \"method\": \"GET\"}], \"amount\": {\"value\": \"84.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"177\", \"invoice_id\": \"INV-20260302-177-69a508ccd85a5\", \"create_time\": \"2026-03-02T03:49:46Z\", \"update_time\": \"2026-03-02T03:49:46Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"177\", \"invoice_id\": \"INV-20260302-177-69a508ccd85a5\", \"description\": \"Commande #177 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_177\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"9HM29848PF193760C\"}', '176.145.254.59', NULL, NULL, '2026-03-02 03:49:47', NULL),
(66, 'PP_20260302_69a50bd572010', 178, 24, '39.80', 'paypal', '4W598472NT092683P', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"9T202745WT481881W\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"4W598472NT092683P\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4W598472NT092683P\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T04:02:12Z\", \"update_time\": \"2026-03-02T04:02:29Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"9T202745WT481881W\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9T202745WT481881W\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/9T202745WT481881W/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4W598472NT092683P\", \"method\": \"GET\"}], \"amount\": {\"value\": \"39.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"178\", \"invoice_id\": \"INV-20260302-178-69a50bc3af770\", \"create_time\": \"2026-03-02T04:02:28Z\", \"update_time\": \"2026-03-02T04:02:28Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"178\", \"invoice_id\": \"INV-20260302-178-69a50bc3af770\", \"description\": \"Commande #178 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_178\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"4W598472NT092683P\"}', '176.145.254.59', NULL, NULL, '2026-03-02 04:02:29', NULL);
INSERT INTO `transactions` (`id_transaction`, `numero_transaction`, `id_commande`, `id_client`, `montant`, `methode_paiement`, `reference_paiement`, `statut`, `details`, `ip_client`, `user_agent`, `session_id`, `date_creation`, `date_modification`) VALUES
(67, 'PP_20260302_69a5107f6b580', 180, 25, '189.70', 'paypal', '4L444954MP545150M', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"53U07202TK841882Y\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"4L444954MP545150M\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4L444954MP545150M\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-02T04:22:02Z\", \"update_time\": \"2026-03-02T04:22:23Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"189.70\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"53U07202TK841882Y\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/53U07202TK841882Y\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/53U07202TK841882Y/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/4L444954MP545150M\", \"method\": \"GET\"}], \"amount\": {\"value\": \"189.70\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"180\", \"invoice_id\": \"INV-20260302-180-69a5106a43983\", \"create_time\": \"2026-03-02T04:22:22Z\", \"update_time\": \"2026-03-02T04:22:22Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"180\", \"invoice_id\": \"INV-20260302-180-69a5106a43983\", \"description\": \"Commande #180 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_180\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"4L444954MP545150M\"}', '176.145.254.59', NULL, NULL, '2026-03-02 04:22:23', NULL),
(68, 'PP_20260303_69a6448ae4b42', 184, 26, '124.80', 'paypal', '6BR186212R235122V', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"4T303172M50993228\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"6BR186212R235122V\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6BR186212R235122V\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-03T02:15:17Z\", \"update_time\": \"2026-03-03T02:16:42Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"124.80\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"4T303172M50993228\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/4T303172M50993228\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/4T303172M50993228/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/6BR186212R235122V\", \"method\": \"GET\"}], \"amount\": {\"value\": \"124.80\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"184\", \"invoice_id\": \"INV-20260303-184-69a64434baecb\", \"create_time\": \"2026-03-03T02:16:42Z\", \"update_time\": \"2026-03-03T02:16:42Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"184\", \"invoice_id\": \"INV-20260303-184-69a64434baecb\", \"description\": \"Commande #184 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_184\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"6BR186212R235122V\"}', '176.145.254.59', NULL, NULL, '2026-03-03 02:16:42', NULL),
(69, 'PP_20260303_69a67783cc02c', 185, 27, '134.70', 'paypal', '16965061BP5556438', 'paye', '{\"payer_id\": \"7HHSGDAL98AD2\", \"capture_id\": \"2VL76989JC7758511\", \"payer_email\": \"sb-lbcqf47423737@personal.example.com\", \"full_response\": {\"id\": \"16965061BP5556438\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/16965061BP5556438\", \"method\": \"GET\"}], \"payer\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"payer_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\"}, \"intent\": \"CAPTURE\", \"status\": \"COMPLETED\", \"create_time\": \"2026-03-03T05:53:40Z\", \"update_time\": \"2026-03-03T05:54:11Z\", \"payment_source\": {\"paypal\": {\"name\": {\"surname\": \"Doe\", \"given_name\": \"John\"}, \"address\": {\"country_code\": \"FR\"}, \"account_id\": \"7HHSGDAL98AD2\", \"email_address\": \"sb-lbcqf47423737@personal.example.com\", \"account_status\": \"VERIFIED\"}}, \"purchase_units\": [{\"payee\": {\"merchant_id\": \"63GB2YZTVNU52\", \"display_data\": {\"brand_name\": \"HEURE DU CADEAU\"}, \"email_address\": \"sb-vyvj047419601@business.example.com\"}, \"amount\": {\"value\": \"134.70\", \"currency_code\": \"EUR\"}, \"payments\": {\"captures\": [{\"id\": \"2VL76989JC7758511\", \"links\": [{\"rel\": \"self\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2VL76989JC7758511\", \"method\": \"GET\"}, {\"rel\": \"refund\", \"href\": \"https://api.sandbox.paypal.com/v2/payments/captures/2VL76989JC7758511/refund\", \"method\": \"POST\"}, {\"rel\": \"up\", \"href\": \"https://api.sandbox.paypal.com/v2/checkout/orders/16965061BP5556438\", \"method\": \"GET\"}], \"amount\": {\"value\": \"134.70\", \"currency_code\": \"EUR\"}, \"status\": \"PENDING\", \"custom_id\": \"185\", \"invoice_id\": \"INV-20260303-185-69a67763b55da\", \"create_time\": \"2026-03-03T05:54:11Z\", \"update_time\": \"2026-03-03T05:54:11Z\", \"final_capture\": true, \"status_details\": {\"reason\": \"PENDING_REVIEW\"}, \"seller_protection\": {\"status\": \"NOT_ELIGIBLE\"}}]}, \"custom_id\": \"185\", \"invoice_id\": \"INV-20260303-185-69a67763b55da\", \"description\": \"Commande #185 - HEURE DU CADEAU\", \"reference_id\": \"ORDER_185\", \"soft_descriptor\": \"PAYPAL *TEST STORE\"}]}, \"paypal_order_id\": \"16965061BP5556438\"}', '176.145.254.59', NULL, NULL, '2026-03-03 05:54:11', NULL);

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
`adresse_facturation_differente` int
,`date_commande` datetime
,`email` varchar(255)
,`is_temporary` tinyint(1)
,`nom` varchar(100)
,`nombre_items` bigint
,`numero_commande` varchar(50)
,`prenom` varchar(100)
,`total_ttc` decimal(10,2)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_conversions`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_conversions` (
`clients_convertis` bigint
,`conversions` bigint
,`date_conversion` date
,`jours_moyen_conversion` decimal(10,1)
,`methode_conversion` enum('post_commande','formulaire','newsletter','admin')
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_paniers_actifs`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_paniers_actifs` (
`date_creation` datetime
,`date_modification` datetime
,`dernier_ajout` datetime
,`email_client` varchar(255)
,`id_client` int
,`id_panier` int
,`nombre_items` bigint
,`session_id` varchar(255)
,`statut` enum('actif','fusionne','valide','abandonne')
,`telephone_client` varchar(20)
,`total_articles` decimal(32,0)
,`valeur_totale` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_populaires_panier`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_produits_populaires_panier` (
`id_produit` int
,`moyenne_quantite_par_panier` decimal(14,4)
,`nom` varchar(255)
,`paniers_actifs` bigint
,`paniers_actifs_count` bigint
,`quantite_total_paniers` decimal(32,0)
,`reference` varchar(50)
);

-- --------------------------------------------------------

--
-- Doublure de structure pour la vue `vue_produits_stock_alerte`
-- (Voir ci-dessous la vue réelle)
--
CREATE TABLE `vue_produits_stock_alerte` (
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
CREATE TABLE `vue_statistiques_produits` (
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
  MODIFY `id_adresse` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=397;

--
-- AUTO_INCREMENT pour la table `checkout_sessions`
--
ALTER TABLE `checkout_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id_client` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id_commande` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=186;

--
-- AUTO_INCREMENT pour la table `commande_items`
--
ALTER TABLE `commande_items`
  MODIFY `id_item` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=209;

--
-- AUTO_INCREMENT pour la table `commande_temporaire`
--
ALTER TABLE `commande_temporaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=298;

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
-- AUTO_INCREMENT pour la table `logs`
--
ALTER TABLE `logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=444;

--
-- AUTO_INCREMENT pour la table `panier`
--
ALTER TABLE `panier`
  MODIFY `id_panier` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=386;

--
-- AUTO_INCREMENT pour la table `panier_logs`
--
ALTER TABLE `panier_logs`
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `panier_temporaire`
--
ALTER TABLE `panier_temporaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `produits`
--
ALTER TABLE `produits`
  MODIFY `id_produit` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `produits_populaires`
--
ALTER TABLE `produits_populaires`
  MODIFY `id_populaire` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id_transaction` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

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
