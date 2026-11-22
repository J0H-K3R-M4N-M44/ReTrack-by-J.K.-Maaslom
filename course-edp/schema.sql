-- RepairTrack EDP classroom schema (v3). New, ISOLATED database only.
-- The script deliberately contains no DROP, ALTER, REPLACE or destructive migration.
-- It never selects or changes the historical database named `repairtrack`.
-- A second execution will fail on CREATE TABLE rather than overwrite existing records.
-- Requires MySQL 8.0.16+ with enforced CHECK constraints and InnoDB.
-- DateTime values are UTC. PHP must set the SQL session timezone to +00:00.

CREATE DATABASE IF NOT EXISTS repairtrack_edp
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE repairtrack_edp;

-- The five staff experiences share a small set of defined roles.
CREATE TABLE branches (
  branch_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_code VARCHAR(20) NOT NULL,
  branch_name VARCHAR(120) NOT NULL,
  address VARCHAR(255) NULL,
  phone VARCHAR(40) NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (branch_id),
  UNIQUE KEY uq_branch_code (branch_code)
) ENGINE=InnoDB;

CREATE TABLE roles (
  role_code VARCHAR(24) NOT NULL,
  role_name VARCHAR(60) NOT NULL,
  PRIMARY KEY (role_code),
  UNIQUE KEY uq_role_name (role_name)
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role_code VARCHAR(24) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_user_email (email),
  KEY idx_user_role (role_code),
  CONSTRAINT fk_user_role FOREIGN KEY (role_code) REFERENCES roles(role_code)
) ENGINE=InnoDB;

