ALTER TABLE services
    ADD COLUMN IF NOT EXISTS short_description TEXT NULL AFTER alias;

UPDATE services
SET short_description = SUBSTRING(COALESCE(hero_description, ''), 1, 500)
WHERE short_description IS NULL OR short_description = '';
