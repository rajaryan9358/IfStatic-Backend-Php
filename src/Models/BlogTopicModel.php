<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

final class BlogTopicModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'name',
        'slug',
        'description',
        'sort_order',
        'created_at',
        'updated_at'
    ];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $statement = $this->db->query(
            sprintf('SELECT %s FROM blog_topics ORDER BY sort_order ASC, name ASC', implode(', ', self::COLUMNS))
        );

        return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM blog_topics WHERE id = :id LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM blog_topics WHERE slug = :slug LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): array
    {
        $now = $this->timestamp();
        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'sort_order' => $data['sortOrder'] ?? 0,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $id = $this->insert('blog_topics', $payload);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTopic(int $id, array $data): ?array
    {
        $payload = [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? '',
            'sort_order' => $data['sortOrder'] ?? 0,
            'updated_at' => $this->timestamp(),
        ];
        $this->updateById('blog_topics', $id, $payload);

        return $this->find($id);
    }

    public function deleteTopic(int $id): void
    {
        $this->deleteById('blog_topics', $id);
    }

    /**
     * @param array<int> $blogIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getTopicsByBlogIds(array $blogIds): array
    {
        $map = [];
        if (empty($blogIds)) {
            return $map;
        }

        $placeholders = implode(', ', array_fill(0, count($blogIds), '?'));
        $statement = $this->db->prepare(
            "SELECT rel.blog_id, t." . implode(', t.', self::COLUMNS) . "
             FROM blog_topic_relations rel
             INNER JOIN blog_topics t ON t.id = rel.topic_id
             WHERE rel.blog_id IN ({$placeholders})
             ORDER BY t.sort_order ASC, t.name ASC"
        );
        $statement->execute($blogIds);

        while ($row = $statement->fetch()) {
            $blogId = (int) $row['blog_id'];
            unset($row['blog_id']);
            if (!isset($map[$blogId])) {
                $map[$blogId] = [];
            }
            $map[$blogId][] = $this->mapRow($row);
        }

        return $map;
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
            'slug' => $row['slug'],
            'description' => $row['description'] ?? '',
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }
}
