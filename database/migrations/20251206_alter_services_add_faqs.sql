-- Add services.faqs if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'services'
    AND COLUMN_NAME = 'faqs'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE services ADD COLUMN faqs LONGTEXT NULL AFTER mobile_apps;',
  'DO 0;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE services
SET faqs = '[]'
WHERE faqs IS NULL OR faqs = '';
