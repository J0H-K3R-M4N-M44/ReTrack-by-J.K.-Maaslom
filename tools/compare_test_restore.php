<?php
declare(strict_types=1);

// Read-only SQL reconciliation for TWO fictional, local repairtrack_test_* databases.
// Never creates, alters, restores, or drops schemas. Do not use on shop data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../app/db.php';

function restoreTestName(string $value): bool
{
    return preg_match('/^repairtrack_test_[A-Za-z0-9_]{1,48}$/D', $value) === 1;
}
function restoreLocalConfig(array $config, string $source): bool
{
    $host = strtolower(trim((string) ($config['host'] ?? '')));
    $parsed = parse_url((string) ($config['app_url'] ?? ''));
    return restoreTestName($source) &&
        ($config['database'] ?? null) === $source &&
        in_array($host, ['127.0.0.1', 'localhost', '::1'], true) &&
        is_array($parsed) &&
        in_array(strtolower((string) ($parsed['host'] ?? '')), ['127.0.0.1', 'localhost', '::1'], true) &&
        in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true);
}
function restoreSchemaTables(PDO $pdo, string $database): array
{
    $statement = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=? AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME",
    );
    $statement->execute([$database]);
    return $statement->fetchAll(PDO::FETCH_COLUMN);
}
function restoreColumnSignature(PDO $pdo, string $database): string
{
    $stmt = $pdo->prepare(
        "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,COLUMN_TYPE,IS_NULLABLE,
                COLUMN_DEFAULT,EXTRA,CHARACTER_SET_NAME,COLLATION_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME,ORDINAL_POSITION",
    );
    $stmt->execute([$database]);
    $digest = hash_init('sha256');
    while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
        hash_update($digest, json_encode(array_values($row), JSON_THROW_ON_ERROR));
        hash_update($digest, "\n");
    }
    return hash_final($digest);
}
function restoreTableFingerprint(PDO $pdo, string $table): array
{
    if (!preg_match('/^[A-Za-z0-9_]+$/D', $table)) {
        throw new RuntimeException('Unexpected schema identifier.');
    }
    // Small fictional fixtures only. Large production datasets require a
    // different snapshot/content comparison and independent resource limits.
    $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    if ($count > 10000) {
        throw new RuntimeException('Test table exceeds the 10,000-row limit.');
    }
    // Order-independent multiset fingerprints detect same-count changes even
    // where a table lacks an explicit primary key.
    $rows = [];
    $statement = $pdo->query('SELECT * FROM `' . $table . '`');
    while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
        $hash = hash_init('sha256');
        foreach ($row as $key => $value) {
            $bytes = $value === null ? '' : (string) $value;
            hash_update($hash, pack('N', strlen($key)) . $key);
            hash_update($hash, $value === null ? 'N' : 'V' . pack('N', strlen($bytes)) . $bytes);
        }
        $rows[] = hash_final($hash);
    }
    sort($rows, SORT_STRING);
    return [$count, hash('sha256', implode("\n", $rows))];
}
function restoreCompare(PDO $source, PDO $restored, string $sourceDb, string $restoredDb): int
{
    $from = restoreSchemaTables($source, $sourceDb);
    $to = restoreSchemaTables($restored, $restoredDb);
    if (!$from || $from !== $to) {
        throw new RuntimeException('Restored base-table inventory differs.');
    }
    if (restoreColumnSignature($source, $sourceDb) !== restoreColumnSignature($restored, $restoredDb)) {
        throw new RuntimeException('Restored table column definitions differ.');
    }
    foreach ($from as $table) {
        if (restoreTableFingerprint($source, $table) !== restoreTableFingerprint($restored, $table)) {
            throw new RuntimeException('Restored table content differs.');
        }
    }
    return count($from);
}

if ($argc === 2 && $argv[1] === '--help') {
    echo "REPAIRTRACK_DISPOSABLE_RECONCILE=I_CONFIRM_DISPOSABLE_ONLY php tools/compare_test_restore.php SOURCE_DB RESTORED_DB\n";
    echo "Both schemas must already exist, begin repairtrack_test_, be local, and differ.\n";
    echo "Read-only; no passwords or record contents printed. Exit 0 pass, 2 block.\n";
    exit(0);
}
if ($argc !== 3 ||
    getenv('REPAIRTRACK_DISPOSABLE_RECONCILE') !== 'I_CONFIRM_DISPOSABLE_ONLY' ||
    !restoreTestName($argv[1]) || !restoreTestName($argv[2]) ||
    $argv[1] === $argv[2]) {
    fwrite(STDERR, "BLOCK: two distinct, confirmed fictional test schemas are required.\n");
    exit(2);
}

try {
    $config = databaseConfig();
    if (!restoreLocalConfig($config, $argv[1])) {
        throw new RuntimeException('Configured source or local environment is not disposable.');
    }
    $host = (string) $config['host'];
    $port = (int) ($config['port'] ?? 3306);
    if ($port < 1 || $port > 65535) {
        throw new RuntimeException('Invalid local MySQL port.');
    }
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdoFor = static function (string $db) use ($config, $host, $port, $options): PDO {
        return new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            $options,
        );
    };
    $source = $pdoFor($argv[1]);
    $target = $pdoFor($argv[2]);
    $tables = restoreCompare($source, $target, $argv[1], $argv[2]);
    echo "PASS: $tables fictional SQL tables match in row counts, columns and record fingerprints.\n";
    echo "Private file hashes, SQL constraints, HTTP checks and human XAMPP acceptance remain separate.\n";
    exit(0);
} catch (Throwable $error) {
    // Never disclose DSNs, SQL errors, credentials, or application row data.
    fwrite(STDERR, "BLOCK: fictional SQL reconciliation failed. Privately check schema names, access, and contents.\n");
    exit(2);
}
