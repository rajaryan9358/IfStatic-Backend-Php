<?php

declare(strict_types=1);

namespace App\Http\Error;

use App\Http\Exceptions\HttpException;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Exception\HttpException as SlimHttpException;
use Slim\Interfaces\CallableResolverInterface;
use Slim\Middleware\ErrorMiddleware;
use Throwable;

final class JsonErrorHandler extends ErrorMiddleware
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ) {
        parent::__construct($callableResolver, $responseFactory, $displayErrorDetails, $logErrors, $logErrorDetails);
        $this->setDefaultErrorHandler(function ($request, Throwable $exception) use ($responseFactory, $displayErrorDetails) {
            $status = 500;
            $message = 'An unexpected error occurred.';
            $details = [];

            if ($exception instanceof HttpException) {
                $status = $exception->getStatusCode();
                $message = $exception->getMessage();
                $details = $exception->getDetails();
            } elseif ($exception instanceof SlimHttpException) {
                $status = $exception->getCode() ?: 500;
                $message = $exception->getMessage();
            }

            if ($displayErrorDetails && $status === 500) {
                $details['trace'] = $exception->getTraceAsString();
            }

            $response = $responseFactory->createResponse($status);
            $payload = [
                'status' => 'error',
                'message' => $message,
            ];

            if (!empty($details)) {
                $payload['details'] = $details;
            }

            $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        });
    }
}
