<?php

declare(strict_types=1);

namespace Promis\Src\Planning\Domain;

/**
 * Lifecycle statuses for Procurement Plan Versions (procurement_plan_versions.status).
 * Supports FR-049, FR-050.
 */
enum PlanVersionStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
    case SUPERSEDED = 'SUPERSEDED';

    /**
     * Determine if this version is an active approved baseline.
     */
    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Determine if this version can be revised or updated.
     */
    public function isDraft(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * All allowed status values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
