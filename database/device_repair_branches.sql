-- Phase 1: additive, non-authorizing device repair-branch field.
-- Run only after a backup with application writes paused. Safe to rerun after partial DDL.
-- Existing billing/visibility scopes continue to use repair_tickets.accountable_branch_id.
DELIMITER $$
DROP PROCEDURE IF EXISTS migrate_device_repair_branches$$
CREATE PROCEDURE migrate_device_repair_branches()
BEGIN
  IF DATABASE() IS NULL OR
     NOT EXISTS (SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Select an existing RepairTrack database first.';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices'
      AND COLUMN_NAME='repair_branch_id') THEN
    ALTER TABLE ticket_devices
      ADD COLUMN repair_branch_id BIGINT UNSIGNED NULL
      COMMENT 'Device repair branch; phase-1 value mirrors ticket accountability';
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices'
      AND INDEX_NAME='idx_td_repair_branch') THEN
    ALTER TABLE ticket_devices ADD KEY idx_td_repair_branch (repair_branch_id, repair_status);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='ticket_devices'
      AND COLUMN_NAME='repair_branch_id' AND REFERENCED_TABLE_NAME='branches'
      AND REFERENCED_COLUMN_NAME='branch_id') THEN
    ALTER TABLE ticket_devices
      ADD CONSTRAINT fk_td_repair_branch FOREIGN KEY (repair_branch_id)
      REFERENCES branches(branch_id);
  END IF;
  -- Backfill only unresolved issued devices. Never overwrite explicit ownership,
  -- even if a previous upgrade was interrupted.
  UPDATE ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
    SET d.repair_branch_id=t.accountable_branch_id,
        d.updated_at=d.updated_at -- Preserve historical sort and update timestamps.
    WHERE t.record_stage='ISSUED' AND d.repair_branch_id IS NULL
      AND t.accountable_branch_id IS NOT NULL;
  IF EXISTS (
    SELECT 1 FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
    WHERE t.record_stage='ISSUED' AND d.repair_branch_id IS NULL
  ) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT='Issued devices still lack repair branches; inspect before enabling new features.';
  END IF;
END$$
CALL migrate_device_repair_branches()$$
DROP PROCEDURE migrate_device_repair_branches$$
DELIMITER ;
