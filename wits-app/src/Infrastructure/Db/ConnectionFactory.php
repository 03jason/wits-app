<?php
declare(strict_types=1);

namespace App\Infrastructure\Db;

use PDO;
use RuntimeException;

final class ConnectionFactory
{
    public static function makeFromEnv(): PDO
    {
        $driver = getenv('DB_DRIVER') ?: 'mysql';
        $host   = getenv('DB_HOST')   ?: 'db';
        $port   = getenv('DB_PORT')   ?: '3306';
        $db     = getenv('DB_NAME')   ?: 'wits';
        $user   = getenv('DB_USER')   ?: 'wits';
        $pass   = getenv('DB_PASS')   ?: 'secret';

        if ($driver !== 'mysql') {
            throw new RuntimeException('Unsupported DB driver: ' . $driver);
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}
