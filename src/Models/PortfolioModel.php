<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;
use Throwable;

final class PortfolioModel extends BaseModel
{
    private const COLUMNS = [
        'p.id',
        'p.service_id',
        'p.slug',
        'p.name',
        'p.description',
        'p.sort_order',
        'p.company',
        'p.image',
        'p.hero_category',
        'p.hero_title',
        'p.hero_subtitle',
        'p.hero_tagline',
        'p.summary',
        'p.website_url',
        'p.tags',
        'p.features',
        'p.tech_stack',
        'p.gallery',
        'p.cta_buttons',
        'p.show_in_home',
        'p.download_title',
        'p.download_description',
        'p.show_download_section',
        'p.cta_title',
        'p.meta_title',
        'p.meta_description',
        'p.meta_schema',
        'p.head_tag_manager',
        'p.body_tag_manager',
        'p.created_at',
        'p.updated_at',
        's.name AS service_name',
        's.alias AS service_alias'
    ];

    /** @var array<int, string> */
    private array $availableColumns = [];

    private TestimonialModel $testimonials;

    public function __construct(PDO $db, ?TestimonialModel $testimonials = null)
    {
        parent::__construct($db);
        $this->availableColumns = $this->detectColumns();
        $this->testimonials = $testimonials ?? new TestimonialModel($db);
    }

    /**
     * @return array<int, string>
     */
    private function detectColumns(): array
    {
        try {
            $statement = $this->db->query('SHOW COLUMNS FROM portfolios');
            $fields = $statement ? array_column($statement->fetchAll(), 'Field') : [];
            return $fields ?: array_map([$this, 'stripTablePrefix'], self::COLUMNS);
        } catch (Throwable $e) {
            return array_map([$this, 'stripTablePrefix'], self::COLUMNS);
        }
    }

    private function stripTablePrefix(string $column): string
    {
        if (stripos($column, ' AS ') !== false) {
            return trim((string) preg_replace('/^.*\s+AS\s+/i', '', $column));
        }

        return str_contains($column, '.') ? (string) substr($column, strpos($column, '.') + 1) : $column;
    }

