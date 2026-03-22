<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\ContactQueryModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class ContactController extends Controller
{
    use ValidatesRequest;

    private const STATUS_VALUES = ['new', 'in-progress', 'resolved'];

    private ContactQueryModel $contacts;

    public function __construct(?ContactQueryModel $contacts = null)
    {
        $this->contacts = $contacts ?? new ContactQueryModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        $status = $request->getQueryParams()['status'] ?? null;
        $status = is_string($status) ? trim($status) : '';
        $data = $this->contacts->list(['status' => $status ?: null]);

        return $this->respond($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateContactPayload($request->getParsedBody() ?? []);
        $query = $this->contacts->createQuery($payload);

        return $this->respond($response, ['data' => $query], 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid contact query identifier', 400);
        }
        $payload = $this->validateStatusPayload($request->getParsedBody() ?? []);
        $updated = $this->contacts->updateStatus($id, $payload['status']);
        if (!$updated) {
            throw new HttpException('Contact query not found', 404);
        }

        return $this->respond($response, ['data' => $updated]);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateContactPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $payload = [];

        $name = $this->requireString($raw, 'name', $errors);
        if ($name !== null) {
          $payload['name'] = $name;
        }

        $email = $this->requireString($raw, 'email', $errors);
        if ($email !== null) {
          $payload['email'] = $this->assertEmail($email, 'email', $errors);
        }

        $message = $this->requireString($raw, 'message', $errors);
        if ($message !== null) {
          $payload['message'] = $message;
        }

        $payload['subject'] = $this->optionalString($raw, 'subject', $errors, true) ?? '';
        $payload['phone'] = $this->optionalString($raw, 'phone', $errors, true) ?? '';
        $payload['source'] = $this->optionalString($raw, 'source', $errors, true) ?? '';

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return $payload;
    }

    /**
     * @param mixed $raw
     * @return array{status: string}
     */
    private function validateStatusPayload($raw): array
    {
        if (!is_array($raw)) {
            throw new ValidationException(['payload' => ['Request body must be a JSON object.']]);
        }

        $errors = [];
        $status = $this->requireString($raw, 'status', $errors);
        if ($status !== null && !in_array($status, self::STATUS_VALUES, true)) {
            $errors['status'][] = 'Status must be one of: ' . implode(', ', self::STATUS_VALUES);
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        return ['status' => $status];
    }
}
