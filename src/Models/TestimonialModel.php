<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;

final class TestimonialModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'name',
        'handle',
        'sort_order',
        'role',
        'company',
        'location',
        'project',
        'budget',
        'timeframe',
        'testimonial',
        'rating',
        'image',
        'skills',
        'page_aliases',
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
    public function list(?string $pagePath = null): array
    {
        $params = [];
        $sql = sprintf('SELECT %s FROM testimonials', implode(', ', self::COLUMNS));
        if ($pagePath) {
            $sql .= ' WHERE JSON_CONTAINS(page_aliases, JSON_QUOTE(:path))';
            $params['path'] = $pagePath;
            $alias = $this->extractAlias($pagePath);
            if ($alias && $alias !== $pagePath) {
                $sql .= ' OR JSON_CONTAINS(page_aliases, JSON_QUOTE(:alias))';
                $params['alias'] = $alias;
            }
        }
        $sql .= ' ORDER BY sort_order ASC, created_at DESC';

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM testimonials WHERE id = :id LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createTestimonial(array $payload): array
    {
        $now = $this->timestamp();
        $data = $this->toPayload($payload);
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $id = $this->insert('testimonials', $data);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateTestimonial(int $id, array $payload): ?array
    {
        $data = $this->toPayload($payload);
        $data['updated_at'] = $this->timestamp();
        $this->updateById('testimonials', $id, $data);

        return $this->find($id);
    }

    public function deleteTestimonial(int $id): void
    {
        $this->deleteById('testimonials', $id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function toPayload(array $payload): array
    {
        return [
            'name' => $payload['name'],
            'handle' => $payload['handle'] ?? null,
            'role' => $payload['role'] ?? null,
            'company' => $payload['company'] ?? null,
            'location' => $payload['location'] ?? null,
            'project' => $payload['project'] ?? null,
            'budget' => $payload['budget'] ?? null,
            'timeframe' => $payload['timeframe'] ?? null,
            'testimonial' => $payload['testimonial'],
            'rating' => $payload['rating'] ?? 5,
            'image' => $payload['image'] ?? null,
            'skills' => Json::encode($payload['skills'] ?? []),
            'page_aliases' => Json::encode($payload['pageAliases'] ?? []),
            'sort_order' => $payload['sortOrder'] ?? 0,
        ];
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
            'handle' => $row['handle'],
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'role' => $row['role'],
            'company' => $row['company'],
            'location' => $row['location'],
            'project' => $row['project'],
            'budget' => $row['budget'],
            'timeframe' => $row['timeframe'],
            'testimonial' => $row['testimonial'],
            'rating' => (int) ($row['rating'] ?? 5),
            'image' => $row['image'],
            'skills' => Json::decode($row['skills']),
            'pageAliases' => Json::decode($row['page_aliases']),
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
        ];
    }

    private function extractAlias(string $pagePath): string
    {
        $parts = array_values(array_filter(explode('/', $pagePath)));
        if (empty($parts)) {
            return $pagePath;
        }

        return end($parts) ?: $pagePath;
    }
}
