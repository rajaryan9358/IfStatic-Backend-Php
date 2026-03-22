CREATE TABLE IF NOT EXISTS portfolio_service_tab_seo_meta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_alias VARCHAR(128) NOT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description TEXT NULL,
  meta_schema MEDIUMTEXT NULL,
  head_tag_manager MEDIUMTEXT NULL,
  body_tag_manager MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_portfolio_service_tab_seo_meta_service_alias (service_alias)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
