-- RepairTrack local-demo database baseline
-- Target: MySQL 8.0.16+ / MariaDB version with compatible CHECK and JSON support.
-- Encoding: utf8mb4. Demo notifications are SIMULATED. No SMS/WhatsApp provider is called.
-- IMPORTANT: This is a starter schema, not production-hardened deployment configuration.

CREATE DATABASE IF NOT EXISTS repairtrack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE repairtrack;
SET NAMES utf8mb4;
SET time_zone = '+00:00'; -- Store timestamps in UTC; display local time in Asia/Manila in PHP.

CREATE TABLE IF NOT EXISTS branches (
  branch_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_code VARCHAR(20) NOT NULL,
  branch_name VARCHAR(120) NOT NULL,
  address TEXT NULL,
  phone VARCHAR(40) NULL,
  email VARCHAR(190) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (branch_id),
  UNIQUE KEY uq_branches_code (branch_code),
  KEY idx_branches_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  user_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('ADMIN','MANAGER','INTAKE_AGENT','TECHNICIAN','COURIER','VIEWER') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_branch_assignments (
  user_id BIGINT UNSIGNED NOT NULL,
  branch_id BIGINT UNSIGNED NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, branch_id),
  KEY idx_uba_branch (branch_id),
  CONSTRAINT fk_uba_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_uba_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
  customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  normalized_name VARCHAR(160) NOT NULL,
  customer_notes TEXT NULL COMMENT 'Internal notes only; never expose on public tracking page.',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  KEY idx_customers_normalized_name (normalized_name),
  KEY idx_customers_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_contacts (
  contact_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  contact_type ENUM('PHONE','EMAIL') NOT NULL,
  contact_value VARCHAR(190) NOT NULL,
  normalized_value VARCHAR(190) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  preferred_for_notifications TINYINT(1) NOT NULL DEFAULT 0,
  verified_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (contact_id),
  UNIQUE KEY uq_customer_contact (customer_id, contact_type, normalized_value),
  KEY idx_contact_search (contact_type, normalized_value),
  CONSTRAINT fk_contacts_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_devices (
  customer_device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  device_type VARCHAR(80) NOT NULL,
  make VARCHAR(100) NULL,
  model VARCHAR(160) NULL,
  specifications TEXT NULL,
  serial_number VARCHAR(190) NULL COMMENT 'Optional; do not force a fabricated placeholder.',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_device_id),
  KEY idx_customer_devices_customer (customer_id),
  KEY idx_customer_devices_serial (serial_number),
  CONSTRAINT fk_customer_devices_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS storage_locations (
  location_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NULL,
  location_code VARCHAR(40) NOT NULL,
  location_name VARCHAR(120) NOT NULL,
  location_type ENUM('BRANCH_BIN','WORKBENCH','TRANSIT','WITH_CUSTOMER','UNKNOWN') NOT NULL DEFAULT 'BRANCH_BIN',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (location_id),
  UNIQUE KEY uq_location_branch_code (branch_id, location_code),
  KEY idx_location_branch_type (branch_id, location_type),
  CONSTRAINT fk_locations_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_tickets (
  ticket_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_code VARCHAR(24) NULL COMMENT 'Public ticket code generated only when intake is issued.',
  record_stage ENUM('DRAFT','ISSUED','ABANDONED') NOT NULL DEFAULT 'DRAFT',
  case_state ENUM('OPEN','CLOSED') NULL,
  closure_outcome ENUM('ALL_COMPLETED','ALL_CANCELLED','MIXED_OUTCOME') NULL,
  customer_id BIGINT UNSIGNED NULL,
  intake_branch_id BIGINT UNSIGNED NOT NULL,
  accountable_branch_id BIGINT UNSIGNED NULL,
  accepted_by_user_id BIGINT UNSIGNED NOT NULL,
  arrival_method ENUM('WALK_IN','MAIL') NOT NULL,
  intake_date DATE NULL COMMENT 'Local intake date, set on ticket issuance using Asia/Manila rules.',
  tracking_token_hash CHAR(64) NULL COMMENT 'Store only a hash of the random public tracking token.',
  tracking_token_ciphertext TEXT NULL COMMENT 'Optional application-encrypted token to reprint the link; encryption key must be outside DB/source control.',
  terms_version VARCHAR(40) NULL,
  terms_accepted TINYINT(1) NOT NULL DEFAULT 0,
  terms_signature_path VARCHAR(500) NULL,
  terms_signed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at DATETIME NULL,
  closed_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_id),
  UNIQUE KEY uq_ticket_code (ticket_code),
  UNIQUE KEY uq_tracking_token_hash (tracking_token_hash),
  KEY idx_ticket_customer_date (customer_id, intake_date),
  KEY idx_ticket_branch_stage (intake_branch_id, record_stage, case_state),
  KEY idx_ticket_accountable_branch (accountable_branch_id, record_stage),
  CONSTRAINT fk_ticket_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT fk_ticket_intake_branch FOREIGN KEY (intake_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_ticket_accountable_branch FOREIGN KEY (accountable_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_ticket_accepted_by FOREIGN KEY (accepted_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_ticket_issue_fields CHECK (
    (record_stage <> 'ISSUED') OR
    (ticket_code IS NOT NULL AND customer_id IS NOT NULL AND accountable_branch_id IS NOT NULL AND issued_at IS NOT NULL AND intake_date IS NOT NULL AND case_state IS NOT NULL AND terms_accepted = 1 AND terms_signature_path IS NOT NULL AND terms_signed_at IS NOT NULL)
  )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_devices (
  ticket_device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  child_sequence SMALLINT UNSIGNED NOT NULL,
  item_code VARCHAR(32) NULL COMMENT 'Child identity, e.g. RT-ABC123-01; allocated at issue.',
  customer_device_id BIGINT UNSIGNED NULL,
  device_type_snapshot VARCHAR(80) NULL COMMENT 'Nullable while the intake record is a draft; required before issue.',
  make_snapshot VARCHAR(100) NULL,
  model_snapshot VARCHAR(160) NULL,
  specifications_snapshot TEXT NULL,
  serial_number_snapshot VARCHAR(190) NULL,
  alternative_identifiers TEXT NULL COMMENT 'Required when serial number is unavailable; per intake evidence.',
  reported_symptoms TEXT NULL,
  condition_notes TEXT NULL,
  accessory_checklist_completed TINYINT(1) NOT NULL DEFAULT 0,
  repair_status ENUM('DRAFT','RECEIVED','DIAGNOSING','AWAITING_CUSTOMER_APPROVAL','AWAITING_PARTS','IN_PROGRESS','TESTING','READY_FOR_PICKUP','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  public_summary TEXT NULL COMMENT 'Approved customer-facing summary only.',
  assigned_technician_id BIGINT UNSIGNED NULL,
  physical_location_id BIGINT UNSIGNED NULL,
  custody_state ENUM('AT_LOCATION','IN_TRANSIT','WITH_CUSTOMER','UNKNOWN') NOT NULL DEFAULT 'UNKNOWN',
  duplicate_fingerprint CHAR(64) NULL COMMENT 'HMAC fingerprint of normalized name + device identity + local intake date; no raw name in this field.',
  return_handover_at DATETIME NULL,
  return_handover_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_device_id),
  UNIQUE KEY uq_ticket_device_sequence (ticket_id, child_sequence),
  UNIQUE KEY uq_ticket_device_code (item_code),
  UNIQUE KEY uq_duplicate_fingerprint (duplicate_fingerprint),
  KEY idx_ticket_device_status (repair_status, updated_at),
  KEY idx_ticket_device_location (physical_location_id, custody_state),
  KEY idx_ticket_device_assignee (assigned_technician_id, repair_status),
  KEY idx_ticket_device_customer_device (customer_device_id),
  CONSTRAINT fk_td_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_td_customer_device FOREIGN KEY (customer_device_id) REFERENCES customer_devices(customer_device_id),
  CONSTRAINT fk_td_technician FOREIGN KEY (assigned_technician_id) REFERENCES users(user_id),
  CONSTRAINT fk_td_location FOREIGN KEY (physical_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_td_handover_by FOREIGN KEY (return_handover_by) REFERENCES users(user_id),
  CONSTRAINT chk_td_issued_fields CHECK (
    (repair_status = 'DRAFT') OR
    (device_type_snapshot IS NOT NULL AND CHAR_LENGTH(TRIM(device_type_snapshot)) > 0
     AND reported_symptoms IS NOT NULL AND CHAR_LENGTH(TRIM(reported_symptoms)) > 0
     AND condition_notes IS NOT NULL AND CHAR_LENGTH(TRIM(condition_notes)) > 0
     AND accessory_checklist_completed = 1
     AND ((serial_number_snapshot IS NOT NULL AND CHAR_LENGTH(TRIM(serial_number_snapshot)) > 0)
          OR (alternative_identifiers IS NOT NULL AND CHAR_LENGTH(TRIM(alternative_identifiers)) > 0)))
  )
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_accessory_items (
  accessory_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  accessory_name VARCHAR(120) NOT NULL,
  received TINYINT(1) NOT NULL DEFAULT 0,
  notes VARCHAR(500) NULL,
  recorded_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (accessory_item_id),
  KEY idx_accessory_ticket_device (ticket_device_id),
  CONSTRAINT fk_accessory_ticket_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_accessory_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_device_photos (
  photo_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  photo_category ENUM('OVERALL_CONDITION','DAMAGE_DETAIL','SERIAL_LABEL','ACCESSORIES','OTHER') NOT NULL,
  stored_path VARCHAR(500) NOT NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL,
  byte_size BIGINT UNSIGNED NOT NULL,
  sha256_hash CHAR(64) NOT NULL,
  uploaded_by_user_id BIGINT UNSIGNED NOT NULL,
  captured_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (photo_id),
  KEY idx_photos_ticket_device_category (ticket_device_id, photo_category),
  CONSTRAINT fk_photos_ticket_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_photos_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_device_acknowledgments (
  acknowledgment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  acknowledgment_type ENUM('CONDITION_ASSESSMENT') NOT NULL DEFAULT 'CONDITION_ASSESSMENT',
  customer_name_at_signing VARCHAR(160) NOT NULL,
  signature_path VARCHAR(500) NULL,
  policy_version VARCHAR(40) NOT NULL,
  accepted TINYINT(1) NOT NULL,
  signature_source ENUM('COUNTER_CAPTURE','CUSTOMER_PORTAL','SIMULATED_DEMO') NOT NULL DEFAULT 'COUNTER_CAPTURE',
  signed_at DATETIME NULL,
  recorded_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (acknowledgment_id),
  UNIQUE KEY uq_td_ack_version (ticket_device_id, acknowledgment_type, policy_version),
  CONSTRAINT fk_ack_ticket_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT chk_ack_accepted_signature CHECK (accepted = 0 OR (signature_path IS NOT NULL AND signed_at IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_intake_gate_events (
  gate_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  gate_code ENUM('CUSTOMER_MATCH','DEVICE_IDENTITY','SYMPTOMS','CONDITION_NOTES','ACCESSORY_CHECKLIST','REQUIRED_PHOTOS','CONDITION_SIGNOFF','TERMS_ACCEPTANCE','DUPLICATE_CHECK') NOT NULL,
  passed TINYINT(1) NOT NULL,
  details VARCHAR(1000) NULL,
  checked_by_user_id BIGINT UNSIGNED NOT NULL,
  checked_at DATETIME NOT NULL,
  PRIMARY KEY (gate_event_id),
  KEY idx_gate_ticket (ticket_id, checked_at),
  CONSTRAINT fk_gate_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_gate_ticket_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_gate_user FOREIGN KEY (checked_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_status_events (
  status_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status ENUM('DRAFT','RECEIVED','DIAGNOSING','AWAITING_CUSTOMER_APPROVAL','AWAITING_PARTS','IN_PROGRESS','TESTING','READY_FOR_PICKUP','COMPLETED','CANCELLED') NOT NULL,
  actor_type ENUM('USER','CUSTOMER','SYSTEM') NOT NULL DEFAULT 'USER',
  actor_user_id BIGINT UNSIGNED NULL,
  reason_code VARCHAR(60) NULL,
  internal_note TEXT NULL,
  public_summary TEXT NULL,
  is_customer_visible TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (status_event_id),
  KEY idx_status_events_device_time (ticket_device_id, created_at),
  KEY idx_status_events_public (ticket_device_id, is_customer_visible, created_at),
  CONSTRAINT fk_status_event_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_status_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_correction_notes (
  correction_note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  author_user_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(300) NOT NULL,
  correction_note TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (correction_note_id),
  KEY idx_correction_ticket (ticket_id, created_at),
  CONSTRAINT fk_correction_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_correction_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_correction_author FOREIGN KEY (author_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_transfers (
  device_transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  transfer_code VARCHAR(32) NOT NULL,
  from_branch_id BIGINT UNSIGNED NOT NULL,
  to_branch_id BIGINT UNSIGNED NOT NULL,
  status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED') NOT NULL DEFAULT 'IN_TRANSIT',
  initiated_by_user_id BIGINT UNSIGNED NOT NULL,
  dispatched_at DATETIME NOT NULL,
  received_confirmed_by_user_id BIGINT UNSIGNED NULL,
  received_confirmed_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (device_transfer_id),
  UNIQUE KEY uq_device_transfer_code (transfer_code),
  KEY idx_device_transfer_route_status (from_branch_id, to_branch_id, status),
  CONSTRAINT fk_dt_from_branch FOREIGN KEY (from_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_dt_to_branch FOREIGN KEY (to_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_dt_initiator FOREIGN KEY (initiated_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_dt_receiver FOREIGN KEY (received_confirmed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_dt_different_branches CHECK (from_branch_id <> to_branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_transfer_items (
  device_transfer_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_transfer_id BIGINT UNSIGNED NOT NULL,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  from_location_id BIGINT UNSIGNED NOT NULL,
  transit_location_id BIGINT UNSIGNED NOT NULL,
  to_location_id BIGINT UNSIGNED NOT NULL,
  item_status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED') NOT NULL DEFAULT 'IN_TRANSIT',
  sent_condition_note TEXT NULL,
  receipt_condition_note TEXT NULL,
  received_by_user_id BIGINT UNSIGNED NULL,
  received_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (device_transfer_item_id),
  UNIQUE KEY uq_transfer_device_once (device_transfer_id, ticket_device_id),
  KEY idx_transfer_item_status (item_status, device_transfer_id),
  CONSTRAINT fk_dti_transfer FOREIGN KEY (device_transfer_id) REFERENCES device_transfers(device_transfer_id),
  CONSTRAINT fk_dti_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_dti_from_location FOREIGN KEY (from_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_dti_transit_location FOREIGN KEY (transit_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_dti_to_location FOREIGN KEY (to_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_dti_receiver FOREIGN KEY (received_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_custody_events (
  custody_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('INTAKE_RECEIVED','LOCATION_CHANGED','TRANSFER_DISPATCHED','TRANSFER_RECEIVED','RETURNED_TO_CUSTOMER','DELIVERY_ATTEMPTED','CUSTODY_EXCEPTION','CORRECTION') NOT NULL,
  from_location_id BIGINT UNSIGNED NULL,
  to_location_id BIGINT UNSIGNED NULL,
  device_transfer_id BIGINT UNSIGNED NULL,
  performed_by_user_id BIGINT UNSIGNED NULL,
  note TEXT NULL,
  happened_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (custody_event_id),
  KEY idx_custody_device_time (ticket_device_id, happened_at),
  KEY idx_custody_transfer (device_transfer_id),
  CONSTRAINT fk_custody_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_custody_from FOREIGN KEY (from_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_custody_to FOREIGN KEY (to_location_id) REFERENCES storage_locations(location_id),
  CONSTRAINT fk_custody_transfer FOREIGN KEY (device_transfer_id) REFERENCES device_transfers(device_transfer_id),
  CONSTRAINT fk_custody_user FOREIGN KEY (performed_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS parts (
  part_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku VARCHAR(80) NOT NULL,
  part_name VARCHAR(160) NOT NULL,
  description TEXT NULL,
  unit_of_measure VARCHAR(30) NOT NULL DEFAULT 'piece',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (part_id),
  UNIQUE KEY uq_parts_sku (sku),
  KEY idx_parts_active_name (is_active, part_name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS branch_inventory (
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  quantity_on_hand DECIMAL(12,3) NOT NULL DEFAULT 0,
  reorder_level DECIMAL(12,3) NOT NULL DEFAULT 5,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (branch_id, part_id),
  KEY idx_inventory_reorder (branch_id, quantity_on_hand, reorder_level),
  CONSTRAINT fk_inventory_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_inventory_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT chk_inventory_nonnegative CHECK (quantity_on_hand >= 0),
  CONSTRAINT chk_inventory_reorder_nonnegative CHECK (reorder_level >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_transfers (
  inventory_transfer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  transfer_code VARCHAR(32) NOT NULL,
  from_branch_id BIGINT UNSIGNED NOT NULL,
  to_branch_id BIGINT UNSIGNED NOT NULL,
  status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED') NOT NULL DEFAULT 'IN_TRANSIT',
  initiated_by_user_id BIGINT UNSIGNED NOT NULL,
  dispatched_at DATETIME NOT NULL,
  received_by_user_id BIGINT UNSIGNED NULL,
  received_at DATETIME NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (inventory_transfer_id),
  UNIQUE KEY uq_inventory_transfer_code (transfer_code),
  KEY idx_inv_transfer_route_status (from_branch_id, to_branch_id, status),
  CONSTRAINT fk_it_from_branch FOREIGN KEY (from_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_it_to_branch FOREIGN KEY (to_branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_it_initiator FOREIGN KEY (initiated_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_it_receiver FOREIGN KEY (received_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_it_different_branches CHECK (from_branch_id <> to_branch_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_transfer_items (
  inventory_transfer_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_transfer_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  received_quantity DECIMAL(12,3) NULL,
  item_status ENUM('IN_TRANSIT','RECEIVED','EXCEPTION','CANCELLED') NOT NULL DEFAULT 'IN_TRANSIT',
  receipt_note VARCHAR(500) NULL,
  PRIMARY KEY (inventory_transfer_item_id),
  UNIQUE KEY uq_inv_transfer_part (inventory_transfer_id, part_id),
  CONSTRAINT fk_iti_transfer FOREIGN KEY (inventory_transfer_id) REFERENCES inventory_transfers(inventory_transfer_id),
  CONSTRAINT fk_iti_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT chk_iti_positive_qty CHECK (quantity > 0),
  CONSTRAINT chk_iti_received_qty CHECK (received_quantity IS NULL OR (received_quantity >= 0 AND received_quantity <= quantity))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_transactions (
  inventory_transaction_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  transaction_type ENUM('INITIAL_STOCK','RECEIPT','TICKET_USE','TRANSFER_OUT','TRANSFER_IN','ADJUSTMENT','RETURN') NOT NULL,
  quantity_delta DECIMAL(12,3) NOT NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  inventory_transfer_item_id BIGINT UNSIGNED NULL,
  reference_code VARCHAR(80) NULL,
  reason VARCHAR(500) NULL,
  performed_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (inventory_transaction_id),
  KEY idx_inv_tx_branch_part_time (branch_id, part_id, created_at),
  KEY idx_inv_tx_ticket_device (ticket_device_id, transaction_type),
  KEY idx_inv_tx_transfer_item (inventory_transfer_item_id),
  CONSTRAINT fk_invtx_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_invtx_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT fk_invtx_ticket_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_invtx_transfer_item FOREIGN KEY (inventory_transfer_item_id) REFERENCES inventory_transfer_items(inventory_transfer_item_id),
  CONSTRAINT fk_invtx_user FOREIGN KEY (performed_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_invtx_nonzero_delta CHECK (quantity_delta <> 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_parts_used (
  ticket_part_use_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NOT NULL,
  inventory_transaction_id BIGINT UNSIGNED NOT NULL,
  quantity_used DECIMAL(12,3) NOT NULL,
  unit_cost_at_use DECIMAL(12,2) NOT NULL DEFAULT 0,
  added_by_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_part_use_id),
  UNIQUE KEY uq_ticket_part_use_movement (inventory_transaction_id),
  KEY idx_ticket_parts_device (ticket_device_id),
  CONSTRAINT fk_tpu_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_tpu_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT fk_tpu_movement FOREIGN KEY (inventory_transaction_id) REFERENCES inventory_transactions(inventory_transaction_id),
  CONSTRAINT fk_tpu_user FOREIGN KEY (added_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_tpu_positive_qty CHECK (quantity_used > 0),
  CONSTRAINT chk_tpu_nonnegative_cost CHECK (unit_cost_at_use >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_quotes (
  quote_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL,
  quote_status ENUM('DRAFT','AWAITING_CUSTOMER','APPROVED','DECLINED','EXPIRED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT',
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  labor_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  parts_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  prepared_by_user_id BIGINT UNSIGNED NOT NULL,
  sent_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (quote_id),
  UNIQUE KEY uq_quote_device_version (ticket_device_id, version_no),
  KEY idx_quote_status (quote_status, sent_at),
  CONSTRAINT fk_quote_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_quote_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_quote_amounts CHECK (labor_amount >= 0 AND parts_amount >= 0 AND other_amount >= 0 AND total_amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_quote_items (
  quote_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id BIGINT UNSIGNED NOT NULL,
  part_id BIGINT UNSIGNED NULL,
  item_type ENUM('LABOR','PART','OTHER') NOT NULL,
  description VARCHAR(300) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (quote_item_id),
  KEY idx_quote_items_quote (quote_id),
  CONSTRAINT fk_quote_item_quote FOREIGN KEY (quote_id) REFERENCES repair_quotes(quote_id),
  CONSTRAINT fk_quote_item_part FOREIGN KEY (part_id) REFERENCES parts(part_id),
  CONSTRAINT chk_quote_item_qty CHECK (quantity > 0),
  CONSTRAINT chk_quote_item_values CHECK (unit_price >= 0 AND line_total >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_quote_decisions (
  quote_decision_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  quote_id BIGINT UNSIGNED NOT NULL,
  decision ENUM('APPROVED','DECLINED') NOT NULL,
  decision_source ENUM('CUSTOMER_PORTAL','STAFF_RECORDED','SIMULATED_DEMO') NOT NULL,
  recorded_by_user_id BIGINT UNSIGNED NULL,
  customer_name_at_decision VARCHAR(160) NULL,
  customer_comment TEXT NULL,
  decision_at DATETIME NOT NULL,
  PRIMARY KEY (quote_decision_id),
  UNIQUE KEY uq_quote_decision_once (quote_id),
  CONSTRAINT fk_quote_decision_quote FOREIGN KEY (quote_id) REFERENCES repair_quotes(quote_id),
  CONSTRAINT fk_quote_decision_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_bills (
  bill_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  bill_number VARCHAR(32) NULL COMMENT 'Public bill number assigned when issued.',
  bill_status ENUM('DRAFT','ISSUED','VOIDED') NOT NULL DEFAULT 'DRAFT',
  currency CHAR(3) NOT NULL DEFAULT 'PHP',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  prepared_by_user_id BIGINT UNSIGNED NOT NULL,
  issued_at DATETIME NULL,
  voided_at DATETIME NULL,
  void_reason VARCHAR(500) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bill_id),
  UNIQUE KEY uq_bill_ticket_version (ticket_id, version_no),
  UNIQUE KEY uq_bill_number (bill_number),
  KEY idx_bill_status (bill_status, created_at),
  CONSTRAINT fk_bill_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_bill_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_bill_amounts CHECK (subtotal >= 0 AND discount_amount >= 0 AND tax_amount >= 0 AND total_amount >= 0),
  CONSTRAINT chk_bill_issue_fields CHECK (bill_status <> 'ISSUED' OR (bill_number IS NOT NULL AND issued_at IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS repair_bill_items (
  bill_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_id BIGINT UNSIGNED NOT NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  quote_item_id BIGINT UNSIGNED NULL,
  ticket_part_use_id BIGINT UNSIGNED NULL,
  item_type ENUM('DIAGNOSTIC','LABOR','PART','OTHER') NOT NULL,
  description VARCHAR(300) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (bill_item_id),
  UNIQUE KEY uq_bill_item_quote_item (quote_item_id),
  UNIQUE KEY uq_bill_item_part_use (ticket_part_use_id),
  KEY idx_bill_items_bill (bill_id),
  KEY idx_bill_items_device (ticket_device_id),
  CONSTRAINT fk_bill_item_bill FOREIGN KEY (bill_id) REFERENCES repair_bills(bill_id),
  CONSTRAINT fk_bill_item_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_bill_item_quote FOREIGN KEY (quote_item_id) REFERENCES repair_quote_items(quote_item_id),
  CONSTRAINT fk_bill_item_part_use FOREIGN KEY (ticket_part_use_id) REFERENCES ticket_parts_used(ticket_part_use_id),
  CONSTRAINT chk_bill_item_values CHECK (quantity > 0 AND unit_price >= 0 AND line_total >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ticket_operational_events (
  operational_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('ON_HOLD_STARTED','ON_HOLD_ENDED','OUT_FOR_DELIVERY','DELIVERY_ATTEMPTED','DELIVERY_CONFIRMED','CUSTOMER_CONTACT_ATTEMPTED','OTHER') NOT NULL,
  note TEXT NULL,
  customer_visible TINYINT(1) NOT NULL DEFAULT 0,
  recorded_by_user_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (operational_event_id),
  KEY idx_operational_event_device_time (ticket_device_id, occurred_at),
  CONSTRAINT fk_operational_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_operational_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS mock_notification_log (
  notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  channel ENUM('IN_APP','SMS','EMAIL','WHATSAPP') NOT NULL,
  recipient_masked VARCHAR(190) NULL,
  template_key VARCHAR(100) NOT NULL,
  rendered_preview TEXT NOT NULL,
  delivery_mode ENUM('SIMULATED','REAL') NOT NULL DEFAULT 'SIMULATED',
  delivery_status ENUM('QUEUED','SIMULATED','SENT','FAILED','SKIPPED') NOT NULL DEFAULT 'SIMULATED',
  dedupe_key CHAR(64) NOT NULL,
  provider_message_id VARCHAR(190) NULL,
  error_summary VARCHAR(1000) NULL,
  triggered_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notification_id),
  UNIQUE KEY uq_notification_dedupe (dedupe_key),
  KEY idx_notification_ticket_time (ticket_id, triggered_at),
  KEY idx_notification_status (delivery_mode, delivery_status, triggered_at),
  CONSTRAINT fk_notification_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_notification_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_events (
  audit_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_type ENUM('USER','CUSTOMER','SYSTEM') NOT NULL DEFAULT 'USER',
  actor_user_id BIGINT UNSIGNED NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  reason VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (audit_event_id),
  KEY idx_audit_entity (entity_type, entity_id, created_at),
  KEY idx_audit_actor (actor_user_id, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Optional low-risk demo seed data. No users or default passwords are seeded.
INSERT IGNORE INTO branches (branch_code, branch_name, address) VALUES
  ('BR-A', 'RepairTrack Demo Branch A', 'Demo address - replace before presentation'),
  ('BR-B', 'RepairTrack Demo Branch B', 'Demo address - replace before presentation'),
  ('BR-C', 'RepairTrack Demo Branch C', 'Demo address - replace before presentation');

-- After inserting branches, create locations for each branch. Repeat safely on re-run.
INSERT INTO storage_locations (branch_id, location_code, location_name, location_type)
SELECT b.branch_id, 'INTAKE', 'Intake / Receiving', 'BRANCH_BIN'
FROM branches b
WHERE b.branch_code IN ('BR-A','BR-B','BR-C')
  AND NOT EXISTS (
    SELECT 1 FROM storage_locations sl
    WHERE sl.branch_id = b.branch_id AND sl.location_code = 'INTAKE'
  );

INSERT INTO storage_locations (branch_id, location_code, location_name, location_type)
SELECT b.branch_id, 'REPAIR-BENCH', 'Repair Bench', 'WORKBENCH'
FROM branches b
WHERE b.branch_code IN ('BR-A','BR-B','BR-C')
  AND NOT EXISTS (
    SELECT 1 FROM storage_locations sl
    WHERE sl.branch_id = b.branch_id AND sl.location_code = 'REPAIR-BENCH'
  );

INSERT INTO storage_locations (branch_id, location_code, location_name, location_type)
SELECT b.branch_id, 'IN-TRANSIT', 'In Transit', 'TRANSIT'
FROM branches b
WHERE b.branch_code IN ('BR-A','BR-B','BR-C')
  AND NOT EXISTS (
    SELECT 1 FROM storage_locations sl
    WHERE sl.branch_id = b.branch_id AND sl.location_code = 'IN-TRANSIT'
  );

-- Operational enforcement is deliberately implemented in PHP service transactions:
-- 1. Gate ticket issuance on complete fields, required photos, acknowledgments, and duplicate check.
-- 2. Generate the ticket code and opaque tracking token only at issuance.
-- 3. Keep condition/creation evidence immutable through normal UI edits; use correction notes.
-- 4. Enforce status transitions and role permissions in a single service layer.
-- 5. Update inventory_transactions and branch_inventory in one database transaction with row locks.
-- 6. Set notifications to SIMULATED for local-demo; unique dedupe_key prevents repeated mock sends.
