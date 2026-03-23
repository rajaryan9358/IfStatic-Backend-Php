<?php

declare(strict_types=1);

use App\Http\Exceptions\HttpException;
use App\Http\Exceptions\ValidationException;
use App\Http\Middleware\CorsMiddleware;
use App\Support\Env;
use Dotenv\Dotenv;
use Slim\Exception\HttpException as SlimHttpException;
use Slim\Factory\AppFactory;

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->add(new CorsMiddleware($app->getResponseFactory()));

$displayErrors = Env::bool('APP_DEBUG', false);
$errorMiddleware = $app->addErrorMiddleware($displayErrors, true, true);
$errorMiddleware->setDefaultErrorHandler(function ($request, \Throwable $exception) use ($app, $displayErrors) {
    $status = 500;
    $message = 'An unexpected error occurred.';
    $details = [];

    if ($exception instanceof ValidationException || $exception instanceof HttpException) {
        $status = $exception->getCode() ?: $exception->getStatusCode();
        $message = $exception->getMessage();
        if (method_exists($exception, 'getDetails')) {
            $details = $exception->getDetails();
        }
    } elseif ($exception instanceof SlimHttpException) {
        $status = $exception->getCode() ?: 500;
        $message = $exception->getMessage();
    }

    if ($displayErrors && $status === 500) {
        $details['trace'] = $exception->getTraceAsString();
    }

    $response = $app->getResponseFactory()->createResponse($status);
    $payload = ['status' => 'error', 'message' => $message];
    if (!empty($details)) {
        $payload['details'] = $details;
    }
    $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json');
});

(require $root . '/routes/api.php')($app);

return $app;
