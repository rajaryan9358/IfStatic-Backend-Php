<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Database;
use App\Http\Exceptions\ValidationException;
use App\Models\BlogModel;
use App\Models\BlogTopicModel;
use App\Models\PortfolioModel;
use App\Models\ServiceCityModel;
use App\Models\ServiceModel;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Throwable;

final class SitemapService
{
    private const XML_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    private const XML_SCHEMA_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';
    private const XML_SCHEMA_LOCATION = 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd';
    private const GENERATOR_COMMENT = ' created with IFStatic sitemap manager ';

    /** @var array<int, string> */
    private const STATIC_PATHS = [
        '/',
        '/about',
        '/contact',
        '/services',
        '/blogs',
        '/portfolio',
        '/privacy-policy',
        '/terms-and-conditions',
        '/lander',
    ];

    public function getSitemapPath(): string
    {
        $configured = Env::get('SITEMAP_FILE_PATH');
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return dirname(__DIR__, 3) . '/frontend/public/sitemap.xml';
    }

    public function getSiteBaseUrl(): string
    {
        $raw = Env::get('SITE_BASE_URL')
            ?? Env::get('FRONTEND_SITE_URL')
            ?? Env::get('NEXT_PUBLIC_SITE_URL')
            ?? Env::get('APP_URL')
            ?? 'https://ifstatic.com';

        return rtrim((string) $raw, '/');
    }

    public function getPublicSitemapUrl(): string
    {
        return $this->getSiteBaseUrl() . '/sitemap.xml';
    }

    /**
     * @return array<string, mixed>
     */
    public function read(): array
    {
        $xml = $this->readXml();
        $entries = $this->parseEntries($xml);

        return [
            'xml' => $xml,
            'filePath' => $this->getSitemapPath(),
            'publicUrl' => $this->getPublicSitemapUrl(),
            'baseUrl' => $this->getSiteBaseUrl(),
            'urlCount' => count($entries),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function saveRawXml(string $xml): array
    {
        $validatedXml = $this->normalizeXml($xml);
        $this->writeXml($validatedXml);

        return $this->read();
    }

    /**
     * @return array<string, mixed>
     */
    public function generateDefault(): array
    {
        $entries = $this->buildDefaultEntries();
        $xml = $this->renderEntries($entries);
        $this->writeXml($xml);

        return [
            ...$this->read(),
            'generatedCount' => count($entries),
            'appendedCount' => count($entries),
        ];
    }

    /**
     * @param array<int, string> $types
     * @return array<string, mixed>
     */
    public function appendDynamicContentUrls(array $types = ['blogs', 'service-cities', 'portfolios']): array
    {
        $currentEntries = $this->parseEntries($this->readXml());
        $currentMap = $this->indexEntries($currentEntries);
        $candidateEntries = $this->buildEntriesForTypes($types);

        $appended = 0;
        foreach ($candidateEntries as $entry) {
            $key = $this->normalizeComparableLoc((string) ($entry['loc'] ?? ''));
            if ($key === '' || isset($currentMap[$key])) {
                continue;
            }

            $currentEntries[] = $entry;
            $currentMap[$key] = true;
            $appended++;
        }

        if ($appended > 0) {
            $this->writeXml($this->renderEntries($currentEntries));
        }

        return [
            ...$this->read(),
            'appendedCount' => $appended,
        ];
    }

    public function ensureBlogUrl(string $slug, ?string $lastmod = null): void
    {
        $this->ensureEntries([[
            'loc' => $this->pathToLoc($this->buildBlogPath($slug)),
            'lastmod' => $this->normalizeLastmod($lastmod),
            'priority' => $this->defaultPriorityForPath($this->buildBlogPath($slug)),
        ]]);
    }

    public function ensurePortfolioUrl(string $slug, ?string $lastmod = null): void
    {
        $this->ensureEntries([[
            'loc' => $this->pathToLoc($this->buildPortfolioPath($slug)),
            'lastmod' => $this->normalizeLastmod($lastmod),
            'priority' => $this->defaultPriorityForPath($this->buildPortfolioPath($slug)),
        ]]);
    }

    public function ensureServiceCityUrl(string $serviceAlias, string $citySlug, ?string $lastmod = null): void
    {
        $path = $this->buildServiceCityPath($serviceAlias, $citySlug);
        $this->ensureEntries([[
            'loc' => $this->pathToLoc($path),
            'lastmod' => $this->normalizeLastmod($lastmod),
            'priority' => $this->defaultPriorityForPath($path),
        ]]);
    }

    public function ensureServiceUrls(string $serviceAlias, ?string $lastmod = null): void
    {
        $this->ensurePaths($this->buildServicePaths($serviceAlias), $lastmod);
    }

    public function replacePath(?string $oldPath, ?string $newPath, ?string $lastmod = null): void
    {
        $entries = $this->parseEntries($this->readXml());

        if ($oldPath) {
            $oldLoc = $this->pathToLoc($oldPath);
            $oldKey = $this->normalizeComparableLoc($oldLoc);
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => $this->normalizeComparableLoc((string) ($entry['loc'] ?? '')) !== $oldKey
            ));
        }

        if ($newPath) {
            $entries = $this->appendEntries($entries, [[
                'loc' => $this->pathToLoc($newPath),
                'lastmod' => $this->normalizeLastmod($lastmod),
                'priority' => $this->defaultPriorityForPath($newPath),
            ]]);
        }

        $this->writeXml($this->renderEntries($entries));
    }

