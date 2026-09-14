<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Auth;

use Promis\Core\Auth\Authorization;
use Promis\Src\Execution\Exception\UnauthorizedExecutionException;

/**
 * Server-side authorization guard enforcing RBAC permissions and entity-scoped boundaries
 * for all Procurement Execution and Requisition operations.
 */
final class ExecutionAuthorizationGuard
{
    public static function canCreateRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::CREATE, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_CREATE, $planningEntityId);
    }

    public static function requireCanCreateRequisition(int $planningEntityId): void
    {
        if (!self::canCreateRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have permission to create requisitions for entity #{$planningEntityId}."
            );
        }
    }

    public static function canViewRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::VIEW, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_VIEW, $planningEntityId);
    }

    public static function requireCanViewRequisition(int $planningEntityId): void
    {
        if (!self::canViewRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have permission to view requisitions for entity #{$planningEntityId}."
            );
        }
    }

    public static function canEditRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::EDIT, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_EDIT, $planningEntityId);
    }

    public static function requireCanEditRequisition(int $planningEntityId): void
    {
        if (!self::canEditRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have permission to edit this requisition."
            );
        }
    }

    public static function canSubmitRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::SUBMIT, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_SUBMIT, $planningEntityId);
    }

    public static function requireCanSubmitRequisition(int $planningEntityId): void
    {
        if (!self::canSubmitRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have permission to submit requisitions for approval."
            );
        }
    }

    public static function canEndorseRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::ENDORSE, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_ENDORSE, $planningEntityId);
    }

    public static function requireCanEndorseRequisition(int $planningEntityId): void
    {
        if (!self::canEndorseRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have permission to endorse requisitions."
            );
        }
    }

    public static function canApproveRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::APPROVE, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_APPROVE, $planningEntityId);
    }

    public static function requireCanApproveRequisition(int $planningEntityId): void
    {
        if (!self::canApproveRequisition($planningEntityId)) {
            throw new UnauthorizedExecutionException(
                "Access denied. You do not have authority to approve this requisition."
            );
        }
    }

    public static function canReturnRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::RETURN_REQ, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_RETURN, $planningEntityId);
    }

    public static function requireCanReturnRequisition(int $planningEntityId): void
    {
        if (!self::canReturnRequisition($planningEntityId)) {
            throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException(
                "Access denied. You do not have permission to return requisitions for correction."
            );
        }
    }

    public static function canRejectRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::REJECT, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_REJECT, $planningEntityId);
    }

    public static function requireCanRejectRequisition(int $planningEntityId): void
    {
        if (!self::canRejectRequisition($planningEntityId)) {
            throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException(
                "Access denied. You do not have permission to reject requisitions."
            );
        }
    }

    public static function canReceiveRequisition(int $planningEntityId): bool
    {
        return Authorization::allows(ExecutionPermissions::RECEIVE, $planningEntityId)
            || Authorization::allows(ExecutionPermissions::ALIAS_RECEIVE, $planningEntityId);
    }

    public static function requireCanReceiveRequisition(int $planningEntityId): void
    {
        if (!self::canReceiveRequisition($planningEntityId)) {
            throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException(
                "Access denied. You do not have permission to receive requisitions into procurement."
            );
        }
    }

    public static function requireCanExecuteAction(\Promis\Src\Execution\Domain\WorkflowAction|string $action, int $planningEntityId): void
    {
        $actionEnum = $action instanceof \Promis\Src\Execution\Domain\WorkflowAction
            ? $action
            : \Promis\Src\Execution\Domain\WorkflowAction::tryFromString((string)$action);

        if ($actionEnum === null) {
            throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException("Invalid or unknown workflow action.");
        }

        switch ($actionEnum) {
            case \Promis\Src\Execution\Domain\WorkflowAction::SUBMIT:
                if (!self::canSubmitRequisition($planningEntityId)) {
                    throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException("Access denied. You do not have permission to submit requisitions for entity #{$planningEntityId}.");
                }
                break;
            case \Promis\Src\Execution\Domain\WorkflowAction::ENDORSE:
                if (!self::canEndorseRequisition($planningEntityId)) {
                    throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException("Access denied. You do not have permission to endorse requisitions for entity #{$planningEntityId}.");
                }
                break;
            case \Promis\Src\Execution\Domain\WorkflowAction::APPROVE:
                if (!self::canApproveRequisition($planningEntityId)) {
                    throw new \Promis\Src\Execution\Exception\UnauthorizedWorkflowActionException("Access denied. You do not have permission to approve requisitions for entity #{$planningEntityId}.");
                }
                break;
            case \Promis\Src\Execution\Domain\WorkflowAction::RETURN:
                self::requireCanReturnRequisition($planningEntityId);
                break;
            case \Promis\Src\Execution\Domain\WorkflowAction::REJECT:
                self::requireCanRejectRequisition($planningEntityId);
                break;
            case \Promis\Src\Execution\Domain\WorkflowAction::RECEIVE:
                self::requireCanReceiveRequisition($planningEntityId);
                break;
        }
    }
}
