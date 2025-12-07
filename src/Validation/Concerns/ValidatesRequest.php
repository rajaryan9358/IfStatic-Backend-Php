<?php

declare(strict_types=1);

namespace App\Validation\Concerns;

trait ValidatesRequest
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, array<int, string>> $errors
     */
    private function requireString(array $input, string $field, array &$errors, bool $allowEmpty = false): ?string
    {
        if (!array_key_exists($field, $input) || !is_string($input[$field])) {
            $errors[$field][] = 'The field is required and must be a string.';
            return null;
        }

        $value = trim($input[$field]);
        if (!$allowEmpty && $value === '') {
            $errors[$field][] = 'The field cannot be empty.';
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array<int, string>> $errors
     */
    private function optionalString(array $input, string $field, array &$errors, bool $allowEmpty = true): ?string
    {
        if (!array_key_exists($field, $input)) {
            return null;
        }

        if (!is_string($input[$field])) {
            $errors[$field][] = 'The field must be a string.';
            return null;
        }

        $value = trim($input[$field]);
        if (!$allowEmpty && $value === '') {
            $errors[$field][] = 'The field cannot be empty.';
            return null;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array<int, string>> $errors
     */
    private function requireInt(array $input, string $field, array &$errors): ?int
    {
        if (!array_key_exists($field, $input)) {
            $errors[$field][] = 'The field is required.';
            return null;
        }

        $value = $input[$field];
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $errors[$field][] = 'The field must be an integer.';
        return null;
    }

    /**
     * @param mixed $value
     * @return array<int, mixed>
     */
    private function ensureArray($value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<int, string>
     */
    private function ensureStringArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item) {
            return is_string($item) ? trim($item) : null;
        }, $value), static fn ($item) => $item !== null && $item !== ''));
    }

    /**
     * @param mixed $value
     * @param array<string> $requiredFields
     * @return array<int, array<string, mixed>>
     */
    private function ensureArrayOfObjects($value, array $requiredFields = []): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (!is_array($item)) {
                continue;
            }
            $valid = true;
            foreach ($requiredFields as $field) {
                if (!isset($item[$field]) || trim((string) $item[$field]) === '') {
                    $valid = false;
                    break;
                }
            }
            if ($valid) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function assertSlug(string $value, string $field, array &$errors): string
    {
        $normalized = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9-]+$/', $normalized)) {
            $errors[$field][] = 'The field must contain lowercase letters, numbers, or hyphens only.';
        }

        return $normalized;
    }

    private function assertPath(string $value, string $field, array &$errors): string
    {
        $normalized = strtolower(trim($value));
        if (!preg_match('/^\/[a-z0-9-\/]*$/', $normalized)) {
            $errors[$field][] = 'The field must be a valid lowercase path.';
        }

        return $normalized;
    }

    private function assertEmail(string $value, string $field, array &$errors): string
    {
        $normalized = strtolower(trim($value));
        if (!filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            $errors[$field][] = 'The field must be a valid email address.';
        }

        return $normalized;
    }

    private function assertUrl(?string $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $trimmed = trim($value);
        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            $errors[$field][] = 'The field must be a valid URL.';
        }

        return $trimmed;
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function sanitizeNumericArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $unique = [];
        foreach ($value as $item) {
            $parsed = (int) $item;
            if ($parsed > 0) {
                $unique[$parsed] = $parsed;
            }
        }

        return array_values($unique);
    }


    /**
     * @param mixed $value
     * @return array<int, array{question: string, answer: string, sortOrder: int}>
     */
    private function sanitizeFaqs($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $faqs = [];
        foreach ($value as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = isset($item['question']) ? trim((string) $item['question']) : '';
            $answer = isset($item['answer']) ? trim((string) $item['answer']) : '';
            if ($question === '' || $answer === '') {
                continue;
            }

            $sortOrderRaw = $item['sortOrder'] ?? $index;
            $sortOrder = is_numeric($sortOrderRaw) ? (int) $sortOrderRaw : (int) $index;

            $faqs[] = [
                'question' => $question,
                'answer' => $answer,
                'sortOrder' => $sortOrder,
            ];
        }

        usort($faqs, static fn (array $a, array $b) => $a['sortOrder'] <=> $b['sortOrder']);

        return $faqs;
    }
}