    /**
     * @param array<int, string> $oldPaths
     * @param array<int, string> $newPaths
     */
    public function replacePaths(array $oldPaths, array $newPaths, ?string $lastmod = null): void
    {
        $entries = $this->parseEntries($this->readXml());
        $removeKeys = [];

        foreach ($oldPaths as $oldPath) {
            $normalized = $this->normalizePath($oldPath);
            if ($normalized === '') {
                continue;
            }
            $removeKeys[$this->normalizeComparableLoc($this->pathToLoc($normalized))] = true;
        }

        if ($removeKeys) {
            $entries = array_values(array_filter(
                $entries,
                fn (array $entry): bool => !isset($removeKeys[$this->normalizeComparableLoc((string) ($entry['loc'] ?? ''))])
            ));
        }

        $append = [];
        foreach ($newPaths as $newPath) {
            $normalized = $this->normalizePath($newPath);
            if ($normalized === '') {
                continue;
            }
            $append[] = [
                'loc' => $this->pathToLoc($normalized),
                'lastmod' => $this->normalizeLastmod($lastmod),
            ];
        }

        $entries = $this->appendEntries($entries, $append);
        $this->writeXml($this->renderEntries($entries));
    }

    public function removePath(?string $path): void
    {
        $normalized = $this->normalizePath((string) $path);
        if ($normalized === '') {
            return;
        }

        $locKey = $this->normalizeComparableLoc($this->pathToLoc($normalized));
        $entries = array_values(array_filter(
            $this->parseEntries($this->readXml()),
            fn (array $entry): bool => $this->normalizeComparableLoc((string) ($entry['loc'] ?? '')) !== $locKey
        ));

        $this->writeXml($this->renderEntries($entries));
    }

    /**
     * @param array<int, string> $paths
     */
    public function ensurePaths(array $paths, ?string $lastmod = null): void
    {
        $append = [];
        foreach ($paths as $path) {
            $normalized = $this->normalizePath($path);
            if ($normalized === '') {
                continue;
            }
            $append[] = [
                'loc' => $this->pathToLoc($normalized),
                'lastmod' => $this->normalizeLastmod($lastmod),
                'priority' => $this->defaultPriorityForPath($normalized),
            ];
        }

        if ($append === []) {
            return;
        }

        $entries = $this->appendEntries($this->parseEntries($this->readXml()), $append);
        $this->writeXml($this->renderEntries($entries));
    }

    /**
     * @param array<int, array{loc: string, lastmod?: string, priority?: string}> $entries
     */
    public function ensureEntries(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $existing = $this->parseEntries($this->readXml());
        $existing = $this->appendEntries($existing, $entries);
        $this->writeXml($this->renderEntries($existing));
    }

    public function buildBlogPath(string $slug): string
    {
        return '/blog/' . $this->sanitizeSlug($slug);
    }

    public function buildPortfolioPath(string $slug): string
    {
        return '/project/' . $this->sanitizeSlug($slug);
    }

    public function buildServiceCityPath(string $serviceAlias, string $citySlug): string
    {
        return sprintf('/services/%s/%s', $this->sanitizeSlug($serviceAlias), $this->sanitizeSlug($citySlug));
    }

