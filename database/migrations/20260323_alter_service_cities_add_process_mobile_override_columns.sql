ALTER TABLE service_cities
  ADD COLUMN use_process_override TINYINT(1) NOT NULL DEFAULT 0 AFTER show_process,
  ADD COLUMN use_mobile_apps_override TINYINT(1) NOT NULL DEFAULT 0 AFTER show_mobile_apps,
  ADD COLUMN mobile_apps_label VARCHAR(255) NULL AFTER process_title,
  ADD COLUMN mobile_apps_title VARCHAR(255) NULL AFTER mobile_apps_label,
  ADD COLUMN mobile_apps LONGTEXT NULL AFTER approach_list;