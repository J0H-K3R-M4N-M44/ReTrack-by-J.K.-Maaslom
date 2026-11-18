-- Existing databases: back up first, then select the RepairTrack database.
-- Safe to rerun. This feature stores counter-cash collections only.
CREATE TABLE IF NOT EXISTS repair_bill_payments (
  payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('COUNTER_CASH') NOT NULL DEFAULT 'COUNTER_CASH',
  amount DECIMAL(12,2) NOT NULL,
  request_key CHAR(64) NOT NULL,
  received_by_user_id BIGINT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  UNIQUE KEY uq_bill_payment_request (request_key),
  KEY idx_bill_payment_time (bill_id, received_at),
  CONSTRAINT fk_payment_bill FOREIGN KEY (bill_id) REFERENCES repair_bills(bill_id),
  CONSTRAINT fk_payment_collector FOREIGN KEY (received_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_bill_payment_amount CHECK (amount > 0)
) ENGINE=InnoDB;
