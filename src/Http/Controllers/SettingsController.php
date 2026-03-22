<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\SettingsModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class SettingsController extends Controller
{
    private SettingsModel $settings;

    public function __construct(?SettingsModel $settings = null)
    {
        $this->settings = $settings ?? new SettingsModel(Database::connection());
    }

    public function publicAdminSettings(Request $request, Response $response): Response
    {
        $settings = $this->settings->getPublicSettings();
        return $this->respond($response, ['data' => $settings]);
    }

    public function secureAdminSettings(Request $request, Response $response): Response
    {
        $settings = $this->settings->getSecureSettings();
        return $this->respond($response, ['data' => $settings]);
    }

    public function updateAdminSettings(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $enabled = array_key_exists('enabled', $body) ? (bool) $body['enabled'] : null;
        $username = array_key_exists('username', $body) ? trim((string) $body['username']) : null;
        $password = $body['password'] ?? null;
        $updates = [];
        $current = $this->settings->getRaw();
        $currentUsername = (string) ($current['admin_username'] ?? '');
        $currentPasswordHash = (string) ($current['admin_password_hash'] ?? '');

        if ($enabled !== null) {
            $updates['enabled'] = $enabled;
        }

        if ($username !== null) {
            if ($username === '') {
                $errors['username'][] = 'Username cannot be empty.';
            } else {
                $updates['username'] = $username;
            }
        }

        if ($password !== null) {
            $passwordString = (string) $password;
            if ($passwordString === '') {
                $errors['password'][] = 'Password cannot be empty.';
            } elseif (strlen($passwordString) < 8) {
                $errors['password'][] = 'Password must be at least 8 characters.';
            } else {
                $updates['passwordHash'] = password_hash($passwordString, PASSWORD_BCRYPT);
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $updated = $this->settings->updateAdminSettings($updates);

        $isEnabled = $enabled ?? (bool) ($current['admin_enabled'] ?? false);
        $finalUsername = $updates['username'] ?? $currentUsername;
        $finalPasswordHash = $updates['passwordHash'] ?? $currentPasswordHash;

        if ($isEnabled && $finalUsername === '') {
            throw new HttpException('Admin username must be set before enabling access.', 400);
        }

        if ($isEnabled && $finalPasswordHash === '') {
            throw new HttpException('Admin password must be set before enabling access.', 400);
        }

        return $this->respond($response, ['data' => $updated]);
    }
}
