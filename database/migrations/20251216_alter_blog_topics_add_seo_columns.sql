-- Add blog_topics SEO columns if missing (MySQL-compatible)
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blog_topics'
    AND COLUMN_NAME = 'meta_title'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blog_topics ADD COLUMN meta_title VARCHAR(255) NULL AFTER description;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blog_topics'
    AND COLUMN_NAME = 'meta_description'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blog_topics ADD COLUMN meta_description TEXT NULL AFTER meta_title;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'blog_topics'
    AND COLUMN_NAME = 'meta_schema'
);

SET @sql := IF(@col_exists = 0, 'ALTER TABLE blog_topics ADD COLUMN meta_schema TEXT NULL AFTER meta_description;', 'DO 0;');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
