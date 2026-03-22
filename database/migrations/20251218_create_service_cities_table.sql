-- Create service_cities table (MySQL-compatible)

CREATE TABLE IF NOT EXISTS service_cities (
  id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  service_id int(11) NOT NULL,

  city_name VARCHAR(191) NOT NULL,
  title VARCHAR(255) NULL,
  slug VARCHAR(191) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_international TINYINT(1) NOT NULL DEFAULT 0,

  show_hero TINYINT(1) NOT NULL DEFAULT 1,
  show_process TINYINT(1) NOT NULL DEFAULT 1,
  show_tools TINYINT(1) NOT NULL DEFAULT 1,
  show_mobile_apps TINYINT(1) NOT NULL DEFAULT 1,
  show_faqs TINYINT(1) NOT NULL DEFAULT 1,

  hero_label VARCHAR(255) NULL,
  hero_title TEXT NULL,
  hero_description MEDIUMTEXT NULL,
  hero_cta_text VARCHAR(255) NULL,
  hero_main_image VARCHAR(500) NULL,

  approach_image VARCHAR(500) NULL,
  process_label VARCHAR(255) NULL,
  process_title VARCHAR(255) NULL,
  approach_list LONGTEXT NULL,

  faqs LONGTEXT NULL,

  meta_title VARCHAR(255) NULL,
  meta_description TEXT NULL,
  meta_schema MEDIUMTEXT NULL,
  head_tag_manager MEDIUMTEXT NULL,
  body_tag_manager MEDIUMTEXT NULL,

  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_service_city_slug (service_id, slug),
  INDEX idx_service_cities_service (service_id),
  CONSTRAINT fk_service_cities_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
