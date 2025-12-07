<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Exceptions\HttpException;
use App\Support\AuthToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;

final class AdminAuthMiddleware
{
    public function __invoke(Request $request, RequestHandlerInterface $handler): Response
    {
        $token = $this->extractToken($request);
        if (!$token) {
            throw new HttpException('Authentication required.', 401);
        }

        try {
            AuthToken::verify($token);
        } catch (\Throwable $exception) {
            throw new HttpException('Invalid or expired token.', 401);
        }

        return $handler->handle($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
