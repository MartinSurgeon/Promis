<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Throwable;

/**
 * 403 Forbidden - Authorization / Permission Exception.
 */
class AuthorizationException extends AppException
{
    public function __construct(string $message = "Forbidden. You do not have permission to perform this action.", ?Throwable $previous = null)
    {
        parent::__construct($message, 403, [], $previous);
    }
}
