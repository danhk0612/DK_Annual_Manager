<?php

declare(strict_types=1);

namespace DKAnnual;

use PDO;

final class Database
{
    public static function connect(Config $config): PDO
    {
        $host = (string) $config->get('database.host', '127.0.0.1');
        $port = (int) $config->get('database.port', 3306);
        $database = (string) $config->get('database.database');
        $username = (string) $config->get('database.username');
        $password = (string) $config->get('database.password');
        $charset = (string) $config->get('database.charset', 'utf8mb4');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
