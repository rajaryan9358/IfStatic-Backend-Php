<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Env;
use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $dbname = Env::get('DB_NAME');
        $user = Env::get('DB_USER');
        $password = Env::get('DB_PASSWORD');
        $port = Env::get('DB_PORT', '3306');
        $socket = Env::get('DB_SOCKET');

        if (!$dbname || !$user) {
            throw new RuntimeException('Database configuration is missing.');
        }

        if ($socket) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $socket, $dbname);
        } else {
            $normalizedHost = strtolower(trim($host)) === 'localhost' ? '127.0.0.1' : $host;
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $normalizedHost, $port, $dbname);
        }

        try {
            $pdo = new PDO($dsn, $user, $password ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to the database: ' . $exception->getMessage(), 0, $exception);
        }

        self::$connection = $pdo;

        return self::$connection;
    }
}
