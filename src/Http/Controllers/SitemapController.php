<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\ValidationException;
use App\Support\SitemapService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SitemapController extends Controller
{
    private SitemapService $sitemap;

    public function __construct(?SitemapService $sitemap = null)
    {
        $this->sitemap = $sitemap ?? new SitemapService();
    }

    public function show(Request $request, Response $response): Response
    {
        return $this->respond($response, ['data' => $this->sitemap->read()]);
    }

    public function xml(Request $request, Response $response): Response
    {
        $data = $this->sitemap->read();
        $response->getBody()->write((string) ($data['xml'] ?? ''));

        return $response
            ->withHeader('Content-Type', 'application/xml; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, max-age=0')
            ->withStatus(200);
    }

    public function update(Request $request, Response $response): Response
    {
        $raw = $request->getParsedBody() ?? [];
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $xml = isset($raw['xml']) ? trim((string) $raw['xml']) : '';
        if ($xml === '') {
            throw new ValidationException(['xml' => ['XML content is required.']]);
        }

        return $this->respond($response, ['data' => $this->sitemap->saveRawXml($xml)]);
    }

    public function generateDefault(Request $request, Response $response): Response
    {
        return $this->respond($response, ['data' => $this->sitemap->generateDefault()]);
    }

    public function appendContentUrls(Request $request, Response $response): Response
    {
        $raw = $request->getParsedBody() ?? [];
        $types = ['blogs', 'service-cities', 'portfolios'];

        if (is_array($raw) && isset($raw['types']) && is_array($raw['types'])) {
            $types = array_values(array_filter(
                array_map(static fn ($type): string => strtolower(trim((string) $type)), $raw['types']),
                static fn (string $type): bool => $type !== ''
            ));
        }

        return $this->respond($response, ['data' => $this->sitemap->appendDynamicContentUrls($types)]);
    }
}