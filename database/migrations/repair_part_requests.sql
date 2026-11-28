-- Internal technician-to-manager repair-part needs. Does not purchase, reserve or move stock.
CREATE TABLE IF NOT EXISTS repair_part_requests (
  part_request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_description VARCHAR(160) NOT NULL,
  quantity DECIMAL(7,3) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  status ENUM('OPEN','ACKNOWLEDGED','DECLINED','RESOLVED') NOT NULL DEFAULT 'OPEN',
  requested_by_user_id BIGINT UNSIGNED NOT NULL,
  reviewed_by_user_id BIGINT UNSIGNED NULL,
  review_note VARCHAR(500) NULL,
  reviewed_at DATETIME NULL,
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note VARCHAR(500) NULL,
  resolved_at DATETIME NULL,
  version_no INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (part_request_id),
  KEY idx_part_request_device (ticket_device_id, created_at),
  KEY idx_part_request_branch (branch_id, status, created_at),
  CONSTRAINT fk_part_request_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_part_request_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_part_request_requester FOREIGN KEY (requested_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_part_request_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_part_request_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_part_request_quantity CHECK (quantity > 0),
  CONSTRAINT chk_part_request_status CHECK (
    (status = 'OPEN' AND reviewed_by_user_id IS NULL AND reviewed_at IS NULL
      AND resolved_at IS NULL AND resolved_by_user_id IS NULL)
    OR (status IN ('ACKNOWLEDGED','DECLINED') AND reviewed_by_user_id IS NOT NULL
      AND reviewed_at IS NOT NULL AND resolved_at IS NULL AND resolved_by_user_id IS NULL)
    OR (status = 'RESOLVED' AND reviewed_by_user_id IS NOT NULL
      AND reviewed_at IS NOT NULL AND resolved_by_user_id IS NOT NULL AND resolved_at IS NOT NULL)
  )
) ENGINE=InnoDB;
