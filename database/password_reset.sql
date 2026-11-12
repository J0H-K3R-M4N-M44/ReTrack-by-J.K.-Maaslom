-- Apply once to an existing RepairTrack database before enabling email recovery.
-- Tokens are stored as SHA-256 hashes and expire after 30 minutes in app code.
-- created_at is supplied in UTC by PHP so request throttling stays timezone-safe.
CREATE TABLE IF NOT EXISTS password_reset_tokens (
  reset_token_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (reset_token_id),
  UNIQUE KEY uq_password_reset_token_hash (token_hash),
  KEY idx_password_reset_user_expiry (user_id, expires_at),
  CONSTRAINT fk_password_reset_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;
