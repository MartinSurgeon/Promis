<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Exception;

/**
 * Thrown when an acting user attempts a workflow action outside their granted permission or entity scope.
 */
class UnauthorizedWorkflowActionException extends UnauthorizedExecutionException
{
}
