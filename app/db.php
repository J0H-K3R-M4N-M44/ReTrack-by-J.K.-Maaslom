<?php
declare(strict_types=1);

/**
 * Return one reusable PDO connection for this request.
 * Edit config/config.php with your local MySQL settings.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $configFile = __DIR__ . '/../config/config.php';

    if (!is_file($configFile)) {
        throw new RuntimeException(
            'Missing config/config.php. Copy config/config.example.php and add your MySQL settings.'
        );
    }

    $config = require $configFile;

    $host = $config['host'] ?? 'localhost';
    $port = (int) ($config['port'] ?? 3306);
    $database = $config['database'] ?? 'repairtrack';
    $username = $config['username'] ?? 'root';
    $password = $config['password'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
