CREATE TABLE IF NOT EXISTS push_subscriptions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  endpoint_hash CHAR(64) NOT NULL,
  endpoint TEXT NOT NULL,
  p256dh TEXT NOT NULL,
  auth TEXT NOT NULL,
  user_agent VARCHAR(500) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_endpoint_hash (endpoint_hash),
  KEY idx_active_updated (is_active, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS content_snapshots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section VARCHAR(40) NOT NULL,
  item_key VARCHAR(190) NOT NULL DEFAULT 'main',
  source_url TEXT NOT NULL,
  content_hash CHAR(64) NOT NULL,
  title VARCHAR(255) NULL,
  excerpt TEXT NULL,
  image_url TEXT NULL,
  published_at VARCHAR(80) NULL,
  payload_json LONGTEXT NULL,
  last_seen_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_section_item_hash (section, item_key, content_hash),
  KEY idx_section_item_latest (section, item_key, id),
  KEY idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notification_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  section VARCHAR(40) NOT NULL,
  item_key VARCHAR(190) NULL,
  content_hash CHAR(64) NULL,
  source_url TEXT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  sent_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_notification_dedupe (section, item_key, content_hash),
  KEY idx_section_created (section, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

