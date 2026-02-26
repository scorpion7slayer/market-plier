-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Hôte : localhost:3306
-- Généré le : jeu. 26 fév. 2026 à 12:29
-- Version du serveur : 8.0.45-0ubuntu0.24.04.1
-- Version de PHP : 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `marketplier`
--

-- --------------------------------------------------------

--
-- Structure de la table `profile`
--

CREATE TABLE `profile` (
  `description` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `username` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `auth_provider` enum('local','google','both') NOT NULL DEFAULT 'local',
  `password_hash` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `auth_token` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`username`, `email`, `google_id`, `auth_provider`, `password_hash`, `profile_photo`, `is_admin`, `created_at`, `auth_token`) VALUES
('Gaspami', 'gaspami@gmail.com', NULL, 'local', '$2y$12$GCgfAY/6wmA5rQ.BrzYzHO.tFeoWPWP8VI4TpHr.yrpxrSbdKxCjG', 'user_d9f6b978000ec5741ff5d2b886f925b4db46b567701fda41b41e10fef93f6e78_1770967886.png', 0, '2026-02-13 07:31:08', 'd9f6b978000ec5741ff5d2b886f925b4db46b567701fda41b41e10fef93f6e78'),
('theo', 'theodarville@gmail.com', '101826161579785365348', 'both', '$2y$12$HVkLWHAeJ54/bawzD8unHelKOmdBwTn56aYEvlPHIYDJNQt5U2ZgO', 'user_4337b9b2c6d3633fd055c0c878396ef4b2f83de0a572a036356bcfac74d0480d_1770968406.png', 0, '2026-02-13 07:28:32', '4337b9b2c6d3633fd055c0c878396ef4b2f83de0a572a036356bcfac74d0480d');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `auth_token` (`auth_token`),
  ADD UNIQUE KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_auth_token` (`auth_token`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
