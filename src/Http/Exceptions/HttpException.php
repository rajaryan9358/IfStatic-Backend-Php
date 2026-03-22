<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    protected int $status;
    protected array $details;

    public function __construct(string $message, int $status = 500, array $details = [])
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    /**
     * @return array<mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
