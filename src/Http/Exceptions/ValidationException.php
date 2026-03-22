<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

class ValidationException extends HttpException
{
    public function __construct(array $errors)
    {
        parent::__construct('Invalid payload', 400, $errors);
    }
}
