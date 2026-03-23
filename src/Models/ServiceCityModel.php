<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;
use RuntimeException;
use Throwable;

final class ServiceCityModel extends BaseModel
{
    private const COLUMNS = [
        'id',
        'service_id',
        'city_name',
        'title',
        'slug',
        'sort_order',
        'is_international',
        'show_hero',
        'show_process',
        'use_process_override',
        'show_tools',
        'show_mobile_apps',
        'use_mobile_apps_override',
        'show_faqs',
        'show_portfolios',
        'show_testimonials',
        'hero_label',
        'hero_title',
        'hero_description',
        'hero_cta_text',
        'hero_main_image',
        'approach_image',
        'process_label',
        'process_title',
        'mobile_apps_label',
        'mobile_apps_title',
        'approach_list',
        'mobile_apps',
        'faqs',
        'meta_title',
        'meta_description',
        'meta_schema',
        'head_tag_manager',
        'body_tag_manager',
        'created_at',
        'updated_at',
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
            $statement = $this->db->query('SHOW COLUMNS FROM service_cities');
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
    public function all(?int $serviceId = null): array
    {
        if ($serviceId && $serviceId > 0) {
            $statement = $this->db->prepare(
                sprintf(
                    'SELECT %s FROM service_cities WHERE service_id = :service_id ORDER BY sort_order ASC, created_at DESC',
                    implode(', ', $this->selectableColumns())
                )
            );
            $statement->execute(['service_id' => $serviceId]);
            $rows = $statement->fetchAll();
            return array_map(fn (array $row) => $this->mapRow($row), $rows);
        }

        $statement = $this->db->query(
            sprintf(
                'SELECT %s FROM service_cities ORDER BY service_id ASC, sort_order ASC, created_at DESC',
                implode(', ', $this->selectableColumns())
            )
        );
        $rows = $statement->fetchAll();

        return array_map(fn (array $row) => $this->mapRow($row), $rows);
    }

    public function findById(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM service_cities WHERE id = :id LIMIT 1', implode(', ', $this->selectableColumns()))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function findByServiceAndSlug(int $serviceId, string $slug): ?array
    {
        $statement = $this->db->prepare(
            sprintf(
                'SELECT %s FROM service_cities WHERE service_id = :service_id AND slug = :slug LIMIT 1',
                implode(', ', $this->selectableColumns())
            )
        );
        $statement->execute(['service_id' => $serviceId, 'slug' => $slug]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function findByServiceAliasAndSlug(string $serviceAlias, string $citySlug): ?array
    {
        $statement = $this->db->prepare(
            'SELECT c.*'
            . ' FROM service_cities c'
            . ' INNER JOIN services s ON s.id = c.service_id'
            . ' WHERE s.alias = :service_alias AND c.slug = :city_slug'
            . ' LIMIT 1'
        );
        $statement->execute(['service_alias' => $serviceAlias, 'city_slug' => $citySlug]);
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

        $id = $this->insert('service_cities', $payload);
        $city = $this->findById($id);
        if (!$city) {
            throw new RuntimeException('Failed to load service city after creation');
        }

        return $city;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $id, array $payload): ?array
    {
        $payload['updated_at'] = $this->timestamp();
        $this->updateById('service_cities', $id, $payload);

        $city = $this->findById($id);
        if (!$city) {
            throw new RuntimeException('Failed to load service city after update');
        }

        return $city;
    }

    public function delete(int $id): void
    {
        $this->deleteById('service_cities', $id);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'serviceId' => (int) $row['service_id'],
            'cityName' => (string) ($row['city_name'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'isInternational' => (bool) ((int) ($row['is_international'] ?? 0)),

            'showHero' => (bool) ((int) ($row['show_hero'] ?? 1)),
            'showProcess' => (bool) ((int) ($row['show_process'] ?? 1)),
            'useProcessOverride' => (bool) ((int) ($row['use_process_override'] ?? 0)),
            'showTools' => (bool) ((int) ($row['show_tools'] ?? 1)),
            'showMobileApps' => (bool) ((int) ($row['show_mobile_apps'] ?? 1)),
            'useMobileAppsOverride' => (bool) ((int) ($row['use_mobile_apps_override'] ?? 0)),
            'showFaqs' => (bool) ((int) ($row['show_faqs'] ?? 1)),
            'showPortfolios' => (bool) ((int) ($row['show_portfolios'] ?? 1)),
            'showTestimonials' => (bool) ((int) ($row['show_testimonials'] ?? 1)),

            'heroLabel' => (string) ($row['hero_label'] ?? ''),
            'heroTitle' => (string) ($row['hero_title'] ?? ''),
            'heroDescription' => (string) ($row['hero_description'] ?? ''),
            'heroCtaText' => (string) ($row['hero_cta_text'] ?? ''),
            'heroMainImage' => (string) ($row['hero_main_image'] ?? ''),

            'approachImage' => (string) ($row['approach_image'] ?? ''),
            'processLabel' => (string) ($row['process_label'] ?? ''),
            'processTitle' => (string) ($row['process_title'] ?? ''),
            'mobileAppsLabel' => (string) ($row['mobile_apps_label'] ?? ''),
            'mobileAppsTitle' => (string) ($row['mobile_apps_title'] ?? ''),
            'approachList' => Json::decode($row['approach_list'] ?? null),
            'mobileApps' => Json::decode($row['mobile_apps'] ?? null),

            'faqs' => Json::decode($row['faqs'] ?? null),

            'metaTitle' => (string) ($row['meta_title'] ?? ''),
            'metaDescription' => (string) ($row['meta_description'] ?? ''),
            'metaSchema' => (string) ($row['meta_schema'] ?? ''),
            'headTagManager' => (string) ($row['head_tag_manager'] ?? ''),
            'bodyTagManager' => (string) ($row['body_tag_manager'] ?? ''),

            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $city
     * @return array<string, mixed>
     */
    public function toDatabasePayload(array $city): array
    {
        return $this->filterPayload([
            'service_id' => (int) ($city['serviceId'] ?? 0),
            'city_name' => (string) ($city['cityName'] ?? ''),
            'title' => (string) ($city['title'] ?? ''),
            'slug' => (string) ($city['slug'] ?? ''),
            'sort_order' => (int) ($city['sortOrder'] ?? 0),
            'is_international' => !empty($city['isInternational']) ? 1 : 0,

            'show_hero' => !empty($city['showHero']) ? 1 : 0,
            'show_process' => !empty($city['showProcess']) ? 1 : 0,
            'use_process_override' => !empty($city['useProcessOverride']) ? 1 : 0,
            'show_tools' => !empty($city['showTools']) ? 1 : 0,
            'show_mobile_apps' => !empty($city['showMobileApps']) ? 1 : 0,
            'use_mobile_apps_override' => !empty($city['useMobileAppsOverride']) ? 1 : 0,
            'show_faqs' => !empty($city['showFaqs']) ? 1 : 0,
            'show_portfolios' => !empty($city['showPortfolios']) ? 1 : 0,
            'show_testimonials' => !empty($city['showTestimonials']) ? 1 : 0,

            'hero_label' => (string) ($city['heroLabel'] ?? ''),
            'hero_title' => (string) ($city['heroTitle'] ?? ''),
            'hero_description' => (string) ($city['heroDescription'] ?? ''),
            'hero_cta_text' => (string) ($city['heroCtaText'] ?? ''),
            'hero_main_image' => (string) ($city['heroMainImage'] ?? ''),

            'approach_image' => (string) ($city['approachImage'] ?? ''),
            'process_label' => (string) ($city['processLabel'] ?? ''),
            'process_title' => (string) ($city['processTitle'] ?? ''),
            'mobile_apps_label' => (string) ($city['mobileAppsLabel'] ?? ''),
            'mobile_apps_title' => (string) ($city['mobileAppsTitle'] ?? ''),
            'approach_list' => Json::encode($city['approachList'] ?? []),
            'mobile_apps' => Json::encode($city['mobileApps'] ?? []),

            'faqs' => Json::encode($city['faqs'] ?? []),

            'meta_title' => (string) ($city['metaTitle'] ?? ''),
            'meta_description' => (string) ($city['metaDescription'] ?? ''),
            'meta_schema' => (string) ($city['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($city['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($city['bodyTagManager'] ?? ''),
        ]);
    }
}
