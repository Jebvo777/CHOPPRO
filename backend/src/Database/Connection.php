<?php

declare(strict_types=1);

namespace Choppro\Database;

use PDO;

final class Connection
{
    private function __construct(private readonly PDO $pdo)
    {
    }

    public static function fromConfig(array $config): self
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $config['database'],
        );

        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return new self($pdo);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
