-- Add tag manager columns to blog_topics table (MySQL-compatible, idempotent).

SET @add_blog_topics_head := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blog_topics'
        AND COLUMN_NAME = 'head_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE blog_topics ADD COLUMN head_tag_manager MEDIUMTEXT NULL AFTER meta_schema'
  )
);
PREPARE stmt FROM @add_blog_topics_head;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_blog_topics_body := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'blog_topics'
        AND COLUMN_NAME = 'body_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE blog_topics ADD COLUMN body_tag_manager MEDIUMTEXT NULL AFTER head_tag_manager'
  )
);
PREPARE stmt2 FROM @add_blog_topics_body;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
