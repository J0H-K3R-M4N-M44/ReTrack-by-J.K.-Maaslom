-- Reviewed acquisition-cost evidence for one part at one purchasing/dispatch branch.
-- Costs are attestations of supplier documents, not actual purchase ledger postings.
CREATE TABLE IF NOT EXISTS part_acquisition_cost_records (
  cost_record_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  unit_cost_php DECIMAL(12,2) NOT NULL,
  source_type ENUM('SUPPLIER_INVOICE','PURCHASE_RECEIPT','OTHER_VERIFIED') NOT NULL,
  supplier_name VARCHAR(120) NOT NULL,
  document_reference VARCHAR(160) NOT NULL,
  document_date DATE NOT NULL,
  explanation VARCHAR(500) NOT NULL,
  prepared_by_user_id BIGINT UNSIGNED NOT NULL,
  prepared_at DATETIME NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  pending_branch_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status='PENDING' THEN branch_id ELSE NULL END
  ) STORED,
  pending_part_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status='PENDING' THEN part_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (cost_record_id),
  UNIQUE KEY uq_pending_part_acquisition_cost (pending_branch_id,pending_part_id),
  KEY idx_part_acquisition_history (branch_id,part_id,cost_record_id),
  KEY idx_part_acquisition_status (status,prepared_at),
  CONSTRAINT fk_part_cost_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_part_cost_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT fk_part_cost_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_part_cost_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_part_cost_positive CHECK (unit_cost_php > 0 AND unit_cost_php <= 1000000),
  CONSTRAINT chk_part_cost_decision CHECK (
    (status='PENDING' AND reviewed_by_user_id IS NULL AND reviewed_at IS NULL)
    OR (status IN ('APPROVED','REJECTED') AND reviewed_by_user_id IS NOT NULL AND reviewed_at IS NOT NULL)
  )
) ENGINE=InnoDB;

-- Link new monetary valuations to exact immutable, branch-scoped approved cost evidence.
-- Older, manually attested loss valuations remain valid with NULL link.
ALTER TABLE stock_transfer_loss_valuations
  ADD COLUMN part_acquisition_cost_id BIGINT UNSIGNED NULL,
  ADD KEY idx_loss_valuation_part_cost (part_acquisition_cost_id),
  ADD CONSTRAINT fk_loss_valuation_part_cost FOREIGN KEY (part_acquisition_cost_id)
    REFERENCES part_acquisition_cost_records(cost_record_id);
