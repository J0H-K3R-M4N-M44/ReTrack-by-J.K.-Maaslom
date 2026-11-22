-- Read-only MySQL/Workbench spot-check. Select an isolated repairtrack_test_* DB.
-- This script NEVER updates customer, inventory, custody or payment records.
-- A successful result has 0 violations in every row. The PHP runner also checks
-- constraints, snapshots, and deliberate negative tests.
SELECT DATABASE() AS selected_database,
       IF(DATABASE() LIKE 'repairtrack_test_%', 'DISPOSABLE TEST SCHEMA', 'NOT A TEST SCHEMA - DO NOT MUTATE') AS safety_context;

SELECT check_name, violations FROM (
  SELECT 'issued device lacks repair branch' AS check_name,
    (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
      WHERE t.record_stage='ISSUED' AND d.repair_branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'issued device differs from ticket branch',
    (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
      WHERE t.record_stage='ISSUED' AND d.repair_branch_id<>t.accountable_branch_id)
  UNION ALL
  SELECT 'draft device has assigned repair branch',
    (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
      WHERE t.record_stage='DRAFT' AND d.repair_branch_id IS NOT NULL)
  UNION ALL
  SELECT 'device references missing ticket',
    (SELECT COUNT(*) FROM ticket_devices d LEFT JOIN repair_tickets t ON t.ticket_id=d.ticket_id
      WHERE t.ticket_id IS NULL)
  UNION ALL
  SELECT 'device references missing repair branch',
    (SELECT COUNT(*) FROM ticket_devices d LEFT JOIN branches b ON b.branch_id=d.repair_branch_id
      WHERE d.repair_branch_id IS NOT NULL AND b.branch_id IS NULL)
  UNION ALL
  SELECT 'bill line belongs to another ticket',
    (SELECT COUNT(*) FROM repair_bill_items i JOIN repair_bills b ON b.bill_id=i.bill_id
      JOIN ticket_devices d ON d.ticket_device_id=i.ticket_device_id WHERE d.ticket_id<>b.ticket_id)
  UNION ALL
  SELECT 'payment has no bill',
    (SELECT COUNT(*) FROM repair_bill_payments p LEFT JOIN repair_bills b ON b.bill_id=p.bill_id
      WHERE b.bill_id IS NULL)
  UNION ALL
  SELECT 'negative shelf quantity',
    (SELECT COUNT(*) FROM branch_inventory WHERE quantity_on_hand<0)
  UNION ALL
  SELECT 'reserved stock has no shelf record',
    (SELECT COUNT(*) FROM stock_reservations r LEFT JOIN branch_inventory s
      ON s.branch_id=r.branch_id AND s.part_id=r.part_id WHERE s.part_id IS NULL)
  UNION ALL
  SELECT 'stock hold mismatches quoted part',
    (SELECT COUNT(*) FROM stock_reservations r JOIN repair_quote_items i
      ON i.quote_item_id=r.quote_item_id WHERE r.part_id<>i.part_id)
  UNION ALL
  SELECT 'custody event references missing device',
    (SELECT COUNT(*) FROM device_custody_events e LEFT JOIN ticket_devices d
      ON d.ticket_device_id=e.ticket_device_id WHERE d.ticket_device_id IS NULL)
  UNION ALL
  SELECT 'customer-returned device still has shelf location',
    (SELECT COUNT(*) FROM ticket_devices
      WHERE custody_state='WITH_CUSTOMER' AND physical_location_id IS NOT NULL)
) checks
ORDER BY violations DESC, check_name;

SELECT 'repair_branch_id column' AS schema_check,
       COUNT(*) AS found
FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='ticket_devices' AND COLUMN_NAME='repair_branch_id'
UNION ALL
SELECT 'repair_branch_id foreign key',
       COUNT(*)
FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='ticket_devices' AND CONSTRAINT_NAME='fk_td_repair_branch'
    AND REFERENCED_TABLE_NAME='branches' AND REFERENCED_COLUMN_NAME='branch_id'
UNION ALL
SELECT 'repair_branch_id index',
       COUNT(DISTINCT INDEX_NAME)
FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='ticket_devices' AND INDEX_NAME='idx_td_repair_branch';
