<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Support\Env;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;

final class UploadController extends Controller
{
    private string $uploadDir;
    private array $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    public function __construct(?string $uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?? dirname(__DIR__, 3) . '/public/uploads';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }
    }

    public function uploadImage(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();
        $file = $files['image'] ?? null;
        if (!$file instanceof UploadedFileInterface) {
            throw new ValidationException(['image' => ['Image file is required.']]);
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new HttpException('Failed to upload image', 400);
        }

        $mime = $file->getClientMediaType() ?? '';
        if (!in_array($mime, $this->allowedMime, true)) {
            throw new ValidationException(['image' => ['Only image files are allowed.']]);
        }

        $maxSize = (int) (Env::get('UPLOAD_MAX_SIZE', (string) (5 * 1024 * 1024)) ?? 5242880);
        if ($file->getSize() !== null && $file->getSize() > $maxSize) {
            throw new HttpException('Image file is too large.', 413);
        }

        $clientFilename = $file->getClientFilename() ?? 'upload';
        $extension = strtolower(pathinfo($clientFilename, PATHINFO_EXTENSION));
        $baseName = pathinfo($clientFilename, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName) ?: 'upload';
        $finalName = $this->uniqueFilename($safeBase, $extension ?: 'png');

        $filePath = $this->uploadDir . DIRECTORY_SEPARATOR . $finalName;
        $file->moveTo($filePath);

        $baseUrl = rtrim(Env::get('APP_URL') ?? $this->detectBaseUrl($request), '/');
        $url = $baseUrl . '/uploads/' . $finalName;

        return $this->respond($response, [
            'url' => $url,
            'fileName' => $finalName,
            'size' => $file->getSize(),
        ], 201);
    }

    private function uniqueFilename(string $base, string $extension): string
    {
        $ext = $extension ? '.' . $extension : '';
        $candidate = $base . $ext;
        $counter = 1;

        while (file_exists($this->uploadDir . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = sprintf('%s-%d%s', $base, $counter, $ext);
            $counter++;
        }

        return $candidate;
    }

    private function detectBaseUrl(Request $request): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme() ?: 'http';
        $host = $uri->getHost() ?: 'localhost';
        $port = $uri->getPort();
        $portPart = $port ? ':' . $port : '';

        return sprintf('%s://%s%s', $scheme, $host, $portPart);
    }
}
