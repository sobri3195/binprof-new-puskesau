<?php
declare(strict_types=1);
namespace Binprof\Repositories;

use PDO;
use PDOStatement;
use Throwable;

final class Database
{
    public readonly PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec("SET time_zone = '+00:00'");
        $this->pdo->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
    }

    public function run(string $sql, array $args = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach (array_values($args) as $i => $arg) {
            $stmt->bindValue($i + 1, $arg, match (true) {
                is_int($arg) => PDO::PARAM_INT,
                is_null($arg) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $stmt->execute();
        return $stmt;
    }

    public function one(string $sql, array $args = []): ?array
    {
        return $this->run($sql, $args)->fetch() ?: null;
    }

    public function all(string $sql, array $args = []): array
    {
        return $this->run($sql, $args)->fetchAll();
    }

    public function lastId(): int { return (int) $this->pdo->lastInsertId(); }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }
}
