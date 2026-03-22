<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class ContactQueryModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'name',
        'email',
        'subject',
        'message',
        'source',
        'status',
        'created_at',
        'updated_at'
    ];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $sql = sprintf('SELECT %s FROM contact_queries', implode(', ', self::COLUMNS));
        $params = [];
        if (!empty($filters['status'])) {
            $sql .= ' WHERE status = :status';
            $params['status'] = $filters['status'];
        }
        $sql .= ' ORDER BY created_at DESC';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM contact_queries WHERE id = :id LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createQuery(array $payload): array
    {
        $now = $this->timestamp();
        $data = [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'subject' => $payload['subject'] ?? null,
            'message' => $payload['message'],
            'source' => $payload['source'] ?? 'contact',
            'status' => $payload['status'] ?? 'new',
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $id = $this->insert('contact_queries', $data);

        return $this->find($id);
    }

    public function updateStatus(int $id, string $status): ?array
    {
        $statement = $this->db->prepare('UPDATE contact_queries SET status = :status, updated_at = :updated WHERE id = :id');
        $statement->execute([
            'status' => $status,
            'updated' => $this->timestamp(),
            'id' => $id,
        ]);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'subject' => $row['subject'] ?? '',
            'message' => $row['message'] ?? '',
            'source' => $row['source'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }
}
