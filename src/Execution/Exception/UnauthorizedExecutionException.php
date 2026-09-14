<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Exception;

use Promis\Core\Exception\AuthorizationException;

/**
 * Exception thrown when a user attempts an execution operation outside their granted scope.
 */
class UnauthorizedExecutionException extends AuthorizationException
{
}
