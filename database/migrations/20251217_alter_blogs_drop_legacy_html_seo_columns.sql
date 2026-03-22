-- Drop legacy SEO columns from blogs table.
-- Uses information_schema checks so the migration is safe to run even if columns already removed.
-- Uses DO 0 as a no-op to avoid returning result sets (prevents PDO unbuffered query issues).

SET @drop_html_title := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blogs'
        AND COLUMN_NAME = 'html_title'
    ),
    'ALTER TABLE blogs DROP COLUMN html_title',
    'DO 0'
  )
);
PREPARE stmt FROM @drop_html_title;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_html_meta_tags := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blogs'
        AND COLUMN_NAME = 'html_meta_tags'
    ),
    'ALTER TABLE blogs DROP COLUMN html_meta_tags',
    'DO 0'
  )
);
PREPARE stmt2 FROM @drop_html_meta_tags;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
