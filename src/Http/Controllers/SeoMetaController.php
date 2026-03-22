<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\SeoMetaModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SeoMetaController extends Controller
{
    use ValidatesRequest;

    private const ALLOWED_PAGES = [
        'home',
        'services',
        'portfolios',
        'blogs',
        'about',
        'contact',
        'terms',
        'privacy',
    ];

    private const ALLOWED_META_TYPES = [
        'title',
        'description',
        'schema',
        'head_tag_manager',
        'body_tag_manager',
    ];

    private SeoMetaModel $seo;

    public function __construct(?SeoMetaModel $seo = null)
    {
        $this->seo = $seo ?? new SeoMetaModel(Database::connection());
    }

    public function publicIndex(Request $request, Response $response): Response
    {
        $page = $this->validatedPageFromQuery($request, true);
        $data = $this->seo->all($page);

        return $this->respond($response, ['data' => $data]);
    }

    public function index(Request $request, Response $response): Response
    {
        $page = $this->validatedPageFromQuery($request, false);
        $data = $this->seo->all($page);

        return $this->respond($response, ['data' => $data]);
    }

    public function upsert(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $page = $this->requireString($body, 'page', $errors);
        $metaType = $this->requireString($body, 'metaType', $errors);
        $metaData = $this->optionalString($body, 'metaData', $errors, true);

        if ($page !== null) {
            $page = strtolower($page);
            if (!in_array($page, self::ALLOWED_PAGES, true)) {
                $errors['page'][] = 'Invalid page. Allowed: ' . implode(', ', self::ALLOWED_PAGES) . '.';
            }
        }

        if ($metaType !== null) {
            $metaType = strtolower($metaType);
            if (!in_array($metaType, self::ALLOWED_META_TYPES, true)) {
                $errors['metaType'][] = 'Invalid metaType. Allowed: ' . implode(', ', self::ALLOWED_META_TYPES) . '.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $saved = $this->seo->upsert($page, $metaType, $metaData ?? '');

        return $this->respond($response, ['data' => $saved]);
    }

    private function validatedPageFromQuery(Request $request, bool $required): ?string
    {
        $params = $request->getQueryParams();
        $raw = $params['page'] ?? null;

        if ($raw === null || trim((string) $raw) === '') {
            if ($required) {
                throw new HttpException('page query parameter is required.', 400);
            }
            return null;
        }

        $page = strtolower(trim((string) $raw));
        if (!in_array($page, self::ALLOWED_PAGES, true)) {
            throw new HttpException('Invalid page. Allowed: ' . implode(', ', self::ALLOWED_PAGES) . '.', 400);
        }

        return $page;
    }
}
