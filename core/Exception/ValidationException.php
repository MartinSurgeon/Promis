<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Throwable;

/**
 * 422 Unprocessable Entity - Validation / Constraint Exception.
 */
class ValidationException extends AppException
{
    protected array $errors = [];

    public function __construct(string $message = "The given data was invalid.", array $errors = [], ?Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, 422, ['errors' => $errors], $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
