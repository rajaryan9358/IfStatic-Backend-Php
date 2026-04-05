<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\PortfolioHomeOrderModel;
use App\Models\PortfolioModel;
use App\Models\TestimonialModel;
use App\Support\SitemapService;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PortfolioController extends Controller
{
    use ValidatesRequest;

    private PortfolioModel $portfolios;
    private PortfolioHomeOrderModel $homeOrder;

    public function __construct(?PortfolioModel $portfolios = null)
    {
        $connection = Database::connection();
        $this->portfolios = $portfolios ?? new PortfolioModel($connection, new TestimonialModel($connection));
        $this->homeOrder = new PortfolioHomeOrderModel($connection);
    }

    /**
     * @param mixed $value
     * @return array<int, array{image: string, showOnMobile: bool, showOnTablet: bool, showOnDesktop: bool}>
     */
    private function sanitizeGalleryItems($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $image = trim($item);
                if ($image === '') {
                    continue;
                }

                $items[] = [
                    'image' => $image,
                    'showOnMobile' => true,
                    'showOnTablet' => true,
                    'showOnDesktop' => true,
                ];
                continue;
            }

            if (!is_array($item)) {
                continue;
            }

            $image = isset($item['image']) ? trim((string) $item['image']) : '';
            if ($image === '' && isset($item['url'])) {
                $image = trim((string) $item['url']);
            }

            if ($image === '') {
                continue;
            }

            $items[] = [
                'image' => $image,
                'showOnMobile' => array_key_exists('showOnMobile', $item) ? (bool) $item['showOnMobile'] : true,
                'showOnTablet' => array_key_exists('showOnTablet', $item) ? (bool) $item['showOnTablet'] : true,
                'showOnDesktop' => array_key_exists('showOnDesktop', $item) ? (bool) $item['showOnDesktop'] : true,
            ];
        }

        return $items;
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $serviceAliasRaw = $query['serviceAlias'] ?? null;
        $serviceAlias = is_string($serviceAliasRaw) && trim($serviceAliasRaw) !== ''
            ? strtolower(trim($serviceAliasRaw))
            : null;

        $serviceId = null;
        if (array_key_exists('serviceId', $query)) {
            $rawId = $query['serviceId'];
            if (is_numeric($rawId) && (int) $rawId > 0) {
                $serviceId = (int) $rawId;
            } else {
                throw new HttpException('Invalid service identifier', 400);
            }
        }

        $filters = array_filter(
            [
                'serviceId' => $serviceId,
                'serviceAlias' => $serviceAlias,
            ],
            static fn($value) => $value !== null && $value !== ''
        );

        $data = $this->portfolios->all($filters);

        return $this->respond($response, ['data' => $data]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid portfolio identifier', 400);
        }
        $portfolio = $this->portfolios->find($id);
        if (!$portfolio) {
            throw new HttpException('Portfolio not found', 404);
        }

        return $this->respond($response, ['data' => $portfolio]);
    }

    public function showBySlug(Request $request, Response $response, array $args): Response
    {
        $slug = strtolower($args['slug']);
        $portfolio = $this->portfolios->findBySlug($slug);
        if (!$portfolio) {
            throw new HttpException('Portfolio not found', 404);
        }

        return $this->respond($response, ['data' => $portfolio]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validatePortfolioPayload($request->getParsedBody() ?? []);
        $portfolio = $this->portfolios->createPortfolio($payload);

        if ($portfolio && !empty($portfolio['slug'])) {
            (new SitemapService())->ensurePortfolioUrl((string) $portfolio['slug'], null);
        }

        return $this->respond($response, ['data' => $portfolio], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid portfolio identifier', 400);
        }
        $existing = $this->portfolios->find($id);
        if (!$existing) {
            throw new HttpException('Portfolio not found', 404);
        }

        $sitemap = new SitemapService();
        $oldPath = !empty($existing['slug']) ? $sitemap->buildPortfolioPath((string) $existing['slug']) : null;
        $payload = $this->validatePortfolioPayload($request->getParsedBody() ?? []);
        $portfolio = $this->portfolios->updatePortfolio($id, $payload);

        if ($portfolio && !empty($portfolio['slug'])) {
            $sitemap->replacePath(
                $oldPath,
                $sitemap->buildPortfolioPath((string) $portfolio['slug']),
                null
            );
        }

        return $this->respond($response, ['data' => $portfolio]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid portfolio identifier', 400);
        }
        $existing = $this->portfolios->find($id);
        if (!$existing) {
            throw new HttpException('Portfolio not found', 404);
        }

        $sitemap = new SitemapService();
        $oldPath = !empty($existing['slug']) ? $sitemap->buildPortfolioPath((string) $existing['slug']) : null;
        $this->portfolios->deletePortfolio($id);

        $sitemap->removePath($oldPath);

        return $response->withStatus(204);
    }

    public function home(Request $request, Response $response): Response
    {
        $data = $this->portfolios->all(['showInHome' => true]);

        $orderedIds = $this->homeOrder->listPortfolioIds();
        if ($orderedIds) {
            $positions = array_flip($orderedIds);
            usort(
                $data,
                static function (array $a, array $b) use ($positions): int {
                    $aId = (int) ($a['id'] ?? 0);
                    $bId = (int) ($b['id'] ?? 0);
                    $aPos = $positions[$aId] ?? PHP_INT_MAX;
                    $bPos = $positions[$bId] ?? PHP_INT_MAX;

                    if ($aPos !== $bPos) {
                        return $aPos <=> $bPos;
                    }

                    $aSort = (int) ($a['sortOrder'] ?? 0);
                    $bSort = (int) ($b['sortOrder'] ?? 0);
                    if ($aSort !== $bSort) {
                        return $aSort <=> $bSort;
                    }

                    return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
                }
            );
        }

        return $this->respond($response, ['data' => $data]);
    }

    public function homeOrder(Request $request, Response $response): Response
    {
        return $this->respond($response, ['data' => $this->homeOrder->listPortfolioIds()]);
    }

    public function updateHomeOrder(Request $request, Response $response): Response
    {
        $raw = $request->getParsedBody() ?? [];
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $portfolioIds = $this->sanitizeNumericArray($raw['portfolioIds'] ?? []);
        $enabledIds = array_map(
            static fn (array $portfolio): int => (int) ($portfolio['id'] ?? 0),
            $this->portfolios->all(['showInHome' => true])
        );
        $allowedIds = array_values(array_filter($enabledIds, static fn (int $id): bool => $id > 0));
        $allowedMap = array_fill_keys($allowedIds, true);

        $normalized = array_values(array_filter(
            $portfolioIds,
            static fn (int $id): bool => isset($allowedMap[$id])
        ));

        $missing = array_values(array_filter(
            $allowedIds,
            static fn (int $id): bool => !in_array($id, $normalized, true)
        ));

        $finalOrder = array_merge($normalized, $missing);

        $this->homeOrder->replaceOrder($finalOrder);

        return $this->respond($response, ['data' => $this->homeOrder->listPortfolioIds()]);
    }

    public function showMetaBySlug(Request $request, Response $response, array $args): Response
    {
        $slug = strtolower($args['slug']);
        $portfolio = $this->portfolios->findBySlug($slug);
        if (!$portfolio) {
            throw new HttpException('Portfolio not found', 404);
        }

        $meta = [
            'metaTitle' => $portfolio['metaTitle'] ?? '',
            'metaDescription' => $portfolio['metaDescription'] ?? '',
            'metaSchema' => $portfolio['metaSchema'] ?? '',
            'headTagManager' => $portfolio['headTagManager'] ?? '',
            'bodyTagManager' => $portfolio['bodyTagManager'] ?? '',
            // helpful fallbacks
            'fallbackTitle' => $portfolio['heroTitle'] ?? $portfolio['name'] ?? '',
        ];

        return $this->respond($response, ['data' => $meta]);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validatePortfolioPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $serviceId = $this->requireInt($raw, 'serviceId', $errors);
        if ($serviceId !== null && $serviceId <= 0) {
            $errors['serviceId'][] = 'Service ID must be a positive integer.';
        }

        $slug = $this->requireString($raw, 'slug', $errors);
        if ($slug !== null) {
            $slug = $this->assertSlug($slug, 'slug', $errors);
        }

        $name = $this->requireString($raw, 'name', $errors);
        $description = $this->requireString($raw, 'description', $errors, true);

        $features = $this->ensureArrayOfObjects($raw['features'] ?? [], ['title', 'description']);
        $techStack = $this->ensureArrayOfObjects($raw['techStack'] ?? [], ['name']);
        $ctaButtons = $this->ensureArrayOfObjects($raw['ctaButtons'] ?? [], ['url']);
        $gallery = $this->sanitizeGalleryItems($raw['gallery'] ?? []);
        $tags = $this->ensureStringArray($raw['tags'] ?? []);
        $showInHome = isset($raw['showInHome'])
            ? filter_var($raw['showInHome'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $downloadTitle = $this->optionalString($raw, 'downloadTitle', $errors, true);
        $downloadDescription = $this->optionalString($raw, 'downloadDescription', $errors, true);
        $ctaTitle = $this->optionalString($raw, 'ctaTitle', $errors, true);
        $metaTitle = $this->optionalString($raw, 'metaTitle', $errors, true);
        $metaDescription = $this->optionalString($raw, 'metaDescription', $errors, true);
        $metaSchema = $this->optionalString($raw, 'metaSchema', $errors, true);

        
        $headTagManager = $this->optionalString($raw, 'headTagManager', $errors, true);
        $bodyTagManager = $this->optionalString($raw, 'bodyTagManager', $errors, true);
$showDownloadSection = null;
        if (array_key_exists('showDownloadSection', $raw)) {
            $showDownloadSection = filter_var($raw['showDownloadSection'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($showDownloadSection === null) {
                $errors['showDownloadSection'][] = 'showDownloadSection must be a boolean.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return [
            'serviceId' => $serviceId,
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'company' => $this->optionalString($raw, 'company', $errors, true),
            'image' => $this->optionalString($raw, 'image', $errors, true),
            'heroCategory' => $this->optionalString($raw, 'heroCategory', $errors, true),
            'heroTitle' => $this->optionalString($raw, 'heroTitle', $errors, true),
            'heroSubtitle' => $this->optionalString($raw, 'heroSubtitle', $errors, true),
            'heroTagline' => $this->optionalString($raw, 'heroTagline', $errors, true),
            'summary' => $this->optionalString($raw, 'summary', $errors, true),
            'websiteUrl' => $this->assertUrl($this->optionalString($raw, 'websiteUrl', $errors, true), 'websiteUrl', $errors),
            'tags' => $tags,
            'features' => $features,
            'techStack' => $techStack,
            'gallery' => $gallery,
            'ctaButtons' => $ctaButtons,
            'showInHome' => $showInHome ?? false,
            'showDownloadSection' => $showDownloadSection,
            'downloadTitle' => $downloadTitle,
            'downloadDescription' => $downloadDescription,
            'ctaTitle' => $ctaTitle,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaSchema' => $metaSchema,
            'headTagManager' => $headTagManager,
            'bodyTagManager' => $bodyTagManager,
            'sortOrder' => isset($raw['sortOrder']) ? (int) $raw['sortOrder'] : 0,
        ];
    }
}
