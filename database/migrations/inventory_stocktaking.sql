-- Monthly branch-wide physical stocktaking. This is an audited COUNT and sign-off,
-- never a silent replacement for approved inventory adjustments.
CREATE TABLE IF NOT EXISTS inventory_stocktakes (
  stocktake_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  period_ym CHAR(7) NOT NULL,
  status ENUM('OPEN','PENDING','SIGNED_OFF','CANCELLED') NOT NULL DEFAULT 'OPEN',
  opened_by_user_id BIGINT UNSIGNED NOT NULL,
  opened_at DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  signed_by_user_id BIGINT UNSIGNED NULL,
  signed_at DATETIME NULL,
  decision_note VARCHAR(500) NULL,
  active_branch_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status IN ('OPEN','PENDING') THEN branch_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (stocktake_id),
  UNIQUE KEY uq_active_stocktake_branch (active_branch_id),
  KEY idx_stocktake_branch_period (branch_id,period_ym,stocktake_id),
  CONSTRAINT fk_stocktake_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_stocktake_opener FOREIGN KEY (opened_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_stocktake_signer FOREIGN KEY (signed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_stocktake_period CHECK (period_ym REGEXP '^[0-9]{4}-(0[1-9]|1[0-2])$'),
  CONSTRAINT chk_stocktake_signed CHECK (
    (status='SIGNED_OFF' AND signed_by_user_id IS NOT NULL AND signed_at IS NOT NULL)
    OR (status<>'SIGNED_OFF' AND signed_by_user_id IS NULL AND signed_at IS NULL)
  )
) ENGINE=InnoDB;

-- Every item stocked by the branch (including inactive SKUs) is snapshotted
-- at opening; counts and re-counts preserve evidence through audit_events.
CREATE TABLE IF NOT EXISTS inventory_stocktake_lines (
  stocktake_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  opening_on_hand DECIMAL(12,3) NOT NULL,
  opening_reserved DECIMAL(12,3) NOT NULL,
  opening_movement_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
  counted_on_hand DECIMAL(12,3) NULL,
  counted_expected_on_hand DECIMAL(12,3) NULL,
  counted_reserved DECIMAL(12,3) NULL,
  counted_movement_id BIGINT UNSIGNED NULL,
  counted_by_user_id BIGINT UNSIGNED NULL,
  counted_at DATETIME NULL,
  count_note VARCHAR(500) NULL,
  PRIMARY KEY (stocktake_id,part_id),
  KEY idx_stocktake_lines_part (part_id,stocktake_id),
  CONSTRAINT fk_stocktake_line_batch FOREIGN KEY (stocktake_id)
    REFERENCES inventory_stocktakes(stocktake_id),
  CONSTRAINT fk_stocktake_line_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT fk_stocktake_line_counter FOREIGN KEY (counted_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT chk_stocktake_opening_nonnegative CHECK (
    opening_on_hand>=0 AND opening_reserved>=0
  ),
  CONSTRAINT chk_stocktake_count_complete CHECK (
    (counted_on_hand IS NULL AND counted_expected_on_hand IS NULL
      AND counted_reserved IS NULL AND counted_movement_id IS NULL
      AND counted_by_user_id IS NULL AND counted_at IS NULL AND count_note IS NULL)
    OR (counted_on_hand>=0 AND counted_expected_on_hand>=0 AND counted_reserved>=0
      AND counted_movement_id IS NOT NULL AND counted_by_user_id IS NOT NULL
      AND counted_at IS NOT NULL AND count_note IS NOT NULL)
  )
) ENGINE=InnoDB;
