<?php
declare(strict_types=1);
namespace Choppro\Stage2;
use PDO;

final class Db
{
    public PDO $pdo;
    public function __construct(public array $config)
    {
        $d = $config['database'];
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $d['name'])) throw new \RuntimeException('Некорректное имя базы');
        $this->pdo = new PDO('mysql:host='.$d['host'].';port='.$d['port'].';dbname='.$d['name'].';charset=utf8mb4', $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
        $this->pdo->exec("SET time_zone = '+00:00'");
    }
    public function run(string $sql, array $args = []): \PDOStatement
    {
        $q = $this->pdo->prepare($sql); $q->execute($args); return $q;
    }
    public function one(string $sql, array $args = []): ?array { return $this->run($sql, $args)->fetch() ?: null; }
    public function all(string $sql, array $args = []): array { return $this->run($sql, $args)->fetchAll(); }
    public function scalar(string $sql, array $args = []): mixed { return $this->run($sql, $args)->fetchColumn(); }
    public function transaction(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) return $fn();
        $this->pdo->beginTransaction();
        try { $v = $fn(); $this->pdo->commit(); return $v; }
        catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }
}
