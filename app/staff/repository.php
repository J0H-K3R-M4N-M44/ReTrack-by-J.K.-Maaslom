<?php
declare(strict_types=1);
require_once __DIR__ . '/../web.php';
require_once __DIR__ . '/../intake/repository.php';

function staffRoles(): array
{
    return [
        'MANAGER' => 'Manager',
        'INTAKE_AGENT' => 'Intake agent',
        'TECHNICIAN' => 'Technician',
        'COURIER' => 'Courier',
        'VIEWER' => 'Viewer',
    ];
}

function staffReadValues(array $input): array
{
    $branches = is_array($input['branches'] ?? null) ? $input['branches'] : [];
    $branches = array_filter(
        $branches,
        static fn($id) => is_string($id) && ctype_digit($id) && (int) $id > 0,
    );
    $branches = array_values(array_unique(array_map('intval', $branches)));
    sort($branches);
    return [
        'full_name' => trim(requestText($input, 'full_name')),
        'email' => strtolower(trim(requestText($input, 'email'))),
        'role' => requestText($input, 'role'),
        'branches' => $branches,
        'default_branch' => requestText($input, 'default_branch'),
    ];
}

function staffValidate(PDO $pdo, array $values, int $adminId, int $editingId = 0): array
{
    $errors = [];
    if (
        $values['full_name'] === '' ||
        !mb_check_encoding($values['full_name'], 'UTF-8') ||
        mb_strlen($values['full_name']) > 160
    ) {
        $errors['full_name'] = 'Enter a name of up to 160 characters.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
        $errors['email'] = 'Enter a valid email address of up to 190 characters.';
    }
    if (!isset(staffRoles()[$values['role']])) {
        $errors['role'] = 'Choose a staff role. Administrator access cannot be granted here.';
    }
    $allowed = array_map('intval', array_column(intakeBranches($pdo, $adminId), 'branch_id'));
    if (!$values['branches'] || array_diff($values['branches'], $allowed)) {
        $errors['branches'] =
            'Choose at least one active branch assigned to your administrator account.';
    }
    if (
        !ctype_digit($values['default_branch']) ||
        !in_array((int) $values['default_branch'], $values['branches'], true)
    ) {
        $errors['default_branch'] = 'Choose a home branch from the selected branches.';
    }
    $query = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
    $query->execute([$values['email'], $editingId]);
    if ($query->fetchColumn()) {
        $errors['email'] =
            'That email is already in use. Use a different email or find the existing staff account.';
    }
    return $errors;
}

// Require ownership of every assigned branch, not just one overlapping branch.
// This prevents a branch admin changing a shared account outside their authority.
function staffScopeSql(): string
{
    return "u.role <> 'ADMIN'
        AND EXISTS (SELECT 1 FROM user_branch_assignments s WHERE s.user_id = u.user_id)
        AND NOT EXISTS (
            SELECT 1 FROM user_branch_assignments s
            WHERE s.user_id = u.user_id AND NOT EXISTS (
                SELECT 1 FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id
                WHERE a.user_id = ? AND a.branch_id = s.branch_id
            )
        )";
}

