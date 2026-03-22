-- Add portfolios.show_download_section if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'portfolios'
    AND COLUMN_NAME = 'show_download_section'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE portfolios ADD COLUMN show_download_section TINYINT(1) NOT NULL DEFAULT 1 AFTER show_in_home;',
  'DO 0;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add portfolios.cta_title if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'portfolios'
    AND COLUMN_NAME = 'cta_title'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE portfolios ADD COLUMN cta_title VARCHAR(255) NULL AFTER download_description;',
  'DO 0;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
