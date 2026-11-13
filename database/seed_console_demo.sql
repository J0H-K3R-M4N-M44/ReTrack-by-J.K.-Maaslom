-- DEMO ONLY. Run create_demo_database.sql, then seed_demo.sql, then this file.
-- This expands the original fixture without adding tables or disabling foreign keys.
-- It refuses reruns and non-baseline data. All people, branches and consent are fictional.
USE repairtrack_demo;
SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP PROCEDURE IF EXISTS seed_console_demo;
DELIMITER $$
CREATE PROCEDURE seed_console_demo()
BEGIN
    DECLARE n INT DEFAULT 31;
    DECLARE branch_number INT;
    DECLARE technician_number INT;
    DECLARE customer_number BIGINT;
    DECLARE ticket_number BIGINT;
    DECLARE device_number BIGINT;
    DECLARE quote_number BIGINT;
    DECLARE customer_label VARCHAR(160);
    DECLARE device_kind VARCHAR(80);
    DECLARE device_brand VARCHAR(100);
    DECLARE repair_stage VARCHAR(40);
    DECLARE draft_record BOOLEAN;
    DECLARE approved_quote BOOLEAN;
    DECLARE created_time DATETIME;
    DECLARE photo_path VARCHAR(60) DEFAULT 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png';
    DECLARE form_path VARCHAR(60) DEFAULT 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb.png';
    DECLARE demo_hash VARCHAR(255) DEFAULT '$2y$10$MzFVTa63rZrJfCVlEVjHyOeLb9pHhWngoFHkPRyXQDZyEgI6VyQa.';
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    IF DATABASE() <> 'repairtrack_demo' OR @@FOREIGN_KEY_CHECKS <> 1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Use repairtrack_demo with foreign key checks enabled.';
    END IF;
    START TRANSACTION;
    IF (SELECT COUNT(*) FROM users) <> 8
        OR (SELECT COUNT(*) FROM repair_tickets) <> 30
        OR (SELECT COUNT(*) FROM audit_events) <> 30
        OR NOT EXISTS (SELECT 1 FROM users WHERE user_id = 1 AND email = 'admin@example.test')
        OR EXISTS (SELECT 1 FROM audit_events WHERE action = 'CONSOLE_DEMO_V2_SEEDED') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed refused: import the untouched original demo fixture first. Never run on an edited demo.';
    END IF;

    -- Friendly logins. Password for active sample accounts: DemoRepair2026!
    UPDATE users SET full_name = 'Demo Global Admin' WHERE user_id = 1;
    UPDATE users SET full_name = 'Demo Central Manager', email = 'manager.central@example.test' WHERE user_id = 2;
    UPDATE users SET full_name = 'Demo Central Intake', email = 'intake.central@example.test' WHERE user_id = 3;
    UPDATE users SET full_name = 'Demo Central Technician', email = 'tech.central@example.test' WHERE user_id = 4;
    UPDATE users SET full_name = 'Demo Central Courier', email = 'courier.central@example.test' WHERE user_id = 5;
    UPDATE users SET full_name = 'Demo Central Viewer', email = 'viewer.central@example.test' WHERE user_id = 6;
    UPDATE users SET full_name = 'Demo North Technician', email = 'tech.north@example.test' WHERE user_id = 7;

    INSERT INTO users (user_id, full_name, email, password_hash, role, is_active) VALUES
        (9, 'Demo Central Admin', 'admin.central@example.test', demo_hash, 'ADMIN', 1),
        (10, 'Demo North Intake', 'intake.north@example.test', demo_hash, 'INTAKE_AGENT', 1),
        (11, 'Demo North Manager', 'manager.north@example.test', demo_hash, 'MANAGER', 1),
        (12, 'Demo Hub Technician', 'tech.hub@example.test', demo_hash, 'TECHNICIAN', 1),
        (13, 'Demo Hub Manager', 'manager.hub@example.test', demo_hash, 'MANAGER', 1),
        (14, 'Demo Hub Courier', 'courier.hub@example.test', demo_hash, 'COURIER', 1),
        (15, 'Demo North Viewer', 'viewer.north@example.test', demo_hash, 'VIEWER', 1),
        (16, 'Demo Pending Technician', 'pending.tech@example.test', demo_hash, 'TECHNICIAN', 0),
        (17, 'Demo Disabled Technician', 'disabled.tech@example.test', demo_hash, 'TECHNICIAN', 0);

    -- Restrict staff by branch so access-control demonstrations are meaningful.
    DELETE FROM user_branch_assignments WHERE user_id BETWEEN 2 AND 7;
    INSERT INTO user_branch_assignments (user_id, branch_id, is_default) VALUES
        (2,1,1),(3,1,1),(4,1,1),(5,1,1),(6,1,1),(7,2,1),
        (9,1,1),(10,2,1),(11,2,1),(12,3,1),(13,3,1),(14,3,1),(15,2,1),(16,1,1),(17,2,1);
    UPDATE ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id
    SET d.assigned_technician_id = CASE t.accountable_branch_id WHEN 1 THEN 4 WHEN 2 THEN 7 ELSE 12 END
    WHERE d.assigned_technician_id IS NOT NULL;

    -- The private evidence helper installs these clearly watermarked PNG fixtures.
    UPDATE repair_tickets SET terms_signature_path = form_path WHERE terms_signature_path IS NOT NULL;
    UPDATE ticket_device_acknowledgments SET signature_path = form_path, signature_source = 'SIMULATED_DEMO'
        WHERE signature_path IS NOT NULL;
    UPDATE ticket_device_photos SET stored_path = photo_path, original_filename = 'demo-condition.png',
        mime_type = 'image/png', byte_size = 12236, sha256_hash = '8313b0c596daa5484267658f60a4d3978659c523122431062ee56b7e119610b3';

    -- Add 120 customers and tickets. Every fifth entry is a reopenable draft.
    -- Other entries cycle through repair stages and device categories.
    WHILE n <= 150 DO
        SET branch_number = MOD(n - 1, 3) + 1;
        SET technician_number = CASE branch_number WHEN 1 THEN 4 WHEN 2 THEN 7 ELSE 12 END;
        SET draft_record = MOD(n, 5) = 0;
        SET device_kind = ELT(MOD(n, 8) + 1, 'Laptop', 'Desktop computer', 'Tablet', 'Mobile phone',
            'Monitor', 'Printer', 'Game console', 'Handheld PC');
        SET device_brand = ELT(MOD(n, 8) + 1, 'Lenovo', 'Acer', 'Samsung', 'Motorola', 'Dell', 'Epson', 'Sony', 'Valve');
        SET repair_stage = IF(draft_record, 'DRAFT', ELT(MOD(n, 7) + 1, 'RECEIVED', 'DIAGNOSING',
            'AWAITING_CUSTOMER_APPROVAL', 'AWAITING_PARTS', 'IN_PROGRESS', 'TESTING', 'READY_FOR_PICKUP'));
        SET approved_quote = repair_stage IN ('IN_PROGRESS','TESTING','READY_FOR_PICKUP')
            OR (repair_stage = 'DIAGNOSING' AND MOD(n,2) = 0);
        SET created_time = DATE_SUB(UTC_TIMESTAMP(), INTERVAL MOD(n, 45) DAY);
        SET customer_label = CONCAT('Demo Customer ', LPAD(n, 3, '0'));

        INSERT INTO customers (full_name, normalized_name, customer_notes, created_at, updated_at)
        VALUES (customer_label, LOWER(customer_label), 'Fictional classroom demo record.', created_time, created_time);
        SET customer_number = LAST_INSERT_ID();
        INSERT INTO customer_contacts (customer_id, contact_type, contact_value, normalized_value, is_primary)
        VALUES (customer_number, 'EMAIL', CONCAT('customer',n,'@example.test'), CONCAT('customer',n,'@example.test'), 1);

        INSERT INTO repair_tickets (ticket_code, record_stage, case_state, customer_id, intake_branch_id,
            accountable_branch_id, accepted_by_user_id, arrival_method, intake_date, tracking_token_hash,
            terms_version, terms_accepted, terms_signature_path, terms_signed_at, created_at, issued_at, updated_at)
        VALUES (IF(draft_record,NULL,CONCAT('DEMO-V2-',LPAD(n,4,'0'))), IF(draft_record,'DRAFT','ISSUED'),
            IF(draft_record,NULL,'OPEN'), customer_number, branch_number, IF(draft_record,NULL,branch_number), 1,
            IF(MOD(n,4)=0,'MAIL','WALK_IN'), IF(draft_record,NULL,DATE(DATE_ADD(created_time,INTERVAL 8 HOUR))),
            IF(draft_record,NULL,SHA2(CONCAT('NONPUBLIC-DEMO-V2-',n),256)), IF(draft_record,NULL,'DEMO-v2'),
            IF(draft_record,0,1), IF(draft_record,NULL,form_path), IF(draft_record,NULL,created_time),
            created_time, IF(draft_record,NULL,created_time), created_time);
        SET ticket_number = LAST_INSERT_ID();

        INSERT INTO ticket_devices (ticket_id, child_sequence, item_code, device_type_snapshot, make_snapshot,
            model_snapshot, serial_number_snapshot, alternative_identifiers, reported_symptoms, condition_notes,
            accessory_checklist_completed, repair_status, assigned_technician_id, physical_location_id,
            custody_state, created_at, updated_at)
        VALUES (ticket_number,1,IF(draft_record,NULL,CONCAT('DEMO-V2-',LPAD(n,4,'0'),'-01')),
            device_kind,device_brand,CONCAT('Demo model ',MOD(n,12)+1),
            IF(MOD(n,6)=0,NULL,CONCAT('DEMO-V2-SN-',n)),
            IF(MOD(n,6)=0,CONCAT('Demo label ',n,'; dark case with fictional asset sticker'),NULL),
            CONCAT('Selected: ',ELT(MOD(n,4)+1,'Will not turn on','Overheating','Slow or freezing','Other'),
                IF(MOD(n,4)=3,CHAR(10),''),
                IF(MOD(n,4)=3,'Details: Intermittent trigger or control issue','')),
            CONCAT('Selected: ',IF(MOD(n,3)=0,'Scratched casing','No visible damage')),
            IF(draft_record,0,1),repair_stage,
            IF(draft_record OR repair_stage='RECEIVED',NULL,technician_number), branch_number*10+2,
            'AT_LOCATION',created_time,created_time);
        SET device_number = LAST_INSERT_ID();

        INSERT INTO ticket_status_events (ticket_device_id,from_status,to_status,actor_user_id,internal_note,created_at)
        VALUES (device_number,NULL,repair_stage,IF(draft_record,1,technician_number),'Synthetic starting state for demonstration.',created_time);
        INSERT INTO device_custody_events (ticket_device_id,event_type,to_location_id,performed_by_user_id,note,happened_at)
        VALUES (device_number,'INTAKE_RECEIVED',branch_number*10+2,1,'Synthetic storage assignment.',created_time);

        IF NOT draft_record THEN
            INSERT INTO ticket_accessory_items (ticket_device_id,accessory_name,received,notes,recorded_by_user_id)
            VALUES (device_number,'Power adapter',MOD(n,2)=0,'Demo checklist; no real property received.',1);
            INSERT INTO ticket_device_photos (ticket_device_id,photo_category,stored_path,original_filename,mime_type,
                byte_size,sha256_hash,uploaded_by_user_id,captured_at)
            VALUES (device_number,'OVERALL_CONDITION',photo_path,'demo-condition.png','image/png',
                12236,'8313b0c596daa5484267658f60a4d3978659c523122431062ee56b7e119610b3',1,created_time);
            INSERT INTO ticket_device_acknowledgments (ticket_device_id,customer_name_at_signing,signature_path,
                policy_version,accepted,signature_source,signed_at,recorded_at)
            VALUES (device_number,customer_label,form_path,'DEMO-v2',1,'SIMULATED_DEMO',created_time,created_time);
            INSERT INTO ticket_intake_gate_events (ticket_id,ticket_device_id,gate_code,passed,details,checked_by_user_id,checked_at)
            SELECT ticket_number,device_number,gate_code,1,'Synthetic fixture, not a real intake check.',1,created_time
            FROM (SELECT 'CUSTOMER_MATCH' AS gate_code UNION ALL SELECT 'DEVICE_IDENTITY' UNION ALL SELECT 'SYMPTOMS'
                UNION ALL SELECT 'CONDITION_NOTES' UNION ALL SELECT 'ACCESSORY_CHECKLIST' UNION ALL SELECT 'REQUIRED_PHOTOS'
                UNION ALL SELECT 'CONDITION_SIGNOFF' UNION ALL SELECT 'TERMS_ACCEPTANCE' UNION ALL SELECT 'DUPLICATE_CHECK') gates;

            IF repair_stage <> 'RECEIVED' THEN
                INSERT INTO repair_quotes (ticket_device_id,version_no,quote_status,labor_amount,total_amount,prepared_by_user_id,sent_at)
                VALUES (device_number,1,IF(approved_quote,'APPROVED','AWAITING_CUSTOMER'),500,500,1,created_time);
                SET quote_number = LAST_INSERT_ID();
                INSERT INTO repair_quote_items (quote_id,item_type,description,quantity,unit_price,line_total)
                VALUES (quote_number,'LABOR','Demo service estimate',1,500,500);
                IF approved_quote THEN
                    INSERT INTO repair_quote_decisions (quote_id,decision,decision_source,recorded_by_user_id,
                        customer_name_at_decision,customer_comment,decision_at)
                    VALUES (quote_number,'APPROVED','SIMULATED_DEMO',1,customer_label,'Fictional approval for console testing.',created_time);
                END IF;
            END IF;
        END IF;
        INSERT INTO audit_events (actor_user_id,entity_type,entity_id,action,reason,created_at)
        VALUES (1,'repair_ticket',ticket_number,'DEMO_V2_RECORD','Fictional record, not production data.',created_time);
        SET n = n + 1;
    END WHILE;
    INSERT INTO audit_events (actor_user_id,entity_type,entity_id,action,after_data,created_at)
    VALUES (1,'demo_seed',2,'CONSOLE_DEMO_V2_SEEDED','{"version":2,"synthetic":true}',UTC_TIMESTAMP());
    COMMIT;
END$$
DELIMITER ;
CALL seed_console_demo();
DROP PROCEDURE seed_console_demo;
SELECT 'Console demo ready. Run check_console_demo.sql and prepare_demo_evidence.php.' AS result;
