<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\SettingsModel;
use App\Support\AuthToken;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController extends Controller
{
    private SettingsModel $settings;

    public function __construct(?SettingsModel $settings = null)
    {
        $this->settings = $settings ?? new SettingsModel(Database::connection());
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $username = isset($body['username']) ? trim((string) $body['username']) : '';
        $password = isset($body['password']) ? (string) $body['password'] : '';
        $errors = [];

        if ($username === '') {
            $errors['username'][] = 'Username is required.';
        }

        if ($password === '') {
            $errors['password'][] = 'Password is required.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $settings = $this->settings->getRaw();
        $enabled = (bool) ($settings['admin_enabled'] ?? false);
        if (!$enabled) {
            throw new HttpException('Admin access is currently disabled.', 403);
        }

        $storedUsername = (string) ($settings['admin_username'] ?? '');
        $storedHash = (string) ($settings['admin_password_hash'] ?? '');
        if ($storedUsername === '' || $storedHash === '') {
            throw new HttpException('Admin credentials are not configured.', 400);
        }

        if (!hash_equals($storedUsername, $username)) {
            throw new HttpException('Invalid credentials.', 401);
        }

        if (!password_verify($password, $storedHash)) {
            throw new HttpException('Invalid credentials.', 401);
        }

        $token = AuthToken::issue(['sub' => 'admin']);

        return $this->respond($response, ['data' => ['token' => $token]]);
    }
}
