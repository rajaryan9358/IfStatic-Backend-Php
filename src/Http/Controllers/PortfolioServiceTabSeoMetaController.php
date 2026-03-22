<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\PortfolioServiceTabSeoMetaModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PortfolioServiceTabSeoMetaController extends Controller
{
    use ValidatesRequest;

    private PortfolioServiceTabSeoMetaModel $seo;

    public function __construct(?PortfolioServiceTabSeoMetaModel $seo = null)
    {
        $this->seo = $seo ?? new PortfolioServiceTabSeoMetaModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $serviceAlias = $params['serviceAlias'] ?? $params['service_alias'] ?? null;
        $serviceAlias = is_string($serviceAlias) ? trim($serviceAlias) : null;

        $data = $this->seo->all($serviceAlias);
        return $this->respond($response, ['data' => $data]);
    }

    public function upsert(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $serviceAlias = $this->requireString($body, 'serviceAlias', $errors);
        $metaTitle = $this->optionalString($body, 'metaTitle', $errors, true);
        $metaDescription = $this->optionalString($body, 'metaDescription', $errors, true);
        $metaSchema = $this->optionalString($body, 'metaSchema', $errors, true);
        $headTagManager = $this->optionalString($body, 'headTagManager', $errors, true);
        $bodyTagManager = $this->optionalString($body, 'bodyTagManager', $errors, true);

        if ($serviceAlias !== null) {
            $serviceAlias = strtolower(trim($serviceAlias));
            if ($serviceAlias === '') {
                $errors['serviceAlias'][] = 'serviceAlias is required.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        if ($serviceAlias === null) {
            throw new HttpException('serviceAlias is required.', 400);
        }

        $saved = $this->seo->upsert($serviceAlias, [
            'metaTitle' => $metaTitle ?? '',
            'metaDescription' => $metaDescription ?? '',
            'metaSchema' => $metaSchema ?? '',
            'headTagManager' => $headTagManager ?? '',
            'bodyTagManager' => $bodyTagManager ?? '',
        ]);

        return $this->respond($response, ['data' => $saved]);
    }
}
