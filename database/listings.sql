-- Table des annonces
-- À exécuter dans phpMyAdmin sur la base `marketplier`

CREATE TABLE `listings` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `auth_token`     VARCHAR(64)      NOT NULL,
  `title`          VARCHAR(100)     NOT NULL,
  `description`    TEXT             NOT NULL,
  `price`          DECIMAL(10,2)    UNSIGNED NOT NULL,
  `category`       VARCHAR(50)      NOT NULL,
  `item_condition` VARCHAR(30)      NOT NULL,
  `image`          VARCHAR(255)     DEFAULT NULL,
  `location`       VARCHAR(100)     DEFAULT NULL,
  `created_at`     TIMESTAMP        NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auth_token` (`auth_token`),
  CONSTRAINT `fk_listing_auth_token`
    FOREIGN KEY (`auth_token`) REFERENCES `users` (`auth_token`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
