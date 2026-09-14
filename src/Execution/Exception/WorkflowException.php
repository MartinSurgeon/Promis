<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Exception;

use Promis\Core\Exception\AppException;

/**
 * Base exception for workflow definition, rule evaluation, and transition errors.
 */
class WorkflowException extends AppException
{
}
