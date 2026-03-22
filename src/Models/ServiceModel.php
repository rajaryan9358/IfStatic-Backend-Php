<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;
use RuntimeException;
use Throwable;

final class ServiceModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'name',
        'alias',
        'short_description',
        'sort_order',
        'hero_label',
        'hero_title',
        'hero_description',
        'hero_cta_text',
        'hero_main_image',
        'approach_image',
        'process_label',
        'process_title',
        'tools_label',
        'tools_title',
        'mobile_apps_label',
        'mobile_apps_title',
        'portfolio_label',
        'portfolio_title',
        'approach_list',
        'tools_list',
        'mobile_apps',
        'faqs',
        'featured_portfolio_ids',
        'service_icon',
        'meta_title',
        'meta_description',
        'meta_schema',
        'head_tag_manager',
        'body_tag_manager',
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
            $statement = $this->db->query('SHOW COLUMNS FROM services');
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
    public function all(): array
    {
        $statement = $this->db->query(
            sprintf('SELECT %s FROM services ORDER BY sort_order ASC, created_at DESC', implode(', ', $this->selectableColumns()))
        );

        $rows = $statement->fetchAll();

        return array_map(fn (array $row) => $this->mapRow($row), $rows);
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $column = ctype_digit($identifier) ? 'id' : 'alias';
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM services WHERE %s = :identifier LIMIT 1', implode(', ', self::COLUMNS), $column)
        );
        $statement->execute(['identifier' => $identifier]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): array
    {
        $now = $this->timestamp();
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;
        $id = $this->insert('services', $payload);
        $service = $this->findByIdentifier((string) $id);
        if (!$service) {
            throw new RuntimeException('Failed to load service after creation');
        }

        return $service;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $id, array $payload): ?array
    {
        $payload['updated_at'] = $this->timestamp();
        $this->updateById('services', $id, $payload);
        $service = $this->findByIdentifier((string) $id);
        if (!$service) {
            throw new RuntimeException('Failed to load service after update');
        }

        return $service;
    }

    public function delete(int $id): void
    {
        $this->deleteById('services', $id);
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
            'alias' => $row['alias'],
            'shortDescription' => $row['short_description'],
            'heroLabel' => $row['hero_label'],
            'heroTitle' => $row['hero_title'],
            'heroDescription' => $row['hero_description'],
            'heroCtaText' => $row['hero_cta_text'],
            'heroMainImage' => $row['hero_main_image'],
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'approachImage' => $row['approach_image'],
            'processLabel' => $row['process_label'],
            'processTitle' => $row['process_title'],
            'toolsLabel' => $row['tools_label'],
            'toolsTitle' => $row['tools_title'],
            'mobileAppsLabel' => $row['mobile_apps_label'],
            'mobileAppsTitle' => $row['mobile_apps_title'],
            'portfolioLabel' => $row['portfolio_label'],
            'portfolioTitle' => $row['portfolio_title'],
            'approachList' => Json::decode($row['approach_list']),
            'toolsList' => Json::decode($row['tools_list']),
            'mobileApps' => Json::decode($row['mobile_apps']),
            'faqs' => Json::decode($row['faqs']),
            'featuredPortfolioIds' => Json::decode($row['featured_portfolio_ids']),
            'service_icon' => $row['service_icon'] ?? '',
            'metaTitle' => $row['meta_title'] ?? '',
            'metaDescription' => $row['meta_description'] ?? '',
            'metaSchema' => $row['meta_schema'] ?? '',
            'headTagManager' => $row['head_tag_manager'] ?? '',
            'bodyTagManager' => $row['body_tag_manager'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $service
     * @return array<string, mixed>
     */
    public function toDatabasePayload(array $service): array
    {
        return $this->filterPayload([
            'name' => $service['name'],
            'alias' => $service['alias'],
            'short_description' => $service['shortDescription'],
            'sort_order' => $service['sortOrder'],
            'hero_label' => $service['heroLabel'],
            'hero_title' => $service['heroTitle'],
            'hero_description' => $service['heroDescription'],
            'hero_cta_text' => $service['heroCtaText'],
            'hero_main_image' => $service['heroMainImage'],
            'approach_image' => $service['approachImage'],
            'process_label' => $service['processLabel'],
            'process_title' => $service['processTitle'],
            'tools_label' => $service['toolsLabel'],
            'tools_title' => $service['toolsTitle'],
            'mobile_apps_label' => $service['mobileAppsLabel'],
            'mobile_apps_title' => $service['mobileAppsTitle'],
            'portfolio_label' => $service['portfolioLabel'],
            'portfolio_title' => $service['portfolioTitle'],
            'approach_list' => Json::encode($service['approachList']),
            'tools_list' => Json::encode($service['toolsList']),
            'mobile_apps' => Json::encode($service['mobileApps']),
            'faqs' => Json::encode($service['faqs'] ?? []),
            'featured_portfolio_ids' => Json::encode($service['featuredPortfolioIds']),
            'service_icon' => $service['service_icon'] ?? '',
            'meta_title' => $service['metaTitle'] ?? '',
            'meta_description' => $service['metaDescription'] ?? '',
            'meta_schema' => $service['metaSchema'] ?? '',
            'head_tag_manager' => $service['headTagManager'] ?? '',
            'body_tag_manager' => $service['bodyTagManager'] ?? '',
        ]);
    }
}
