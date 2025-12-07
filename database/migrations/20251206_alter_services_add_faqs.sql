ALTER TABLE services
    ADD COLUMN IF NOT EXISTS faqs LONGTEXT NULL AFTER mobile_apps;

UPDATE services
SET faqs = '[]'
WHERE faqs IS NULL OR faqs = '';
