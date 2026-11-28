-- Select your RepairTrack database in Workbench. Back up before migrating.
-- MySQL 8 / compatible MariaDB; safe to rerun.
-- Convert historical TIMESTAMP events to UTC before copying into DATETIME.
SET time_zone = '+00:00';
DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_staff_workspace$$
CREATE PROCEDURE migrate_staff_workspace()
BEGIN
  IF DATABASE() IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Select the RepairTrack database first.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ticket_devices' AND COLUMN_NAME = 'repair_started_at') THEN
    ALTER TABLE ticket_devices ADD COLUMN repair_started_at DATETIME NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair_tickets' AND COLUMN_NAME = 'portal_code') THEN
    ALTER TABLE repair_tickets ADD COLUMN portal_code CHAR(5) NULL;
  END IF;
  -- A prior run may have added the column but stopped before creating its index.
  IF NOT EXISTS (
    SELECT INDEX_NAME FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair_tickets'
    GROUP BY INDEX_NAME HAVING MAX(NON_UNIQUE) = 0 AND COUNT(*) = 1
      AND MAX(COLUMN_NAME) = 'portal_code' AND MAX(SUB_PART) IS NULL
  ) THEN
    ALTER TABLE repair_tickets ADD UNIQUE KEY uq_portal_code (portal_code);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repair_tickets' AND COLUMN_NAME = 'portal_pin_hash') THEN
    ALTER TABLE repair_tickets ADD COLUMN portal_pin_hash VARCHAR(255) NULL;
  END IF;
  ALTER TABLE users MODIFY COLUMN role ENUM('ADMIN','MANAGER','INTAKE_AGENT','TECHNICIAN','COURIER','VIEWER','CASHIER') NOT NULL;
  -- Recover only a real historical transition, never invent a start time.
  UPDATE ticket_devices d JOIN (
    SELECT ticket_device_id, MIN(created_at) AS first_start FROM ticket_status_events
    WHERE to_status = 'IN_PROGRESS' AND (from_status IS NULL OR from_status <> 'IN_PROGRESS')
    GROUP BY ticket_device_id
  ) e ON e.ticket_device_id = d.ticket_device_id
  SET d.repair_started_at = e.first_start WHERE d.repair_started_at IS NULL;
END$$
CALL migrate_staff_workspace()$$
DROP PROCEDURE migrate_staff_workspace$$
DELIMITER ;
