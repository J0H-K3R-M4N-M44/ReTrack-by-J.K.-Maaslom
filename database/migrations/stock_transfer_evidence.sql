-- Private, append-only evidence for independently reviewed stock loss/recovery cases.
-- Files are stored outside the project/web root, never in MySQL or public URLs.
CREATE TABLE IF NOT EXISTS stock_transfer_evidence (
  evidence_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loss_case_id BIGINT UNSIGNED NULL,
  recovery_id BIGINT UNSIGNED NULL,
  stored_name VARCHAR(52) NOT NULL,
  original_filename VARCHAR(150) NOT NULL,
  mime_type ENUM('image/jpeg','image/png','application/pdf') NOT NULL,
  file_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL,
  PRIMARY KEY (evidence_id),
  UNIQUE KEY uq_stock_evidence_stored_name (stored_name),
  KEY idx_stock_evidence_loss (loss_case_id, uploaded_at),
  KEY idx_stock_evidence_recovery (recovery_id, uploaded_at),
  CONSTRAINT fk_stock_evidence_loss FOREIGN KEY (loss_case_id)
    REFERENCES stock_transfer_loss_cases(loss_case_id),
  CONSTRAINT fk_stock_evidence_recovery FOREIGN KEY (recovery_id)
    REFERENCES stock_transfer_recoveries(recovery_id),
  CONSTRAINT fk_stock_evidence_user FOREIGN KEY (uploaded_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT chk_stock_evidence_one_case CHECK (
    (loss_case_id IS NOT NULL AND recovery_id IS NULL)
    OR (loss_case_id IS NULL AND recovery_id IS NOT NULL)
  ),
  CONSTRAINT chk_stock_evidence_size CHECK (file_bytes > 0 AND file_bytes <= 5242880)
) ENGINE=InnoDB;
