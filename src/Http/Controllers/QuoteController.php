<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Database\Database;
use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Models\QuoteRequestModel;
use App\Validation\Concerns\ValidatesRequest;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class QuoteController extends Controller
{
    use ValidatesRequest;

    private const STATUS_VALUES = ['new', 'reviewing', 'closed'];

    private QuoteRequestModel $quotes;

    public function __construct(?QuoteRequestModel $quotes = null)
    {
        $this->quotes = $quotes ?? new QuoteRequestModel(Database::connection());
    }

    public function index(Request $request, Response $response): Response
    {
        $status = $request->getQueryParams()['status'] ?? null;
        $status = is_string($status) ? trim($status) : '';
        $data = $this->quotes->list(['status' => $status ?: null]);

        return $this->respond($response, ['data' => $data]);
    }

    public function store(Request $request, Response $response): Response
    {
        $payload = $this->validateQuotePayload($request->getParsedBody() ?? []);
        $quote = $this->quotes->createRequest($payload);

        return $this->respond($response, ['data' => $quote], 201);
    }

    public function updateStatus(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        if ($id <= 0) {
            throw new HttpException('Invalid quote request identifier', 400);
        }
        $payload = $this->validateStatusPayload($request->getParsedBody() ?? []);
        $updated = $this->quotes->updateStatus($id, $payload['status']);
        if (!$updated) {
            throw new HttpException('Quote request not found', 404);
        }

        return $this->respond($response, ['data' => $updated]);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    private function validateQuotePayload($raw): array
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

        $payload['phone'] = $this->optionalString($raw, 'phone', $errors, true);
        $payload['service'] = $this->optionalString($raw, 'service', $errors, true);
        $payload['appType'] = $this->optionalString($raw, 'appType', $errors, true);

        $projectDetails = $this->requireString($raw, 'projectDetails', $errors, false);
        if ($projectDetails !== null) {
            $payload['projectDetails'] = $projectDetails;
        }

        $contactMethod = $this->optionalString($raw, 'contactMethod', $errors, true);
        if ($contactMethod !== null && $contactMethod !== '') {
            $allowedMethods = ['email', 'phone', 'whatsapp'];
            if (!in_array($contactMethod, $allowedMethods, true)) {
                $errors['contactMethod'][] = 'Invalid contact method.';
            } else {
                $payload['contactMethod'] = $contactMethod;
            }
        }

        $payload['source'] = $this->optionalString($raw, 'source', $errors, true);

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
