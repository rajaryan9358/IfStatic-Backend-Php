<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\ServiceModel;
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

        $payload = $this->validateServicePayload($request->getParsedBody() ?? []);
        $updated = $this->services->update($id, $this->services->toDatabasePayload($payload));

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

        $this->services->delete($id);

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
