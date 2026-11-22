<?php
declare(strict_types=1);

/**
 * Read-only catalogs for the EDP classroom UI.
 * Database references determine allowed device types, defects and statuses.
 * Do not take table or SQL identifiers from a browser request.
 */
function edpReferenceCatalogs(PDO $pdo): array
{
    return [
        'device_types' => $pdo->query('SELECT device_type_code AS code, label FROM device_types
            WHERE is_active = 1 ORDER BY sort_order, label')->fetchAll(PDO::FETCH_ASSOC),
        'defect_types' => $pdo->query('SELECT defect_code AS code, label FROM defect_types
            WHERE is_active = 1 ORDER BY sort_order, label')->fetchAll(PDO::FETCH_ASSOC),
        'repair_statuses' => $pdo->query('SELECT status_code AS code, label, is_terminal
            FROM repair_statuses ORDER BY sort_order, label')->fetchAll(PDO::FETCH_ASSOC),
    ];
}

/** Scope storage-location choices to the signed-in staff member's active branch. */
function edpIntakeLocations(PDO $pdo, int $actorId, int $branchId): array
{
    $stmt = $pdo->prepare('SELECT l.location_id, l.location_code, l.location_name
        FROM storage_locations l
        JOIN branches b ON b.branch_id = l.branch_id AND b.is_active = 1
        JOIN user_branch_assignments a ON a.branch_id = b.branch_id AND a.user_id = ?
        JOIN users u ON u.user_id = a.user_id
        WHERE l.branch_id = ? AND l.is_active = 1 AND u.is_active = 1
          AND u.role_code IN (\'ADMIN\',\'MANAGER\',\'INTAKE_AGENT\')
        ORDER BY l.location_name');
    $stmt->execute([$actorId, $branchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}