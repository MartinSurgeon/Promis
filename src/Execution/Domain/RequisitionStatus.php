<?php

declare(strict_types=1);

namespace Promis\Src\Execution\Domain;

/**
 * Lifecycle statuses for Departmental Requisitions (requisitions.status).
 * Strictly mapped to confirmed physical schema definitions.
 */
enum RequisitionStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';
    case ENDORSED = 'ENDORSED';
    case DEPARTMENT_APPROVED = 'DEPARTMENT_APPROVED';
    case COMMITMENT_AUTHORIZED = 'COMMITMENT_AUTHORIZED';
    case PROCUREMENT_RECEIVED = 'PROCUREMENT_RECEIVED';
    case RETURNED = 'RETURNED';
    case REJECTED = 'REJECTED';

    /**
     * Check if the requisition is in an editable draft or returned state.
     */
    public function isEditable(): bool
    {
        return match ($this) {
            self::DRAFT, self::RETURNED => true,
            default => false,
        };
    }

    /**
     * Check if the status represents a terminal state.
     */
    public function isTerminal(): bool
    {
        return $this === self::REJECTED;
    }

    /**
     * Institutional human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Request Sent (Waiting for Department)',
            self::ENDORSED => 'Department Recommended (Waiting for Faculty)',
            self::DEPARTMENT_APPROVED => 'Faculty Approved (Waiting for Finance)',
            self::COMMITMENT_AUTHORIZED => 'Approved for Purchase (In Purchase Processing)',
            self::PROCUREMENT_RECEIVED => 'Items Received',
            self::RETURNED => 'Returned for Changes',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Concise status title for badges and compact displays.
     */
    public function title(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Request Sent',
            self::ENDORSED => 'Department Recommended',
            self::DEPARTMENT_APPROVED => 'Faculty Approved',
            self::COMMITMENT_AUTHORIZED => 'Approved for Purchase',
            self::PROCUREMENT_RECEIVED => 'Items Received',
            self::RETURNED => 'Returned',
            self::REJECTED => 'Rejected',
        };
    }

    /**
     * Sub-label or governance stage explanation for detailed status context.
     */
    public function sublabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft Request',
            self::SUBMITTED => 'Waiting for Department Review',
            self::ENDORSED => 'Waiting for Faculty Approval',
            self::DEPARTMENT_APPROVED => 'Waiting for Finance Approval',
            self::COMMITMENT_AUTHORIZED => 'Waiting for Purchase Processing',
            self::PROCUREMENT_RECEIVED => 'Items Received & Completed',
            self::RETURNED => 'Needs Changes by Requester',
            self::REJECTED => 'Request Closed',
        };
    }

    /**
     * Semantic CSS badge variant class.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'draft',
            self::SUBMITTED => 'submitted',
            self::ENDORSED => 'endorsed',
            self::DEPARTMENT_APPROVED => 'approved',
            self::COMMITMENT_AUTHORIZED => 'committed',
            self::PROCUREMENT_RECEIVED => 'received',
            self::RETURNED => 'returned',
            self::REJECTED => 'rejected',
        };
    }

    /**
     * 1-based index in the canonical 6-stage forward governance pipeline.
     * Returns null for exceptional branches (RETURNED, REJECTED).
     */
    public function stepperIndex(): ?int
    {
        return match ($this) {
            self::DRAFT => 1,
            self::SUBMITTED => 2,
            self::ENDORSED => 3,
            self::DEPARTMENT_APPROVED => 4,
            self::COMMITMENT_AUTHORIZED => 5,
            self::PROCUREMENT_RECEIVED => 6,
            self::RETURNED, self::REJECTED => null,
        };
    }

    /**
     * Check if the status is an exceptional or negative decision branch.
     */
    public function isNegative(): bool
    {
        return match ($this) {
            self::RETURNED, self::REJECTED => true,
            default => false,
        };
    }

    /**
     * Centralized key-value list for UI filter dropdowns.
     *
     * @return array<string, string>
     */
    public static function filterList(): array
    {
        $list = ['ALL' => 'All Statuses'];
        foreach (self::cases() as $status) {
            $list[$status->value] = $status->label();
        }
        return $list;
    }
}
