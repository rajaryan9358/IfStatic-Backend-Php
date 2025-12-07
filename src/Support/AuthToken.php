<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use JsonException;
use RuntimeException;

final class AuthToken
{
    private const DEFAULT_TTL_SECONDS = 7200; // 2 hours

    /**
     * @param array<string, mixed> $claims
     */
    public static function issue(array $claims = [], ?int $ttlSeconds = null): string
    {
        $issuedAt = (new DateTimeImmutable('now'))->getTimestamp();
        $payload = array_merge($claims, [
            'iat' => $issuedAt,
            'exp' => $issuedAt + ($ttlSeconds ?? self::DEFAULT_TTL_SECONDS),
            'sub' => $claims['sub'] ?? 'admin',
        ]);

        $body = self::base64UrlEncode(self::jsonEncode($payload));
        $signature = self::base64UrlEncode(hash_hmac('sha256', $body, self::secret(), true));

        return $body . '.' . $signature;
    }

    /**
     * @return array<string, mixed>
     */
    public static function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid token format.');
        }

        [$body, $signature] = $parts;
        $expectedSignature = self::base64UrlEncode(hash_hmac('sha256', $body, self::secret(), true));
        if (!hash_equals($expectedSignature, $signature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $payload = self::jsonDecode(self::base64UrlDecode($body));
        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= time()) {
            throw new RuntimeException('Token expired.');
        }

        return $payload;
    }

    private static function secret(): string
    {
        $secret = Env::get('ADMIN_AUTH_SECRET');
        if (!$secret) {
            $secret = Env::get('APP_KEY', 'ifstatic-admin-secret');
        }

        return $secret ?? 'ifstatic-admin-secret';
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Unable to decode token payload.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private static function jsonDecode(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Invalid token payload.', 0, $exception);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Token payload must be an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function jsonEncode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode token payload.', 0, $exception);
        }
    }
}
