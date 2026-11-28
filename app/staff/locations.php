<?php
declare(strict_types=1);
require_once __DIR__ . '/../intake/repository.php';

// Only real assigned branches and ordinary storage bins/workbenches can be added.
// This does not create branches, repair ownership, transit records or inventory.
function locationReadValues(array $input): array
{
    return [
        'branch_id' => requestText($input, 'branch_id'),
        'location_code' => strtoupper(trim(requestText($input, 'location_code'))),
        'location_name' => trim(requestText($input, 'location_name')),
        'location_type' => requestText($input, 'location_type', 'BRANCH_BIN'),
    ];
}

function locationValidate(array $values): array
{
    $errors = [];
    if (!ctype_digit($values['branch_id']) || (int) $values['branch_id'] < 1) {
        $errors['branch_id'] = 'Choose an assigned branch.';
    }
    if (!preg_match('/^[A-Z0-9][A-Z0-9-]{1,39}$/D', $values['location_code'])) {
        $errors['location_code'] = 'Use 2–40 letters, numbers or hyphens; start with a letter or number.';
    }
    if (
        !mb_check_encoding($values['location_name'], 'UTF-8') ||
        mb_strlen($values['location_name']) < 2 ||
        mb_strlen($values['location_name']) > 120 ||
        preg_match('/[\x00-\x1F\x7F]/', $values['location_name'])
    ) {
        $errors['location_name'] = 'Enter a readable location name of 2–120 characters.';
    }
    if (!in_array($values['location_type'], ['BRANCH_BIN', 'WORKBENCH'], true)) {
        $errors['location_type'] = 'Choose a branch bin or a workbench.';
    }
    return $errors;
}

function locationCreate(PDO $pdo, int $actorId, array $values): int
{
    $errors = locationValidate($values);
    if ($errors) {
        throw new DomainException(implode(' ', $errors));
    }
    $pdo->beginTransaction();
    try {
        $actor = $pdo->prepare(
            "SELECT user_id, role FROM users WHERE user_id=? AND is_active=1 FOR UPDATE",
        );
        $actor->execute([$actorId]);
        $user = $actor->fetch();
        if (!$user || !in_array($user['role'], ['ADMIN', 'MANAGER'], true)) {
            throw new DomainException('Only active administrators and managers can set up storage locations.');
        }

        // Branch is rechecked on write, independent of the submitted form.
        $branch = $pdo->prepare(
            'SELECT b.branch_id FROM branches b
             JOIN user_branch_assignments a ON a.branch_id=b.branch_id
             WHERE b.branch_id=? AND a.user_id=? AND b.is_active=1 FOR UPDATE',
        );
        $branch->execute([(int) $values['branch_id'], $actorId]);
        if (!$branch->fetchColumn()) {
            throw new DomainException('That branch is inactive or outside your assigned work.');
        }
        $exists = $pdo->prepare(
            'SELECT location_id FROM storage_locations
             WHERE branch_id=? AND location_code=? FOR UPDATE',
        );
        $exists->execute([(int) $values['branch_id'], $values['location_code']]);
        if ($exists->fetchColumn()) {
            throw new DomainException('This branch already has a location with that code.');
        }
        $insert = $pdo->prepare(
            'INSERT INTO storage_locations
             (branch_id,location_code,location_name,location_type)
             VALUES (?,?,?,?)',
        );
        $insert->execute([
            (int) $values['branch_id'],
            $values['location_code'],
            $values['location_name'],
            $values['location_type'],
        ]);
        $locationId = (int) $pdo->lastInsertId();
        $audit = $pdo->prepare(
            "INSERT INTO audit_events
             (actor_type,actor_user_id,entity_type,entity_id,action,after_data,created_at)
             VALUES ('USER',?,'storage_location',?,'STORAGE_LOCATION_CREATED',?,UTC_TIMESTAMP())",
        );
        $audit->execute([
            $actorId,
            $locationId,
            json_encode($values, JSON_THROW_ON_ERROR),
        ]);
        $pdo->commit();
        return $locationId;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === 1062) {
            throw new DomainException('This location code already exists at the selected branch.');
        }
        throw $error;
    }
}

function locationPage(PDO $pdo, int $actorId, string $rawPage = '1'): array
{
    $from = 'FROM storage_locations l
        JOIN branches b ON b.branch_id=l.branch_id AND b.is_active=1
        JOIN user_branch_assignments a ON a.branch_id=b.branch_id
        WHERE a.user_id=?';
    $count = $pdo->prepare('SELECT COUNT(*) ' . $from);
    $count->execute([$actorId]);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 20));
    $page = ctype_digit($rawPage) ? min($pages, max(1, (int) $rawPage)) : 1;
    $offset = ($page - 1) * 20;
    $query = $pdo->prepare(
        'SELECT l.location_id,l.location_code,l.location_name,l.location_type,
                l.is_active,b.branch_name ' . $from .
        ' ORDER BY b.branch_name,l.location_code LIMIT 20 OFFSET ' . $offset,
    );
    $query->execute([$actorId]);
    return [
        'rows' => $query->fetchAll(),
        'page' => $page,
        'pages' => $pages,
        'total' => $total,
    ];
}
