CREATE TABLE IF NOT EXISTS repair_bill_changes (
  bill_change_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_id BIGINT UNSIGNED NOT NULL,
  change_type ENUM('CORRECTION','REFUND') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (bill_change_id),
  KEY idx_bill_changes_bill (bill_id, bill_change_id),
  CONSTRAINT fk_bill_changes_bill FOREIGN KEY (bill_id) REFERENCES repair_bills (bill_id),
  CONSTRAINT fk_bill_changes_actor FOREIGN KEY (actor_user_id) REFERENCES users (user_id),
  CONSTRAINT chk_bill_changes_amount CHECK (amount >= 0 AND (change_type = 'CORRECTION' OR amount > 0))
) ENGINE=InnoDB;