CREATE TABLE user_branch_assignments (
  user_id INT UNSIGNED NOT NULL,
  branch_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, branch_id),
  CONSTRAINT fk_uba_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_uba_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  reset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  PRIMARY KEY (reset_id),
  UNIQUE KEY uq_reset_hash (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- Normalized reference data for predictable statuses, defect categories and reports.
CREATE TABLE device_types (
  device_type_code VARCHAR(30) NOT NULL,
  label VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (device_type_code),
  UNIQUE KEY uq_device_type_label (label),
  UNIQUE KEY uq_device_type_sort (sort_order)
) ENGINE=InnoDB;

CREATE TABLE defect_types (
  defect_code VARCHAR(32) NOT NULL,
  label VARCHAR(90) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (defect_code),
  UNIQUE KEY uq_defect_label (label),
  UNIQUE KEY uq_defect_sort (sort_order)
) ENGINE=InnoDB;

CREATE TABLE repair_statuses (
  status_code VARCHAR(32) NOT NULL,
  label VARCHAR(80) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL,
  is_terminal BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (status_code),
  UNIQUE KEY uq_status_label (label),
  UNIQUE KEY uq_status_sort (sort_order)
) ENGINE=InnoDB;

CREATE TABLE allowed_status_transitions (
  from_status VARCHAR(32) NOT NULL,
  to_status VARCHAR(32) NOT NULL,
  PRIMARY KEY (from_status, to_status),
  CONSTRAINT fk_transition_from FOREIGN KEY (from_status) REFERENCES repair_statuses(status_code),
  CONSTRAINT fk_transition_to FOREIGN KEY (to_status) REFERENCES repair_statuses(status_code),
  CONSTRAINT chk_transition_not_same CHECK (from_status <> to_status)
) ENGINE=InnoDB;

CREATE TABLE storage_locations (
  location_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  branch_id INT UNSIGNED NOT NULL,
  location_code VARCHAR(30) NOT NULL,
  location_name VARCHAR(100) NOT NULL,
  is_active BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (location_id),
  UNIQUE KEY uq_location_code (branch_id, location_code),
  UNIQUE KEY uq_location_branch_pair (location_id, branch_id),
  CONSTRAINT fk_location_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
) ENGINE=InnoDB;

CREATE TABLE customers (
  customer_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(160) NOT NULL,
  phone VARCHAR(25) NOT NULL COMMENT 'Normalized contact used for portal PIN derivation at issuance',
  email VARCHAR(190) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  KEY idx_customer_phone (phone),
  CONSTRAINT chk_customer_phone CHECK (phone REGEXP '^[0-9]{4,15}$')
) ENGINE=InnoDB;

CREATE TABLE customer_devices (
  customer_device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL,
  device_type_code VARCHAR(30) NOT NULL,
  brand VARCHAR(100) NULL,
  model VARCHAR(160) NULL,
  serial_number VARCHAR(190) NULL,
  PRIMARY KEY (customer_device_id),
  KEY idx_customer_device_customer (customer_id),
  UNIQUE KEY uq_device_owner (customer_device_id, customer_id),
  CONSTRAINT fk_customer_device_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT fk_customer_device_type FOREIGN KEY (device_type_code) REFERENCES device_types(device_type_code)
) ENGINE=InnoDB;

CREATE TABLE repair_tickets (
  ticket_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NULL COMMENT 'A draft may be saved before the customer is selected',
  branch_id INT UNSIGNED NOT NULL COMMENT 'Exactly one accountable repair branch for this ticket',
  ticket_code VARCHAR(24) NULL COMMENT 'Allocated when issuing the draft; distinct from the private portal code',
  portal_code CHAR(5) NULL COMMENT 'Five hexadecimal characters; generated only when issued',
  portal_pin_hash VARCHAR(255) NULL COMMENT 'Hash of four-digit phone suffix at issuance',
  ticket_state ENUM('DRAFT','OPEN','CLOSED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  intake_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  issued_at DATETIME NULL,
  closed_at DATETIME NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  terms_version VARCHAR(40) NULL,
  terms_signed_at DATETIME NULL,
  terms_signature_path VARCHAR(500) NULL COMMENT 'Opaque reference to protected signed intake file',
  PRIMARY KEY (ticket_id),
  UNIQUE KEY uq_ticket_code (ticket_code),
  UNIQUE KEY uq_portal_code (portal_code),
  UNIQUE KEY uq_ticket_customer_pair (ticket_id, customer_id),
  UNIQUE KEY uq_ticket_branch_pair (ticket_id, branch_id),
  KEY idx_ticket_branch (branch_id, ticket_state, intake_at),
  KEY idx_ticket_customer (customer_id, intake_at),
  CONSTRAINT fk_ticket_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT fk_ticket_branch FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
  CONSTRAINT fk_ticket_creator FOREIGN KEY (created_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_portal_code CHECK (portal_code IS NULL OR portal_code REGEXP '^[0-9A-F]{5}$'),
  CONSTRAINT chk_portal_credentials CHECK ((portal_code IS NULL AND portal_pin_hash IS NULL) OR
                                           (portal_code IS NOT NULL AND portal_pin_hash IS NOT NULL)),
  CONSTRAINT chk_ticket_state_fields CHECK (
    (ticket_state = 'DRAFT' AND ticket_code IS NULL AND issued_at IS NULL AND closed_at IS NULL
     AND portal_code IS NULL AND portal_pin_hash IS NULL AND terms_signed_at IS NULL)
    OR
    (ticket_state <> 'DRAFT' AND customer_id IS NOT NULL AND
     ticket_code IS NOT NULL AND portal_code IS NOT NULL AND portal_pin_hash IS NOT NULL AND
     terms_version IS NOT NULL AND terms_signed_at IS NOT NULL AND terms_signature_path IS NOT NULL AND
     issued_at IS NOT NULL AND
     ((ticket_state = 'OPEN' AND closed_at IS NULL) OR (ticket_state IN ('CLOSED','CANCELLED') AND closed_at IS NOT NULL)))
  )
) ENGINE=InnoDB;

CREATE TABLE ticket_devices (
  ticket_device_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  customer_id BIGINT UNSIGNED NULL COMMENT 'Draft may be device-first; must match ticket/customer at issue',
  branch_id INT UNSIGNED NOT NULL COMMENT 'Must match the ticket accountable branch',
  customer_device_id BIGINT UNSIGNED NULL,
  sequence_no SMALLINT UNSIGNED NOT NULL,
  item_code VARCHAR(32) NULL COMMENT 'Unique per-device tracking label, created at issuance',
  device_type_code VARCHAR(30) NULL COMMENT 'Draft device category before customer device exists',
  make_snapshot VARCHAR(100) NULL,
  model_snapshot VARCHAR(160) NULL,
  serial_snapshot VARCHAR(190) NULL,
  status_code VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  assigned_technician_id INT UNSIGNED NULL,
  location_id INT UNSIGNED NULL,
  reported_symptoms TEXT NOT NULL,
  diagnosis TEXT NULL,
  condition_notes TEXT NULL,
  accessories VARCHAR(255) NULL,
  custody_state ENUM('UNKNOWN','AT_LOCATION','IN_TRANSIT','WITH_CUSTOMER') NOT NULL DEFAULT 'UNKNOWN',
  return_handover_at DATETIME NULL,
  return_handover_by INT UNSIGNED NULL,
  repair_started_at DATETIME NULL COMMENT 'First entry into IN_PROGRESS (UTC); never reset',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (ticket_device_id),
  UNIQUE KEY uq_ticket_device_sequence (ticket_id, sequence_no),
  UNIQUE KEY uq_ticket_device_item_code (item_code),
  UNIQUE KEY uq_ticket_device_once (ticket_id, customer_device_id),
  UNIQUE KEY uq_td_ticket_pair (ticket_device_id, ticket_id),
  KEY idx_device_status (status_code, updated_at),
  KEY idx_device_technician (assigned_technician_id, status_code),
  CONSTRAINT fk_td_ticket_customer FOREIGN KEY (ticket_id, customer_id)
    REFERENCES repair_tickets(ticket_id, customer_id),
  CONSTRAINT fk_td_device_owner FOREIGN KEY (customer_device_id, customer_id)
    REFERENCES customer_devices(customer_device_id, customer_id),
  CONSTRAINT fk_td_ticket_branch FOREIGN KEY (ticket_id, branch_id)
    REFERENCES repair_tickets(ticket_id, branch_id),
  CONSTRAINT fk_td_status FOREIGN KEY (status_code) REFERENCES repair_statuses(status_code),
  CONSTRAINT fk_td_device_type FOREIGN KEY (device_type_code) REFERENCES device_types(device_type_code),
  CONSTRAINT fk_td_handover_user FOREIGN KEY (return_handover_by) REFERENCES users(user_id),
  CONSTRAINT fk_td_tech_branch FOREIGN KEY (assigned_technician_id, branch_id)
    REFERENCES user_branch_assignments(user_id, branch_id),
  CONSTRAINT fk_td_location_branch FOREIGN KEY (location_id, branch_id)
    REFERENCES storage_locations(location_id, branch_id),
  CONSTRAINT chk_td_sequence CHECK (sequence_no >= 1),
  CONSTRAINT chk_td_issued CHECK (status_code = 'DRAFT' OR
    (customer_id IS NOT NULL AND customer_device_id IS NOT NULL AND item_code IS NOT NULL
     AND device_type_code IS NOT NULL)),
  CONSTRAINT chk_td_handover CHECK (
    (return_handover_at IS NULL AND return_handover_by IS NULL AND custody_state <> 'WITH_CUSTOMER')
    OR (return_handover_at IS NOT NULL AND return_handover_by IS NOT NULL
        AND custody_state = 'WITH_CUSTOMER' AND status_code IN ('COMPLETED','CANCELLED')))
) ENGINE=InnoDB;

CREATE TABLE ticket_defects (
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  defect_code VARCHAR(32) NOT NULL,
  details VARCHAR(500) NULL,
  PRIMARY KEY (ticket_device_id, defect_code),
  CONSTRAINT fk_ticket_defect_td FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_ticket_defect_type FOREIGN KEY (defect_code) REFERENCES defect_types(defect_code)
) ENGINE=InnoDB;

CREATE TABLE ticket_status_events (
  status_event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  from_status VARCHAR(32) NULL,
  to_status VARCHAR(32) NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  internal_note TEXT NULL,
  public_summary VARCHAR(500) NULL,
  is_customer_visible BOOLEAN NOT NULL DEFAULT FALSE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (status_event_id),
  KEY idx_event_device_date (ticket_device_id, created_at),
  CONSTRAINT fk_event_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_event_from FOREIGN KEY (from_status) REFERENCES repair_statuses(status_code),
  CONSTRAINT fk_event_to FOREIGN KEY (to_status) REFERENCES repair_statuses(status_code),
  CONSTRAINT fk_event_transition FOREIGN KEY (from_status, to_status)
    REFERENCES allowed_status_transitions(from_status, to_status),
  CONSTRAINT fk_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE repair_quotes (
  quote_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  quote_status ENUM('DRAFT','AWAITING_CUSTOMER','APPROVED','DECLINED','EXPIRED','SUPERSEDED') NOT NULL DEFAULT 'DRAFT',
  labor_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  parts_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  other_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  prepared_by_user_id INT UNSIGNED NOT NULL,
  decision_recorded_by INT UNSIGNED NULL,
  decision_at DATETIME NULL,
  sent_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (quote_id),
  UNIQUE KEY uq_quote_version (ticket_device_id, version_no),
  CONSTRAINT fk_quote_device FOREIGN KEY (ticket_device_id) REFERENCES ticket_devices(ticket_device_id),
  CONSTRAINT fk_quote_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_quote_decision_user FOREIGN KEY (decision_recorded_by) REFERENCES users(user_id),
  CONSTRAINT chk_quote_components CHECK (labor_amount >= 0 AND parts_amount >= 0 AND other_amount >= 0),
  CONSTRAINT chk_quote_total CHECK (total_amount = labor_amount + parts_amount + other_amount),
  CONSTRAINT chk_quote_expiry CHECK (expires_at IS NULL OR (sent_at IS NOT NULL AND expires_at > sent_at)),
  CONSTRAINT chk_quote_decision CHECK (quote_status NOT IN ('APPROVED','DECLINED') OR
                                       (decision_recorded_by IS NOT NULL AND decision_at IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE repair_bills (
  bill_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NOT NULL,
  version_no SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  bill_number VARCHAR(32) NULL COMMENT 'Drafts have no issued bill number',
  bill_status ENUM('DRAFT','ISSUED','VOIDED') NOT NULL DEFAULT 'DRAFT',
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_by_user_id INT UNSIGNED NOT NULL,
  issued_at DATETIME NULL,
  active_ticket_id BIGINT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN bill_status='ISSUED' THEN ticket_id ELSE NULL END) STORED,
  PRIMARY KEY (bill_id),
  UNIQUE KEY uq_one_active_issued_bill (active_ticket_id),
  UNIQUE KEY uq_bill_number (bill_number),
  UNIQUE KEY uq_bill_ticket_version (ticket_id, version_no),
  UNIQUE KEY uq_bill_ticket_pair (bill_id, ticket_id),
  CONSTRAINT fk_bill_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_bill_creator FOREIGN KEY (created_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_bill_nonnegative CHECK (subtotal >= 0 AND discount_amount >= 0 AND
                                       tax_amount >= 0 AND discount_amount <= subtotal),
  CONSTRAINT chk_bill_total CHECK (total_amount = subtotal - discount_amount + tax_amount),
  CONSTRAINT chk_bill_issued CHECK (
    (bill_status = 'DRAFT' AND issued_at IS NULL AND bill_number IS NULL)
    OR (bill_status IN ('ISSUED','VOIDED') AND bill_number IS NOT NULL AND issued_at IS NOT NULL))
) ENGINE=InnoDB;

-- Immutable issued-bill breakdown by repair device. No stock accounting needed.
CREATE TABLE repair_bill_items (
  bill_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_id BIGINT UNSIGNED NOT NULL,
  ticket_id BIGINT UNSIGNED NOT NULL,
  ticket_device_id BIGINT UNSIGNED NOT NULL,
  description VARCHAR(160) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (bill_item_id),
  UNIQUE KEY uq_bill_device (bill_id, ticket_device_id),
  CONSTRAINT fk_bill_item_ticket FOREIGN KEY (bill_id, ticket_id)
    REFERENCES repair_bills(bill_id, ticket_id),
  CONSTRAINT fk_bill_item_device FOREIGN KEY (ticket_device_id, ticket_id)
    REFERENCES ticket_devices(ticket_device_id, ticket_id),
  CONSTRAINT chk_bill_item_amount CHECK (amount >= 0)
) ENGINE=InnoDB;

CREATE TABLE repair_bill_payments (
  payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_id BIGINT UNSIGNED NOT NULL,
  payment_method ENUM('COUNTER_CASH','COD') NOT NULL DEFAULT 'COUNTER_CASH' COMMENT 'COD only after verified collection; separate workflow required',
  amount DECIMAL(12,2) NOT NULL,
  request_key CHAR(64) NOT NULL,
  received_by_user_id INT UNSIGNED NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payment_id),
  UNIQUE KEY uq_payment_request_key (request_key),
  KEY idx_payment_bill (bill_id, received_at),
  CONSTRAINT fk_payment_bill FOREIGN KEY (bill_id) REFERENCES repair_bills(bill_id),
  CONSTRAINT fk_payment_cashier FOREIGN KEY (received_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_payment_positive CHECK (amount > 0),
  CONSTRAINT chk_payment_key CHECK (request_key REGEXP '^[0-9a-fA-F]{64}$')
) ENGINE=InnoDB;

-- Actual images are private files, not public URLs. PHP authorizes every download.
CREATE TABLE media_assets (
  asset_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id BIGINT UNSIGNED NOT NULL COMMENT 'Always bind assets to a known customer',
  ticket_id BIGINT UNSIGNED NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  asset_category ENUM('CUSTOMER_IMAGE','DEVICE_CONDITION','DAMAGE_DETAIL','SERIAL_LABEL','ACCESSORIES','SIGNATURE','OTHER') NOT NULL,
  stored_path VARCHAR(500) NOT NULL COMMENT 'Relative opaque identifier in private storage, not a public path',
  original_filename VARCHAR(255) NULL,
  mime_type ENUM('image/jpeg','image/png','image/webp') NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256_hash CHAR(64) NOT NULL,
  uploaded_by_user_id INT UNSIGNED NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (asset_id),
  UNIQUE KEY uq_media_stored_path (stored_path),
  KEY idx_media_customer (customer_id),
  KEY idx_media_ticket (ticket_id),
  KEY idx_media_ticket_device (ticket_device_id),
  CONSTRAINT fk_media_customer FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
  CONSTRAINT fk_media_ticket_customer FOREIGN KEY (ticket_id, customer_id)
    REFERENCES repair_tickets(ticket_id, customer_id),
  CONSTRAINT fk_media_ticket_device FOREIGN KEY (ticket_device_id, ticket_id)
    REFERENCES ticket_devices(ticket_device_id, ticket_id),
  CONSTRAINT fk_media_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_media_owner CHECK (ticket_device_id IS NULL OR ticket_id IS NOT NULL),
  CONSTRAINT chk_media_path CHECK (stored_path REGEXP '^[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*[.](jpg|jpeg|png|webp)$'),
  CONSTRAINT chk_media_size CHECK (size_bytes BETWEEN 1 AND 5242880),
  CONSTRAINT chk_media_hash CHECK (sha256_hash REGEXP '^[0-9a-fA-F]{64}$')
) ENGINE=InnoDB;

-- Notes are text records; the Module 3 Notepad file watcher is a separate app feature.
CREATE TABLE notes (
  note_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT UNSIGNED NULL,
  ticket_device_id BIGINT UNSIGNED NULL,
  author_user_id INT UNSIGNED NOT NULL,
  note_title VARCHAR(120) NOT NULL,
  note_text MEDIUMTEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (note_id),
  KEY idx_note_ticket (ticket_id),
  KEY idx_note_device (ticket_device_id),
  CONSTRAINT fk_note_ticket FOREIGN KEY (ticket_id) REFERENCES repair_tickets(ticket_id),
  CONSTRAINT fk_note_device_ticket FOREIGN KEY (ticket_device_id, ticket_id)
    REFERENCES ticket_devices(ticket_device_id, ticket_id),
  CONSTRAINT fk_note_author FOREIGN KEY (author_user_id) REFERENCES users(user_id),
  CONSTRAINT chk_note_owner CHECK (ticket_id IS NOT NULL)
) ENGINE=InnoDB;

-- A small activity history, not a general enterprise audit/event-stream platform.
CREATE TABLE audit_events (
  audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id INT UNSIGNED NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(60) NOT NULL,
  details_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (audit_id),
  KEY idx_audit_entity (entity_type, entity_id, created_at),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;
