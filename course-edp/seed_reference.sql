-- Reference catalogs and FICTIONAL Sogod classroom branch only.
-- Execute only after schema.sql, in the dedicated repairtrack_edp database.
-- Never seed real customers, passwords, access PINs, photos or payments here.
USE repairtrack_edp;

INSERT INTO roles (role_code, role_name) VALUES
('ADMIN','Administrator'),
('MANAGER','Manager'),
('INTAKE_AGENT','Intake Staff'),
('TECHNICIAN','Technician'),
('CASHIER','Cashier');

INSERT INTO device_types (device_type_code, label, sort_order) VALUES
('LAPTOP','Laptop',1),('DESKTOP','Desktop PC',2),('ALL_IN_ONE','All-in-One PC',3),
('MONITOR','Monitor',4),('PRINTER','Printer',5),('OTHER','Other',6);

INSERT INTO defect_types (defect_code, label, sort_order) VALUES
('NO_POWER','No power',1),('NO_DISPLAY','No display',2),
('OVERHEATING','Overheating',3),('SLOW','Slow performance',4),
('BATTERY','Battery failure',5),('CHARGING','Charging issue',6),
('STORAGE','Storage issue',7),('SOFTWARE','Software issue',8),('OTHER','Other',9);

-- Codes deliberately match the established PHP terminology.
INSERT INTO repair_statuses (status_code, label, sort_order, is_terminal) VALUES
('DRAFT','Draft',0,FALSE),
('RECEIVED','Received',1,FALSE),
('DIAGNOSING','Diagnosing',2,FALSE),
('AWAITING_CUSTOMER_APPROVAL','Awaiting Customer Approval',3,FALSE),
('AWAITING_PARTS','Awaiting Parts',4,FALSE),
('IN_PROGRESS','In Repair',5,FALSE),
('TESTING','Testing',6,FALSE),
('READY_FOR_PICKUP','Ready for Pickup',7,FALSE),
('COMPLETED','Completed',8,TRUE),
('CANCELLED','Cancelled',9,TRUE);

-- These are possible category transitions; PHP checks who may trigger them
-- and whether the quote, diagnosis, payment and custody prerequisites hold.
INSERT INTO allowed_status_transitions (from_status, to_status) VALUES
('DRAFT','RECEIVED'),
('RECEIVED','DIAGNOSING'),('RECEIVED','CANCELLED'),
('DIAGNOSING','AWAITING_CUSTOMER_APPROVAL'),
('DIAGNOSING','AWAITING_PARTS'),
('DIAGNOSING','IN_PROGRESS'),
('DIAGNOSING','CANCELLED'),
('AWAITING_CUSTOMER_APPROVAL','AWAITING_PARTS'),
('AWAITING_CUSTOMER_APPROVAL','IN_PROGRESS'),
('AWAITING_CUSTOMER_APPROVAL','CANCELLED'),
('AWAITING_PARTS','IN_PROGRESS'),
('AWAITING_PARTS','CANCELLED'),
('IN_PROGRESS','TESTING'),
('IN_PROGRESS','AWAITING_PARTS'),
('TESTING','IN_PROGRESS'),
('TESTING','READY_FOR_PICKUP'),
('READY_FOR_PICKUP','COMPLETED');

-- This is a fictional demo record, NOT a verified or live Sogod business.
INSERT INTO branches (branch_code, branch_name, address, phone) VALUES
('SOGOD-DEMO', 'Sogod Classroom Repair Desk', 'Sogod, Southern Leyte, Philippines', NULL);

-- Look up the branch by its stable code, never assume AUTO_INCREMENT = 1.
INSERT INTO storage_locations (branch_id, location_code, location_name)
SELECT b.branch_id, l.location_code, l.location_name
FROM branches AS b
CROSS JOIN (
  SELECT 'COUNTER' AS location_code, 'Front Counter' AS location_name
  UNION ALL SELECT 'BENCH', 'Technician Workbench'
  UNION ALL SELECT 'SHELF', 'Pickup Shelf'
) AS l
WHERE b.branch_code = 'SOGOD-DEMO';
