-- Import create_demo_database.sql first. This never selects your live repairtrack database.
USE repairtrack_demo;
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET @seed_now = UTC_TIMESTAMP();
SET @seed_start = DATE_SUB(@seed_now, INTERVAL 14 DAY);

-- One transaction: either all sample rows are inserted, or none are.
-- A rerun is deliberately rejected instead of silently overwriting test changes.
DROP PROCEDURE IF EXISTS seed_repairtrack_demo;
DELIMITER $$
CREATE PROCEDURE seed_repairtrack_demo()
BEGIN
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  IF DATABASE() <> 'repairtrack_demo' OR @@FOREIGN_KEY_CHECKS <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Use repairtrack_demo with foreign key checks enabled.';
  END IF;
  START TRANSACTION;
  IF EXISTS (SELECT 1 FROM branches LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: branches is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM users LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: users is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM user_branch_assignments LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: user_branch_assignments is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM customers LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: customers is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM customer_contacts LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: customer_contacts is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM customer_devices LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: customer_devices is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM storage_locations LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: storage_locations is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_tickets LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_tickets is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_devices LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_devices is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_accessory_items LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_accessory_items is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_device_photos LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_device_photos is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_device_acknowledgments LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_device_acknowledgments is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_intake_gate_events LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_intake_gate_events is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_status_events LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_status_events is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_correction_notes LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_correction_notes is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM device_transfers LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: device_transfers is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM device_transfer_items LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: device_transfer_items is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM device_custody_events LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: device_custody_events is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM parts LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: parts is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM branch_inventory LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: branch_inventory is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM inventory_transfers LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: inventory_transfers is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM inventory_transfer_items LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: inventory_transfer_items is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM inventory_transactions LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: inventory_transactions is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_parts_used LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_parts_used is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_quotes LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_quotes is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_quote_items LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_quote_items is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_quote_decisions LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_quote_decisions is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_bills LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_bills is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM repair_bill_items LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: repair_bill_items is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM ticket_operational_events LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: ticket_operational_events is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM mock_notification_log LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: mock_notification_log is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM audit_events LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: audit_events is not empty.';
  END IF;
  IF EXISTS (SELECT 1 FROM password_reset_tokens LIMIT 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: password_reset_tokens is not empty.';
  END IF;

  -- branches: 3 sample rows.
  INSERT INTO branches (
    branch_id, branch_code, branch_name, address, email
  ) VALUES (
    1, 'DEMO-1', 'Sogod Central Demo', 'Fictional test branch, Sogod, Southern Leyte', 'branch1@example.test'
  );
  INSERT INTO branches (
    branch_id, branch_code, branch_name, address, email
  ) VALUES (
    2, 'DEMO-2', 'Sogod North Demo', 'Fictional test branch, Sogod, Southern Leyte', 'branch2@example.test'
  );
  INSERT INTO branches (
    branch_id, branch_code, branch_name, address, email
  ) VALUES (
    3, 'DEMO-3', 'Sogod Service Hub Demo', 'Fictional test branch, Sogod, Southern Leyte', 'branch3@example.test'
  );

  -- users: 8 sample rows.
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    1, 'Demo Admin 1', 'admin@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'ADMIN', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    2, 'Demo Manager 2', 'demo.user2@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'MANAGER', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    3, 'Demo Intake_Agent 3', 'demo.user3@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'INTAKE_AGENT', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    4, 'Demo Technician 4', 'demo.user4@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'TECHNICIAN', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    5, 'Demo Courier 5', 'demo.user5@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'COURIER', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    6, 'Demo Viewer 6', 'demo.user6@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'VIEWER', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    7, 'Demo Technician 7', 'demo.user7@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'TECHNICIAN', 1
  );
  INSERT INTO users (
    user_id, full_name, email, password_hash, role, is_active
  ) VALUES (
    8, 'Demo Admin 8', 'demo.user8@example.test', '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.', 'ADMIN', 0
  );

  -- user_branch_assignments: 24 sample rows.
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    1, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    1, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    1, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    2, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    2, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    2, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    3, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    3, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    3, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    4, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    4, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    4, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    5, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    5, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    5, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    6, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    6, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    6, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    7, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    7, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    7, 3, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    8, 1, 1
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    8, 2, 0
  );
  INSERT INTO user_branch_assignments (
    user_id, branch_id, is_default
  ) VALUES (
    8, 3, 0
  );

  -- customers: 30 sample rows.
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    1, 'Demo Customer 01', 'demo customer 01', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    2, 'Demo Customer 02', 'demo customer 02', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    3, 'Demo Customer 03', 'demo customer 03', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    4, 'Demo Customer 04', 'demo customer 04', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    5, 'Demo Customer 05', 'demo customer 05', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    6, 'Demo Customer 06', 'demo customer 06', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    7, 'Demo Customer 07', 'demo customer 07', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    8, 'Demo Customer 08', 'demo customer 08', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    9, 'Demo Customer 09', 'demo customer 09', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 240 HOUR), DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    10, 'Demo Customer 10', 'demo customer 10', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    11, 'Demo Customer 11', 'demo customer 11', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 288 HOUR), DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    12, 'Demo Customer 12', 'demo customer 12', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    13, 'Demo Customer 13', 'demo customer 13', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    14, 'Demo Customer 14', 'demo customer 14', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    15, 'Demo Customer 15', 'demo customer 15', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    16, 'Demo Customer 16', 'demo customer 16', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    17, 'Demo Customer 17', 'demo customer 17', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    18, 'Demo Customer 18', 'demo customer 18', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    19, 'Demo Customer 19', 'demo customer 19', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    20, 'Demo Customer 20', 'demo customer 20', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    21, 'Demo Customer 21', 'demo customer 21', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 240 HOUR), DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    22, 'Demo Customer 22', 'demo customer 22', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    23, 'Demo Customer 23', 'demo customer 23', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 288 HOUR), DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    24, 'Demo Customer 24', 'demo customer 24', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    25, 'Demo Customer 25', 'demo customer 25', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    26, 'Demo Customer 26', 'demo customer 26', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    27, 'Demo Customer 27', 'demo customer 27', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    28, 'Demo Customer 28', 'demo customer 28', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    29, 'Demo Customer 29', 'demo customer 29', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO customers (
    customer_id, full_name, normalized_name, customer_notes, created_at, updated_at
  ) VALUES (
    30, 'Demo Customer 30', 'demo customer 30', 'Fictional record; internal staff note.', DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );

  -- customer_contacts: 30 sample rows.
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    1, 1, 'EMAIL', 'customer01@example.test', 'customer01@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    2, 2, 'EMAIL', 'customer02@example.test', 'customer02@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    3, 3, 'EMAIL', 'customer03@example.test', 'customer03@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    4, 4, 'EMAIL', 'customer04@example.test', 'customer04@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    5, 5, 'EMAIL', 'customer05@example.test', 'customer05@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    6, 6, 'EMAIL', 'customer06@example.test', 'customer06@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    7, 7, 'EMAIL', 'customer07@example.test', 'customer07@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    8, 8, 'EMAIL', 'customer08@example.test', 'customer08@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    9, 9, 'EMAIL', 'customer09@example.test', 'customer09@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    10, 10, 'EMAIL', 'customer10@example.test', 'customer10@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    11, 11, 'EMAIL', 'customer11@example.test', 'customer11@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    12, 12, 'EMAIL', 'customer12@example.test', 'customer12@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    13, 13, 'EMAIL', 'customer13@example.test', 'customer13@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    14, 14, 'EMAIL', 'customer14@example.test', 'customer14@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    15, 15, 'EMAIL', 'customer15@example.test', 'customer15@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    16, 16, 'EMAIL', 'customer16@example.test', 'customer16@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    17, 17, 'EMAIL', 'customer17@example.test', 'customer17@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    18, 18, 'EMAIL', 'customer18@example.test', 'customer18@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    19, 19, 'EMAIL', 'customer19@example.test', 'customer19@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    20, 20, 'EMAIL', 'customer20@example.test', 'customer20@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    21, 21, 'EMAIL', 'customer21@example.test', 'customer21@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    22, 22, 'EMAIL', 'customer22@example.test', 'customer22@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    23, 23, 'EMAIL', 'customer23@example.test', 'customer23@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    24, 24, 'EMAIL', 'customer24@example.test', 'customer24@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    25, 25, 'EMAIL', 'customer25@example.test', 'customer25@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    26, 26, 'EMAIL', 'customer26@example.test', 'customer26@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    27, 27, 'EMAIL', 'customer27@example.test', 'customer27@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    28, 28, 'EMAIL', 'customer28@example.test', 'customer28@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    29, 29, 'EMAIL', 'customer29@example.test', 'customer29@example.test', 1, 1
  );
  INSERT INTO customer_contacts (
    contact_id, customer_id, contact_type, contact_value, normalized_value, is_primary, preferred_for_notifications
  ) VALUES (
    30, 30, 'EMAIL', 'customer30@example.test', 'customer30@example.test', 1, 1
  );

  -- customer_devices: 31 sample rows.
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    1, 1, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0001'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    2, 2, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0002'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    3, 3, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0003'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    4, 4, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0004'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    5, 5, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0005'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    6, 6, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0006'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    7, 7, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0007'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    8, 8, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0008'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    9, 9, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0009'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    10, 10, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0010'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    11, 11, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0011'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    12, 12, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0012'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    13, 13, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0013'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    14, 14, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0014'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    15, 15, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0015'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    16, 16, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0016'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    17, 17, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0017'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    18, 18, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0018'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    19, 19, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0019'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    20, 20, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0020'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    21, 21, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0021'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    22, 22, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0022'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    23, 23, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0023'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    24, 24, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0024'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    25, 25, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0025'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    26, 26, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0026'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    27, 27, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0027'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    28, 28, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0028'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    29, 29, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0029'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    30, 30, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0030'
  );
  INSERT INTO customer_devices (
    customer_device_id, customer_id, device_type, make, model, serial_number
  ) VALUES (
    31, 2, 'Desktop computer', 'Demo brand', 'Tower', NULL
  );

  -- storage_locations: 10 sample rows.
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    11, 1, 'INTAKE', 'Demo intake', 'BRANCH_BIN'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    12, 1, 'BENCH', 'Demo bench', 'WORKBENCH'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    13, 1, 'TRANSIT', 'Demo transit', 'TRANSIT'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    21, 2, 'INTAKE', 'Demo intake', 'BRANCH_BIN'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    22, 2, 'BENCH', 'Demo bench', 'WORKBENCH'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    23, 2, 'TRANSIT', 'Demo transit', 'TRANSIT'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    31, 3, 'INTAKE', 'Demo intake', 'BRANCH_BIN'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    32, 3, 'BENCH', 'Demo bench', 'WORKBENCH'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    33, 3, 'TRANSIT', 'Demo transit', 'TRANSIT'
  );
  INSERT INTO storage_locations (
    location_id, branch_id, location_code, location_name, location_type
  ) VALUES (
    99, NULL, 'DEMO-CUSTOMER', 'With customer (demo)', 'WITH_CUSTOMER'
  );

  -- repair_tickets: 30 sample rows.
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    1, NULL, 'DRAFT', NULL, NULL, 1, 1, NULL, 3, 'WALK_IN', NULL, NULL, NULL, 0, NULL, NULL, DATE_ADD(@seed_start, INTERVAL 48 HOUR), NULL, NULL, DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    2, 'DEMO-RT-0002', 'ISSUED', 'OPEN', NULL, 2, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 73 HOUR), INTERVAL 8 HOUR)), '32ec3e2de495845cd92f512612fe13f804b8cf5037bafc4f1ac9ed3f3c8209a4', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    3, 'DEMO-RT-0003', 'ISSUED', 'OPEN', NULL, 3, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 97 HOUR), INTERVAL 8 HOUR)), '1627836cec0c73a3da40be5190317cce1b8aa2cb1280ab70e1a5b8a94356f60c', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    4, 'DEMO-RT-0004', 'ISSUED', 'OPEN', NULL, 4, 1, 1, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 121 HOUR), INTERVAL 8 HOUR)), 'ac99fc03e11367aa232f4f32cf6cf199746f1fc18ae859f44a17d1bf0f488659', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    5, 'DEMO-RT-0005', 'ISSUED', 'OPEN', NULL, 5, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 145 HOUR), INTERVAL 8 HOUR)), 'eff76fa82df9514cfafceb488738ae5c2ba30c2c5746c37735e3c45f584a9c00', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    6, 'DEMO-RT-0006', 'ISSUED', 'OPEN', NULL, 6, 3, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 169 HOUR), INTERVAL 8 HOUR)), '7d9e0021caa540aca743272db23e7c39d0ba407615eb5def496de21e79b1e7b2', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    7, 'DEMO-RT-0007', 'ISSUED', 'OPEN', NULL, 7, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 193 HOUR), INTERVAL 8 HOUR)), '073500c9c8dc683cd04ae4db365d819babd615fc555b49016ad6c28b8b882695', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 193 HOUR), DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 193 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    8, 'DEMO-RT-0008', 'ISSUED', 'OPEN', NULL, 8, 2, 2, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 217 HOUR), INTERVAL 8 HOUR)), 'cdf0cb64eba6347f108abadc5990467684768f33271ab305c041a0008eafb2e9', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 217 HOUR), DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 217 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    9, 'DEMO-RT-0009', 'ISSUED', 'CLOSED', 'ALL_COMPLETED', 9, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 241 HOUR), INTERVAL 8 HOUR)), 'ebc6fcd841ef6ebded426e62b33e48023b26235cc7f523c2915a02c52e9c7360', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 241 HOUR), DATE_ADD(@seed_start, INTERVAL 240 HOUR), DATE_ADD(@seed_start, INTERVAL 241 HOUR), DATE_ADD(@seed_start, INTERVAL 252 HOUR), DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    10, 'DEMO-RT-0010', 'ISSUED', 'CLOSED', 'ALL_CANCELLED', 10, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 265 HOUR), INTERVAL 8 HOUR)), '09f81a48bd15932d0039e919c2b2c5901853a70438616d3d10639a803d5e0b29', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 265 HOUR), DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 265 HOUR), DATE_ADD(@seed_start, INTERVAL 276 HOUR), DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    11, NULL, 'DRAFT', NULL, NULL, 11, 2, NULL, 3, 'WALK_IN', NULL, NULL, NULL, 0, NULL, NULL, DATE_ADD(@seed_start, INTERVAL 288 HOUR), NULL, NULL, DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    12, 'DEMO-RT-0012', 'ISSUED', 'OPEN', NULL, 12, 3, 3, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 25 HOUR), INTERVAL 8 HOUR)), '596867a619db0b373dc3f6e1a6015cb97e0fcbcf6b4a2885d3286554c3995287', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 25 HOUR), DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 25 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    13, 'DEMO-RT-0013', 'ISSUED', 'OPEN', NULL, 13, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 49 HOUR), INTERVAL 8 HOUR)), '638bf21cea25bd51ed2d482de0963f0662348096f3ae9aa6775ad237823c461a', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 49 HOUR), DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 49 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    14, 'DEMO-RT-0014', 'ISSUED', 'OPEN', NULL, 14, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 73 HOUR), INTERVAL 8 HOUR)), 'a915563e30b2043dfb028fe7959b242005b2ba9ec732c18b4a805b5f18cedcf3', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    15, 'DEMO-RT-0015', 'ISSUED', 'OPEN', NULL, 15, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 97 HOUR), INTERVAL 8 HOUR)), '974b054a7d30d035137f706a0393311b3a66c185098a2eeb7fdb8f4a9251983c', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    16, 'DEMO-RT-0016', 'ISSUED', 'OPEN', NULL, 16, 1, 1, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 121 HOUR), INTERVAL 8 HOUR)), 'ca17761243b57a5becdb78a03ba0883e405b86a8c06debee8126385e23a1b4fa', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    17, 'DEMO-RT-0017', 'ISSUED', 'OPEN', NULL, 17, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 145 HOUR), INTERVAL 8 HOUR)), 'b75dc4e2c4caa3d2e9836e21c5c426652fa05eba1ed4ed62caeffea5a1b5cdc9', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    18, 'DEMO-RT-0018', 'ISSUED', 'OPEN', NULL, 18, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 169 HOUR), INTERVAL 8 HOUR)), 'ceb493c10c8d60e32bc7c2ca44f268dbd704dcfe8997e6d87c90f86811b2a2b5', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    19, 'DEMO-RT-0019', 'ISSUED', 'CLOSED', 'ALL_COMPLETED', 19, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 193 HOUR), INTERVAL 8 HOUR)), '73c90edc8fa3e42a66dc893a34587c28686110d195d89b0ea6cc46237f451ecb', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 193 HOUR), DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 193 HOUR), DATE_ADD(@seed_start, INTERVAL 204 HOUR), DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    20, 'DEMO-RT-0020', 'ISSUED', 'CLOSED', 'ALL_CANCELLED', 20, 2, 2, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 217 HOUR), INTERVAL 8 HOUR)), 'cfbaa72824763f32a86a7346046b0b0cbc29cbfb72d5d28dd17bcada7d4e6776', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 217 HOUR), DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 217 HOUR), DATE_ADD(@seed_start, INTERVAL 228 HOUR), DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    21, NULL, 'DRAFT', NULL, NULL, 21, 3, NULL, 3, 'WALK_IN', NULL, NULL, NULL, 0, NULL, NULL, DATE_ADD(@seed_start, INTERVAL 240 HOUR), NULL, NULL, DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    22, 'DEMO-RT-0022', 'ISSUED', 'OPEN', NULL, 22, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 265 HOUR), INTERVAL 8 HOUR)), '17737cca79d3d7f74e7c78395dc0a5daeb889e0d4f7131d456dc18c1b4475f75', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 265 HOUR), DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 265 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    23, 'DEMO-RT-0023', 'ISSUED', 'OPEN', NULL, 23, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 289 HOUR), INTERVAL 8 HOUR)), 'bfb91394c8a2e96c029f19cd19f4a7f9271c28d30e3d216aa83d89cf49323f4a', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 289 HOUR), DATE_ADD(@seed_start, INTERVAL 288 HOUR), DATE_ADD(@seed_start, INTERVAL 289 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    24, 'DEMO-RT-0024', 'ISSUED', 'OPEN', NULL, 24, 3, 3, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 25 HOUR), INTERVAL 8 HOUR)), '728932abbec4cf3b96e17c6a7fb94b9588d129d3e1ac4683dfe964a0f9acdf08', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 25 HOUR), DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 25 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    25, 'DEMO-RT-0025', 'ISSUED', 'OPEN', NULL, 25, 1, 1, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 49 HOUR), INTERVAL 8 HOUR)), '7b980d05d74515fdfaa63cc787e35487c7480625120c383ff60da3d02f641bb2', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 49 HOUR), DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 49 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    26, 'DEMO-RT-0026', 'ISSUED', 'OPEN', NULL, 26, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 73 HOUR), INTERVAL 8 HOUR)), 'f608e49c1657943c05b631aa2cb3f86126d25833ea3676870f4d6fcba042f4d6', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    27, 'DEMO-RT-0027', 'ISSUED', 'OPEN', NULL, 27, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 97 HOUR), INTERVAL 8 HOUR)), '0990f9d346fe2304cb5236c27100b4529794887493134ddfa495b991fc273b19', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    28, 'DEMO-RT-0028', 'ISSUED', 'OPEN', NULL, 28, 1, 1, 3, 'MAIL', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 121 HOUR), INTERVAL 8 HOUR)), '5ebd07c4b9ed06eb9e74986680f9d14c25b1e9b6511cbfbff635857d3d3579b9', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    29, 'DEMO-RT-0029', 'ISSUED', 'CLOSED', 'ALL_COMPLETED', 29, 2, 2, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 145 HOUR), INTERVAL 8 HOUR)), 'd98e377bcf9c02ebb718fd78e77e84f33133cfa4deacb0cb5493d287b4f38f8a', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 156 HOUR), DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO repair_tickets (
    ticket_id, ticket_code, record_stage, case_state, closure_outcome, customer_id, intake_branch_id, accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash, terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, closed_at, updated_at
  ) VALUES (
    30, 'DEMO-RT-0030', 'ISSUED', 'CLOSED', 'ALL_CANCELLED', 30, 3, 3, 3, 'WALK_IN', DATE(DATE_ADD(DATE_ADD(@seed_start, INTERVAL 169 HOUR), INTERVAL 8 HOUR)), '51a190701d9fcb814d5451e9620e4b245104a1c4638911b4bceb37d09f7a15a1', 'DEMO-v1', 1, 'assets/demo/acknowledgment.svg', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 180 HOUR), DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );

  -- ticket_devices: 31 sample rows.
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    1, 1, 1, NULL, 1, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0001', 'Slow startup', 'Light case scratches; screen intact (simulated).', 0, 'DRAFT', 'Sample repair progress only.', NULL, 11, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    2, 2, 1, 'DEMO-RT-0002-01', 2, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0002', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'RECEIVED', 'Sample repair progress only.', NULL, 21, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    3, 3, 1, 'DEMO-RT-0003-01', 3, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0003', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'DIAGNOSING', 'Sample repair progress only.', 4, 32, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    4, 4, 1, 'DEMO-RT-0004-01', 4, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0004', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_CUSTOMER_APPROVAL', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    5, 5, 1, 'DEMO-RT-0005-01', 5, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0005', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_PARTS', 'Sample repair progress only.', 4, 23, 'IN_TRANSIT', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    6, 6, 1, 'DEMO-RT-0006-01', 6, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0006', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'IN_PROGRESS', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    7, 7, 1, 'DEMO-RT-0007-01', 7, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0007', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'TESTING', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    8, 8, 1, 'DEMO-RT-0008-01', 8, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0008', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'READY_FOR_PICKUP', 'Sample repair progress only.', 4, 22, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    9, 9, 1, 'DEMO-RT-0009-01', 9, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0009', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'COMPLETED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 252 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 240 HOUR), DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    10, 10, 1, 'DEMO-RT-0010-01', 10, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0010', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'CANCELLED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 276 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    11, 11, 1, NULL, 11, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0011', 'Overheating', 'Light case scratches; screen intact (simulated).', 0, 'DRAFT', 'Sample repair progress only.', NULL, 21, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 288 HOUR), DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    12, 12, 1, 'DEMO-RT-0012-01', 12, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0012', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'RECEIVED', 'Sample repair progress only.', NULL, 31, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    13, 13, 1, 'DEMO-RT-0013-01', 13, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0013', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'DIAGNOSING', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    14, 14, 1, 'DEMO-RT-0014-01', 14, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0014', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_CUSTOMER_APPROVAL', 'Sample repair progress only.', 4, 22, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    15, 15, 1, 'DEMO-RT-0015-01', 15, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0015', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_PARTS', 'Sample repair progress only.', 4, 32, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    16, 16, 1, 'DEMO-RT-0016-01', 16, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0016', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'IN_PROGRESS', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    17, 17, 1, 'DEMO-RT-0017-01', 17, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0017', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'TESTING', 'Sample repair progress only.', 4, 22, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    18, 18, 1, 'DEMO-RT-0018-01', 18, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0018', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'READY_FOR_PICKUP', 'Sample repair progress only.', 4, 32, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    19, 19, 1, 'DEMO-RT-0019-01', 19, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0019', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'COMPLETED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 204 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 192 HOUR), DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    20, 20, 1, 'DEMO-RT-0020-01', 20, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0020', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'CANCELLED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 228 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 216 HOUR), DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    21, 21, 1, NULL, 21, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0021', 'Will not power on', 'Light case scratches; screen intact (simulated).', 0, 'DRAFT', 'Sample repair progress only.', NULL, 31, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 240 HOUR), DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    22, 22, 1, 'DEMO-RT-0022-01', 22, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0022', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'RECEIVED', 'Sample repair progress only.', NULL, 11, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 264 HOUR), DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    23, 23, 1, 'DEMO-RT-0023-01', 23, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0023', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'DIAGNOSING', 'Sample repair progress only.', 4, 22, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 288 HOUR), DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    24, 24, 1, 'DEMO-RT-0024-01', 24, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0024', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_CUSTOMER_APPROVAL', 'Sample repair progress only.', 4, 32, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 24 HOUR), DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    25, 25, 1, 'DEMO-RT-0025-01', 25, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0025', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'AWAITING_PARTS', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 48 HOUR), DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    26, 26, 1, 'DEMO-RT-0026-01', 26, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0026', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'IN_PROGRESS', 'Sample repair progress only.', 4, 22, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    27, 27, 1, 'DEMO-RT-0027-01', 27, 'Laptop', 'Demo brand', 'Model 4', 'DEMO-SN-0027', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'TESTING', 'Sample repair progress only.', 4, 32, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 96 HOUR), DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    28, 28, 1, 'DEMO-RT-0028-01', 28, 'Laptop', 'Demo brand', 'Model 1', 'DEMO-SN-0028', 'Slow startup', 'Light case scratches; screen intact (simulated).', 1, 'READY_FOR_PICKUP', 'Sample repair progress only.', 4, 12, 'AT_LOCATION', NULL, NULL, DATE_ADD(@seed_start, INTERVAL 120 HOUR), DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    29, 29, 1, 'DEMO-RT-0029-01', 29, 'Laptop', 'Demo brand', 'Model 2', 'DEMO-SN-0029', 'Overheating', 'Light case scratches; screen intact (simulated).', 1, 'COMPLETED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 156 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 144 HOUR), DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, serial_number_snapshot, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, public_summary, assigned_technician_id, physical_location_id, custody_state, return_handover_at, return_handover_by, created_at, updated_at
  ) VALUES (
    30, 30, 1, 'DEMO-RT-0030-01', 30, 'Laptop', 'Demo brand', 'Model 3', 'DEMO-SN-0030', 'Will not power on', 'Light case scratches; screen intact (simulated).', 1, 'CANCELLED', 'Sample repair progress only.', 4, 99, 'WITH_CUSTOMER', DATE_ADD(@seed_start, INTERVAL 180 HOUR), 3, DATE_ADD(@seed_start, INTERVAL 168 HOUR), DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO ticket_devices (
    ticket_device_id, ticket_id, child_sequence, item_code, customer_device_id, device_type_snapshot, make_snapshot, model_snapshot, alternative_identifiers, reported_symptoms, condition_notes, accessory_checklist_completed, repair_status, physical_location_id, custody_state, created_at, updated_at
  ) VALUES (
    31, 2, 2, 'DEMO-RT-0002-02', 31, 'Desktop computer', 'Demo brand', 'Tower', 'Blue case with demo asset label 31', 'No display', 'Case intact', 1, 'RECEIVED', 21, 'AT_LOCATION', DATE_ADD(@seed_start, INTERVAL 72 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );

  -- ticket_accessory_items: 31 sample rows.
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    1, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    2, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    3, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    4, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    5, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    6, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    7, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    8, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    9, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    10, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    11, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    12, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    13, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    14, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    15, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    16, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    17, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    18, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    19, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    20, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    21, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    22, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    23, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    24, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    25, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    26, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    27, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    28, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    29, 'Charger', 1, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, notes, recorded_by_user_id
  ) VALUES (
    30, 'Charger', 0, 'Checklist sample; missing chargers are recorded as not received.', 3
  );
  INSERT INTO ticket_accessory_items (
    ticket_device_id, accessory_name, received, recorded_by_user_id
  ) VALUES (
    31, 'Power cable', 1, 3
  );

  -- ticket_device_photos: 31 sample rows.
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    1, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    2, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    3, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    4, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    5, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    6, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    7, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    8, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    9, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    10, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    11, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    12, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    13, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    14, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    15, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    16, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    17, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    18, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    19, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    20, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    21, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    22, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    23, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    24, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    25, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    26, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    27, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    28, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    29, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, original_filename, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    30, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO ticket_device_photos (
    ticket_device_id, photo_category, stored_path, mime_type, byte_size, sha256_hash, uploaded_by_user_id, captured_at
  ) VALUES (
    31, 'OVERALL_CONDITION', 'assets/demo/device-evidence.svg', 'image/svg+xml', 360, 'a653f94ea8319e8446c2379b558576f8e2d81a137841cc4f6321bf7af3116687', 3, DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );

  -- ticket_device_acknowledgments: 28 sample rows.
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    2, 'Demo Customer 02', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    3, 'Demo Customer 03', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    4, 'Demo Customer 04', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    5, 'Demo Customer 05', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    6, 'Demo Customer 06', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    7, 'Demo Customer 07', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 193 HOUR), DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    8, 'Demo Customer 08', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 217 HOUR), DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    9, 'Demo Customer 09', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 241 HOUR), DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    10, 'Demo Customer 10', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 265 HOUR), DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    12, 'Demo Customer 12', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 25 HOUR), DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    13, 'Demo Customer 13', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 49 HOUR), DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    14, 'Demo Customer 14', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    15, 'Demo Customer 15', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    16, 'Demo Customer 16', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    17, 'Demo Customer 17', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    18, 'Demo Customer 18', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    19, 'Demo Customer 19', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 193 HOUR), DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    20, 'Demo Customer 20', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 217 HOUR), DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    22, 'Demo Customer 22', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 265 HOUR), DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    23, 'Demo Customer 23', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 289 HOUR), DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    24, 'Demo Customer 24', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 25 HOUR), DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    25, 'Demo Customer 25', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 49 HOUR), DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    26, 'Demo Customer 26', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    27, 'Demo Customer 27', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 97 HOUR), DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    28, 'Demo Customer 28', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    29, 'Demo Customer 29', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 145 HOUR), DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    30, 'Demo Customer 30', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_device_acknowledgments (
    ticket_device_id, customer_name_at_signing, signature_path, policy_version, accepted, signature_source, signed_at, recorded_at
  ) VALUES (
    31, 'Demo Customer 02', 'assets/demo/acknowledgment.svg', 'DEMO-v1', 1, 'SIMULATED_DEMO', DATE_ADD(@seed_start, INTERVAL 73 HOUR), DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );

  -- ticket_intake_gate_events: 250 sample rows.
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 2, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    3, 3, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    4, 4, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    5, 5, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    6, 6, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    7, 7, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    8, 8, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    9, 9, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    10, 10, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    12, 12, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    13, 13, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    14, 14, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    15, 15, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    16, 16, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    17, 17, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    18, 18, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    19, 19, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    20, 20, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    22, 22, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    23, 23, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    24, 24, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    25, 25, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    26, 26, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    27, 27, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    28, 28, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    29, 29, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'CUSTOMER_MATCH', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'DEVICE_IDENTITY', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'SYMPTOMS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'CONDITION_NOTES', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'ACCESSORY_CHECKLIST', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'REQUIRED_PHOTOS', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'CONDITION_SIGNOFF', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'TERMS_ACCEPTANCE', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    30, 30, 'DUPLICATE_CHECK', 1, 'Simulated intake evidence; not a real customer approval.', 3, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'DEVICE_IDENTITY', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'SYMPTOMS', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'CONDITION_NOTES', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'ACCESSORY_CHECKLIST', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'REQUIRED_PHOTOS', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'CONDITION_SIGNOFF', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_intake_gate_events (
    ticket_id, ticket_device_id, gate_code, passed, details, checked_by_user_id, checked_at
  ) VALUES (
    2, 31, 'DUPLICATE_CHECK', 1, 'Second-device simulated evidence.', 3, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );

  -- ticket_status_events: 124 sample rows.
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    1, NULL, 'DRAFT', 'USER', 3, 'Internal sample event.', 'Sample stage: DRAFT', 0, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    2, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    3, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    3, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    4, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    4, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    4, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 123 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    5, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    5, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 146 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    5, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 147 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    5, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    6, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    6, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 170 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    6, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 171 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    6, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    6, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 194 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 195 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    7, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 198 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 218 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 219 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 221 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 222 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    8, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 223 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 242 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 243 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 244 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 245 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 246 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 247 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    9, 'READY_FOR_PICKUP', 'COMPLETED', 'USER', 4, 'Internal sample event.', 'Sample stage: COMPLETED', 1, DATE_ADD(@seed_start, INTERVAL 248 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    10, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    10, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 266 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    10, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 267 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    10, 'AWAITING_CUSTOMER_APPROVAL', 'CANCELLED', 'USER', 4, 'Internal sample event.', 'Sample stage: CANCELLED', 1, DATE_ADD(@seed_start, INTERVAL 268 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    11, NULL, 'DRAFT', 'USER', 3, 'Internal sample event.', 'Sample stage: DRAFT', 0, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    12, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    13, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    13, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 50 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    14, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    14, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 74 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    14, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 75 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    15, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    15, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    15, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 99 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    15, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    16, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    16, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    16, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 123 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    16, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    16, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 146 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 147 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    17, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 150 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 170 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 171 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 174 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    18, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 175 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 193 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 194 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 195 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 198 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 199 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    19, 'READY_FOR_PICKUP', 'COMPLETED', 'USER', 4, 'Internal sample event.', 'Sample stage: COMPLETED', 1, DATE_ADD(@seed_start, INTERVAL 200 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    20, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 217 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    20, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 218 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    20, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 219 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    20, 'AWAITING_CUSTOMER_APPROVAL', 'CANCELLED', 'USER', 4, 'Internal sample event.', 'Sample stage: CANCELLED', 1, DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    21, NULL, 'DRAFT', 'USER', 3, 'Internal sample event.', 'Sample stage: DRAFT', 0, DATE_ADD(@seed_start, INTERVAL 241 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    22, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 265 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    23, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 289 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    23, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 290 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    24, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 25 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    24, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 26 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    24, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 27 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    25, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    25, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 50 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    25, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 51 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    25, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 52 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    26, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    26, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 74 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    26, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 75 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    26, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 76 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    26, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 77 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 97 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 99 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 101 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    27, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 102 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 121 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 123 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 126 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    28, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 127 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 145 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 146 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 147 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_PARTS', 1, DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'AWAITING_PARTS', 'IN_PROGRESS', 'USER', 4, 'Internal sample event.', 'Sample stage: IN_PROGRESS', 1, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'IN_PROGRESS', 'TESTING', 'USER', 4, 'Internal sample event.', 'Sample stage: TESTING', 1, DATE_ADD(@seed_start, INTERVAL 150 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'TESTING', 'READY_FOR_PICKUP', 'USER', 4, 'Internal sample event.', 'Sample stage: READY_FOR_PICKUP', 1, DATE_ADD(@seed_start, INTERVAL 151 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    29, 'READY_FOR_PICKUP', 'COMPLETED', 'USER', 4, 'Internal sample event.', 'Sample stage: COMPLETED', 1, DATE_ADD(@seed_start, INTERVAL 152 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    30, NULL, 'RECEIVED', 'USER', 3, 'Internal sample event.', 'Sample stage: RECEIVED', 1, DATE_ADD(@seed_start, INTERVAL 169 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    30, 'RECEIVED', 'DIAGNOSING', 'USER', 4, 'Internal sample event.', 'Sample stage: DIAGNOSING', 1, DATE_ADD(@seed_start, INTERVAL 170 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    30, 'DIAGNOSING', 'AWAITING_CUSTOMER_APPROVAL', 'USER', 4, 'Internal sample event.', 'Sample stage: AWAITING_CUSTOMER_APPROVAL', 1, DATE_ADD(@seed_start, INTERVAL 171 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, from_status, to_status, actor_type, actor_user_id, internal_note, public_summary, is_customer_visible, created_at
  ) VALUES (
    30, 'AWAITING_CUSTOMER_APPROVAL', 'CANCELLED', 'USER', 4, 'Internal sample event.', 'Sample stage: CANCELLED', 1, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO ticket_status_events (
    ticket_device_id, to_status, actor_user_id, public_summary, is_customer_visible, created_at
  ) VALUES (
    31, 'RECEIVED', 3, 'Second device received.', 1, DATE_ADD(@seed_start, INTERVAL 73 HOUR)
  );

  -- ticket_correction_notes: 1 sample rows.
  INSERT INTO ticket_correction_notes (
    ticket_id, ticket_device_id, author_user_id, reason, correction_note
  ) VALUES (
    2, 2, 3, 'Clarify intake condition', 'Customer confirmed the scratch existed before intake.'
  );

  -- device_transfers: 2 sample rows.
  INSERT INTO device_transfers (
    device_transfer_id, transfer_code, from_branch_id, to_branch_id, status, initiated_by_user_id, dispatched_at, received_confirmed_by_user_id, received_confirmed_at
  ) VALUES (
    1, 'DEMO-DT-1', 2, 3, 'IN_TRANSIT', 2, DATE_ADD(@seed_start, INTERVAL 147 HOUR), NULL, NULL
  );
  INSERT INTO device_transfers (
    device_transfer_id, transfer_code, from_branch_id, to_branch_id, status, initiated_by_user_id, dispatched_at, received_confirmed_by_user_id, received_confirmed_at
  ) VALUES (
    2, 'DEMO-DT-2', 3, 1, 'RECEIVED', 2, DATE_ADD(@seed_start, INTERVAL 171 HOUR), 2, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );

  -- device_transfer_items: 2 sample rows.
  INSERT INTO device_transfer_items (
    device_transfer_id, ticket_device_id, from_location_id, transit_location_id, to_location_id, item_status, sent_condition_note, receipt_condition_note, received_by_user_id, received_at
  ) VALUES (
    1, 5, 21, 23, 32, 'IN_TRANSIT', 'Case condition unchanged.', NULL, NULL, NULL
  );
  INSERT INTO device_transfer_items (
    device_transfer_id, ticket_device_id, from_location_id, transit_location_id, to_location_id, item_status, sent_condition_note, receipt_condition_note, received_by_user_id, received_at
  ) VALUES (
    2, 6, 31, 33, 12, 'RECEIVED', 'Case condition unchanged.', 'Received intact.', 2, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );

  -- device_custody_events: 62 sample rows.
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    1, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    2, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    3, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    3, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    4, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    4, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    5, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, device_transfer_id, performed_by_user_id, happened_at
  ) VALUES (
    5, 'TRANSFER_DISPATCHED', 21, 23, 1, 5, DATE_ADD(@seed_start, INTERVAL 147 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    6, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, device_transfer_id, performed_by_user_id, happened_at
  ) VALUES (
    6, 'TRANSFER_DISPATCHED', 31, 33, 2, 5, DATE_ADD(@seed_start, INTERVAL 171 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, device_transfer_id, performed_by_user_id, happened_at
  ) VALUES (
    6, 'TRANSFER_RECEIVED', 33, 12, 2, 2, DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    7, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    7, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 194 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    8, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    8, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 218 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    9, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    9, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 242 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    9, 'RETURNED_TO_CUSTOMER', 32, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    10, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    10, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 266 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    10, 'RETURNED_TO_CUSTOMER', 12, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    11, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    12, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    13, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    13, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 50 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    14, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    14, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 74 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    15, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    15, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    16, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    16, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    17, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    17, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 146 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    18, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    18, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 170 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    19, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 192 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    19, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 194 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    19, 'RETURNED_TO_CUSTOMER', 12, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    20, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 216 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    20, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 218 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    20, 'RETURNED_TO_CUSTOMER', 22, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    21, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 240 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    22, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 264 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    23, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 288 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    23, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 290 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    24, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 24 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    24, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 26 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    25, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    25, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 50 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    26, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    26, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 74 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    27, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 96 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    27, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 98 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    28, 'INTAKE_RECEIVED', 11, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 120 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    28, 'LOCATION_CHANGED', 11, 12, 4, DATE_ADD(@seed_start, INTERVAL 122 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    29, 'INTAKE_RECEIVED', 21, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 144 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    29, 'LOCATION_CHANGED', 21, 22, 4, DATE_ADD(@seed_start, INTERVAL 146 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    29, 'RETURNED_TO_CUSTOMER', 22, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    30, 'INTAKE_RECEIVED', 31, 3, 'Sample device received.', DATE_ADD(@seed_start, INTERVAL 168 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    30, 'LOCATION_CHANGED', 31, 32, 4, DATE_ADD(@seed_start, INTERVAL 170 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, from_location_id, to_location_id, performed_by_user_id, note, happened_at
  ) VALUES (
    30, 'RETURNED_TO_CUSTOMER', 32, 99, 3, 'Simulated physical handover.', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO device_custody_events (
    ticket_device_id, event_type, to_location_id, performed_by_user_id, happened_at
  ) VALUES (
    31, 'INTAKE_RECEIVED', 21, 3, DATE_ADD(@seed_start, INTERVAL 72 HOUR)
  );

  -- parts: 6 sample rows.
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    1, 'DEMO-P001', '500 GB SSD', 'Fictional inventory for integrity tests'
  );
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    2, 'DEMO-P002', '8 GB RAM', 'Fictional inventory for integrity tests'
  );
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    3, 'DEMO-P003', 'Laptop charger', 'Fictional inventory for integrity tests'
  );
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    4, 'DEMO-P004', 'Cooling fan', 'Fictional inventory for integrity tests'
  );
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    5, 'DEMO-P005', 'Keyboard', 'Fictional inventory for integrity tests'
  );
  INSERT INTO parts (
    part_id, sku, part_name, description
  ) VALUES (
    6, 'DEMO-P006', 'CMOS battery', 'Fictional inventory for integrity tests'
  );

  -- branch_inventory: 18 sample rows.
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 1, 15, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 2, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 3, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 4, 18, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 5, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    1, 6, 19, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 1, 23, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 2, 18, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 3, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 4, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 5, 18, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    2, 6, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 1, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 2, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 3, 18, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 4, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 5, 20, 5
  );
  INSERT INTO branch_inventory (
    branch_id, part_id, quantity_on_hand, reorder_level
  ) VALUES (
    3, 6, 19, 5
  );

  -- inventory_transfers: 1 sample rows.
  INSERT INTO inventory_transfers (
    inventory_transfer_id, transfer_code, from_branch_id, to_branch_id, status, initiated_by_user_id, dispatched_at, received_by_user_id, received_at
  ) VALUES (
    1, 'DEMO-IT-0001', 1, 2, 'RECEIVED', 2, DATE_ADD(@seed_start, INTERVAL 48 HOUR), 2, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );

  -- inventory_transfer_items: 1 sample rows.
  INSERT INTO inventory_transfer_items (
    inventory_transfer_item_id, inventory_transfer_id, part_id, quantity, received_quantity, item_status, receipt_note
  ) VALUES (
    1, 1, 1, 3, 3, 'RECEIVED', 'All three demo units received.'
  );

  -- inventory_transactions: 32 sample rows.
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 1, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 1
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 1, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 2
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 1, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 3
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 2, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 4
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 2, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 5
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 2, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 6
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 3, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 7
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 3, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 8
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 3, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 9
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 4, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 10
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 4, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 11
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 4, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 12
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 5, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 13
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 5, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 14
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 5, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 15
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    1, 6, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 16
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    2, 6, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 17
  );
  INSERT INTO inventory_transactions (
    branch_id, part_id, transaction_type, quantity_delta, reference_code, reason, performed_by_user_id, created_at, inventory_transaction_id
  ) VALUES (
    3, 6, 'INITIAL_STOCK', 20, 'DEMO-OPENING', 'Opening sample stock', 2, DATE_ADD(@seed_start, INTERVAL 0 HOUR), 18
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1001, 1, 6, 'TICKET_USE', -1, 6, 'DEMO-USE-6', 4, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1002, 1, 1, 'TICKET_USE', -1, 7, 'DEMO-USE-7', 4, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1003, 2, 2, 'TICKET_USE', -1, 8, 'DEMO-USE-8', 4, DATE_ADD(@seed_start, INTERVAL 221 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1004, 3, 3, 'TICKET_USE', -1, 9, 'DEMO-USE-9', 4, DATE_ADD(@seed_start, INTERVAL 245 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1005, 1, 4, 'TICKET_USE', -1, 16, 'DEMO-USE-16', 4, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1006, 2, 5, 'TICKET_USE', -1, 17, 'DEMO-USE-17', 4, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1007, 3, 6, 'TICKET_USE', -1, 18, 'DEMO-USE-18', 4, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1008, 1, 1, 'TICKET_USE', -1, 19, 'DEMO-USE-19', 4, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1009, 2, 2, 'TICKET_USE', -1, 26, 'DEMO-USE-26', 4, DATE_ADD(@seed_start, INTERVAL 77 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1010, 3, 3, 'TICKET_USE', -1, 27, 'DEMO-USE-27', 4, DATE_ADD(@seed_start, INTERVAL 101 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1011, 1, 4, 'TICKET_USE', -1, 28, 'DEMO-USE-28', 4, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, ticket_device_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    1012, 2, 5, 'TICKET_USE', -1, 29, 'DEMO-USE-29', 4, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, inventory_transfer_item_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    2001, 1, 1, 'TRANSFER_OUT', -3, 1, 'DEMO-IT-0001', 2, DATE_ADD(@seed_start, INTERVAL 48 HOUR)
  );
  INSERT INTO inventory_transactions (
    inventory_transaction_id, branch_id, part_id, transaction_type, quantity_delta, inventory_transfer_item_id, reference_code, performed_by_user_id, created_at
  ) VALUES (
    2002, 2, 1, 'TRANSFER_IN', 3, 1, 'DEMO-IT-0001', 2, DATE_ADD(@seed_start, INTERVAL 49 HOUR)
  );

  -- ticket_parts_used: 12 sample rows.
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    1, 6, 6, 1001, 1, 90.0, 4, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    2, 7, 1, 1002, 1, 1380.0, 4, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    3, 8, 2, 1003, 1, 960.0, 4, DATE_ADD(@seed_start, INTERVAL 221 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    4, 9, 3, 1004, 1, 540.0, 4, DATE_ADD(@seed_start, INTERVAL 245 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    5, 16, 4, 1005, 1, 450.0, 4, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    6, 17, 5, 1006, 1, 660.0, 4, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    7, 18, 6, 1007, 1, 90.0, 4, DATE_ADD(@seed_start, INTERVAL 173 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    8, 19, 1, 1008, 1, 1380.0, 4, DATE_ADD(@seed_start, INTERVAL 197 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    9, 26, 2, 1009, 1, 960.0, 4, DATE_ADD(@seed_start, INTERVAL 77 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    10, 27, 3, 1010, 1, 540.0, 4, DATE_ADD(@seed_start, INTERVAL 101 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    11, 28, 4, 1011, 1, 450.0, 4, DATE_ADD(@seed_start, INTERVAL 125 HOUR)
  );
  INSERT INTO ticket_parts_used (
    ticket_part_use_id, ticket_device_id, part_id, inventory_transaction_id, quantity_used, unit_cost_at_use, added_by_user_id, created_at
  ) VALUES (
    12, 29, 5, 1012, 1, 660.0, 4, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );

  -- repair_quotes: 23 sample rows.
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    1, 4, 1, 'EXPIRED', 500, 500, 4, DATE_ADD(@seed_start, INTERVAL 122 HOUR), DATE_ADD(@seed_start, INTERVAL 123 HOUR), DATE_ADD(@seed_start, INTERVAL 121 HOUR), DATE_ADD(@seed_start, INTERVAL 123 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    2, 4, 2, 'AWAITING_CUSTOMER', 650, 750, 1400, 4, DATE_ADD(@seed_start, INTERVAL 123 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 122 HOUR), DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    3, 5, 1, 'APPROVED', 650, 1100, 1750, 4, DATE_ADD(@seed_start, INTERVAL 147 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 146 HOUR), DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    4, 6, 1, 'SUPERSEDED', 500, 500, 4, DATE_ADD(@seed_start, INTERVAL 170 HOUR), DATE_ADD(@seed_start, INTERVAL 171 HOUR), DATE_ADD(@seed_start, INTERVAL 169 HOUR), DATE_ADD(@seed_start, INTERVAL 171 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    5, 6, 2, 'APPROVED', 650, 150, 800, 4, DATE_ADD(@seed_start, INTERVAL 171 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 170 HOUR), DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    6, 7, 1, 'APPROVED', 650, 2300, 2950, 4, DATE_ADD(@seed_start, INTERVAL 195 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 194 HOUR), DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    7, 8, 1, 'APPROVED', 650, 1600, 2250, 4, DATE_ADD(@seed_start, INTERVAL 219 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 218 HOUR), DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    8, 9, 1, 'APPROVED', 650, 900, 1550, 4, DATE_ADD(@seed_start, INTERVAL 243 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 242 HOUR), DATE_ADD(@seed_start, INTERVAL 244 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    9, 10, 1, 'DECLINED', 650, 750, 1400, 4, DATE_ADD(@seed_start, INTERVAL 267 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 266 HOUR), DATE_ADD(@seed_start, INTERVAL 268 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    10, 14, 1, 'AWAITING_CUSTOMER', 650, 1600, 2250, 4, DATE_ADD(@seed_start, INTERVAL 75 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 74 HOUR), DATE_ADD(@seed_start, INTERVAL 76 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    11, 15, 1, 'APPROVED', 650, 900, 1550, 4, DATE_ADD(@seed_start, INTERVAL 99 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 98 HOUR), DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    12, 16, 1, 'APPROVED', 650, 750, 1400, 4, DATE_ADD(@seed_start, INTERVAL 123 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 122 HOUR), DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    13, 17, 1, 'APPROVED', 650, 1100, 1750, 4, DATE_ADD(@seed_start, INTERVAL 147 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 146 HOUR), DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    14, 18, 1, 'APPROVED', 650, 150, 800, 4, DATE_ADD(@seed_start, INTERVAL 171 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 170 HOUR), DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    15, 19, 1, 'APPROVED', 650, 2300, 2950, 4, DATE_ADD(@seed_start, INTERVAL 195 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 194 HOUR), DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    16, 20, 1, 'DECLINED', 650, 1600, 2250, 4, DATE_ADD(@seed_start, INTERVAL 219 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 218 HOUR), DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    17, 24, 1, 'AWAITING_CUSTOMER', 650, 150, 800, 4, DATE_ADD(@seed_start, INTERVAL 27 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 26 HOUR), DATE_ADD(@seed_start, INTERVAL 28 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    18, 25, 1, 'APPROVED', 650, 2300, 2950, 4, DATE_ADD(@seed_start, INTERVAL 51 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 50 HOUR), DATE_ADD(@seed_start, INTERVAL 52 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    19, 26, 1, 'APPROVED', 650, 1600, 2250, 4, DATE_ADD(@seed_start, INTERVAL 75 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 74 HOUR), DATE_ADD(@seed_start, INTERVAL 76 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    20, 27, 1, 'APPROVED', 650, 900, 1550, 4, DATE_ADD(@seed_start, INTERVAL 99 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 98 HOUR), DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    21, 28, 1, 'APPROVED', 650, 750, 1400, 4, DATE_ADD(@seed_start, INTERVAL 123 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 122 HOUR), DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    22, 29, 1, 'APPROVED', 650, 1100, 1750, 4, DATE_ADD(@seed_start, INTERVAL 147 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 146 HOUR), DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quotes (
    quote_id, ticket_device_id, version_no, quote_status, labor_amount, parts_amount, total_amount, prepared_by_user_id, sent_at, expires_at, created_at, updated_at
  ) VALUES (
    23, 30, 1, 'DECLINED', 650, 150, 800, 4, DATE_ADD(@seed_start, INTERVAL 171 HOUR), DATE_ADD(@seed_now, INTERVAL 7 DAY), DATE_ADD(@seed_start, INTERVAL 170 HOUR), DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );

  -- repair_quote_items: 44 sample rows.
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    1, 1, 'LABOR', 'Earlier sample quote', 1, 500, 500
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    2, 2, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    3, 2, 4, 'PART', 'Replacement part', 1, 750, 750
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    4, 3, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    5, 3, 5, 'PART', 'Replacement part', 1, 1100, 1100
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    6, 4, 'LABOR', 'Earlier sample quote', 1, 500, 500
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    7, 5, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    8, 5, 6, 'PART', 'Replacement part', 1, 150, 150
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    9, 6, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    10, 6, 1, 'PART', 'Replacement part', 1, 2300, 2300
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    11, 7, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    12, 7, 2, 'PART', 'Replacement part', 1, 1600, 1600
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    13, 8, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    14, 8, 3, 'PART', 'Replacement part', 1, 900, 900
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    15, 9, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    16, 9, 4, 'PART', 'Replacement part', 1, 750, 750
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    17, 10, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    18, 10, 2, 'PART', 'Replacement part', 1, 1600, 1600
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    19, 11, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    20, 11, 3, 'PART', 'Replacement part', 1, 900, 900
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    21, 12, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    22, 12, 4, 'PART', 'Replacement part', 1, 750, 750
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    23, 13, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    24, 13, 5, 'PART', 'Replacement part', 1, 1100, 1100
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    25, 14, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    26, 14, 6, 'PART', 'Replacement part', 1, 150, 150
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    27, 15, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    28, 15, 1, 'PART', 'Replacement part', 1, 2300, 2300
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    29, 16, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    30, 16, 2, 'PART', 'Replacement part', 1, 1600, 1600
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    31, 17, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    32, 17, 6, 'PART', 'Replacement part', 1, 150, 150
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    33, 18, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    34, 18, 1, 'PART', 'Replacement part', 1, 2300, 2300
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    35, 19, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    36, 19, 2, 'PART', 'Replacement part', 1, 1600, 1600
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    37, 20, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    38, 20, 3, 'PART', 'Replacement part', 1, 900, 900
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    39, 21, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    40, 21, 4, 'PART', 'Replacement part', 1, 750, 750
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    41, 22, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    42, 22, 5, 'PART', 'Replacement part', 1, 1100, 1100
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    43, 23, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_quote_items (
    quote_item_id, quote_id, part_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    44, 23, 6, 'PART', 'Replacement part', 1, 150, 150
  );

  -- repair_quote_decisions: 18 sample rows.
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    3, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 05', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    5, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 06', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    6, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 07', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    7, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 08', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    8, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 09', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 244 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    9, 'DECLINED', 'SIMULATED_DEMO', 3, 'Demo Customer 10', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 268 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    11, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 15', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    12, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 16', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    13, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 17', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    14, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 18', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    15, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 19', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 196 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    16, 'DECLINED', 'SIMULATED_DEMO', 3, 'Demo Customer 20', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 220 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    18, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 25', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 52 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    19, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 26', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 76 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    20, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 27', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 100 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    21, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 28', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 124 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    22, 'APPROVED', 'SIMULATED_DEMO', 3, 'Demo Customer 29', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 148 HOUR)
  );
  INSERT INTO repair_quote_decisions (
    quote_id, decision, decision_source, recorded_by_user_id, customer_name_at_decision, customer_comment, decision_at
  ) VALUES (
    23, 'DECLINED', 'SIMULATED_DEMO', 3, 'Demo Customer 30', 'Fictional decision for testing.', DATE_ADD(@seed_start, INTERVAL 172 HOUR)
  );

  -- repair_bills: 6 sample rows.
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    1, 8, 1, 'DEMO-BILL-0001', 'ISSUED', 2250, 2250, 3, DATE_ADD(@seed_start, INTERVAL 225 HOUR), DATE_ADD(@seed_start, INTERVAL 225 HOUR), DATE_ADD(@seed_start, INTERVAL 225 HOUR)
  );
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    2, 9, 1, 'DEMO-BILL-0002', 'ISSUED', 1550, 1550, 3, DATE_ADD(@seed_start, INTERVAL 249 HOUR), DATE_ADD(@seed_start, INTERVAL 249 HOUR), DATE_ADD(@seed_start, INTERVAL 249 HOUR)
  );
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    3, 18, 1, 'DEMO-BILL-0003', 'ISSUED', 800, 800, 3, DATE_ADD(@seed_start, INTERVAL 177 HOUR), DATE_ADD(@seed_start, INTERVAL 177 HOUR), DATE_ADD(@seed_start, INTERVAL 177 HOUR)
  );
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    4, 19, 1, 'DEMO-BILL-0004', 'ISSUED', 2950, 2950, 3, DATE_ADD(@seed_start, INTERVAL 201 HOUR), DATE_ADD(@seed_start, INTERVAL 201 HOUR), DATE_ADD(@seed_start, INTERVAL 201 HOUR)
  );
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    5, 28, 1, 'DEMO-BILL-0005', 'ISSUED', 1400, 1400, 3, DATE_ADD(@seed_start, INTERVAL 129 HOUR), DATE_ADD(@seed_start, INTERVAL 129 HOUR), DATE_ADD(@seed_start, INTERVAL 129 HOUR)
  );
  INSERT INTO repair_bills (
    bill_id, ticket_id, version_no, bill_number, bill_status, subtotal, total_amount, prepared_by_user_id, issued_at, created_at, updated_at
  ) VALUES (
    6, 29, 1, 'DEMO-BILL-0006', 'ISSUED', 1750, 1750, 3, DATE_ADD(@seed_start, INTERVAL 153 HOUR), DATE_ADD(@seed_start, INTERVAL 153 HOUR), DATE_ADD(@seed_start, INTERVAL 153 HOUR)
  );

  -- repair_bill_items: 12 sample rows.
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    1, 8, 11, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    1, 8, 12, 3, 'PART', 'Replacement part', 1, 1600, 1600
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    2, 9, 13, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    2, 9, 14, 4, 'PART', 'Replacement part', 1, 900, 900
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    3, 18, 25, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    3, 18, 26, 7, 'PART', 'Replacement part', 1, 150, 150
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    4, 19, 27, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    4, 19, 28, 8, 'PART', 'Replacement part', 1, 2300, 2300
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    5, 28, 39, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    5, 28, 40, 11, 'PART', 'Replacement part', 1, 750, 750
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    6, 29, 41, 'LABOR', 'Diagnostic and installation labor', 1, 650, 650
  );
  INSERT INTO repair_bill_items (
    bill_id, ticket_device_id, quote_item_id, ticket_part_use_id, item_type, description, quantity, unit_price, line_total
  ) VALUES (
    6, 29, 42, 12, 'PART', 'Replacement part', 1, 1100, 1100
  );

  -- ticket_operational_events: 3 sample rows.
  INSERT INTO ticket_operational_events (
    ticket_device_id, event_type, note, customer_visible, recorded_by_user_id, occurred_at
  ) VALUES (
    5, 'ON_HOLD_STARTED', 'Sample part is on order.', 1, 4, DATE_ADD(@seed_start, INTERVAL 149 HOUR)
  );
  INSERT INTO ticket_operational_events (
    ticket_device_id, event_type, note, customer_visible, recorded_by_user_id, occurred_at
  ) VALUES (
    15, 'ON_HOLD_STARTED', 'Sample part is on order.', 1, 4, DATE_ADD(@seed_start, INTERVAL 101 HOUR)
  );
  INSERT INTO ticket_operational_events (
    ticket_device_id, event_type, note, customer_visible, recorded_by_user_id, occurred_at
  ) VALUES (
    25, 'ON_HOLD_STARTED', 'Sample part is on order.', 1, 4, DATE_ADD(@seed_start, INTERVAL 53 HOUR)
  );

  -- mock_notification_log: 27 sample rows.
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    2, 2, 'DEMO_STATUS_UPDATE', 'SMS', 'c***2@example.test', 'demo.status', 'Simulated status: RECEIVED', 'SIMULATED', 'SIMULATED', '7f59734744a3eb95328bed664370524d7deb6e5eda0db417278524292240a8ce', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    3, 3, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***3@example.test', 'demo.status', 'Simulated status: DIAGNOSING', 'SIMULATED', 'SIMULATED', 'd62e9a22f2d44ead018605cbc2f341c4684ab5c5507951023850cb531bd8bb22', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    4, 4, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***4@example.test', 'demo.status', 'Simulated status: AWAITING_CUSTOMER_APPROVAL', 'SIMULATED', 'SIMULATED', '83594faa7b7ccf48b89440c3c93bcc15123581f055171f8b1ec7a0a7f87c04cd', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    5, 5, 'DEMO_STATUS_UPDATE', 'SMS', 'c***5@example.test', 'demo.status', 'Simulated status: AWAITING_PARTS', 'SIMULATED', 'SIMULATED', '55b6c504ac922f254935217d25f69dad19110d0e02127f50957a5c6f647de089', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    6, 6, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***6@example.test', 'demo.status', 'Simulated status: IN_PROGRESS', 'SIMULATED', 'SIMULATED', 'a3b2e3162a4597b6216b14872324a3bc7469fa0e0550fdf2fd937ca935c9b8b9', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    7, 7, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***7@example.test', 'demo.status', 'Simulated status: TESTING', 'SIMULATED', 'SIMULATED', '5ec67026ac773dfa23b2078faaa894f96eebee6ee3e311fc7988a27df53ada7a', DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    8, 8, 'DEMO_STATUS_UPDATE', 'SMS', 'c***8@example.test', 'demo.status', 'Simulated status: READY_FOR_PICKUP', 'SIMULATED', 'SIMULATED', 'da37275875a3f13e3696f0819c62a4953c9fb1ed2f305013d438b00cd095833f', DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    9, 9, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***9@example.test', 'demo.status', 'Simulated status: COMPLETED', 'SIMULATED', 'SIMULATED', '99563950bc2ad50ceb334ff480e267b09bbf57c7c41982bf0c417ce3c68dc234', DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    10, 10, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***10@example.test', 'demo.status', 'Simulated status: CANCELLED', 'SIMULATED', 'SIMULATED', '4f18781ca0bf48b851402bb48583327ec17eb2a2b5379687f691ccc25baad504', DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    12, 12, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***12@example.test', 'demo.status', 'Simulated status: RECEIVED', 'SIMULATED', 'SIMULATED', '04174940f7c0c4c77e01c9c4acc561789928334e857a7af53901fb17cc6870fa', DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    13, 13, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***13@example.test', 'demo.status', 'Simulated status: DIAGNOSING', 'SIMULATED', 'SIMULATED', '3f38a2363f3691adaf609278a2cae4beae1c72e791afb10841272bda6b803760', DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    14, 14, 'DEMO_STATUS_UPDATE', 'SMS', 'c***14@example.test', 'demo.status', 'Simulated status: AWAITING_CUSTOMER_APPROVAL', 'SIMULATED', 'SIMULATED', 'fab5bf8f568fa91f4683d90e7a370ef4b3f76e36d45aae365a7b47d27072e18a', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    15, 15, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***15@example.test', 'demo.status', 'Simulated status: AWAITING_PARTS', 'SIMULATED', 'SIMULATED', '419a2e0bade193cf136b2ff3ecd6decdca10205b375ca3110877acc106e44bcd', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    16, 16, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***16@example.test', 'demo.status', 'Simulated status: IN_PROGRESS', 'SIMULATED', 'SIMULATED', '2b509f6c401240bf148259ea372a6a720d48107c8d1bc5f20ca2764a1da6d6eb', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    17, 17, 'DEMO_STATUS_UPDATE', 'SMS', 'c***17@example.test', 'demo.status', 'Simulated status: TESTING', 'SIMULATED', 'SIMULATED', 'ec6f261b53ce8d357a9f7cc92e4f78332dac32e05deb78d2022ae9a79cf83921', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    18, 18, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***18@example.test', 'demo.status', 'Simulated status: READY_FOR_PICKUP', 'SIMULATED', 'SIMULATED', 'fa99b46bf6c66f64317597e907c765b6743ca6e9f76b006ee031febe2ac42051', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    19, 19, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***19@example.test', 'demo.status', 'Simulated status: COMPLETED', 'SIMULATED', 'SIMULATED', '7398ecce2413998e63f214ceb81902d66e95a8d27a907c6487f0a1add1ec4489', DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    20, 20, 'DEMO_STATUS_UPDATE', 'SMS', 'c***20@example.test', 'demo.status', 'Simulated status: CANCELLED', 'SIMULATED', 'SIMULATED', '6d47ca7994e9f5b5e01efc1acc7ce440628e34c65669c846dae98a67e41e4a8c', DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    22, 22, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***22@example.test', 'demo.status', 'Simulated status: RECEIVED', 'SIMULATED', 'SIMULATED', '05cb0003ca80922e95e418f4c024f2ed1ada9e0ba4e9376afe55bd600eb5c75f', DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    23, 23, 'DEMO_STATUS_UPDATE', 'SMS', 'c***23@example.test', 'demo.status', 'Simulated status: DIAGNOSING', 'SIMULATED', 'SIMULATED', 'b771133df14847557a93458032c6a6d01700920b08fff6edf3a38dbd315ccd49', DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    24, 24, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***24@example.test', 'demo.status', 'Simulated status: AWAITING_CUSTOMER_APPROVAL', 'SIMULATED', 'SIMULATED', '5f9a6e87eda3fa588dc607902859236a5434ee24808962eb25047a262e816e0b', DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    25, 25, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***25@example.test', 'demo.status', 'Simulated status: AWAITING_PARTS', 'SIMULATED', 'SIMULATED', 'e6b3f732a6e3e2b204615d37ea4e5be46e0f8a010350a052cffc2db1aac6a7c9', DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    26, 26, 'DEMO_STATUS_UPDATE', 'SMS', 'c***26@example.test', 'demo.status', 'Simulated status: IN_PROGRESS', 'SIMULATED', 'SIMULATED', 'fd2f8c7aa53bf775610c4b3e6dbd5b6651e1786528ac8eac8a7c608a7532f053', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    27, 27, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***27@example.test', 'demo.status', 'Simulated status: TESTING', 'SIMULATED', 'SIMULATED', '4bdf4fb388d59a6dea0a50e97a55e162a8c20965be7662c503648ec8afdaaac8', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    28, 28, 'DEMO_STATUS_UPDATE', 'EMAIL', 'c***28@example.test', 'demo.status', 'Simulated status: READY_FOR_PICKUP', 'SIMULATED', 'SIMULATED', '6d3a6ac0625368c1b46df1c21eddae35ca6575682f6235cabd7b799e13b918cc', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    29, 29, 'DEMO_STATUS_UPDATE', 'SMS', 'c***29@example.test', 'demo.status', 'Simulated status: COMPLETED', 'SIMULATED', 'SIMULATED', 'c2c357d1bd8424ba95d20363f90196684f323bc8def83e7b7e98e75ac2a5b797', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO mock_notification_log (
    ticket_id, ticket_device_id, event_type, channel, recipient_masked, template_key, rendered_preview, delivery_mode, delivery_status, dedupe_key, triggered_at
  ) VALUES (
    30, 30, 'DEMO_STATUS_UPDATE', 'IN_APP', 'c***30@example.test', 'demo.status', 'Simulated status: CANCELLED', 'SIMULATED', 'SIMULATED', 'afea03c6ee4f04c90e2603bf36fe31ac9d2b6bc7a303152c49fdebea05baadbd', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );

  -- audit_events: 30 sample rows.
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 1, 'DEMO_SEEDED', '{"record_stage": "DRAFT", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 2, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 3, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 4, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 5, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 6, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 7, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 8, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 9, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 10, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 11, 'DEMO_SEEDED', '{"record_stage": "DRAFT", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 12, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 13, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 14, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 15, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 16, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 17, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 18, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 19, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 204 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 20, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 228 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 21, 'DEMO_SEEDED', '{"record_stage": "DRAFT", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 252 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 22, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 276 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 23, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 300 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 24, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 36 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 25, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 60 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 26, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 84 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 27, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 108 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 28, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 132 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 29, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 156 HOUR)
  );
  INSERT INTO audit_events (
    actor_type, actor_user_id, entity_type, entity_id, action, after_data, reason, created_at
  ) VALUES (
    'USER', 3, 'repair_ticket', 30, 'DEMO_SEEDED', '{"record_stage": "ISSUED", "demo": true}', 'Synthetic scenario, not a real service record.', DATE_ADD(@seed_start, INTERVAL 180 HOUR)
  );

  -- password_reset_tokens: 2 sample rows.
  INSERT INTO password_reset_tokens (
    user_id, token_hash, expires_at, used_at, created_at
  ) VALUES (
    1, '536ee5d743b22538002603c6b4aaa6f85ee5f76a472674fcde0fada5639ad9be', DATE_ADD(@seed_start, INTERVAL 1 HOUR), DATE_ADD(@seed_start, INTERVAL 1 HOUR), DATE_ADD(@seed_start, INTERVAL 0 HOUR)
  );
  INSERT INTO password_reset_tokens (
    user_id, token_hash, expires_at, used_at, created_at
  ) VALUES (
    2, '5195465d99b87d3cbb7a8fcd31d4292b7b29972865fe83cfe8d8d66840b1d5cc', DATE_ADD(@seed_start, INTERVAL 1 HOUR), NULL, DATE_ADD(@seed_start, INTERVAL 0 HOUR)
  );

  COMMIT;
END$$
DELIMITER ;
CALL seed_repairtrack_demo();
DROP PROCEDURE seed_repairtrack_demo;

SELECT 'Sample records inserted. Run check_demo_integrity.sql next.' AS result;
