-- Market Plier — Schéma de base de données (exemple)
-- Version du serveur : 8.0+
-- Encodage : utf8mb4
--
-- Ce fichier contient uniquement la structure des tables et des données
-- de configuration par défaut. Il ne contient aucune donnée utilisateur.
--
-- Usage :
--   mysql -u <user> -p marketplier < database/example.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- --------------------------------------------------------
-- Table `users`
-- --------------------------------------------------------

CREATE TABLE `users` (
  `username` varchar(30) NOT NULL,
  `email` varchar(255) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `auth_provider` enum('local','google','both') NOT NULL DEFAULT 'local',
  `password_hash` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `auth_token` varchar(64) NOT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `stripe_account_id` varchar(255) DEFAULT NULL,
  `stripe_onboarding_complete` tinyint(1) DEFAULT '0',
  `profile_photo_data` longblob,
  `profile_photo_mime` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `profile`
-- --------------------------------------------------------

CREATE TABLE `profile` (
  `auth_token` varchar(64) NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `user_settings`
-- --------------------------------------------------------

CREATE TABLE `user_settings` (
  `auth_token` varchar(64) NOT NULL,
  `notif_email` tinyint(1) NOT NULL DEFAULT '1',
  `notif_price_alerts` tinyint(1) NOT NULL DEFAULT '0',
  `notif_weekly_summary` tinyint(1) NOT NULL DEFAULT '1',
  `privacy_public_profile` tinyint(1) NOT NULL DEFAULT '1',
  `privacy_show_email` tinyint(1) NOT NULL DEFAULT '0',
  `privacy_activity_history` tinyint(1) NOT NULL DEFAULT '1',
  `language` varchar(5) NOT NULL DEFAULT 'fr'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `listings`
-- --------------------------------------------------------

CREATE TABLE `listings` (
  `id` int UNSIGNED NOT NULL,
  `auth_token` varchar(64) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) UNSIGNED NOT NULL,
  `quantity` int UNSIGNED NOT NULL DEFAULT '1',
  `category` varchar(50) NOT NULL,
  `item_condition` varchar(30) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('active','pending','sold') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `listing_images`
-- --------------------------------------------------------

CREATE TABLE `listing_images` (
  `id` int UNSIGNED NOT NULL,
  `listing_id` int UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int UNSIGNED DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `image_data` longblob,
  `mime_type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `favorites`
-- --------------------------------------------------------

CREATE TABLE `favorites` (
  `id` int UNSIGNED NOT NULL,
  `auth_token` varchar(64) NOT NULL,
  `listing_id` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `conversations`
-- --------------------------------------------------------

CREATE TABLE `conversations` (
  `id` int UNSIGNED NOT NULL,
  `user1_token` varchar(64) NOT NULL,
  `user2_token` varchar(64) NOT NULL,
  `listing_id` int UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `messages`
-- --------------------------------------------------------

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `conversation_id` int UNSIGNED NOT NULL,
  `sender_token` varchar(64) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `notifications`
-- --------------------------------------------------------

CREATE TABLE `notifications` (
  `id` int UNSIGNED NOT NULL,
  `auth_token` varchar(64) NOT NULL,
  `type` enum('message','review','favorite','system','sale') NOT NULL DEFAULT 'system',
  `title` varchar(255) NOT NULL,
  `content` text,
  `link` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `orders`
-- --------------------------------------------------------

CREATE TABLE `orders` (
  `id` int UNSIGNED NOT NULL,
  `listing_id` int UNSIGNED NOT NULL,
  `buyer_token` varchar(64) NOT NULL,
  `seller_token` varchar(64) NOT NULL,
  `stripe_session_id` varchar(255) DEFAULT NULL,
  `stripe_payment_intent` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) NOT NULL,
  `delivery_address` text,
  `status` enum('pending','completed','refunded') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `reviews`
-- --------------------------------------------------------

CREATE TABLE `reviews` (
  `id` int UNSIGNED NOT NULL,
  `reviewer_token` varchar(64) NOT NULL,
  `seller_token` varchar(64) NOT NULL,
  `listing_id` int UNSIGNED DEFAULT NULL,
  `rating` tinyint UNSIGNED NOT NULL,
  `comment` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `push_subscriptions`
-- --------------------------------------------------------

CREATE TABLE `push_subscriptions` (
  `id` int UNSIGNED NOT NULL,
  `auth_token` varchar(64) NOT NULL,
  `endpoint` text NOT NULL,
  `p256dh` varchar(255) NOT NULL,
  `auth_key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------
-- Table `site_settings`
-- --------------------------------------------------------

CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Valeurs par défaut
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('google_login', '1'),
('listing_limit', '0'),
('maintenance_mode', '0'),
('moderation_enabled', '0'),
('registration_open', '1');

-- ========================================================
-- Clés primaires et index
-- ========================================================

ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv_user1` (`user1_token`),
  ADD KEY `idx_conv_user2` (`user2_token`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `favorites`
  ADD PRIMARY KEY (`id`),
  ADD KEY `auth_token` (`auth_token`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auth_token` (`auth_token`);

ALTER TABLE `listing_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_sender` (`sender_token`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_auth_token` (`auth_token`);

ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orders_buyer` (`buyer_token`),
  ADD KEY `idx_orders_seller` (`seller_token`),
  ADD KEY `idx_orders_stripe_session` (`stripe_session_id`);

ALTER TABLE `profile`
  ADD PRIMARY KEY (`auth_token`);

ALTER TABLE `push_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_push_auth_token` (`auth_token`);

ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_review` (`reviewer_token`,`seller_token`,`listing_id`),
  ADD KEY `idx_seller` (`seller_token`),
  ADD KEY `idx_reviewer` (`reviewer_token`),
  ADD KEY `listing_id` (`listing_id`);

ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

ALTER TABLE `users`
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `auth_token` (`auth_token`),
  ADD UNIQUE KEY `idx_google_id` (`google_id`),
  ADD KEY `idx_auth_token` (`auth_token`);

ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`auth_token`);

-- ========================================================
-- AUTO_INCREMENT
-- ========================================================

ALTER TABLE `conversations` MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `favorites`     MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `listings`      MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `listing_images` MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `messages`      MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `notifications` MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `orders`        MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `push_subscriptions` MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `reviews`       MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

-- ========================================================
-- Clés étrangères
-- ========================================================

ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user1_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user2_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL;

ALTER TABLE `favorites`
  ADD CONSTRAINT `favorites_ibfk_1` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE,
  ADD CONSTRAINT `favorites_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

ALTER TABLE `listings`
  ADD CONSTRAINT `fk_listing_auth_token` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

ALTER TABLE `listing_images`
  ADD CONSTRAINT `listing_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

ALTER TABLE `profile`
  ADD CONSTRAINT `fk_profile_auth_token` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`seller_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL;

ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_user_settings_auth` FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`) ON DELETE CASCADE;

COMMIT;