function staffLoad(PDO $pdo, int $userId, int $adminId, bool $lock = false): array
{
    $query = $pdo->prepare(
        'SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.updated_at FROM users u WHERE u.user_id = ? AND ' .
            staffScopeSql() .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $query->execute([$userId, $adminId]);
    $user = $query->fetch();
    if (!$user) {
        throw new DomainException(
            'This staff account is unavailable or includes branches you do not manage.',
        );
    }
    $assignments = $pdo->prepare(
        'SELECT branch_id, is_default FROM user_branch_assignments WHERE user_id = ? ORDER BY branch_id' .
            ($lock ? ' FOR UPDATE' : ''),
    );
    $assignments->execute([$userId]);
    $user['assignments'] = $assignments->fetchAll();
    return $user;
}

function staffVersion(array $user): string
{
    return hash('sha256', json_encode($user, JSON_THROW_ON_ERROR));
}

function staffList(PDO $pdo, int $adminId, array $input): array
{
    $term = mb_substr(trim(requestText($input, 'q')), 0, 190);
    $role = requestText($input, 'role');
    $status = requestText($input, 'status');
    $where = staffScopeSql() . " AND (u.full_name LIKE ? ESCAPE '!' OR u.email LIKE ? ESCAPE '!')";
    $params = [$adminId, likePattern($term), likePattern($term)];
    if (isset(staffRoles()[$role])) {
        $where .= ' AND u.role = ?';
        $params[] = $role;
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where .= ' AND u.is_active = ?';
        $params[] = $status === 'active' ? 1 : 0;
    }
    $count = $pdo->prepare('SELECT COUNT(*) FROM users u WHERE ' . $where);
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / 12));
    $rawPage = requestText($input, 'page', '1');
    $page = ctype_digit($rawPage) ? min($pages, max(1, (int) $rawPage)) : 1;
    $offset = ($page - 1) * 12;
    // Fetch branch labels together. Do not run a separate query for every row.
    $query = $pdo->prepare(
        "SELECT u.user_id, u.full_name, u.email, u.role, u.is_active, u.last_login_at,
        (SELECT GROUP_CONCAT(b.branch_code ORDER BY b.branch_code SEPARATOR ', ')
         FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id WHERE a.user_id = u.user_id) AS branch_codes,
        EXISTS (SELECT 1 FROM password_reset_tokens p WHERE p.user_id = u.user_id AND p.used_at IS NULL AND p.expires_at > UTC_TIMESTAMP()) AS has_pending_link
        FROM users u WHERE " .
            $where .
            ' ORDER BY u.full_name, u.user_id LIMIT 12 OFFSET ' .
            $offset,
    );
    $query->execute($params);
    return [
        'rows' => $query->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'from' => $total ? $offset + 1 : 0,
        'to' => min($offset + 12, $total),
    ];
}

function staffLockAdmin(PDO $pdo, int $adminId): void
{
    $query = $pdo->prepare(
        "SELECT user_id FROM users WHERE user_id = ? AND role = 'ADMIN' AND is_active = 1 FOR UPDATE",
    );
    $query->execute([$adminId]);
    if (!$query->fetchColumn()) {
        throw new DomainException('An active administrator must manage staff.');
    }
    // Keep this admin's branch assignments stable throughout the write.
    $query = $pdo->prepare(
        'SELECT a.branch_id FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id WHERE a.user_id = ? FOR UPDATE',
    );
    $query->execute([$adminId]);
    $query->fetchAll();
}

function staffWriteBranches(PDO $pdo, int $userId, array $values): void
{
    $pdo->prepare('DELETE FROM user_branch_assignments WHERE user_id = ?')->execute([$userId]);
    $insert = $pdo->prepare(
        'INSERT INTO user_branch_assignments (user_id, branch_id, is_default) VALUES (?, ?, ?)',
    );
    foreach ($values['branches'] as $branchId) {
        $insert->execute([
            $userId,
            $branchId,
            $branchId === (int) $values['default_branch'] ? 1 : 0,
        ]);
    }
}

function staffAudit(PDO $pdo, int $adminId, int $userId, string $action, array $details): void
{
    $query = $pdo->prepare(
        "INSERT INTO audit_events (actor_type, actor_user_id, entity_type, entity_id, action, after_data, created_at) VALUES ('USER', ?, 'user', ?, ?, ?, UTC_TIMESTAMP())",
    );
    $query->execute([$adminId, $userId, $action, json_encode($details, JSON_THROW_ON_ERROR)]);
}

// Activation tokens have a distinct prefix; the password-reset endpoint accepts
// only 64 hexadecimal characters, so the two token purposes cannot be exchanged.
function staffIssueActivation(PDO $pdo, int $userId): string
{
    $token = 'staff_' . bin2hex(random_bytes(32));
    $pdo->prepare(
        'DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL',
    )->execute([$userId]);
    $insert = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 24 HOUR, UTC_TIMESTAMP())',
    );
    $insert->execute([$userId, hash('sha256', $token)]);
    return $token;
}

