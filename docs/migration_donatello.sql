CREATE TABLE IF NOT EXISTS app_settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO app_settings(setting_key, setting_value) VALUES
('premium_price_uah','50.00'),('premium_duration_days','30'),('premium_currency','UAH'),('premium_enabled','1')
ON DUPLICATE KEY UPDATE setting_key=VALUES(setting_key);

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS is_premium TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS subscription_expires_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS donatello_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  match_code VARCHAR(40) NOT NULL UNIQUE,
  expected_amount DECIMAL(12,2) NOT NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'UAH',
  status VARCHAR(30) NOT NULL DEFAULT 'pending',
  donatello_pub_id VARCHAR(120) DEFAULT NULL,
  donor_name VARCHAR(255) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  paid_amount DECIMAL(12,2) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  matched_at DATETIME NULL,
  premium_activated_at DATETIME NULL,
  INDEX(user_id), INDEX(status), INDEX(donatello_pub_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
