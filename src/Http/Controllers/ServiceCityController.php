<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\ServiceCityModel;
use App\Models\ServiceModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ServiceCityController extends Controller
{
    use ValidatesRequest;

    private ServiceCityModel $cities;
    private ServiceModel $services;

    public function __construct(?ServiceCityModel $cities = null, ?ServiceModel $services = null)
    {
        $db = Database::connection();
        $this->cities = $cities ?? new ServiceCityModel($db);
        $this->services = $services ?? new ServiceModel($db);
    }

    // Admin
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $serviceId = isset($params['serviceId']) && is_numeric($params['serviceId']) ? (int) $params['serviceId'] : null;

        $data = $this->cities->all($serviceId);

        return $this->respond($response, ['data' => $data]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException('Invalid city identifier', 400);
        }

        $city = $this->cities->findById($id);
        if (!$city) {
            throw new HttpException('Service city not found', 404);
        }

        return $this->respond($response, ['data' => $city]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validatePayload($request->getParsedBody() ?? []);

        $serviceId = (int) ($payload['serviceId'] ?? 0);
        $service = $this->services->findByIdentifier((string) $serviceId);
        if (!$service) {
            throw new HttpException('Service not found', 404);
        }

        $created = $this->cities->create($this->cities->toDatabasePayload($payload));

        return $this->respond($response, ['data' => $created], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException('Invalid city identifier', 400);
        }

        $existing = $this->cities->findById($id);
        if (!$existing) {
            throw new HttpException('Service city not found', 404);
        }

        $payload = $this->validatePayload($request->getParsedBody() ?? []);

        $serviceId = (int) ($payload['serviceId'] ?? 0);
        if ($serviceId <= 0 || $serviceId !== (int) ($existing['serviceId'] ?? 0)) {
            throw new HttpException('serviceId cannot be changed', 400);
        }

        $updated = $this->cities->update($id, $this->cities->toDatabasePayload($payload));

        return $this->respond($response, ['data' => $updated]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            throw new HttpException('Invalid city identifier', 400);
        }

        $existing = $this->cities->findById($id);
        if (!$existing) {
            throw new HttpException('Service city not found', 404);
        }

        $this->cities->delete($id);

        return $response->withStatus(204);
    }

    // Public
    public function publicIndex(Request $request, Response $response, array $args): Response
    {
        $identifier = (string) ($args['identifier'] ?? '');
        $service = $this->services->findByIdentifier($identifier);
        if (!$service) {
            throw new HttpException('Service not found', 404);
        }

        $cities = $this->cities->all((int) ($service['id'] ?? 0));

        // Minimal payload for listing
        $list = array_map(static function (array $city): array {
            return [
                'id' => (int) ($city['id'] ?? 0),
                'serviceId' => (int) ($city['serviceId'] ?? 0),
                'cityName' => (string) ($city['cityName'] ?? ''),
                'title' => (string) ($city['title'] ?? ''),
                'slug' => (string) ($city['slug'] ?? ''),
                'sortOrder' => (int) ($city['sortOrder'] ?? 0),
                'isInternational' => !empty($city['isInternational']),
            ];
        }, $cities);

        usort(
            $list,
            static fn (array $a, array $b): int => ($a['sortOrder'] <=> $b['sortOrder'])
                ?: strcasecmp((string) ($a['cityName'] ?? ''), (string) ($b['cityName'] ?? ''))
        );

        return $this->respond($response, ['data' => $list]);
    }

    public function publicShow(Request $request, Response $response, array $args): Response
    {
        $identifier = (string) ($args['identifier'] ?? '');
        $citySlug = strtolower(trim((string) ($args['citySlug'] ?? '')));

        $service = $this->services->findByIdentifier($identifier);
        if (!$service) {
            throw new HttpException('Service not found', 404);
        }

        $serviceId = (int) ($service['id'] ?? 0);
        $city = $this->cities->findByServiceAndSlug($serviceId, $citySlug);
        if (!$city) {
            throw new HttpException('City not found', 404);
        }

        return $this->respond($response, [
            'data' => [
                'service' => $service,
                'city' => $city,
            ],
        ]);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validatePayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $payload = [];

        $serviceId = $this->requireInt($raw, 'serviceId', $errors);
        if ($serviceId !== null) {
            $payload['serviceId'] = $serviceId;
        }

        $cityName = $this->requireString($raw, 'cityName', $errors);
        if ($cityName !== null) {
            $payload['cityName'] = $cityName;
        }

        $payload['title'] = $this->optionalString($raw, 'title', $errors, true) ?? '';

        $slugValue = $this->requireString($raw, 'slug', $errors);
        if ($slugValue !== null) {
            $payload['slug'] = $this->assertSlug($slugValue, 'slug', $errors);
        }

        $payload['sortOrder'] = isset($raw['sortOrder']) && is_numeric($raw['sortOrder'])
            ? (int) $raw['sortOrder']
            : 0;

        $payload['isInternational'] = !empty($raw['isInternational']);

        $payload['showHero'] = array_key_exists('showHero', $raw) ? !empty($raw['showHero']) : true;
        $payload['showProcess'] = array_key_exists('showProcess', $raw) ? !empty($raw['showProcess']) : true;
        $payload['useProcessOverride'] = array_key_exists('useProcessOverride', $raw) ? !empty($raw['useProcessOverride']) : false;
        $payload['showTools'] = array_key_exists('showTools', $raw) ? !empty($raw['showTools']) : true;
        $payload['showMobileApps'] = array_key_exists('showMobileApps', $raw) ? !empty($raw['showMobileApps']) : true;
        $payload['useMobileAppsOverride'] = array_key_exists('useMobileAppsOverride', $raw) ? !empty($raw['useMobileAppsOverride']) : false;
        $payload['showFaqs'] = array_key_exists('showFaqs', $raw) ? !empty($raw['showFaqs']) : true;

        $payload['showPortfolios'] = array_key_exists('showPortfolios', $raw) ? !empty($raw['showPortfolios']) : true;
        $payload['showTestimonials'] = array_key_exists('showTestimonials', $raw) ? !empty($raw['showTestimonials']) : true;

        // Hero overrides
        $payload['heroLabel'] = $this->optionalString($raw, 'heroLabel', $errors, true) ?? '';
        $payload['heroTitle'] = $this->optionalString($raw, 'heroTitle', $errors, true) ?? '';
        $payload['heroDescription'] = $this->optionalString($raw, 'heroDescription', $errors, true) ?? '';
        $payload['heroCtaText'] = $this->optionalString($raw, 'heroCtaText', $errors, true) ?? '';
        $payload['heroMainImage'] = $this->optionalString($raw, 'heroMainImage', $errors, true) ?? '';

        // Process overrides
        $payload['approachImage'] = $this->optionalString($raw, 'approachImage', $errors, true) ?? '';
        $payload['processLabel'] = $this->optionalString($raw, 'processLabel', $errors, true) ?? '';
        $payload['processTitle'] = $this->optionalString($raw, 'processTitle', $errors, true) ?? '';
        $payload['approachList'] = $this->ensureArrayOfObjects($raw['approachList'] ?? [], ['title']);

        $payload['mobileAppsLabel'] = $this->optionalString($raw, 'mobileAppsLabel', $errors, true) ?? '';
        $payload['mobileAppsTitle'] = $this->optionalString($raw, 'mobileAppsTitle', $errors, true) ?? '';
        $payload['mobileApps'] = $this->ensureArrayOfObjects($raw['mobileApps'] ?? [], ['title']);

        if (!$payload['useProcessOverride']) {
            $payload['approachImage'] = '';
            $payload['processLabel'] = '';
            $payload['processTitle'] = '';
            $payload['approachList'] = [];
        }

        if (!$payload['useMobileAppsOverride']) {
            $payload['mobileAppsLabel'] = '';
            $payload['mobileAppsTitle'] = '';
            $payload['mobileApps'] = [];
        }

        // FAQs overrides
        $payload['faqs'] = $this->sanitizeFaqs($raw['faqs'] ?? []);

        // SEO
        $payload['metaTitle'] = $this->optionalString($raw, 'metaTitle', $errors, true) ?? '';
        $payload['metaDescription'] = $this->optionalString($raw, 'metaDescription', $errors, true) ?? '';
        $payload['metaSchema'] = $this->optionalString($raw, 'metaSchema', $errors, true) ?? '';
        $payload['headTagManager'] = $this->optionalString($raw, 'headTagManager', $errors, true) ?? '';
        $payload['bodyTagManager'] = $this->optionalString($raw, 'bodyTagManager', $errors, true) ?? '';

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $payload;
    }
}
