ALTER TABLE service_cities
  ADD COLUMN show_portfolios TINYINT(1) NOT NULL DEFAULT 1 AFTER show_faqs,
  ADD COLUMN show_testimonials TINYINT(1) NOT NULL DEFAULT 1 AFTER show_portfolios;
