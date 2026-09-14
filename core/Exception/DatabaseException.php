<?php

declare(strict_types=1);

namespace Promis\Core\Exception;

use Throwable;

/**
 * 500 Database Exception wrapper protecting technical SQL details from client disclosure.
 */
class DatabaseException extends AppException
{
    public function __construct(string $message = "A database error occurred.", ?Throwable $previous = null)
    {
        parent::__construct($message, 500, [], $previous);
    }
}
