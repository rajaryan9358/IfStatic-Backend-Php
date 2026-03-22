<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Models\BlogModel;
use App\Models\BlogTopicModel;
use App\Models\PortfolioModel;
use App\Models\PortfolioServiceTabSeoMetaModel;
use App\Models\SeoMetaModel;
use App\Models\ServiceModel;
use App\Models\ServiceCityModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class MetaController extends Controller
{
    private const ALLOWED_PAGE_TYPES = [
        'home',
        'services',
        'service_city',
        'portfolios',
        'blogs',
        'about',
        'contact',
        'terms',
        'privacy',
        'projects',
        'blogdetail',
    ];

    private SeoMetaModel $seo;
    private ServiceModel $services;
    private ServiceCityModel $serviceCities;
    private PortfolioModel $portfolios;
    private PortfolioServiceTabSeoMetaModel $portfolioTabs;
    private BlogTopicModel $blogTopics;
    private BlogModel $blogs;

    public function __construct(
        ?SeoMetaModel $seo = null,
        ?ServiceModel $services = null,
        ?ServiceCityModel $serviceCities = null,
        ?PortfolioModel $portfolios = null,
        ?PortfolioServiceTabSeoMetaModel $portfolioTabs = null,
        ?BlogTopicModel $blogTopics = null,
        ?BlogModel $blogs = null
    ) {
        $db = Database::connection();
        $this->seo = $seo ?? new SeoMetaModel($db);
        $this->services = $services ?? new ServiceModel($db);
        $this->serviceCities = $serviceCities ?? new ServiceCityModel($db);
        $this->portfolios = $portfolios ?? new PortfolioModel($db);
        $this->portfolioTabs = $portfolioTabs ?? new PortfolioServiceTabSeoMetaModel($db);
        $this->blogTopics = $blogTopics ?? new BlogTopicModel($db);
        $this->blogs = $blogs ?? new BlogModel($db);
    }

    public function show(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $pageTypeRaw = $params['page_type'] ?? $params['pageType'] ?? null;
        $subTypeRaw = $params['sub_type'] ?? $params['subType'] ?? '';

        $pageType = strtolower(trim((string) ($pageTypeRaw ?? '')));
        if ($pageType === '') {
            throw new HttpException('page_type is required.', 400);
        }

        if (!in_array($pageType, self::ALLOWED_PAGE_TYPES, true)) {
            throw new HttpException('Invalid page_type. Allowed: ' . implode(', ', self::ALLOWED_PAGE_TYPES) . '.', 400);
        }

        $subType = strtolower(trim((string) $subTypeRaw));

        $meta = match ($pageType) {
            'home', 'about', 'contact', 'terms', 'privacy' => $this->fromSeoMetaPage($pageType),
            'services' => $subType === '' ? $this->fromSeoMetaPage('services') : $this->fromServiceAlias($subType),
            'portfolios' => $subType === '' ? $this->fromSeoMetaPage('portfolios') : $this->fromPortfolioServiceTab($subType) ?? $this->fromSeoMetaPage('portfolios'),
            'blogs' => $subType === '' ? $this->fromSeoMetaPage('blogs') : $this->fromBlogTopicSlug($subType),
            'projects' => $this->fromProjectAlias($subType),
            'blogdetail' => $this->fromBlogSlug($subType),
            default => $this->emptyMeta(),
        };

        return $this->respond($response, [
            'data' => array_merge(
                [
                    'page_type' => $pageType,
                    'sub_type' => $subType,
                ],
                $meta
            ),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function emptyMeta(): array
    {
        return [
            'meta_title' => '',
            'meta_description' => '',
            'meta_schema' => '',
            'head_tag_manager' => '',
            'body_tag_manager' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fromSeoMetaPage(string $page): array
    {
        $rows = $this->seo->all($page);
        $meta = $this->emptyMeta();

        foreach ($rows as $row) {
            $type = (string) ($row['metaType'] ?? '');
            $data = (string) ($row['metaData'] ?? '');

            if ($type === 'title') {
                $meta['meta_title'] = $data;
            } elseif ($type === 'description') {
                $meta['meta_description'] = $data;
            } elseif ($type === 'schema') {
                $meta['meta_schema'] = $data;
            } elseif ($type === 'head_tag_manager') {
                $meta['head_tag_manager'] = $data;
            } elseif ($type === 'body_tag_manager') {
                $meta['body_tag_manager'] = $data;
            }
        }

        return $meta;
    }



    /**
     * sub_type format: {serviceAlias}/{citySlug}
     *
     * @return array<string, string>
     */
    private function fromServiceCitySubType(string $subType): array
    {
        $raw = trim((string) $subType);
        if ($raw === '' || !str_contains($raw, '/')) {
            throw new HttpException('sub_type must be in the format "{serviceAlias}/{citySlug}" for service_city.', 400);
        }

        [$serviceAlias, $citySlug] = array_pad(explode('/', $raw, 2), 2, '');
        $serviceAlias = strtolower(trim((string) $serviceAlias));
        $citySlug = strtolower(trim((string) $citySlug));

        if ($serviceAlias === '' || $citySlug === '') {
            throw new HttpException('sub_type must include both service alias and city slug.', 400);
        }

        $service = $this->services->findByIdentifier($serviceAlias);
        if (!$service) {
            throw new HttpException('Service not found for sub_type.', 404);
        }

        $serviceId = (int) ($service['id'] ?? 0);
        $city = $this->serviceCities->findByServiceAndSlug($serviceId, $citySlug);
        if (!$city) {
            throw new HttpException('Service city not found for sub_type.', 404);
        }

        $pick = static function ($primary, $fallback): string {
            $p = is_string($primary) ? trim($primary) : '';
            if ($p !== '') return $primary;
            return (string) ($fallback ?? '');
        };

        return [
            'meta_title' => $pick($city['metaTitle'] ?? '', $service['metaTitle'] ?? ''),
            'meta_description' => $pick($city['metaDescription'] ?? '', $service['metaDescription'] ?? ''),
            'meta_schema' => $pick($city['metaSchema'] ?? '', $service['metaSchema'] ?? ''),
            'head_tag_manager' => $pick($city['headTagManager'] ?? '', $service['headTagManager'] ?? ''),
            'body_tag_manager' => $pick($city['bodyTagManager'] ?? '', $service['bodyTagManager'] ?? ''),
        ];
    }
    /**
     * @return array<string, string>
     */
    private function fromServiceAlias(string $alias): array
    {
        $service = $this->services->findByIdentifier($alias);
        if (!$service) {
            throw new HttpException('Service not found for sub_type.', 404);
        }

        return [
            'meta_title' => (string) ($service['metaTitle'] ?? ''),
            'meta_description' => (string) ($service['metaDescription'] ?? ''),
            'meta_schema' => (string) ($service['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($service['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($service['bodyTagManager'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function fromPortfolioServiceTab(string $serviceAlias): ?array
    {
        $row = $this->portfolioTabs->findByServiceAlias($serviceAlias);
        if (!$row) {
            return null;
        }

        return [
            'meta_title' => (string) ($row['metaTitle'] ?? ''),
            'meta_description' => (string) ($row['metaDescription'] ?? ''),
            'meta_schema' => (string) ($row['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($row['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($row['bodyTagManager'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fromBlogTopicSlug(string $slug): array
    {
        $topic = $this->blogTopics->findBySlug($slug);
        if (!$topic) {
            throw new HttpException('Blog topic not found for sub_type.', 404);
        }

        return [
            'meta_title' => (string) ($topic['metaTitle'] ?? ''),
            'meta_description' => (string) ($topic['metaDescription'] ?? ''),
            'meta_schema' => (string) ($topic['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($topic['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($topic['bodyTagManager'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fromProjectAlias(string $alias): array
    {
        if ($alias === '') {
            throw new HttpException('sub_type is required for projects.', 400);
        }

        $portfolio = $this->portfolios->findBySlug($alias);
        if (!$portfolio) {
            throw new HttpException('Project not found for sub_type.', 404);
        }

        return [
            'meta_title' => (string) ($portfolio['metaTitle'] ?? ''),
            'meta_description' => (string) ($portfolio['metaDescription'] ?? ''),
            'meta_schema' => (string) ($portfolio['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($portfolio['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($portfolio['bodyTagManager'] ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function fromBlogSlug(string $slug): array
    {
        if ($slug === '') {
            throw new HttpException('sub_type is required for blogdetail.', 400);
        }

        $blog = $this->blogs->findBySlug($slug);
        if (!$blog) {
            throw new HttpException('Blog not found for sub_type.', 404);
        }

        return [
            'meta_title' => (string) ($blog['metaTitle'] ?? ''),
            'meta_description' => (string) ($blog['metaDescription'] ?? ''),
            'meta_schema' => (string) ($blog['metaSchema'] ?? ''),
            'head_tag_manager' => (string) ($blog['headTagManager'] ?? ''),
            'body_tag_manager' => (string) ($blog['bodyTagManager'] ?? ''),
        ];
    }
}
