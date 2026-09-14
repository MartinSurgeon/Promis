<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Exception;
use Throwable;

/**
 * Base Application Checked Exception.
 */
class AppException extends Exception
{
    protected int $statusCode = 500;
    protected array $context = [];

    public function __construct(string $message = "", int $statusCode = 500, array $context = [], ?Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->context = $context;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
