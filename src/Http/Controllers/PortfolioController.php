<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\PortfolioModel;
use App\Models\TestimonialModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PortfolioController extends Controller
{
    use ValidatesRequest;

    private PortfolioModel $portfolios;

    public function __construct(?PortfolioModel $portfolios = null)
    {
        $connection = Database::connection();
        $this->portfolios = $portfolios ?? new PortfolioModel($connection, new TestimonialModel($connection));
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

        $payload = $this->validatePortfolioPayload($request->getParsedBody() ?? []);
        $portfolio = $this->portfolios->updatePortfolio($id, $payload);

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

        $this->portfolios->deletePortfolio($id);

        return $response->withStatus(204);
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
        $gallery = $this->ensureStringArray($raw['gallery'] ?? []);
        $tags = $this->ensureStringArray($raw['tags'] ?? []);
        $showInHome = isset($raw['showInHome'])
            ? filter_var($raw['showInHome'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;
        $downloadTitle = $this->optionalString($raw, 'downloadTitle', $errors, true);
        $downloadDescription = $this->optionalString($raw, 'downloadDescription', $errors, true);

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
            'downloadTitle' => $downloadTitle,
            'downloadDescription' => $downloadDescription,
            'sortOrder' => isset($raw['sortOrder']) ? (int) $raw['sortOrder'] : 0,
        ];
    }
}
