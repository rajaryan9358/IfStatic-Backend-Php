<?php

declare(strict_types=1);

namespace App\Models;

use DateTimeImmutable;
use PDO;

abstract class BaseModel
{
    protected PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    protected function timestamp(): string
    {
        return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function insert(string $table, array $payload): int
    {
        $columns = array_keys($payload);
        $setClause = implode(', ', array_map(static fn ($column) => "`{$column}` = :{$column}", $columns));
        $statement = $this->db->prepare("INSERT INTO {$table} SET {$setClause}");
        $statement->execute($payload);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function updateById(string $table, int $id, array $payload): void
    {
        $columns = array_keys($payload);
        $setClause = implode(', ', array_map(static fn ($column) => "`{$column}` = :{$column}", $columns));
        $payload['id'] = $id;
        $statement = $this->db->prepare("UPDATE {$table} SET {$setClause} WHERE id = :id");
        $statement->execute($payload);
    }

    protected function deleteById(string $table, int $id): void
    {
        $statement = $this->db->prepare("DELETE FROM {$table} WHERE id = :id");
        $statement->execute(['id' => $id]);
    }
}