    /**
     * @return array<int, string>
     */
    private function selectableColumns(): array
    {
        return array_values(array_filter(
            self::COLUMNS,
            function (string $column): bool {
                if (stripos($column, ' AS ') !== false) {
                    return true;
                }

                $rawColumn = $this->stripTablePrefix($column);
                return in_array($rawColumn, $this->availableColumns, true);
            }
        ));
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
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['serviceAlias'])) {
            $clauses[] = 's.alias = :alias';
            $params['alias'] = $filters['serviceAlias'];
        }
        if (!empty($filters['serviceId'])) {
            $clauses[] = 'p.service_id = :serviceId';
            $params['serviceId'] = (int) $filters['serviceId'];
        }
        if (array_key_exists('showInHome', $filters) && in_array('show_in_home', $this->availableColumns, true)) {
            $clauses[] = 'p.show_in_home = :showInHome';
            $params['showInHome'] = (int) ((bool) $filters['showInHome']);
        }

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        $sql = sprintf(
            'SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id %s ORDER BY p.sort_order ASC, p.created_at DESC',
            implode(', ', $this->selectableColumns()),
            $where
        );

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row) => $this->mapRow($row, false), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id WHERE p.id = :id LIMIT 1', implode(', ', $this->selectableColumns()))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id WHERE p.slug = :slug LIMIT 1', implode(', ', $this->selectableColumns()))
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    /**
     * @param array<string, mixed> $portfolio
     */
    public function createPortfolio(array $portfolio): ?array
    {
        $payload = $this->toPayload($portfolio);
        $now = $this->timestamp();
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;
        $id = $this->insert('portfolios', $payload);

        return $this->find($id);
    }

    /**
     * @param array<string, mixed> $portfolio
     */
    public function updatePortfolio(int $id, array $portfolio): ?array
    {
        $payload = $this->toPayload($portfolio);
        $payload['updated_at'] = $this->timestamp();
        $this->updateById('portfolios', $id, $payload);

        return $this->find($id);
    }

    public function deletePortfolio(int $id): void
    {
        $this->deleteById('portfolios', $id);
    }

    /**
     * @param array<string, mixed> $portfolio
     * @return array<string, mixed>
     */
    private function toPayload(array $portfolio): array
    {
        return $this->filterPayload([
            'service_id' => $portfolio['serviceId'],
            'slug' => strtolower($portfolio['slug']),
            'name' => $portfolio['name'],
            'description' => $portfolio['description'] ?? '',
            'company' => $portfolio['company'] ?? null,
            'image' => $portfolio['image'] ?? null,
            'hero_category' => $portfolio['heroCategory'] ?? null,
            'hero_title' => $portfolio['heroTitle'] ?? null,
            'hero_subtitle' => $portfolio['heroSubtitle'] ?? null,
            'hero_tagline' => $portfolio['heroTagline'] ?? null,
            'summary' => $portfolio['summary'] ?? null,
            'website_url' => $portfolio['websiteUrl'] ?? null,
            'tags' => Json::encode($portfolio['tags'] ?? []),
            'features' => Json::encode($portfolio['features'] ?? []),
            'tech_stack' => Json::encode($portfolio['techStack'] ?? []),
            'gallery' => Json::encode($portfolio['gallery'] ?? []),
            'cta_buttons' => Json::encode($portfolio['ctaButtons'] ?? []),
            'show_in_home' => isset($portfolio['showInHome']) ? (int) ((bool) $portfolio['showInHome']) : 0,
            'show_download_section' => array_key_exists('showDownloadSection', $portfolio)
                ? (int) ((bool) $portfolio['showDownloadSection'])
                : 1,
            'download_title' => $portfolio['downloadTitle'] ?? ($portfolio['heroTagline'] ?? ''),
            'download_description' => $portfolio['downloadDescription'] ?? ($portfolio['summary'] ?? ''),
            'cta_title' => $portfolio['ctaTitle'] ?? '',
            'meta_title' => $portfolio['metaTitle'] ?? '',
            'meta_description' => $portfolio['metaDescription'] ?? '',
            'meta_schema' => $portfolio['metaSchema'] ?? '',
            'head_tag_manager' => $portfolio['headTagManager'] ?? '',
            'body_tag_manager' => $portfolio['bodyTagManager'] ?? '',
            'sort_order' => $portfolio['sortOrder'] ?? 0,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row, bool $withTestimonials = true): array
    {
        $portfolio = [
            'id' => (int) ($row['id'] ?? 0),
            'serviceId' => (int) ($row['service_id'] ?? 0),
            'serviceName' => $row['service_name'] ?? null,
            'serviceAlias' => $row['service_alias'] ?? null,
            'slug' => $row['slug'] ?? '',
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'company' => $row['company'] ?? null,
            'image' => $row['image'] ?? null,
            'heroCategory' => $row['hero_category'] ?? null,
            'heroTitle' => $row['hero_title'] ?? null,
            'heroSubtitle' => $row['hero_subtitle'] ?? null,
            'heroTagline' => $row['hero_tagline'] ?? null,
            'summary' => $row['summary'] ?? null,
            'websiteUrl' => $row['website_url'] ?? null,
            'tags' => Json::decode($row['tags'] ?? null),
            'features' => Json::decode($row['features'] ?? null),
            'techStack' => Json::decode($row['tech_stack'] ?? null),
            'gallery' => Json::decode($row['gallery'] ?? null),
            'ctaButtons' => Json::decode($row['cta_buttons'] ?? null),
            'showInHome' => (bool) ($row['show_in_home'] ?? false),
            'downloadTitle' => $row['download_title'] ?? '',
            'downloadDescription' => $row['download_description'] ?? '',
            'showDownloadSection' => (bool) ($row['show_download_section'] ?? true),
            'ctaTitle' => $row['cta_title'] ?? null,
            'metaTitle' => $row['meta_title'] ?? '',
            'metaDescription' => $row['meta_description'] ?? '',
            'metaSchema' => $row['meta_schema'] ?? '',
            'headTagManager' => $row['head_tag_manager'] ?? '',
            'bodyTagManager' => $row['body_tag_manager'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'testimonials' => [],
        ];

        if ($withTestimonials) {
            $pagePath = $portfolio['slug'] ? '/portfolio/' . $portfolio['slug'] : null;
            $portfolio['testimonials'] = $pagePath ? $this->testimonials->list($pagePath) : [];
        }

        return $portfolio;
    }
}
