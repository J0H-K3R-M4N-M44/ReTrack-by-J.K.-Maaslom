-- Older estimates remain payable without inventing historical installations.
SET @stock_column_sql = IF(EXISTS(SELECT 1 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'repair_quote_items' AND column_name = 'tracks_branch_stock'),
  'DO 0', 'ALTER TABLE repair_quote_items ADD COLUMN tracks_branch_stock TINYINT(1) NOT NULL DEFAULT 0');
PREPARE stock_column_change FROM @stock_column_sql;
EXECUTE stock_column_change;
DEALLOCATE PREPARE stock_column_change;

CREATE TABLE IF NOT EXISTS stock_reservations (
  reservation_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote_item_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  reserved_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stock_hold_item (quote_item_id),
  KEY idx_stock_hold_shelf (branch_id, part_id),
  FOREIGN KEY (quote_item_id) REFERENCES repair_quote_items(quote_item_id),
  FOREIGN KEY (branch_id, part_id) REFERENCES branch_inventory(branch_id, part_id),
  FOREIGN KEY (reserved_by) REFERENCES users(user_id),
  CHECK (quantity > 0)
) ENGINE=InnoDB;