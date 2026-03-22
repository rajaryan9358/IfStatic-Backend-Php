<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class PortfolioServiceTabSeoMetaModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'service_alias',
        'meta_title',
        'meta_description',
        'meta_schema',
        'head_tag_manager',
        'body_tag_manager',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(?string $serviceAlias = null): array
    {
        if ($serviceAlias !== null && trim($serviceAlias) !== '') {
            $statement = $this->db->prepare(
                sprintf(
                    'SELECT %s FROM portfolio_service_tab_seo_meta WHERE service_alias = :service_alias LIMIT 1',
                    implode(', ', self::COLUMNS)
                )
            );
            $statement->execute(['service_alias' => strtolower(trim($serviceAlias))]);
            $row = $statement->fetch();

            return $row ? [$this->mapRow($row)] : [];
        }

        $statement = $this->db->query(
            sprintf(
                'SELECT %s FROM portfolio_service_tab_seo_meta ORDER BY service_alias ASC',
                implode(', ', self::COLUMNS)
            )
        );

        return array_map(fn (array $row) => $this->mapRow($row), $statement->fetchAll());
    }

    public function findByServiceAlias(string $serviceAlias): ?array
    {
        $statement = $this->db->prepare(
            sprintf(
                'SELECT %s FROM portfolio_service_tab_seo_meta WHERE service_alias = :service_alias LIMIT 1',
                implode(', ', self::COLUMNS)
            )
        );
        $statement->execute(['service_alias' => strtolower(trim($serviceAlias))]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(string $serviceAlias, array $data): array
    {
        $now = $this->timestamp();

        $statement = $this->db->prepare(
            'INSERT INTO portfolio_service_tab_seo_meta '
            . '(service_alias, meta_title, meta_description, meta_schema, head_tag_manager, body_tag_manager, created_at, updated_at) '
            . 'VALUES (:service_alias, :meta_title, :meta_description, :meta_schema, :head_tag_manager, :body_tag_manager, :created_at, :updated_at) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'meta_title = VALUES(meta_title), '
            . 'meta_description = VALUES(meta_description), '
            . 'meta_schema = VALUES(meta_schema), '
            . 'head_tag_manager = VALUES(head_tag_manager), '
            . 'body_tag_manager = VALUES(body_tag_manager), '
            . 'updated_at = VALUES(updated_at)'
        );

        $normalized = strtolower(trim($serviceAlias));

        $statement->execute([
            'service_alias' => $normalized,
            'meta_title' => $data['metaTitle'] ?? null,
            'meta_description' => $data['metaDescription'] ?? null,
            'meta_schema' => $data['metaSchema'] ?? null,
            'head_tag_manager' => $data['headTagManager'] ?? null,
            'body_tag_manager' => $data['bodyTagManager'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $row = $this->findByServiceAlias($normalized);
        if (!$row) {
            throw new RuntimeException('Unable to load portfolio service-tab SEO meta after upsert.');
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
            'serviceAlias' => $row['service_alias'],
            'metaTitle' => $row['meta_title'] ?? '',
            'metaDescription' => $row['meta_description'] ?? '',
            'metaSchema' => $row['meta_schema'] ?? '',
            'headTagManager' => $row['head_tag_manager'] ?? '',
            'bodyTagManager' => $row['body_tag_manager'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }
}
