<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\ServiceModel;
use App\Support\SitemapService;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ServiceController extends Controller
{
    use ValidatesRequest;

    private ServiceModel $services;

    public function __construct(?ServiceModel $services = null)
    {
        $this->services = $services ?? new ServiceModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        $data = $this->services->all();

        return $this->respond($response, ['data' => $data]);
    }

    public function indexMinimal(Request $request, Response $response): Response
    {
        $services = $this->services->all();
        $minimal = array_map(
            static fn(array $service): array => [
                'id' => isset($service['id']) ? (int) $service['id'] : null,
                'name' => $service['name'] ?? '',
                'alias' => $service['alias'] ?? '',
                'sortOrder' => isset($service['sortOrder']) ? (int) $service['sortOrder'] : 0,
            ],
            $services
        );

        usort(
            $minimal,
            static fn(array $a, array $b): int => ($a['sortOrder'] <=> $b['sortOrder'])
                ?: strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''))
        );

        return $this->respond($response, ['data' => $minimal]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $service = $this->services->findByIdentifier($args['identifier']);
        if (!$service) {
            throw new HttpException('Service not found', 404);
        }

        return $this->respond($response, ['data' => $service]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateServicePayload($request->getParsedBody() ?? []);
        $service = $this->services->create($this->services->toDatabasePayload($payload));

        if ($service && !empty($service['alias'])) {
            (new SitemapService())->ensureServiceUrls((string) $service['alias'], (string) ($service['updatedAt'] ?? ''));
        }

        return $this->respond($response, ['data' => $service], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid service identifier', 400);
        }

        $existing = $this->services->findByIdentifier((string) $id);
        if (!$existing) {
            throw new HttpException('Service not found', 404);
        }

        $sitemap = new SitemapService();
        $oldPaths = !empty($existing['alias']) ? $sitemap->buildServicePaths((string) $existing['alias']) : [];
        $payload = $this->validateServicePayload($request->getParsedBody() ?? []);
        $updated = $this->services->update($id, $this->services->toDatabasePayload($payload));

        if ($updated && !empty($updated['alias'])) {
            $sitemap->replacePaths($oldPaths, $sitemap->buildServicePaths((string) $updated['alias']), (string) ($updated['updatedAt'] ?? ''));
        }

        return $this->respond($response, ['data' => $updated]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid service identifier', 400);
        }

        $existing = $this->services->findByIdentifier((string) $id);
        if (!$existing) {
            throw new HttpException('Service not found', 404);
        }

        $sitemap = new SitemapService();
        $oldPaths = !empty($existing['alias']) ? $sitemap->buildServicePaths((string) $existing['alias']) : [];
        $this->services->delete($id);

        foreach ($oldPaths as $oldPath) {
            $sitemap->removePath($oldPath);
        }

        return $response->withStatus(204);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateServicePayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $payload = [];

        $name = $this->requireString($raw, 'name', $errors);
        if ($name !== null) {
            $payload['name'] = $name;
        }

        $aliasValue = $this->requireString($raw, 'alias', $errors);
        if ($aliasValue !== null) {
            $payload['alias'] = $this->assertSlug($aliasValue, 'alias', $errors);
        }

        $payload['shortDescription'] = $this->optionalString($raw, 'shortDescription', $errors, true);
        if ($payload['shortDescription'] === null) {
            $payload['shortDescription'] = '';
        }

        $heroTitle = $this->requireString($raw, 'heroTitle', $errors);
        if ($heroTitle !== null) {
            $payload['heroTitle'] = $heroTitle;
        }

        $payload['heroLabel'] = $this->optionalString($raw, 'heroLabel', $errors, true);
        $payload['heroDescription'] = $this->optionalString($raw, 'heroDescription', $errors, true);
        $payload['heroCtaText'] = $this->optionalString($raw, 'heroCtaText', $errors, true);
        $payload['heroMainImage'] = $this->optionalString($raw, 'heroMainImage', $errors, true);
        $payload['approachImage'] = $this->optionalString($raw, 'approachImage', $errors, true);
        $payload['processLabel'] = $this->optionalString($raw, 'processLabel', $errors, true);
        $payload['processTitle'] = $this->optionalString($raw, 'processTitle', $errors, true);
        $payload['toolsLabel'] = $this->optionalString($raw, 'toolsLabel', $errors, true);
        $payload['toolsTitle'] = $this->optionalString($raw, 'toolsTitle', $errors, true);
        $payload['mobileAppsLabel'] = $this->optionalString($raw, 'mobileAppsLabel', $errors, true);
        $payload['mobileAppsTitle'] = $this->optionalString($raw, 'mobileAppsTitle', $errors, true);
        $payload['portfolioLabel'] = $this->optionalString($raw, 'portfolioLabel', $errors, true);
        $payload['portfolioTitle'] = $this->optionalString($raw, 'portfolioTitle', $errors, true);
        $payload['service_icon'] = $this->assertUrl(
            $this->optionalString($raw, 'service_icon', $errors, true),
            'service_icon',
            $errors
        );
        $payload['metaTitle'] = $this->optionalString($raw, 'metaTitle', $errors, true);
        $payload['metaDescription'] = $this->optionalString($raw, 'metaDescription', $errors, true);
        $payload['metaSchema'] = $this->optionalString($raw, 'metaSchema', $errors, true);

        

        $payload['headTagManager'] = $this->optionalString($raw, 'headTagManager', $errors, true);
        $payload['bodyTagManager'] = $this->optionalString($raw, 'bodyTagManager', $errors, true);
$payload['approachList'] = $this->ensureArrayOfObjects($raw['approachList'] ?? [], ['title']);
        $payload['toolsList'] = $this->ensureArrayOfObjects($raw['toolsList'] ?? [], ['name']);
        $payload['mobileApps'] = $this->ensureArrayOfObjects($raw['mobileApps'] ?? [], ['title']);
        $payload['featuredPortfolioIds'] = $this->sanitizeNumericArray($raw['featuredPortfolioIds'] ?? []);
        $payload['faqs'] = $this->sanitizeFaqs($raw['faqs'] ?? []);

        $payload['sortOrder'] = isset($raw['sortOrder']) && is_numeric($raw['sortOrder'])
            ? (int) $raw['sortOrder']
            : 0;

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $payload;
    }

}
