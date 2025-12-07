<?php

declare(strict_types=1);

namespace App\Support;

final class Json
{
    /**
     * @param mixed $value
     */
    public static function encode($value): string
    {
        if ($value === null) {
            return '[]';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return '[]';
        }

        return $encoded;
    }

    /**
     * @param string|null $value
     * @return array<mixed>
     */
    public static function decode(?string $value, array $fallback = []): array
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return $fallback;
        }

        return $decoded;
    }
}
