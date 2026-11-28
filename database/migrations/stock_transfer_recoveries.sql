-- Recovered stock after an approved physical loss is a separate, independently
-- approved event. The original approved loss, missing report and dispatch stay intact.
-- Only approval adds a positive TRANSFER_IN and raises received_quantity.
CREATE TABLE IF NOT EXISTS stock_transfer_recoveries (
  recovery_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loss_case_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  expected_received_quantity DECIMAL(12,3) NOT NULL,
  evidence_reference VARCHAR(160) NOT NULL,
  report_reason VARCHAR(500) NOT NULL,
  reported_by_user_id BIGINT UNSIGNED NOT NULL,
  reported_at DATETIME NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  movement_id BIGINT UNSIGNED NULL,
  pending_case_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status='PENDING' THEN loss_case_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (recovery_id),
  UNIQUE KEY uq_pending_transfer_recovery (pending_case_id),
  UNIQUE KEY uq_transfer_recovery_movement (movement_id),
  KEY idx_recovery_case_time (loss_case_id, reported_at),
  KEY idx_recovery_status_time (status, reported_at),
  CONSTRAINT fk_recovery_loss_case FOREIGN KEY (loss_case_id)
    REFERENCES stock_transfer_loss_cases(loss_case_id),
  CONSTRAINT fk_recovery_reporter FOREIGN KEY (reported_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT fk_recovery_reviewer FOREIGN KEY (reviewed_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT fk_recovery_movement FOREIGN KEY (movement_id)
    REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT chk_recovery_positive CHECK (quantity > 0),
  CONSTRAINT chk_recovery_expected_nonnegative CHECK (expected_received_quantity >= 0),
  CONSTRAINT chk_recovery_approved_movement CHECK (
    (status='APPROVED' AND movement_id IS NOT NULL AND reviewed_by_user_id IS NOT NULL)
    OR (status IN ('PENDING','REJECTED') AND movement_id IS NULL)
  )
) ENGINE=InnoDB;
