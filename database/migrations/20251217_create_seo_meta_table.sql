CREATE TABLE IF NOT EXISTS seo_meta (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  page VARCHAR(32) NOT NULL,
  meta_type VARCHAR(32) NOT NULL,
  meta_data MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_page_meta_type (page, meta_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
