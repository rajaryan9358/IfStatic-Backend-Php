<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Json;
use PDO;

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
        'p.created_at',
        'p.updated_at',
        's.name AS service_name',
        's.alias AS service_alias'
    ];

    private TestimonialModel $testimonials;

    public function __construct(PDO $db, ?TestimonialModel $testimonials = null)
    {
        parent::__construct($db);
        $this->testimonials = $testimonials ?? new TestimonialModel($db);
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

        $where = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        $sql = sprintf(
            'SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id %s ORDER BY p.sort_order ASC, p.created_at DESC',
            implode(', ', self::COLUMNS),
            $where
        );

        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row) => $this->mapRow($row, false), $statement->fetchAll());
    }

    public function find(int $id): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id WHERE p.id = :id LIMIT 1', implode(', ', self::COLUMNS))
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->mapRow($row) : null;
    }

    public function findBySlug(string $slug): ?array
    {
        $statement = $this->db->prepare(
            sprintf('SELECT %s FROM portfolios p LEFT JOIN services s ON s.id = p.service_id WHERE p.slug = :slug LIMIT 1', implode(', ', self::COLUMNS))
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
        return [
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
            'download_title' => $portfolio['downloadTitle'] ?? ($portfolio['heroTagline'] ?? null),
            'download_description' => $portfolio['downloadDescription'] ?? ($portfolio['summary'] ?? null),
            'sort_order' => $portfolio['sortOrder'] ?? 0,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRow(array $row, bool $withTestimonials = true): array
    {
        $portfolio = [
            'id' => (int) $row['id'],
            'serviceId' => (int) $row['service_id'],
            'serviceName' => $row['service_name'],
            'serviceAlias' => $row['service_alias'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'description' => $row['description'],
            'sortOrder' => (int) ($row['sort_order'] ?? 0),
            'company' => $row['company'],
            'image' => $row['image'],
            'heroCategory' => $row['hero_category'],
            'heroTitle' => $row['hero_title'],
            'heroSubtitle' => $row['hero_subtitle'],
            'heroTagline' => $row['hero_tagline'],
            'summary' => $row['summary'],
            'websiteUrl' => $row['website_url'],
            'tags' => Json::decode($row['tags']),
            'features' => Json::decode($row['features']),
            'techStack' => Json::decode($row['tech_stack']),
            'gallery' => Json::decode($row['gallery']),
            'ctaButtons' => Json::decode($row['cta_buttons']),
            'showInHome' => (bool) ($row['show_in_home'] ?? false),
            'downloadTitle' => $row['download_title'],
            'downloadDescription' => $row['download_description'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'testimonials' => [],
        ];

        if ($withTestimonials) {
            $pagePath = $portfolio['slug'] ? '/portfolio/' . $portfolio['slug'] : null;
            $portfolio['testimonials'] = $pagePath ? $this->testimonials->list($pagePath) : [];
        }

        return $portfolio;
    }
}
