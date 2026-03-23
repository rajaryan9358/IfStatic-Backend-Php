<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;
use Throwable;

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

    /** @var array<int, string> */
    private array $availableColumns = [];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->availableColumns = $this->detectColumns();
    }

    /**
     * @return array<int, string>
     */
    private function detectColumns(): array
    {
        try {
            $statement = $this->db->query('SHOW COLUMNS FROM testimonials');
            $fields = $statement ? array_column($statement->fetchAll(), 'Field') : [];
            return $fields ?: self::COLUMNS;
        } catch (Throwable $e) {
            return self::COLUMNS;
        }
    }

    /**
     * @return array<int, string>
     */
    private function selectableColumns(): array
    {
        return array_values(array_intersect(self::COLUMNS, $this->availableColumns));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPayload(array $payload): array
    {
        return array_filter(
            $payload,
            fn ($value, string $key) => in_array($key, $this->availableColumns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $pagePath = null): array
    {
        $params = [];
        $sql = sprintf('SELECT %s FROM testimonials', implode(', ', $this->selectableColumns()));

        if ($pagePath && in_array('page_aliases', $this->availableColumns, true)) {
            $sql .= ' WHERE JSON_CONTAINS(page_aliases, JSON_QUOTE(:path))';
            $params['path'] = $pagePath;
            $alias = $this->extractAlias($pagePath);
            if ($alias && $alias !== $pagePath) {
                $sql .= ' OR JSON_CONTAINS(page_aliases, JSON_QUOTE(:alias))';
                $params['alias'] = $alias;
            }
        }

        $sortColumn = in_array('sort_order', $this->availableColumns, true) ? 'sort_order' : 'id';
        $dateColumn = in_array('created_at', $this->availableColumns, true) ? 'created_at' : 'id';
        $sql .= sprintf(' ORDER BY %s ASC, %s DESC', $sortColumn, $dateColumn);

        try {
            $statement = $this->db->prepare($sql);
            $statement->execute($params);

            return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
        } catch (Throwable $e) {
            $fallbackSql = sprintf(
                'SELECT %s FROM testimonials ORDER BY %s ASC, %s DESC',
                implode(', ', $this->selectableColumns()),
                $sortColumn,
                $dateColumn
            );
            $statement = $this->db->prepare($fallbackSql);
            $statement->execute();

            return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
        }
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM testimonials WHERE id = :id LIMIT 1', implode(', ', $this->selectableColumns()))
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
        return $this->filterPayload([
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
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? '',
            'handle' => $row['handle'] ?? null,
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'role' => $row['role'] ?? null,
            'company' => $row['company'] ?? null,
            'location' => $row['location'] ?? null,
            'project' => $row['project'] ?? null,
            'budget' => $row['budget'] ?? null,
            'timeframe' => $row['timeframe'] ?? null,
            'testimonial' => $row['testimonial'] ?? '',
            'rating' => (int) ($row['rating'] ?? 5),
            'image' => $row['image'] ?? null,
            'skills' => Json::decode($row['skills'] ?? null),
            'pageAliases' => Json::decode($row['page_aliases'] ?? null),
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
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
