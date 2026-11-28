-- Operational loss closure for inventory that was already debited at dispatch.
-- No second stock adjustment is made on approval: the missing quantity never
-- reached the destination branch. Physical evidence is referenced, not uploaded.
ALTER TABLE inventory_transfer_items
  MODIFY item_status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED','LOSS_CLOSED')
  NOT NULL DEFAULT 'IN_TRANSIT';
ALTER TABLE inventory_transfers
  MODIFY status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED','LOSS_CLOSED')
  NOT NULL DEFAULT 'IN_TRANSIT';

CREATE TABLE IF NOT EXISTS stock_transfer_loss_cases (
  loss_case_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_transfer_item_id BIGINT UNSIGNED NOT NULL,
  missing_audit_event_id BIGINT UNSIGNED NOT NULL,
  received_at_report DECIMAL(12,3) NOT NULL,
  missing_quantity DECIMAL(12,3) NOT NULL,
  evidence_reference VARCHAR(160) NOT NULL,
  report_reason VARCHAR(500) NOT NULL,
  reported_by_user_id BIGINT UNSIGNED NOT NULL,
  reported_at DATETIME NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  review_reason VARCHAR(500) NULL,
  active_item_id BIGINT UNSIGNED GENERATED ALWAYS AS (
    CASE WHEN status IN ('PENDING','APPROVED') THEN inventory_transfer_item_id ELSE NULL END
  ) STORED,
  PRIMARY KEY (loss_case_id),
  UNIQUE KEY uq_active_transfer_loss (active_item_id),
  KEY idx_loss_item_time (inventory_transfer_item_id, reported_at),
  KEY idx_loss_status_time (status, reported_at),
  CONSTRAINT fk_loss_transfer_item FOREIGN KEY (inventory_transfer_item_id)
    REFERENCES inventory_transfer_items(inventory_transfer_item_id),
  CONSTRAINT fk_loss_missing_event FOREIGN KEY (missing_audit_event_id)
    REFERENCES audit_events(audit_event_id),
  CONSTRAINT fk_loss_reported_by FOREIGN KEY (reported_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT fk_loss_reviewed_by FOREIGN KEY (reviewed_by_user_id)
    REFERENCES users(user_id),
  CONSTRAINT chk_loss_positive CHECK (missing_quantity > 0),
  CONSTRAINT chk_loss_received_nonnegative CHECK (received_at_report >= 0)
) ENGINE=InnoDB;
