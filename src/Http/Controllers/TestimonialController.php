<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\TestimonialModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class TestimonialController extends Controller
{
    use ValidatesRequest;

    private TestimonialModel $testimonials;

    public function __construct(?TestimonialModel $testimonials = null)
    {
        $this->testimonials = $testimonials ?? new TestimonialModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        $pagePath = $request->getQueryParams()['pagePath'] ?? null;
        $data = $this->testimonials->list(is_string($pagePath) ? $pagePath : null);

        return $this->respond($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateTestimonialPayload($request->getParsedBody() ?? []);
        $testimonial = $this->testimonials->createTestimonial($payload);

        return $this->respond($response, ['data' => $testimonial], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->testimonials->find($id);
        if (!$existing) {
            throw new HttpException('Testimonial not found', 404);
        }

        $payload = $this->validateTestimonialPayload($request->getParsedBody() ?? []);
        $testimonial = $this->testimonials->updateTestimonial($id, $payload);

        return $this->respond($response, ['data' => $testimonial]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->testimonials->find($id);
        if (!$existing) {
            throw new HttpException('Testimonial not found', 404);
        }

        $this->testimonials->deleteTestimonial($id);

        return $response->withStatus(204);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateTestimonialPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $name = $this->requireString($raw, 'name', $errors);
        $testimonial = $this->requireString($raw, 'testimonial', $errors);

        $rating = 5;
        if (isset($raw['rating'])) {
            $ratingValue = (int) $raw['rating'];
            if ($ratingValue < 1 || $ratingValue > 5) {
                $errors['rating'][] = 'Rating must be between 1 and 5.';
            } else {
                $rating = $ratingValue;
            }
        }

        $skills = $this->ensureStringArray($raw['skills'] ?? []);
        $pageAliases = $this->normalizePageAliases($raw['pageAliases'] ?? [], $errors);
        $sortOrder = isset($raw['sortOrder']) ? (int) $raw['sortOrder'] : 0;

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'handle' => $this->optionalString($raw, 'handle', $errors, true),
            'role' => $this->optionalString($raw, 'role', $errors, true),
            'company' => $this->optionalString($raw, 'company', $errors, true),
            'location' => $this->optionalString($raw, 'location', $errors, true),
            'project' => $this->optionalString($raw, 'project', $errors, true),
            'budget' => $this->optionalString($raw, 'budget', $errors, true),
            'timeframe' => $this->optionalString($raw, 'timeframe', $errors, true),
            'testimonial' => $testimonial,
            'rating' => $rating,
            'image' => $this->optionalString($raw, 'image', $errors, true),
            'skills' => $skills,
            'pageAliases' => $pageAliases,
            'sortOrder' => $sortOrder,
        ];
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizePageAliases($value, array &$errors): array
    {
        if (!is_array($value)) {
            return [];
        }

        $aliases = [];
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                continue;
            }
            $trimmed = trim($item);
            if (str_starts_with($trimmed, '/')) {
                $aliases[] = $this->assertPath($trimmed, 'pageAliases', $errors);
            } else {
                $aliases[] = $this->assertSlug($trimmed, 'pageAliases', $errors);
            }
        }

        return array_values(array_unique($aliases));
    }
}
