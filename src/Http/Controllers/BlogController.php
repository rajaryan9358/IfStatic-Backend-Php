<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\BlogModel;
use App\Models\BlogTopicModel;
use App\Support\SitemapService;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class BlogController extends Controller
{
    use ValidatesRequest;

    private BlogModel $blogs;

    public function __construct(?BlogModel $blogs = null)
    {
        $connection = Database::connection();
        $this->blogs = $blogs ?? new BlogModel($connection, new BlogTopicModel($connection));
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        $limit = isset($params['limit']) ? (int) $params['limit'] : null;
        $topicSlug = isset($params['topicSlug']) ? strtolower(trim((string) $params['topicSlug'])) : null;
        if (!$topicSlug && isset($params['topic'])) {
            $topicSlug = strtolower(trim((string) $params['topic']));
        }

        $blogs = $this->blogs->all([
            'search' => $search ?: null,
            'topicSlug' => $topicSlug ?: null,
            'limit' => $limit && $limit > 0 ? $limit : null,
        ]);

        return $this->respond($response, ['data' => $blogs]);
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid blog identifier', 400);
        }
        $blog = $this->blogs->findById($id);
        if (!$blog) {
            throw new HttpException('Blog not found', 404);
        }

        return $this->respond($response, ['data' => $blog]);
    }

    public function showBySlug(Request $request, Response $response, array $args): Response
    {
        $slug = strtolower($args['slug']);
        $blog = $this->blogs->findBySlug($slug);
        if (!$blog) {
            throw new HttpException('Blog not found', 404);
        }

        return $this->respond($response, ['data' => $blog]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateBlogPayload($request->getParsedBody() ?? []);
        $blog = $this->blogs->create($payload);

        if ($blog && !empty($blog['slug'])) {
            (new SitemapService())->ensureBlogUrl((string) $blog['slug'], null);
        }

        return $this->respond($response, ['data' => $blog], 201);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->blogs->findById($id);
        if (!$existing) {
            throw new HttpException('Blog not found', 404);
        }

        $oldPath = !empty($existing['slug']) ? (new SitemapService())->buildBlogPath((string) $existing['slug']) : null;
        $payload = $this->validateBlogPayload($request->getParsedBody() ?? []);
        $blog = $this->blogs->updateBlog($id, $payload);

        if ($blog && !empty($blog['slug'])) {
            (new SitemapService())->replacePath(
                $oldPath,
                (new SitemapService())->buildBlogPath((string) $blog['slug']),
                null
            );
        }

        return $this->respond($response, ['data' => $blog]);
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid blog identifier', 400);
        }
        $existing = $this->blogs->findById($id);
        $this->blogs->deleteBlog($id);

        if ($existing && !empty($existing['slug'])) {
            (new SitemapService())->removePath((new SitemapService())->buildBlogPath((string) $existing['slug']));
        }

        return $response->withStatus(204);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateBlogPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $title = $this->requireString($raw, 'title', $errors);
        $slug = $this->requireString($raw, 'slug', $errors);
        if ($slug !== null) {
            $slug = $this->assertSlug($slug, 'slug', $errors);
        }
        $category = $this->requireString($raw, 'category', $errors);
        $excerpt = $this->requireString($raw, 'excerpt', $errors);
        $author = $this->requireString($raw, 'author', $errors);

        $publishDate = $raw['date'] ?? null;
        if ($publishDate) {
            $timestamp = strtotime((string) $publishDate);
            if ($timestamp === false) {
                $errors['date'][] = 'Publish date must be a valid ISO date string.';
            } else {
                $publishDate = date('Y-m-d H:i:s', $timestamp);
            }
        } else {
            $publishDate = date('Y-m-d H:i:s');
        }

        $tags = $this->ensureStringArray($raw['tags'] ?? []);
        $topicIds = $this->sanitizeNumericArray($raw['topicIds'] ?? []);

        $content = $this->optionalString($raw, 'content', $errors, true);
        $readTime = $this->optionalString($raw, 'readTime', $errors, true);
        $image = $this->optionalString($raw, 'image', $errors, true);

        $metaTitle = $this->optionalString($raw, 'metaTitle', $errors, true);
        $metaDescription = $this->optionalString($raw, 'metaDescription', $errors, true);
        $metaSchema = $this->optionalString($raw, 'metaSchema', $errors, true);

        
        $headTagManager = $this->optionalString($raw, 'headTagManager', $errors, true);
        $bodyTagManager = $this->optionalString($raw, 'bodyTagManager', $errors, true);
if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'category' => $category,
            'excerpt' => $excerpt,
            'content' => $content,
            'author' => $author,
            'date' => $publishDate,
            'readTime' => $readTime,
            'image' => $image,
            'metaTitle' => $metaTitle,
            'metaDescription' => $metaDescription,
            'metaSchema' => $metaSchema,
            'headTagManager' => $headTagManager,
            'bodyTagManager' => $bodyTagManager,
            'tags' => $tags,
            'topicIds' => $topicIds,
        ];
    }
}
