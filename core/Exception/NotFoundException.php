<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Throwable;

/**
 * 404 Not Found Exception.
 */
class NotFoundException extends AppException
{
    public function __construct(string $message = "Resource or endpoint not found.", ?Throwable $previous = null)
    {
        parent::__construct($message, 404, [], $previous);
    }
}
