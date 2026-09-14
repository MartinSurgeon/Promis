<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Auth;

use Promis\Core\Auth\Authorization;
use Promis\Core\Exception\AuthorizationException;
use Promis\Src\Planning\Domain\PlanningPermissions;

/**
 * Server-side authorization guard enforcing RBAC permissions and entity-scoped boundaries
 * for all Procurement Planning operations.
 */
final class PlanAuthorizationGuard
{
    public static function canViewPlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::VIEW, $planningEntityId);
    }

    public static function requireCanViewPlan(int $planningEntityId): void
    {
        if (!self::canViewPlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to view procurement plans for this entity.");
        }
    }

    public static function canCreatePlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::CREATE, $planningEntityId);
    }

    public static function requireCanCreatePlan(int $planningEntityId): void
    {
        if (!self::canCreatePlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to formulate a procurement plan for this entity.");
        }
    }

    public static function canEditPlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::EDIT, $planningEntityId);
    }

    public static function requireCanEditPlan(int $planningEntityId): void
    {
        if (!self::canEditPlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to modify this procurement plan.");
        }
    }

    public static function canSubmitPlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::SUBMIT, $planningEntityId);
    }

    public static function requireCanSubmitPlan(int $planningEntityId): void
    {
        if (!self::canSubmitPlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to submit this procurement plan for approval.");
        }
    }

    public static function canReviewPlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::REVIEW, $planningEntityId);
    }

    public static function requireCanReviewPlan(int $planningEntityId): void
    {
        if (!self::canReviewPlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to conduct quarterly plan reviews for this entity.");
        }
    }

    public static function canApprovePlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::APPROVE, $planningEntityId);
    }

    public static function requireCanApprovePlan(int $planningEntityId): void
    {
        if (!self::canApprovePlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have approval authority for this procurement plan.");
        }
    }

    public static function canRevisePlan(int $planningEntityId): bool
    {
        return Authorization::allows(PlanningPermissions::REVISE, $planningEntityId);
    }

    public static function requireCanRevisePlan(int $planningEntityId): void
    {
        if (!self::canRevisePlan($planningEntityId)) {
            throw new AuthorizationException("Access denied. You do not have permission to revise this procurement plan.");
        }
    }
}
