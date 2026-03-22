-- Add tag manager columns to portfolios table (MySQL-compatible, idempotent).

SET @add_portfolios_head := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'portfolios'
        AND COLUMN_NAME = 'head_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE portfolios ADD COLUMN head_tag_manager MEDIUMTEXT NULL AFTER meta_schema'
  )
);
PREPARE stmt FROM @add_portfolios_head;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_portfolios_body := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'portfolios'
        AND COLUMN_NAME = 'body_tag_manager'
    ),
    'SELECT 1',
    'ALTER TABLE portfolios ADD COLUMN body_tag_manager MEDIUMTEXT NULL AFTER head_tag_manager'
  )
);
PREPARE stmt2 FROM @add_portfolios_body;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
