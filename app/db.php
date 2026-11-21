<?php
declare(strict_types=1);

/**
 * Return one reusable PDO connection for this request.
 * Edit config/config.php with your local MySQL settings.
 * PDO lets the app send SQL queries safely and read their results.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = databaseConfig();
    $pdo = connectDatabase($config, $config['database'] ?? 'repairtrack');
    return $pdo;
}

function databaseConfig(): array
{
    $configFile = __DIR__ . '/../config/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException(
            'Missing config/config.php. Copy config/config.example.php and add your MySQL settings.',
        );
    }

    return require $configFile;
}

// Initial setup connects to MySQL before the repairtrack database exists.
function connectDatabase(array $config, ?string $database): PDO
{
    $host = $config['host'] ?? 'localhost';
    $port = (int) ($config['port'] ?? 3306);
    $username = $config['username'] ?? 'root';
    $password = $config['password'] ?? '';

    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    if ($database !== null) {
        $dsn .= ";dbname={$database}";
    }

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // TIMESTAMP values must match the UTC dates used by repair events and reports.
    $pdo->exec("SET time_zone = '+00:00'");

    return $pdo;
}
