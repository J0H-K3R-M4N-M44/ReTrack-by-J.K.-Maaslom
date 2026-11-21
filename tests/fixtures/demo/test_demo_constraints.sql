-- Intentionally invalid writes: all row changes are rolled back.
-- Must run separately, after setup and seed, in a disposable demo database.
USE repairtrack_demo;
SET @previous_sql_mode = @@SESSION.sql_mode;
SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
DROP PROCEDURE IF EXISTS test_demo_constraints;
DELIMITER $$
CREATE PROCEDURE test_demo_constraints()
BEGIN
  DECLARE error_number INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    SET SESSION sql_mode = @previous_sql_mode;
    RESIGNAL;
  END;
  IF DATABASE() <> 'repairtrack_demo' OR @@FOREIGN_KEY_CHECKS <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Use repairtrack_demo with foreign key checks enabled.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM users WHERE user_id=1 AND email='admin@example.test') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed the demo database before testing constraints.';
  END IF;
  START TRANSACTION;

  -- Expected rejection: duplicate branch code.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO branches (branch_code,branch_name) VALUES ('DEMO-1','Invalid duplicate');
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (1062) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: duplicate branch code was not rejected as expected.';
  END IF;
  SELECT 'duplicate branch code' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: orphan customer contact.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO customer_contacts (customer_id,contact_type,contact_value,normalized_value) VALUES (999999,'EMAIL','invalid@example.test','invalid@example.test');
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (1452) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: orphan customer contact was not rejected as expected.';
  END IF;
  SELECT 'orphan customer contact' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: negative stock.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    UPDATE branch_inventory SET quantity_on_hand=-1 WHERE branch_id=1 AND part_id=1;
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (3819, 4025) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: negative stock was not rejected as expected.';
  END IF;
  SELECT 'negative stock' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: zero quote quantity.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    UPDATE repair_quote_items SET quantity=0 WHERE quote_item_id=1;
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (3819, 4025) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: zero quote quantity was not rejected as expected.';
  END IF;
  SELECT 'zero quote quantity' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: incomplete issued ticket.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO repair_tickets (record_stage,intake_branch_id,accepted_by_user_id,arrival_method) VALUES ('ISSUED',1,3,'WALK_IN');
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (3819, 4025) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: incomplete issued ticket was not rejected as expected.';
  END IF;
  SELECT 'incomplete issued ticket' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: same-branch transfer.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO device_transfers (transfer_code,from_branch_id,to_branch_id,initiated_by_user_id,dispatched_at) VALUES ('DEMO-INVALID-TRANSFER',1,1,2,UTC_TIMESTAMP());
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (3819, 4025) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: same-branch transfer was not rejected as expected.';
  END IF;
  SELECT 'same-branch transfer' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: accepted acknowledgment without signature.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO ticket_device_acknowledgments (ticket_device_id,customer_name_at_signing,policy_version,accepted,recorded_at) VALUES (1,'Invalid demo','INVALID-TEST',1,UTC_TIMESTAMP());
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (3819, 4025) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: accepted acknowledgment without signature was not rejected as expected.';
  END IF;
  SELECT 'accepted acknowledgment without signature' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: invalid role enum.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    UPDATE users SET role='NOT_A_ROLE' WHERE user_id=2;
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (1265, 1406) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: invalid role enum was not rejected as expected.';
  END IF;
  SELECT 'invalid role enum' AS test_name, 'PASS' AS result, error_number AS expected_error;

  -- Expected rejection: required name missing.
  SAVEPOINT invalid_write;
  SET error_number = 0;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION, SQLWARNING
      GET DIAGNOSTICS CONDITION 1 error_number = MYSQL_ERRNO;
    INSERT INTO customers (full_name,normalized_name) VALUES (NULL,'invalid');
  END;
  ROLLBACK TO SAVEPOINT invalid_write;
  IF error_number NOT IN (1048) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FAIL: required name missing was not rejected as expected.';
  END IF;
  SELECT 'required name missing' AS test_name, 'PASS' AS result, error_number AS expected_error;

  ROLLBACK;
END$$
DELIMITER ;
CALL test_demo_constraints();
DROP PROCEDURE test_demo_constraints;
SET SESSION sql_mode = @previous_sql_mode;
