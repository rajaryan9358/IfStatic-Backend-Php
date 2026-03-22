-- Add tag manager columns to blogs table (MySQL-compatible, idempotent).

SET @add_blogs_head := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blogs'
        AND COLUMN_NAME = 'head_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE blogs ADD COLUMN head_tag_manager MEDIUMTEXT NULL AFTER meta_schema'
  )
);
PREPARE stmt FROM @add_blogs_head;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_blogs_body := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blogs'
        AND COLUMN_NAME = 'body_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE blogs ADD COLUMN body_tag_manager MEDIUMTEXT NULL AFTER head_tag_manager'
  )
);
PREPARE stmt2 FROM @add_blogs_body;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
