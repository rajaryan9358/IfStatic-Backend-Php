-- Add blogs SEO columns if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blogs'
    AND COLUMN_NAME = 'meta_title'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blogs ADD COLUMN meta_title VARCHAR(255) NULL AFTER html_meta_tags;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blogs'
    AND COLUMN_NAME = 'meta_description'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blogs ADD COLUMN meta_description TEXT NULL AFTER meta_title;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blogs'
    AND COLUMN_NAME = 'meta_schema'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blogs ADD COLUMN meta_schema TEXT NULL AFTER meta_description;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE blogs
SET meta_title = COALESCE(NULLIF(meta_title, ''), NULLIF(title, '')) ,
    meta_description = COALESCE(NULLIF(meta_description, ''), NULLIF(excerpt, ''))
WHERE (meta_title IS NULL OR meta_title = '' OR meta_description IS NULL OR meta_description = '');
