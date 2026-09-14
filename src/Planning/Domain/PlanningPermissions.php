<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Technical permission identifiers for Procurement Planning domain.
 * Supports FR-002, FR-007, FR-010, FR-011, FR-049, FR-050.
 */
final class PlanningPermissions
{
    public const VIEW = 'procurement_plan.view';
    public const CREATE = 'procurement_plan.create';
    public const EDIT = 'procurement_plan.edit';
    public const SUBMIT = 'procurement_plan.submit';
    public const APPROVE = 'procurement_plan.approve';
    public const REJECT = 'procurement_plan.reject';
    public const RETURN_QUERY = 'procurement_plan.return';
    public const REVIEW = 'procurement_plan.review';
    public const REVISE = 'procurement_plan.revise';

    /**
     * All permissions defined for planning module.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::SUBMIT,
            self::APPROVE,
            self::REJECT,
            self::RETURN_QUERY,
            self::REVIEW,
            self::REVISE,
        ];
    }
}
