-- Historical PHP cost valuations for approved physical transfer loss cases.
-- This register is NOT a cash receipt, refund, general-ledger entry or stock movement.
-- Every submission records a frozen quantity/cost snapshot; revisions append rows.
CREATE TABLE IF NOT EXISTS stock_transfer_loss_valuations (
  valuation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  loss_case_id BIGINT UNSIGNED NOT NULL,
  revision_no INT UNSIGNED NOT NULL,
  remaining_quantity DECIMAL(12,3) NOT NULL,
  unit_cost_php DECIMAL(12,2) NOT NULL,
  exposure_php DECIMAL(15,2) NOT NULL,
  cost_basis ENUM('SUPPLIER_INVOICE','PURCHASE_RECEIPT','VERIFIED_COST_RECORD','OTHER_VERIFIED') NOT NULL,
  cost_reference VARCHAR(160) NOT NULL,
  report_reason VARCHAR(500) NOT NULL,
  prepared_by_user_id BIGINT UNSIGNED NOT NULL,
  prepared_at DATETIME NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  pending_loss_case_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status='PENDING' THEN loss_case_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (valuation_id),
  UNIQUE KEY uq_loss_valuation_revision (loss_case_id, revision_no),
  UNIQUE KEY uq_pending_loss_valuation (pending_loss_case_id),
  KEY idx_loss_valuation_latest (loss_case_id, valuation_id),
  KEY idx_loss_valuation_status (status, prepared_at),
  CONSTRAINT fk_loss_valuation_case FOREIGN KEY (loss_case_id)
    REFERENCES stock_transfer_loss_cases(loss_case_id),
  CONSTRAINT fk_loss_valuation_preparer FOREIGN KEY (prepared_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT fk_loss_valuation_reviewer FOREIGN KEY (reviewed_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT chk_loss_valuation_remaining CHECK (remaining_quantity >= 0),
  CONSTRAINT chk_loss_valuation_cost CHECK (unit_cost_php > 0 AND unit_cost_php <= 1000000),
  CONSTRAINT chk_loss_valuation_exposure CHECK (exposure_php >= 0),
  CONSTRAINT chk_loss_valuation_currency CHECK (currency='PHP'),
  CONSTRAINT chk_loss_valuation_review_state CHECK (
    (status='PENDING' AND reviewed_by_user_id IS NULL AND reviewed_at IS NULL)
    OR (status IN ('APPROVED','REJECTED') AND reviewed_by_user_id IS NOT NULL
      AND reviewed_at IS NOT NULL)
  )
) ENGINE=InnoDB;
