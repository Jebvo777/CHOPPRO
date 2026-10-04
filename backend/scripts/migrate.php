<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/Autoloader.php';

use Choppro\Database\Connection;

$connection = Connection::fromConfig(require dirname(__DIR__) . '/config/database.php');
$pdo = $connection->pdo();

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (" .
    "name VARCHAR(255) PRIMARY KEY, " .
    "applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP" .
    ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$directory = dirname(__DIR__) . '/database/migrations';
$files = glob($directory . '/*.sql') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $name = basename($file);
    $check = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name = ?');
    $check->execute([$name]);
    if ((int) $check->fetchColumn() > 0) {
        echo "SKIP {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Cannot read migration {$name}");
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $insert = $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)');
        $insert->execute([$name]);
        $pdo->commit();
        echo "OK {$name}\n";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
