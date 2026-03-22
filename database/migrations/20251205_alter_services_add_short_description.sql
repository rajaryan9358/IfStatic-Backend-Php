-- Add services.short_description if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'services'
    AND COLUMN_NAME = 'short_description'
);

SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE services ADD COLUMN short_description TEXT NULL AFTER alias;',
  'DO 0;'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE services
SET short_description = SUBSTRING(COALESCE(hero_description, ''), 1, 500)
WHERE short_description IS NULL OR short_description = '';
