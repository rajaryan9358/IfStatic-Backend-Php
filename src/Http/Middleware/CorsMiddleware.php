<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Env;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class CorsMiddleware implements MiddlewareInterface
{
    private array $allowedOrigins;
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
        $origins = Env::get('ALLOWED_ORIGINS', '*');
        $this->allowedOrigins = array_filter(array_map('trim', explode(',', $origins ?? '*')));
        if (empty($this->allowedOrigins)) {
            $this->allowedOrigins = ['*'];
        }
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(204);
            return $this->applyHeaders($request, $response);
        }

        $response = $handler->handle($request);

        return $this->applyHeaders($request, $response);
    }

    private function applyHeaders(Request $request, Response $response): Response
    {
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigin = $this->determineOrigin($origin);

        if ($allowedOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', $request->getHeaderLine('Access-Control-Request-Headers') ?: 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }

    private function determineOrigin(?string $origin): ?string
    {
        if ($origin === null || $origin === '') {
            return $this->allowsWildcard() ? '*' : null;
        }

        if ($this->allowsWildcard() || in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return null;
    }

    private function allowsWildcard(): bool
    {
        return in_array('*', $this->allowedOrigins, true);
    }
}
