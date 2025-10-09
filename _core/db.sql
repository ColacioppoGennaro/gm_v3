-- users: già esiste (user_id PK)
CREATE TABLE IF NOT EXISTS oauth_accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(32) NOT NULL,
  refresh_token TEXT NOT NULL,
  scope TEXT NOT NULL,
  access_token TEXT NULL,
  access_expires_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_provider (user_id, provider)
);

CREATE TABLE IF NOT EXISTS calendars (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(32) NOT NULL,
  google_calendar_id VARCHAR(256) NOT NULL,
  summary VARCHAR(256) NULL,
  color VARCHAR(32) NULL,
  primary_flag TINYINT(1) DEFAULT 0,
  sync_enabled TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_cal (user_id, provider, google_calendar_id)
);

CREATE TABLE IF NOT EXISTS sync_state (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(32) NOT NULL,
  google_calendar_id VARCHAR(256) NOT NULL,
  channel_id VARCHAR(128) NOT NULL,
  resource_id VARCHAR(128) NOT NULL,
  sync_token VARCHAR(512) NULL,
  channel_expire_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_channel (provider, channel_id),
  KEY idx_user_cal (user_id, google_calendar_id)
);

CREATE TABLE IF NOT EXISTS events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  provider VARCHAR(32) NOT NULL,
  google_event_id VARCHAR(256) NULL,
  calendar_id INT NULL,
  payload_json JSON NOT NULL,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  source VARCHAR(16) NOT NULL DEFAULT 'google'
);
