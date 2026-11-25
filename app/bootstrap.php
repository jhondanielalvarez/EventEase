<?php
declare(strict_types=1);

// EventEase application bootstrap. Local secrets are outside the document root.
date_default_timezone_set('Asia/Manila');

function eventease_private_dir(): string
{
    $configured = getenv('EVENTEASE_PRIVATE_DIR');
    return is_string($configured) && trim($configured) !== ''
        ? rtrim($configured, '/\\')
        : dirname(__DIR__, 3) . '/eventease-private';
}
function eventease_pdo(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $configFile = eventease_private_dir() . '/config.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('EventEase database configuration is missing.');
    }

    $config = require $configFile;
    $db = $config['database'];
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $db['host'],
        $db['port'],
        $db['name']
    );
    $connection = new PDO($dsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $connection->exec("SET time_zone = '+08:00'");
    return $connection;
}

function eventease_deposit_percent(PDO $db): float {
    $v=$db->query("SELECT setting_value FROM app_settings WHERE setting_key='down_payment_percent'")->fetchColumn();
    return $v!==false && is_numeric($v) && (float)$v>=0 && (float)$v<=100 ? (float)$v : 30.0;
}