    /**
     * @return array<int, string>
     */
    public function buildServicePaths(string $serviceAlias): array
    {
        $alias = $this->sanitizeSlug($serviceAlias);
        if ($alias === '') {
            return [];
        }

        return [
            '/services/' . $alias,
            '/services/' . $alias . '/cities',
            '/portfolio/' . $alias,
        ];
    }

    private function readXml(): string
    {
        $path = $this->getSitemapPath();
        if (!is_file($path)) {
            $this->writeXml($this->renderEntries($this->buildDefaultEntries()));
        }

        $xml = @file_get_contents($path);
        if (!is_string($xml) || trim($xml) === '') {
            $xml = $this->renderEntries($this->buildDefaultEntries());
            $this->writeXml($xml);
        }

        return $this->normalizeXml($xml);
    }

    private function writeXml(string $xml): void
    {
        $path = $this->getSitemapPath();
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($path, $xml);
    }

    private function normalizeXml(string $xml): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML(trim($xml));
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement) {
            $messages = array_map(
                static fn ($error): string => trim((string) $error->message),
                $errors
            );
            throw new ValidationException([
                'xml' => $messages ?: ['Sitemap XML is invalid.'],
            ]);
        }

        if (strtolower($document->documentElement->localName) !== 'urlset') {
            throw new ValidationException([
                'xml' => ['Sitemap XML must use a <urlset> root node.'],
            ]);
        }

        if (!$document->documentElement->hasAttribute('xmlns')) {
            $document->documentElement->setAttribute('xmlns', self::XML_NAMESPACE);
        }

        return trim((string) $document->saveXML()) . PHP_EOL;
    }

    /**
    * @return array<int, array{loc: string, lastmod?: string, priority?: string}>
     */
    private function parseEntries(string $xml): array
    {
        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sm', self::XML_NAMESPACE);

        $entries = [];
        /** @var DOMElement $url */
        foreach ($xpath->query('/sm:urlset/sm:url') ?: [] as $url) {
            $locNode = $xpath->query('sm:loc', $url)?->item(0);
            if (!$locNode) {
                continue;
            }

            $entry = ['loc' => trim((string) $locNode->textContent)];
            $lastmodNode = $xpath->query('sm:lastmod', $url)?->item(0);
            if ($lastmodNode && trim((string) $lastmodNode->textContent) !== '') {
                $entry['lastmod'] = trim((string) $lastmodNode->textContent);
            }

            $priorityNode = $xpath->query('sm:priority', $url)?->item(0);
            if ($priorityNode && trim((string) $priorityNode->textContent) !== '') {
                $entry['priority'] = $this->normalizePriority((string) $priorityNode->textContent);
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
    * @param array<int, array{loc: string, lastmod?: string, priority?: string}> $entries
     */
    private function renderEntries(array $entries): string
    {
        $sorted = array_values(array_filter($entries, fn (array $entry): bool => trim((string) ($entry['loc'] ?? '')) !== ''));
        usort(
            $sorted,
            fn (array $a, array $b): int => strcmp(
                $this->normalizeComparableLoc((string) ($a['loc'] ?? '')),
                $this->normalizeComparableLoc((string) ($b['loc'] ?? ''))
            )
        );

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        $urlset = $document->createElementNS(self::XML_NAMESPACE, 'urlset');
        $urlset->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XML_SCHEMA_NAMESPACE);
        $urlset->setAttributeNS(self::XML_SCHEMA_NAMESPACE, 'xsi:schemaLocation', self::XML_SCHEMA_LOCATION);
        $document->appendChild($urlset);
        $urlset->appendChild($document->createComment(self::GENERATOR_COMMENT));

        foreach ($sorted as $entry) {
            $url = $document->createElement('url');
            $url->appendChild($document->createElement('loc', (string) $entry['loc']));

            $lastmod = $this->normalizeLastmod($entry['lastmod'] ?? null);
            if ($lastmod !== null) {
                $url->appendChild($document->createElement('lastmod', $lastmod));
            }

            $priority = $this->normalizePriority((string) ($entry['priority'] ?? $this->defaultPriorityForLoc((string) ($entry['loc'] ?? ''))));
            if ($priority !== null) {
                $url->appendChild($document->createElement('priority', $priority));
            }

            $urlset->appendChild($url);
        }

        return trim((string) $document->saveXML()) . PHP_EOL;
    }

    /**
    * @param array<int, array{loc: string, lastmod?: string, priority?: string}> $entries
     * @return array<string, bool>
     */
    private function indexEntries(array $entries): array
    {
        $map = [];
        foreach ($entries as $entry) {
            $key = $this->normalizeComparableLoc((string) ($entry['loc'] ?? ''));
            if ($key !== '') {
                $map[$key] = true;
            }
        }

        return $map;
    }

    /**
    * @param array<int, array{loc: string, lastmod?: string, priority?: string}> $entries
    * @param array<int, array{loc: string, lastmod?: string, priority?: string}> $append
    * @return array<int, array{loc: string, lastmod?: string, priority?: string}>
     */
    private function appendEntries(array $entries, array $append): array
    {
        $map = $this->indexEntries($entries);

        foreach ($append as $entry) {
            $key = $this->normalizeComparableLoc((string) ($entry['loc'] ?? ''));
            if ($key === '' || isset($map[$key])) {
                continue;
            }

            $entries[] = $entry;
            $map[$key] = true;
        }

        return $entries;
    }

    /**
    * @return array<int, array{loc: string, lastmod?: string, priority?: string}>
     */
    private function buildDefaultEntries(): array
    {
        $entries = [];

        foreach (self::STATIC_PATHS as $path) {
            $entries[] = [
                'loc' => $this->pathToLoc($path),
                'lastmod' => $this->normalizeLastmod(null),
                'priority' => $this->defaultPriorityForPath($path),
            ];
        }

        return $this->appendEntries($entries, $this->buildEntriesForTypes([
            'services',
            'blog-topics',
            'blogs',
            'service-cities',
            'portfolios',
        ]));
    }

    /**
     * @param array<int, string> $types
    * @return array<int, array{loc: string, lastmod?: string, priority?: string}>
     */
    private function buildEntriesForTypes(array $types): array
    {
        $entries = [];
        $types = array_values(array_unique(array_map(static fn (string $type): string => strtolower(trim($type)), $types)));

        foreach ($types as $type) {
            if ($type === 'services') {
                foreach ($this->safeServices() as $service) {
                    $paths = $this->buildServicePaths((string) ($service['alias'] ?? ''));
                    $lastmod = (string) ($service['updatedAt'] ?? $service['createdAt'] ?? '');
                    foreach ($paths as $path) {
                        $entries[] = [
                            'loc' => $this->pathToLoc($path),
                            'lastmod' => $this->normalizeLastmod($lastmod),
                            'priority' => $this->defaultPriorityForPath($path),
                        ];
                    }
                }
                continue;
            }

            if ($type === 'blog-topics') {
                foreach ($this->safeBlogTopics() as $topic) {
                    $slug = $this->sanitizeSlug((string) ($topic['slug'] ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $entries[] = [
                        'loc' => $this->pathToLoc('/blogs/' . $slug),
                        'lastmod' => $this->normalizeLastmod((string) ($topic['updatedAt'] ?? $topic['createdAt'] ?? '')),
                        'priority' => $this->defaultPriorityForPath('/blogs/' . $slug),
                    ];
                }
                continue;
            }

            if ($type === 'blogs') {
                foreach ($this->safeBlogs() as $blog) {
                    $slug = $this->sanitizeSlug((string) ($blog['slug'] ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $entries[] = [
                        'loc' => $this->pathToLoc($this->buildBlogPath($slug)),
                        'lastmod' => $this->normalizeLastmod((string) ($blog['updatedAt'] ?? $blog['date'] ?? $blog['createdAt'] ?? '')),
                        'priority' => $this->defaultPriorityForPath($this->buildBlogPath($slug)),
                    ];
                }
                continue;
            }

            if ($type === 'service-cities') {
                $serviceMap = $this->serviceAliasMap();
                foreach ($this->safeServiceCities() as $city) {
                    $serviceId = (int) ($city['serviceId'] ?? 0);
                    $serviceAlias = $serviceMap[$serviceId] ?? '';
                    $citySlug = $this->sanitizeSlug((string) ($city['slug'] ?? ''));
                    if ($serviceAlias === '' || $citySlug === '') {
                        continue;
                    }
                    $entries[] = [
                        'loc' => $this->pathToLoc($this->buildServiceCityPath($serviceAlias, $citySlug)),
                        'lastmod' => $this->normalizeLastmod((string) ($city['updatedAt'] ?? $city['createdAt'] ?? '')),
                        'priority' => $this->defaultPriorityForPath($this->buildServiceCityPath($serviceAlias, $citySlug)),
                    ];
                }
                continue;
            }

            if ($type === 'portfolios') {
                foreach ($this->safePortfolios() as $portfolio) {
                    $slug = $this->sanitizeSlug((string) ($portfolio['slug'] ?? ''));
                    if ($slug === '') {
                        continue;
                    }
                    $entries[] = [
                        'loc' => $this->pathToLoc($this->buildPortfolioPath($slug)),
                        'lastmod' => $this->normalizeLastmod((string) ($portfolio['updatedAt'] ?? $portfolio['createdAt'] ?? '')),
                        'priority' => $this->defaultPriorityForPath($this->buildPortfolioPath($slug)),
                    ];
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safeServices(): array
    {
        try {
            return (new ServiceModel(Database::connection()))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safeBlogs(): array
    {
        try {
            $db = Database::connection();
            return (new BlogModel($db, new BlogTopicModel($db)))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safeBlogTopics(): array
    {
        try {
            return (new BlogTopicModel(Database::connection()))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safePortfolios(): array
    {
        try {
            return (new PortfolioModel(Database::connection()))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function safeServiceCities(): array
    {
        try {
            return (new ServiceCityModel(Database::connection()))->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function serviceAliasMap(): array
    {
        $map = [];
        foreach ($this->safeServices() as $service) {
            $id = (int) ($service['id'] ?? 0);
            $alias = $this->sanitizeSlug((string) ($service['alias'] ?? ''));
            if ($id > 0 && $alias !== '') {
                $map[$id] = $alias;
            }
        }

        return $map;
    }

    private function pathToLoc(string $path): string
    {
        $normalized = $this->normalizePath($path);
        if ($normalized === '/') {
            return $this->getSiteBaseUrl() . '/';
        }

        return $this->getSiteBaseUrl() . $normalized;
    }

    private function normalizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        $onlyPath = parse_url($trimmed, PHP_URL_PATH);
        $normalized = is_string($onlyPath) && $onlyPath !== '' ? $onlyPath : $trimmed;
        $normalized = '/' . ltrim($normalized, '/');
        $normalized = preg_replace('#/+#', '/', $normalized) ?: '/';

        if ($normalized !== '/') {
            $normalized = rtrim($normalized, '/');
        }

        return $normalized === '' ? '/' : $normalized;
    }

    private function sanitizeSlug(string $value): string
    {
        return trim(strtolower($value), " \t\n\r\0\x0B/");
    }

    private function normalizeComparableLoc(string $loc): string
    {
        $trimmed = trim($loc);
        if ($trimmed === '') {
            return '';
        }

        $parts = parse_url($trimmed);
        if (is_array($parts)) {
            $host = strtolower((string) ($parts['host'] ?? ''));
            $path = $this->normalizePath((string) ($parts['path'] ?? '/'));
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));

            if ($host !== '') {
                return $scheme . '://' . $host . $path;
            }

            return $path;
        }

        return $this->normalizePath($trimmed);
    }

    private function normalizeLastmod(?string $value): ?string
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return gmdate('c');
        }

        $timestamp = strtotime($trimmed);
        if ($timestamp === false) {
            return gmdate('c');
        }

        return gmdate('c', $timestamp);
    }

    private function defaultPriorityForLoc(string $loc): string
    {
        $path = parse_url($loc, PHP_URL_PATH);
        return $this->defaultPriorityForPath(is_string($path) ? $path : '/');
    }

    private function defaultPriorityForPath(string $path): string
    {
        return $this->normalizePath($path) === '/' ? '1.00' : '0.80';
    }

    private function normalizePriority(?string $value): ?string
    {
        $trimmed = is_string($value) ? trim($value) : '';
        if ($trimmed === '') {
            return null;
        }

        $numeric = (float) $trimmed;
        if ($numeric < 0) {
            $numeric = 0;
        }
        if ($numeric > 1) {
            $numeric = 1;
        }

        return number_format($numeric, 2, '.', '');
    }
}