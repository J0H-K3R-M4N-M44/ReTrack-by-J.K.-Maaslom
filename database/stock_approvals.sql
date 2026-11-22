-- Original quarantine movements remain immutable. A disposition is a single final decision
-- made by a *different* manager after physically verifying the isolated part.
CREATE TABLE IF NOT EXISTS stock_quarantine_dispositions (
  disposition_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quarantine_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('RELEASE','SCRAP') NOT NULL,
  release_movement_id BIGINT UNSIGNED NULL,
  approved_by_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  approved_at DATETIME NOT NULL,
  PRIMARY KEY (disposition_id),
  UNIQUE KEY uq_quarantine_final_decision (quarantine_id),
  UNIQUE KEY uq_quarantine_release_movement (release_movement_id),
  CONSTRAINT fk_disposition_quarantine FOREIGN KEY (quarantine_id) REFERENCES stock_quarantines(quarantine_id),
  CONSTRAINT fk_disposition_movement FOREIGN KEY (release_movement_id) REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT fk_disposition_approver FOREIGN KEY (approved_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_disposition_release_movement CHECK (
    (decision = 'RELEASE' AND release_movement_id IS NOT NULL)
    OR (decision = 'SCRAP' AND release_movement_id IS NULL)
  )
) ENGINE=InnoDB;

-- Large physical-count differences are requests, not immediate ledger changes.
-- A pending request is unique per branch/part; old decisions stay as audit evidence.
CREATE TABLE IF NOT EXISTS stock_count_reviews (
  stock_count_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  expected_on_hand DECIMAL(12,3) NOT NULL,
  expected_reserved DECIMAL(12,3) NOT NULL,
  expected_movement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  target_on_hand DECIMAL(12,3) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  adjustment_movement_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  pending_part_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status = 'PENDING' THEN part_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (stock_count_review_id),
  UNIQUE KEY uq_pending_stock_count (branch_id, pending_part_id),
  UNIQUE KEY uq_stock_count_adjustment (adjustment_movement_id),
  KEY idx_stock_count_branch_status (branch_id, status, created_at),
  CONSTRAINT fk_stock_count_branch_part FOREIGN KEY (branch_id,part_id) REFERENCES branch_inventory(branch_id,part_id),
  CONSTRAINT fk_stock_count_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_stock_count_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_stock_count_movement FOREIGN KEY (adjustment_movement_id) REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT chk_stock_count_nonnegative CHECK (expected_on_hand >= 0
    AND expected_reserved >= 0 AND target_on_hand >= 0)
) ENGINE=InnoDB;
