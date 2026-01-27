-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : sam. 27 déc. 2025 à 04:16
-- Version du serveur : 8.0.44-0ubuntu0.22.04.1
-- Version de PHP : 8.1.2-1ubuntu2.22

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
(3, 'admin', '007', 'lhpp.philippe@gmail.com', 'superadmin', 'active', '2025-12-10 02:39:26', '2025-12-10 02:39:26', 0, 0, NULL, '2025-12-07 06:28:27', '2025-12-10 02:39:26');

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
-- Déclencheurs `commandes`
--
DELIMITER $$
CREATE TRIGGER `before_commande_insert` BEFORE INSERT ON `commandes` FOR EACH ROW BEGIN
    DECLARE next_id INT;
    
    -- Obtenir le prochain ID disponible
    SELECT AUTO_INCREMENT INTO next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'commandes';
    
    -- Si next_id est NULL, utiliser 1
    IF next_id IS NULL THEN
        SET next_id = 1;
    END IF;
    
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
(1, '40', '{\"nom\":\"LOR\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":null,\"societe\":null,\"adresse\":\"AZERTY\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\",\"mode_livraison\":\"standard\",\"emballage_cadeau\":0,\"instructions\":null}', NULL, 'standard', 0, NULL, '2025-12-25 06:58:37'),
(2, '41', '{\"nom\":\"MADOIRE\",\"prenom\":\"Fran\\u00e7ois\",\"email\":\"animateurfrancoiscatic9@gmail.com\",\"telephone\":null,\"societe\":null,\"adresse\":\"QSDFGHJK\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\",\"mode_livraison\":\"standard\",\"emballage_cadeau\":0,\"instructions\":null}', NULL, 'standard', 0, NULL, '2025-12-25 07:43:20'),
(3, '42', '{\"nom\":\"LOR\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":null,\"societe\":null,\"adresse\":\"116 RUE DE JAVEL\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\",\"mode_livraison\":\"standard\",\"emballage_cadeau\":0,\"instructions\":null}', NULL, 'standard', 0, NULL, '2025-12-26 01:48:00'),
(4, '44', '{\"nom\":\"LOR\",\"prenom\":\"Philippe\",\"email\":\"lhpp.philippe@gmail.com\",\"telephone\":null,\"societe\":null,\"adresse\":\"116 RUE DE JAVEL\",\"complement\":null,\"code_postal\":\"75015\",\"ville\":\"Paris\",\"pays\":\"France\",\"mode_livraison\":\"standard\",\"emballage_cadeau\":0,\"instructions\":null}', NULL, 'standard', 0, NULL, '2025-12-26 02:15:22');

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
(80, 'info', 'info', 'Formulaire livraison traité avec succès', 15, '13.38.65.236', NULL, NULL, NULL, '2025-12-26 02:15:22');

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
(48, NULL, '312229s56rlo6s1m14d90o1g7p', NULL, NULL, NULL, 'actif', '2025-12-26 03:51:02', '2025-12-26 05:46:05', NULL),
(49, NULL, '3eua2vjk3khr86t6gqggear2nh', NULL, NULL, NULL, 'actif', '2025-12-27 01:07:05', NULL, NULL);

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

--
-- Déchargement des données de la table `panier_logs`
--

INSERT INTO `panier_logs` (`id_log`, `id_panier`, `session_id`, `action`, `id_produit`, `ancienne_quantite`, `nouvelle_quantite`, `ip_address`, `user_agent`, `date_action`) VALUES
(1, 48, '312229s56rlo6s1m14d90o1g7p', 'suppression', 4, 0, 0, '13.38.65.236', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 04:55:31'),
(2, 48, '312229s56rlo6s1m14d90o1g7p', 'suppression', 1, 0, 0, '13.38.38.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 05:44:59'),
(3, 48, '312229s56rlo6s1m14d90o1g7p', 'suppression', 1, 0, 0, '13.38.38.206', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2025-12-26 05:46:05');

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
(1, 'REF001', 'Bougie parfumée \"Élégance\"', 'bougie-parfumee-elegance', 'Bougie artisanale parfum vanille et santal. 50h de combustion.', 'Bougie artisanale parfum vanille et santal', '29.08', '20.00', 100, 10, 1, 'Artisanat Français', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(2, 'REF002', 'Coffret gourmand \"Délice\"', 'coffret-gourmand-delice', 'Sélection des meilleures spécialités françaises. Emballage cadeau inclus.', 'Sélection de spécialités françaises', '41.58', '20.00', 50, 10, 1, 'Saveurs de France', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(3, 'REF003', 'Montre \"Temps Précieux\"', 'montre-temps-precieux', 'Montre élégante avec gravure personnalisée au dos du boitier.', 'Montre avec gravure personnalisée', '74.92', '20.00', 25, 5, 1, 'Luxe & Style', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2025-12-07 16:56:32', NULL),
(4, 'REF004', 'Set bijoux \"Lumière\"', 'set-bijoux-lumiere', 'Collier, boucles d\'oreilles et bracelet assortis. Argent 925.', 'Set bijoux en argent 925', '1000.00', '20.00', 30, 5, 1, 'Bijoux d\'Exception', NULL, NULL, NULL, NULL, NULL, 0, 0, 0, 0, 0, '0.00', 0, 0, 0, 'actif', '2025-12-07 16:56:32', '2025-12-10 03:17:08');

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
  MODIFY `id_adresse` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT pour la table `checkout_sessions`
--
ALTER TABLE `checkout_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `clients`
--
ALTER TABLE `clients`
  MODIFY `id_client` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT pour la table `commande_temporaire`
--
ALTER TABLE `commande_temporaire`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `id_log` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT pour la table `panier`
--
ALTER TABLE `panier`
  MODIFY `id_panier` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

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
-- AUTO_INCREMENT pour la table `produits_populaires`
--
ALTER TABLE `produits_populaires`
  MODIFY `id_populaire` int NOT NULL AUTO_INCREMENT;

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
-- Contraintes pour la table `commande_items`
--
ALTER TABLE `commande_items`
  ADD CONSTRAINT `fk_commande_items_produit` FOREIGN KEY (`id_produit`) REFERENCES `produits` (`id_produit`);

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
