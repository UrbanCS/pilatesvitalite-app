CREATE TABLE IF NOT EXISTS push_subscription_preferences (
  subscription_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(40) NOT NULL,
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (subscription_id, category),
  KEY idx_pref_category_enabled (category, is_enabled, subscription_id),
  CONSTRAINT fk_push_pref_subscription
    FOREIGN KEY (subscription_id) REFERENCES push_subscriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
