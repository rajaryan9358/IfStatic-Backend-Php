CREATE TABLE IF NOT EXISTS portfolio_home_order (
  id INT(11) NOT NULL AUTO_INCREMENT,
  portfolio_id INT(11) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_portfolio_home_order_portfolio (portfolio_id),
  KEY idx_portfolio_home_order_sort_order (sort_order),
  CONSTRAINT fk_portfolio_home_order_portfolio
    FOREIGN KEY (portfolio_id) REFERENCES portfolios(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;