<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\BlogTopicModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BlogTopicController extends Controller
{
    use ValidatesRequest;

    private BlogTopicModel $topics;

    public function __construct(?BlogTopicModel $topics = null)
    {
        $this->topics = $topics ?? new BlogTopicModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        return $this->respond($response, ['data' => $this->topics->all()]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $topic = $this->topics->find($id);
        if (!$topic) {
            throw new HttpException('Blog topic not found', 404);
        }

        return $this->respond($response, ['data' => $topic]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateTopicPayload($request->getParsedBody() ?? []);
        $topic = $this->topics->create($payload);

        return $this->respond($response, ['data' => $topic], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->topics->find($id);
        if (!$existing) {
            throw new HttpException('Blog topic not found', 404);
        }

        $payload = $this->validateTopicPayload($request->getParsedBody() ?? []);
        $topic = $this->topics->updateTopic($id, $payload);

        return $this->respond($response, ['data' => $topic]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->topics->find($id);
        if (!$existing) {
            throw new HttpException('Blog topic not found', 404);
        }

        $this->topics->deleteTopic($id);

        return $response->withStatus(204);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateTopicPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $name = $this->requireString($raw, 'name', $errors);
        $slug = $this->requireString($raw, 'slug', $errors);
        if ($slug !== null) {
            $slug = $this->assertSlug($slug, 'slug', $errors);
        }

        $description = $this->optionalString($raw, 'description', $errors, true) ?? '';
        $sortOrder = isset($raw['sortOrder']) && is_numeric($raw['sortOrder']) ? (int) $raw['sortOrder'] : 0;

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'sortOrder' => $sortOrder,
        ];
    }
}
