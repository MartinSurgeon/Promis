<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Throwable;

/**
 * 401 Unauthorized - Authentication Exception.
 */
class AuthenticationException extends AppException
{
    public function __construct(string $message = "Unauthenticated or session expired.", ?Throwable $previous = null)
    {
        parent::__construct($message, 401, [], $previous);
    }
}
