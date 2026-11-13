-- Read-only checks for the expanded demo; run after all three import files.
USE repairtrack_demo;
SELECT check_name, violations, IF(violations = 0, 'PASS', 'FAIL') AS result
FROM (
  SELECT 'table has sample rows: branches' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM branches) AS violations
  UNION ALL
  SELECT 'table has sample rows: users' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM users) AS violations
  UNION ALL
  SELECT 'table has sample rows: user_branch_assignments' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM user_branch_assignments) AS violations
  UNION ALL
  SELECT 'table has sample rows: customers' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM customers) AS violations
  UNION ALL
  SELECT 'table has sample rows: customer_contacts' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM customer_contacts) AS violations
  UNION ALL
  SELECT 'table has sample rows: customer_devices' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM customer_devices) AS violations
  UNION ALL
  SELECT 'table has sample rows: storage_locations' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM storage_locations) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_tickets' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_tickets) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_devices' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_devices) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_accessory_items' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_accessory_items) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_device_photos' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_device_photos) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_device_acknowledgments' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_device_acknowledgments) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_intake_gate_events' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_intake_gate_events) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_status_events' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_status_events) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_correction_notes' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_correction_notes) AS violations
  UNION ALL
  SELECT 'table has sample rows: device_transfers' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM device_transfers) AS violations
  UNION ALL
  SELECT 'table has sample rows: device_transfer_items' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM device_transfer_items) AS violations
  UNION ALL
  SELECT 'table has sample rows: device_custody_events' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM device_custody_events) AS violations
  UNION ALL
  SELECT 'table has sample rows: parts' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM parts) AS violations
  UNION ALL
  SELECT 'table has sample rows: branch_inventory' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM branch_inventory) AS violations
  UNION ALL
  SELECT 'table has sample rows: inventory_transfers' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM inventory_transfers) AS violations
  UNION ALL
  SELECT 'table has sample rows: inventory_transfer_items' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM inventory_transfer_items) AS violations
  UNION ALL
  SELECT 'table has sample rows: inventory_transactions' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM inventory_transactions) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_parts_used' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_parts_used) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_quotes' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_quotes) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_quote_items' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_quote_items) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_quote_decisions' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_quote_decisions) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_bills' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_bills) AS violations
  UNION ALL
  SELECT 'table has sample rows: repair_bill_items' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM repair_bill_items) AS violations
  UNION ALL
  SELECT 'table has sample rows: ticket_operational_events' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM ticket_operational_events) AS violations
  UNION ALL
  SELECT 'table has sample rows: mock_notification_log' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM mock_notification_log) AS violations
  UNION ALL
  SELECT 'table has sample rows: audit_events' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM audit_events) AS violations
  UNION ALL
  SELECT 'table has sample rows: password_reset_tokens' AS check_name, (SELECT IF(COUNT(*) = 0, 1, 0) FROM password_reset_tokens) AS violations
  UNION ALL
  SELECT 'foreign key: fk_uba_user' AS check_name, (SELECT COUNT(*) FROM user_branch_assignments c LEFT JOIN users p ON p.user_id = c.user_id WHERE c.user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_uba_branch' AS check_name, (SELECT COUNT(*) FROM user_branch_assignments c LEFT JOIN branches p ON p.branch_id = c.branch_id WHERE c.branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_contacts_customer' AS check_name, (SELECT COUNT(*) FROM customer_contacts c LEFT JOIN customers p ON p.customer_id = c.customer_id WHERE c.customer_id IS NOT NULL AND p.customer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_customer_devices_customer' AS check_name, (SELECT COUNT(*) FROM customer_devices c LEFT JOIN customers p ON p.customer_id = c.customer_id WHERE c.customer_id IS NOT NULL AND p.customer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_locations_branch' AS check_name, (SELECT COUNT(*) FROM storage_locations c LEFT JOIN branches p ON p.branch_id = c.branch_id WHERE c.branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_ticket_customer' AS check_name, (SELECT COUNT(*) FROM repair_tickets c LEFT JOIN customers p ON p.customer_id = c.customer_id WHERE c.customer_id IS NOT NULL AND p.customer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_ticket_intake_branch' AS check_name, (SELECT COUNT(*) FROM repair_tickets c LEFT JOIN branches p ON p.branch_id = c.intake_branch_id WHERE c.intake_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_ticket_accountable_branch' AS check_name, (SELECT COUNT(*) FROM repair_tickets c LEFT JOIN branches p ON p.branch_id = c.accountable_branch_id WHERE c.accountable_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_ticket_accepted_by' AS check_name, (SELECT COUNT(*) FROM repair_tickets c LEFT JOIN users p ON p.user_id = c.accepted_by_user_id WHERE c.accepted_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_td_ticket' AS check_name, (SELECT COUNT(*) FROM ticket_devices c LEFT JOIN repair_tickets p ON p.ticket_id = c.ticket_id WHERE c.ticket_id IS NOT NULL AND p.ticket_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_td_customer_device' AS check_name, (SELECT COUNT(*) FROM ticket_devices c LEFT JOIN customer_devices p ON p.customer_device_id = c.customer_device_id WHERE c.customer_device_id IS NOT NULL AND p.customer_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_td_technician' AS check_name, (SELECT COUNT(*) FROM ticket_devices c LEFT JOIN users p ON p.user_id = c.assigned_technician_id WHERE c.assigned_technician_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_td_location' AS check_name, (SELECT COUNT(*) FROM ticket_devices c LEFT JOIN storage_locations p ON p.location_id = c.physical_location_id WHERE c.physical_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_td_handover_by' AS check_name, (SELECT COUNT(*) FROM ticket_devices c LEFT JOIN users p ON p.user_id = c.return_handover_by WHERE c.return_handover_by IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_accessory_ticket_device' AS check_name, (SELECT COUNT(*) FROM ticket_accessory_items c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_accessory_recorder' AS check_name, (SELECT COUNT(*) FROM ticket_accessory_items c LEFT JOIN users p ON p.user_id = c.recorded_by_user_id WHERE c.recorded_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_photos_ticket_device' AS check_name, (SELECT COUNT(*) FROM ticket_device_photos c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_photos_uploader' AS check_name, (SELECT COUNT(*) FROM ticket_device_photos c LEFT JOIN users p ON p.user_id = c.uploaded_by_user_id WHERE c.uploaded_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_ack_ticket_device' AS check_name, (SELECT COUNT(*) FROM ticket_device_acknowledgments c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_gate_ticket' AS check_name, (SELECT COUNT(*) FROM ticket_intake_gate_events c LEFT JOIN repair_tickets p ON p.ticket_id = c.ticket_id WHERE c.ticket_id IS NOT NULL AND p.ticket_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_gate_ticket_device' AS check_name, (SELECT COUNT(*) FROM ticket_intake_gate_events c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_gate_user' AS check_name, (SELECT COUNT(*) FROM ticket_intake_gate_events c LEFT JOIN users p ON p.user_id = c.checked_by_user_id WHERE c.checked_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_status_event_device' AS check_name, (SELECT COUNT(*) FROM ticket_status_events c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_status_event_actor' AS check_name, (SELECT COUNT(*) FROM ticket_status_events c LEFT JOIN users p ON p.user_id = c.actor_user_id WHERE c.actor_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_correction_ticket' AS check_name, (SELECT COUNT(*) FROM ticket_correction_notes c LEFT JOIN repair_tickets p ON p.ticket_id = c.ticket_id WHERE c.ticket_id IS NOT NULL AND p.ticket_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_correction_device' AS check_name, (SELECT COUNT(*) FROM ticket_correction_notes c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_correction_author' AS check_name, (SELECT COUNT(*) FROM ticket_correction_notes c LEFT JOIN users p ON p.user_id = c.author_user_id WHERE c.author_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dt_from_branch' AS check_name, (SELECT COUNT(*) FROM device_transfers c LEFT JOIN branches p ON p.branch_id = c.from_branch_id WHERE c.from_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dt_to_branch' AS check_name, (SELECT COUNT(*) FROM device_transfers c LEFT JOIN branches p ON p.branch_id = c.to_branch_id WHERE c.to_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dt_initiator' AS check_name, (SELECT COUNT(*) FROM device_transfers c LEFT JOIN users p ON p.user_id = c.initiated_by_user_id WHERE c.initiated_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dt_receiver' AS check_name, (SELECT COUNT(*) FROM device_transfers c LEFT JOIN users p ON p.user_id = c.received_confirmed_by_user_id WHERE c.received_confirmed_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_transfer' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN device_transfers p ON p.device_transfer_id = c.device_transfer_id WHERE c.device_transfer_id IS NOT NULL AND p.device_transfer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_device' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_from_location' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN storage_locations p ON p.location_id = c.from_location_id WHERE c.from_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_transit_location' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN storage_locations p ON p.location_id = c.transit_location_id WHERE c.transit_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_to_location' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN storage_locations p ON p.location_id = c.to_location_id WHERE c.to_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_dti_receiver' AS check_name, (SELECT COUNT(*) FROM device_transfer_items c LEFT JOIN users p ON p.user_id = c.received_by_user_id WHERE c.received_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_custody_device' AS check_name, (SELECT COUNT(*) FROM device_custody_events c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_custody_from' AS check_name, (SELECT COUNT(*) FROM device_custody_events c LEFT JOIN storage_locations p ON p.location_id = c.from_location_id WHERE c.from_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_custody_to' AS check_name, (SELECT COUNT(*) FROM device_custody_events c LEFT JOIN storage_locations p ON p.location_id = c.to_location_id WHERE c.to_location_id IS NOT NULL AND p.location_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_custody_transfer' AS check_name, (SELECT COUNT(*) FROM device_custody_events c LEFT JOIN device_transfers p ON p.device_transfer_id = c.device_transfer_id WHERE c.device_transfer_id IS NOT NULL AND p.device_transfer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_custody_user' AS check_name, (SELECT COUNT(*) FROM device_custody_events c LEFT JOIN users p ON p.user_id = c.performed_by_user_id WHERE c.performed_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_inventory_branch' AS check_name, (SELECT COUNT(*) FROM branch_inventory c LEFT JOIN branches p ON p.branch_id = c.branch_id WHERE c.branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_inventory_part' AS check_name, (SELECT COUNT(*) FROM branch_inventory c LEFT JOIN parts p ON p.part_id = c.part_id WHERE c.part_id IS NOT NULL AND p.part_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_it_from_branch' AS check_name, (SELECT COUNT(*) FROM inventory_transfers c LEFT JOIN branches p ON p.branch_id = c.from_branch_id WHERE c.from_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_it_to_branch' AS check_name, (SELECT COUNT(*) FROM inventory_transfers c LEFT JOIN branches p ON p.branch_id = c.to_branch_id WHERE c.to_branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_it_initiator' AS check_name, (SELECT COUNT(*) FROM inventory_transfers c LEFT JOIN users p ON p.user_id = c.initiated_by_user_id WHERE c.initiated_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_it_receiver' AS check_name, (SELECT COUNT(*) FROM inventory_transfers c LEFT JOIN users p ON p.user_id = c.received_by_user_id WHERE c.received_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_iti_transfer' AS check_name, (SELECT COUNT(*) FROM inventory_transfer_items c LEFT JOIN inventory_transfers p ON p.inventory_transfer_id = c.inventory_transfer_id WHERE c.inventory_transfer_id IS NOT NULL AND p.inventory_transfer_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_iti_part' AS check_name, (SELECT COUNT(*) FROM inventory_transfer_items c LEFT JOIN parts p ON p.part_id = c.part_id WHERE c.part_id IS NOT NULL AND p.part_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_invtx_branch' AS check_name, (SELECT COUNT(*) FROM inventory_transactions c LEFT JOIN branches p ON p.branch_id = c.branch_id WHERE c.branch_id IS NOT NULL AND p.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_invtx_part' AS check_name, (SELECT COUNT(*) FROM inventory_transactions c LEFT JOIN parts p ON p.part_id = c.part_id WHERE c.part_id IS NOT NULL AND p.part_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_invtx_ticket_device' AS check_name, (SELECT COUNT(*) FROM inventory_transactions c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_invtx_transfer_item' AS check_name, (SELECT COUNT(*) FROM inventory_transactions c LEFT JOIN inventory_transfer_items p ON p.inventory_transfer_item_id = c.inventory_transfer_item_id WHERE c.inventory_transfer_item_id IS NOT NULL AND p.inventory_transfer_item_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_invtx_user' AS check_name, (SELECT COUNT(*) FROM inventory_transactions c LEFT JOIN users p ON p.user_id = c.performed_by_user_id WHERE c.performed_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_tpu_device' AS check_name, (SELECT COUNT(*) FROM ticket_parts_used c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_tpu_part' AS check_name, (SELECT COUNT(*) FROM ticket_parts_used c LEFT JOIN parts p ON p.part_id = c.part_id WHERE c.part_id IS NOT NULL AND p.part_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_tpu_movement' AS check_name, (SELECT COUNT(*) FROM ticket_parts_used c LEFT JOIN inventory_transactions p ON p.inventory_transaction_id = c.inventory_transaction_id WHERE c.inventory_transaction_id IS NOT NULL AND p.inventory_transaction_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_tpu_user' AS check_name, (SELECT COUNT(*) FROM ticket_parts_used c LEFT JOIN users p ON p.user_id = c.added_by_user_id WHERE c.added_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_device' AS check_name, (SELECT COUNT(*) FROM repair_quotes c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_preparer' AS check_name, (SELECT COUNT(*) FROM repair_quotes c LEFT JOIN users p ON p.user_id = c.prepared_by_user_id WHERE c.prepared_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_item_quote' AS check_name, (SELECT COUNT(*) FROM repair_quote_items c LEFT JOIN repair_quotes p ON p.quote_id = c.quote_id WHERE c.quote_id IS NOT NULL AND p.quote_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_item_part' AS check_name, (SELECT COUNT(*) FROM repair_quote_items c LEFT JOIN parts p ON p.part_id = c.part_id WHERE c.part_id IS NOT NULL AND p.part_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_decision_quote' AS check_name, (SELECT COUNT(*) FROM repair_quote_decisions c LEFT JOIN repair_quotes p ON p.quote_id = c.quote_id WHERE c.quote_id IS NOT NULL AND p.quote_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_quote_decision_user' AS check_name, (SELECT COUNT(*) FROM repair_quote_decisions c LEFT JOIN users p ON p.user_id = c.recorded_by_user_id WHERE c.recorded_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_ticket' AS check_name, (SELECT COUNT(*) FROM repair_bills c LEFT JOIN repair_tickets p ON p.ticket_id = c.ticket_id WHERE c.ticket_id IS NOT NULL AND p.ticket_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_preparer' AS check_name, (SELECT COUNT(*) FROM repair_bills c LEFT JOIN users p ON p.user_id = c.prepared_by_user_id WHERE c.prepared_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_item_bill' AS check_name, (SELECT COUNT(*) FROM repair_bill_items c LEFT JOIN repair_bills p ON p.bill_id = c.bill_id WHERE c.bill_id IS NOT NULL AND p.bill_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_item_device' AS check_name, (SELECT COUNT(*) FROM repair_bill_items c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_item_quote' AS check_name, (SELECT COUNT(*) FROM repair_bill_items c LEFT JOIN repair_quote_items p ON p.quote_item_id = c.quote_item_id WHERE c.quote_item_id IS NOT NULL AND p.quote_item_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_bill_item_part_use' AS check_name, (SELECT COUNT(*) FROM repair_bill_items c LEFT JOIN ticket_parts_used p ON p.ticket_part_use_id = c.ticket_part_use_id WHERE c.ticket_part_use_id IS NOT NULL AND p.ticket_part_use_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_operational_device' AS check_name, (SELECT COUNT(*) FROM ticket_operational_events c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_operational_user' AS check_name, (SELECT COUNT(*) FROM ticket_operational_events c LEFT JOIN users p ON p.user_id = c.recorded_by_user_id WHERE c.recorded_by_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_notification_ticket' AS check_name, (SELECT COUNT(*) FROM mock_notification_log c LEFT JOIN repair_tickets p ON p.ticket_id = c.ticket_id WHERE c.ticket_id IS NOT NULL AND p.ticket_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_notification_device' AS check_name, (SELECT COUNT(*) FROM mock_notification_log c LEFT JOIN ticket_devices p ON p.ticket_device_id = c.ticket_device_id WHERE c.ticket_device_id IS NOT NULL AND p.ticket_device_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_audit_user' AS check_name, (SELECT COUNT(*) FROM audit_events c LEFT JOIN users p ON p.user_id = c.actor_user_id WHERE c.actor_user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'foreign key: fk_password_reset_user' AS check_name, (SELECT COUNT(*) FROM password_reset_tokens c LEFT JOIN users p ON p.user_id = c.user_id WHERE c.user_id IS NOT NULL AND p.user_id IS NULL) AS violations
  UNION ALL
  SELECT 'one primary contact per customer' AS check_name, (SELECT COUNT(*) FROM customers c WHERE (SELECT COUNT(*) FROM customer_contacts cc WHERE cc.customer_id = c.customer_id AND cc.is_primary = 1) <> 1) AS violations
  UNION ALL
  SELECT 'one default branch per user' AS check_name, (SELECT COUNT(*) FROM users u WHERE (SELECT COUNT(*) FROM user_branch_assignments a WHERE a.user_id = u.user_id AND a.is_default = 1) <> 1) AS violations
  UNION ALL
  SELECT 'device owner matches ticket customer' AS check_name, (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id JOIN customer_devices c ON c.customer_device_id = d.customer_device_id WHERE c.customer_id <> t.customer_id) AS violations
  UNION ALL
  SELECT 'assigned user is an active technician' AS check_name, (SELECT COUNT(*) FROM ticket_devices d JOIN users u ON u.user_id = d.assigned_technician_id WHERE u.role <> 'TECHNICIAN' OR u.is_active <> 1) AS violations
  UNION ALL
  SELECT 'draft tickets have no public code' AS check_name, (SELECT COUNT(*) FROM repair_tickets WHERE record_stage = 'DRAFT' AND (ticket_code IS NOT NULL OR tracking_token_hash IS NOT NULL)) AS violations
  UNION ALL
  SELECT 'issued tickets have public identity and consent' AS check_name, (SELECT COUNT(*) FROM repair_tickets WHERE record_stage = 'ISSUED' AND (ticket_code IS NULL OR tracking_token_hash IS NULL OR customer_id IS NULL OR accountable_branch_id IS NULL OR case_state IS NULL OR issued_at IS NULL OR intake_date IS NULL OR terms_accepted <> 1 OR terms_signature_path IS NULL OR terms_signed_at IS NULL)) AS violations
  UNION ALL
  SELECT 'issued devices have photo and acknowledgment' AS check_name, (SELECT COUNT(*) FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id = d.ticket_id WHERE t.record_stage = 'ISSUED' AND (NOT EXISTS (SELECT 1 FROM ticket_device_photos p WHERE p.ticket_device_id = d.ticket_device_id) OR NOT EXISTS (SELECT 1 FROM ticket_device_acknowledgments a WHERE a.ticket_device_id = d.ticket_device_id AND a.accepted = 1 AND a.signed_at IS NOT NULL))) AS violations
  UNION ALL
  SELECT 'ticket_intake_gate_events device belongs to ticket' AS check_name, (SELECT COUNT(*) FROM ticket_intake_gate_events x JOIN ticket_devices d ON d.ticket_device_id = x.ticket_device_id WHERE x.ticket_id <> d.ticket_id) AS violations
  UNION ALL
  SELECT 'ticket_correction_notes device belongs to ticket' AS check_name, (SELECT COUNT(*) FROM ticket_correction_notes x JOIN ticket_devices d ON d.ticket_device_id = x.ticket_device_id WHERE x.ticket_id <> d.ticket_id) AS violations
  UNION ALL
  SELECT 'mock_notification_log device belongs to ticket' AS check_name, (SELECT COUNT(*) FROM mock_notification_log x JOIN ticket_devices d ON d.ticket_device_id = x.ticket_device_id WHERE x.ticket_id <> d.ticket_id) AS violations
  UNION ALL
  SELECT 'latest status equals current device status' AS check_name, (SELECT COUNT(*) FROM ticket_devices d WHERE NOT EXISTS (SELECT 1 FROM ticket_status_events e WHERE e.ticket_device_id = d.ticket_device_id) OR d.repair_status <> (SELECT e.to_status FROM ticket_status_events e WHERE e.ticket_device_id = d.ticket_device_id ORDER BY e.created_at DESC, e.status_event_id DESC LIMIT 1)) AS violations
  UNION ALL
  SELECT 'status history chain is continuous' AS check_name, (SELECT COUNT(*) FROM (SELECT from_status, LAG(to_status) OVER (PARTITION BY ticket_device_id ORDER BY created_at, status_event_id) AS previous_status FROM ticket_status_events) h WHERE NOT (h.from_status <=> h.previous_status)) AS violations
  UNION ALL
  SELECT 'latest custody location equals device location' AS check_name, (SELECT COUNT(*) FROM ticket_devices d WHERE NOT EXISTS (SELECT 1 FROM device_custody_events e WHERE e.ticket_device_id = d.ticket_device_id) OR NOT (d.physical_location_id <=> (SELECT e.to_location_id FROM device_custody_events e WHERE e.ticket_device_id = d.ticket_device_id ORDER BY e.happened_at DESC, e.custody_event_id DESC LIMIT 1))) AS violations
  UNION ALL
  SELECT 'closed tickets contain only returned terminal devices' AS check_name, (SELECT COUNT(*) FROM repair_tickets t WHERE t.case_state = 'CLOSED' AND (t.closed_at IS NULL OR NOT EXISTS (SELECT 1 FROM ticket_devices d WHERE d.ticket_id=t.ticket_id) OR EXISTS (SELECT 1 FROM ticket_devices d WHERE d.ticket_id=t.ticket_id AND (d.repair_status NOT IN ('COMPLETED','CANCELLED') OR d.custody_state <> 'WITH_CUSTOMER' OR d.return_handover_at IS NULL)))) AS violations
  UNION ALL
  SELECT 'inventory ledger matches current balance' AS check_name, (SELECT COUNT(*) FROM branch_inventory b WHERE b.quantity_on_hand <> COALESCE((SELECT SUM(t.quantity_delta) FROM inventory_transactions t WHERE t.branch_id = b.branch_id AND t.part_id = b.part_id), 0)) AS violations
  UNION ALL
  SELECT 'every ledger pair has an inventory balance' AS check_name, (SELECT COUNT(*) FROM inventory_transactions t LEFT JOIN branch_inventory b ON b.branch_id=t.branch_id AND b.part_id=t.part_id WHERE b.branch_id IS NULL) AS violations
  UNION ALL
  SELECT 'used parts match stock movements' AS check_name, (SELECT COUNT(*) FROM ticket_parts_used p JOIN inventory_transactions t ON t.inventory_transaction_id = p.inventory_transaction_id WHERE t.transaction_type <> 'TICKET_USE' OR t.part_id <> p.part_id OR NOT (t.ticket_device_id <=> p.ticket_device_id) OR t.quantity_delta <> -p.quantity_used) AS violations
  UNION ALL
  SELECT 'received inventory transfers reconcile both movements' AS check_name, (SELECT COUNT(*) FROM inventory_transfer_items i JOIN inventory_transfers t ON t.inventory_transfer_id=i.inventory_transfer_id WHERE i.item_status='RECEIVED' AND (i.received_quantity <> i.quantity OR i.received_quantity IS NULL OR COALESCE((SELECT SUM(x.quantity_delta) FROM inventory_transactions x WHERE x.inventory_transfer_item_id=i.inventory_transfer_item_id AND x.branch_id=t.from_branch_id AND x.part_id=i.part_id AND x.transaction_type='TRANSFER_OUT'),0) <> -i.quantity OR COALESCE((SELECT SUM(x.quantity_delta) FROM inventory_transactions x WHERE x.inventory_transfer_item_id=i.inventory_transfer_item_id AND x.branch_id=t.to_branch_id AND x.part_id=i.part_id AND x.transaction_type='TRANSFER_IN'),0) <> i.received_quantity)) AS violations
  UNION ALL
  SELECT 'quote line arithmetic' AS check_name, (SELECT COUNT(*) FROM repair_quote_items WHERE line_total <> ROUND(quantity * unit_price, 2)) AS violations
  UNION ALL
  SELECT 'quote totals and categories match lines' AS check_name, (SELECT COUNT(*) FROM repair_quotes q WHERE q.total_amount <> q.labor_amount + q.parts_amount + q.other_amount OR q.total_amount <> COALESCE((SELECT SUM(i.line_total) FROM repair_quote_items i WHERE i.quote_id=q.quote_id),0) OR q.labor_amount <> COALESCE((SELECT SUM(i.line_total) FROM repair_quote_items i WHERE i.quote_id=q.quote_id AND i.item_type='LABOR'),0) OR q.parts_amount <> COALESCE((SELECT SUM(i.line_total) FROM repair_quote_items i WHERE i.quote_id=q.quote_id AND i.item_type='PART'),0) OR q.other_amount <> COALESCE((SELECT SUM(i.line_total) FROM repair_quote_items i WHERE i.quote_id=q.quote_id AND i.item_type='OTHER'),0)) AS violations
  UNION ALL
  SELECT 'quote decisions agree with approved or declined quotes' AS check_name, (SELECT COUNT(*) FROM repair_quotes q LEFT JOIN repair_quote_decisions d ON d.quote_id=q.quote_id WHERE q.quote_status IN ('APPROVED','DECLINED') AND (d.quote_id IS NULL OR d.decision <> q.quote_status)) AS violations
  UNION ALL
  SELECT 'bill line arithmetic' AS check_name, (SELECT COUNT(*) FROM repair_bill_items WHERE line_total <> ROUND(quantity * unit_price, 2)) AS violations
  UNION ALL
  SELECT 'bill totals match lines and adjustments' AS check_name, (SELECT COUNT(*) FROM repair_bills b WHERE b.subtotal <> COALESCE((SELECT SUM(i.line_total) FROM repair_bill_items i WHERE i.bill_id=b.bill_id),0) OR b.total_amount <> b.subtotal - b.discount_amount + b.tax_amount) AS violations
  UNION ALL
  SELECT 'bill devices belong to billed ticket' AS check_name, (SELECT COUNT(*) FROM repair_bill_items i JOIN repair_bills b ON b.bill_id=i.bill_id JOIN ticket_devices d ON d.ticket_device_id=i.ticket_device_id WHERE d.ticket_id <> b.ticket_id) AS violations
  UNION ALL
  SELECT 'billed quote belongs to device and is approved' AS check_name, (SELECT COUNT(*) FROM repair_bill_items i JOIN repair_quote_items qi ON qi.quote_item_id=i.quote_item_id JOIN repair_quotes q ON q.quote_id=qi.quote_id WHERE q.quote_status <> 'APPROVED' OR NOT (q.ticket_device_id <=> i.ticket_device_id) OR qi.line_total <> i.line_total) AS violations
  UNION ALL
  SELECT 'billed part usage belongs to same device' AS check_name, (SELECT COUNT(*) FROM repair_bill_items i JOIN ticket_parts_used u ON u.ticket_part_use_id=i.ticket_part_use_id WHERE NOT (u.ticket_device_id <=> i.ticket_device_id) OR i.quantity <> u.quantity_used) AS violations
  UNION ALL
  SELECT 'transfer location branches match route' AS check_name, (SELECT COUNT(*) FROM device_transfer_items i JOIN device_transfers t ON t.device_transfer_id=i.device_transfer_id JOIN storage_locations f ON f.location_id=i.from_location_id JOIN storage_locations d ON d.location_id=i.to_location_id WHERE NOT (f.branch_id <=> t.from_branch_id) OR NOT (d.branch_id <=> t.to_branch_id)) AS violations
  UNION ALL
  SELECT 'received transfers have confirmation' AS check_name, (SELECT COUNT(*) FROM device_transfers WHERE status='RECEIVED' AND (received_confirmed_by_user_id IS NULL OR received_confirmed_at IS NULL)) AS violations
  UNION ALL
  SELECT 'notifications are simulated' AS check_name, (SELECT COUNT(*) FROM mock_notification_log WHERE delivery_mode <> 'SIMULATED' OR delivery_status <> 'SIMULATED') AS violations
  UNION ALL
  SELECT 'sample reset tokens cannot be used' AS check_name, (SELECT COUNT(*) FROM password_reset_tokens WHERE used_at IS NULL AND expires_at > UTC_TIMESTAMP()) AS violations
  UNION ALL
  SELECT 'audit ticket references exist' AS check_name, (SELECT COUNT(*) FROM audit_events a LEFT JOIN repair_tickets t ON t.ticket_id=a.entity_id WHERE a.entity_type='repair_ticket' AND t.ticket_id IS NULL) AS violations
) checks
ORDER BY violations DESC, check_name;


SELECT 'customers' AS entity, COUNT(*) AS total FROM customers
UNION ALL SELECT 'tickets', COUNT(*) FROM repair_tickets
UNION ALL SELECT 'devices', COUNT(*) FROM ticket_devices
UNION ALL SELECT 'accounts', COUNT(*) FROM users;
SELECT check_name, violations, IF(violations = 0, 'PASS', 'FAIL') AS result FROM (
 SELECT 'technician branch matches current repair branch' AS check_name, COUNT(*) AS violations
 FROM ticket_devices d JOIN repair_tickets t ON t.ticket_id=d.ticket_id
 WHERE d.assigned_technician_id IS NOT NULL AND NOT EXISTS (
 SELECT 1 FROM user_branch_assignments a WHERE a.user_id=d.assigned_technician_id AND a.branch_id=t.accountable_branch_id)
 UNION ALL SELECT 'exactly one v2 seed marker', ABS(COUNT(*)-1) FROM audit_events WHERE action='CONSOLE_DEMO_V2_SEEDED'
) results;
