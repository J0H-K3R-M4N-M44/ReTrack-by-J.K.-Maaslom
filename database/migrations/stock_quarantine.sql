-- A quarantined part is physically separated and never part of usable stock.
-- SHELF: its negative ADJUSTMENT ledger movement removes it from on-hand stock.
-- INSTALLATION: an already-issued/installed part is isolated without double-debiting stock.
CREATE TABLE IF NOT EXISTS stock_quarantines (
  quarantine_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  source_type ENUM('SHELF','INSTALLATION') NOT NULL,
  adjustment_movement_id BIGINT UNSIGNED NULL,
  original_use_movement_id BIGINT UNSIGNED NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  reason VARCHAR(500) NOT NULL,
  recorded_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (quarantine_id),
  UNIQUE KEY uq_stock_quarantine_shelf_movement (adjustment_movement_id),
  UNIQUE KEY uq_stock_quarantine_original_use (original_use_movement_id),
  KEY idx_quarantine_branch_part (branch_id, part_id, created_at),
  KEY idx_quarantine_ticket (ticket_device_id),
  CONSTRAINT fk_quarantine_branch_part FOREIGN KEY (branch_id, part_id) REFERENCES branch_inventory(branch_id, part_id),
  CONSTRAINT fk_quarantine_adjustment FOREIGN KEY (adjustment_movement_id) REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT fk_quarantine_original_use FOREIGN KEY (original_use_movement_id) REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT fk_quarantine_ticket FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_quarantine_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_quarantine_positive CHECK (quantity > 0),
  CONSTRAINT chk_quarantine_source CHECK (
    (source_type = 'SHELF' AND adjustment_movement_id IS NOT NULL
      AND original_use_movement_id IS NULL AND ticket_device_id IS NULL)
    OR (source_type = 'INSTALLATION' AND adjustment_movement_id IS NULL
      AND original_use_movement_id IS NOT NULL AND ticket_device_id IS NOT NULL)
  )
) ENGINE=InnoDB;
