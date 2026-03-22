<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class SeoMetaModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'page',
        'meta_type',
        'meta_data',
        'created_at',
        'updated_at',
    ];

    public function __construct(PDO $db)
    {
        parent::__construct($db);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $page = null): array
    {
        if ($page !== null) {
            $statement = $this->db->prepare(
                sprintf('SELECT %s FROM seo_meta WHERE page = :page ORDER BY meta_type ASC', implode(', ', self::COLUMNS))
            );
            $statement->execute(['page' => $page]);
        } else {
            $statement = $this->db->query(
                sprintf('SELECT %s FROM seo_meta ORDER BY page ASC, meta_type ASC', implode(', ', self::COLUMNS))
            );
        }

        $rows = $statement->fetchAll();
        return array_map(fn (array $row) => $this->mapRow($row), $rows);
    }

    public function findByPageAndType(string $page, string $metaType): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM seo_meta WHERE page = :page AND meta_type = :meta_type LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['page' => $page, 'meta_type' => $metaType]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function upsert(string $page, string $metaType, ?string $metaData): array
    {
        $now = $this->timestamp();

        $statement = $this->db->prepare(
            'INSERT INTO seo_meta (page, meta_type, meta_data, created_at, updated_at) '
            . 'VALUES (:page, :meta_type, :meta_data, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE meta_data = VALUES(meta_data), updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'page' => $page,
            'meta_type' => $metaType,
            'meta_data' => $metaData,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = $this->findByPageAndType($page, $metaType);
        if (!$row) {
            throw new RuntimeException('Unable to load SEO meta after upsert.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'page' => $row['page'],
            'metaType' => $row['meta_type'],
            'metaData' => $row['meta_data'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }
}