function staffCreate(PDO $pdo, int $adminId, array $values): array
{
    $pdo->beginTransaction();
    try {
        staffLockAdmin($pdo, $adminId);
        $errors = staffValidate($pdo, $values, $adminId);
        if ($errors) {
            throw new DomainException(implode(' ', $errors));
        }
        // No usable password exists until the staff member activates the account.
        $insert = $pdo->prepare(
            'INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 0)',
        );
        $insert->execute([
            $values['full_name'],
            $values['email'],
            password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
            $values['role'],
        ]);
        $userId = (int) $pdo->lastInsertId();
        staffWriteBranches($pdo, $userId, $values);
        $token = staffIssueActivation($pdo, $userId);
        staffAudit($pdo, $adminId, $userId, 'STAFF_CREATED', $values);
        $pdo->commit();
        return ['user_id' => $userId, 'token' => $token];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function staffUpdate(PDO $pdo, int $adminId, int $userId, string $version, array $values): void
{
    $pdo->beginTransaction();
    try {
        staffLockAdmin($pdo, $adminId);
        $before = staffLoad($pdo, $userId, $adminId, true);
        if (!hash_equals($version, staffVersion($before))) {
            throw new DomainException('This account changed. Reload it before editing.');
        }
        $errors = staffValidate($pdo, $values, $adminId, $userId);
        if ($errors) {
            throw new DomainException(implode(' ', $errors));
        }
        $query = $pdo->prepare(
            'UPDATE users SET full_name = ?, email = ?, role = ? WHERE user_id = ?',
        );
        $query->execute([$values['full_name'], $values['email'], $values['role'], $userId]);
        staffWriteBranches($pdo, $userId, $values);
        // Revoke outstanding links if the account's identity or permissions change.
        if (
            $before['email'] !== $values['email'] ||
            $before['role'] !== $values['role'] ||
            array_map('intval', array_column($before['assignments'], 'branch_id')) !==
                $values['branches']
        ) {
            $pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL',
            )->execute([$userId]);
        }
        staffAudit($pdo, $adminId, $userId, 'STAFF_UPDATED', $values);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function staffChangeAccess(
    PDO $pdo,
    int $adminId,
    int $userId,
    string $version,
    string $action,
): ?string {
    $pdo->beginTransaction();
    try {
        staffLockAdmin($pdo, $adminId);
        $user = staffLoad($pdo, $userId, $adminId, true);
        if (!hash_equals($version, staffVersion($user))) {
            throw new DomainException('This account changed. Reload it before continuing.');
        }
        $token = null;
        if ($action === 'deactivate') {
            $pdo->prepare('UPDATE users SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
            $pdo->prepare(
                'DELETE FROM password_reset_tokens WHERE user_id = ? AND used_at IS NULL',
            )->execute([$userId]);
        } elseif ($action === 'invite' && !(int) $user['is_active']) {
            $token = staffIssueActivation($pdo, $userId);
        } else {
            throw new DomainException('Only inactive staff can receive an activation link.');
        }
        staffAudit(
            $pdo,
            $adminId,
            $userId,
            $action === 'invite' ? 'STAFF_ACTIVATION_REISSUED' : 'STAFF_DEACTIVATED',
            ['role' => $user['role']],
        );
        $pdo->commit();
        return $token;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function staffActivate(PDO $pdo, string $token, string $password): bool
{
    if (!preg_match('/^staff_[a-f0-9]{64}$/', $token) || !passwordIsAcceptable($password)) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        // Lock user before token, matching management's lock order.
        $find = $pdo->prepare("SELECT u.user_id FROM users u WHERE u.is_active = 0 AND u.role <> 'ADMIN'
            AND EXISTS (SELECT 1 FROM password_reset_tokens p WHERE p.user_id = u.user_id AND p.token_hash = ? AND p.used_at IS NULL AND p.expires_at > UTC_TIMESTAMP()) FOR UPDATE");
        $find->execute([hash('sha256', $token)]);
        $userId = $find->fetchColumn();
        if (!$userId) {
            $pdo->rollBack();
            return false;
        }
        $check = $pdo->prepare(
            'SELECT reset_token_id FROM password_reset_tokens WHERE user_id = ? AND token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE',
        );
        $check->execute([$userId, hash('sha256', $token)]);
        if (!$check->fetchColumn()) {
            $pdo->rollBack();
            return false;
        }
        $branches = $pdo->prepare(
            'SELECT a.branch_id FROM user_branch_assignments a JOIN branches b ON b.branch_id = a.branch_id WHERE a.user_id = ? AND b.is_active = 1 FOR UPDATE',
        );
        $branches->execute([$userId]);
        if (!$branches->fetchColumn()) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare(
            'UPDATE users SET password_hash = ?, is_active = 1 WHERE user_id = ?',
        )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
        $pdo->prepare(
            'UPDATE password_reset_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL',
        )->execute([$userId]);
        staffAudit($pdo, (int) $userId, (int) $userId, 'STAFF_ACTIVATED', []);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}
