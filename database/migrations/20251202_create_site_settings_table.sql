CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `admin_username` VARCHAR(190) DEFAULT NULL,
  `admin_password_hash` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`admin_enabled`, `admin_username`, `admin_password_hash`, `created_at`, `updated_at`)
SELECT 0, NULL, NULL, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `site_settings` LIMIT 1);
